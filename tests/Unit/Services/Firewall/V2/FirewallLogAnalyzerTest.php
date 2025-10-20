<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\Firewall\FirewallAnalysisResult;
use App\Services\Firewall\V2\FirewallLogAnalyzer;
use App\Services\{FirewallService};
use App\Services\{SshConnectionManager, SshSession};
use Mockery as m;

describe('FirewallLogAnalyzer V2', function () {
    beforeEach(function () {
        $this->firewallService = m::mock(FirewallService::class);
        $this->sshManager = m::mock(SshConnectionManager::class);
        $this->session = m::mock(SshSession::class);

        $this->analyzer = new FirewallLogAnalyzer(
            $this->firewallService,
            $this->sshManager
        );

        $this->host = Host::factory()->create([
            'panel' => 'directadmin',
            'fqdn' => 'test.example.com',
        ]);

        $this->ipAddress = '10.0.0.1';
    });

    describe('analyzeDirectAdmin()', function () {
        it('performs complete analysis workflow for DirectAdmin', function () {
            // Arrange: Mock SSH session
            $this->sshManager->shouldReceive('createSession')
                ->with($this->host)
                ->once()
                ->andReturn($this->session);

            $this->session->shouldReceive('getSshKeyPath')
                ->andReturn('/tmp/test_key');

            $this->session->shouldReceive('cleanup')
                ->once();

            // Arrange: Mock CSF primary analysis (no blocks found)
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'csf', $this->ipAddress)
                ->once()
                ->andReturn('No matches found for 10.0.0.1');

            // Arrange: Mock deep CSF analysis
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'csf_deny_check', $this->ipAddress)
                ->once()
                ->andReturn('');

            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'csf_tempip_check', $this->ipAddress)
                ->once()
                ->andReturn('');

            // Arrange: Mock BFM analysis (IP found in blacklist)
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'da_bfm_check', $this->ipAddress)
                ->once()
                ->andReturn('10.0.0.1 20241201103335');

            // Arrange: Mock additional services
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'exim_directadmin', $this->ipAddress)
                ->once()
                ->andReturn('');

            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'dovecot_directadmin', $this->ipAddress)
                ->once()
                ->andReturn('');

            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'mod_security_da', $this->ipAddress)
                ->once()
                ->andReturn('');

            // Act
            $result = $this->analyzer->analyzeDirectAdmin($this->ipAddress, $this->host);

            // Assert
            expect($result)->toBeInstanceOf(FirewallAnalysisResult::class)
                ->and($result->isBlocked())->toBeTrue() // BFM block detected
                ->and($result->getLogs())->toHaveKey('csf')
                ->and($result->getLogs())->toHaveKey('da_bfm_check')
                ->and($result->getLogs()['da_bfm_check'])->toBe('10.0.0.1 20241201103335');
        });

        it('detects CSF primary blocks correctly', function () {
            // Arrange: Mock CSF with DENYIN block
            $this->sshManager->shouldReceive('createSession')->andReturn($this->session);
            $this->session->shouldReceive('getSshKeyPath')->andReturn('/tmp/test_key');
            $this->session->shouldReceive('cleanup');

            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'csf', $this->ipAddress)
                ->once()
                ->andReturn('Chain DENYIN (policy DROP)');

            // Mock BFM (no additional blocks)
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'da_bfm_check', $this->ipAddress)
                ->once()
                ->andReturn('');

            // Mock additional services
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', m::pattern('/^(exim|dovecot|mod_security)_/'), $this->ipAddress)
                ->times(3)
                ->andReturn('');

            // Act
            $result = $this->analyzer->analyzeDirectAdmin($this->ipAddress, $this->host);

            // Assert
            expect($result->isBlocked())->toBeTrue()
                ->and($result->getLogs()['csf'])->toContain('DENYIN');
        });

        it('performs exact IP matching for BFM blocks', function () {
            // Arrange: Mock session
            $this->sshManager->shouldReceive('createSession')->andReturn($this->session);
            $this->session->shouldReceive('getSshKeyPath')->andReturn('/tmp/test_key');
            $this->session->shouldReceive('cleanup');

            // Mock CSF (no blocks)
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'csf', $this->ipAddress)
                ->andReturn('No matches found');

            // Mock deep CSF (no blocks)
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', m::pattern('/csf_(deny|tempip)_check/'), $this->ipAddress)
                ->times(2)
                ->andReturn('');

            // Mock BFM with partial IP matches (should NOT trigger false positive)
            $bfmOutput = "192.168.10.0.1 20241201103335\n10.0.0.100 20241201103336\n10.0.0.1 20241201103337";
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'da_bfm_check', $this->ipAddress)
                ->andReturn($bfmOutput);

            // Mock additional services
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', m::pattern('/^(exim|dovecot|mod_security)_/'), $this->ipAddress)
                ->times(3)
                ->andReturn('');

            // Act
            $result = $this->analyzer->analyzeDirectAdmin($this->ipAddress, $this->host);

            // Assert: Debe detectar el bloqueo exacto de 10.0.0.1
            expect($result->isBlocked())->toBeTrue()
                ->and($result->getLogs()['da_bfm_check'])->toBe($bfmOutput);
        });

        it('separates CSF and BFM analysis responsibilities correctly', function () {
            // Arrange: Mock session
            $this->sshManager->shouldReceive('createSession')->andReturn($this->session);
            $this->session->shouldReceive('getSshKeyPath')->andReturn('/tmp/test_key');
            $this->session->shouldReceive('cleanup');

            // Mock CSF (blocked)
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'csf', $this->ipAddress)
                ->andReturn('Chain DENYIN (policy DROP)');

            // Mock BFM (also blocked - should be independent)
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', 'da_bfm_check', $this->ipAddress)
                ->andReturn('10.0.0.1 20241201103335');

            // Mock additional services
            $this->firewallService->shouldReceive('checkProblems')
                ->with($this->host, '/tmp/test_key', m::pattern('/^(exim|dovecot|mod_security)_/'), $this->ipAddress)
                ->times(3)
                ->andReturn('');

            // Act
            $result = $this->analyzer->analyzeDirectAdmin($this->ipAddress, $this->host);

            // Assert: Ambos sistemas detectan bloqueos independientemente
            expect($result->isBlocked())->toBeTrue()
                ->and($result->getLogs())->toHaveKey('csf')
                ->and($result->getLogs())->toHaveKey('da_bfm_check')
                ->and($result->getLogs()['csf'])->toContain('DENYIN')
                ->and($result->getLogs()['da_bfm_check'])->toContain('10.0.0.1');
        });
    });

    afterEach(function () {
        m::close();
    });
});
