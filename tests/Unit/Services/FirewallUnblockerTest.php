<?php

declare(strict_types=1);

use App\Exceptions\{CommandExecutionException, CsfServiceException};
use App\Models\Host;
use App\Services\Firewall\FirewallAnalysisResult;
use App\Services\{FirewallUnblocker, SshConnectionManager, SshSession};
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Log::spy();

    $this->sshManager = Mockery::mock(SshConnectionManager::class);
    $this->session = Mockery::mock(SshSession::class);
    $this->unblocker = new FirewallUnblocker($this->sshManager);
});

// ============================================================================
// SCENARIO 1: CSF Blocks Detection
// ============================================================================

test('hasCsfBlocks detects DENYIN pattern', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'IP blocked by DENYIN rule']
    );

    $host = Host::factory()->create(['panel' => 'cpanel']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('execute')->andReturn('success');
    $this->session->allows('getHost')->andReturn($host);

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->toHaveKey('csf');
});

test('hasCsfBlocks detects DROP pattern', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'DROP packets from this IP']
    );

    $host = Host::factory()->create(['panel' => 'cpanel']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('execute')->andReturn('success');
    $this->session->allows('getHost')->andReturn($host);

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->toHaveKey('csf');
});

test('hasCsfBlocks detects Temporary Blocks', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'Temporary Blocks: 192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'cpanel']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('execute')->andReturn('success');
    $this->session->allows('getHost')->andReturn($host);

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->toHaveKey('csf');
});

test('hasCsfBlocks detects csf_deny file blocks', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf_deny' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'cpanel']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('execute')->andReturn('success');
    $this->session->allows('getHost')->andReturn($host);

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->toHaveKey('csf');
});

test('hasCsfBlocks detects csf_tempip blocks', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf_tempip' => '192.168.1.1|some data']
    );

    $host = Host::factory()->create(['panel' => 'cpanel']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('execute')->andReturn('success');
    $this->session->allows('getHost')->andReturn($host);

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->toHaveKey('csf');
});

test('hasCsfBlocks returns false for non-blocking CSF content', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: false,
        logs: ['csf' => 'IP is not blocked, clean']
    );

    $host = Host::factory()->create(['panel' => 'cpanel']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->not->toHaveKey('csf');
});

// ============================================================================
// SCENARIO 2: BFM Blocks Detection
// ============================================================================

test('hasBfmBlocks detects DirectAdmin BFM entries', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Dynamic mock that responds based on command
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist')) {
                return "10.0.0.1\n192.168.1.1\n10.0.0.2";
            }
            if (str_contains($command, 'echo') && str_contains($command, 'ip_blacklist')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->toHaveKey('bfm');
});

test('hasBfmBlocks returns false when no BFM data', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: false,
        logs: ['csf' => 'some data']
    );

    $host = Host::factory()->create(['panel' => 'directadmin']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->not->toHaveKey('bfm');
});

// ============================================================================
// SCENARIO 3: OPML Business Rules
// ============================================================================

test('OPML rule: CSF blocks only - performs CSF unblock and whitelist', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'DENYIN rule found']
    );

    $host = Host::factory()->create(['panel' => 'cpanel', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Mock execute to check denies first, then execute unblock commands
    $this->session->allows('execute')->andReturnUsing(function ($command) {
        if (str_contains($command, 'csf.deny')) {
            return '192.168.1.1'; // IP found in permanent deny
        }
        if (str_contains($command, 'csf.tempip')) {
            return ''; // Not in temporary deny
        }
        if (str_contains($command, 'csf -dr')) {
            return 'IP 192.168.1.1 removed';
        }
        if (str_contains($command, 'csf -ta')) {
            return 'IP 192.168.1.1 whitelisted for 24 hours';
        }

        return '';
    });

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->toHaveKey('csf')
        ->and($result)->not->toHaveKey('bfm')
        ->and($result['csf'])->toHaveKey('unblock_permanent')
        ->and($result['csf'])->toHaveKey('whitelist');

    Log::shouldHaveReceived('info')
        ->with('Firewall Unblocker: OPML compliance summary', Mockery::on(function ($context) {
            return $context['opml_rule_applied'] === 'CSF blocks only: CSF unblock/whitelist';
        }));
});

test('OPML rule: BFM blocks only - performs BFM removal WITHOUT CSF whitelist', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Dynamic mock that responds based on command
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist')) {
                return "10.0.0.1\n192.168.1.1\n10.0.0.2";
            }
            if (str_contains($command, 'echo') && str_contains($command, 'ip_blacklist')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->not->toHaveKey('csf')
        ->and($result)->toHaveKey('bfm');

    Log::shouldHaveReceived('info')
        ->with('Firewall Unblocker: OPML compliance summary', Mockery::on(function ($context) {
            return $context['opml_rule_applied'] === 'BFM blocks only: BFM removal (NO CSF temporal whitelist)';
        }));
});

