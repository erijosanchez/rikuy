<?php

namespace App\Assistant;

use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Cliente mínimo del Chat Completions de Groq (API compatible con OpenAI), con
 * soporte de function calling (tools). Solo transporta mensajes; la lógica del
 * loop de herramientas vive en DataAssistant.
 */
class GroqClient
{
    public function __construct(protected HttpFactory $http) {}

    public function isConfigured(): bool
    {
        return ! empty(config('services.groq.key'));
    }

    /**
     * Una ronda de chat. Devuelve el `message` del asistente (puede traer
     * tool_calls) o lanza si la API falla.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $tools): array
    {
        $response = $this->http
            ->withToken(config('services.groq.key'))
            ->timeout((int) config('services.groq.timeout', 20))
            ->acceptJson()
            ->post(rtrim(config('services.groq.url'), '/').'/chat/completions', [
                'model' => config('services.groq.model'),
                'temperature' => 0.1,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ])
            ->throw();

        return $response->json('choices.0.message', []);
    }
}
