<?php

declare(strict_types=1);

use App\Enums\PanelType;
use App\Exceptions\{CommandExecutionException, CsfServiceException};
use App\Models\Host;
use App\Services\Firewall\V2\FirewallUnblocker;
use App\Services\{FirewallService, SshConnectionManager, SshSession};
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Log::spy();

    $this->firewallService = Mockery::mock(FirewallService::class);
    $this->sshManager = Mockery::mock(SshConnectionManager::class);
    $this->session = Mockery::mock(SshSession::class);
    $this->unblocker = new FirewallUnblocker($this->firewallService, $this->sshManager);
});

// ============================================================================
// SCENARIO 1: CSF Unblock Operations
// ============================================================================

test('unblockFromCsf successfully unblocks IP', function () {
    $host = Host::factory()->create(['panel' => PanelType::CPANEL, 'fqdn' => 'test.example.com']);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('getSshKeyPath')->andReturn('/path/to/key');
    $this->session->allows('cleanup');

    // Mock FirewallService calls
    $this->firewallService->allows('checkProblems')
        ->with($host, '/path/to/key', 'unblock', '192.168.1.1')
        ->andReturn('IP 192.168.1.1 removed from deny list');

    $this->firewallService->allows('checkProblems')
        ->with($host, '/path/to/key', 'whitelist', '192.168.1.1')
        ->andReturn('IP 192.168.1.1 whitelisted for 24 hours');

    $result = $this->unblocker->unblockFromCsf('192.168.1.1', $host);

    expect($result)->toHaveKey('unblock')
        ->and($result)->toHaveKey('whitelist')
        ->and($result['unblock']['success'])->toBeTrue()
        ->and($result['whitelist']['success'])->toBeTrue();

    Log::shouldHaveReceived('info')
        ->with('CSF unblock operations completed successfully', Mockery::any());
});

test('unblockFromCsf throws CsfServiceException on failure', function () {
    // Mock Log channel to prevent error
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')->andReturn(null);

    $host = Host::factory()->create(['panel' => PanelType::CPANEL]);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('getSshKeyPath')->andReturn('/path/to/key');
    $this->session->expects('cleanup')->once();

    $this->firewallService->allows('checkProblems')
        ->andThrow(new Exception('Connection failed'));

    expect(fn () => $this->unblocker->unblockFromCsf('192.168.1.1', $host))
        ->toThrow(CsfServiceException::class);
});

test('unblockFromCsf cleans up session even on exception', function () {
    // Mock Log channel
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')->andReturn(null);

    $host = Host::factory()->create(['panel' => PanelType::CPANEL]);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('getSshKeyPath')->andReturn('/path/to/key');
    $this->session->expects('cleanup')->once();

    $this->firewallService->allows('checkProblems')
        ->andThrow(new Exception('Error'));

    try {
        $this->unblocker->unblockFromCsf('192.168.1.1', $host);
    } catch (Exception $e) {
        // Expected
    }
});

// ============================================================================
// SCENARIO 2: BFM Removal Operations
// ============================================================================

test('removeFromBfmBlacklist skips for non-DirectAdmin hosts', function () {
    $host = Host::factory()->create(['panel' => PanelType::CPANEL]);

    $result = $this->unblocker->removeFromBfmBlacklist('192.168.1.1', $host);

    expect($result['skipped'])->toBeTrue()
        ->and($result['reason'])->toBe('Host is not DirectAdmin');
});

test('removeFromBfmBlacklist removes IP from DirectAdmin host', function () {
    $host = Host::factory()->create(['panel' => PanelType::DIRECTADMIN, 'fqdn' => 'test.example.com']);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');

    // Dynamic mock
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'sed')) {
                return ''; // sed command output
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->removeFromBfmBlacklist('192.168.1.1', $host);

    expect($result)->toHaveKey('removal')
        ->and($result)->toHaveKey('verification')
        ->and($result['removal']['success'])->toBeTrue()
        ->and($result['verification']['removed'])->toBeTrue();

    Log::shouldHaveReceived('info')
        ->with('BFM removal operations completed successfully', Mockery::any());
});

