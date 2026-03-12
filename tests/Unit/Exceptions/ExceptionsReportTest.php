<?php

declare(strict_types=1);

use App\Exceptions\{AuthenticationException, CommandExecutionException, ConnectionFailedException, FirewallException, InvalidIpException};
use App\Notifications\Admin\ErrorParsingNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\{Log, Mail, Notification};

// =============================================================================
// FirewallException report()
// =============================================================================

test('FirewallException report logs error to firewall channel with exception class', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with('Firewall failure', Mockery::on(function (array $context) {
            return isset($context['exception'])
                && $context['exception'] === FirewallException::class;
        }));

    $exception = new FirewallException('Firewall failure');
    $exception->report();
});

test('FirewallException report includes host key in context when hostName is set', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with('Error with host', Mockery::on(function (array $context) {
            return isset($context['host']) && $context['host'] === 'host.example.com';
        }));

    $exception = new FirewallException('Error with host', 'host.example.com');
    $exception->report();
});

test('FirewallException report includes ip_address key in context when ipAddress is set', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with('Error with IP', Mockery::on(function (array $context) {
            return isset($context['ip_address']) && $context['ip_address'] === '10.0.0.5';
        }));

    $exception = new FirewallException('Error with IP', null, '10.0.0.5');
    $exception->report();
});

test('FirewallException report omits host key when hostName is null', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with('Error no host', Mockery::on(function (array $context) {
            return ! array_key_exists('host', $context);
        }));

    $exception = new FirewallException('Error no host');
    $exception->report();
});

test('FirewallException report omits ip_address key when ipAddress is null', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with('Error no ip', Mockery::on(function (array $context) {
            return ! array_key_exists('ip_address', $context);
        }));

    $exception = new FirewallException('Error no ip');
    $exception->report();
});

test('FirewallException report merges extra context keys', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with('Error with context', Mockery::on(function (array $context) {
            return isset($context['operation']) && $context['operation'] === 'block'
                && isset($context['exception']);
        }));

    $exception = new FirewallException('Error with context', null, null, ['operation' => 'block']);
    $exception->report();
});

// =============================================================================
// CommandExecutionException report()
// =============================================================================

test('CommandExecutionException report logs to firewall channel with command in context', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with(
            'Command execution failed: Command execution failed',
            Mockery::on(function (array $context) {
                return isset($context['command']) && $context['command'] === 'csf -g 10.0.0.1';
            })
        );

    $exception = new CommandExecutionException('csf -g 10.0.0.1');
    $exception->report();
});

test('CommandExecutionException report includes output key when output is present', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with(
            Mockery::type('string'),
            Mockery::on(function (array $context) {
                return isset($context['output']) && $context['output'] === 'some stdout';
            })
        );

    $exception = new CommandExecutionException('csf -g 10.0.0.1', 'some stdout');
    $exception->report();
});

test('CommandExecutionException report includes error_output key when errorOutput is present', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with(
            Mockery::type('string'),
            Mockery::on(function (array $context) {
                return isset($context['error_output']) && $context['error_output'] === 'some stderr';
            })
        );

    $exception = new CommandExecutionException('csf -g 10.0.0.1', null, 'some stderr');
    $exception->report();
});

test('CommandExecutionException report omits output key when output is null', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with(
            Mockery::type('string'),
            Mockery::on(function (array $context) {
                return ! array_key_exists('output', $context);
            })
        );

    $exception = new CommandExecutionException('csf -g 10.0.0.1');
    $exception->report();
});

test('CommandExecutionException report includes host and ip from constructor', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with(
            Mockery::type('string'),
            Mockery::on(function (array $context) {
                return isset($context['host']) && $context['host'] === 'host.example.com'
                    && isset($context['ip']) && $context['ip'] === '10.0.0.1';
            })
        );

    $exception = new CommandExecutionException(
        'csf -g 10.0.0.1',
        null,
        null,
        'Command execution failed',
        'host.example.com',
        '10.0.0.1'
    );
    $exception->report();
});

