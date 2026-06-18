<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Microservicio Python de forecasting (Fase 6).
    'forecast' => [
        'url' => env('FORECAST_SERVICE_URL', 'http://forecast:8000'),
        'timeout' => (int) env('FORECAST_SERVICE_TIMEOUT', 8),
    ],

    // Asistente de datos NL vía Groq (function calling) — Fase 7.
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'url' => env('GROQ_API_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'timeout' => (int) env('GROQ_TIMEOUT', 20),
    ],

    // Reportes PDF con Browsershot (Chromium headless) — Fase 8.
    'browsershot' => [
        'enabled' => (bool) env('BROWSERSHOT_ENABLED', true),
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
        'no_sandbox' => (bool) env('BROWSERSHOT_NO_SANDBOX', true),
    ],

];
