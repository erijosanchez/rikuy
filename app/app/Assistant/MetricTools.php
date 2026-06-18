<?php

namespace App\Assistant;

use App\Analytics\OrderMetrics;

/**
 * Catálogo de herramientas (function calling) que el asistente puede invocar.
 * Cada una se resuelve contra la capa de métricas (OrderMetrics), aislada por
 * tenant: el LLM nunca toca la base de datos ni inventa números, solo elige qué
 * herramienta llamar y con qué argumentos. La data devuelta es la fuente de la
 * respuesta — de ahí el "no fabrica respuestas" del DoD.
 */
class MetricTools
{
    public function __construct(protected int $organizationId) {}

    protected function metrics(): OrderMetrics
    {
        return OrderMetrics::for($this->organizationId);
    }

    /**
     * Definiciones en formato OpenAI/Groq (tools) con descripciones en español.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        $year = ['type' => 'integer', 'description' => 'Año a filtrar (p. ej. 2024). Omitir para toda la historia.'];
        $month = ['type' => 'integer', 'description' => 'Mes 1-12. Requiere también el año.'];

        return [
            $this->tool(
                'periodo_reciente',
                'Devuelve el periodo (año y mes) más reciente con datos. Úsalo cuando el usuario diga "último mes", "mes pasado" o "más reciente".',
            ),
            $this->tool(
                'resumen_ventas',
                'KPIs de ventas: total facturado, número de órdenes, ticket promedio y unidades. Opcionalmente filtrado por año/mes.',
                ['year' => $year, 'month' => $month],
            ),
            $this->tool(
                'top_productos',
                'Ranking de productos por monto facturado, con participación %. Opcionalmente filtrado por año/mes.',
                [
                    'limite' => ['type' => 'integer', 'description' => 'Cuántos productos devolver (por defecto 5).'],
                    'year' => $year,
                    'month' => $month,
                ],
            ),
            $this->tool(
                'ventas_por_region',
                'Monto facturado por región, con participación %. Opcionalmente filtrado por año/mes.',
                ['year' => $year, 'month' => $month],
            ),
            $this->tool(
                'tendencia_mensual',
                'Serie mensual de ventas (monto, órdenes, acumulado y variación intermensual) sobre toda la historia.',
            ),
            $this->tool(
                'comparar_anios',
                'Compara el año indicado (o el más reciente) contra el año anterior: montos y variación %.',
                ['year' => $year],
            ),
        ];
    }

    /**
     * Ejecuta una herramienta y devuelve su resultado como array serializable.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function execute(string $name, array $args): array
    {
        $year = isset($args['year']) ? (int) $args['year'] : null;
        $month = isset($args['month']) ? (int) $args['month'] : null;

        return match ($name) {
            'periodo_reciente' => $this->wrap($this->metrics()->latestPeriod() ?? ['vacio' => true]),
            'resumen_ventas' => $this->wrap($this->metrics()->forPeriod($year, $month)->summary()),
            'top_productos' => $this->wrap([
                'productos' => $this->metrics()->forPeriod($year, $month)
                    ->topProducts(max(1, min(20, (int) ($args['limite'] ?? 5)))),
            ]),
            'ventas_por_region' => $this->wrap(['regiones' => $this->metrics()->forPeriod($year, $month)->byRegion()]),
            'tendencia_mensual' => $this->wrap(['serie' => $this->metrics()->monthlyTrend()]),
            'comparar_anios' => $this->wrap($this->metrics()->forYear($year)->comparison() ?? ['sin_datos' => true]),
            default => ['error' => "Herramienta desconocida: {$name}"],
        };
    }

    public function has(string $name): bool
    {
        foreach ($this->definitions() as $def) {
            if ($def['function']['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected function tool(string $name, string $description, array $properties = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) $properties,
                    'required' => [],
                ],
            ],
        ];
    }

    /**
     * Anota la moneda para que el modelo no asuma la divisa.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function wrap(array $payload): array
    {
        return ['moneda' => 'PEN', ...$payload];
    }
}
