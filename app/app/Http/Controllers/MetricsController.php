<?php

namespace App\Http\Controllers;

use App\Analytics\OrderMetrics;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    /**
     * Endpoint de KPIs del tenant activo (usuario autenticado o sandbox demo).
     * Acepta ?year para recortar las medidas a un periodo concreto.
     */
    public function index(Request $request, TenantManager $tenants): JsonResponse
    {
        $base = OrderMetrics::for($tenants->current()->id);
        $years = $base->availableYears();

        $year = $request->integer('year');
        $selectedYear = ($year > 0 && in_array($year, $years, true)) ? $year : null;

        $metrics = $base->forYear($selectedYear);

        return response()->json([
            'summary' => $metrics->summary(),
            'trend' => $metrics->monthlyTrend(),
            'topProducts' => $metrics->topProducts(5),
            'byRegion' => $metrics->byRegion(),
            'comparison' => $metrics->comparison(),
            'filters' => [
                'years' => $years,
                'selectedYear' => $selectedYear,
            ],
        ]);
    }
}
