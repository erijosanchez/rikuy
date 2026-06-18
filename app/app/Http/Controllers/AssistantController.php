<?php

namespace App\Http\Controllers;

use App\Assistant\DataAssistant;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    /**
     * Página de chat del asistente (Inertia).
     */
    public function show(TenantManager $tenants): Response
    {
        return Inertia::render('Assistant', [
            'readOnly' => $tenants->isDemo(),
            'organization' => $tenants->current()->only(['name', 'slug', 'is_demo']),
            'enabled' => ! empty(config('services.groq.key')),
            'suggestions' => [
                '¿Cuál fue el top 5 de productos del último mes?',
                '¿Cómo van las ventas este año vs. el anterior?',
                '¿Qué región factura más?',
                'Dame el resumen de ventas del último mes.',
            ],
        ]);
    }

    /**
     * Responde una pregunta en lenguaje natural con números reales (JSON).
     * Solo lee la capa de métricas → seguro de exponer también en el sandbox demo.
     */
    public function ask(Request $request, TenantManager $tenants, DataAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $reply = $assistant->ask($data['question'], $tenants->current()->id);

        return response()->json($reply);
    }
}
