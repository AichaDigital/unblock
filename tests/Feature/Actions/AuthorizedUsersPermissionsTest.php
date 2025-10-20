<?php

use App\Actions\CheckFirewallAction;
use App\Models\{Host, Hosting, User, UserHostingPermission};
use App\Services\Firewall\{FirewallAnalyzerFactory, FirewallAnalyzerInterface};
use Tests\FirewallTestConstants as TC;

// Basic tests for authorized users system
test('principal user can create authorized users', function () {
    $parentUser = User::factory()->create(['parent_user_id' => null]);

    $authorizedUser = User::factory()->create([
        'parent_user_id' => $parentUser->id,
    ]);

    expect($parentUser->authorizedUsers()->count())->toBe(1)
        ->and($authorizedUser->parentUser->id)->toBe($parentUser->id);
});

test('principal user owns hostings directly', function () {
    $parentUser = User::factory()->create();
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host->id,
    ]);

    expect($parentUser->hostings()->count())->toBe(1)
        ->and($parentUser->hasAccessToHosting($hosting->id))->toBeTrue();
});

test('principal user has access to directly assigned hosts', function () {
    $parentUser = User::factory()->create();
    $host = Host::factory()->create();

    // Assign direct host access
    $parentUser->hosts()->attach($host->id, ['is_active' => true]);

    expect($parentUser->hasAccessToHost($host->id))->toBeTrue();
});

// Tests for specific permission assignment
test('principal user can assign hosting access to authorized user', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host->id,
    ]);

    // Parent assigns hosting access to an authorized user
    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    expect($authorizedUser->hasAccessToHosting($hosting->id))->toBeTrue()
        ->and($authorizedUser->hasAccessToHost($host->id))->toBeTrue();
});

test('authorized user can have access to multiple hostings', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();

    $hosting1 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host->id]);
    $hosting2 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host->id]);

    // Assign access to different hostings
    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting1->id,
        'is_active' => true,
    ]);

    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting2->id,
        'is_active' => true,
    ]);

    expect($authorizedUser->hasAccessToHosting($hosting1->id))->toBeTrue()
        ->and($authorizedUser->hasAccessToHosting($hosting2->id))->toBeTrue();
});

test('principal user can assign host access to authorized user', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();

    // Parent assigns host access to an authorized user
    $authorizedUser->hosts()->attach($host->id, ['is_active' => true]);

    expect($authorizedUser->hasAccessToHost($host->id))->toBeTrue()
        ->and($authorizedUser->hosts()->count())->toBe(1);
});

test('authorized user can have mixed access to hostings and hosts', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host1 = Host::factory()->create();
    $host2 = Host::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host2->id]);

    // Direct host access
    $authorizedUser->hosts()->attach($host1->id, ['is_active' => true]);

    // Specific hosting access
    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    expect($authorizedUser->hasAccessToHost($host1->id))->toBeTrue()
        ->and($authorizedUser->hasAccessToHost($host2->id))->toBeTrue()
        ->and($authorizedUser->hasAccessToHosting($hosting->id))->toBeTrue();  // Direct host
});

// Access denial tests
test('authorized user cannot access non-assigned resources', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $otherUser = User::factory()->create();

    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $otherUser->id, 'host_id' => $host->id]);

    expect($authorizedUser->hasAccessToHost($host->id))->toBeFalse()
        ->and($authorizedUser->hasAccessToHosting($hosting->id))->toBeFalse();
});

test('hosting access can be revoked', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host->id]);

    $permission = UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    expect($authorizedUser->hasAccessToHosting($hosting->id))->toBeTrue();

    // Revoke access
    $permission->update(['is_active' => false]);

    expect($authorizedUser->fresh()->hasAccessToHosting($hosting->id))->toBeFalse();
});

test('host access can be revoked', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();

    // Assign host access
    $authorizedUser->hosts()->attach($host->id, ['is_active' => true]);
    expect($authorizedUser->hasAccessToHost($host->id))->toBeTrue();

    // Revoke access
    $authorizedUser->hosts()->updateExistingPivot($host->id, ['is_active' => false]);

    expect($authorizedUser->fresh()->hasAccessToHost($host->id))->toBeFalse();
});

