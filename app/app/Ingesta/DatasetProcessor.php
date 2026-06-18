<?php

namespace App\Ingesta;

use App\Models\Dataset;
use App\Models\DatasetRow;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Toma un Dataset en estado "processing", lee su archivo aplicando el mapeo de
 * columnas y aterriza las filas normalizadas en dataset_rows. Es síncrono: el
 * job de cola lo invoca, y el seeder del demo también.
 */
class DatasetProcessor
{
    public function __construct(protected SpreadsheetReader $reader) {}

    public function process(Dataset $dataset): void
    {
        $dataset->forceFill([
            'status' => Dataset::STATUS_PROCESSING,
            'error' => null,
        ])->save();

        try {
            $path = $this->resolvePath($dataset);
            $map = $dataset->column_map ?? [];

            // Reproceso idempotente: limpia filas previas (sin scope de tenant
            // porque el job corre fuera de una request).
            DatasetRow::query()->where('dataset_id', $dataset->id)->delete();

            $count = 0;
            $buffer = [];
            $now = now()->toDateTimeString();

            $this->reader->eachRow($path, function (array $sourceRow, int $rowNumber) use (
                $dataset, $map, &$count, &$buffer, $now
            ) {
                $canonical = [];
                foreach ($map as $field => $header) {
                    if ($header === null || $header === '') {
                        continue;
                    }
                    $canonical[$field] = CanonicalSchema::cast($field, $sourceRow[$header] ?? null);
                }

                $buffer[] = [
                    'dataset_id' => $dataset->id,
                    'organization_id' => $dataset->organization_id,
                    'row_number' => $rowNumber,
                    'data' => json_encode($canonical, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                ];
                $count++;

                if (count($buffer) >= 500) {
                    DatasetRow::insert($buffer);
                    $buffer = [];
                }
            });

            if ($buffer !== []) {
                DatasetRow::insert($buffer);
            }

            $dataset->forceFill([
                'status' => Dataset::STATUS_READY,
                'rows' => $count,
                'processed_at' => now(),
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            $dataset->forceFill([
                'status' => Dataset::STATUS_FAILED,
                'error' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    protected function resolvePath(Dataset $dataset): string
    {
        $path = (string) $dataset->file_path;

        if ($path !== '' && is_file($path)) {
            return $path;
        }

        $absolute = Storage::disk('local')->path($path);

        if (! is_file($absolute)) {
            throw new \RuntimeException("Archivo del dataset no encontrado: {$path}");
        }

        return $absolute;
    }
}
