<?php

declare(strict_types=1);

use App\Jobs\ProcessHqWhitelistJob;
use App\Mail\HqWhitelistMail;
use App\Models\{Host, User};
use App\Services\FirewallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Log, Mail, Storage};

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Storage::fake('ssh');
    Log::spy();
});

// ============================================================================
// SCENARIO 1: HQ Host Resolution
// ============================================================================

test('job skips when HQ host not found by ID or FQDN', function () {
    config()->set('unblock.hq.host_id', 999); // Non-existent ID
    config()->set('unblock.hq.fqdn', null);

    $firewallService = Mockery::mock(FirewallService::class);
    // Should not call any methods

    $job = new ProcessHqWhitelistJob(
        ip: '192.168.1.100',
        userId: 1
    );

    $job->handle($firewallService);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('HQ host not found, skipping HQ whitelist check');

    Mail::assertNothingSent();
});

test('job resolves HQ host by ID when configured', function () {
    $hqHost = Host::factory()->create([
        'fqdn' => 'hq.example.com',
        'hash' => 'test-ssh-key-content', // ggignore
    ]);

    config()->set('unblock.hq.host_id', $hqHost->id);
    config()->set('unblock.hq.fqdn', null);
    config()->set('unblock.admin_email', 'admin@example.com');

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/test_key_hq');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', Mockery::any())
        ->andReturn('IP blocked in ModSec'); // IP is blocked
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'whitelist_hq', Mockery::any())
        ->andReturn('whitelisted');

    $job = new ProcessHqWhitelistJob(
        ip: '192.168.1.100',
        userId: 1
    );

    $job->handle($firewallService);

    // Should have processed successfully
    Log::shouldHaveReceived('info')
        ->with('HQ whitelist applied and user notified', Mockery::any());
});

test('job resolves HQ host by FQDN when ID not configured', function () {
    $hqHost = Host::factory()->create([
        'fqdn' => 'hq-server.example.com',
        'hash' => 'test-ssh-key-content', // ggignore
    ]);

    config()->set('unblock.hq.host_id', null);
    config()->set('unblock.hq.fqdn', 'hq-server.example.com');
    config()->set('unblock.admin_email', 'admin@example.com');

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/test_key_hq');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', Mockery::any())
        ->andReturn('blocked');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'whitelist_hq', Mockery::any())
        ->andReturn('whitelisted');

    $job = new ProcessHqWhitelistJob(
        ip: '10.0.0.1',
        userId: 1
    );

    $job->handle($firewallService);

    Log::shouldHaveReceived('info')
        ->with('HQ whitelist applied and user notified', Mockery::any());
});

test('job prefers host ID over FQDN when both are configured', function () {
    $hostById = Host::factory()->create([
        'fqdn' => 'host-by-id.example.com',
        'hash' => 'test-key-1', // ggignore
    ]);

    $hostByFqdn = Host::factory()->create([
        'fqdn' => 'host-by-fqdn.example.com',
        'hash' => 'test-key-2', // ggignore
    ]);

    config()->set('unblock.hq.host_id', $hostById->id);
    config()->set('unblock.hq.fqdn', 'host-by-fqdn.example.com');
    config()->set('unblock.admin_email', 'admin@example.com');

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/test_key');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', Mockery::any())
        ->andReturn('blocked');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'whitelist_hq', Mockery::any())
        ->andReturn('whitelisted');

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);
    $job->handle($firewallService);

    // Should use host-by-id
    Log::shouldHaveReceived('info')
        ->with('HQ whitelist applied and user notified', [
            'ip' => '192.168.1.1',
            'fqdn' => 'host-by-id.example.com', // ← ID host, not FQDN host
        ]);
});

// ============================================================================
// SCENARIO 2: Missing SSH Key (Hash)
// ============================================================================

test('job skips when HQ host has no SSH key (empty hash)', function () {
    $hqHost = Host::factory()->create([
        'fqdn' => 'hq.example.com',
        'hash' => '', // ← Empty hash
    ]);

    config()->set('unblock.hq.host_id', $hqHost->id);

    $firewallService = Mockery::mock(FirewallService::class);
    // Should not call generateSshKey

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);
    $job->handle($firewallService);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('HQ host has no SSH private key (hash). Skipping HQ whitelist check', [
            'host_id' => $hqHost->id,
            'fqdn' => 'hq.example.com',
        ]);

    Mail::assertNothingSent();
});