// Tests for CheckFirewallAction (the only real action in the system)
test('CheckFirewallAction works with authorized user hosting access', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->withWhmcsServerId(1)->create([
        'ip' => TC::TEST_HOST_IP,
        'fqdn' => TC::TEST_HOST_FQDN,
        'alias' => 'test-host',
        'port_ssh' => TC::TEST_SSH_PORT,
        'panel' => 'cpanel',
        'admin' => TC::TEST_ADMIN_USER,
    ]);
    $hosting = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host->id]);

    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // V2: Mock all dependencies for CheckFirewallAction
    $sshManager = Mockery::mock(\App\Services\SshConnectionManager::class);
    $analyzerFactory = Mockery::mock(\App\Services\Firewall\FirewallAnalyzerFactory::class);
    $unblocker = Mockery::mock(\App\Services\FirewallUnblocker::class);
    $reportGenerator = Mockery::mock(\App\Services\ReportGenerator::class);
    $auditService = Mockery::mock(\App\Services\AuditService::class);

    // Configurar el mock de SshSession
    $sshSession = Mockery::mock(\App\Services\SshSession::class);
    $sshSession->shouldReceive('cleanup')->zeroOrMoreTimes();

    // Configurar createSession para que devuelva el mock de SshSession
    $sshManager->shouldReceive('createSession')
        ->zeroOrMoreTimes()
        ->andReturn($sshSession);

    $action = new CheckFirewallAction(
        $sshManager,
        $analyzerFactory,
        $unblocker,
        $reportGenerator,
        $auditService
    );

    $result = $action->handle(
        ip: TC::TEST_IP,
        userId: $authorizedUser->id,
        hostId: $host->id,
        develop: 'test_command'
    );

    expect($result['success'])->toBeTrue();
});

test('CheckFirewallAction works with authorized user host access', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->withWhmcsServerId(1)->create([
        'ip' => TC::TEST_HOST_IP,
        'fqdn' => TC::TEST_HOST_FQDN,
        'alias' => 'test-host',
        'port_ssh' => TC::TEST_SSH_PORT,
        'panel' => 'cpanel',
        'admin' => TC::TEST_ADMIN_USER,
    ]);

    // Direct host access
    $authorizedUser->hosts()->attach($host->id, ['is_active' => true]);

    // V2: Mock all dependencies for CheckFirewallAction
    $sshManager = Mockery::mock(\App\Services\SshConnectionManager::class);
    $analyzerFactory = Mockery::mock(\App\Services\Firewall\FirewallAnalyzerFactory::class);
    $unblocker = Mockery::mock(\App\Services\FirewallUnblocker::class);
    $reportGenerator = Mockery::mock(\App\Services\ReportGenerator::class);
    $auditService = Mockery::mock(\App\Services\AuditService::class);

    // Configurar el mock de SshSession
    $sshSession = Mockery::mock(\App\Services\SshSession::class);
    $sshSession->shouldReceive('cleanup')->zeroOrMoreTimes();

    // Configurar createSession para que devuelva el mock de SshSession
    $sshManager->shouldReceive('createSession')
        ->zeroOrMoreTimes()
        ->andReturn($sshSession);

    $action = new CheckFirewallAction(
        $sshManager,
        $analyzerFactory,
        $unblocker,
        $reportGenerator,
        $auditService
    );

    $result = $action->handle(
        ip: TC::TEST_IP,
        userId: $authorizedUser->id,
        hostId: $host->id,
        develop: 'test_command'
    );

    expect($result['success'])->toBeTrue();
});

test('CheckFirewallAction denies access for unauthorized user', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->withWhmcsServerId(1)->create([
        'ip' => TC::TEST_HOST_IP,
        'fqdn' => TC::TEST_HOST_FQDN,
        'alias' => 'test-host',
        'port_ssh' => TC::TEST_SSH_PORT,
        'panel' => 'cpanel',
        'admin' => TC::TEST_ADMIN_USER,
    ]);

    // No access assigned

    // V2: Mock all dependencies for CheckFirewallAction
    $sshManager = Mockery::mock(\App\Services\SshConnectionManager::class);
    $analyzerFactory = Mockery::mock(\App\Services\Firewall\FirewallAnalyzerFactory::class);
    $unblocker = Mockery::mock(\App\Services\FirewallUnblocker::class);
    $reportGenerator = Mockery::mock(\App\Services\ReportGenerator::class);
    $auditService = Mockery::mock(\App\Services\AuditService::class);

    // Configurar el mock de SshSession
    $sshSession = Mockery::mock(\App\Services\SshSession::class);
    $sshSession->shouldReceive('cleanup')->zeroOrMoreTimes();

    // Configurar createSession para que devuelva el mock de SshSession
    $sshManager->shouldReceive('createSession')
        ->zeroOrMoreTimes()
        ->andReturn($sshSession);

    $action = new CheckFirewallAction(
        $sshManager,
        $analyzerFactory,
        $unblocker,
        $reportGenerator,
        $auditService
    );

    // Test access validation (no develop mode) - should throw exception for unauthorized access
    expect(fn () => $action->handle(
        ip: TC::TEST_IP,
        userId: $authorizedUser->id,
        hostId: $host->id,
        develop: null // No develop mode - test real validation
    ))->toThrow(\Exception::class); // Should throw exception for unauthorized access
});

