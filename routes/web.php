<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Ruta principal '/' - Sistema OTP Login usando componente Livewire
Volt::route('/', 'otp-login')
    ->middleware(['guest', 'throttle:10,1'])
    ->name('login');

// Rutas protegidas
Volt::route('dashboard', 'unified-dashboard')
    ->middleware(['auth', 'session.timeout', 'simple.mode'])
    ->name('dashboard');

// Rutas de utilidad
Route::get('/report/{id}', ReportController::class)->name('report.show');

// Simple Unblock Mode (always register route, middleware will handle access control)
Route::get('/simple-unblock', \App\Livewire\SimpleUnblockForm::class)
    ->middleware(['simple.mode.enabled', 'throttle.simple.unblock'])
    ->name('simple.unblock');

// Admin OTP Verification
Route::get('/admin/otp/verify', \App\Livewire\AdminOtpVerification::class)
    ->middleware(['web', 'auth'])
    ->name('admin.otp.verify');