test('removeFromBfmBlacklist throws CommandExecutionException on failure', function () {
    $host = Host::factory()->create(['panel' => PanelType::DIRECTADMIN]);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->expects('cleanup')->once();

    $this->session->allows('execute')
        ->andThrow(new Exception('File not found'));

    expect(fn () => $this->unblocker->removeFromBfmBlacklist('192.168.1.1', $host))
        ->toThrow(CommandExecutionException::class);

    Log::shouldHaveReceived('error')
        ->with('BFM removal operations failed', Mockery::any());
});

test('removeFromBfmBlacklist cleans up session on exception', function () {
    $host = Host::factory()->create(['panel' => PanelType::DIRECTADMIN]);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->expects('cleanup')->once();

    $this->session->allows('execute')
        ->andThrow(new Exception('Error'));

    try {
        $this->unblocker->removeFromBfmBlacklist('192.168.1.1', $host);
    } catch (Exception $e) {
        // Expected
    }
});

// ============================================================================
// SCENARIO 3: Complete Unblock Operation
// ============================================================================

test('performCompleteUnblock executes CSF only for cPanel', function () {
    $host = Host::factory()->create(['panel' => PanelType::CPANEL, 'fqdn' => 'test.example.com']);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('getSshKeyPath')->andReturn('/path/to/key');
    $this->session->allows('cleanup');

    $this->firewallService->allows('checkProblems')->andReturn('success');

    $result = $this->unblocker->performCompleteUnblock('192.168.1.1', $host);

    expect($result)->toHaveKey('csf')
        ->and($result)->not->toHaveKey('bfm');
});

test('performCompleteUnblock executes CSF and BFM for DirectAdmin', function () {
    $host = Host::factory()->create(['panel' => PanelType::DIRECTADMIN, 'fqdn' => 'test.example.com']);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('getSshKeyPath')->andReturn('/path/to/key');
    $this->session->allows('cleanup');

    $this->firewallService->allows('checkProblems')->andReturn('success');

    // Mock BFM commands
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'sed')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->performCompleteUnblock('192.168.1.1', $host);

    expect($result)->toHaveKey('csf')
        ->and($result)->toHaveKey('bfm');
});

// ============================================================================
// SCENARIO 4: BFM Command Building
// ============================================================================

