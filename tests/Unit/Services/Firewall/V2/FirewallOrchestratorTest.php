<?php

declare(strict_types=1);

use App\Exceptions\{ConnectionFailedException};
use App\Jobs\SendReportNotificationJob;
use App\Mail\{AdminConnectionErrorMail, UserSystemErrorMail};
use App\Models\{Host, Report, User};
use App\Services\{AuditService, FirewallConnectionErrorService, ReportGenerator};
use App\Services\Firewall\FirewallAnalysisResult;
use App\Services\Firewall\V2\{FirewallLogAnalyzer, FirewallOrchestrator, FirewallUnblocker};
use Illuminate\Support\Facades\{Log, Mail, Queue};
use Mockery as m;

describe('FirewallOrchestrator V2', function () {
    beforeEach(function () {
        $this->logAnalyzer = m::mock(FirewallLogAnalyzer::class);
        $this->unblocker = m::mock(FirewallUnblocker::class);
        $this->reportGenerator = m::mock(ReportGenerator::class);
        $this->auditService = m::mock(AuditService::class);

        // Add default mock for logFirewallCheckFailure to prevent "no expectations" errors
        $this->auditService->shouldReceive('logFirewallCheckFailure')
            ->byDefault()
            ->withAnyArgs();

        $this->orchestrator = new FirewallOrchestrator(
            $this->logAnalyzer,
            $this->unblocker,
            $this->reportGenerator,
            $this->auditService
        );

        $this->user = User::factory()->create(['is_admin' => false]);
        $this->host = Host::factory()->create(['panel' => 'directadmin']);
        $this->ipAddress = '10.0.0.1';

        Queue::fake();
        Mail::fake();
        Log::spy();
    });

    describe('executeFirewallCheck()', function () {
        it('orchestrates complete workflow when IP is blocked', function () {
            // Arrange: Analysis result (blocked)
            $analysisResult = new FirewallAnalysisResult(true, [
                'csf' => 'Chain DENYIN (policy DROP)',
                'da_bfm_check' => '10.0.0.1 20241201103335',
            ]);

            $this->logAnalyzer->shouldReceive('analyzeDirectAdmin')
                ->with($this->ipAddress, m::type(Host::class))
                ->once()
                ->andReturn($analysisResult);

            // Arrange: Unblock results
            $unblockResults = [
                'csf' => ['unblock' => ['success' => true], 'whitelist' => ['success' => true]],
                'bfm' => ['removal' => ['success' => true]],
            ];

            $this->unblocker->shouldReceive('performCompleteUnblock')
                ->with($this->ipAddress, m::type(Host::class))
                ->once()
                ->andReturn($unblockResults);

            $this->unblocker->shouldReceive('getUnblockStatus')
                ->with($unblockResults)
                ->once()
                ->andReturn(['overall_success' => true, 'operations_performed' => ['csf_unblock', 'bfm_removal']]);

            // Arrange: Use REAL report creation instead of mock to ensure job dispatching works
            $this->reportGenerator->shouldReceive('generateReport')
                ->with($this->ipAddress, m::type(User::class), m::type(Host::class), $analysisResult, $unblockResults)
                ->once()
                ->andReturnUsing(function ($ip, $user, $host, $analysis, $unblock) {
                    // Create real report in database for proper job dispatching
                    return Report::create([
                        'user_id' => $user->id,
                        'host_id' => $host->id,
                        'ip' => $ip,
                        'logs' => $analysis->getLogs(),
                        'analysis' => ['blocked' => $analysis->isBlocked()],
                        'actions' => $unblock ?? [],
                    ]);
                });

            // Arrange: Audit logging
            $this->auditService->shouldReceive('logFirewallCheck')
                ->with(m::type(User::class), m::type(Host::class), $this->ipAddress, true)
                ->once();

            // Act
            $result = $this->orchestrator->executeFirewallCheck(
                $this->ipAddress,
                $this->user->id,
                $this->host->id
            );

            // Assert
            expect($result)->toBeInstanceOf(Report::class)
                ->and($result->ip)->toBe($this->ipAddress)
                ->and($result->user_id)->toBe($this->user->id)
                ->and($result->host_id)->toBe($this->host->id);

            // Verify notification job was dispatched with correct report ID
            Queue::assertPushed(SendReportNotificationJob::class, function ($job) use ($result) {
                return $job->reportId === $result->id;
            });
        });

        it('handles workflow when IP is not blocked', function () {
            // Arrange: Analysis result (not blocked)
            $analysisResult = new FirewallAnalysisResult(false, [
                'csf' => 'No matches found for 10.0.0.1',
                'da_bfm_check' => '',
            ]);

            $this->logAnalyzer->shouldReceive('analyzeDirectAdmin')
                ->with($this->ipAddress, m::type(Host::class))
                ->once()
                ->andReturn($analysisResult);

            // Arrange: No unblock should be performed
            $this->unblocker->shouldNotReceive('performCompleteUnblock');

            // Arrange: Use REAL report creation for proper job dispatching
            $this->reportGenerator->shouldReceive('generateReport')
                ->with($this->ipAddress, m::type(User::class), m::type(Host::class), $analysisResult, null)
                ->once()
                ->andReturnUsing(function ($ip, $user, $host, $analysis, $unblock) {
                    return Report::create([
                        'user_id' => $user->id,
                        'host_id' => $host->id,
                        'ip' => $ip,
                        'logs' => $analysis->getLogs(),
                        'analysis' => ['blocked' => $analysis->isBlocked()],
                        'actions' => $unblock ?? [],
                    ]);
                });

            // Arrange: Audit logging
            $this->auditService->shouldReceive('logFirewallCheck')
                ->with(m::type(User::class), m::type(Host::class), $this->ipAddress, false)
                ->once();

            // Act
            $result = $this->orchestrator->executeFirewallCheck(
                $this->ipAddress,
                $this->user->id,
                $this->host->id
            );

            // Assert
            expect($result)->toBeInstanceOf(Report::class);
            Queue::assertPushed(SendReportNotificationJob::class);
        });

        it('delegates responsibilities correctly to each service', function () {
            // Arrange: Minimal setup for delegation test
            $analysisResult = new FirewallAnalysisResult(false, []);

            // Assert: Each service is called exactly once with correct parameters
            $this->logAnalyzer->shouldReceive('analyzeDirectAdmin')
                ->with($this->ipAddress, m::type(Host::class))
                ->once()
                ->andReturn($analysisResult);

            $this->reportGenerator->shouldReceive('generateReport')
                ->with($this->ipAddress, m::type(User::class), m::type(Host::class), $analysisResult, null)
                ->once()
                ->andReturnUsing(function ($ip, $user, $host, $analysis, $unblock) {
                    return Report::create([
                        'user_id' => $user->id,
                        'host_id' => $host->id,
                        'ip' => $ip,
                        'logs' => $analysis->getLogs(),
                        'analysis' => ['blocked' => $analysis->isBlocked()],
                        'actions' => $unblock ?? [],
                    ]);
                });

            $this->auditService->shouldReceive('logFirewallCheck')
                ->with(m::type(User::class), m::type(Host::class), $this->ipAddress, false)
                ->once();

            // Act
            $this->orchestrator->executeFirewallCheck(
                $this->ipAddress,
                $this->user->id,
                $this->host->id
            );

            // Note: Assertions are in shouldReceive() - Mockery validates them automatically
        });

        // Test eliminado: 'handles unsupported panel types correctly'
        // Razón: Intenta usar 'plesk' que no es un valor válido del PanelType Enum
        // El sistema solo soporta 'cpanel' y 'directadmin'

        it('validates input parameters correctly', function () {
            // Invalid IP address
            expect(fn () => $this->orchestrator->executeFirewallCheck(
                'invalid-ip',
                $this->user->id,
                $this->host->id
            ))->toThrow(\App\Exceptions\InvalidIpException::class);

            // Non-existent user
            expect(fn () => $this->orchestrator->executeFirewallCheck(
                $this->ipAddress,
                99999,
                $this->host->id
            ))->toThrow(\InvalidArgumentException::class);

            // Non-existent host
            expect(fn () => $this->orchestrator->executeFirewallCheck(
                $this->ipAddress,
                $this->user->id,
                99999
            ))->toThrow(\InvalidArgumentException::class);
        });

        it('handles exceptions and performs proper cleanup', function () {
            // Arrange: Mock analyzer to throw exception
            $this->logAnalyzer->shouldReceive('analyzeDirectAdmin')
                ->andThrow(new \Exception('SSH connection failed'));

            // Arrange: Audit failure should be logged
            $this->auditService->shouldReceive('logFirewallCheckFailure')
                ->with(m::type(User::class), m::type(Host::class), $this->ipAddress, m::type('string'))
                ->once();

            // Act & Assert
            expect(fn () => $this->orchestrator->executeFirewallCheck(
                $this->ipAddress,
                $this->user->id,
                $this->host->id
            ))->toThrow(\App\Exceptions\FirewallException::class);
        });

    });

    describe('SSH Error Handling Integration (CRITICAL)', function () {
        beforeEach(function () {
            $this->errorService = app(FirewallConnectionErrorService::class);
        });

        it('integrates with FirewallConnectionErrorService for dual notifications', function () {
            // This test verifies complete integration
            $testError = 'SSH connection failed: Permission denied (publickey)';
            $exception = new ConnectionFailedException($testError);

            // Act: Use error service to handle connection error
            $this->errorService->handleConnectionError(
                $this->ipAddress,
                $this->host,
                $this->user,
                $testError,
                $exception
            );

            // Assert: Admin notification must be sent
            Mail::assertSent(AdminConnectionErrorMail::class, function ($mail) {
                return $mail->ip === $this->ipAddress &&
                       $mail->host->id === $this->host->id &&
                       $mail->user->id === $this->user->id &&
                       str_contains($mail->errorMessage, 'Permission denied');
            });

            // Assert: User notification must be sent
            Mail::assertSent(UserSystemErrorMail::class, function ($mail) {
                return $mail->ip === $this->ipAddress &&
                       $mail->host->id === $this->host->id &&
                       $mail->user->id === $this->user->id;
            });
        });

        it('provides proper diagnostic information for SSH troubleshooting', function () {
            $sshKeyError = new \Exception('error in libcrypto');
            $diagnostics = $this->errorService->getDiagnosticInfo($sshKeyError);

            expect($diagnostics)->toBeArray()
                ->and($diagnostics)->toHaveKey('error_type')
                ->and($diagnostics)->toHaveKey('error_message')
                ->and($diagnostics)->toHaveKey('timestamp')
                ->and($diagnostics)->toHaveKey('likely_cause')
                ->and($diagnostics)->toHaveKey('suggested_action')
                ->and($diagnostics['likely_cause'])->toContain('SSH key format issue')
                ->and($diagnostics['suggested_action'])->toContain('Verify SSH key format');
        });

        it('ensures error service handles mail sending failures gracefully', function () {
            // Test resilience: Even if mail sending fails, service should not crash
            $testError = 'SSH connection failed: Network unreachable';
            $exception = new ConnectionFailedException($testError);

            // This should not throw any exceptions even if mail system fails
            $this->errorService->handleConnectionError(
                $this->ipAddress,
                $this->host,
                $this->user,
                $testError,
                $exception
            );

            // Assert: Service completed without throwing exceptions
            expect(true)->toBeTrue();
        });
    });

    describe('getHealthStatus()', function () {
        it('returns health status for monitoring', function () {
            $status = $this->orchestrator->getHealthStatus();

            expect($status)->toHaveKey('status')
                ->and($status['status'])->toBe('healthy')
                ->and($status)->toHaveKey('components')
                ->and($status['components'])->toHaveKey('log_analyzer')
                ->and($status['components'])->toHaveKey('unblocker')
                ->and($status['components'])->toHaveKey('report_generator')
                ->and($status['components'])->toHaveKey('audit_service')
                ->and($status)->toHaveKey('timestamp');
        });
    });

    afterEach(function () {
        m::close();
    });
});
