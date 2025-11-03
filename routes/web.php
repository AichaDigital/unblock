<?php

use App\Http\Controllers\{ErrorController, ReportController};
use Illuminate\Support\Facades\{Route};
use Livewire\Volt\Volt;

// Ruta principal '/' - Sistema OTP Login usando componente Livewire
Volt::route('/', 'otp-login')
    ->middleware('throttle:10,1')
    ->name('login');

// Rutas protegidas
Volt::route('dashboard', 'unified-dashboard')
    ->middleware(['auth', 'session.timeout', 'simple.mode'])
    ->name('dashboard');

// Rutas de utilidad
Route::get('/error/{code}', ErrorController::class)->name('error.show');
Route::get('/report/{id}', ReportController::class)->name('report.show');

// Simple Unblock Mode (conditional - only if enabled)
if (config('unblock.simple_mode.enabled')) {
    Route::get('/simple-unblock', \App\Livewire\SimpleUnblockForm::class)
        ->middleware(['auth', 'session.timeout', 'simple.mode', 'throttle.simple.unblock'])
        ->name('simple.unblock');
}

// Admin OTP Verification
Route::get('/admin/otp/verify', \App\Livewire\AdminOtpVerification::class)
    ->middleware(['web', 'auth'])
    ->name('admin.otp.verify');
