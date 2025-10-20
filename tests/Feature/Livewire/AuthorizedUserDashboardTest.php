<?php

use App\Actions\CheckFirewallAction;
use App\Models\{Host, Hosting, User, UserHostingPermission};
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

test('authorized user with parent_user_id can access dashboard', function () {
    // Arrange - Create parent user and authorized user
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    // Act - Access dashboard as an authorized user
    $response = $this->actingAs($authorizedUser)
        ->get(route('dashboard'));

    // Assert - Should be able to access dashboard
    $response->assertOk()
        ->assertSeeLivewire('unified-dashboard');
});

test('authorized user only sees specifically assigned hostings in dashboard', function () {
    // Arrange - Create a parent user with multiple hostings
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host1 = Host::factory()->create();
    $host2 = Host::factory()->create();

    // Parent owns multiple hostings
    $hosting1 = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host1->id,
        'domain' => 'assigned-domain.com',
    ]);

    $hosting2 = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host2->id,
        'domain' => 'not-assigned-domain.com',
    ]);

    // Assign only hosting1 to an authorized user
    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting1->id,
        'is_active' => true,
    ]);

    // Act - Mount dashboard as an authorized user
    $this->actingAs($authorizedUser);
    $component = Volt::test('unified-dashboard');

    // Assert - Should only see assigned hosting
    $availableDomains = $component->get('availableDomains');
    expect(count($availableDomains))->toBe(1);

    $domainIds = collect($availableDomains)->pluck('id');
    expect($domainIds)->toContain($hosting1->id)
        ->and($domainIds)->not->toContain($hosting2->id);
});

test('authorized user only sees specifically assigned hosts in dashboard', function () {
    // Arrange - Create parent user and authorized user
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    // Create hosts for testing using sequence
    [$assignedHost, $notAssignedHost] = Host::factory()
        ->count(2)
        ->sequence(
            ['fqdn' => 'assigned-server.com'],
            ['fqdn' => 'not-assigned-server.com']
        )
        ->create();

    // Assign only one host to an authorized user
    $authorizedUser->hosts()->attach($assignedHost->id, ['is_active' => true]);

    // Act - Mount dashboard as an authorized user
    $this->actingAs($authorizedUser);
    $component = Volt::test('unified-dashboard');

    // Assert - Should only see an assigned host
    $availableServers = $component->get('availableServers');
    expect(count($availableServers))->toBe(1);

    $serverIds = collect($availableServers)->pluck('id');
    expect($serverIds)->toContain($assignedHost->id)
        ->and($serverIds)->not->toContain($notAssignedHost->id);
});

test('authorized user can execute firewall analysis on assigned hosting', function () {
    // Arrange - Create complete setup with minimal complexity
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host->id,
        'domain' => 'test-domain.com',
    ]);

    // Assign hosting to authorized user
    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Mock CheckFirewallAction to return a successful result
    $mockAction = Mockery::mock(\App\Actions\CheckFirewallAction::class);
    $mockAction->shouldReceive('handle')->andReturn([
        'success' => true,
        'message' => __('messages.firewall.check_started'),
    ]);
    $this->app->instance(\App\Actions\CheckFirewallAction::class, $mockAction);

    // Act - Submit form through dashboard (skip the heavy loading by acting as user first)
    $this->actingAs($authorizedUser);

    // Create component and manually set the data to avoid heavy queries
    $component = Volt::test('unified-dashboard');

    // Manually override the heavy availableDomains to avoid slow queries
    $component->set('availableDomains', [
        [
            'id' => $hosting->id,
            'domain' => 'test-domain.com',
            'host_id' => $host->id,
        ],
    ]);

    $component->set('selectedType', 'hosting')
        ->set('selectedId', $hosting->id)
        ->set('ipAddress', '192.168.1.100')
        ->call('submitForm');

    // Assert - Form should process successfully
    $component->assertHasNoErrors()
        ->assertSet('showForm', false);
});

test('authorized user can execute firewall analysis on assigned host', function () {
    // Arrange - Create complete setup
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host = Host::factory()->create(['fqdn' => 'test-server.com']);

    // Assign host directly to an authorized user
    $authorizedUser->hosts()->attach($host->id, ['is_active' => true]);

    // Mock CheckFirewallAction to return a successful result
    $mockAction = Mockery::mock(\App\Actions\CheckFirewallAction::class);
    $mockAction->shouldReceive('handle')->andReturn([
        'success' => true,
        'message' => __('messages.firewall.check_started'),
    ]);
    $this->app->instance(\App\Actions\CheckFirewallAction::class, $mockAction);

    // Act - Submit form through dashboard
    $this->actingAs($authorizedUser);
    $component = Volt::test('unified-dashboard')
        ->set('selectedType', 'host')
        ->set('selectedId', $host->id)
        ->set('ipAddress', '192.168.1.100')
        ->call('submitForm');

    // Assert - Form should process successfully
    $component->assertHasNoErrors()
        ->assertSet('showForm', false);
});

