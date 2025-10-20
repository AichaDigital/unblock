<?php

use App\Models\{Host, Hosting, User};
use Livewire\Volt\Volt;

test('unified dashboard component mounts correctly for admin', function () {
    // Create admin and hosts
    $admin = User::factory()->admin()->create();
    Host::factory()->count(3)->create();
    Hosting::factory()->count(5)->create();

    // Mount component as admin
    $response = $this->actingAs($admin)
        ->get(route('dashboard'));

    // Verify basic response
    $response->assertOk()
        ->assertSeeLivewire('unified-dashboard');
});

test('unified dashboard component mounts correctly for regular user', function () {
    // Create regular user with hostings
    $user = User::factory()->create();
    $host = Host::factory()->create();
    Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
    ]);

    // Grant user access to host
    $user->hosts()->attach($host->id, ['is_active' => true]);

    // Mount component as regular user
    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    // Verify basic response
    $response->assertOk()
        ->assertSeeLivewire('unified-dashboard');
});

test('admin can search by domain', function () {
    // Create admin and hosting
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create([
        'host_id' => $host->id,
        'domain' => 'test-domain.com',
    ]);

    // Act as admin
    $this->actingAs($admin);

    // Mock CheckFirewallAction to return a successful result
    $mockAction = Mockery::mock(\App\Actions\CheckFirewallAction::class);
    $mockAction->shouldReceive('handle')->andReturn([
        'success' => true,
        'message' => __('messages.firewall.check_started'),
    ]);
    $this->app->instance(\App\Actions\CheckFirewallAction::class, $mockAction);

    // Test domain search using V2 direct execution
    $component = Volt::test('unified-dashboard')
        ->set('selectedType', 'hosting')
        ->set('selectedId', $hosting->id)
        ->set('ipAddress', '192.168.1.1')
        ->call('submitForm');

    // Verify no errors and form transitions to success state
    $component->assertHasNoErrors()
        ->assertSet('showForm', false);
});

test('admin can search by server', function () {
    // Create admin and host
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();

    // Act as admin
    $this->actingAs($admin);

    // Mock CheckFirewallAction to return a successful result
    $mockAction = Mockery::mock(\App\Actions\CheckFirewallAction::class);
    $mockAction->shouldReceive('handle')->andReturn([
        'success' => true,
        'message' => __('messages.firewall.check_started'),
    ]);
    $this->app->instance(\App\Actions\CheckFirewallAction::class, $mockAction);

    // Test server search using V2 direct execution
    $component = Volt::test('unified-dashboard')
        ->set('selectedType', 'host')
        ->set('selectedId', $host->id)
        ->set('ipAddress', '192.168.1.1')
        ->call('submitForm');

    // Verify no errors and form transitions to success state
    $component->assertHasNoErrors()
        ->assertSet('showForm', false);
});

test('regular user can only access authorized domains', function () {
    // Create regular user and hosts
    $user = User::factory()->create();
    $authorizedHost = Host::factory()->create();
    $unauthorizedHost = Host::factory()->create();

    $authorizedHosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $authorizedHost->id,
        'domain' => 'authorized-domain.com',
    ]);

    $unauthorizedHosting = Hosting::factory()->create([
        'host_id' => $unauthorizedHost->id,
        'domain' => 'unauthorized-domain.com',
    ]);

    // Grant user access to authorized host only
    $user->hosts()->attach($authorizedHost->id, ['is_active' => true]);

    // Act as a regular user
    $this->actingAs($user);

    // Mount component and verify a user only sees authorized domains
    $component = Volt::test('unified-dashboard');

    // Verify a user only sees 1 domain (the authorized one)
    expect(count($component->get('availableDomains')))->toBe(1);

    // Verify the authorized domain is included
    $domains = collect($component->get('availableDomains'));
    expect($domains->pluck('id'))->toContain($authorizedHosting->id)
        ->and($domains->pluck('id'))->not->toContain($unauthorizedHosting->id);

    // Mock CheckFirewallAction for authorized access
    $mockAction = Mockery::mock(\App\Actions\CheckFirewallAction::class);
    $mockAction->shouldReceive('handle')->andReturn([
        'success' => true,
        'message' => __('messages.firewall.check_started'),
    ]);
    $this->app->instance(\App\Actions\CheckFirewallAction::class, $mockAction);

    // Test that user can access authorized domain
    $component = Volt::test('unified-dashboard')
        ->set('selectedType', 'hosting')
        ->set('selectedId', $authorizedHosting->id)
        ->set('ipAddress', '192.168.1.1')
        ->call('submitForm');

    $component->assertHasNoErrors()
        ->assertSet('showForm', false); // Should transition to success state

    // Test that a user cannot manually set unauthorized domain ID (should fail validation)
    // This tests the validation layer protection against manually crafted requests
    $component = Volt::test('unified-dashboard')
        ->set('selectedType', 'hosting')
        ->set('selectedId', $unauthorizedHosting->id)
        ->set('ipAddress', '192.168.1.1')
        ->call('submitForm');

    // The component should process the form, but validation should fail
    // because the hosting won't be found in the user's available domains during validation
    // This will result in a general error, not a specific validation error
    $component->assertSet('errorMessage', __('firewall.errors.process_error'));
});

