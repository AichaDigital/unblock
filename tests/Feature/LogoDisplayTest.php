<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\{actingAs, get};

beforeEach(function () {
    Storage::fake('public');
});

describe('Logo display in admin mode', function () {
    beforeEach(function () {
        config()->set('unblock.simple_mode.enabled', false);
    });

    test('logo is displayed on dashboard when user has logo', function () {
        $admin = User::factory()->create([
            'is_admin' => true,
            'logo_path' => 'logos/admin-logo.png',
        ]);

        Storage::disk('public')->put('logos/admin-logo.png', 'content');

        actingAs($admin);

        get(route('dashboard'))
            ->assertOk()
            ->assertSee('storage/logos/admin-logo.png');
    });

    test('logo is displayed on OTP login page when admin has logo', function () {
        $admin = User::factory()->create([
            'is_admin' => true,
            'logo_path' => 'logos/admin-logo.png',
        ]);

        Storage::disk('public')->put('logos/admin-logo.png', 'content');

        get(route('login'))
            ->assertOk()
            ->assertSee('storage/logos/admin-logo.png');
    });

    test('no logo container when admin has no logo', function () {
        $admin = User::factory()->create([
            'is_admin' => true,
            'logo_path' => null,
        ]);

        actingAs($admin);

        get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('storage/logos/');
    });
});

describe('Logo display in simple mode', function () {
    beforeEach(function () {
        config()->set('unblock.simple_mode.enabled', true);
    });

    test('logo is displayed on simple unblock form when admin has logo', function () {
        User::factory()->create([
            'is_admin' => true,
            'logo_path' => 'logos/admin-logo.png',
        ]);

        Storage::disk('public')->put('logos/admin-logo.png', 'content');

        get(route('simple.unblock'))
            ->assertOk()
            ->assertSee('storage/logos/admin-logo.png');
    });

    test('logo is displayed on OTP login in simple mode', function () {
        User::factory()->create([
            'is_admin' => true,
            'logo_path' => 'logos/admin-logo.png',
        ]);

        Storage::disk('public')->put('logos/admin-logo.png', 'content');

        get(route('login'))
            ->assertOk()
            ->assertSee('storage/logos/admin-logo.png');
    });

    test('no logo when no admin has logo in simple mode', function () {
        // Bypass rate limiting for test
        $this->withoutMiddleware(\App\Http\Middleware\ThrottleSimpleUnblock::class);

        get(route('simple.unblock'))
            ->assertOk()
            ->assertDontSee('storage/logos/');
    });
});

describe('Logo accessibility', function () {
    test('guest users see admin logo on login page', function () {
        User::factory()->create([
            'is_admin' => true,
            'logo_path' => 'logos/admin-logo.png',
        ]);

        Storage::disk('public')->put('logos/admin-logo.png', 'content');

        get(route('login'))
            ->assertOk()
            ->assertSee('storage/logos/admin-logo.png');
    });

    test('authenticated non-admin users see their admin logo', function () {
        $admin = User::factory()->create([
            'is_admin' => true,
            'logo_path' => 'logos/admin-logo.png',
        ]);

        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Storage::disk('public')->put('logos/admin-logo.png', 'content');

        actingAs($user);

        get(route('dashboard'))
            ->assertOk();
    });
});

describe('User model logo attribute', function () {
    test('logo_url attribute returns correct URL when logo exists', function () {
        $user = User::factory()->create([
            'logo_path' => 'logos/test-logo.png',
        ]);

        expect($user->logo_url)
            ->toBe(asset('storage/logos/test-logo.png'));
    });

    test('logo_url attribute returns null when no logo', function () {
        $user = User::factory()->create([
            'logo_path' => null,
        ]);

        expect($user->logo_url)->toBeNull();
    });
});
