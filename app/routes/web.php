<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Landing', [
        'phase' => 'Fase 4 — Dashboard ejecutivo',
    ]);
})->name('landing');

// Sandbox público: tenant demo, sin login y de solo lectura.
Route::middleware('tenant:demo')->group(function () {
    Route::get('/demo', [DashboardController::class, 'index'])->name('demo');
    Route::get('/demo/metrics', [MetricsController::class, 'index'])->name('demo.metrics');
});

// Invitados (no autenticados): registro y login.
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// Área autenticada: tenant resuelto desde el usuario.
Route::middleware(['auth', 'tenant:user'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/metrics', [MetricsController::class, 'index'])->name('metrics');

    // Ingesta de datasets (subida → mapeo → procesado en cola).
    Route::post('/datasets', [DatasetController::class, 'store'])->name('datasets.store');
    Route::get('/datasets/{dataset}/map', [DatasetController::class, 'map'])->name('datasets.map');
    Route::post('/datasets/{dataset}/map', [DatasetController::class, 'process'])->name('datasets.process');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
