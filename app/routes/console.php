<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Evaluación diaria de reglas de alerta por tenant (Fase 5).
Schedule::command('rikuy:check-alerts')->dailyAt('06:00')->withoutOverlapping();
