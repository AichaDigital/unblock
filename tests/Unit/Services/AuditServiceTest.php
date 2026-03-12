<?php

declare(strict_types=1);

use App\Models\{Host, User};
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;

test('logFirewallCheck logs info with correct context', function () {
    Log::spy();

    $user = User::factory()->create();
    $host = Host::factory()->create();
    $service = new AuditService;

    $service->logFirewallCheck($user, $host, '10.0.0.1', true);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Firewall check operation audited', Mockery::on(function (array $context) use ($user, $host) {
            return $context['user_id'] === $user->id
                && $context['host_id'] === $host->id
                && $context['host_fqdn'] === $host->fqdn
                && $context['ip_address'] === '10.0.0.1'
                && $context['was_blocked'] === true
                && $context['operation'] === 'firewall_check';
        }));
});

test('logFirewallCheck logs was_blocked as false when IP was not blocked', function () {
    Log::spy();

    $user = User::factory()->create();
    $host = Host::factory()->create();
    $service = new AuditService;

    $service->logFirewallCheck($user, $host, '10.0.0.2', false);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Firewall check operation audited', Mockery::on(function (array $context) {
            return $context['was_blocked'] === false;
        }));
});

test('logFirewallCheckFailure logs error with correct context', function () {
    Log::spy();

    $user = User::factory()->create();
    $host = Host::factory()->create();
    $service = new AuditService;

    $service->logFirewallCheckFailure($user, $host, '10.0.0.1', 'SSH connection refused');

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Firewall check operation failed', Mockery::on(function (array $context) use ($user, $host) {
            return $context['user_id'] === $user->id
                && $context['host_id'] === $host->id
                && $context['host_fqdn'] === $host->fqdn
                && $context['ip_address'] === '10.0.0.1'
                && $context['error_message'] === 'SSH connection refused'
                && $context['operation'] === 'firewall_check_failure';
        }));
});