// Test de casos complejos
test('multiple authorized users can access same resources independently', function () {
    $parentUser = User::factory()->create();
    $authorizedUser1 = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $authorizedUser2 = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host->id]);

    // Both users get access to the same hosting
    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser1->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser2->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    expect($authorizedUser1->hasAccessToHosting($hosting->id))->toBeTrue()
        ->and($authorizedUser2->hasAccessToHosting($hosting->id))->toBeTrue();
});

test('principal user retains access regardless of authorized user permissions', function () {
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host->id]);

    // Authorized user gets access
    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Parent should still have access as goes an owner
    expect($parentUser->hasAccessToHosting($hosting->id))->toBeTrue()
        ->and($authorizedUser->hasAccessToHosting($hosting->id))->toBeTrue();
});

// NUEVOS TESTS ROBUSTOS PARA EVITAR FALSA SEGURIDAD

test('authorized user only sees assigned resources among many available', function () {
    // Arrange - Create multiple parent users with multiple resources
    $parentUser1 = User::factory()->create(['parent_user_id' => null]);
    $parentUser2 = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser1->id]);

    // Create multiple hosts and hostings for different parents
    $host1 = Host::factory()->create(['fqdn' => 'host1.example.com']);
    $host2 = Host::factory()->create(['fqdn' => 'host2.example.com']);
    $host3 = Host::factory()->create(['fqdn' => 'host3.example.com']);

    // Parent1 hostings (authorized user's parent)
    $hosting1 = Hosting::factory()->create(['user_id' => $parentUser1->id, 'host_id' => $host1->id, 'domain' => 'domain1.com']);
    $hosting2 = Hosting::factory()->create(['user_id' => $parentUser1->id, 'host_id' => $host2->id, 'domain' => 'domain2.com']);

    // Parent2 hostings (different parent)
    $hosting3 = Hosting::factory()->create(['user_id' => $parentUser2->id, 'host_id' => $host3->id, 'domain' => 'domain3.com']);

    // Assign ONLY hosting1 to an authorized user
    // (not hosting2 from the same parent, not hosting3 from different parent)
    UserHostingPermission::create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting1->id,
        'is_active' => true,
    ]);

    // Assert - Authorized user should ONLY have access to hosting1
    expect($authorizedUser->hasAccessToHosting($hosting1->id))->toBeTrue()
        ->and($authorizedUser->hasAccessToHosting($hosting2->id))->toBeFalse()
        ->and($authorizedUser->hasAccessToHosting($hosting3->id))->toBeFalse()
        ->and($parentUser1->hasAccessToHosting($hosting1->id))->toBeTrue()
        ->and($parentUser1->hasAccessToHosting($hosting2->id))->toBeTrue()
        ->and($parentUser1->hasAccessToHosting($hosting3->id))->toBeFalse()
        ->and($parentUser2->hasAccessToHosting($hosting3->id))->toBeTrue()
        ->and($parentUser2->hasAccessToHosting($hosting1->id))->toBeFalse()
        ->and($parentUser2->hasAccessToHosting($hosting2->id))->toBeFalse();
});

