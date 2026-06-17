<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Landing', [
        'phase' => 'Fase 0 — Cimientos',
    ]);
})->name('landing');