test('OPML rule: CSF + BFM blocks - performs both operations', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'DENYIN rule found', 'da_bfm' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Dynamic mock that responds to both CSF and BFM commands
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            // CSF commands
            if (str_contains($command, 'csf -dr')) {
                return 'IP removed';
            }
            if (str_contains($command, 'csf -ta')) {
                return 'IP whitelisted';
            }
            // BFM commands
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist')) {
                return "10.0.0.1\n192.168.1.1\n10.0.0.2";
            }
            if (str_contains($command, 'echo') && str_contains($command, 'ip_blacklist')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->toHaveKey('csf')
        ->and($result)->toHaveKey('bfm');

    Log::shouldHaveReceived('info')
        ->with('Firewall Unblocker: OPML compliance summary', Mockery::on(function ($context) {
            return $context['opml_rule_applied'] === 'CSF blocks + BFM blocks: CSF unblock/whitelist + BFM removal';
        }));
});

test('OPML rule: no blocks - no operations performed', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: false,
        logs: ['csf' => 'Clean, no blocks']
    );

    $host = Host::factory()->create(['panel' => 'cpanel', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->not->toHaveKey('csf')
        ->and($result)->not->toHaveKey('bfm');

    Log::shouldHaveReceived('info')
        ->with('Firewall Unblocker: OPML compliance summary', Mockery::on(function ($context) {
            return $context['opml_rule_applied'] === 'No blocks detected: no operations performed';
        }));
});

// ============================================================================
// SCENARIO 4: Panel-Specific Behavior
// ============================================================================

test('BFM operations only run for DirectAdmin hosts', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $cpanelHost = Host::factory()->create(['panel' => 'cpanel']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');

    $result = $this->unblocker->unblockIp('192.168.1.1', $cpanelHost, $analysisResult);

    expect($result)->not->toHaveKey('bfm');
});

test('BFM operations run for DirectAdmin hosts with BFM blocks', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $daHost = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($daHost);

    // Dynamic mock
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist')) {
                return "10.0.0.1\n192.168.1.1\n10.0.0.2";
            }
            if (str_contains($command, 'echo') && str_contains($command, 'ip_blacklist')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->unblockIp('192.168.1.1', $daHost, $analysisResult);

    expect($result)->toHaveKey('bfm');
});

// ============================================================================
// SCENARIO 5: BFM Removal Logic
// ============================================================================

test('performBfmRemoval removes IP from blacklist file', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Dynamic mock - must be specific about order
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            // First cat - get blacklist with IP
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist') && ! str_contains($command, 'grep')) {
                return "10.0.0.1\n192.168.1.1\n10.0.0.2";
            }
            // Echo to write filtered content
            if (str_contains($command, 'echo') && str_contains($command, 'ip_blacklist')) {
                return '';
            }
            // Verification grep - IP should not be found after removal
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result)->toHaveKey('bfm')
        ->and($result['bfm'])->toHaveKey('removal')
        ->and($result['bfm'])->toHaveKey('verification')
        ->and($result['bfm']['removal']['success'])->toBeTrue();
});

test('performBfmRemoval handles IP not in blacklist', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Dynamic mock - IP not in blacklist
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist')) {
                return "10.0.0.1\n10.0.0.2"; // No 192.168.1.1
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result['bfm']['removal'])->toHaveKey('note')
        ->and($result['bfm']['removal']['note'])->toBe('IP not found in blacklist');
});

test('performBfmRemoval handles empty blacklist file', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Dynamic mock - empty file
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist')) {
                return ''; // Empty file
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result['bfm']['removal']['note'])->toBe('IP not found in blacklist');
});

// ============================================================================
// SCENARIO 6: Error Handling
// ============================================================================

test('performCsfOperations throws CsfServiceException on failure', function () {
    // Mock Log channel to prevent error
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')->andReturn(null);

    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'DENYIN rule found']
    );

    $host = Host::factory()->create(['panel' => 'cpanel', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    $this->session->allows('execute')
        ->andThrow(new Exception('SSH connection failed'));

    expect(fn () => $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult))
        ->toThrow(CsfServiceException::class);
});

test('performBfmOperations throws CommandExecutionException on failure', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Execute throws exception
    $this->session->allows('execute')
        ->andThrow(new Exception('File not found'));

    expect(fn () => $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult))
        ->toThrow(CommandExecutionException::class);

    Log::shouldHaveReceived('error')
        ->with('BFM file processing failed', Mockery::any());
});

