<?php

namespace Tests\Feature;

use App\Analytics\OrderMetrics;
use App\Analytics\StarSchemaBuilder;
use App\Models\Dataset;
use App\Models\DatasetRow;
use App\Models\FactOrder;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsMetricsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dataset de prueba con números a mano (5 órdenes).
     */
    protected function seedFixture(Organization $org): Dataset
    {
        $dataset = Dataset::withoutGlobalScope('tenant')->create([
            'organization_id' => $org->id,
            'name' => 'Fixture',
            'status' => Dataset::STATUS_READY,
            'rows' => 5,
        ]);

        $rows = [
            ['fecha' => '2024-01-10', 'producto' => 'Laptop', 'monto' => 1000, 'cantidad' => 2, 'proveedor' => 'P1', 'entidad' => 'E1', 'region' => 'Lima'],
            ['fecha' => '2024-01-20', 'producto' => 'Mouse', 'monto' => 200, 'cantidad' => 5, 'proveedor' => 'P2', 'entidad' => 'E2', 'region' => 'Lima'],
            ['fecha' => '2024-02-05', 'producto' => 'Laptop', 'monto' => 1500, 'cantidad' => 3, 'proveedor' => 'P1', 'entidad' => 'E1', 'region' => 'Cusco'],
            ['fecha' => '2024-02-15', 'producto' => 'Teclado', 'monto' => 300, 'cantidad' => 4, 'proveedor' => 'P2', 'entidad' => 'E2', 'region' => 'Cusco'],
            ['fecha' => '2024-03-01', 'producto' => 'Laptop', 'monto' => 500, 'cantidad' => 1, 'proveedor' => 'P1', 'entidad' => 'E3', 'region' => 'Lima'],
        ];

        foreach ($rows as $i => $data) {
            DatasetRow::create([
                'dataset_id' => $dataset->id,
                'organization_id' => $org->id,
                'row_number' => $i + 1,
                'data' => $data,
            ]);
        }

        return $dataset;
    }

    public function test_builder_populates_facts_and_dimensions(): void
    {
        $org = Organization::factory()->create();
        $dataset = $this->seedFixture($org);

        app(StarSchemaBuilder::class)->build($dataset);

        $this->assertSame(5, FactOrder::withoutGlobalScope('tenant')->count());
        $this->assertSame(3, DB::table('dim_product')->where('organization_id', $org->id)->count()); // Laptop, Mouse, Teclado
        $this->assertSame(2, DB::table('dim_supplier')->where('organization_id', $org->id)->count()); // P1, P2
        $this->assertSame(2, DB::table('dim_region')->where('organization_id', $org->id)->count()); // Lima, Cusco
        $this->assertSame(5, DB::table('dim_date')->count()); // 5 fechas distintas
    }

    public function test_summary_matches_manual_calculation(): void
    {
        $org = Organization::factory()->create();
        app(StarSchemaBuilder::class)->build($this->seedFixture($org));

        $summary = OrderMetrics::for($org->id)->summary();

        $this->assertSame(3500.0, $summary['monto']);          // 1000+200+1500+300+500
        $this->assertSame(5, $summary['ordenes']);
        $this->assertSame(700.0, $summary['ticket_promedio']); // 3500 / 5
        $this->assertSame(15.0, $summary['unidades']);         // 2+5+3+4+1
    }

    public function test_top_products_ranking_and_share(): void
    {
        $org = Organization::factory()->create();
        app(StarSchemaBuilder::class)->build($this->seedFixture($org));

        $top = OrderMetrics::for($org->id)->topProducts(5);

        $this->assertSame('Laptop', $top[0]['producto']);
        $this->assertSame(1, $top[0]['ranking']);
        $this->assertSame(3000.0, $top[0]['monto']);           // 1000+1500+500
        $this->assertSame(85.7, $top[0]['participacion_pct']); // 3000/3500
        $this->assertSame('Teclado', $top[1]['producto']);
        $this->assertSame('Mouse', $top[2]['producto']);
    }

    public function test_monthly_trend_uses_window_functions(): void
    {
        $org = Organization::factory()->create();
        app(StarSchemaBuilder::class)->build($this->seedFixture($org));

        $trend = OrderMetrics::for($org->id)->monthlyTrend();

        $this->assertCount(3, $trend);

        // Enero
        $this->assertSame('2024-01', $trend[0]['period']);
        $this->assertSame(1200.0, $trend[0]['monto']);
        $this->assertSame(1200.0, $trend[0]['acumulado']);
        $this->assertNull($trend[0]['variacion_pct']);

        // Febrero: acumulado y variación (window functions)
        $this->assertSame(1800.0, $trend[1]['monto']);
        $this->assertSame(3000.0, $trend[1]['acumulado']);
        $this->assertSame(50.0, $trend[1]['variacion_pct']);   // (1800-1200)/1200

        // Marzo
        $this->assertSame(3500.0, $trend[2]['acumulado']);
        $this->assertSame(-72.2, $trend[2]['variacion_pct']);  // (500-1800)/1800
    }

    public function test_fact_total_equals_source_total(): void
    {
        $org = Organization::factory()->create();
        $dataset = $this->seedFixture($org);
        app(StarSchemaBuilder::class)->build($dataset);

        // Integridad: la suma del hecho cuadra con la fuente (dataset_rows).
        $factTotal = (float) FactOrder::withoutGlobalScope('tenant')->sum('monto');
        $sourceTotal = DatasetRow::withoutGlobalScope('tenant')
            ->where('dataset_id', $dataset->id)
            ->get()
            ->sum(fn ($r) => (float) $r->data['monto']);

        $this->assertSame($sourceTotal, $factTotal);
    }

    public function test_metrics_are_isolated_by_tenant(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        app(StarSchemaBuilder::class)->build($this->seedFixture($orgA));
        app(StarSchemaBuilder::class)->build($this->seedFixture($orgB));

        // Cada tenant ve solo su propio total, no la suma de ambos.
        $this->assertSame(3500.0, OrderMetrics::for($orgA->id)->summary()['monto']);
        $this->assertSame(3500.0, OrderMetrics::for($orgB->id)->summary()['monto']);
    }

    public function test_metrics_endpoint_returns_kpis(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        app(StarSchemaBuilder::class)->build($this->seedFixture($org));

        $this->actingAs($user)->getJson('/metrics')
            ->assertOk()
            ->assertJsonPath('summary.monto', fn ($v) => abs((float) $v - 3500.0) < 0.001)
            ->assertJsonPath('summary.ordenes', 5)
            ->assertJsonStructure(['summary', 'trend', 'topProducts', 'byRegion']);
    }

    public function test_demo_metrics_endpoint_is_public(): void
    {
        $demo = Organization::factory()->demo()->create(['slug' => 'demo']);
        app(StarSchemaBuilder::class)->build($this->seedFixture($demo));

        $this->getJson('/demo/metrics')
            ->assertOk()
            ->assertJsonPath('summary.ordenes', 5);
    }
}
