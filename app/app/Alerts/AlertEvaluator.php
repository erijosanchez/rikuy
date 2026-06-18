<?php

namespace App\Alerts;

use App\Analytics\OrderMetrics;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use Illuminate\Support\Collection;

/**
 * Contrasta las reglas de un tenant contra la serie mensual de métricas.
 *
 * Para cada regla recorre los pares de meses consecutivos y, donde la variación
 * intermensual de la medida rompe el umbral en la dirección configurada, crea un
 * AlertEvent. El registro es único por (regla, periodo): reejecutar no duplica
 * disparos, solo añade los periodos nuevos. Devuelve los eventos recién creados
 * para que el llamador decida a quién notificar.
 */
class AlertEvaluator
{
    /**
     * Evalúa todas las reglas activas de la organización.
     *
     * @return Collection<int, AlertEvent> eventos nuevos creados en esta corrida
     */
    public function evaluateOrganization(int $organizationId): Collection
    {
        $rules = AlertRule::withoutGlobalScope('tenant')
            ->where('organization_id', $organizationId)
            ->where('enabled', true)
            ->get();

        $trend = OrderMetrics::for($organizationId)->monthlyTrend();
        $new = collect();

        foreach ($rules as $rule) {
            $new = $new->merge($this->evaluateRule($rule, $trend));
        }

        return $new;
    }

    /**
     * Evalúa una regla contra una serie mensual ya calculada.
     *
     * @param  array<int, array<string, mixed>>  $trend  filas de OrderMetrics::monthlyTrend()
     * @return Collection<int, AlertEvent>
     */
    public function evaluateRule(AlertRule $rule, array $trend): Collection
    {
        $created = collect();

        for ($i = 1; $i < count($trend); $i++) {
            $current = $trend[$i];
            $previous = $trend[$i - 1];

            $observed = (float) $current[$rule->measure];
            $prior = (float) $previous[$rule->measure];

            if ($prior <= 0) {
                continue;
            }

            $changePct = round((($observed - $prior) / $prior) * 100, 2);

            if (! $this->breaches($rule, $changePct)) {
                continue;
            }

            $event = $this->record($rule, $current['period'], $observed, $prior, $changePct);

            if ($event !== null) {
                $created->push($event);
            }
        }

        if ($created->isNotEmpty()) {
            $rule->forceFill(['last_triggered_at' => now()])->save();
        }

        return $created;
    }

    /**
     * ¿La variación rompe el umbral en la dirección configurada?
     *  - drop: cayó al menos threshold% (variación <= -umbral).
     *  - rise: subió al menos threshold% (variación >= +umbral).
     */
    protected function breaches(AlertRule $rule, float $changePct): bool
    {
        return $rule->direction === 'rise'
            ? $changePct >= $rule->threshold_pct
            : $changePct <= -$rule->threshold_pct;
    }

    /**
     * Persiste el evento si no existe ya para ese (regla, periodo).
     */
    protected function record(AlertRule $rule, string $period, float $observed, float $previous, float $changePct): ?AlertEvent
    {
        $exists = AlertEvent::withoutGlobalScope('tenant')
            ->where('alert_rule_id', $rule->id)
            ->where('period', $period)
            ->exists();

        if ($exists) {
            return null;
        }

        return AlertEvent::create([
            'organization_id' => $rule->organization_id,
            'alert_rule_id' => $rule->id,
            'period' => $period,
            'measure' => $rule->measure,
            'observed' => round($observed, 2),
            'previous' => round($previous, 2),
            'change_pct' => $changePct,
            'message' => $this->message($rule, $period, $changePct),
        ]);
    }

    /**
     * Mensaje legible en español, p. ej.:
     * "Ventas caen 23.4% en 2024-03 (umbral 20%)."
     */
    protected function message(AlertRule $rule, string $period, float $changePct): string
    {
        return sprintf(
            '%s %s %s%% en %s (umbral %s%%).',
            $rule->measureLabel(),
            $rule->directionLabel(),
            number_format(abs($changePct), 1),
            $period,
            rtrim(rtrim(number_format($rule->threshold_pct, 2), '0'), '.'),
        );
    }
}
