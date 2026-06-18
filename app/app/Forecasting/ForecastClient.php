<?php

namespace App\Forecasting;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del microservicio Python de forecasting (FastAPI). Resiliente: si el
 * servicio no responde o devuelve algo inesperado, retorna null para que el
 * dashboard siga funcionando sin la proyección.
 */
class ForecastClient
{
    public function __construct(protected HttpFactory $http) {}

    /**
     * Proyecta `periods` meses sobre una serie [{ds: 'YYYY-MM', y: float}, ...].
     *
     * @param  array<int, array{ds: string, y: float}>  $series
     * @return array{model: string, confidence: float, forecast: array<int, array<string, mixed>>}|null
     */
    public function monthly(array $series, int $periods = 3, float $confidence = 0.80): ?array
    {
        // Hace falta un mínimo de historia para que la proyección signifique algo.
        if (count($series) < 4) {
            return null;
        }

        try {
            $response = $this->http
                ->timeout(config('services.forecast.timeout', 8))
                ->acceptJson()
                ->post($this->endpoint(), [
                    'series' => $series,
                    'periods' => $periods,
                    'confidence' => $confidence,
                ]);

            if ($response->failed()) {
                Log::warning('Forecast service respondió con error', ['status' => $response->status()]);

                return null;
            }

            $data = $response->json();

            return isset($data['forecast']) && is_array($data['forecast']) ? $data : null;
        } catch (\Throwable $e) {
            Log::warning('Forecast service inalcanzable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function endpoint(): string
    {
        return rtrim(config('services.forecast.url'), '/').'/forecast';
    }
}