test('job skips when HQ host has null hash', function () {
    $hqHost = Host::factory()->create([
        'fqdn' => 'hq.example.com',
        'hash' => null,
    ]);

    config()->set('unblock.hq.host_id', $hqHost->id);

    $firewallService = Mockery::mock(FirewallService::class);

    $job = new ProcessHqWhitelistJob(ip: '10.0.0.1', userId: 1);
    $job->handle($firewallService);

    Log::shouldHaveReceived('warning')
        ->with('HQ host has no SSH private key (hash). Skipping HQ whitelist check', Mockery::any());
});

// ============================================================================
// SCENARIO 3: IP Not Blocked in ModSecurity
// ============================================================================

test('job skips whitelist when IP not blocked in ModSecurity', function () {
    $hqHost = Host::factory()->create([
        'fqdn' => 'hq.example.com',
        'hash' => 'test-ssh-key', // ggignore
    ]);

    config()->set('unblock.hq.host_id', $hqHost->id);
    config()->set('unblock.admin_email', 'admin@example.com');

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/test_key');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', '192.168.1.1')
        ->andReturn(''); // ← Empty = not blocked

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);
    $job->handle($firewallService);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('IP not blocked on HQ host. No whitelist or email will be sent', [
            'ip' => '192.168.1.1',
            'fqdn' => 'hq.example.com',
        ]);

    // Should NOT call whitelist_hq
    $firewallService->shouldNotHaveReceived('checkProblems', [
        Mockery::any(), Mockery::any(), 'whitelist_hq', Mockery::any(),
    ]);

    Mail::assertNothingSent();
});

// ============================================================================
// SCENARIO 4: IP Blocked - Whitelist and Notify
// ============================================================================

test('job applies whitelist and sends email when IP blocked', function () {
    $hqHost = Host::factory()->create([
        'fqdn' => 'hq.example.com',
        'hash' => 'test-ssh-key-blocked', // ggignore
    ]);

    $adminUser = User::factory()->admin()->create([
        'email' => 'admin@example.com',
    ]);

    config()->set('unblock.hq.host_id', $hqHost->id);
    config()->set('unblock.admin_email', 'admin@example.com');
    config()->set('unblock.hq.ttl', 3600); // 1 hour

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/test_key_blocked');
    $firewallService->expects('checkProblems')
        ->with(Mockery::type(Host::class), '/tmp/test_key_blocked', 'mod_security_da', '10.0.0.50')
        ->once()
        ->andReturn('ModSecurity: IP blocked'); // ← Blocked
    $firewallService->expects('checkProblems')
        ->with(Mockery::type(Host::class), '/tmp/test_key_blocked', 'whitelist_hq', '10.0.0.50')
        ->once()
        ->andReturn('IP whitelisted successfully');

    $job = new ProcessHqWhitelistJob(ip: '10.0.0.50', userId: $adminUser->id);
    $job->handle($firewallService);

    // Should queue email (HqWhitelistMail implements ShouldQueue)
    Mail::assertQueued(HqWhitelistMail::class, function ($mail) use ($hqHost) {
        return $mail->user->email === 'admin@example.com'
            && $mail->ip === '10.0.0.50'
            && $mail->ttlSeconds === 3600
            && $mail->hqHost->id === $hqHost->id
            && ! empty($mail->modsecLogs);
    });

    Log::shouldHaveReceived('info')
        ->with('HQ whitelist applied and user notified', [
            'ip' => '10.0.0.50',
            'fqdn' => 'hq.example.com',
        ]);
});

// ============================================================================
// SCENARIO 5: Admin Email Notification
// ============================================================================

test('job does not send email when admin_email not configured', function () {
    $hqHost = Host::factory()->create([
        'fqdn' => 'hq.example.com',
        'hash' => 'test-key', // ggignore
    ]);

    config()->set('unblock.hq.host_id', $hqHost->id);
    config()->set('unblock.admin_email', null); // ← No admin email

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/test_key');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', Mockery::any())
        ->andReturn('blocked');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'whitelist_hq', Mockery::any())
        ->andReturn('whitelisted');

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);
    $job->handle($firewallService);

    Mail::assertNothingSent();
});

test('job uses existing admin user for email', function () {
    $hqHost = Host::factory()->create(['hash' => 'test-key']); // ggignore
    $adminUser = User::factory()->admin()->create(['email' => 'existing-admin@example.com']);

    config()->set('unblock.hq.host_id', $hqHost->id);
    config()->set('unblock.admin_email', 'existing-admin@example.com');
    config()->set('unblock.hq.ttl', 7200);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/key');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', Mockery::any())
        ->andReturn('blocked');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'whitelist_hq', Mockery::any())
        ->andReturn('ok');

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);
    $job->handle($firewallService);

    Mail::assertQueued(HqWhitelistMail::class, function ($mail) use ($adminUser) {
        return $mail->user->id === $adminUser->id
            && $mail->user->email === 'existing-admin@example.com';
    });
});

