<?php

namespace App\Analytics;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Capa de métricas/medidas sobre el esquema estrella, aislada por tenant.
 *
 * Acepta un filtro de periodo opcional (año): cuando se fija, todas las medidas
 * (KPIs, breakdowns, tendencia) se recortan a ese año vía la dimensión de fecha;
 * cuando es null, agregan sobre toda la historia. El KPI mensual usa window
 * functions (acumulado y variación intermensual) sobre mv_orders_monthly.
 */
class OrderMetrics
{
    public function __construct(
        protected int $organizationId,
        protected ?int $year = null,
        protected ?int $month = null,
    ) {}

    public static function for(int $organizationId, ?int $year = null): self
    {
        return new self($organizationId, $year);
    }

    /**
     * Devuelve una copia recortada al año indicado (o sin filtro si es null).
     */
    public function forYear(?int $year): self
    {
        return new self($this->organizationId, $year);
    }

    /**
     * Copia recortada a un periodo año/mes (cualquiera de los dos puede ser null).
     */
    public function forPeriod(?int $year, ?int $month): self
    {
        return new self($this->organizationId, $year, $month);
    }

    public function year(): ?int
    {
        return $this->year;
    }

    /**
     * Periodo (año/mes) más reciente con data; null si el tenant está vacío.
     */
    public function latestPeriod(): ?array
    {
        $row = DB::table('mv_orders_monthly')
            ->where('organization_id', $this->organizationId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'year' => (int) $row->year,
            'month' => (int) $row->month,
            'month_name' => $row->month_name,
            'period' => sprintf('%04d-%02d', $row->year, $row->month),
        ];
    }

    /**
     * KPIs de cabecera: total facturado, nº de órdenes, ticket promedio, unidades.
     */
    public function summary(): array
    {
        $row = $this->baseQuery()
            ->selectRaw('COUNT(*) AS ordenes, COALESCE(SUM(f.monto), 0) AS monto, COALESCE(SUM(f.cantidad), 0) AS unidades')
            ->first();

        $ordenes = (int) $row->ordenes;
        $monto = (float) $row->monto;

        return [
            'monto' => round($monto, 2),
            'ordenes' => $ordenes,
            'unidades' => round((float) $row->unidades, 2),
            'ticket_promedio' => $ordenes > 0 ? round($monto / $ordenes, 2) : 0.0,
        ];
    }

    /**
     * Serie mensual con acumulado y variación intermensual (window functions).
     * Si hay año fijado, la serie se recorta a sus meses.
     */
    public function monthlyTrend(): array
    {
        $query = DB::table('mv_orders_monthly')
            ->where('organization_id', $this->organizationId)
            ->selectRaw(
                'year, month, month_name, monto, ordenes, unidades, '
                .'SUM(monto) OVER (ORDER BY year, month) AS acumulado, '
                .'LAG(monto) OVER (ORDER BY year, month) AS monto_prev'
            )
            ->orderBy('year')
            ->orderBy('month');

        if ($this->year !== null) {
            $query->where('year', $this->year);
        }

        if ($this->month !== null) {
            $query->where('month', $this->month);
        }

        return $query->get()->map(function ($r) {
            $monto = (float) $r->monto;
            $prev = $r->monto_prev !== null ? (float) $r->monto_prev : null;

            return [
                'period' => sprintf('%04d-%02d', $r->year, $r->month),
                'year' => (int) $r->year,
                'month' => (int) $r->month,
                'month_name' => $r->month_name,
                'monto' => round($monto, 2),
                'ordenes' => (int) $r->ordenes,
                'unidades' => round((float) $r->unidades, 2),
                'acumulado' => round((float) $r->acumulado, 2),
                'variacion_pct' => ($prev !== null && $prev > 0)
                    ? round((($monto - $prev) / $prev) * 100, 1)
                    : null,
            ];
        })->all();
    }

