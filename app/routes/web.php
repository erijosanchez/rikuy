<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Landing', [
        'phase' => 'Fase 1 — Auth + Tenancy',
    ]);
})->name('landing');

// Sandbox público: tenant demo, sin login y de solo lectura.
Route::get('/demo', [DashboardController::class, 'index'])
    ->middleware('tenant:demo')
    ->name('demo');

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
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
