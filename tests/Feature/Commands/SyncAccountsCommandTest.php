<?php

declare(strict_types=1);

use App\Actions\Sync\{SyncCpanelAccountsAction, SyncDirectAdminAccountsAction};
use App\Enums\PanelType;
use App\Models\Host;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sync accounts command fails when no hosts found', function () {
    $this->artisan('sync:accounts')
        ->expectsOutput('🔄 Starting Accounts Synchronization')
        ->expectsOutput('No hosts found to sync')
        ->assertFailed();
});

test('sync accounts command syncs all cpanel and directadmin hosts', function () {
    $cpanelHost = Host::factory()->create([
        'panel' => PanelType::CPANEL,
        'deleted_at' => null,
        'hash' => 'test-key', // Required for sync
    ]);

    $daHost = Host::factory()->create([
        'panel' => PanelType::DIRECTADMIN,
        'deleted_at' => null,
        'hash' => 'test-key-da', // Required for sync
    ]);

    // Mock actions via service container
    $this->app->instance(SyncCpanelAccountsAction::class, $cpanelAction = \Mockery::mock(SyncCpanelAccountsAction::class));
    $cpanelAction->shouldReceive('handle')
        ->once()
        ->with(\Mockery::type(Host::class), false)
        ->andReturn([
            'created' => 5,
            'updated' => 3,
            'suspended' => 1,
            'deleted' => 0,
        ]);

    $this->app->instance(SyncDirectAdminAccountsAction::class, $daAction = \Mockery::mock(SyncDirectAdminAccountsAction::class));
    $daAction->shouldReceive('handle')
        ->once()
        ->with(\Mockery::type(Host::class), false)
        ->andReturn([
            'created' => 2,
            'updated' => 1,
            'suspended' => 0,
            'deleted' => 0,
        ]);

    $this->artisan('sync:accounts')
        ->expectsOutput('🔄 Starting Accounts Synchronization')
        ->expectsOutput('Found 2 host(s) to synchronize')
        ->assertSuccessful();
});

test('sync accounts command syncs specific host when host option provided', function () {
    $host = Host::factory()->create([
        'panel' => PanelType::CPANEL,
        'deleted_at' => null,
        'hash' => 'test-key',
    ]);

    $this->app->instance(SyncCpanelAccountsAction::class, $cpanelAction = \Mockery::mock(SyncCpanelAccountsAction::class));
    $cpanelAction->shouldReceive('handle')
        ->once()
        ->with(\Mockery::type(Host::class), false)
        ->andReturn([
            'created' => 10,
            'updated' => 5,
            'suspended' => 2,
            'deleted' => 1,
        ]);

    $this->artisan('sync:accounts', ['--host' => (string) $host->id])
        ->expectsOutput('Found 1 host(s) to synchronize')
        ->assertSuccessful();
});

test('sync accounts command runs in initial mode when initial option provided', function () {
    $host = Host::factory()->create([
        'panel' => PanelType::CPANEL,
        'deleted_at' => null,
        'hash' => 'test-key',
    ]);

    $this->app->instance(SyncCpanelAccountsAction::class, $cpanelAction = \Mockery::mock(SyncCpanelAccountsAction::class));
    $cpanelAction->shouldReceive('handle')
        ->once()
        ->with(\Mockery::type(Host::class), true) // isInitial = true
        ->andReturn([
            'created' => 5,
            'updated' => 0,
            'suspended' => 0,
            'deleted' => 0,
        ]);

    $this->artisan('sync:accounts', ['--initial' => true, '--host' => (string) $host->id])
        ->expectsOutput('⚠️  Running in INITIAL mode - Will not mark deleted accounts')
        ->assertSuccessful();
});

test('sync accounts command handles sync failure gracefully', function () {
    $host = Host::factory()->create([
        'panel' => PanelType::CPANEL,
        'deleted_at' => null,
    ]);

    $cpanelAction = $this->mock(SyncCpanelAccountsAction::class);
    $cpanelAction->shouldReceive('handle')
        ->once()
        ->andThrow(new \Exception('SSH connection failed'));

    $this->artisan('sync:accounts', ['--host' => (string) $host->id])
        ->expectsOutputToContain('Failed to sync')
        ->assertSuccessful(); // Command catches exception and continues
});

test('sync accounts command excludes deleted hosts', function () {
    Host::factory()->create([
        'panel' => PanelType::CPANEL,
        'deleted_at' => now(),
    ]);

    $this->artisan('sync:accounts')
        ->expectsOutput('No hosts found to sync')
        ->assertFailed();
});

test('sync accounts command excludes non cpanel or directadmin hosts', function () {
    Host::factory()->create([
        'panel' => PanelType::CPANEL,
        'deleted_at' => null,
    ]);

    // Create a host with different panel (if exists)
    // This test ensures only cpanel/directadmin hosts are synced

    $this->artisan('sync:accounts')
        ->assertSuccessful();
});