    /**
     * Top productos por monto, con ranking y participación sobre el total.
     */
    public function topProducts(int $limit = 5): array
    {
        $rows = $this->baseQuery()
            ->join('dim_product as p', 'p.id', '=', 'f.product_id')
            ->groupBy('p.name')
            ->selectRaw('p.name AS producto, SUM(f.monto) AS monto, COUNT(*) AS ordenes')
            ->orderByDesc('monto')
            ->limit($limit)
            ->get();

        return $this->withShareAndRank($rows, 'producto');
    }

    /**
     * Monto por región, con participación sobre el total.
     */
    public function byRegion(): array
    {
        $rows = $this->baseQuery()
            ->leftJoin('dim_region as r', 'r.id', '=', 'f.region_id')
            ->groupBy('r.name')
            ->selectRaw("COALESCE(r.name, 'Sin región') AS region, SUM(f.monto) AS monto, COUNT(*) AS ordenes")
            ->orderByDesc('monto')
            ->get();

        return $this->withShareAndRank($rows, 'region');
    }

    /**
     * Años con data, de más reciente a más antiguo. Alimenta el filtro de periodo.
     */
    public function availableYears(): array
    {
        return DB::table('mv_orders_monthly')
            ->where('organization_id', $this->organizationId)
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    /**
     * Comparativo del periodo seleccionado contra el año anterior. Si no hay año
     * fijado, compara el año más reciente con data contra el previo. Devuelve
     * null cuando no hay ningún año con data.
     */
    public function comparison(): ?array
    {
        $years = $this->availableYears();

        if ($years === []) {
            return null;
        }

        $current = $this->year ?? $years[0];
        $previous = $current - 1;

        $actual = $this->yearTotals($current);
        $previo = $this->yearTotals($previous);

        $variacion = $previo['monto'] > 0
            ? round((($actual['monto'] - $previo['monto']) / $previo['monto']) * 100, 1)
            : null;

        return [
            'year_actual' => $current,
            'year_previo' => $previous,
            'tiene_previo' => in_array($previous, $years, true),
            'monto_actual' => $actual['monto'],
            'monto_previo' => $previo['monto'],
            'ordenes_actual' => $actual['ordenes'],
            'ordenes_previo' => $previo['ordenes'],
            'variacion_pct' => $variacion,
        ];
    }

    /**
     * Totales agregados de un año concreto desde la vista mensual.
     */
    protected function yearTotals(int $year): array
    {
        $row = DB::table('mv_orders_monthly')
            ->where('organization_id', $this->organizationId)
            ->where('year', $year)
            ->selectRaw('COALESCE(SUM(monto), 0) AS monto, COALESCE(SUM(ordenes), 0) AS ordenes')
            ->first();

        return [
            'monto' => round((float) $row->monto, 2),
            'ordenes' => (int) $row->ordenes,
        ];
    }

    /**
     * Query base del hecho, aislada por tenant y recortada al año si aplica.
     * El join a dim_date solo se añade cuando hay filtro de periodo.
     */
    protected function baseQuery(): Builder
    {
        $query = DB::table('fact_orders as f')
            ->where('f.organization_id', $this->organizationId);

        if ($this->year !== null || $this->month !== null) {
            $query->join('dim_date as d', 'd.id', '=', 'f.date_id');

            if ($this->year !== null) {
                $query->where('d.year', $this->year);
            }

            if ($this->month !== null) {
                $query->where('d.month', $this->month);
            }
        }

        return $query;
    }

    /**
     * Total del periodo activo, para calcular participaciones.
     */
    protected function totalMonto(): float
    {
        return (float) $this->baseQuery()->sum('f.monto');
    }

    /**
     * Añade ranking y % de participación a una colección agregada.
     */
    protected function withShareAndRank(Collection $rows, string $labelKey): array
    {
        $total = $this->totalMonto();
        $rank = 0;

        return $rows->map(function ($r) use ($total, $labelKey, &$rank) {
            $monto = (float) $r->monto;

            return [
                $labelKey => $r->{$labelKey},
                'monto' => round($monto, 2),
                'ordenes' => (int) $r->ordenes,
                'participacion_pct' => $total > 0 ? round($monto / $total * 100, 1) : 0.0,
                'ranking' => ++$rank,
            ];
        })->all();
    }
}
