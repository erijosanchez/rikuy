<?php

namespace App\Reports;

use App\Analytics\OrderMetrics;
use App\Models\Organization;
use Illuminate\Support\Carbon;

/**
 * Ensambla el contenido del reporte ejecutivo de un tenant a partir de la capa
 * de métricas (mismos números que el dashboard). Devuelve una estructura plana
 * lista para pintar en la vista imprimible.
 */
class ExecutiveReport
{
    public function __construct(protected Organization $organization) {}

    public static function for(Organization $organization): self
    {
        return new self($organization);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $metrics = OrderMetrics::for($this->organization->id);

        return [
            'organization' => $this->organization->name,
            'generated_at' => Carbon::now(),
            'latest_period' => $metrics->latestPeriod(),
            'kpis' => $metrics->summary(),
            'comparison' => $metrics->comparison(),
            'trend' => $metrics->monthlyTrend(),
            'top_products' => $metrics->topProducts(8),
            'by_region' => $metrics->byRegion(),
        ];
    }

    public function filename(): string
    {
        $slug = $this->organization->slug ?: 'rikuy';

        return sprintf('rikuy-reporte-%s-%s.pdf', $slug, Carbon::now()->format('Y-m-d'));
    }

    public function isEmpty(): bool
    {
        return ((int) OrderMetrics::for($this->organization->id)->summary()['ordenes']) === 0;
    }
}