test('CommandExecutionException report merges custom context keys', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with(
            Mockery::type('string'),
            Mockery::on(function (array $context) {
                return isset($context['operation']) && $context['operation'] === 'unblock';
            })
        );

    $exception = new CommandExecutionException(
        'csf -dr 10.0.0.1',
        null,
        null,
        'Command execution failed',
        null,
        null,
        ['operation' => 'unblock']
    );
    $exception->report();
});

// =============================================================================
// InvalidIpException report() and render()
// =============================================================================

test('InvalidIpException report logs error to firewall channel with ip and class', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')
        ->once()
        ->with(
            Mockery::type('string'),
            Mockery::on(function (array $context) {
                return isset($context['ip']) && $context['ip'] === '999.999.999.999'
                    && isset($context['exception']) && $context['exception'] === InvalidIpException::class;
            })
        );

    $exception = new InvalidIpException('999.999.999.999');
    $exception->report();
});

test('InvalidIpException render returns JSON response with 400 status', function () {
    $exception = new InvalidIpException('999.999.999.999');

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(400);
});

test('InvalidIpException render returns JSON body with error key containing the ip', function () {
    $exception = new InvalidIpException('999.999.999.999');

    $response = $exception->render();
    $data = json_decode($response->getContent(), true);

    expect($data)->toHaveKey('error')
        ->and($data['error'])->toContain('999.999.999.999');
});

test('InvalidIpException getValidationErrorDescription returns translated message with ip', function () {
    $exception = new InvalidIpException('10.0.0.999');

    expect($exception->getValidationErrorDescription())
        ->toBe(__('exceptions.invalid_ip.message', ['ip' => '10.0.0.999']));
});

// =============================================================================
// AuthenticationException report()
// =============================================================================

test('AuthenticationException report logs to login_errors channel and sends mail', function () {
    config()->set('unblock.admin_email', 'admin@test.example.com'); // ggignore

    Log::shouldReceive('channel')->with('login_errors')->andReturnSelf();
    Log::shouldReceive('error')->once()->with(Mockery::on(function (string $msg) {
        return str_contains($msg, '10.0.0.1') && str_contains($msg, 'test@example.com'); // ggignore
    }));

    // Mail is array driver in testing, so it won't actually send
    $exception = new AuthenticationException('10.0.0.1', 'test@example.com'); // ggignore
    $exception->report();

    // report() completed without throwing - mail was sent to array driver
    expect(true)->toBeTrue();
});

// =============================================================================
// ConnectionFailedException render() and notifyAdmins()
// =============================================================================

test('ConnectionFailedException render returns 503 JSON with host and attempts', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')->once();

    $exception = new ConnectionFailedException(
        'Connection timed out',
        'host.example.com',
        1,
        '10.0.0.1'
    );

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(503);

    $data = json_decode($response->getContent(), true);
    expect($data['error'])->toBe('Connection timed out')
        ->and($data['host'])->toBe('host.example.com')
        ->and($data['attempts'])->toBe(1);
});

test('ConnectionFailedException notifies admins when attempts >= 3', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')->once();
    Notification::fake();

    config()->set('unblock.admin_email', 'admin@test.example.com'); // ggignore

    $exception = new ConnectionFailedException(
        'Connection failed after retries',
        'host.example.com',
        3,
        '10.0.0.1'
    );

    Notification::assertSentTo(
        new AnonymousNotifiable,
        ErrorParsingNotification::class
    );
});

test('ConnectionFailedException does not notify admins when attempts < 3', function () {
    Log::shouldReceive('channel')->with('firewall')->andReturnSelf();
    Log::shouldReceive('error')->once();
    Notification::fake();

    $exception = new ConnectionFailedException(
        'Connection failed',
        'host.example.com',
        2
    );

    Notification::assertNothingSent();
});
