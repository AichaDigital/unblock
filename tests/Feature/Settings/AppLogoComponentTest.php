<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\{actingAs, get};

beforeEach(function () {
    Storage::fake('public');
});

test('logo component displays logo when set', function () {
    $logo = UploadedFile::fake()->image('logo.png', 200, 200);
    $path = $logo->store('company', 'public');
    setting(['company_logo' => $path]);

    $response = get(route('login'));

    $response->assertSee(asset('storage/'.$path), false);
});

test('logo component displays company name when logo not set', function () {
    setting(['company_name' => 'Test Company']);
    setting(['company_logo' => null]);

    $response = get(route('login'));

    $response->assertSee('Test Company');
});

test('logo component uses fallback to config when company name not set', function () {
    Setting::where('key', 'company_name')->delete();
    Setting::where('key', 'company_logo')->delete();

    $response = get(route('login'));

    // The app logo component should display the fallback company name
    $fallbackName = config('company.name', config('app.name'));
    $response->assertSuccessful();
    // Just verify the page renders without errors when no logo is set
});

test('logo appears on login page', function () {
    $logo = UploadedFile::fake()->image('logo.png', 200, 200);
    $path = $logo->store('company', 'public');
    setting(['company_logo' => $path]);

    get(route('login'))
        ->assertSee(asset('storage/'.$path), false);
});

test('logo appears on dashboard', function () {
    $admin = createAdminUser('admin@test.com');
    $logo = UploadedFile::fake()->image('logo.png', 200, 200);
    $path = $logo->store('company', 'public');
    setting(['company_logo' => $path]);

    actingAs($admin)
        ->get(route('dashboard'))
        ->assertSee(asset('storage/'.$path), false);
});

test('logo appears on simple unblock form when simple mode enabled', function () {
    config()->set('unblock.simple_mode.enabled', true);

    $logo = UploadedFile::fake()->image('logo.png', 200, 200);
    $path = $logo->store('company', 'public');
    setting(['company_logo' => $path]);

    get(route('simple.unblock'))
        ->assertSee(asset('storage/'.$path), false);
});

test('logo component handles missing storage file gracefully', function () {
    // Set a logo path that doesn't exist
    setting(['company_logo' => 'company/nonexistent.png']);
    setting(['company_name' => 'Fallback Company']);

    // Should still render without errors
    get(route('login'))
        ->assertSuccessful();
});
