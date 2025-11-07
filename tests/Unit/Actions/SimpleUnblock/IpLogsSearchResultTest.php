<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\IpLogsSearchResult;

// ============================================================================
// SCENARIO 1: Construction and Property Access
// ============================================================================

test('DTO can be constructed with IP found in logs', function () {
    $result = new IpLogsSearchResult(
        ip: '192.168.1.100',
        foundInLogs: true,
        logEntries: [
            '192.168.1.100 - - [01/Jan/2025:12:00:00] GET /index.html',
            '192.168.1.100 failed authentication',
        ]
    );

    expect($result->ip)->toBe('192.168.1.100')
        ->and($result->foundInLogs)->toBeTrue()
        ->and($result->logEntries)->toHaveCount(2)
        ->and($result->logEntries[0])->toContain('GET /index.html')
        ->and($result->logEntries[1])->toContain('failed authentication');
});

test('DTO can be constructed with IP not found in logs', function () {
    $result = new IpLogsSearchResult(
        ip: '10.0.0.50',
        foundInLogs: false,
        logEntries: []
    );

    expect($result->ip)->toBe('10.0.0.50')
        ->and($result->foundInLogs)->toBeFalse()
        ->and($result->logEntries)->toBeEmpty();
});

test('DTO can be constructed with default empty log entries', function () {
    $result = new IpLogsSearchResult(
        ip: '172.16.0.1',
        foundInLogs: false
    );

    expect($result->ip)->toBe('172.16.0.1')
        ->and($result->foundInLogs)->toBeFalse()
        ->and($result->logEntries)->toBeArray()
        ->and($result->logEntries)->toBeEmpty();
});

// ============================================================================
// SCENARIO 2: Immutability
// ============================================================================

test('DTO is immutable (readonly class)', function () {
    $result = new IpLogsSearchResult(
        ip: '192.168.1.1',
        foundInLogs: true,
        logEntries: ['log entry']
    );

    $reflection = new ReflectionClass($result);

    expect($reflection->isReadOnly())->toBeTrue();
});

// ============================================================================
// SCENARIO 3: IPv4 Addresses
// ============================================================================

test('DTO stores IPv4 address correctly', function () {
    $result = new IpLogsSearchResult(
        ip: '203.0.113.42',
        foundInLogs: true,
        logEntries: ['Apache log with 203.0.113.42']
    );

    expect($result->ip)->toBe('203.0.113.42')
        ->and($result->foundInLogs)->toBeTrue();
});

// ============================================================================
// SCENARIO 4: IPv6 Addresses
// ============================================================================

test('DTO stores IPv6 address correctly', function () {
    $result = new IpLogsSearchResult(
        ip: '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        foundInLogs: true,
        logEntries: ['Nginx log with IPv6']
    );

    expect($result->ip)->toBe('2001:0db8:85a3:0000:0000:8a2e:0370:7334')
        ->and($result->foundInLogs)->toBeTrue();
});

test('DTO stores compressed IPv6 address correctly', function () {
    $result = new IpLogsSearchResult(
        ip: '2001:db8::1',
        foundInLogs: false
    );

    expect($result->ip)->toBe('2001:db8::1')
        ->and($result->foundInLogs)->toBeFalse();
});

// ============================================================================
// SCENARIO 5: Multiple Log Entries
// ============================================================================

test('DTO handles multiple log entries correctly', function () {
    $logEntries = [
        '[2025-01-01 10:00:00] Apache: 192.168.1.1 accessed /admin',
        '[2025-01-01 10:05:00] Nginx: 192.168.1.1 GET /login',
        '[2025-01-01 10:10:00] Exim: 192.168.1.1 authentication failed',
        '[2025-01-01 10:15:00] CSF: 192.168.1.1 blocked',
    ];

    $result = new IpLogsSearchResult(
        ip: '192.168.1.1',
        foundInLogs: true,
        logEntries: $logEntries
    );

    expect($result->logEntries)->toHaveCount(4)
        ->and($result->logEntries)->toBe($logEntries);
});

