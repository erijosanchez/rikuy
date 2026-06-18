<?php

namespace Tests\Feature;

use App\Analytics\StarSchemaBuilder;
use App\Forecasting\ForecastClient;
use App\Models\Dataset;
use App\Models\DatasetRow;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Respuesta de muestra del microservicio FastAPI.
     */
    protected function fakeForecast(): array
    {
        return [
            'model' => 'ets-trend',
            'history_points' => 6,
            'confidence' => 0.8,
            'forecast' => [
                ['ds' => '2024-07', 'yhat' => 1600.0, 'yhat_lower' => 1400.0, 'yhat_upper' => 1800.0],
                ['ds' => '2024-08', 'yhat' => 1700.0, 'yhat_lower' => 1450.0, 'yhat_upper' => 1950.0],
                ['ds' => '2024-09', 'yhat' => 1800.0, 'yhat_lower' => 1500.0, 'yhat_upper' => 2100.0],
            ],
        ];
    }

    protected function seedMonths(Organization $org, int $months = 6): void
    {
        $dataset = Dataset::withoutGlobalScope('tenant')->create([
            'organization_id' => $org->id,
            'name' => 'Fixture',
            'status' => Dataset::STATUS_READY,
            'rows' => $months,
        ]);

        for ($m = 1; $m <= $months; $m++) {
            DatasetRow::create([
                'dataset_id' => $dataset->id,
                'organization_id' => $org->id,
                'row_number' => $m,
                'data' => [
                    'fecha' => sprintf('2024-%02d-10', $m),
                    'producto' => 'Laptop',
                    'monto' => 1000 + 100 * $m,
                    'cantidad' => 2,
                    'proveedor' => 'P1',
                    'entidad' => 'E1',
                    'region' => 'Lima',
                ],
            ]);
        }

        app(StarSchemaBuilder::class)->build($dataset);
    }

    public function test_client_returns_null_with_too_little_history(): void
    {
        Http::fake();

        $result = app(ForecastClient::class)->monthly([
            ['ds' => '2024-01', 'y' => 100],
            ['ds' => '2024-02', 'y' => 120],
        ]);

        $this->assertNull($result);
        Http::assertNothingSent(); // ni siquiera llama al servicio
    }

    public function test_client_posts_series_and_parses_forecast(): void
    {
        Http::fake([
            '*/forecast' => Http::response($this->fakeForecast()),
        ]);

        $series = [];
        for ($m = 1; $m <= 6; $m++) {
            $series[] = ['ds' => sprintf('2024-%02d', $m), 'y' => 1000 + 100 * $m];
        }

        $result = app(ForecastClient::class)->monthly($series, periods: 3, confidence: 0.8);

        $this->assertSame('ets-trend', $result['model']);
        $this->assertCount(3, $result['forecast']);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/forecast')
            && $request['periods'] === 3
            && count($request['series']) === 6);
    }

    public function test_client_is_resilient_when_service_fails(): void
    {
        Http::fake(['*/forecast' => Http::response('boom', 500)]);

        $series = [];
        for ($m = 1; $m <= 6; $m++) {
            $series[] = ['ds' => sprintf('2024-%02d', $m), 'y' => 1000 + 100 * $m];
        }

        $this->assertNull(app(ForecastClient::class)->monthly($series));
    }

    public function test_dashboard_includes_forecast_when_service_responds(): void
    {
        Http::fake(['*/forecast' => Http::response($this->fakeForecast())]);

        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $this->seedMonths($org);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('forecast.model', 'ets-trend')
                ->has('forecast.points', 3));
    }

    public function test_dashboard_forecast_is_null_when_year_filter_active(): void
    {
        Http::fake(['*/forecast' => Http::response($this->fakeForecast())]);

        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $this->seedMonths($org);

        // Con un año fijado no se proyecta (la tendencia va recortada).
        $this->actingAs($user)
            ->get('/dashboard?year=2024')
            ->assertInertia(fn ($page) => $page->where('forecast', null));

        Http::assertNothingSent();
    }

    public function test_dashboard_survives_forecast_service_outage(): void
    {
        Http::fake(['*/forecast' => fn () => throw new \RuntimeException('connection refused')]);

        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $this->seedMonths($org);

        // El dashboard sigue renderizando aunque el servicio esté caído.
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('forecast', null));
    }
}
