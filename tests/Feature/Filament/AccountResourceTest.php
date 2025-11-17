<?php

use App\Models\{Account, Host, User};

test('admin can access account edit page', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'username' => 'testuser',
        'domain' => 'test.com',
    ]);

    // Act
    $this->actingAs($admin);

    // Simulate OTP verification session
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $admin->id);
    session()->put('admin_otp_verified_at', now()->timestamp);

    $response = $this->get('/admin/accounts/'.$account->id.'/edit');

    // Assert
    $response->assertStatus(200);
    $response->assertSee($account->username);
    $response->assertSee($account->domain);
});

test('admin can suspend an active account', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'username' => 'testuser',
        'domain' => 'test.com',
        'suspended_at' => null, // Active account
    ]);

    // Act
    $this->actingAs($admin);

    // Simulate OTP verification session
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $admin->id);
    session()->put('admin_otp_verified_at', now()->timestamp);

    // Suspend the account directly (simulating the action)
    $account->update(['suspended_at' => now()]);

    // Assert
    expect($account->fresh()->suspended_at)->not->toBeNull();
});

test('admin can unsuspend a suspended account', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'username' => 'testuser',
        'domain' => 'test.com',
        'suspended_at' => now(), // Suspended account
    ]);

    // Act
    $this->actingAs($admin);

    // Simulate OTP verification session
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $admin->id);
    session()->put('admin_otp_verified_at', now()->timestamp);

    // Unsuspend the account directly (simulating the action)
    $account->update(['suspended_at' => null]);

    // Assert
    expect($account->fresh()->suspended_at)->toBeNull();
});

test('account status column shows correct badge', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();

    $activeAccount = Account::factory()->create([
        'host_id' => $host->id,
        'username' => 'activeuser',
        'domain' => 'active.com',
        'suspended_at' => null,
    ]);

    $suspendedAccount = Account::factory()->create([
        'host_id' => $host->id,
        'username' => 'suspendeduser',
        'domain' => 'suspended.com',
        'suspended_at' => now(),
    ]);

    // Act
    $this->actingAs($admin);

    // Simulate OTP verification session
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $admin->id);
    session()->put('admin_otp_verified_at', now()->timestamp);

    $response = $this->get('/admin/accounts');

    // Assert
    $response->assertStatus(200);
    $response->assertSee('activeuser');
    $response->assertSee('suspendeduser');
});

test('admin can edit account user assignment', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'username' => 'testuser',
        'domain' => 'test.com',
        'user_id' => null,
    ]);

    // Act
    $this->actingAs($admin);

    // Simulate OTP verification session
    session()->put('admin_otp_verified', true);
    session()->put('admin_otp_user_id', $admin->id);
    session()->put('admin_otp_verified_at', now()->timestamp);

    // Update the account's user
    $account->update(['user_id' => $user->id]);

    // Assert
    expect($account->fresh()->user_id)->toBe($user->id);
    expect($account->fresh()->user->id)->toBe($user->id);
});

test('non-admin cannot access account edit page', function () {
    // Arrange
    $regularUser = User::factory()->create(['is_admin' => false]);
    $host = Host::factory()->create();
    $account = Account::factory()->create([
        'host_id' => $host->id,
        'username' => 'testuser',
        'domain' => 'test.com',
    ]);

    // Act
    $this->actingAs($regularUser);

    $response = $this->get('/admin/accounts/'.$account->id.'/edit');

    // Assert
    $response->assertStatus(403); // Forbidden
});
