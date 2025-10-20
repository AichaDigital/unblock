<?php

declare(strict_types=1);

use App\Exceptions\ConnectionFailedException;
use App\Mail\{AdminConnectionErrorMail, UserSystemErrorMail};
use App\Models\{Host, User};
use App\Services\FirewallConnectionErrorService;
use App\Services\{SshConnectionManager, SshSession};
use Illuminate\Support\Facades\{File, Log, Mail, Storage};

describe('SshConnectionManager V2', function () {
    beforeEach(function () {
        $this->manager = new SshConnectionManager;
        $this->host = Host::factory()->create([
            'fqdn' => 'test.example.com',
            'port_ssh' => 22,
            'hash' => "-----BEGIN OPENSSH PRIVATE KEY-----\ntest_key_content\n-----END OPENSSH PRIVATE KEY-----",
        ]);

        // Configurar mails
        Mail::fake();
        Log::spy();
    });

    describe('generateSshKey()', function () {
        it('generates SSH key with proper format', function () {
            $keyPath = $this->manager->generateSshKey($this->host->hash);

            expect($keyPath)->toBeString()
                ->and(str_contains($keyPath, '.ssh/key_'))
                ->toBeTrue();

            // Verify that the file was created
            $filename = basename($keyPath);
            expect(Storage::disk('ssh')->exists($filename))->toBeTrue();

            // Limpiar
            Storage::disk('ssh')->delete($filename);
        });

        it('normalizes line endings correctly', function () {
            $hashWithBadLineEndings = "-----BEGIN OPENSSH PRIVATE KEY-----\r\ntest_key\r\n-----END OPENSSH PRIVATE KEY-----";

            $keyPath = $this->manager->generateSshKey($hashWithBadLineEndings);
            $filename = basename($keyPath);

            $content = Storage::disk('ssh')->get($filename);
            expect($content)->not->toContain("\r\n")
                ->and($content)->toEndWith("\n");

            // Limpiar
            Storage::disk('ssh')->delete($filename);
        });

        it('generates unique file names for multiple keys', function () {
            $keyPath1 = $this->manager->generateSshKey($this->host->hash);
            $keyPath2 = $this->manager->generateSshKey($this->host->hash);

            expect($keyPath1)->not->toBe($keyPath2);

            // Limpiar
            Storage::disk('ssh')->delete(basename($keyPath1));
            Storage::disk('ssh')->delete(basename($keyPath2));
        });
    });

    describe('prepareMultiplexingPath()', function () {
        it('creates multiplexing directory if it does not exist', function () {
            $this->manager->prepareMultiplexingPath();

            expect(File::exists('/tmp/cm'))->toBeTrue();
        });

        it('does not fail if directory already exists', function () {
            // Arrange: Create directory first
            if (! File::exists('/tmp/cm')) {
                File::makeDirectory('/tmp/cm', 0755, true);
            }

            // Act & Assert: Should not throw when directory already exists
            expect(fn () => $this->manager->prepareMultiplexingPath())
                ->not->toThrow(\Exception::class);
        });
    });

    describe('createSession()', function () {
        it('creates SSH session with proper configuration', function () {
            $session = $this->manager->createSession($this->host);

            expect($session)->toBeInstanceOf(SshSession::class)
                ->and($session->getHost())->toBe($this->host)
                ->and($session->getSshKeyPath())->toBeString();
        });

        it('enables multiplexing in created session', function () {
            // Test indirecto: verificar que prepareMultiplexingPath se ejecuta
            $session = $this->manager->createSession($this->host);

            expect(File::exists('/tmp/cm'))->toBeTrue();
        });

        it('creates session with clean state', function () {
            $session = $this->manager->createSession($this->host);

            expect($session)->toBeInstanceOf(SshSession::class);
            expect($session->getHost()->id)->toBe($this->host->id);
            expect($session->getSshKeyPath())->toContain('key_');
        });
    });

    describe('removeSshKey()', function () {
        it('removes SSH key file when it exists', function () {
            $keyPath = $this->manager->generateSshKey($this->host->hash);
            $filename = basename($keyPath);

            // Verificar que existe
            expect(Storage::disk('ssh')->exists($filename))->toBeTrue();

            // Remover
            $this->manager->removeSshKey($keyPath);

            // Verify that it was removed
            expect(Storage::disk('ssh')->exists($filename))->toBeFalse();
        });

        it('does not fail when key file does not exist', function () {
            $nonExistentPath = '/tmp/non_existent_key';

            expect(fn () => $this->manager->removeSshKey($nonExistentPath))
                ->not->toThrow(\Exception::class);
        });
    });

    describe('SSH Error Handling (CRITICAL)', function () {
        beforeEach(function () {
            $this->user = User::factory()->create(['email' => 'test@example.com']);
            $this->errorService = app(FirewallConnectionErrorService::class);
        });

        it('verifies FirewallConnectionErrorService dual notification requirement', function () {
            // Este test verifica que el servicio de errores existe y funciona
            $testIp = '192.168.1.100';
            $testError = 'SSH connection failed: Connection refused';
            $exception = new ConnectionFailedException($testError);

            // Act: Handle connection error (esto debe enviar 2 emails)
            $this->errorService->handleConnectionError(
                $testIp,
                $this->host,
                $this->user,
                $testError,
                $exception
            );

            // Assert: Admin notification sent
            Mail::assertSent(AdminConnectionErrorMail::class, function ($mail) use ($testIp) {
                return $mail->ip === $testIp &&
                       $mail->host->id === $this->host->id &&
                       $mail->user->id === $this->user->id;
            });

            // Assert: User notification sent
            Mail::assertSent(UserSystemErrorMail::class, function ($mail) use ($testIp) {
                return $mail->ip === $testIp &&
                       $mail->host->id === $this->host->id &&
                       $mail->user->id === $this->user->id;
            });
        });

        it('identifies critical SSH errors correctly', function () {
            $criticalErrors = [
                'proc_open function not available',
                'error in libcrypto',
                'Permission denied (publickey)',
                'Connection refused',
                'Connection timed out',
                'Host key verification failed',
            ];

            foreach ($criticalErrors as $errorMessage) {
                $exception = new \Exception($errorMessage);
                expect($this->errorService->isCriticalError($exception))
                    ->toBeTrue("Should identify '{$errorMessage}' as critical");
            }
        });

        it('provides diagnostic information for SSH errors', function () {
            $exception = new \Exception('error in libcrypto');
            $diagnostics = $this->errorService->getDiagnosticInfo($exception);

            expect($diagnostics)->toHaveKey('error_type')
                ->and($diagnostics)->toHaveKey('error_message')
                ->and($diagnostics)->toHaveKey('timestamp')
                ->and($diagnostics)->toHaveKey('likely_cause')
                ->and($diagnostics)->toHaveKey('suggested_action');
        });

        it('handles connection errors with complete context', function () {
            $testIp = '192.168.1.100';
            $testError = 'Permission denied (publickey)';
            $exception = new ConnectionFailedException($testError, $this->host->fqdn, $this->host->port_ssh, $testIp);

            // Verify error service can handle complete context
            expect(fn () => $this->errorService->handleConnectionError(
                $testIp,
                $this->host,
                $this->user,
                $testError,
                $exception
            ))->not->toThrow(\Exception::class);

            // Verify dual notifications were sent
            Mail::assertSent(AdminConnectionErrorMail::class);
            Mail::assertSent(UserSystemErrorMail::class);
        });
    });
});
