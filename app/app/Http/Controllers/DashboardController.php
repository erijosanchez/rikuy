<?php

namespace App\Http\Controllers;

use App\Analytics\OrderMetrics;
use App\Forecasting\ForecastClient;
use App\Models\Dataset;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, TenantManager $tenants, ForecastClient $forecaster): Response
    {
        $organization = $tenants->current();

        // Dataset usa BelongsToTenant: la consulta ya viene aislada al tenant.
        $datasets = Dataset::orderBy('name')->get();

        // Filtro de periodo (Fase 4): año validado contra la data del tenant.
        $base = OrderMetrics::for($organization->id);
        $years = $base->availableYears();
        $selectedYear = $this->resolveYear($request, $years);

        $metrics = $base->forYear($selectedYear);
        $trend = $metrics->monthlyTrend();

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
            'trend' => $trend,
            'topProducts' => $metrics->topProducts(8),
            'bySupplier' => $metrics->bySupplier(8),
            'byRegion' => $metrics->byRegion(),
            'byEntity' => $metrics->byEntity(8),
            'comparison' => $metrics->comparison(),
            'forecast' => $this->forecast($forecaster, $selectedYear, $trend),
            'filters' => [
                'years' => $years,
                'selectedYear' => $selectedYear,
            ],
        ]);
    }

    /**
     * Proyección del KPI principal (monto) sobre la serie completa. Solo en la
     * vista "Todo": con un año fijado la tendencia va recortada y proyectar más
     * allá de ese año no tendría sentido. Resiliente: null si el servicio falla.
     *
     * @param  array<int, array<string, mixed>>  $trend
     * @return array{model: string, confidence: float, points: array<int, mixed>}|null
     */
    protected function forecast(ForecastClient $forecaster, ?int $selectedYear, array $trend): ?array
    {
        if ($selectedYear !== null) {
            return null;
        }

        $series = array_map(
            fn (array $row) => ['ds' => $row['period'], 'y' => $row['monto']],
            $trend,
        );

        $result = $forecaster->monthly($series, periods: 3, confidence: 0.80);

        if ($result === null) {
            return null;
        }

        return [
            'model' => $result['model'] ?? 'unknown',
            'confidence' => $result['confidence'] ?? 0.80,
            'points' => $result['forecast'],
        ];
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