test('dashboard shows validation error for missing selection', function () {
    // Create admin
    $admin = User::factory()->admin()->create();

    // Act as admin
    $this->actingAs($admin);

    // Submit a form without selection
    Volt::test('unified-dashboard')
        ->set('ipAddress', '192.168.1.1')
        ->call('submitForm')
        ->assertHasErrors(['selectedType']);

    // Submit form with type but no ID
    Volt::test('unified-dashboard')
        ->set('selectedType', 'hosting')
        ->set('ipAddress', '192.168.1.1')
        ->call('submitForm')
        ->assertHasErrors(['selectedId']);
});

test('dashboard shows validation error for missing ip', function () {
    // Create admin and host
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();

    // Act as admin
    $this->actingAs($admin);

    // Submit a form without IP (explicitly set to empty)
    Volt::test('unified-dashboard')
        ->set('selectedType', 'host')
        ->set('selectedId', $host->id)
        ->set('ipAddress', '')
        ->call('submitForm')
        ->assertHasErrors(['ipAddress']);
});

test('dashboard shows validation error for invalid ip', function () {
    // Create admin and host
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();

    // Act as admin
    $this->actingAs($admin);

    // Submit form with invalid IP
    Volt::test('unified-dashboard')
        ->set('selectedType', 'host')
        ->set('selectedId', $host->id)
        ->set('ipAddress', 'invalid-ip')
        ->call('submitForm')
        ->assertHasErrors(['ipAddress']);
});

test('dashboard processes valid form submission using V2 direct execution', function () {
    // Create admin and host
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();

    // Act as admin
    $this->actingAs($admin);

    // Mock CheckFirewallAction to return a successful result
    $mockAction = Mockery::mock(\App\Actions\CheckFirewallAction::class);
    $mockAction->shouldReceive('handle')->andReturn([
        'success' => true,
        'message' => __('messages.firewall.check_started'),
    ]);
    $this->app->instance(\App\Actions\CheckFirewallAction::class, $mockAction);

    // Submit valid form
    $component = Volt::test('unified-dashboard')
        ->set('selectedType', 'host')
        ->set('selectedId', $host->id)
        ->set('ipAddress', '192.168.1.1')
        ->call('submitForm');

    // Verify no errors and a form is hidden
    $component->assertHasNoErrors()
        ->assertSet('showForm', false);
});

test('dashboard detects client ip correctly', function () {
    // Create admin
    $admin = User::factory()->admin()->create();

    // Act as admin
    $this->actingAs($admin);

    // Verify that IP is detected (will be 127.0.0.1 in tests)
    $component = Volt::test('unified-dashboard');

    expect($component->get('detectedIp'))->not->toBeEmpty()
        ->and($component->get('ipAddress'))->toBe($component->get('detectedIp'));
});

