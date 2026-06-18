<?php

namespace App\Console\Commands;

use App\Ingesta\DatasetProcessor;
use App\Models\Dataset;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Carga el dataset demo de PERÚ COMPRAS (órdenes de compra de Catálogos
 * Electrónicos) al tenant demo. Idempotente.
 *
 * Por defecto usa el CSV de muestra bundleado en el repo, así el demo funciona
 * offline / en CI. Con --url descarga el CSV real de datosabiertos.gob.pe.
 */
class SeedDemo extends Command
{
    protected $signature = 'rikuy:seed-demo {--url= : URL del CSV de PERÚ COMPRAS (datosabiertos.gob.pe)}';

    protected $description = 'Carga el dataset demo de PERÚ COMPRAS al tenant demo (idempotente).';

    public function handle(DatasetProcessor $processor): int
    {
        $demo = Organization::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'PERÚ COMPRAS (Demo)', 'is_demo' => true],
        );

        $datasetName = 'Órdenes de compra — Catálogos Electrónicos';

        // El tenant demo es 100% gestionado por este comando: elimina cualquier
        // dataset que no administremos (p. ej. placeholders de fases previas).
        Dataset::withoutGlobalScope('tenant')
            ->where('organization_id', $demo->id)
            ->where('name', '!=', $datasetName)
            ->delete();

        $storedPath = 'datasets/demo/peru_compras_ordenes.csv';

        if ($url = $this->option('url')) {
            $this->info("Descargando desde {$url}…");
            $body = Http::timeout(180)->get($url)->throw()->body();
            Storage::disk('local')->put($storedPath, $body);
        } else {
            $sample = database_path('seeders/data/peru_compras_ordenes_sample.csv');
            Storage::disk('local')->put($storedPath, file_get_contents($sample));
        }

        // El CSV ya trae los nombres canónicos como encabezados → mapeo identidad.
        $map = [
            'fecha' => 'fecha',
            'producto' => 'producto',
            'monto' => 'monto',
            'cantidad' => 'cantidad',
            'proveedor' => 'proveedor',
            'entidad' => 'entidad',
            'region' => 'region',
        ];

        $dataset = Dataset::withoutGlobalScope('tenant')->updateOrCreate(
            ['organization_id' => $demo->id, 'name' => $datasetName],
            [
                'source' => 'seeder',
                'original_filename' => 'peru_compras_ordenes.csv',
                'file_path' => $storedPath,
                'column_map' => $map,
                'status' => Dataset::STATUS_PROCESSING,
            ],
        );

        $processor->process($dataset->refresh());

        $this->info("Tenant demo cargado: «{$dataset->name}» con {$dataset->fresh()->rows} filas.");

        return self::SUCCESS;
    }
}