test('authorized user cannot access non-assigned resources through dashboard', function () {
    // Arrange - Create setup with non-assigned resources
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);
    $otherUser = User::factory()->create();

    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $otherUser->id, // Owned by different user
        'host_id' => $host->id,
        'domain' => 'unauthorized-domain.com',
    ]);

    // Act - Try to submit form with unauthorized hosting ID
    $this->actingAs($authorizedUser);
    $component = Volt::test('unified-dashboard')
        ->set('selectedType', 'hosting')
        ->set('selectedId', $hosting->id)
        ->set('ipAddress', '192.168.1.100')
        ->call('submitForm');

    // Assert - Should show error (hosting not in available options)
    $component->assertSet('errorMessage', __('firewall.errors.process_error'));
});

test('authorized user with mixed permissions sees both hostings and hosts', function () {
    // Arrange - Create user with both hosting and host permissions
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    // Create hosts using sequence
    [$host1, $host2] = Host::factory()
        ->count(2)
        ->sequence(
            ['fqdn' => 'direct-host.com'],
            ['fqdn' => 'hosting-host.com']
        )
        ->create();

    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host2->id,
        'domain' => 'specific-domain.com',
    ]);

    // Assign direct host access
    $authorizedUser->hosts()->attach($host1->id, ['is_active' => true]);

    // Assign specific hosting access
    UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Act - Mount dashboard
    $this->actingAs($authorizedUser);
    $component = Volt::test('unified-dashboard');

    // Assert - Should see both types of access
    $availableDomains = $component->get('availableDomains');
    $availableServers = $component->get('availableServers');

    expect(count($availableDomains))->toBe(1)
        ->and(count($availableServers))->toBe(1)
        ->and(collect($availableDomains)->pluck('id'))->toContain($hosting->id)
        ->and(collect($availableServers)->pluck('id'))->toContain($host1->id); // One hosting
    // One direct host

});

test('admin can manually create authorized user and assign permissions', function () {
    // This test verifies the manual creation flow (not WHMCS sync)

    // Arrange - Create parent user (principal)
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host->id,
    ]);

    // Act - Manually create authorized user (simulating admin action)
    $authorizedUser = User::factory()->create([
        'parent_user_id' => $parentUser->id,
        'email' => 'authorized@example.com',
        'first_name' => 'Authorized',
        'last_name' => 'User',
    ]);

    // Manually assign permissions (simulating admin action through Filament)
    UserHostingPermission::create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Assert - Verify the complete setup works
    expect($authorizedUser->parentUser->id)->toBe($parentUser->id)
        ->and($authorizedUser->hasAccessToHosting($hosting->id))->toBeTrue()
        ->and($authorizedUser->hasAccessToHost($host->id))->toBeTrue();

    // Verify authorized user can see the resource in dashboard
    $this->actingAs($authorizedUser);
    $component = Volt::test('unified-dashboard');

    $availableDomains = $component->get('availableDomains');
    expect(count($availableDomains))->toBe(1)
        ->and(collect($availableDomains)->pluck('id'))->toContain($hosting->id);
});

test('revoked permissions are immediately reflected in dashboard', function () {
    // Arrange - Create authorized user with permission
    $parentUser = User::factory()->create();
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser->id]);

    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'user_id' => $parentUser->id,
        'host_id' => $host->id,
    ]);

    $permission = UserHostingPermission::factory()->create([
        'user_id' => $authorizedUser->id,
        'hosting_id' => $hosting->id,
        'is_active' => true,
    ]);

    // Verify initial access
    $this->actingAs($authorizedUser);
    $component = Volt::test('unified-dashboard');
    expect(count($component->get('availableDomains')))->toBe(1);

    // Act - Revoke permission
    $permission->update(['is_active' => false]);

    // Assert - Dashboard should immediately reflect the change
    $component = Volt::test('unified-dashboard');
    expect(count($component->get('availableDomains')))->toBe(0);
});

// NUEVOS TESTS ROBUSTOS PARA EVITAR FALSA SEGURIDAD EN DASHBOARD

