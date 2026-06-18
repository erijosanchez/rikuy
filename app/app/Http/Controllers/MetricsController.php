<?php

namespace App\Http\Controllers;

use App\Analytics\OrderMetrics;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    /**
     * Endpoint de KPIs del tenant activo (usuario autenticado o sandbox demo).
     */
    public function index(TenantManager $tenants): JsonResponse
    {
        $metrics = OrderMetrics::for($tenants->current()->id);

        return response()->json([
            'summary' => $metrics->summary(),
            'trend' => $metrics->monthlyTrend(),
            'topProducts' => $metrics->topProducts(5),
            'byRegion' => $metrics->byRegion(),
        ]);
    }
}
