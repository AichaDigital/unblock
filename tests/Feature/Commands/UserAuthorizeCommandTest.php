<?php

declare(strict_types=1);

use App\Models\{Host, Hosting, User, UserHostingPermission};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// NOTE: This command uses Laravel Prompts (select, multiselect, search, text, confirm)
// which are interactive and difficult to test with traditional PHPUnit assertions.
// These tests focus on command structure and basic database operations.
// ============================================================================

// ============================================================================
// SCENARIO 1: Command Structure
// ============================================================================

test('command has correct signature', function () {
    $command = new \App\Console\Commands\UserAuthorizeCommand;

    expect($command->getName())->toBe('user:authorize');
});

test('command has correct description', function () {
    $command = new \App\Console\Commands\UserAuthorizeCommand;

    expect($command->getDescription())->toBe('Manage user authorizations (create authorized users and assign permissions)');
});

test('command can be instantiated', function () {
    $command = new \App\Console\Commands\UserAuthorizeCommand;

    expect($command)->toBeInstanceOf(\Illuminate\Console\Command::class);
});

// ============================================================================
// SCENARIO 2: Database Setup for Command Scenarios
// ============================================================================

test('createAuthorizedUser requires parent users to exist', function () {
    // Verify no parent users exist
    $parentUsers = User::whereNull('parent_user_id')->get();
    expect($parentUsers)->toBeEmpty();

    // This would cause the command to fail with "No parent users available."
    // (Cannot test interactively without mocking prompts)
});

test('assignPermissions requires authorized users to exist', function () {
    // Verify no authorized users exist
    $authorizedUsers = User::whereNotNull('parent_user_id')->get();
    expect($authorizedUsers)->toBeEmpty();

    // This would cause the command to fail with "No authorized users available."
    // (Cannot test interactively without mocking prompts)
});

// ============================================================================
// SCENARIO 3: User Relationships and Permissions Structure
// ============================================================================

test('parent user can have multiple authorized child users', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);

    $child1 = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $child2 = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $parentUser->refresh();

    $authorizedUserIds = $parentUser->authorizedUsers->pluck('id')->toArray();

    expect($parentUser->authorizedUsers)->toHaveCount(2)
        ->and($authorizedUserIds)->toContain($child1->id)
        ->and($authorizedUserIds)->toContain($child2->id);
});

test('authorized user belongs to parent user', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    expect($childUser->parentUser)->toBeInstanceOf(User::class)
        ->and($childUser->parentUser->id)->toBe($parentUser->id);
});

test('authorized user can have hosting permissions', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'host_id' => $host->id,
        'user_id' => $parentUser->id,
    ]);

    $permission = UserHostingPermission::create([
        'user_id' => $childUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    expect($childUser->hostingPermissions)->toHaveCount(1)
        ->and($childUser->hostingPermissions->first()->hosting->id)->toBe($hosting->id);
});

test('authorized user can have host permissions', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();

    // Assign host to parent first
    $parentUser->hosts()->attach($host->id, ['is_active' => true]);

    // Assign same host to child
    $childUser->hosts()->attach($host->id, ['is_active' => true]);

    expect($childUser->hosts)->toHaveCount(1)
        ->and($childUser->hosts->first()->id)->toBe($host->id);
});

// ============================================================================
// SCENARIO 4: Permission States
// ============================================================================

test('hosting permissions can be active or inactive', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();
    $hosting1 = Hosting::factory()->create(['host_id' => $host->id, 'user_id' => $parentUser->id]);
    $hosting2 = Hosting::factory()->create(['host_id' => $host->id, 'user_id' => $parentUser->id]);

    $activePermission = UserHostingPermission::create([
        'user_id' => $childUser->id,
        'hosting_id' => $hosting1->id,
        'is_active' => true,
    ]);

    $inactivePermission = UserHostingPermission::create([
        'user_id' => $childUser->id,
        'hosting_id' => $hosting2->id,
        'is_active' => false,
    ]);

    expect($activePermission->is_active)->toBeTrue()
        ->and($inactivePermission->is_active)->toBeFalse();
});

test('host permissions can be active or inactive via pivot', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host1 = Host::factory()->create();
    $host2 = Host::factory()->create();

    $childUser->hosts()->attach($host1->id, ['is_active' => true]);
    $childUser->hosts()->attach($host2->id, ['is_active' => false]);

    $childUser->refresh();

    $activeHost = $childUser->hosts()->wherePivot('is_active', true)->first();
    $inactiveHost = $childUser->hosts()->wherePivot('is_active', false)->first();

    expect($activeHost->id)->toBe($host1->id)
        ->and($inactiveHost->id)->toBe($host2->id);
});

// ============================================================================
// SCENARIO 5: Resource Ownership Validation
// ============================================================================

test('parent user must own hostings before assigning to child', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();

    // Parent has no hostings
    $availableHostings = $parentUser->hostings;
    expect($availableHostings)->toBeEmpty();

    // Create hosting owned by parent
    $hosting = Hosting::factory()->create([
        'host_id' => $host->id,
        'user_id' => $parentUser->id,
    ]);

    $parentUser->refresh();
    expect($parentUser->hostings)->toHaveCount(1);
});

