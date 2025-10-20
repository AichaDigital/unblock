<?php

use App\Actions\WhmcsSynchro;
use App\Models\{Host, Hosting, User};
use Illuminate\Support\Facades\{Config, DB};

beforeEach(function () {
    Config::set('unblock.whmcs.sync', [
        'users' => [
            'enabled' => true,
            'create_if_not_exists' => true,
        ],
        'hostings' => [
            'enabled' => true,
        ],
    ]);

    // Configurar la base de datos de WHMCS para testing
    Config::set('database.connections.whmcs', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    // Crear las tablas necesarias en la base de datos de prueba de WHMCS
    DB::connection('whmcs')->statement('CREATE TABLE IF NOT EXISTS tblclients (
        id INTEGER PRIMARY KEY,
        firstname VARCHAR(255),
        lastname VARCHAR(255),
        companyname VARCHAR(255),
        email VARCHAR(255),
        password VARCHAR(255),
        status VARCHAR(255)
    )');

    DB::connection('whmcs')->statement('CREATE TABLE IF NOT EXISTS tblhosting (
        id INTEGER PRIMARY KEY,
        userid INTEGER,
        domain VARCHAR(255),
        username VARCHAR(255),
        server VARCHAR(255),
        domainstatus VARCHAR(255)
    )');
});

test('synchronizes users and hostings from whmcs', function () {
    // Arrange
    $host1 = Host::factory()->withWhmcsServerId(100)->create();
    $host2 = Host::factory()->withWhmcsServerId(200)->create();

    // Insertar datos de prueba en WHMCS
    DB::connection('whmcs')->table('tblclients')->insert([
        'id' => 1,
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john@example.com',
        'status' => 'Active',
    ]);

    DB::connection('whmcs')->table('tblhosting')->insert([
        [
            'userid' => 1,
            'domain' => 'domain1.com',
            'server' => '100',
            'username' => 'user1',
            'domainstatus' => 'Active',
        ],
        [
            'userid' => 1,
            'domain' => 'domain2.com',
            'server' => '200',
            'username' => 'user2',
            'domainstatus' => 'Active',
        ],
    ]);

    // Act
    $action = new WhmcsSynchro;
    $action->handle();

    // Assert
    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->whmcs_client_id)->toBe(1);

    $hostings = $user->hostings;
    expect($hostings)->toHaveCount(2);

    $hosting1 = $hostings->where('domain', 'domain1.com')->first();
    expect($hosting1)
        ->not->toBeNull()
        ->and($hosting1->host_id)->toBe($host1->id)
        ->and($hosting1->username)->toBe('user1');

    $hosting2 = $hostings->where('domain', 'domain2.com')->first();
    expect($hosting2)
        ->not->toBeNull()
        ->and($hosting2->host_id)->toBe($host2->id)
        ->and($hosting2->username)->toBe('user2');
});

test('deactivates users and hostings when removed from whmcs', function () {
    // Arrange
    $user = User::factory()->withWhmcsClientId(1)->create();
    $host = Host::factory()->withWhmcsServerId(100)->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'domain' => 'domain.com',
    ]);

    $user->hosts()->attach($host->id, ['is_active' => true]);

    // Simular usuario inactivo en WHMCS
    DB::connection('whmcs')->table('tblclients')->insert([
        'id' => 1,
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john@example.com',
        'status' => 'Inactive',
    ]);

    // Act
    $action = new WhmcsSynchro;
    $action->handle();

    // Assert
    expect($user->fresh()->trashed())->toBeTrue();
    expect($hosting->fresh()->trashed())->toBeTrue();
});

test('restores user and hostings when reactivated in whmcs', function () {
    // Arrange
    $user = User::factory()->create([
        'whmcs_client_id' => 1,
        'deleted_at' => now(),
    ]);

    $host = Host::factory()->withWhmcsServerId(100)->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'domain' => 'domain.com',
        'deleted_at' => now(),
    ]);

    // Simular usuario reactivado en WHMCS
    DB::connection('whmcs')->table('tblclients')->insert([
        'id' => 1,
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john@example.com',
        'status' => 'Active',
    ]);

    DB::connection('whmcs')->table('tblhosting')->insert([
        'userid' => 1,
        'domain' => 'domain.com',
        'server' => '100',
        'username' => 'user1',
        'domainstatus' => 'Active',
    ]);

    // Act
    $action = new WhmcsSynchro;
    $action->handle();

    // Assert
    $user = $user->fresh();
    expect($user)
        ->not->toBeNull()
        ->and($user->trashed())->toBeFalse();

    $hosting = $hosting->fresh();
    expect($hosting)->not->toBeNull();
    expect($hosting->trashed())->toBeFalse();

    expect($user->hosts)->toHaveCount(1);
    expect($user->hosts->first()->id)->toBe($host->id);
    expect($user->hosts->first()->pivot->is_active)->toBe(1);
});

test('does not sync manual hostings', function () {
    // Create a user with WHMCS client ID
    $user = User::factory()->create(['whmcs_client_id' => 123]);

    // Create a host
    $host = Host::factory()->create(['whmcs_server_id' => 25]);

    // Create a manual hosting
    $manualHosting = Hosting::factory()->manual()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
        'domain' => 'manual-test.com',
        'username' => 'manual_user',
    ]);

    // Mock WHMCS data with the same domain but different data
    DB::connection('whmcs')->table('tblhosting')->insert([
        'userid' => 123,
        'domain' => 'manual-test.com',
        'username' => 'whmcs_user', // Different username
        'server' => 25,
        'domainstatus' => 'Active',
    ]);

    // Run synchronization
    $action = new WhmcsSynchro;
    $action->loadHosts();
    $action->processActiveHostings($user->id);

    // Verify manual hosting was not modified
    $manualHosting->refresh();
    expect($manualHosting->username)->toBe('manual_user');
    expect($manualHosting->hosting_manual)->toBeTrue();
    expect($manualHosting->trashed())->toBeFalse();
});
