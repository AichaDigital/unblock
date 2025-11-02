<?php

declare(strict_types=1);

/**
 * Tests for CommandOutputParserTrait - Optimized Version
 * Uses real stubs to validate parsing logic
 */

use App\Notifications\Admin\ErrorParsingNotification;
use App\Traits\CommandOutputParserTrait;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->parser = new class
    {
        use CommandOutputParserTrait;
    };
});

describe('Core Parsing Logic', function () {
    test('parseOutput processes CSF deny lines correctly', function () {
        $data = require testPath('stubs').'/directadmin_deny_csf.php';

        $array = $this->parser->parseOutput($data['csf']);

        expect($array)->toBeArray()->toHaveCount(7);
    });

    test('searchDeny extracts IP and date from deny line', function () {
        $lines = [
            'csf.deny: 192.0.2.123 # lfd: (PERMBLOCK) 192.0.2.123 (XX/Unknown/-) has had more than 4 temp blocks in the last 86400 secs - Thu Dec 05 10:33:35 2024',
        ];

        $result = $this->parser->searchDeny($lines);

        expect($result)->toBeArray()
            ->and($result['ip'])->toBe('192.0.2.123')
            ->and($result['date'])->toBe('2024-12-05 10:33:35');
    });

    test('searchDeny notifies admin on parsing errors', function () {
        Notification::fake();

        $lines = [
            'csf.deny: INVALID_IP # lfd: (PERMBLOCK) INVALID_IP (XX/Unknown/-) - Thu Dec 01 10:33:35 2024',
        ];

        $this->parser->searchDeny($lines);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            ErrorParsingNotification::class
        );
    });
});

describe('Service-Specific Parsing', function () {
    test('parseOutput handles ModSecurity logs', function () {
        $data = require testPath('stubs').'/directadmin_mod_security_da.php';

        $array = $this->parser->parseOutput($data['mod_security_da']);

        expect($array)->toBeArray()->toHaveCount(3);
    });

    test('parseOutput handles Exim logs', function () {
        $data = require testPath('stubs').'/directadmin_exim.php';

        $array = $this->parser->parseOutput($data['exim_directadmin']);

        expect($array)->toBeArray()->toHaveCount(6);
    });

    test('parseOutput handles Dovecot logs', function () {
        $data = require testPath('stubs').'/directadmin_dovecot.php';

        $array = $this->parser->parseOutput($data['dovecot']);

        expect($array)->toBeArray()->toHaveCount(6);
    });
});

describe('Utility Functions', function () {
    test('containsAny detects needles in haystack', function () {
        $line = 'csf.deny: 31.4.198.125 # BFM: dovecot1=31 - Sun Dec  1 10:33:35 2024';
        $needles = ['csf.deny', 'Temporary Blocks'];

        expect($this->parser->containsAny($line, $needles))->toBeTrue();
    });

    test('parseDateFromLine extracts dates correctly', function () {
        $line1 = 'csf.deny: 31.4.198.125 # BFM: dovecot1=31 - Sun Dec  1 10:33:35 2024';
        $line2 = 'csf.deny: 31.4.198.125 # BFM: dovecot1=31 - Sun Dec 1 10:33:35 2024';

        expect($this->parser->parseDateFromLine($line1))->toBe('2024-12-01 10:33:35')
            ->and($this->parser->parseDateFromLine($line2))->toBe('2024-12-01 10:33:35');
    });
});
