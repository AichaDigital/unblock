<?php

use App\Models\{Host, Hosting, User, UserHostingPermission};

// Tests to verify that business logic works correctly
test('admin can access user edit page', function () {
    // Arrange - Create complete setup
    $admin = User::factory()->admin()->create();
    $parentUser = User::factory()->create(['parent_user_id' => null]);
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
    $response->assertSee($parentUser->name, false); // false = no escape, search for raw HTML
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
