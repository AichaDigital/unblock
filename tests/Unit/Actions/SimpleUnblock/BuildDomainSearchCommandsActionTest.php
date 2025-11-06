<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\BuildDomainSearchCommandsAction;

test('action builds apache command correctly', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', 'example.com', 'directadmin');

    $apacheCommand = $commands[0];

    expect($apacheCommand)->toContain('/var/log/apache2')
        ->and($apacheCommand)->toContain("'192.168.1.1'")
        ->and($apacheCommand)->toContain("'example.com'")
        ->and($apacheCommand)->toContain('mtime -7')
        ->and($apacheCommand)->toContain('access.log');
});

test('action builds nginx command correctly', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', 'example.com', 'directadmin');

    $nginxCommand = $commands[1];

    expect($nginxCommand)->toContain('/var/log/nginx')
        ->and($nginxCommand)->toContain("'192.168.1.1'")
        ->and($nginxCommand)->toContain("'example.com'")
        ->and($nginxCommand)->toContain('mtime -7')
        ->and($nginxCommand)->toContain('access.log');
});

test('action builds exim command correctly', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', 'example.com', 'directadmin');

    $eximCommand = $commands[2];

    expect($eximCommand)->toContain('/var/log/exim')
        ->and($eximCommand)->toContain("'192.168.1.1'")
        ->and($eximCommand)->toContain("'example.com'")
        ->and($eximCommand)->toContain('mtime -7')
        ->and($eximCommand)->toContain('mainlog');
});

test('action includes cpanel domlogs command for cpanel servers', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', 'example.com', 'cpanel');

    expect($commands)->toHaveCount(4);

    $cpanelCommand = $commands[3];

    expect($cpanelCommand)->toContain('/usr/local/apache/domlogs')
        ->and($cpanelCommand)->toContain("'192.168.1.1'")
        ->and($cpanelCommand)->toContain("'example.com'")
        ->and($cpanelCommand)->toContain('mtime -7');
});

test('action does not include cpanel domlogs for non-cpanel servers', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commandsDirectAdmin = $action->handle('192.168.1.1', 'example.com', 'directadmin');
    $commandsNone = $action->handle('192.168.1.1', 'example.com', 'none');

    expect($commandsDirectAdmin)->toHaveCount(3)
        ->and($commandsNone)->toHaveCount(3);
});

test('action properly escapes IP address with special characters', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1; rm -rf /', 'example.com', 'directadmin');

    foreach ($commands as $command) {
        // escapeshellarg should wrap the IP in single quotes
        expect($command)->toContain("'192.168.1.1; rm -rf /'")
            ->and($command)->not->toContain('192.168.1.1; rm -rf /'."\n"); // Ensure no unescaped version
    }
});

test('action properly escapes domain with special characters', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', "example.com'; DROP TABLE users--", 'directadmin');

    foreach ($commands as $command) {
        // escapeshellarg should wrap the domain and escape any single quotes
        // The exact escaping format may vary, but it should contain the original string escaped
        expect($command)->toContain("'example.com")
            ->and($command)->toContain('DROP TABLE users--');
    }
});

test('action returns array with all standard commands', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', 'example.com', 'directadmin');

    expect($commands)->toBeArray()
        ->and($commands)->toHaveCount(3);

    // Verify all commands are strings
    foreach ($commands as $command) {
        expect($command)->toBeString()
            ->and($command)->not->toBeEmpty();
    }
});

test('action handles IPv6 addresses correctly', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('2001:0db8:85a3:0000:0000:8a2e:0370:7334', 'example.com', 'directadmin');

    expect($commands)->toBeArray()
        ->and($commands)->toHaveCount(3);

    foreach ($commands as $command) {
        expect($command)->toContain("'2001:0db8:85a3:0000:0000:8a2e:0370:7334'");
    }
});

test('action handles domains with subdomains correctly', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', 'subdomain.example.com', 'cpanel');

    expect($commands)->toHaveCount(4);

    foreach ($commands as $command) {
        expect($command)->toContain("'subdomain.example.com'");
    }
});

test('action handles case sensitivity correctly', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', 'EXAMPLE.COM', 'directadmin');

    // Verify grep uses case-insensitive search (-i flag) for domain
    foreach ($commands as $command) {
        expect($command)->toContain('grep -i');
    }
});

test('action limits results with head command', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', 'example.com', 'cpanel');

    // All commands should limit output to prevent overwhelming results
    foreach ($commands as $command) {
        expect($command)->toContain('head -1');
    }
});

test('action suppresses errors with stderr redirection', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', 'example.com', 'directadmin');

    // All commands should redirect stderr to /dev/null
    foreach ($commands as $command) {
        expect($command)->toContain('2>/dev/null');
    }
});

test('action builds commands for empty domain', function () {
    $action = new BuildDomainSearchCommandsAction;
    $commands = $action->handle('192.168.1.1', '', 'directadmin');

    expect($commands)->toBeArray()
        ->and($commands)->toHaveCount(3);

    // Verify empty domain is escaped as ''
    foreach ($commands as $command) {
        expect($command)->toContain("''");
    }
});

test('action builds different commands for different panel types', function () {
    $action = new BuildDomainSearchCommandsAction;

    $cpanelCommands = $action->handle('192.168.1.1', 'example.com', 'cpanel');
    $directAdminCommands = $action->handle('192.168.1.1', 'example.com', 'directadmin');

    expect($cpanelCommands)->toHaveCount(4)
        ->and($directAdminCommands)->toHaveCount(3)
        ->and($cpanelCommands)->not->toEqual($directAdminCommands);
});
