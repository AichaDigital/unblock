<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

use function Pest\Laravel\{actingAs, get};

beforeEach(function () {
    config()->set('app.locale', 'es');
    config()->set('app.fallback_locale', 'en');
    config()->set('app.available_locales', ['es', 'en']);
    session()->forget('locale');
});

test('language switcher component renders successfully', function () {
    actingAs(User::factory()->create());

    get(route('dashboard'))
        ->assertSeeLivewire('language-switcher')
        ->assertSee('Español')
        ->assertSee('English');
});

test('authenticated user can change language to english', function () {
    $user = User::factory()->create(['preferred_locale' => 'es']);
    actingAs($user);

    Livewire::test('language-switcher')
        ->call('changeLocale', 'en')
        ->assertDispatched('locale-changed', locale: 'en')
        ->assertDispatched('notify');

    expect($user->fresh()->preferred_locale)->toBe('en');
    expect(session('locale'))->toBe('en');
});

test('authenticated user can change language to spanish', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);
    actingAs($user);

    Livewire::test('language-switcher')
        ->call('changeLocale', 'es')
        ->assertDispatched('locale-changed', locale: 'es')
        ->assertDispatched('notify');

    expect($user->fresh()->preferred_locale)->toBe('es');
    expect(session('locale'))->toBe('es');
});

test('guest user can change language using session only', function () {
    Livewire::test('language-switcher')
        ->call('changeLocale', 'en')
        ->assertDispatched('locale-changed', locale: 'en')
        ->assertDispatched('notify');

    expect(session('locale'))->toBe('en');
});

test('language switcher ignores invalid locales', function () {
    $user = User::factory()->create(['preferred_locale' => 'es']);
    actingAs($user);

    Livewire::test('language-switcher')
        ->call('changeLocale', 'fr')
        ->assertNotDispatched('locale-changed')
        ->assertNotDispatched('notify');

    expect($user->fresh()->preferred_locale)->toBe('es');
});

test('language switcher displays current locale correctly', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);
    actingAs($user);

    // Simulate middleware setting locale
    app()->setLocale('en');

    Livewire::test('language-switcher')
        ->assertSet('currentLocale', 'en');
});

test('language switcher updates immediately on locale change', function () {
    $user = User::factory()->create(['preferred_locale' => 'es']);
    actingAs($user);

    Livewire::test('language-switcher')
        ->call('changeLocale', 'en')
        ->assertSet('currentLocale', 'en');

    expect(App::getLocale())->toBe('en');
});

test('language switcher persists preference across sessions for authenticated users', function () {
    $user = User::factory()->create(['preferred_locale' => 'es']);
    actingAs($user);

    Livewire::test('language-switcher')
        ->call('changeLocale', 'en');

    // Simulate new session
    session()->forget('locale');
    actingAs($user);

    get(route('dashboard'));

    expect(App::getLocale())->toBe('en');
});

test('language switcher works in simple unblock mode', function () {
    config()->set('unblock.simple_mode.enabled', true);

    $user = User::factory()->create(['preferred_locale' => 'es']);
    actingAs($user);

    get(route('simple.unblock'))
        ->assertSeeLivewire('language-switcher');
});

