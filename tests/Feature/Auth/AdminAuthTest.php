<?php

use App\Models\User;

test('admin can access unified dashboard', function () {
    // Crear un usuario admin
    $admin = User::factory()->admin()->create();

    // Actuar como el admin
    $response = $this
        ->actingAs($admin)
        ->get(route('dashboard'));

    // Verificar que puede acceder y ve el dashboard unificado
    $response->assertOk()
        ->assertSeeLivewire('unified-dashboard');
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
        ->get(route('login')); // Ruta '/'

    // Verificar que es redirigido al dashboard
    $response->assertRedirect(route('dashboard'));
});

test('guest sees otp login form on home page', function () {
    // Access main page without authentication
    $response = $this->get(route('login')); // Ruta '/'

    // Verificar que ve el componente otp-login
    $response->assertOk()
        ->assertSeeLivewire('otp-login');
});
