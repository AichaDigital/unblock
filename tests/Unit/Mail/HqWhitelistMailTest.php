<?php

declare(strict_types=1);

use App\Mail\HqWhitelistMail;
use App\Models\{Host, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Mailable Construction
// ============================================================================

test('mailable can be constructed with required parameters', function () {
    $user = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $hqHost = Host::factory()->create();

    $mailable = new HqWhitelistMail(
        user: $user,
        ip: '192.168.1.100',
        ttlSeconds: 3600,
        hqHost: $hqHost,
        modsecLogs: 'test logs'
    );

    expect($mailable->user)->toBe($user)
        ->and($mailable->ip)->toBe('192.168.1.100')
        ->and($mailable->ttlSeconds)->toBe(3600)
        ->and($mailable->hqHost)->toBe($hqHost)
        ->and($mailable->modsecLogs)->toBe('test logs');
});

test('mailable properties are readonly', function () {
    $user = User::factory()->create();
    $hqHost = Host::factory()->create();
    $mailable = new HqWhitelistMail($user, '10.0.0.1', 7200, $hqHost, 'logs');

    $reflection = new ReflectionClass($mailable);
    $userProperty = $reflection->getProperty('user');
    $ipProperty = $reflection->getProperty('ip');
    $ttlProperty = $reflection->getProperty('ttlSeconds');
    $hostProperty = $reflection->getProperty('hqHost');
    $logsProperty = $reflection->getProperty('modsecLogs');

    expect($userProperty->isReadOnly())->toBeTrue()
        ->and($ipProperty->isReadOnly())->toBeTrue()
        ->and($ttlProperty->isReadOnly())->toBeTrue()
        ->and($hostProperty->isReadOnly())->toBeTrue()
        ->and($logsProperty->isReadOnly())->toBeTrue();
});

// ============================================================================
// SCENARIO 2: Envelope
// ============================================================================

test('envelope returns correct subject from translation', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '192.168.1.1', 3600, Host::factory()->create(), 'ModSec logs');

    $envelope = $mailable->envelope();

    expect($envelope->subject)->toBeString()
        ->and($envelope->subject)->not->toBeEmpty();
});

test('envelope subject uses translation key', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '10.0.0.1', 7200, Host::factory()->create(), 'ModSec logs');

    $envelope = $mailable->envelope();

    // Subject comes from translation, just verify it's not empty and is string
    expect($envelope->subject)->toBeString()
        ->and($envelope->subject)->not->toBeEmpty()
        ->and($envelope->subject)->toContain('ModSecurity'); // Spanish translation contains this
});

// ============================================================================
// SCENARIO 3: Content
// ============================================================================

test('content returns correct view', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '192.168.1.1', 3600, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    expect($content->view)->toBe('emails.hq-whitelist');
});

test('content includes IP address in data', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '203.0.113.42', 3600, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    expect($content->with)->toHaveKey('ip')
        ->and($content->with['ip'])->toBe('203.0.113.42');
});

test('content includes user name in data', function () {
    $user = User::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);
    $mailable = new HqWhitelistMail($user, '192.168.1.1', 3600, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    expect($content->with)->toHaveKey('userName')
        ->and($content->with['userName'])->toBe('Jane Smith');
});

test('content includes TTL in seconds', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '10.0.0.1', 7200, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    expect($content->with)->toHaveKey('ttlSeconds')
        ->and($content->with['ttlSeconds'])->toBe(7200);
});

test('content converts TTL to hours', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '10.0.0.1', 7200, Host::factory()->create(), 'ModSec logs'); // 2 hours

    $content = $mailable->content();

    expect($content->with)->toHaveKey('ttlHours')
        ->and($content->with['ttlHours'])->toBe(2.0); // 7200 / 3600 = 2.0
});

test('content calculates TTL hours with decimals', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '10.0.0.1', 5400, Host::factory()->create(), 'ModSec logs'); // 1.5 hours

    $content = $mailable->content();

    expect($content->with['ttlHours'])->toBe(1.5); // 5400 / 3600 = 1.5
});

test('content rounds TTL hours to 2 decimals', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '10.0.0.1', 5555, Host::factory()->create(), 'ModSec logs'); // ~1.543 hours

    $content = $mailable->content();

    expect($content->with['ttlHours'])->toBe(1.54); // Rounded to 2 decimals
});

test('content includes company name from config', function () {
    config()->set('company.name', 'Test Company Inc');

    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '10.0.0.1', 3600, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    expect($content->with)->toHaveKey('companyName')
        ->and($content->with['companyName'])->toBe('Test Company Inc');
});