test('session cleanup is called even when exception occurs', function () {
    // Mock Log channel to prevent error
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')->andReturn(null);

    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'DENYIN rule found']
    );

    $host = Host::factory()->create(['panel' => 'cpanel']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->expects('cleanup')->once();
    $this->session->allows('getHost')->andReturn($host);

    $this->session->allows('execute')
        ->andThrow(new Exception('Error'));

    try {
        $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);
    } catch (Exception $e) {
        // Expected
    }
});

// ============================================================================
// SCENARIO 7: IP Validation
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
// SCENARIO 8: Unblock Status
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

test('getUnblockStatus returns failure for BFM-only operations when CSF not performed', function () {
    $results = [
        'bfm' => [
            'removal' => ['success' => true],
        ],
    ];

    $status = $this->unblocker->getUnblockStatus($results);

    // BFM success is true, but CSF was not performed, so csf_success is false
    // This makes overall_success false (csf_success && bfm_success)
    expect($status['overall_success'])->toBeFalse()
        ->and($status['bfm_success'])->toBeTrue()
        ->and($status['csf_success'])->toBeFalse()
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
// SCENARIO 9: Logging
// ============================================================================

test('unblockIp logs determination strategy', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'DENYIN rule']
    );

    $host = Host::factory()->create(['panel' => 'cpanel', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('execute')->andReturn('success');
    $this->session->allows('getHost')->andReturn($host);

    $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    Log::shouldHaveReceived('info')
        ->with('Firewall Unblocker: Determining unblock strategy', Mockery::on(function ($context) {
            return $context['ip'] === '192.168.1.1' &&
                   $context['host'] === 'test.example.com' &&
                   isset($context['has_csf_blocks']) &&
                   isset($context['has_bfm_blocks']);
        }));
});

test('performCsfOperations logs success', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['csf' => 'DENYIN rule']
    );

    $host = Host::factory()->create(['panel' => 'cpanel', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Mock execute to return empty for deny checks (IP not blocked) and success for whitelist
    $this->session->allows('execute')->andReturnUsing(function ($command) {
        if (str_contains($command, 'csf.deny') || str_contains($command, 'csf.tempip')) {
            return ''; // Not in deny lists
        }

        return 'success'; // Whitelist command
    });

    $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    Log::shouldHaveReceived('info')
        ->with('CSF operations completed successfully', Mockery::on(function ($context) {
            return isset($context['operations']) &&
                   in_array('temporal_whitelist_24h', $context['operations']);
        }));
});

test('performBfmOperations logs success', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Dynamic mock
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist')) {
                return "10.0.0.1\n192.168.1.1\n10.0.0.2";
            }
            if (str_contains($command, 'echo') && str_contains($command, 'ip_blacklist')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    Log::shouldHaveReceived('info')
        ->with('BFM operations completed successfully', Mockery::any());
});

// ============================================================================
// SCENARIO 10: Edge Cases
// ============================================================================

test('handles IPv6 addresses in BFM removal', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '2001:db8::1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Dynamic mock for IPv6
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist')) {
                return "10.0.0.1\n2001:db8::1";
            }
            if (str_contains($command, 'echo') && str_contains($command, 'ip_blacklist')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->unblockIp('2001:db8::1', $host, $analysisResult);

    expect($result['bfm']['removal']['success'])->toBeTrue();
});

test('handles IPs with regex special characters in BFM removal', function () {
    $analysisResult = new FirewallAnalysisResult(
        blocked: true,
        logs: ['da_bfm' => '192.168.1.1']
    );

    $host = Host::factory()->create(['panel' => 'directadmin', 'fqdn' => 'test.example.com']);
    $this->sshManager->allows('createSession')->andReturn($this->session);
    $this->session->allows('cleanup');
    $this->session->allows('getHost')->andReturn($host);

    // Dynamic mock for IP with dots
    $this->session->allows('execute')
        ->andReturnUsing(function ($command) {
            if (str_contains($command, 'cat /usr/local/directadmin/data/admin/ip_blacklist')) {
                return "10.0.0.1\n192.168.1.1\n10.0.0.2";
            }
            if (str_contains($command, 'echo') && str_contains($command, 'ip_blacklist')) {
                return '';
            }
            if (str_contains($command, 'grep')) {
                return 'IP not found in blacklist';
            }

            return '';
        });

    $result = $this->unblocker->unblockIp('192.168.1.1', $host, $analysisResult);

    expect($result['bfm']['removal']['success'])->toBeTrue();
});