test('buildBfmRemovalCommand escapes IP correctly', function () {
    // We can't test private methods directly, but we can verify the sed command
    // is executed correctly through removeFromBfmBlacklist
    $host = Host::factory()->create(['panel' => PanelType::DIRECTADMIN]);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');

    $sedCommandExecuted = false;
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) use (&$sedCommandExecuted) {
            if (str_contains($command, 'sed') && str_contains($command, '192\.168\.1\.1')) {
                $sedCommandExecuted = true;

                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $this->unblocker->removeFromBfmBlacklist('192.168.1.1', $host);

    expect($sedCommandExecuted)->toBeTrue();
});

// ============================================================================
// SCENARIO 5: IP Validation
// ============================================================================

test('validateIpAddress accepts valid IPv4', function () {
    expect($this->unblocker->validateIpAddress('192.168.1.1'))->toBeTrue()
        ->and($this->unblocker->validateIpAddress('10.0.0.1'))->toBeTrue()
        ->and($this->unblocker->validateIpAddress('203.0.113.42'))->toBeTrue();
});

test('validateIpAddress accepts valid IPv6', function () {
    expect($this->unblocker->validateIpAddress('2001:0db8:85a3::8a2e:0370:7334'))->toBeTrue()
        ->and($this->unblocker->validateIpAddress('2001:db8::1'))->toBeTrue()
        ->and($this->unblocker->validateIpAddress('::1'))->toBeTrue();
});

test('validateIpAddress rejects invalid IPs', function () {
    expect($this->unblocker->validateIpAddress('999.999.999.999'))->toBeFalse()
        ->and($this->unblocker->validateIpAddress('not-an-ip'))->toBeFalse()
        ->and($this->unblocker->validateIpAddress('192.168.1'))->toBeFalse()
        ->and($this->unblocker->validateIpAddress(''))->toBeFalse();
});

// ============================================================================
// SCENARIO 6: Unblock Status
// ============================================================================

test('getUnblockStatus returns success for CSF operations', function () {
    $results = [
        'csf' => [
            'unblock' => ['success' => true],
            'whitelist' => ['success' => true],
        ],
    ];

    $status = $this->unblocker->getUnblockStatus($results);

    expect($status['overall_success'])->toBeTrue()
        ->and($status['csf_success'])->toBeTrue()
        ->and($status['operations_performed'])->toContain('csf_unblock')
        ->and($status['operations_performed'])->toContain('csf_whitelist');
});

test('getUnblockStatus returns success for CSF and BFM operations', function () {
    $results = [
        'csf' => [
            'unblock' => ['success' => true],
            'whitelist' => ['success' => true],
        ],
        'bfm' => [
            'removal' => ['success' => true],
        ],
    ];

    $status = $this->unblocker->getUnblockStatus($results);

    expect($status['overall_success'])->toBeTrue()
        ->and($status['csf_success'])->toBeTrue()
        ->and($status['bfm_success'])->toBeTrue()
        ->and($status['operations_performed'])->toContain('bfm_removal');
});

test('getUnblockStatus returns failure when CSF operations fail', function () {
    $results = [
        'csf' => [
            'unblock' => ['success' => false],
            'whitelist' => ['success' => true],
        ],
    ];

    $status = $this->unblocker->getUnblockStatus($results);

    expect($status['overall_success'])->toBeFalse()
        ->and($status['csf_success'])->toBeFalse();
});

test('getUnblockStatus handles mixed success/failure', function () {
    $results = [
        'csf' => [
            'unblock' => ['success' => true],
            'whitelist' => ['success' => true],
        ],
        'bfm' => [
            'removal' => ['success' => false],
        ],
    ];

    $status = $this->unblocker->getUnblockStatus($results);

    expect($status['overall_success'])->toBeFalse()
        ->and($status['csf_success'])->toBeTrue()
        ->and($status['bfm_success'])->toBeFalse();
});

// ============================================================================
// SCENARIO 7: Logging
// ============================================================================

test('unblockFromCsf logs operations', function () {
    $host = Host::factory()->create(['panel' => PanelType::CPANEL, 'fqdn' => 'test.example.com']);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('getSshKeyPath')->andReturn('/path/to/key');
    $this->session->allows('cleanup');

    $this->firewallService->allows('checkProblems')->andReturn('success');

    $this->unblocker->unblockFromCsf('192.168.1.1', $host);

    Log::shouldHaveReceived('info')
        ->with('CSF unblock operations completed successfully', Mockery::on(function ($context) {
            return $context['ip'] === '192.168.1.1' &&
                   $context['host'] === 'test.example.com' &&
                   $context['operations'] === ['unblock', 'whitelist'];
        }));
});

test('removeFromBfmBlacklist logs operations', function () {
    $host = Host::factory()->create(['panel' => PanelType::DIRECTADMIN, 'fqdn' => 'test.example.com']);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');

    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'sed')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $this->unblocker->removeFromBfmBlacklist('192.168.1.1', $host);

    Log::shouldHaveReceived('info')
        ->with('BFM removal operations completed successfully', Mockery::on(function ($context) {
            return $context['ip'] === '192.168.1.1' &&
                   $context['host'] === 'test.example.com' &&
                   $context['removed'] === true;
        }));
});

// ============================================================================
// SCENARIO 8: Edge Cases
// ============================================================================

test('handles IPv6 addresses in CSF unblock', function () {
    $host = Host::factory()->create(['panel' => PanelType::CPANEL]);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('getSshKeyPath')->andReturn('/path/to/key');
    $this->session->allows('cleanup');

    $this->firewallService->allows('checkProblems')->andReturn('success');

    $result = $this->unblocker->unblockFromCsf('2001:db8::1', $host);

    expect($result['unblock']['success'])->toBeTrue()
        ->and($result['whitelist']['success'])->toBeTrue();
});

test('handles IPv6 addresses in BFM removal', function () {
    $host = Host::factory()->create(['panel' => PanelType::DIRECTADMIN]);

    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');

    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'sed') && str_contains($command, '2001:db8::1')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->removeFromBfmBlacklist('2001:db8::1', $host);

    expect($result['removal']['success'])->toBeTrue();
});
