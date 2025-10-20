<?php

declare(strict_types=1);

use App\Notifications\Admin\ErrorParsingNotification;
use App\Traits\CommandOutputParserTrait;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->traitParse = new class
    {
        use CommandOutputParserTrait;
    };
});

test('parse log on clean lines', function () {
    $stubFilePath = testPath('stubs').'/directadmin_deny_csf.php';

    $data = require $stubFilePath;

    expect(file_exists($stubFilePath))->toBeTrue()->and($data)->toHaveKey('csf');

    $array = $this->traitParse->parseOutput($data['csf']);

    expect($array)->toBeArray()->and($array)->toHaveCount(7);
});

test('containsAny function works correctly', function () {
    $line = 'csf.deny: 31.4.198.125 # BFM: dovecot1=31 (31-4-198-125.red-acceso.airtel.net) - Sun Dec  1 10:33:35 2024';
    $needles = ['csf.deny', 'Temporary Blocks'];

    // Test if the function correctly identifies the presence of 'csf.deny'
    expect($this->traitParse->containsAny($line, $needles))->toBeTrue();
});

test('parse date from line', function () {
    $line = 'csf.deny: 31.4.198.125 # BFM: dovecot1=31 (31-4-198-125.red-acceso.airtel.net) - Sun Dec  1 10:33:35 2024';

    $date = $this->traitParse->parseDateFromLine($line);

    expect($date)->toBeString('2024-12-01 10:33:35');

    $line = 'csf.deny: 31.4.198.125 # BFM: dovecot1=31 (31-4-198-125.red-acceso.airtel.net) - Sun Dec 1 10:33:35 2024';

    $date = $this->traitParse->parseDateFromLine($line);

    expect($date)->toBeString('2024-12-01 10:33:35');
});

test('search deny on clean array', function () {
    $array = [
        'csf.deny: 192.0.2.123 # lfd: (PERMBLOCK) 192.0.2.123 (XX/Unknown/-) has had more than 4 temp blocks in the last 86400 secs - Thu Dec 05 10:33:35 2024',
    ];

    $result = $this->traitParse->searchDeny($array);

    expect($result)->toBeArray()
        ->and($result['ip'])->toBe('192.0.2.123')
        ->and($result['date'])->toBe('2024-12-05 10:33:35');
});

test('search send notification if any issue with ip or date', function () {
    Notification::fake();

    $array = [
        'csf.deny: INVALID_IP # lfd: (PERMBLOCK) INVALID_IP (XX/Unknown/-) has had more than 4 temp blocks in the last 86400 secs - Thu Dec 01 10:33:35 2024',
    ];

    $this->traitParse->searchDeny($array);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        ErrorParsingNotification::class
    );
});

test('extract data from mod_security_da if exists', function () {
    $stubFilePath = testPath('stubs').'/directadmin_mod_security_da.php';

    $data = require $stubFilePath;

    expect(file_exists($stubFilePath))->toBeTrue()->and($data)->toHaveKey('mod_security_da');

    $array = $this->traitParse->parseOutput($data['mod_security_da']);

    // El procesamiento ModSecurity ahora usa JSON y retorna menos elementos procesados
    expect($array)->toBeArray()->and($array)->toHaveCount(3);
});

test('extract data from exim directadmin if exists', function () {
    $stubFilePath = testPath('stubs').'/directadmin_exim.php';

    $data = require $stubFilePath;

    expect(file_exists($stubFilePath))->toBeTrue()->and($data)->toHaveKey('exim_directadmin');

    $array = $this->traitParse->parseOutput($data['exim_directadmin']);

    expect($array)->toBeArray()->and($array)->toHaveCount(6);
});

test('extract data from dovecot directadmin if exists', function () {
    $stubFilePath = testPath('stubs').'/directadmin_dovecot.php';

    $data = require $stubFilePath;

    expect(file_exists($stubFilePath))->toBeTrue()->and($data)->toHaveKey('dovecot');

    $array = $this->traitParse->parseOutput($data['dovecot']);

    expect($array)->toBeArray()->and($array)->toHaveCount(6);
});
