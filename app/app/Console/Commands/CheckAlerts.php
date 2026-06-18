<?php

namespace App\Console\Commands;

use App\Alerts\AlertEvaluator;
use App\Models\Organization;
use App\Notifications\AlertTriggered;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Evalúa las reglas de alerta de cada tenant contra su serie mensual y notifica
 * a sus usuarios por cada disparo nuevo. Idempotente (no re-notifica periodos ya
 * registrados). Pensado para correr en el scheduler; con --tenant evalúa uno solo.
 */
class CheckAlerts extends Command
{
    protected $signature = 'rikuy:check-alerts {--tenant= : Slug de una organización para evaluar solo esa}';

    protected $description = 'Evalúa las reglas de alerta y notifica los disparos nuevos.';

    public function handle(AlertEvaluator $evaluator): int
    {
        $organizations = Organization::query()
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->with('users')
            ->get();

        $totalEvents = 0;

        foreach ($organizations as $organization) {
            $events = $evaluator->evaluateOrganization($organization->id);

            if ($events->isEmpty()) {
                continue;
            }

            $totalEvents += $events->count();

            // Notifica a los usuarios del tenant (el demo no tiene usuarios: sus
            // disparos solo quedan en el log visible en /demo/alerts).
            foreach ($events as $event) {
                Notification::send($organization->users, new AlertTriggered($event));
            }

            $this->line("• {$organization->name}: {$events->count()} alerta(s) nueva(s).");
        }

        $this->info("Listo. {$totalEvents} alerta(s) disparada(s) en esta corrida.");

        return self::SUCCESS;
    }
}
