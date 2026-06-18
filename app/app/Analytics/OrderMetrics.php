<?php

namespace App\Analytics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Capa de métricas/medidas sobre el esquema estrella, aislada por tenant.
 * El KPI mensual usa window functions (acumulado y variación intermensual)
 * sobre la vista materializada mv_orders_monthly.
 */
class OrderMetrics
{
    public function __construct(protected int $organizationId) {}

    public static function for(int $organizationId): self
    {
        return new self($organizationId);
    }

    /**
     * KPIs de cabecera: total facturado, nº de órdenes, ticket promedio, unidades.
     */
    public function summary(): array
    {
        $row = DB::table('fact_orders')
            ->where('organization_id', $this->organizationId)
            ->selectRaw('COUNT(*) AS ordenes, COALESCE(SUM(monto), 0) AS monto, COALESCE(SUM(cantidad), 0) AS unidades')
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
     */
    public function monthlyTrend(): array
    {
        $rows = DB::table('mv_orders_monthly')
            ->where('organization_id', $this->organizationId)
            ->selectRaw(
                'year, month, month_name, monto, ordenes, unidades, '
                .'SUM(monto) OVER (ORDER BY year, month) AS acumulado, '
                .'LAG(monto) OVER (ORDER BY year, month) AS monto_prev'
            )
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return $rows->map(function ($r) {
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
        $rows = DB::table('fact_orders as f')
            ->join('dim_product as p', 'p.id', '=', 'f.product_id')
            ->where('f.organization_id', $this->organizationId)
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
        $rows = DB::table('fact_orders as f')
            ->leftJoin('dim_region as r', 'r.id', '=', 'f.region_id')
            ->where('f.organization_id', $this->organizationId)
            ->groupBy('r.name')
            ->selectRaw("COALESCE(r.name, 'Sin región') AS region, SUM(f.monto) AS monto, COUNT(*) AS ordenes")
            ->orderByDesc('monto')
            ->get();

        return $this->withShareAndRank($rows, 'region');
    }

    /**
     * Total para calcular participaciones, sobre toda la tabla de hechos.
     */
    protected function totalMonto(): float
    {
        return (float) DB::table('fact_orders')
            ->where('organization_id', $this->organizationId)
            ->sum('monto');
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