test('job creates temporary admin user when no admin exists', function () {
    $hqHost = Host::factory()->create(['hash' => 'test-key']); // ggignore

    config()->set('unblock.hq.host_id', $hqHost->id);
    config()->set('unblock.admin_email', 'temp-admin@example.com');

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/key');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', Mockery::any())
        ->andReturn('blocked');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'whitelist_hq', Mockery::any())
        ->andReturn('ok');

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);
    $job->handle($firewallService);

    Mail::assertQueued(HqWhitelistMail::class, function ($mail) {
        return $mail->user->email === 'temp-admin@example.com'
            && $mail->user->id === null; // Not persisted
    });
});

// ============================================================================
// SCENARIO 6: SSH Key Cleanup
// ============================================================================

test('job cleans up SSH key after successful execution', function () {
    $hqHost = Host::factory()->create(['hash' => 'test-key']); // ggignore

    config()->set('unblock.hq.host_id', $hqHost->id);
    config()->set('unblock.admin_email', null);

    $keyPath = 'ssh_keys/test_hq_key_12345';
    Storage::disk('ssh')->put(basename($keyPath), 'fake-ssh-key-content'); // ggignore

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn(storage_path("app/ssh/{$keyPath}"));
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', Mockery::any())
        ->andReturn(''); // Not blocked

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);
    $job->handle($firewallService);

    // Key should be deleted
    Storage::disk('ssh')->assertMissing(basename($keyPath));
});

test('job cleans up SSH key even when exception occurs', function () {
    $hqHost = Host::factory()->create(['hash' => 'test-key']); // ggignore

    config()->set('unblock.hq.host_id', $hqHost->id);

    $keyPath = 'ssh_keys/test_hq_key_error';
    Storage::disk('ssh')->put(basename($keyPath), 'fake-key'); // ggignore

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn(storage_path("app/ssh/{$keyPath}"));
    $firewallService->allows('checkProblems')
        ->andThrow(new Exception('SSH connection failed'));

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);

    try {
        $job->handle($firewallService);
    } catch (Exception $e) {
        // Expected
    }

    // Key should still be deleted (finally block)
    Storage::disk('ssh')->assertMissing(basename($keyPath));
});

// ============================================================================
// SCENARIO 7: Error Handling
// ============================================================================

test('job logs error and re-throws exception on failure', function () {
    $hqHost = Host::factory()->create(['hash' => 'test-key']); // ggignore

    config()->set('unblock.hq.host_id', $hqHost->id);

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/key');
    $firewallService->allows('checkProblems')
        ->andThrow(new Exception('Connection timeout'));

    $job = new ProcessHqWhitelistJob(ip: '10.0.0.1', userId: 1);

    expect(fn () => $job->handle($firewallService))
        ->toThrow(Exception::class, 'Connection timeout');

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Failed to process HQ whitelist job', [
            'ip' => '10.0.0.1',
            'error' => 'Connection timeout',
        ]);
});

// ============================================================================
// SCENARIO 8: TTL Configuration
// ============================================================================

test('job uses default TTL when not configured', function () {
    $hqHost = Host::factory()->create(['hash' => 'test-key']); // ggignore

    config()->set('unblock.hq.host_id', $hqHost->id);
    config()->set('unblock.admin_email', 'admin@example.com');
    config()->set('unblock.hq.ttl', null); // ← Not configured

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/key');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', Mockery::any())
        ->andReturn('blocked');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'whitelist_hq', Mockery::any())
        ->andReturn('ok');

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);
    $job->handle($firewallService);

    Mail::assertQueued(HqWhitelistMail::class, function ($mail) {
        return $mail->ttlSeconds === 7200; // Default 7200s
    });
});

test('job uses custom TTL when configured', function () {
    $hqHost = Host::factory()->create(['hash' => 'test-key']); // ggignore

    config()->set('unblock.hq.host_id', $hqHost->id);
    config()->set('unblock.admin_email', 'admin@example.com');
    config()->set('unblock.hq.ttl', 10800); // 3 hours

    $firewallService = Mockery::mock(FirewallService::class);
    $firewallService->allows('generateSshKey')->andReturn('/tmp/key');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'mod_security_da', Mockery::any())
        ->andReturn('blocked');
    $firewallService->allows('checkProblems')
        ->with(Mockery::any(), Mockery::any(), 'whitelist_hq', Mockery::any())
        ->andReturn('ok');

    $job = new ProcessHqWhitelistJob(ip: '192.168.1.1', userId: 1);
    $job->handle($firewallService);

    Mail::assertQueued(HqWhitelistMail::class, function ($mail) {
        return $mail->ttlSeconds === 10800; // Custom 3 hours
    });
});
