<?php

use App\Actions\CheckFirewallAction;
use App\Exceptions\FirewallException;
use App\Jobs\ProcessFirewallCheckJob;
use App\Models\{Host, User};
use Illuminate\Support\Facades\{Queue};
use Tests\FirewallTestConstants as TC;

test('action exists and has required methods', function () {
    expect(CheckFirewallAction::class)
        ->toHaveMethod('handle')
        ->toHaveMethod('asCommand');
});

test('handles invalid user gracefully', function () {
    $action = app(CheckFirewallAction::class);

    // Act & Assert - Should throw exception for invalid user
    expect(fn () => $action->handle(TC::TEST_IP, 999, 999, null))
        ->toThrow(FirewallException::class, 'Failed to start firewall check for IP '.TC::TEST_IP.': User with ID 999 not found');
});

test('handles invalid host gracefully', function () {
    $user = User::factory()->create();
    $action = app(CheckFirewallAction::class);

    // Act & Assert - Should throw exception for invalid host
    expect(fn () => $action->handle(TC::TEST_IP, $user->id, 999, null))
        ->toThrow(FirewallException::class, 'Failed to start firewall check for IP '.TC::TEST_IP.': Host with ID 999 not found');
});

test('handles invalid IP address gracefully', function () {
    $user = User::factory()->create();
    $host = Host::factory()->withWhmcsServerId(1)->create();
    $action = app(CheckFirewallAction::class);

    // Act & Assert - Should throw exception for invalid IP
    try {
        $action->handle('invalid-ip', $user->id, $host->id, null);
        $this->fail('A FirewallException was not thrown.');
    } catch (FirewallException $e) {
        expect($e->getMessage())->toStartWith('Failed to start firewall check for IP invalid-ip');
    }
});

test('returns develop response when develop parameter provided', function () {
    $user = User::factory()->create();
    $host = Host::factory()->withWhmcsServerId(1)->create();
    $action = app(CheckFirewallAction::class);

    // Act
    $result = $action->handle(
        ip: TC::TEST_IP,
        userId: $user->id,
        hostId: $host->id,
        develop: 'test_command'
    );

    // Assert
    expect($result)
        ->toBeArray()
        ->toHaveKey('success', true)
        ->toHaveKey('message', __('messages.firewall.develop_check_completed'))
        ->toHaveKey('data', ['develop_command' => 'test_command']);
});

test('dispatches job successfully on valid input', function () {
    // Arrange
    Queue::fake();

    $user = User::factory()->create();
    $host = Host::factory()->withWhmcsServerId(1)->create();
    $user->authorizedHosts()->attach($host->id, ['is_active' => true]);
    loginAsUser($user);

    $action = app(CheckFirewallAction::class);

    // Act
    $result = $action->handle(TC::TEST_IP, $user->id, $host->id);

    // Assert
    Queue::assertPushed(ProcessFirewallCheckJob::class, function ($job) use ($user, $host) {
        return $job->ip === TC::TEST_IP &&
               $job->userId === $user->id &&
               $job->hostId === $host->id;
    });

    expect($result)->toBe([
        'success' => true,
        'message' => __('messages.firewall.check_started'),
    ]);
});

test('does not dispatch job if validation fails', function () {
    // Arrange
    Queue::fake();

    $action = app(CheckFirewallAction::class);

    // Act & Assert
    // Use a try-catch block because we want to assert something *after* the exception
    try {
        $action->handle(TC::TEST_IP, 999, 999);
    } catch (FirewallException $e) {
        // Expected
    }

    // Assert that no job was pushed to the queue
    Queue::assertNotPushed(ProcessFirewallCheckJob::class);
});

// The tests 'executes full firewall analysis...' and 'handles firewall analysis exception...'
// are removed because that logic now lives in ProcessFirewallCheckJob and should be tested there.
// We've replaced them with tests that verify the action's new responsibility: dispatching the job.
