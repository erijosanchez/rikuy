<?php

namespace App\Http\Controllers;

use App\Analytics\OrderMetrics;
use App\Models\Dataset;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, TenantManager $tenants): Response
    {
        $organization = $tenants->current();

        // Dataset usa BelongsToTenant: la consulta ya viene aislada al tenant.
        $datasets = Dataset::orderBy('name')->get();

        // Filtro de periodo (Fase 4): año validado contra la data del tenant.
        $base = OrderMetrics::for($organization->id);
        $years = $base->availableYears();
        $selectedYear = $this->resolveYear($request, $years);

        $metrics = $base->forYear($selectedYear);

        return Inertia::render('Dashboard', [
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'is_demo' => $organization->is_demo,
            ],
            'datasets' => $datasets->map(fn (Dataset $dataset) => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'status' => $dataset->status,
                'rows' => $dataset->rows,
                'error' => $dataset->error,
            ]),
            'readOnly' => $tenants->isDemo(),
            'kpis' => $metrics->summary(),
            'trend' => $metrics->monthlyTrend(),
            'topProducts' => $metrics->topProducts(8),
            'byRegion' => $metrics->byRegion(),
            'comparison' => $metrics->comparison(),
            'filters' => [
                'years' => $years,
                'selectedYear' => $selectedYear,
            ],
        ]);
    }

    /**
     * Año pedido por query string, solo si existe en la data; null = "Todo".
     */
    protected function resolveYear(Request $request, array $years): ?int
    {
        $year = $request->integer('year');

        return ($year > 0 && in_array($year, $years, true)) ? $year : null;
    }
}
