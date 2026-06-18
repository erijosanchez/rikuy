<?php

namespace App\Http\Controllers;

use App\Alerts\AlertEvaluator;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Notifications\AlertTriggered;
use App\Tenancy\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    /**
     * Reglas configuradas + últimos disparos del tenant activo. Los modelos usan
     * BelongsToTenant, así que las consultas ya vienen aisladas por tenant.
     */
    public function index(TenantManager $tenants): Response
    {
        $rules = AlertRule::orderByDesc('enabled')->orderBy('id')->get();

        $events = AlertEvent::with('rule:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return Inertia::render('Alerts', [
            'readOnly' => $tenants->isDemo(),
            'organization' => $tenants->current()->only(['name', 'slug', 'is_demo']),
            'rules' => $rules->map(fn (AlertRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'measure' => $r->measure,
                'direction' => $r->direction,
                'threshold_pct' => $r->threshold_pct,
                'enabled' => $r->enabled,
                'last_triggered_at' => $r->last_triggered_at?->toIso8601String(),
            ]),
            'events' => $events->map(fn (AlertEvent $e) => [
                'id' => $e->id,
                'rule' => $e->rule?->name,
                'period' => $e->period,
                'measure' => $e->measure,
                'observed' => $e->observed,
                'previous' => $e->previous,
                'change_pct' => $e->change_pct,
                'message' => $e->message,
                'created_at' => $e->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Crea una regla y la evalúa de inmediato contra el historial: si ya hay
     * periodos que la rompen, se registran y notifican en el acto (DoD: una regla
     * configurada dispara una notificación).
     */
    public function store(Request $request, TenantManager $tenants, AlertEvaluator $evaluator): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'measure' => ['required', Rule::in(AlertRule::MEASURES)],
            'direction' => ['required', Rule::in(AlertRule::DIRECTIONS)],
            'threshold_pct' => ['required', 'numeric', 'min:0.1', 'max:1000'],
        ]);

        $rule = AlertRule::create([
            'name' => ($data['name'] ?? null) ?: $this->defaultName($data),
            'measure' => $data['measure'],
            'direction' => $data['direction'],
            'threshold_pct' => $data['threshold_pct'],
            'enabled' => true,
        ]);

        $events = $evaluator->evaluateOrganization($rule->organization_id);

        foreach ($events as $event) {
            $tenants->current()->users->each->notify(new AlertTriggered($event));
        }

        $fired = $events->count();
        $status = $fired > 0
            ? "Regla creada — {$fired} alerta(s) disparada(s)."
            : 'Regla creada. Aún no hay periodos que la rompan.';

        return back()->with('status', $status);
    }

    /**
     * Activa/desactiva una regla.
     */
    public function update(Request $request, TenantManager $tenants, AlertRule $alert): RedirectResponse
    {
        $this->authorizeTenant($tenants, $alert);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $alert->update(['enabled' => $data['enabled']]);

        return back()->with('status', $data['enabled'] ? 'Regla activada.' : 'Regla pausada.');
    }

    public function destroy(TenantManager $tenants, AlertRule $alert): RedirectResponse
    {
        $this->authorizeTenant($tenants, $alert);

        $alert->delete();

        return back()->with('status', 'Regla eliminada.');
    }

    /**
     * Refuerza el aislamiento por tenant en el route-model binding: la
     * sustitución de modelos corre antes de que el middleware fije el tenant, así
     * que el global scope aún no filtra. Verificamos la propiedad explícitamente.
     */
    protected function authorizeTenant(TenantManager $tenants, AlertRule $alert): void
    {
        abort_unless($alert->organization_id === $tenants->current()?->id, 404);
    }

    /**
     * Nombre por defecto legible cuando el usuario no escribe uno.
     */
    protected function defaultName(array $data): string
    {
        $measure = $data['measure'] === 'ordenes' ? 'Órdenes' : 'Ventas';
        $dir = $data['direction'] === 'rise' ? 'suben' : 'caen';
        $pct = rtrim(rtrim(number_format((float) $data['threshold_pct'], 2), '0'), '.');

        return "{$measure} {$dir} ≥ {$pct}%";
    }
}
