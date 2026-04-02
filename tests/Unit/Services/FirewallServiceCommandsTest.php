<?php

declare(strict_types=1);

use App\Services\FirewallService;

test('unblock command does not include whitelist', function () {
    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    // Unblock debe solo remover, NO whitelist
    expect($commands['unblock'])
        ->toContain('csf -dr')
        ->toContain('csf -tr')
        ->not->toContain('csf -ta');
});

test('whitelist command uses default TTL from config', function () {
    config(['unblock.whitelist_ttl' => 86400]);

    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands['whitelist'])
        ->toContain('csf -ta')
        ->toContain('86400');
});

test('whitelist_simple command uses simple_mode TTL from config', function () {
    config(['unblock.simple_mode.whitelist_ttl' => 3600]);

    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands['whitelist_simple'])
        ->toContain('csf -ta')
        ->toContain('3600');
});

test('whitelist_hq command uses hq TTL from config', function () {
    config(['unblock.hq.ttl' => 7200]);

    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands['whitelist_hq'])
        ->toContain('csf -ta')
        ->toContain('7200');
});

test('da_bfm_check command searches in blacklist file', function () {
    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands['da_bfm_check'])
        ->toContain('grep')
        ->toContain('/usr/local/directadmin/data/admin/ip_blacklist');
});

test('da_bfm_remove command removes IP from blacklist', function () {
    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands['da_bfm_remove'])
        ->toContain('sed')
        ->toContain('/usr/local/directadmin/data/admin/ip_blacklist');
});

test('da_bfm_whitelist_add command appends IP to whitelist', function () {
    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands['da_bfm_whitelist_add'])
        ->toContain('echo')
        ->toContain('/usr/local/directadmin/data/admin/ip_whitelist');
});

test('exim_directadmin command includes || true suffix', function () {
    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands['exim_directadmin'])->toEndWith('|| true');
});

test('dovecot_directadmin command includes || true suffix', function () {
    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands['dovecot_directadmin'])->toEndWith('|| true');
});

test('lfd_history command exists and includes || true suffix', function () {
    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands)->toHaveKey('lfd_history')
        ->and($commands['lfd_history'])->toEndWith('|| true')
        ->and($commands['lfd_history'])->toContain('lfd.log');
});

test('lfd_history command searches both lfd.log and lfd.log.1', function () {
    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    expect($commands['lfd_history'])->toContain('lfd.log')
        ->and($commands['lfd_history'])->toContain('lfd.log.1');
});

test('all grep-based commands have || true to prevent exit code 1 on no matches', function () {
    $service = new FirewallService;
    $commands = $service->getAvailableCommands();

    $grepCommands = [
        'csf_deny_check',
        'csf_tempip_check',
        'mod_security_da',
        'exim_cpanel',
        'dovecot_cpanel',
        'exim_directadmin',
        'dovecot_directadmin',
        'lfd_history',
        'da_bfm_check',
    ];

    foreach ($grepCommands as $cmd) {
        expect($commands[$cmd])->toEndWith('|| true', "Command '{$cmd}' should end with '|| true'");
    }
});