test('content includes all required data keys', function () {
    $user = User::factory()->create();
    $hqHost = Host::factory()->create(['fqdn' => 'hq.example.com']);
    $mailable = new HqWhitelistMail($user, '192.168.1.1', 3600, $hqHost, 'logs');

    $content = $mailable->content();

    expect($content->with)->toHaveKeys([
        'ip',
        'userName',
        'ttlSeconds',
        'ttlHours',
        'companyName',
        'hqHostFqdn',
        'hqHostPanel',
        'modsecLogs',
        'timestamp',
    ]);
});

// ============================================================================
// SCENARIO 4: Attachments
// ============================================================================

test('attachments returns empty array', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '192.168.1.1', 3600, Host::factory()->create(), 'ModSec logs');

    $attachments = $mailable->attachments();

    expect($attachments)->toBeArray()
        ->and($attachments)->toBeEmpty();
});

// ============================================================================
// SCENARIO 5: Queue Implementation
// ============================================================================

test('mailable implements ShouldQueue', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '192.168.1.1', 3600, Host::factory()->create(), 'ModSec logs');

    expect($mailable)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('mailable uses Queueable trait', function () {
    $reflection = new ReflectionClass(HqWhitelistMail::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Illuminate\Bus\Queueable');
});

test('mailable uses SerializesModels trait', function () {
    $reflection = new ReflectionClass(HqWhitelistMail::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Illuminate\Queue\SerializesModels');
});

// ============================================================================
// SCENARIO 6: Different TTL Values
// ============================================================================

test('handles very short TTL', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '10.0.0.1', 60, Host::factory()->create(), 'ModSec logs'); // 1 minute

    $content = $mailable->content();

    expect($content->with['ttlSeconds'])->toBe(60)
        ->and($content->with['ttlHours'])->toBe(0.02); // 60/3600 = 0.016... rounded to 0.02
});

test('handles very long TTL', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '10.0.0.1', 86400, Host::factory()->create(), 'ModSec logs'); // 24 hours

    $content = $mailable->content();

    expect($content->with['ttlSeconds'])->toBe(86400)
        ->and($content->with['ttlHours'])->toBe(24.0);
});

test('handles default TTL from config', function () {
    $user = User::factory()->create();
    $hqHost = Host::factory()->create();
    $defaultTtl = config('unblock.hq.ttl', 7200);

    $mailable = new HqWhitelistMail($user, '10.0.0.1', $defaultTtl, $hqHost, 'logs');

    $content = $mailable->content();

    expect($content->with['ttlSeconds'])->toBe($defaultTtl);
});

// ============================================================================
// SCENARIO 7: IPv6 Support
// ============================================================================

test('handles IPv6 addresses', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '2001:0db8:85a3::8a2e:0370:7334', 3600, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    expect($content->with['ip'])->toBe('2001:0db8:85a3::8a2e:0370:7334');
});

test('handles compressed IPv6 addresses', function () {
    $user = User::factory()->create();
    $mailable = new HqWhitelistMail($user, '2001:db8::1', 3600, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    expect($content->with['ip'])->toBe('2001:db8::1');
});

// ============================================================================
// SCENARIO 8: User Name Edge Cases
// ============================================================================

test('handles user with empty name', function () {
    $user = User::factory()->create([
        'first_name' => '',
        'last_name' => '',
    ]);
    $mailable = new HqWhitelistMail($user, '192.168.1.1', 3600, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    // Empty first + empty last = ' ' (single space)
    expect($content->with['userName'])->toBe(' ');
});

test('handles user with special characters in name', function () {
    $user = User::factory()->create([
        'first_name' => 'Jöhn',
        'last_name' => 'Dœ-Smith',
    ]);
    $mailable = new HqWhitelistMail($user, '192.168.1.1', 3600, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    expect($content->with['userName'])->toBe('Jöhn Dœ-Smith');
});

test('handles user with very long name', function () {
    $longFirstName = str_repeat('A', 127);
    $longLastName = str_repeat('B', 127);
    $user = User::factory()->create([
        'first_name' => $longFirstName,
        'last_name' => $longLastName,
    ]);
    $mailable = new HqWhitelistMail($user, '192.168.1.1', 3600, Host::factory()->create(), 'ModSec logs');

    $content = $mailable->content();

    // Accessor combines with space: first + ' ' + last
    expect($content->with['userName'])->toBe($longFirstName.' '.$longLastName);
});
