<?php

use App\Actions\WhmcsSynchro;
use App\Models\{Host, Hosting, User};
use Illuminate\Support\Facades\{Config, DB};
use Tests\Traits\WhmcsTestTrait;

uses(WhmcsTestTrait::class);

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

    // Configurar base de datos WHMCS por defecto
    $this->setUpWhmcsDatabase();
});

test('finds host by whmcs id', function () {
    // Arrange
    $action = new WhmcsSynchro;
    $host1 = Host::factory()->withWhmcsServerId(100)->create();
    $host2 = Host::factory()->withWhmcsServerId(200)->create();

    $action->loadHosts();

    // Act & Assert
    $found = $action->findHostByWhmcsId('100');
    expect($found->id)->toBe($host1->id);

    $notFound = $action->findHostByWhmcsId('300');
    expect($notFound)->toBeNull();

    $found = $action->findHostByWhmcsId(200);
    expect($found->id)->toBe($host2->id);
});

test('creates user from whmcs data', function () {
    // Arrange
    $action = new WhmcsSynchro;
    $whmcsClient = (object) [
        'id' => 1,
        'firstname' => 'John',
        'lastname' => 'Doe',
        'companyname' => 'Test Inc',
        'email' => 'john@example.com',
        'password' => 'hashed_password',
    ];

    // Act
    $user = $action->createUser($whmcsClient);

    // Assert
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->first_name)->toBe('John')
        ->and($user->last_name)->toBe('Doe')
        ->and($user->company_name)->toBe('Test Inc')
        ->and($user->email)->toBe('john@example.com')
        ->and($user->whmcs_client_id)->toBe(1);
});

test('finds or creates user', function () {
    // Arrange
    $action = new WhmcsSynchro;
    $whmcsClient = (object) [
        'id' => 1,
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'companyname' => '',
    ];

    // Act & Assert - Nuevo usuario
    $newUser = $action->findOrCreateUser($whmcsClient, null);
    expect($newUser)->toBeInstanceOf(User::class)
        ->and($newUser->whmcs_client_id)->toBe(1);

    // Act & Assert - Usuario existente
    $existingUser = $action->findOrCreateUser($whmcsClient, null);
    expect($existingUser->id)->toBe($newUser->id);

    // Act & Assert - Restaurar usuario eliminado
    $newUser->delete();
    $restoredUser = $action->findOrCreateUser($whmcsClient, null);
    expect($restoredUser)->not->toBeNull();
    expect($restoredUser->trashed())->toBeFalse();
    expect($restoredUser->id)->toBe($newUser->id);
});

test('processes active hostings', function () {
    // Arrange
    $action = new WhmcsSynchro;
    $user = User::factory()->withWhmcsClientId(1)->create();
    $host = Host::factory()->withWhmcsServerId(100)->create();

    $this->createWhmcsHosting([
        'userid' => 1,
        'domain' => 'test.com',
        'server' => '100',
        'username' => 'testuser',
        'domainstatus' => 'Active',
    ]);

    $action->loadHosts();

    // Act
    $action->processActiveHostings($user->id);

    // Assert
    $hosting = Hosting::where('domain', 'test.com')->first();
    expect($hosting)->not->toBeNull()
        ->and($hosting->user_id)->toBe($user->id)
        ->and($hosting->host_id)->toBe($host->id)
        ->and($hosting->username)->toBe('testuser')
        ->and($user->hosts)->toHaveCount(1)
        ->and($user->hosts->contains($host->id))->toBeTrue()
        ->and($user->hosts->first()->pivot->is_active)->toBe(1);

});

test('deactivateInactiveUsers eager-loads hostings without an N+1', function () {
    // Regression (AID-342 H2): loading each inactive user's hostings must not
    // issue one query per user. Eager-load with a constraint keeps the hostings
    // SELECT count constant regardless of how many inactive users there are.
    $host = Host::factory()->create();

    // 3 inactive users (whmcs_client_id set, none in the "active" list), each
    // with one automatic hosting (to deactivate) and one manual (to preserve).
    $users = collect([101, 102, 103])->map(function (int $clientId) use ($host) {
        $user = User::factory()->withWhmcsClientId($clientId)->create();
        Hosting::factory()->create(['user_id' => $user->id, 'host_id' => $host->id]);
        Hosting::factory()->manual()->create(['user_id' => $user->id, 'host_id' => $host->id]);

        return $user;
    });

    $action = new WhmcsSynchro;

    DB::enableQueryLog();
    // Empty active list => every user with a whmcs_client_id is inactive.
    (function () {
        $this->deactivateInactiveUsers([]);
    })->call($action);

    $hostingSelects = collect(DB::getQueryLog())
        ->filter(fn (array $q) => str_contains($q['query'], 'select')
            && str_contains($q['query'], '"hostings"'))
        ->count();
    DB::disableQueryLog();

    // Exactly one SELECT for hostings (eager-loaded), not one per user.
    expect($hostingSelects)->toBe(1);

    // Behaviour preserved: automatic hostings soft-deleted, manual ones kept,
    // and all inactive users soft-deleted.
    $users->each(fn (User $user) => expect($user->fresh()->trashed())->toBeTrue());
    expect(Hosting::whereNull('deleted_at')->where('hosting_manual', true)->count())->toBe(3)
        ->and(Hosting::whereNull('deleted_at')->where('hosting_manual', false)->count())->toBe(0);
});

test('handles inactive hostings', function () {
    // Arrange
    $action = new WhmcsSynchro;
    $user = User::factory()->withWhmcsClientId(1)->create();
    $host1 = Host::factory()->withWhmcsServerId(100)->create();
    $host2 = Host::factory()->withWhmcsServerId(101)->create();

    $activeHosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host1->id,
        'domain' => 'active.com',
    ]);

    $inactiveHosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host2->id,
        'domain' => 'inactive.com',
    ]);

    $this->createWhmcsHosting([
        'userid' => 1,
        'domain' => 'active.com',
        'server' => '100',
        'username' => 'activeuser',
        'domainstatus' => 'Active',
    ]);

    $action->loadHosts();

    // Act
    $action->processActiveHostings($user->id);

    // Assert
    expect($activeHosting->fresh()->trashed())->toBeFalse()
        ->and(Hosting::withTrashed()->where('domain', 'inactive.com')->first()->trashed())
        ->toBeTrue();
});
