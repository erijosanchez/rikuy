<?php

namespace App\Analytics;

use App\Models\Dataset;
use App\Models\DatasetRow;
use App\Models\FactOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transforma las filas canónicas de un dataset (dataset_rows) en el esquema
 * estrella: dimensiones (producto, proveedor, entidad, región, fecha) + hechos
 * (fact_orders). Idempotente por dataset. Tras construir, refresca la vista
 * materializada mensual en Postgres.
 */
class StarSchemaBuilder
{
    protected const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /** @var array<string, array<string, int>> caché nombre→id por dimensión */
    protected array $dims = [];

    /** @var array<string, int> caché fecha(Y-m-d)→id */
    protected array $dates = [];

    public function build(Dataset $dataset): void
    {
        $orgId = $dataset->organization_id;

        // Reconstrucción idempotente de los hechos de este dataset.
        FactOrder::withoutGlobalScope('tenant')->where('dataset_id', $dataset->id)->delete();

        $this->dims = [
            'dim_product' => $this->loadDim('dim_product', $orgId),
            'dim_supplier' => $this->loadDim('dim_supplier', $orgId),
            'dim_entity' => $this->loadDim('dim_entity', $orgId),
            'dim_region' => $this->loadDim('dim_region', $orgId),
        ];
        $this->dates = $this->loadDates();

        $buffer = [];

        DatasetRow::withoutGlobalScope('tenant')
            ->where('dataset_id', $dataset->id)
            ->orderBy('id')
            ->chunk(1000, function ($rows) use ($dataset, $orgId, &$buffer) {
                foreach ($rows as $row) {
                    $d = $row->data;

                    $fecha = $d['fecha'] ?? null;
                    $producto = $d['producto'] ?? null;
                    $monto = $d['monto'] ?? null;
                    $proveedor = $d['proveedor'] ?? null;

                    // Requeridos para un hecho válido; las demás filas se omiten.
                    if (! $fecha || ! $producto || $monto === null || ! $proveedor) {
                        continue;
                    }

                    $buffer[] = [
                        'organization_id' => $orgId,
                        'dataset_id' => $dataset->id,
                        'date_id' => $this->dateId($fecha),
                        'product_id' => $this->dimId('dim_product', $orgId, (string) $producto),
                        'supplier_id' => $this->dimId('dim_supplier', $orgId, (string) $proveedor),
                        'entity_id' => $this->optionalDimId('dim_entity', $orgId, $d['entidad'] ?? null),
                        'region_id' => $this->optionalDimId('dim_region', $orgId, $d['region'] ?? null),
                        'monto' => $monto,
                        'cantidad' => $d['cantidad'] ?? null,
                    ];

                    if (count($buffer) >= 500) {
                        FactOrder::insert($buffer);
                        $buffer = [];
                    }
                }
            });

        if ($buffer !== []) {
            FactOrder::insert($buffer);
        }

        $this->refreshMonthlyView();
    }

    /** @return array<string, int> */
    protected function loadDim(string $table, int $orgId): array
    {
        return DB::table($table)
            ->where('organization_id', $orgId)
            ->pluck('id', 'name')
            ->all();
    }

    /** @return array<string, int> */
    protected function loadDates(): array
    {
        return DB::table('dim_date')->pluck('id', 'date')->mapWithKeys(
            fn ($id, $date) => [substr((string) $date, 0, 10) => $id],
        )->all();
    }

    protected function dimId(string $table, int $orgId, string $name): int
    {
        $name = trim($name);

        if (isset($this->dims[$table][$name])) {
            return $this->dims[$table][$name];
        }

        $id = DB::table($table)->insertGetId([
            'organization_id' => $orgId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->dims[$table][$name] = $id;
    }

    protected function optionalDimId(string $table, int $orgId, mixed $name): ?int
    {
        if ($name === null || trim((string) $name) === '') {
            return null;
        }

        return $this->dimId($table, $orgId, (string) $name);
    }

    protected function dateId(string $fecha): int
    {
        $key = substr($fecha, 0, 10);

        if (isset($this->dates[$key])) {
            return $this->dates[$key];
        }

        $c = Carbon::parse($fecha);
        $id = DB::table('dim_date')->insertGetId([
            'date' => $c->toDateString(),
            'year' => $c->year,
            'quarter' => $c->quarter,
            'month' => $c->month,
            'month_name' => self::MESES[$c->month],
            'day' => $c->day,
        ]);

        return $this->dates[$key] = $id;
    }

    protected function refreshMonthlyView(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('REFRESH MATERIALIZED VIEW mv_orders_monthly');
        }
    }
}
