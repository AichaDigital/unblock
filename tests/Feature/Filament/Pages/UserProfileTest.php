<?php

declare(strict_types=1);

use App\Filament\Pages\UserProfile;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\{actingAs, assertDatabaseHas};

beforeEach(function () {
    config()->set('app.locale', 'es');
    config()->set('app.fallback_locale', 'en');
    config()->set('app.available_locales', ['es', 'en']);
});

test('admin can access user profile page', function () {
    $admin = User::factory()->create(['is_admin' => true, 'preferred_locale' => 'es']);
    actingAs($admin);

    Livewire::test(UserProfile::class)
        ->assertSuccessful();
});

test('admin can change language preference from profile', function () {
    $admin = User::factory()->create(['is_admin' => true, 'preferred_locale' => 'es']);
    actingAs($admin);

    Livewire::test(UserProfile::class)
        ->fillForm(['preferred_locale' => 'en'])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas('users', [
        'id' => $admin->id,
        'preferred_locale' => 'en',
    ]);

    expect($admin->fresh()->preferred_locale)->toBe('en');
    expect(session('locale'))->toBe('en');
});

test('language preference persists across sessions', function () {
    $admin = User::factory()->create(['is_admin' => true, 'preferred_locale' => 'en']);
    actingAs($admin);

    Livewire::test(UserProfile::class)
        ->assertFormSet(['preferred_locale' => 'en']);
});

test('user profile displays current user information', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'preferred_locale' => 'es',
    ]);
    actingAs($admin);

    Livewire::test(UserProfile::class)
        ->assertSeeHtml('John Doe')
        ->assertSeeHtml('john@example.com');
});

test('language preference defaults to spanish if not set', function () {
    $admin = User::factory()->create(['is_admin' => true, 'preferred_locale' => null]);
    actingAs($admin);

    Livewire::test(UserProfile::class)
        ->assertFormSet(['preferred_locale' => 'es']);
});

test('only valid locales can be selected', function () {
    $admin = User::factory()->create(['is_admin' => true, 'preferred_locale' => 'es']);
    actingAs($admin);

    $component = Livewire::test(UserProfile::class);

    // Try to set invalid locale
    $component->fillForm(['preferred_locale' => 'fr'])
        ->call('save')
        ->assertHasFormErrors(['preferred_locale']);
});
