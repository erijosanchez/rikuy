<?php

namespace App\Assistant;

use Illuminate\Support\Facades\Log;

/**
 * Asistente de datos en lenguaje natural (Fase 7).
 *
 * Patrón RAG con function calling: el modelo (Groq) decide qué herramientas de
 * la capa de métricas llamar; nosotros las ejecutamos contra datos reales del
 * tenant y le devolvemos los resultados para que redacte la respuesta. El modelo
 * solo orquesta y redacta — los números salen siempre de las herramientas, así
 * que no fabrica respuestas.
 */
class DataAssistant
{
    /** Tope de rondas de tool-calling para acotar coste y evitar loops. */
    protected const MAX_STEPS = 5;

    public function __construct(protected GroqClient $groq) {}

    /**
     * Responde una pregunta del usuario sobre la data del tenant.
     *
     * @return array{ok: bool, answer: string, steps: array<int, mixed>, model: ?string}
     */
    public function ask(string $question, int $organizationId): array
    {
        if (! $this->groq->isConfigured()) {
            return $this->fail('El asistente no está configurado en este entorno (falta GROQ_API_KEY).');
        }

        $tools = new MetricTools($organizationId);
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $question],
        ];

        $steps = [];

        try {
            for ($i = 0; $i < self::MAX_STEPS; $i++) {
                $message = $this->groq->chat($messages, $tools->definitions());
                $toolCalls = $message['tool_calls'] ?? [];

                if (empty($toolCalls)) {
                    return [
                        'ok' => true,
                        'answer' => trim((string) ($message['content'] ?? '')),
                        'steps' => $steps,
                        'model' => config('services.groq.model'),
                    ];
                }

                // El mensaje del asistente con tool_calls debe volver tal cual.
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? '',
                    'tool_calls' => $toolCalls,
                ];

                foreach ($toolCalls as $call) {
                    $name = $call['function']['name'] ?? '';
                    $args = $this->decodeArgs($call['function']['arguments'] ?? '');
                    $result = $tools->has($name)
                        ? $tools->execute($name, $args)
                        : ['error' => "Herramienta desconocida: {$name}"];

                    $steps[] = ['tool' => $name, 'args' => $args, 'result' => $result];

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? '',
                        'name' => $name,
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }
            }

            // Se agotaron las rondas sin una respuesta final redactada.
            return [
                'ok' => true,
                'answer' => 'Consulté la data pero no pude resumir una respuesta. Reformula la pregunta, por favor.',
                'steps' => $steps,
                'model' => config('services.groq.model'),
            ];
        } catch (\Throwable $e) {
            Log::warning('Asistente de datos falló', ['error' => $e->getMessage()]);

            return $this->fail('El asistente no está disponible en este momento. Intenta de nuevo en un momento.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeArgs(string $arguments): array
    {
        if (trim($arguments) === '') {
            return [];
        }

        $decoded = json_decode($arguments, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
            Eres el asistente de datos de Rikuy, una plataforma de inteligencia comercial.
            Respondes en español, de forma breve y concreta, preguntas sobre las ventas del
            negocio del usuario.

            Reglas estrictas:
            - Usa SIEMPRE las herramientas para obtener cifras. Nunca inventes ni estimes
              números: si no llamaste a una herramienta, no afirmes una cifra.
            - Si la herramienta indica que no hay datos (vacío/sin_datos), dilo con claridad
              en vez de inventar.
            - Para "último mes", "mes pasado" o "más reciente", primero usa
              `periodo_reciente` y luego filtra por ese año y mes.
            - Los montos están en soles peruanos (PEN); muéstralos como "S/ 1,234".
            - No inventes productos, regiones ni periodos que no aparezcan en los resultados.
            PROMPT;
    }

    /**
     * @return array{ok: bool, answer: string, steps: array<int, mixed>, model: ?string}
     */
    protected function fail(string $message): array
    {
        return ['ok' => false, 'answer' => $message, 'steps' => [], 'model' => null];
    }
}
