<?php

namespace Tests\Feature;

use App\Analytics\StarSchemaBuilder;
use App\Assistant\DataAssistant;
use App\Assistant\MetricTools;
use App\Models\Dataset;
use App\Models\DatasetRow;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tres meses; el último (2024-03) lo encabeza Teclado, no Laptop, para que
     * "top del último mes" sea distinto del top histórico.
     */
    protected function seedFixture(Organization $org): void
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
            ['fecha' => '2024-03-01', 'producto' => 'Teclado', 'monto' => 800, 'cantidad' => 4, 'proveedor' => 'P2', 'entidad' => 'E2', 'region' => 'Lima'],
            ['fecha' => '2024-03-12', 'producto' => 'Laptop', 'monto' => 500, 'cantidad' => 1, 'proveedor' => 'P1', 'entidad' => 'E3', 'region' => 'Lima'],
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

    // --- Respuestas simuladas del Chat Completions de Groq ---------------------

    protected function groqToolCall(string $name, array $args): array
    {
        return ['choices' => [['message' => [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'call_'.$name,
                'type' => 'function',
                'function' => ['name' => $name, 'arguments' => json_encode($args)],
            ]],
        ]]]];
    }

    protected function groqFinal(string $text): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]];
    }

    // --- MetricTools: datos reales (la fuente de la respuesta) -----------------

    public function test_latest_period_tool_returns_most_recent_month(): void
    {
        $org = Organization::factory()->create();
        $this->seedFixture($org);

        $result = (new MetricTools($org->id))->execute('periodo_reciente', []);

        $this->assertSame(2024, $result['year']);
        $this->assertSame(3, $result['month']);
        $this->assertSame('2024-03', $result['period']);
    }

    public function test_top_products_for_last_month_uses_real_data(): void
    {
        $org = Organization::factory()->create();
        $this->seedFixture($org);

        $result = (new MetricTools($org->id))->execute('top_productos', ['year' => 2024, 'month' => 3, 'limite' => 5]);

        $this->assertSame('Teclado', $result['productos'][0]['producto']);
        $this->assertSame(800.0, $result['productos'][0]['monto']);
        $this->assertSame('Laptop', $result['productos'][1]['producto']);
        $this->assertSame(500.0, $result['productos'][1]['monto']);
    }

    public function test_summary_tool_filters_by_month(): void
    {
        $org = Organization::factory()->create();
        $this->seedFixture($org);

        $result = (new MetricTools($org->id))->execute('resumen_ventas', ['year' => 2024, 'month' => 3]);

        $this->assertSame(1300.0, $result['monto']);   // 800 + 500
        $this->assertSame(2, $result['ordenes']);
    }

    // --- DataAssistant: orquestación del function calling (DoD) ----------------

    public function test_assistant_answers_top5_last_month_with_real_data(): void
    {
        config(['services.groq.key' => 'test-key']);

        // El modelo: 1) pide el periodo reciente, 2) pide el top de ese mes, 3) responde.
        Http::fake(['*/chat/completions' => Http::sequence()
            ->push($this->groqToolCall('periodo_reciente', []))
            ->push($this->groqToolCall('top_productos', ['year' => 2024, 'month' => 3, 'limite' => 5]))
            ->push($this->groqFinal('En el último mes (2024-03), el top fue Teclado (S/ 800) y Laptop (S/ 500).')),
        ]);

        $org = Organization::factory()->create();
        $this->seedFixture($org);

        $reply = app(DataAssistant::class)->ask('¿cuál fue el top 5 de productos del último mes?', $org->id);

        $this->assertTrue($reply['ok']);
        $this->assertStringContainsString('Teclado', $reply['answer']);

        // Trazabilidad: la respuesta se apoyó en herramientas con data real.
        $tools = array_column($reply['steps'], 'tool');
        $this->assertSame(['periodo_reciente', 'top_productos'], $tools);

        $topStep = $reply['steps'][1]['result'];
        $this->assertSame('Teclado', $topStep['productos'][0]['producto']);
        $this->assertSame(800.0, $topStep['productos'][0]['monto']);

        Http::assertSentCount(3);
    }

    public function test_assistant_disabled_without_api_key(): void
    {
        config(['services.groq.key' => null]);
        Http::fake();

        $org = Organization::factory()->create();
        $reply = app(DataAssistant::class)->ask('¿cuánto vendí?', $org->id);

        $this->assertFalse($reply['ok']);
        $this->assertStringContainsString('GROQ_API_KEY', $reply['answer']);
        Http::assertNothingSent();
    }

    public function test_assistant_is_resilient_when_groq_fails(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response('boom', 500)]);

        $org = Organization::factory()->create();
        $this->seedFixture($org);

        $reply = app(DataAssistant::class)->ask('¿cuánto vendí?', $org->id);

        $this->assertFalse($reply['ok']);
        $this->assertStringContainsString('no está disponible', $reply['answer']);
    }

    // --- Endpoints -------------------------------------------------------------

    public function test_endpoint_answers_for_authenticated_user(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response($this->groqFinal('Tus ventas totales son S/ 4,000.'))]);

        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $this->seedFixture($org);

        $this->actingAs($user)
            ->postJson('/assistant', ['question' => '¿cuánto vendí en total?'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('answer', 'Tus ventas totales son S/ 4,000.');
    }

    public function test_endpoint_validates_question(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();

        $this->actingAs($user)
            ->postJson('/assistant', ['question' => 'a'])
            ->assertStatus(422);
    }

    public function test_demo_assistant_is_public_despite_being_post(): void
    {
        config(['services.groq.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response($this->groqFinal('El demo vendió S/ 4,000.'))]);

        $demo = Organization::factory()->demo()->create(['slug' => 'demo']);
        $this->seedFixture($demo);

        // Página visible sin login.
        $this->get('/demo/assistant')->assertOk();

        // Y la consulta (POST) está permitida en el sandbox por ser solo lectura.
        $this->postJson('/demo/assistant', ['question' => '¿cuánto vendió el demo?'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
