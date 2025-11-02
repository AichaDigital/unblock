<?php

declare(strict_types=1);

use App\Services\CsfOutputParser;

test('parses csf deny line with reason and timestamp', function () {
    $csfLine = '158.173.23.58 # lfd: (smtpauth) Failed SMTP AUTH login from 158.173.23.58 (GB/United Kingdom/-): 5 in the last 3600 secs - Thu Oct 30 06:27:30 2025';

    $parser = new CsfOutputParser;
    $result = $parser->parseDenyLine($csfLine);

    expect($result)->toBeArray()
        ->and($result['ip'])->toBe('158.173.23.58')
        ->and($result['reason_type'])->toBe('smtpauth')
        ->and($result['reason'])->toBe('Failed SMTP AUTH login')
        ->and($result['location'])->toBe('GB/United Kingdom/-')
        ->and($result['attempts'])->toBe(5)
        ->and($result['timeframe'])->toBe(3600)
        ->and($result['timestamp'])->toBe('Thu Oct 30 06:27:30 2025');
});

test('parses csf deny line without location', function () {
    $csfLine = '192.168.1.100 # lfd: (imapd) Failed IMAP login: 10 in the last 7200 secs - Fri Nov  1 10:55:16 2025';

    $parser = new CsfOutputParser;
    $result = $parser->parseDenyLine($csfLine);

    expect($result)->toBeArray()
        ->and($result['ip'])->toBe('192.168.1.100')
        ->and($result['reason_type'])->toBe('imapd')
        ->and($result['reason'])->toBe('Failed IMAP login')
        ->and($result['attempts'])->toBe(10)
        ->and($result['timeframe'])->toBe(7200)
        ->and($result['timestamp'])->toBe('Fri Nov  1 10:55:16 2025');
});

test('parses csf deny line with simple reason', function () {
    $csfLine = '1.2.3.4 # Manual block by admin - Sat Nov  2 12:00:00 2025';

    $parser = new CsfOutputParser;
    $result = $parser->parseDenyLine($csfLine);

    expect($result)->toBeArray()
        ->and($result['ip'])->toBe('1.2.3.4')
        ->and($result['reason'])->toContain('Manual block')
        ->and($result['timestamp'])->toBe('Sat Nov  2 12:00:00 2025');
});

test('extracts human readable summary from csf output', function () {
    $csfOutput = <<<'CSF'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

filter DENYIN           231     16   736 DROP       all  --  !lo    *       161.248.179.179      0.0.0.0/0

filter DENYOUT          231     11  2597 LOGDROPOUT  all  --  *      !lo     0.0.0.0/0            161.248.179.179

csf.deny: 161.248.179.179 # lfd: (smtpauth) Failed SMTP AUTH login from 161.248.179.179 (RO/Romania/-): 5 in the last 3600 secs - Sat Nov  1 10:55:16 2025
CSF;

    $parser = new CsfOutputParser;
    $summary = $parser->extractHumanReadableSummary($csfOutput);

    expect($summary)->toBeArray()
        ->and($summary['blocked'])->toBeTrue()
        ->and($summary['block_type'])->toBe('csf.deny')
        ->and($summary['reason_short'])->toBe('Failed SMTP AUTH login')
        ->and($summary['attempts'])->toBe(5)
        ->and($summary['location'])->toBe('RO/Romania/-')
        ->and($summary['blocked_since'])->toBe('Sat Nov  1 10:55:16 2025');
});

test('extracts summary from csf output without deny line', function () {
    $csfOutput = <<<'CSF'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

filter DENYIN           231     16   736 DROP       all  --  !lo    *       161.248.179.179      0.0.0.0/0

filter DENYOUT          231     11  2597 LOGDROPOUT  all  --  *      !lo     0.0.0.0/0            161.248.179.179
CSF;

    $parser = new CsfOutputParser;
    $summary = $parser->extractHumanReadableSummary($csfOutput);

    expect($summary)->toBeArray()
        ->and($summary['blocked'])->toBeTrue()
        ->and($summary['block_type'])->toBe('firewall_rules')
        ->and($summary['reason_short'])->toBeNull();
});

test('returns not blocked when no block patterns found', function () {
    $csfOutput = <<<'CSF'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
No matches found for 1.2.3.4 in iptables
CSF;

    $parser = new CsfOutputParser;
    $summary = $parser->extractHumanReadableSummary($csfOutput);

    expect($summary)->toBeArray()
        ->and($summary['blocked'])->toBeFalse()
        ->and($summary['reason_short'])->toBeNull();
});

test('handles malformed csf output gracefully', function () {
    $csfOutput = 'Random text without proper CSF format';

    $parser = new CsfOutputParser;
    $summary = $parser->extractHumanReadableSummary($csfOutput);

    expect($summary)->toBeArray()
        ->and($summary['blocked'])->toBeFalse();
});