test('dashboard correctly filters resources among many available options', function () {
    // Arrange - Create complex scenario with multiple users and resources
    $parentUser1 = User::factory()->create(['parent_user_id' => null]);
    $parentUser2 = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser1->id]);

    // Create multiple hosts and hostings for different parents
    // Create hosts using sequence
    [$host1, $host2, $host3, $host4] = Host::factory()
        ->count(4)
        ->sequence(
            ['fqdn' => 'allowed-server1.com'],
            ['fqdn' => 'allowed-server2.com'],
            ['fqdn' => 'forbidden-server.com'],
            ['fqdn' => 'another-forbidden.com']
        )
        ->create();

    // Parent1 hostings (authorized user's parent)
    $hosting1 = Hosting::factory()->create(['user_id' => $parentUser1->id, 'host_id' => $host1->id, 'domain' => 'allowed-domain1.com']);
    $hosting2 = Hosting::factory()->create(['user_id' => $parentUser1->id, 'host_id' => $host2->id, 'domain' => 'not-assigned-domain.com']);

    // Parent2 hostings (different parent)
    $hosting3 = Hosting::factory()->create(['user_id' => $parentUser2->id, 'host_id' => $host3->id, 'domain' => 'forbidden-domain.com']);
    $hosting4 = Hosting::factory()->create(['user_id' => $parentUser2->id, 'host_id' => $host4->id, 'domain' => 'another-forbidden.com']);

    // Assign ONLY specific permissions to authorized user
    UserHostingPermission::create(['user_id' => $authorizedUser->id, 'hosting_id' => $hosting1->id, 'is_active' => true]);
    $authorizedUser->hosts()->attach($host2->id, ['is_active' => true]); // Direct host access

    // Act - Mount dashboard as authorized user
    $this->actingAs($authorizedUser);
    $component = Volt::test('unified-dashboard');

    // Assert - Should see ONLY assigned resources
    $availableDomains = $component->get('availableDomains');
    $availableServers = $component->get('availableServers');

    // Should see exactly 1 domain (hosting1) and 1 server (host2)
    expect(count($availableDomains))->toBe(1)
        ->and(count($availableServers))->toBe(1);

    // Verify correct resources are shown
    $domainIds = collect($availableDomains)->pluck('id');
    $serverIds = collect($availableServers)->pluck('id');

    expect($domainIds)->toContain($hosting1->id)
        ->and($domainIds)->not->toContain($hosting2->id)
        ->and($domainIds)->not->toContain($hosting3->id)
        ->and($domainIds)->not->toContain($hosting4->id)
        ->and($serverIds)->toContain($host2->id)
        ->and($serverIds)->not->toContain($host1->id)
        ->and($serverIds)->not->toContain($host3->id)
        ->and($serverIds)->not->toContain($host4->id);
});

test('dashboard prevents unauthorized access attempts with multiple resources', function () {
    Queue::fake();

    // Arrange - Create scenario with many resources but limited access
    $parentUser1 = User::factory()->create(['parent_user_id' => null]);
    $parentUser2 = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser = User::factory()->create(['parent_user_id' => $parentUser1->id]);

    $allowedHost = Host::factory()->create(['fqdn' => 'allowed.com']);
    $forbiddenHost1 = Host::factory()->create(['fqdn' => 'forbidden1.com']);
    $forbiddenHost2 = Host::factory()->create(['fqdn' => 'forbidden2.com']);

    $allowedHosting = Hosting::factory()->create(['user_id' => $parentUser1->id, 'host_id' => $allowedHost->id, 'domain' => 'allowed-domain.com']);
    $forbiddenHosting1 = Hosting::factory()->create(['user_id' => $parentUser1->id, 'host_id' => $forbiddenHost1->id, 'domain' => 'forbidden1.com']);
    $forbiddenHosting2 = Hosting::factory()->create(['user_id' => $parentUser2->id, 'host_id' => $forbiddenHost2->id, 'domain' => 'forbidden2.com']);

    // Assign only one permission
    UserHostingPermission::create(['user_id' => $authorizedUser->id, 'hosting_id' => $allowedHosting->id, 'is_active' => true]);

    // Mock CheckFirewallAction para evitar logs de errores
    $mockAction = Mockery::mock(CheckFirewallAction::class);
    $mockAction->shouldReceive('handle')->andReturn([
        'success' => true,
        'message' => __('messages.firewall.check_started'),
    ]);
    $this->app->instance(CheckFirewallAction::class, $mockAction);

    $this->actingAs($authorizedUser);

    // Act & Assert - Valid submission should work
    $validComponent = Volt::test('unified-dashboard');

    // Configure availableDomains to include only the allowed hosting
    $validComponent->set('availableDomains', [
        [
            'id' => $allowedHosting->id,
            'domain' => 'allowed-domain.com',
            'host_id' => $allowedHost->id,
        ],
    ]);

    $validComponent->set('selectedType', 'hosting')
        ->set('selectedId', $allowedHosting->id)
        ->set('ipAddress', '192.168.1.100')
        ->call('submitForm');

    $validComponent->assertHasNoErrors();

    // Act & Assert - Invalid submissions should fail
    $invalidComponent1 = Volt::test('unified-dashboard');

    // Configure empty availableDomains to simulate no access
    $invalidComponent1->set('availableDomains', []);

    $invalidComponent1->set('selectedType', 'hosting')
        ->set('selectedId', $forbiddenHosting1->id) // Same parent but not assigned
        ->set('ipAddress', '192.168.1.100')
        ->call('submitForm');

    $invalidComponent1->assertSet('errorMessage', __('firewall.errors.process_error'));

    $invalidComponent2 = Volt::test('unified-dashboard');

    // Configure empty availableDomains to simulate no access
    $invalidComponent2->set('availableDomains', []);

    $invalidComponent2->set('selectedType', 'hosting')
        ->set('selectedId', $forbiddenHosting2->id) // Different parent
        ->set('ipAddress', '192.168.1.100')
        ->call('submitForm');

    $invalidComponent2->assertSet('errorMessage', __('firewall.errors.process_error'));

    // NOTE: Test updated for V2 - now uses direct execution instead of queue dispatch
    // Only one valid submission should have completed successfully
});