test('dashboard selection methods work correctly', function () {
    // Create admin
    $admin = User::factory()->admin()->create();
    $host = Host::factory()->create();
    $hosting = Hosting::factory()->create();

    // Act as admin
    $this->actingAs($admin);

    // Test hosting selection
    $component = Volt::test('unified-dashboard')
        ->call('selectHosting', $hosting->id)
        ->assertSet('selectedType', 'hosting')
        ->assertSet('selectedId', $hosting->id)
        ->assertSet('searchTerm', '');

    // Test host selection
    $component->call('selectHost', $host->id)
        ->assertSet('selectedType', 'host')
        ->assertSet('selectedId', $host->id)
        ->assertSet('searchTerm', '');

    // Test clear selection
    $component->call('clearSelection')
        ->assertSet('selectedType', null)
        ->assertSet('selectedId', null);
});

test('dashboard ip helper works for non-admin users', function () {
    // Create regular user
    $user = User::factory()->create();
    $host = Host::factory()->create();
    Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $host->id,
    ]);

    // Act as a regular user
    $this->actingAs($user);

    // Test IP helper toggle
    $component = Volt::test('unified-dashboard')
        ->assertSet('showIpHelper', false)
        ->call('toggleIpHelper')
        ->assertSet('showIpHelper', true)
        ->call('useDetectedIp')
        ->assertSet('showIpHelper', false);

    // Verify IP was set to detect IP
    expect($component->get('ipAddress'))->toBe($component->get('detectedIp'));
});

test('dashboard reload works correctly', function () {
    // Create admin
    $admin = User::factory()->admin()->create();

    // Act as admin
    $this->actingAs($admin);

    // Test reload functionality
    Volt::test('unified-dashboard')
        ->set('showForm', false)
        ->set('searchTerm', 'test')
        ->set('selectedType', 'host')
        ->set('selectedId', 1)
        ->call('reload')
        ->assertSet('showForm', true)
        ->assertSet('searchTerm', '')
        ->assertSet('selectedType', null)
        ->assertSet('selectedId', null)
        ->assertRedirect(route('dashboard'));
});

test('admin sees all domains and servers', function () {
    // Create admin
    $admin = User::factory()->admin()->create();

    // Create hosts with sequence for unique FQDNs
    $hosts = Host::factory()
        ->count(3)
        ->sequence(
            ['fqdn' => 'server1.example.com'],
            ['fqdn' => 'server2.example.com'],
            ['fqdn' => 'server3.example.com'],
        )
        ->create();

    // Create hostings with sequence for unique domains and recycle hosts
    $hostings = Hosting::factory()
        ->count(5)
        ->recycle($hosts)
        ->sequence(
            ['domain' => 'site1.com'],
            ['domain' => 'site2.com'],
            ['domain' => 'site3.com'],
            ['domain' => 'site4.com'],
            ['domain' => 'site5.com'],
        )
        ->create();

    // Act as admin
    $this->actingAs($admin);

    // Mount component and verify admin sees all options
    $component = Volt::test('unified-dashboard');

    // Verify admin sees all hostings and hosts
    expect(count($component->get('availableDomains')))->toBe(5)
        ->and(count($component->get('availableServers')))->toBe(3);
});

test('regular user sees only authorized domains and servers', function () {
    // Create regular user
    $user = User::factory()->create();

    // Create hosts and hostings
    $authorizedHost = Host::factory()->create();
    $unauthorizedHost = Host::factory()->create();

    $authorizedHosting = Hosting::factory()->create([
        'user_id' => $user->id,
        'host_id' => $authorizedHost->id,
    ]);

    Hosting::factory()->create([
        'host_id' => $unauthorizedHost->id,
    ]);

    // Grant user access to authorized host only
    $user->hosts()->attach($authorizedHost->id, ['is_active' => true]);

    // Act as a regular user
    $this->actingAs($user);

    // Mount component and verify a user sees only authorized options
    $component = Volt::test('unified-dashboard');

    expect(count($component->get('availableDomains')))->toBe(1)
        ->and(count($component->get('availableServers')))->toBe(1);

    // Verify the authorized domain is included
    $domains = collect($component->get('availableDomains'));
    expect($domains->pluck('id'))->toContain($authorizedHosting->id);

    // Verify the authorized server is included
    $servers = collect($component->get('availableServers'));
    expect($servers->pluck('id'))->toContain($authorizedHost->id);
});

