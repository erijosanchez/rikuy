<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Crea el tenant sandbox "demo" accesible sin login. Idempotente: puede correr
 * en cada arranque sin duplicar. La Fase 2 (rikuy:seed-demo) cargará aquí los
 * datos abiertos reales de PERÚ COMPRAS; por ahora son datasets de muestra.
 */
class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Organization::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'PERÚ COMPRAS (Demo)', 'is_demo' => true],
        );

        $samples = [
            ['name' => 'Órdenes de compra — Catálogos Electrónicos', 'rows' => 124_530],
            ['name' => 'Precios de mercados mayoristas', 'rows' => 38_902],
            ['name' => 'Proveedores adjudicados', 'rows' => 5_184],
        ];

        foreach ($samples as $sample) {
            Dataset::withoutGlobalScope('tenant')->updateOrCreate(
                ['organization_id' => $demo->id, 'name' => $sample['name']],
                ['status' => 'ready', 'rows' => $sample['rows']],
            );
        }
    }
}