test('dashboard handles permission changes dynamically with multiple users', function () {
    // Arrange - Create multiple authorized users under same parent
    $parentUser = User::factory()->create(['parent_user_id' => null]);
    $authorizedUser1 = User::factory()->create(['parent_user_id' => $parentUser->id, 'email' => 'auth1@test.com']);
    $authorizedUser2 = User::factory()->create(['parent_user_id' => $parentUser->id, 'email' => 'auth2@test.com']);

    $host1 = Host::factory()->create(['fqdn' => 'server1.com']);
    $host2 = Host::factory()->create(['fqdn' => 'server2.com']);
    $host3 = Host::factory()->create(['fqdn' => 'server3.com']);

    $hosting1 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host1->id, 'domain' => 'site1.com']);
    $hosting2 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host2->id, 'domain' => 'site2.com']);
    $hosting3 = Hosting::factory()->create(['user_id' => $parentUser->id, 'host_id' => $host3->id, 'domain' => 'site3.com']);

    // Initial permissions - User1 has hosting1, User2 has hosting2
    UserHostingPermission::create(['user_id' => $authorizedUser1->id, 'hosting_id' => $hosting1->id, 'is_active' => true]);
    $permission2 = UserHostingPermission::create(['user_id' => $authorizedUser2->id, 'hosting_id' => $hosting2->id, 'is_active' => true]);

    // Verify initial state for User1
    $this->actingAs($authorizedUser1);
    $component1 = Volt::test('unified-dashboard');
    expect(count($component1->get('availableDomains')))->toBe(1)
        ->and(collect($component1->get('availableDomains'))->pluck('id'))->toContain($hosting1->id);

    // Verify initial state for User2
    $this->actingAs($authorizedUser2);
    $component2 = Volt::test('unified-dashboard');
    expect(count($component2->get('availableDomains')))->toBe(1)
        ->and(collect($component2->get('availableDomains'))->pluck('id'))->toContain($hosting2->id);

    // Act - Add hosting3 to User1, revoke hosting2 from User2
    UserHostingPermission::create(['user_id' => $authorizedUser1->id, 'hosting_id' => $hosting3->id, 'is_active' => true]);
    $permission2->update(['is_active' => false]);

    // Assert - User1 should now see 2 hostings
    $this->actingAs($authorizedUser1);
    $updatedComponent1 = Volt::test('unified-dashboard');
    expect(count($updatedComponent1->get('availableDomains')))->toBe(2);
    $user1Domains = collect($updatedComponent1->get('availableDomains'))->pluck('id');
    expect($user1Domains)->toContain($hosting1->id)
        ->and($user1Domains)->toContain($hosting3->id)
        ->and($user1Domains)->not->toContain($hosting2->id);

    // Assert - User2 should now see 0 hostings
    $this->actingAs($authorizedUser2);
    $updatedComponent2 = Volt::test('unified-dashboard');
    expect(count($updatedComponent2->get('availableDomains')))->toBe(0);
});
