<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetRow;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatasetIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function csv(): string
    {
        return implode("\n", [
            'Fecha,Producto,Importe,Cantidad,Proveedor,Entidad,Region',
            '2024-03-01,Laptop Core i5,"S/ 4,500.00",2,Comercial Andina S.A.C.,Municipalidad de Lima,Lima',
            '2024-03-02,Impresora,1200.50,1,Tecnología del Pacífico S.A.,UGEL Cusco,Cusco',
        ]);
    }

    public function test_uploading_a_csv_creates_dataset_in_mapping_state(): void
    {
        $user = User::factory()->for(Organization::factory())->create();

        $file = UploadedFile::fake()->createWithContent('ordenes.csv', $this->csv());

        $response = $this->actingAs($user)->post('/datasets', [
            'file' => $file,
            'name' => 'Mis órdenes',
        ]);

        $dataset = Dataset::withoutGlobalScope('tenant')->firstOrFail();

        $response->assertRedirect("/datasets/{$dataset->id}/map");
        $this->assertSame(Dataset::STATUS_MAPPING, $dataset->status);
        $this->assertSame($user->organization_id, $dataset->organization_id);
        Storage::disk('local')->assertExists($dataset->file_path);
    }

    public function test_processing_a_mapped_csv_ingests_canonical_rows(): void
    {
        $user = User::factory()->for(Organization::factory())->create();
        $file = UploadedFile::fake()->createWithContent('ordenes.csv', $this->csv());
        $this->actingAs($user)->post('/datasets', ['file' => $file]);

        $dataset = Dataset::withoutGlobalScope('tenant')->firstOrFail();

        // La cola es 'sync' en tests: el job corre inline al despachar.
        $this->actingAs($user)->post("/datasets/{$dataset->id}/map", [
            'map' => [
                'fecha' => 'Fecha',
                'producto' => 'Producto',
                'monto' => 'Importe',
                'cantidad' => 'Cantidad',
                'proveedor' => 'Proveedor',
                'entidad' => 'Entidad',
                'region' => 'Region',
            ],
        ])->assertRedirect('/dashboard');

        $dataset->refresh();
        $this->assertSame(Dataset::STATUS_READY, $dataset->status);
        $this->assertSame(2, $dataset->rows);

        $rows = DatasetRow::withoutGlobalScope('tenant')
            ->where('dataset_id', $dataset->id)
            ->orderBy('row_number')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame($user->organization_id, $rows[0]->organization_id);

        // Normalización canónica: monto numérico (JSON no preserva .0 en
        // enteros exactos), fecha ISO, producto string.
        $this->assertEquals(4500, $rows[0]->data['monto']);
        $this->assertSame('2024-03-01', $rows[0]->data['fecha']);
        $this->assertSame('Laptop Core i5', $rows[0]->data['producto']);
        $this->assertSame(1200.5, $rows[1]->data['monto']);
    }

    public function test_mapping_requires_the_obligatory_fields(): void
    {
        $user = User::factory()->for(Organization::factory())->create();
        $file = UploadedFile::fake()->createWithContent('ordenes.csv', $this->csv());
        $this->actingAs($user)->post('/datasets', ['file' => $file]);
        $dataset = Dataset::withoutGlobalScope('tenant')->firstOrFail();

        // Falta 'monto' (obligatorio).
        $this->actingAs($user)->from("/datasets/{$dataset->id}/map")
            ->post("/datasets/{$dataset->id}/map", [
                'map' => ['fecha' => 'Fecha', 'producto' => 'Producto', 'proveedor' => 'Proveedor'],
            ])
            ->assertSessionHasErrors('map');

        $this->assertSame(Dataset::STATUS_MAPPING, $dataset->fresh()->status);
    }

    public function test_guest_cannot_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('ordenes.csv', $this->csv());
        $this->post('/datasets', ['file' => $file])->assertRedirect('/login');
    }

    public function test_seed_demo_command_loads_the_demo_tenant(): void
    {
        $this->artisan('rikuy:seed-demo')->assertSuccessful();

        $demo = Organization::where('slug', 'demo')->firstOrFail();
        $this->assertTrue($demo->is_demo);

        $dataset = Dataset::withoutGlobalScope('tenant')
            ->where('organization_id', $demo->id)
            ->where('name', 'Órdenes de compra — Catálogos Electrónicos')
            ->firstOrFail();

        $this->assertSame(Dataset::STATUS_READY, $dataset->status);
        $this->assertSame(180, $dataset->rows);
        $this->assertSame(180, DatasetRow::withoutGlobalScope('tenant')
            ->where('dataset_id', $dataset->id)->count());
    }
}