test('multiple authorized users with different permissions work independently', function () {
    // Arrange - Create one parent with multiple authorized users
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser1 = User::factory()->create(['parent_user_id' => $parentUser->id, 'email' => 'auth1@test.com']);
    $authorizedUser2 = User::factory()->create(['parent_user_id' => $parentUser->id, 'email' => 'auth2@test.com']);
    $authorizedUser3 = User::factory()->create(['parent_user_id' => $parentUser->id, 'email' => 'auth3@test.com']);

    // Create multiple resources
    $host1 = Host::factory()->create(['fqdn' => 'server1.com']);
    $host2 = Host::factory()->create(['fqdn' => 'server2.com']);
    $host3 = Host::factory()->create(['fqdn' => 'server3.com']);

    $hosting1 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host1->id, 'domain' => 'site1.com']);
    $hosting2 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host2->id, 'domain' => 'site2.com']);
    $hosting3 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host3->id, 'domain' => 'site3.com']);

    // Assign different permissions to each authorized user
    // User1: hosting1 + direct host2 access
    UserHostingPermission::create(['user_id' => $authorizedUser1->id, 'hosting_id' => $hosting1->id, 'is_active' => true]);
    $authorizedUser1->hosts()->attach($host2->id, ['is_active' => true]);

    // User2: hosting2 + hosting3
    UserHostingPermission::create(['user_id' => $authorizedUser2->id, 'hosting_id' => $hosting2->id, 'is_active' => true]);
    UserHostingPermission::create(['user_id' => $authorizedUser2->id, 'hosting_id' => $hosting3->id, 'is_active' => true]);

    // User3: direct host1 access only
    $authorizedUser3->hosts()->attach($host1->id, ['is_active' => true]);

    // Assert - Each user should have ONLY their assigned permissions

    // User1 assertions
    expect($authorizedUser1->hasAccessToHosting($hosting1->id))->toBeTrue()
        ->and($authorizedUser1->hasAccessToHosting($hosting2->id))->toBeFalse()
        ->and($authorizedUser1->hasAccessToHosting($hosting3->id))->toBeFalse()
        ->and($authorizedUser1->hasAccessToHost($host1->id))->toBeTrue()
        ->and($authorizedUser1->hasAccessToHost($host2->id))->toBeTrue()
        ->and($authorizedUser1->hasAccessToHost($host3->id))->toBeFalse()
        ->and($authorizedUser2->hasAccessToHosting($hosting1->id))->toBeFalse()
        ->and($authorizedUser2->hasAccessToHosting($hosting2->id))->toBeTrue()
        ->and($authorizedUser2->hasAccessToHosting($hosting3->id))->toBeTrue()
        ->and($authorizedUser2->hasAccessToHost($host1->id))->toBeFalse()
        ->and($authorizedUser2->hasAccessToHost($host2->id))->toBeTrue()
        ->and($authorizedUser2->hasAccessToHost($host3->id))->toBeTrue()
        ->and($authorizedUser3->hasAccessToHosting($hosting1->id))->toBeFalse()
        ->and($authorizedUser3->hasAccessToHosting($hosting2->id))->toBeFalse()
        ->and($authorizedUser3->hasAccessToHosting($hosting3->id))->toBeFalse()
        ->and($authorizedUser3->hasAccessToHost($host1->id))->toBeTrue()
        ->and($authorizedUser3->hasAccessToHost($host2->id))->toBeFalse()
        ->and($authorizedUser3->hasAccessToHost($host3->id))->toBeFalse();
});

test('permission revocation works correctly among multiple permissions', function () {
    // Arrange - Create a user with multiple permissions
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host1 = Host::factory()->create(['fqdn' => 'server1.com']);
    $host2 = Host::factory()->create(['fqdn' => 'server2.com']);
    $host3 = Host::factory()->create(['fqdn' => 'server3.com']);

    $hosting1 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host1->id, 'domain' => 'site1.com']);
    $hosting2 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host2->id, 'domain' => 'site2.com']);

    // Assign multiple permissions
    $permission1 = UserHostingPermission::create(['user_id' => $authorizedUser->id, 'hosting_id' => $hosting1->id, 'is_active' => true]);
    UserHostingPermission::create(['user_id' => $authorizedUser->id, 'hosting_id' => $hosting2->id, 'is_active' => true]);
    $authorizedUser->hosts()->attach($host3->id, ['is_active' => true]);

    // Verify initial state - all permissions active
    expect($authorizedUser->hasAccessToHosting($hosting1->id))->toBeTrue()
        ->and($authorizedUser->hasAccessToHosting($hosting2->id))->toBeTrue()
        ->and($authorizedUser->hasAccessToHost($host3->id))->toBeTrue();

    // Act - Revoke only permission1
    $permission1->update(['is_active' => false]);

    // Assert - Only permission1 should be revoked, others remain
    expect($authorizedUser->fresh()->hasAccessToHosting($hosting1->id))->toBeFalse()
        ->and($authorizedUser->fresh()->hasAccessToHosting($hosting2->id))->toBeTrue()
        ->and($authorizedUser->fresh()->hasAccessToHost($host3->id))->toBeTrue(); // Revoked

    // Act - Revoke host permission
    $authorizedUser->hosts()->updateExistingPivot($host3->id, ['is_active' => false]);

    // Assert - Host permission revoked, hosting permission still active
    expect($authorizedUser->fresh()->hasAccessToHosting($hosting1->id))->toBeFalse()
        ->and($authorizedUser->fresh()->hasAccessToHosting($hosting2->id))->toBeTrue()
        ->and($authorizedUser->fresh()->hasAccessToHost($host3->id))->toBeFalse(); // Still revoked
});

