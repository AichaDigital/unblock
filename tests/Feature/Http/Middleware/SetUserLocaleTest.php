<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\App;

use function Pest\Laravel\{actingAs, get};

test('middleware sets locale from authenticated user preference', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);

    actingAs($user)
        ->get('/dashboard');

    expect(App::getLocale())->toBe('en');
});

test('middleware uses spanish as default for authenticated user without preference', function () {
    $user = User::factory()->create(['preferred_locale' => null]);

    actingAs($user)
        ->get('/dashboard', ['Accept-Language' => '']); // Clear browser preference

    expect(App::getLocale())->toBe('es');
});

test('middleware uses session locale for unauthenticated users', function () {
    session(['locale' => 'en']);

    get('/');

    expect(App::getLocale())->toBe('en');
});

test('middleware uses browser language when available', function () {
    $response = get('/', ['Accept-Language' => 'en-US,en;q=0.9']);

    expect(App::getLocale())->toBe('en');
});

test('middleware defaults to spanish when no preferences are set', function () {
    // Clear Accept-Language header to avoid browser preference
    get('/', ['Accept-Language' => '']);

    expect(App::getLocale())->toBe('es');
});

test('middleware ignores invalid locales', function () {
    session(['locale' => 'invalid']);

    get('/', ['Accept-Language' => '']); // Clear browser preference

    // Should fallback to default (es)
    expect(App::getLocale())->toBe('es');
});

test('middleware respects only available locales', function () {
    $user = User::factory()->create(['preferred_locale' => 'fr']); // French not available

    actingAs($user)
        ->get('/dashboard', ['Accept-Language' => '']); // Clear browser preference

    // Should fallback to default (es) since 'fr' is not in AVAILABLE_LOCALES
    expect(App::getLocale())->toBe('es');
});

test('user can update their locale preference', function () {
    $user = User::factory()->create(['preferred_locale' => 'es']);

    $user->update(['preferred_locale' => 'en']);

    expect($user->preferred_locale)->toBe('en');
});

test('locale persists across requests for authenticated users', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);

    actingAs($user);

    get('/dashboard');
    expect(App::getLocale())->toBe('en');

    // Second request should maintain locale
    get('/dashboard');
    expect(App::getLocale())->toBe('en');
});
