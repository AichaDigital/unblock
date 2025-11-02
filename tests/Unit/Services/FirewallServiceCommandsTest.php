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
