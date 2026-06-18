<?php

namespace Tests\Feature;

use App\Alerts\AlertEvaluator;
use App\Analytics\StarSchemaBuilder;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Models\Dataset;
use App\Models\DatasetRow;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AlertTriggered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Serie con una caída fuerte en marzo (enero 1000 → febrero 1500 → marzo 300):
     * marzo cae 80% vs febrero; febrero sube 50% vs enero.
     */
    protected function seedTrend(Organization $org): void
    {
        $dataset = Dataset::withoutGlobalScope('tenant')->create([
            'organization_id' => $org->id,
            'name' => 'Fixture',
            'status' => Dataset::STATUS_READY,
            'rows' => 3,
        ]);

        $rows = [
            ['fecha' => '2024-01-10', 'producto' => 'Laptop', 'monto' => 1000, 'cantidad' => 2, 'proveedor' => 'P1', 'entidad' => 'E1', 'region' => 'Lima'],
            ['fecha' => '2024-02-10', 'producto' => 'Laptop', 'monto' => 1500, 'cantidad' => 3, 'proveedor' => 'P1', 'entidad' => 'E1', 'region' => 'Lima'],
            ['fecha' => '2024-03-10', 'producto' => 'Laptop', 'monto' => 300, 'cantidad' => 1, 'proveedor' => 'P1', 'entidad' => 'E1', 'region' => 'Lima'],
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

    protected function makeRule(Organization $org, array $overrides = []): AlertRule
    {
        return AlertRule::withoutGlobalScope('tenant')->create(array_merge([
            'organization_id' => $org->id,
            'name' => 'Caída de ventas',
            'measure' => 'monto',
            'direction' => 'drop',
            'threshold_pct' => 20,
            'enabled' => true,
        ], $overrides));
    }

    public function test_rule_drop_triggers_event_on_breaching_period(): void
    {
        $org = Organization::factory()->create();
        $this->seedTrend($org);
        $rule = $this->makeRule($org); // ventas caen >= 20%

        $events = app(AlertEvaluator::class)->evaluateOrganization($org->id);

        // Solo marzo rompe (cae 80%); febrero sube, no dispara una regla 'drop'.
        $this->assertCount(1, $events);
        $this->assertSame('2024-03', $events->first()->period);
        $this->assertSame(-80.0, $events->first()->change_pct);
        $this->assertNotNull($rule->fresh()->last_triggered_at);
    }

    public function test_evaluation_is_idempotent(): void
    {
        $org = Organization::factory()->create();
        $this->seedTrend($org);
        $this->makeRule($org);

        app(AlertEvaluator::class)->evaluateOrganization($org->id);
        $second = app(AlertEvaluator::class)->evaluateOrganization($org->id);

        $this->assertCount(0, $second);                 // no re-dispara
        $this->assertSame(1, AlertEvent::withoutGlobalScope('tenant')->count());
    }

    public function test_disabled_rule_does_not_trigger(): void
    {
        $org = Organization::factory()->create();
        $this->seedTrend($org);
        $this->makeRule($org, ['enabled' => false]);

        $events = app(AlertEvaluator::class)->evaluateOrganization($org->id);

        $this->assertCount(0, $events);
    }

    public function test_rise_direction_only_triggers_on_increase(): void
    {
        $org = Organization::factory()->create();
        $this->seedTrend($org);
        $this->makeRule($org, ['direction' => 'rise', 'threshold_pct' => 40]);

        $events = app(AlertEvaluator::class)->evaluateOrganization($org->id);

        // Febrero sube 50% (>=40) → dispara; marzo cae → no.
        $this->assertCount(1, $events);
        $this->assertSame('2024-02', $events->first()->period);
    }

    public function test_command_notifies_tenant_users(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $this->seedTrend($org);
        $this->makeRule($org);

        $this->artisan('rikuy:check-alerts')->assertSuccessful();

        Notification::assertSentTo($user, AlertTriggered::class);
    }

    public function test_creating_a_rule_via_endpoint_fires_and_notifies(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $this->seedTrend($org);

        // DoD: una regla configurada dispara una notificación.
        $this->actingAs($user)
            ->post('/alerts', [
                'measure' => 'monto',
                'direction' => 'drop',
                'threshold_pct' => 20,
            ])
            ->assertRedirect();

        $this->assertSame(1, AlertEvent::withoutGlobalScope('tenant')->where('organization_id', $org->id)->count());
        Notification::assertSentTo($user, AlertTriggered::class);
    }

    public function test_rules_are_isolated_by_tenant(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $userB = User::factory()->for($orgB)->create();
        $ruleA = $this->makeRule($orgA);

        // El usuario de B no puede tocar la regla de A (route binding aislado).
        $this->actingAs($userB)
            ->patch("/alerts/{$ruleA->id}", ['enabled' => false])
            ->assertNotFound();

        $this->assertTrue($ruleA->fresh()->enabled);
    }

    public function test_demo_alerts_page_is_public_and_read_only(): void
    {
        $demo = Organization::factory()->demo()->create(['slug' => 'demo']);
        $this->seedTrend($demo);
        $this->makeRule($demo);
        app(AlertEvaluator::class)->evaluateOrganization($demo->id);

        // Visible sin login.
        $this->get('/demo/alerts')->assertOk();

        // Y de solo lectura: el sandbox no expone ninguna superficie de escritura
        // de alertas (405: método no permitido en la ruta demo).
        $this->post('/demo/alerts', [])->assertStatus(405);
    }
}