test('DTO preserves log entries order', function () {
    $logEntries = [
        'First log entry',
        'Second log entry',
        'Third log entry',
    ];

    $result = new IpLogsSearchResult(
        ip: '10.0.0.1',
        foundInLogs: true,
        logEntries: $logEntries
    );

    expect($result->logEntries[0])->toBe('First log entry')
        ->and($result->logEntries[1])->toBe('Second log entry')
        ->and($result->logEntries[2])->toBe('Third log entry');
});

// ============================================================================
// SCENARIO 6: Edge Cases
// ============================================================================

test('DTO with foundInLogs true but empty log entries is valid', function () {
    // This might seem contradictory, but it's technically valid for the DTO
    $result = new IpLogsSearchResult(
        ip: '192.168.1.1',
        foundInLogs: true,
        logEntries: []
    );

    expect($result->foundInLogs)->toBeTrue()
        ->and($result->logEntries)->toBeEmpty();
});

test('DTO handles very long log entries', function () {
    $longLogEntry = str_repeat('a', 10000).' - IP: 192.168.1.1';

    $result = new IpLogsSearchResult(
        ip: '192.168.1.1',
        foundInLogs: true,
        logEntries: [$longLogEntry]
    );

    // 10000 'a' + ' - IP: 192.168.1.1' = 10000 + 18 = 10018
    expect($result->logEntries[0])->toHaveLength(10018)
        ->and($result->logEntries[0])->toContain('IP: 192.168.1.1');
});

test('DTO handles log entries with special characters', function () {
    $logEntries = [
        'Log with "quotes" and \'apostrophes\'',
        'Log with $pecial ch@racters & symbols #!',
        "Log with newline\ncharacter", // Double quotes to interpret \n
        "Log with tab\tcharacter", // Double quotes to interpret \t
    ];

    $result = new IpLogsSearchResult(
        ip: '192.168.1.1',
        foundInLogs: true,
        logEntries: $logEntries
    );

    expect($result->logEntries)->toHaveCount(4)
        ->and($result->logEntries[0])->toContain('"quotes"')
        ->and($result->logEntries[1])->toContain('$pecial')
        ->and($result->logEntries[2])->toContain("\n")
        ->and($result->logEntries[3])->toContain("\t");
});

// ============================================================================
// SCENARIO 7: Real-world Log Patterns
// ============================================================================

test('DTO stores Apache access log format correctly', function () {
    $logEntries = [
        '192.168.1.100 - - [01/Jan/2025:12:34:56 +0000] "GET /index.php HTTP/1.1" 200 1234',
    ];

    $result = new IpLogsSearchResult(
        ip: '192.168.1.100',
        foundInLogs: true,
        logEntries: $logEntries
    );

    expect($result->logEntries[0])->toContain('192.168.1.100')
        ->and($result->logEntries[0])->toContain('GET /index.php');
});

test('DTO stores CSF firewall log format correctly', function () {
    $logEntries = [
        'Jan 01 12:00:00 server lfd[1234]: *Firewall Block* 192.168.1.1 (CN/China/-): 5 in the last 3600 secs',
    ];

    $result = new IpLogsSearchResult(
        ip: '192.168.1.1',
        foundInLogs: true,
        logEntries: $logEntries
    );

    expect($result->logEntries[0])->toContain('*Firewall Block*')
        ->and($result->logEntries[0])->toContain('192.168.1.1');
});

test('DTO stores Exim authentication failure log correctly', function () {
    $logEntries = [
        '2025-01-01 12:00:00 dovecot_login authenticator failed for (User) [192.168.1.1]: 535 Incorrect authentication data',
    ];

    $result = new IpLogsSearchResult(
        ip: '192.168.1.1',
        foundInLogs: true,
        logEntries: $logEntries
    );

    expect($result->logEntries[0])->toContain('authenticator failed')
        ->and($result->logEntries[0])->toContain('[192.168.1.1]');
});

// ============================================================================
// SCENARIO 8: Empty IP Edge Case
// ============================================================================

test('DTO accepts empty IP string', function () {
    // Not ideal in real usage, but technically the DTO doesn't validate
    $result = new IpLogsSearchResult(
        ip: '',
        foundInLogs: false
    );

    expect($result->ip)->toBe('')
        ->and($result->foundInLogs)->toBeFalse();
});