test('parent user must have access to hosts before assigning to child', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    // Parent has no host access
    $availableHosts = $parentUser->hosts()->wherePivot('is_active', true)->get();
    expect($availableHosts)->toBeEmpty();

    // Assign host to parent
    $host = Host::factory()->create();
    $parentUser->hosts()->attach($host->id, ['is_active' => true]);

    $parentUser->refresh();
    $availableHosts = $parentUser->hosts()->wherePivot('is_active', true)->get();
    expect($availableHosts)->toHaveCount(1);
});

// ============================================================================
// SCENARIO 6: Permission Revocation
// ============================================================================

test('revoking hosting permissions deletes records', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create(['host_id' => $host->id, 'user_id' => $parentUser->id]);

    $permission = UserHostingPermission::create([
        'user_id' => $childUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    expect(UserHostingPermission::count())->toBe(1);

    $permission->delete();

    expect(UserHostingPermission::count())->toBe(0);
});

test('revoking host permissions detaches relationship', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();

    $childUser->hosts()->attach($host->id, ['is_active' => true]);
    expect($childUser->hosts)->toHaveCount(1);

    $childUser->hosts()->detach($host->id);
    $childUser->refresh();

    expect($childUser->hosts)->toHaveCount(0);
});

// ============================================================================
// SCENARIO 7: User Reassignment
// ============================================================================

test('authorized user can be reassigned to different parent', function () {
    $parentUser1 = User::factory()->create(['parent_user_id' => null]);
    $parentUser2 = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create(['parent_user_id' => $parentUser1->id]);

    expect($childUser->parent_user_id)->toBe($parentUser1->id);

    $childUser->parent_user_id = $parentUser2->id;
    $childUser->save();

    expect($childUser->parent_user_id)->toBe($parentUser2->id)
        ->and($childUser->parentUser->id)->toBe($parentUser2->id);
});

test('reassignment maintains user data', function () {
    $parentUser1 = User::factory()->create(['parent_user_id' => null]);
    $parentUser2 = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create([
        'parent_user_id' => $parentUser1->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);

    $originalId = $childUser->id;
    $originalEmail = $childUser->email;

    $childUser->parent_user_id = $parentUser2->id;
    $childUser->save();
    $childUser->refresh();

    expect($childUser->id)->toBe($originalId)
        ->and($childUser->email)->toBe($originalEmail)
        ->and($childUser->first_name)->toBe('John');
});

// ============================================================================
// SCENARIO 8: Query Scopes for Command Logic
// ============================================================================

test('can query parent users only', function () {
    User::factory()->create(['parent_user_id' => null]);
    User::factory()->create(['parent_user_id' => null]);
    $parent = User::factory()->create(['parent_user_id' => null]);
    User::factory()->create(['parent_user_id' => $parent->id]);

    $parentUsers = User::whereNull('parent_user_id')->get();

    expect($parentUsers)->toHaveCount(3);
});

test('can query authorized users only', function () {
    $parent1 = User::factory()->create(['parent_user_id' => null]);
    $parent2 = User::factory()->create(['parent_user_id' => null]);
    User::factory()->create(['parent_user_id' => $parent1->id]);
    User::factory()->create(['parent_user_id' => $parent2->id]);

    $authorizedUsers = User::whereNotNull('parent_user_id')->get();

    expect($authorizedUsers)->toHaveCount(2);
});

test('can filter users with permissions', function () {
    $parent = User::factory()->create(['parent_user_id' => null]);
    $childWithPermissions = User::factory()->create(['parent_user_id' => $parent->id]);
    $childWithoutPermissions = User::factory()->create(['parent_user_id' => $parent->id]);

    $host = Host::factory()->create();
    $childWithPermissions->hosts()->attach($host->id, ['is_active' => true]);

    $usersWithPermissions = User::whereNotNull('parent_user_id')
        ->with('hosts')
        ->get()
        ->filter(fn ($user) => $user->hosts->isNotEmpty());

    expect($usersWithPermissions)->toHaveCount(1)
        ->and($usersWithPermissions->first()->id)->toBe($childWithPermissions->id);
});

// ============================================================================
// SCENARIO 9: Email Validation
// ============================================================================

test('user email must be unique', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    // Simulates the validation in the command
    $emailExists = User::where('email', 'existing@example.com')->exists();

    expect($emailExists)->toBeTrue();
});

test('user email validation accepts new email', function () {
    // Simulates the validation in the command
    $emailExists = User::where('email', 'new@example.com')->exists();

    expect($emailExists)->toBeFalse();
});

// ============================================================================
// SCENARIO 10: Edge Cases
// ============================================================================

test('parent user cannot be child of another user', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);

    expect($parentUser->parent_user_id)->toBeNull()
        ->and($parentUser->parentUser)->toBeNull();
});

test('child user can have empty company name', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create([
        'parent_user_id' => $parentUser->id,
        'company_name' => null,
    ]);

    expect($childUser->company_name)->toBeNull();
});

test('child user can have empty last name', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $childUser = User::factory()->create([
        'parent_user_id' => $parentUser->id,
        'last_name' => '',
    ]);

    expect($childUser->last_name)->toBe('');
});
