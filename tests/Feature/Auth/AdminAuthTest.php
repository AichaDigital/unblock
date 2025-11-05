<?php

use App\Models\User;

test('admin is redirected to admin panel from unified dashboard', function () {
    config()->set('unblock.simple_mode.enabled', false);

    // Crear un usuario admin
    $admin = User::factory()->admin()->create();

    // Set last_activity to prevent session timeout redirect
    session()->put('last_activity', now()->timestamp);

    // Actuar como el admin - debe ser redirigido al panel de administración
    $response = $this
        ->actingAs($admin)
        ->get(route('dashboard'));

    // Verificar que es redirigido al panel de administración de Filament
    $response->assertRedirect(route('filament.admin.pages.dashboard'));
});

test('regular user can access unified dashboard', function () {
    // Crear un usuario normal
    $user = User::factory()->create([
        'is_admin' => false,
    ]);

    // Actuar como usuario normal
    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    // Verificar que puede acceder al dashboard unificado
    $response->assertOk()
        ->assertSeeLivewire('unified-dashboard');
});

test('guest cannot access dashboard and is redirected to login', function () {
    // Try to access without authentication
    $response = $this->get(route('dashboard'));

    // Verificar que es redirigido al login
    $response->assertRedirect(route('login'));
});

test('authenticated user is redirected to dashboard from home', function () {
    // Crear un usuario
    $user = User::factory()->create();

    // Actuar como usuario autenticado
    $response = $this
        ->actingAs($user)
        ->get('/'); // Ruta '/'

    // Verificar que es redirigido al dashboard
    $response->assertRedirect(route('dashboard'));
});

test('guest sees otp login form on home page', function () {
    // Access main page without authentication
    $response = $this->get('/'); // Ruta '/'

    // Verificar que ve el componente otp-login
    $response->assertOk()
        ->assertSeeLivewire('otp-login');
});