test('admin can search copy users and select one', function () {
    // Create admin and some users
    $admin = User::factory()->admin()->create();
    $user1 = User::factory()->create([
        'first_name' => 'Carlos',
        'last_name' => 'Prat Sánchez',
        'email' => 'cpratsanchez@gmail.com',
        'company_name' => 'GOTIC COSMETIC S.L',
    ]);
    $user2 = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@example.com',
        'company_name' => null, // Test with null company
    ]);

    // Act as admin
    $this->actingAs($admin);

    // Mount component
    $component = Volt::test('unified-dashboard');

    // Verify admin has the copy user properties
    expect($component->get('isAdmin'))->toBeTrue()
        ->and($component->get('copyUserSearch'))->toBe('')
        ->and($component->get('selectedCopyUserId'))->toBeNull()
        ->and($component->get('availableCopyUsers'))->toBeArray();

    // Test searching for users by first name
    $component->set('copyUserSearch', 'carlos')
        ->call('searchCopyUsers');

    expect(count($component->get('availableCopyUsers')))->toBeGreaterThan(0);

    // Verify it found the right user
    $foundUsers = collect($component->get('availableCopyUsers'));
    $foundUser = $foundUsers->firstWhere('email', 'cpratsanchez@gmail.com');
    expect($foundUser)->not->toBeNull()
        ->and($foundUser['first_name'])->toBe('Carlos')
        ->and($foundUser['last_name'])->toBe('Prat Sánchez')
        ->and($foundUser['company_name'])->toBe('GOTIC COSMETIC S.L');

    // Test selecting a user
    $component->call('selectCopyUser', $user1->id);

    expect($component->get('selectedCopyUserId'))->toBe($user1->id)
        ->and($component->get('copyUserSearch'))->toBe('')
        ->and($component->get('availableCopyUsers'))->toBeArray()->toBeEmpty();

    // Test clearing selection
    $component->call('clearCopyUser');

    expect($component->get('selectedCopyUserId'))->toBeNull()
        ->and($component->get('copyUserSearch'))->toBe('');
});

test('non-admin cannot access copy user functionality', function () {
    // Create regular user
    $user = User::factory()->create(['is_admin' => false]);

    // Act as regular user
    $this->actingAs($user);

    // Mount component
    $component = Volt::test('unified-dashboard');

    // Verify user is not admin and doesn't have copy user functionality
    expect($component->get('isAdmin'))->toBeFalse();

    // Try to search users (should not work for non-admins)
    $component->set('copyUserSearch', 'test')
        ->call('searchCopyUsers');

    expect($component->get('availableCopyUsers'))->toBeArray()->toBeEmpty();
});

test('selected copy user data persists after selection', function () {
    // Create admin and a user with complex name like Carlos Prat Sánchez
    $admin = User::factory()->admin()->create();
    $testUser = User::factory()->create([
        'first_name' => 'Carlos',
        'last_name' => 'Prat Sánchez',
        'email' => 'cpratsanchez@gmail.com',
        'company_name' => 'GOTIC COSMETIC S.L',
    ]);

    // Act as admin
    $this->actingAs($admin);

    // Mount component
    $component = Volt::test('unified-dashboard');

    // Search for the user
    $component->set('copyUserSearch', 'carlos')
        ->call('searchCopyUsers');

    // Verify user is found
    $availableUsers = $component->get('availableCopyUsers');
    expect(count($availableUsers))->toBeGreaterThan(0);

    // Select the user
    $component->call('selectCopyUser', $testUser->id);

    // Verify the user data is stored correctly
    $selectedUserData = $component->get('selectedCopyUserData');
    expect($selectedUserData)->not->toBeNull()
        ->and($selectedUserData['id'])->toBe($testUser->id)
        ->and($selectedUserData['first_name'])->toBe('Carlos')
        ->and($selectedUserData['last_name'])->toBe('Prat Sánchez')
        ->and($selectedUserData['email'])->toBe('cpratsanchez@gmail.com')
        ->and($selectedUserData['company_name'])->toBe('GOTIC COSMETIC S.L');

    // Verify search results are cleared but selected data remains
    expect($component->get('availableCopyUsers'))->toBeArray()->toBeEmpty()
        ->and($component->get('copyUserSearch'))->toBe('')
        ->and($component->get('selectedCopyUserId'))->toBe($testUser->id);
});
