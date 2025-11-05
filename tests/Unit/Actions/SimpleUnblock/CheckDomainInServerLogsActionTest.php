<?php

declare(strict_types=1);

use App\Actions\SimpleUnblock\BuildDomainSearchCommandsAction;
use App\Actions\SimpleUnblock\CheckDomainInServerLogsAction;
use App\Actions\SimpleUnblock\DomainLogsSearchResult;
use App\Enums\PanelType;
use App\Exceptions\ConnectionFailedException;
use App\Models\Host;
use App\Services\SshConnectionManager;
use App\Services\SshSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('action finds domain in logs when result is not empty', function () {
    $host = Host::factory()->create([
        'panel' => PanelType::CPANEL,
    ]);

    // Stub SSH session to return non-empty result
    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andReturn('192.168.1.1 - example.com [05/Nov/2025] "GET / HTTP/1.1" 200');
    $session->allows('cleanup')->andReturn(true);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);
    $result = $action->handle('192.168.1.1', 'example.com', $host);

    expect($result->found)->toBeTrue()
        ->and($result->matchingLogs)->toHaveCount(1)
        ->and($result->matchingLogs[0])->toContain('example.com');
});

test('action returns not found when result is empty', function () {
    $host = Host::factory()->create([
        'panel' => PanelType::DIRECTADMIN,
    ]);

    // Stub SSH session to return empty result
    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andReturn('');
    $session->allows('cleanup')->andReturn(true);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);
    $result = $action->handle('192.168.1.1', 'example.com', $host);

    expect($result->found)->toBeFalse()
        ->and($result->matchingLogs)->toBeEmpty();
});

test('action returns not found when result is whitespace only', function () {
    $host = Host::factory()->create();

    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andReturn("   \n\t  ");
    $session->allows('cleanup')->andReturn(true);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);
    $result = $action->handle('192.168.1.1', 'example.com', $host);

    expect($result->found)->toBeFalse();
});

test('action handles connection failure gracefully', function () {
    $host = Host::factory()->create();

    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andThrow(new ConnectionFailedException('SSH connection failed'));
    $session->allows('cleanup')->andReturn(true);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);
    $result = $action->handle('192.168.1.1', 'example.com', $host);

    expect($result->found)->toBeFalse()
        ->and($result->searchedPaths)->toBeEmpty();

    Log::shouldHaveReceived('warning')->with('Could not check domain in logs (connection failed)', Mockery::any());
});

test('action cleans up session even when exception occurs', function () {
    $host = Host::factory()->create();

    $cleanupCalled = false;

    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andThrow(new Exception('Unexpected error'));
    $session->allows('cleanup')->andReturnUsing(function () use (&$cleanupCalled) {
        $cleanupCalled = true;
        return true;
    });

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);

    try {
        $action->handle('192.168.1.1', 'example.com', $host);
    } catch (Exception $e) {
        // Exception expected
    }

    expect($cleanupCalled)->toBeTrue();
});

test('action extracts search paths from commands', function () {
    $host = Host::factory()->create([
        'panel' => PanelType::CPANEL,
    ]);

    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andReturn('some log entry');
    $session->allows('cleanup')->andReturn(true);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);
    $result = $action->handle('192.168.1.1', 'example.com', $host);

    // cPanel has 4 search paths
    expect($result->searchedPaths)->toHaveCount(4)
        ->and($result->searchedPaths)->toContain('/var/log/apache2')
        ->and($result->searchedPaths)->toContain('/var/log/nginx')
        ->and($result->searchedPaths)->toContain('/var/log/exim')
        ->and($result->searchedPaths)->toContain('/usr/local/apache/domlogs');
});

test('action combines commands with OR operator', function () {
    $host = Host::factory()->create([
        'panel' => PanelType::DIRECTADMIN,
    ]);

    $executedCommand = null;

    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andReturnUsing(function ($command) use (&$executedCommand) {
        $executedCommand = $command;
        return '';
    });
    $session->allows('cleanup')->andReturn(true);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);
    $action->handle('192.168.1.1', 'example.com', $host);

    expect($executedCommand)->toContain(' || ')
        ->and($executedCommand)->toContain('/var/log/apache2')
        ->and($executedCommand)->toContain('/var/log/nginx')
        ->and($executedCommand)->toContain('/var/log/exim');
});

test('action logs debug information at start', function () {
    $host = Host::factory()->create([
        'fqdn' => 'test.example.com',
        'panel' => PanelType::CPANEL,
    ]);

    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andReturn('');
    $session->allows('cleanup')->andReturn(true);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);
    $action->handle('192.168.1.1', 'example.com', $host);

    Log::shouldHaveReceived('debug')->with('Searching for domain in server logs', Mockery::on(function ($context) {
        return $context['ip'] === '192.168.1.1'
            && $context['domain'] === 'example.com'
            && $context['host_fqdn'] === 'test.example.com';
    }));
});

test('action logs info with result preview when found', function () {
    $host = Host::factory()->create();

    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andReturn('192.168.1.1 - example.com - Found in logs');
    $session->allows('cleanup')->andReturn(true);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);
    $action->handle('192.168.1.1', 'example.com', $host);

    Log::shouldHaveReceived('info')->with('Domain search in logs completed', Mockery::on(function ($context) {
        return $context['found'] === true
            && isset($context['result_preview'])
            && str_contains($context['result_preview'], 'example.com');
    }));
});

test('action returns DomainLogsSearchResult object', function () {
    $host = Host::factory()->create();

    $session = Mockery::mock(SshSession::class);
    $session->allows('execute')->andReturn('log entry');
    $session->allows('cleanup')->andReturn(true);

    $sshManager = Mockery::mock(SshConnectionManager::class);
    $sshManager->allows('createSession')->andReturn($session);

    $buildCommands = new BuildDomainSearchCommandsAction();

    Log::spy();

    $action = new CheckDomainInServerLogsAction($sshManager, $buildCommands);
    $result = $action->handle('192.168.1.1', 'example.com', $host);

    expect($result)->toBeInstanceOf(DomainLogsSearchResult::class);
});