test('CheckFirewallAction correctly validates permissions among multiple resources', function () {
    // Arrange - Create a complex scenario with multiple users and resources
    $parentUser1 = User::factory()->create(['parent_user_id' => null]);
    $parentUser2 = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser1->id]);

    $host1 = Host::factory()->withWhmcsServerId(1)->create([
        'ip' => '192.168.1.10',
        'fqdn' => 'server1.com',
        'port_ssh' => 22,
        'panel' => 'cpanel',
        'admin' => 'root',
    ]);

    $host2 = Host::factory()->withWhmcsServerId(2)->create([
        'ip' => '192.168.1.20',
        'fqdn' => 'server2.com',
        'port_ssh' => 22,
        'panel' => 'cpanel',
        'admin' => 'root',
    ]);

    // Parent1 owns hosting on host1, Parent2 owns hosting on host2
    $hosting1 = Hosting::factory()->create(['user_id' => $parentUser1->id, 'host_id' => $host1->id, 'domain' => 'allowed.com']);
    Hosting::factory()->create(['user_id' => $parentUser2->id, 'host_id' => $host2->id, 'domain' => 'forbidden.com']);

    // Assign only hosting1 to an authorized user
    UserHostingPermission::create(['user_id' => $authorizedUser->id, 'hosting_id' => $hosting1->id, 'is_active' => true]);

    // V2: Mock all dependencies for CheckFirewallAction
    $sshManager = Mockery::mock(\App\Services\SshConnectionManager::class);
    $analyzerFactory = Mockery::mock(\App\Services\Firewall\FirewallAnalyzerFactory::class);
    $unblocker = Mockery::mock(\App\Services\FirewallUnblocker::class);
    $reportGenerator = Mockery::mock(\App\Services\ReportGenerator::class);
    $auditService = Mockery::mock(\App\Services\AuditService::class);

    // Configurar el mock de SshSession
    $sshSession = Mockery::mock(\App\Services\SshSession::class);
    $sshSession->shouldReceive('cleanup')->zeroOrMoreTimes();

    // Configurar createSession para que devuelva el mock de SshSession
    $sshManager->shouldReceive('createSession')
        ->zeroOrMoreTimes()
        ->andReturn($sshSession);

    $action = new CheckFirewallAction(
        $sshManager,
        $analyzerFactory,
        $unblocker,
        $reportGenerator,
        $auditService
    );

    // Act & Assert - Should succeed for allowed host
    $result1 = $action->handle(
        ip: '192.168.1.100',
        userId: $authorizedUser->id,
        hostId: $host1->id,
        develop: 'test_command'
    );
    expect($result1['success'])->toBeTrue();

    // Act & Assert - Should fail for forbidden host
    expect(fn () => $action->handle(
        ip: '192.168.1.100',
        userId: $authorizedUser->id,
        hostId: $host2->id,
        develop: null // No develop mode - test real validation
    ))->toThrow(\Exception::class); // Should throw exception for unauthorized access
});

beforeEach(function () {
    // ... existing code ...
    $this->analyzerFactory = mock(FirewallAnalyzerFactory::class);
    $this->analyzer = mock(FirewallAnalyzerInterface::class);

    // Mock analyzer factory expectations
    $this->analyzerFactory->shouldReceive('createForHost')
        ->withAnyArgs()
        ->andReturn($this->analyzer);

    // Mock analyzer expectations
    $this->analyzer->shouldReceive('supports')
        ->withAnyArgs()
        ->andReturn(true);

    $this->analyzer->shouldReceive('analyze')
        ->withAnyArgs()
        ->andReturn(new \App\Services\Firewall\FirewallAnalysisResult(true, []));

    // ... existing code ...

    $this->app->instance(FirewallAnalyzerFactory::class, $this->analyzerFactory);
});
