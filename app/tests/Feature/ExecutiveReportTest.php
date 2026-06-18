<?php

namespace Tests\Feature;

use App\Analytics\StarSchemaBuilder;
use App\Models\Dataset;
use App\Models\DatasetRow;
use App\Models\Organization;
use App\Models\User;
use App\Reports\ExecutiveReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveReportTest extends TestCase
{
    use RefreshDatabase;

    protected function seedFixture(Organization $org): void
    {
        $dataset = Dataset::withoutGlobalScope('tenant')->create([
            'organization_id' => $org->id,
            'name' => 'Fixture',
            'status' => Dataset::STATUS_READY,
            'rows' => 3,
        ]);

        $rows = [
            ['fecha' => '2024-01-10', 'producto' => 'Laptop', 'monto' => 1000, 'cantidad' => 2, 'proveedor' => 'P1', 'entidad' => 'E1', 'region' => 'Lima'],
            ['fecha' => '2024-02-10', 'producto' => 'Mouse', 'monto' => 1500, 'cantidad' => 3, 'proveedor' => 'P2', 'entidad' => 'E2', 'region' => 'Cusco'],
            ['fecha' => '2024-03-10', 'producto' => 'Teclado', 'monto' => 800, 'cantidad' => 4, 'proveedor' => 'P2', 'entidad' => 'E2', 'region' => 'Lima'],
        ];

        foreach ($rows as $i => $data) {
            DatasetRow::create([
                'dataset_id' => $dataset->id,
                'organization_id' => $org->id,
                'row_number' => $i + 1,
                'data' => $data,
            ]);
        }

        app(StarSchemaBuilder::class)->build($dataset);
    }

    public function test_report_assembles_metrics(): void
    {
        $org = Organization::factory()->create(['name' => 'ACME', 'slug' => 'acme']);
        $this->seedFixture($org);

        $report = ExecutiveReport::for($org);
        $data = $report->data();

        $this->assertSame('ACME', $data['organization']);
        $this->assertSame(3300.0, $data['kpis']['monto']);     // 1000+1500+800
        $this->assertSame('2024-03', $data['latest_period']['period']);
        $this->assertCount(3, $data['trend']);
        $this->assertSame('rikuy-reporte-acme-'.now()->format('Y-m-d').'.pdf', $report->filename());
        $this->assertFalse($report->isEmpty());
    }

    public function test_printable_view_renders_real_numbers(): void
    {
        $org = Organization::factory()->create(['name' => 'ACME']);
        $this->seedFixture($org);

        $html = view('reports.executive', ['report' => ExecutiveReport::for($org)->data()])->render();

        $this->assertStringContainsString('Reporte ejecutivo', $html);
        $this->assertStringContainsString('ACME', $html);
        $this->assertStringContainsString('S/ 3,300', $html);   // total facturado
        $this->assertStringContainsString('Teclado', $html);
    }

    public function test_authenticated_user_downloads_pdf(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->for($org)->create();
        $this->seedFixture($org);

        $response = $this->actingAs($user)->get('/report/executive.pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('rikuy-reporte-acme', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_demo_report_is_public(): void
    {
        $demo = Organization::factory()->demo()->create(['slug' => 'demo']);
        $this->seedFixture($demo);

        $response = $this->get('/demo/report/executive.pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_report_requires_auth(): void
    {
        $this->get('/report/executive.pdf')->assertRedirect('/login');
    }
}
