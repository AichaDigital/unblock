<?php

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers\HostsRelationManager;
use App\Models\{Host, Hosting, User, UserHostingPermission};

use function Pest\Livewire\livewire;

// Tests to verify that business logic works correctly
test('admin can access user edit page', function () {
    // Arrange - Create complete setup
    $admin = User::factory()->admin()->create();
    $parentUser = User::factory()->create([
        'parent_user_id' => null,
        'first_name' => 'TestParent',
        'last_name' => 'UserAdmin',
    ]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    // Act - Access the edit page as admin
    $this->actingAs($admin);

    // Simulate OTP verification session
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $admin->id);
    session()->put('admin_otp_verified_at', now()->timestamp);

    $response = $this->get('/admin/users/'.$parentUser->id.'/edit');

    // Assert - Page should load successfully
    $response->assertStatus(200);
    $response->assertSee('TestParent UserAdmin');
    $response->assertSee($parentUser->email);
});

test('parent user can assign hosting permissions to authorized users', function () {
    // Arrange - Create complete setup
    $admin = User::factory()->admin()->create();
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host->id,
        'domain' => 'test-domain.com',
    ]);

    // Act - Create the permission (simulating what would happen through the command)
    $this->actingAs($admin);

    // Simulate OTP verification session
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $admin->id);
    session()->put('admin_otp_verified_at', now()->timestamp);

    $permission = UserHostingPermission::create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Assert - Verify permission was created correctly
    expect($permission)->not->toBeNull();
    expect($permission->user_id)->toBe($authorizedUser->id);
    expect($permission->hosting_id)->toBe($hosting->id);
    expect($permission->is_active)->toBeTrue();

    // Verify the authorized user now has access
    expect($authorizedUser->fresh()->hasAccessToHosting($hosting->id))->toBeTrue();
    expect($authorizedUser->fresh()->hasAccessToHost($host->id))->toBeTrue();
});

test('parent user can assign host permissions to authorized users', function () {
    // Arrange - Create complete setup
    $admin = User::factory()->admin()->create();
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host = Host::factory()->create(['fqdn' => 'test-server.com']);

    // Act - Create the permission (simulating what would happen through the command)
    $this->actingAs($admin);

    // Simulate OTP verification session
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $admin->id);
    session()->put('admin_otp_verified_at', now()->timestamp);

    $authorizedUser->hosts()->attach($host->id, ['is_active' => true]);

    // Assert - Verify permission was created correctly
    expect($authorizedUser->fresh()->hosts()->count())->toBe(1);
    expect($authorizedUser->fresh()->hosts()->first()->id)->toBe($host->id);
    expect((bool) $authorizedUser->fresh()->hosts()->first()->pivot->is_active)->toBeTrue();

    // Verify the authorized user now has access
    expect($authorizedUser->fresh()->hasAccessToHost($host->id))->toBeTrue();
});

test('business logic works correctly for authorized users', function () {
    // Arrange - Create complete setup
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host->id,
        'domain' => 'business-test.com',
    ]);

    // Act - Create active and inactive permissions
    $activePermission = UserHostingPermission::create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Assert - Verify business logic
    expect($authorizedUser->fresh()->hasAccessToHosting($hosting->id))->toBeTrue();
    expect($authorizedUser->fresh()->hasAccessToHost($host->id))->toBeTrue();

    // Deactivate permission
    $activePermission->update(['is_active' => false]);

    // Verify access is revoked
    expect($authorizedUser->fresh()->hasAccessToHosting($hosting->id))->toBeFalse();
    expect($authorizedUser->fresh()->hasAccessToHost($host->id))->toBeFalse();

    // Reactivate permission
    $activePermission->update(['is_active' => true]);

    // Verify access is restored
    expect($authorizedUser->fresh()->hasAccessToHosting($hosting->id))->toBeTrue();
    expect($authorizedUser->fresh()->hasAccessToHost($host->id))->toBeTrue();
});

test('server assignment action loads for a user that already has hosts (regression: ambiguous column id)', function () {
    // Arrange - admin session (OTP verified)
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $admin->id);
    session()->put('admin_otp_verified_at', now()->timestamp);

    // The owner user ALREADY has a host assigned, so $assignedHostIds is non-empty
    // and the whereNotIn(...) clause is actually emitted. With an unqualified 'id'
    // over the BelongsToMany base query (which joins user_host_permissions),
    // this triggers "ambiguous column name: id" before the fix.
    $owner = User::factory()->create(['parent_user_id' => null]);
    $assignedHost = Host::factory()->create();
    $owner->hosts()->attach($assignedHost->id, ['is_active' => true]);

    // Another host available to be assigned
    $availableHost = Host::factory()->create();

    // Act - run the full "Asignar Servidor" flow: selecting a host forces Filament
    // to resolve its option label against the record-select options query, which is
    // where the unqualified whereNotIn('id', ...) over the joined base query blows up
    // with "ambiguous column name: id" (the production symptom).
    livewire(HostsRelationManager::class, [
        'ownerRecord' => $owner,
        'pageClass' => EditUser::class,
    ])
        ->callTableAction('attach', data: [
            'recordId' => $availableHost->id,
            'is_active' => true,
        ])
        ->assertHasNoTableActionErrors();

    // Assert - the host was actually attached (flow completed end to end)
    expect($owner->fresh()->hosts()->whereKey($availableHost->id)->exists())->toBeTrue();
});

test('unauthorized users cannot access admin panel', function () {
    // Arrange - Create users
    $regularUser = User::factory()->create(['is_admin' => false]);
    $parentUser = User::factory()->create(['parent_user_id' => null]);

    // Act & Assert - Regular user should not be able to access admin interfaces
    $this->actingAs($regularUser);

    // This should fail because regular users can't access Filament admin panel
    // The OTP middleware will pass (since user is not admin), but VerifyIsAdminMiddleware will block
    $response = $this->get('/admin/users/'.$parentUser->id.'/edit');
    $response->assertStatus(403); // Forbidden
});

test('command-based authorization system works correctly', function () {
    // Arrange - Create complete setup for command testing
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host->id,
        'domain' => 'command-test.com',
    ]);

    // Act - Simulate command operations
    // 1. Assign hosting permission to authorized user
    UserHostingPermission::create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // 2. Assign host permission to authorized user
    $authorizedUser->hosts()->attach($host->id, ['is_active' => true]);

    // Assert - Verify all permissions work
    expect($authorizedUser->fresh()->hasAccessToHosting($hosting->id))->toBeTrue();
    expect($authorizedUser->fresh()->hasAccessToHost($host->id))->toBeTrue();

    // Verify parent user has access to their own hosting
    expect($parentUser->fresh()->hasAccessToHosting($hosting->id))->toBeTrue();

    // Give parent user explicit host access
    $parentUser->hosts()->attach($host->id, ['is_active' => true]);
    expect($parentUser->fresh()->hasAccessToHost($host->id))->toBeTrue();

    // Verify command can revoke permissions
    UserHostingPermission::where('user_id', $authorizedUser->id)->delete();
    $authorizedUser->hosts()->detach($host->id);

    // Verify access is revoked for authorized user
    expect($authorizedUser->fresh()->hasAccessToHosting($hosting->id))->toBeFalse();
    // Authorized user still has access to host through parent's permission (inheritance)
    expect($authorizedUser->fresh()->hasAccessToHost($host->id))->toBeTrue();

    // Parent still has access
    expect($parentUser->fresh()->hasAccessToHosting($hosting->id))->toBeTrue();
    expect($parentUser->fresh()->hasAccessToHost($host->id))->toBeTrue();
});
