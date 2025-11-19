<?php

declare(strict_types=1);

use App\Livewire\LogoUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
});

test('admin can access logo upload component', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(LogoUpload::class)
        ->assertOk();
});

test('non-admin cannot access logo upload component', function () {
    $user = User::factory()->create(['is_admin' => false]);

    actingAs($user);

    Livewire::test(LogoUpload::class)
        ->assertForbidden();
});

test('guest cannot access logo upload component', function () {
    Livewire::test(LogoUpload::class)
        ->assertForbidden();
});

test('admin can upload a logo', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    $file = UploadedFile::fake()->image('logo.png', 500, 500);

    Livewire::test(LogoUpload::class)
        ->set('logo', $file)
        ->call('saveLogo')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $admin->refresh();

    expect($admin->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($admin->logo_path);
});

test('admin can remove logo', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'logo_path' => 'logos/test.png',
    ]);

    Storage::disk('public')->put('logos/test.png', 'fake-content');

    actingAs($admin);

    Livewire::test(LogoUpload::class)
        ->call('removeLogo')
        ->assertDispatched('notify');

    $admin->refresh();

    expect($admin->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing('logos/test.png');
});

test('logo upload validates file type', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    $file = UploadedFile::fake()->create('document.txt', 100);

    Livewire::test(LogoUpload::class)
        ->set('logo', $file)
        ->assertHasErrors(['logo']);
});

test('logo upload validates file size', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    $file = UploadedFile::fake()->image('logo.png', 500, 500)->size(3000);

    Livewire::test(LogoUpload::class)
        ->set('logo', $file)
        ->call('saveLogo')
        ->assertHasErrors(['logo']);
});

test('logo upload validates dimensions', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    $file = UploadedFile::fake()->image('logo.png', 50, 50);

    Livewire::test(LogoUpload::class)
        ->set('logo', $file)
        ->call('saveLogo')
        ->assertHasErrors(['logo']);
});

test('uploading new logo deletes old logo', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'logo_path' => 'logos/old-logo.png',
    ]);

    Storage::disk('public')->put('logos/old-logo.png', 'old-content');

    actingAs($admin);

    $newFile = UploadedFile::fake()->image('new-logo.png', 500, 500);

    Livewire::test(LogoUpload::class)
        ->set('logo', $newFile)
        ->call('saveLogo')
        ->assertHasNoErrors();

    $admin->refresh();

    expect($admin->logo_path)->not->toBe('logos/old-logo.png');
    Storage::disk('public')->assertMissing('logos/old-logo.png');
    Storage::disk('public')->assertExists($admin->logo_path);
});
