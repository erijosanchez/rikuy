<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el tenant activo de la request y lo registra en el TenantManager.
 *
 *  - tenant:user  → la organización del usuario autenticado.
 *  - tenant:demo  → la organización sandbox (is_demo = true), accesible sin
 *                   login y forzada a solo-lectura (bloquea métodos de escritura).
 */
class IdentifyTenant
{
    public function __construct(protected TenantManager $tenants) {}

    public function handle(Request $request, Closure $next, string $source = 'user'): Response
    {
        if ($source === 'demo') {
            // El sandbox es de solo lectura. Única excepción: el asistente de
            // datos, que es POST pero solo CONSULTA la capa de métricas (no escribe).
            $isAssistant = $request->routeIs('demo.assistant.ask');

            abort_unless(
                $request->isMethodSafe() || $isAssistant,
                403,
                'El sandbox demo es de solo lectura.'
            );

            $organization = Organization::where('is_demo', true)->firstOrFail();
        } else {
            $user = $request->user();

            abort_if(
                $user === null || $user->organization === null,
                403,
                'No tienes una organización asignada.'
            );

            $organization = $user->organization;
        }

        $this->tenants->set($organization);

        return $next($request);
    }
}
