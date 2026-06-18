<?php

namespace App\Jobs;

use App\Ingesta\DatasetProcessor;
use App\Models\Dataset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Procesa un dataset subido en segundo plano (Horizon / cola redis).
 */
class ProcessDataset implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $datasetId) {}

    public function handle(DatasetProcessor $processor): void
    {
        // Sin scope de tenant en la cola: buscamos el dataset por id directo.
        $dataset = Dataset::query()->findOrFail($this->datasetId);

        $processor->process($dataset);
    }
}
