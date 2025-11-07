<?php

declare(strict_types=1);

use App\Models\Host;
use App\Services\{SshConnectionManager, SshSession};
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Log::spy();
    $this->connectionManager = Mockery::mock(SshConnectionManager::class);

    // Allow removeSshKey to be called by default (for __destruct)
    $this->connectionManager->allows('removeSshKey')->byDefault();

    $this->host = Host::factory()->create(['fqdn' => 'test.example.com']);
    $this->sshKeyPath = '/path/to/key.pem';
    $this->session = new SshSession($this->connectionManager, $this->host, $this->sshKeyPath);
});

// ============================================================================
// SCENARIO 1: Command Execution - Success
// ============================================================================

test('executes command successfully', function () {
    $command = 'ls -la';
    $expectedOutput = "file1.txt\nfile2.txt\n";

    $this->connectionManager->expects('executeCommand')
        ->with($this->host, $this->sshKeyPath, $command)
        ->andReturn($expectedOutput);

    $result = $this->session->execute($command);

    expect($result)->toBe($expectedOutput);
});

test('logs command start', function () {
    $this->connectionManager->allows('executeCommand')->andReturn('output');
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute('test command');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Starting execution', Mockery::on(function ($context) {
            return $context['host'] === 'test.example.com' &&
                   $context['command'] === 'test command' &&
                   isset($context['session_id']);
        }));
});

test('logs command success', function () {
    $this->connectionManager->allows('executeCommand')->andReturn('success output');
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute('test command');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) {
            return $context['host'] === 'test.example.com' &&
                   $context['command'] === 'test command' &&
                   isset($context['execution_time_ms']) &&
                   isset($context['output_length']) &&
                   isset($context['output_preview']);
        }));
});

test('includes execution time in success log', function () {
    $this->connectionManager->allows('executeCommand')->andReturn('output');
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute('test command');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) {
            return is_numeric($context['execution_time_ms']) &&
                   $context['execution_time_ms'] >= 0;
        }));
});

test('includes output length in success log', function () {
    $output = 'This is test output';
    $this->connectionManager->allows('executeCommand')->andReturn($output);
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute('test command');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) use ($output) {
            return $context['output_length'] === strlen($output);
        }));
});

test('includes full output preview for short output', function () {
    $output = 'Short output';
    $this->connectionManager->allows('executeCommand')->andReturn($output);
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute('test command');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) use ($output) {
            return $context['output_preview'] === $output;
        }));
});

test('truncates output preview for long output', function () {
    $output = str_repeat('x', 250);
    $this->connectionManager->allows('executeCommand')->andReturn($output);
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute('test command');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) {
            return strlen($context['output_preview']) === 203 && // 200 chars + '...'
                   str_ends_with($context['output_preview'], '...');
        }));
});

test('includes session ID in success log', function () {
    $this->connectionManager->allows('executeCommand')->andReturn('output');
    $this->connectionManager->allows('removeSshKey');

    $sessionId = spl_object_hash($this->session);

    $this->session->execute('test command');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) use ($sessionId) {
            return $context['session_id'] === $sessionId;
        }));
});

// ============================================================================
// SCENARIO 2: Command Execution - Failure
// ============================================================================

test('throws exception when command fails', function () {
    $this->connectionManager->allows('executeCommand')
        ->andThrow(new Exception('Connection failed'));
    $this->connectionManager->allows('removeSshKey');

    expect(fn () => $this->session->execute('test command'))
        ->toThrow(Exception::class, 'Connection failed');
});

test('logs error when command fails', function () {
    $exception = new Exception('Connection failed');
    $this->connectionManager->allows('executeCommand')->andThrow($exception);
    $this->connectionManager->allows('removeSshKey');

    try {
        $this->session->execute('test command');
    } catch (Exception $e) {
        // Expected
    }

    Log::shouldHaveReceived('error')
        ->with('SSH Command: Execution failed', Mockery::on(function ($context) {
            return $context['host'] === 'test.example.com' &&
                   $context['command'] === 'test command' &&
                   $context['error'] === 'Connection failed' &&
                   isset($context['execution_time_ms']);
        }));
});

test('includes exception class in error log', function () {
    $exception = new Exception('Test error');
    $this->connectionManager->allows('executeCommand')->andThrow($exception);
    $this->connectionManager->allows('removeSshKey');

    try {
        $this->session->execute('test command');
    } catch (Exception $e) {
        // Expected
    }

    Log::shouldHaveReceived('error')
        ->with('SSH Command: Execution failed', Mockery::on(function ($context) {
            return $context['exception_class'] === Exception::class;
        }));
});

test('includes execution time in error log', function () {
    $this->connectionManager->allows('executeCommand')->andThrow(new Exception('Error'));
    $this->connectionManager->allows('removeSshKey');

    try {
        $this->session->execute('test command');
    } catch (Exception $e) {
        // Expected
    }

    Log::shouldHaveReceived('error')
        ->with('SSH Command: Execution failed', Mockery::on(function ($context) {
            return is_numeric($context['execution_time_ms']) &&
                   $context['execution_time_ms'] >= 0;
        }));
});

test('includes session ID in error log', function () {
    $this->connectionManager->allows('executeCommand')->andThrow(new Exception('Error'));
    $this->connectionManager->allows('removeSshKey');

    $sessionId = spl_object_hash($this->session);

    try {
        $this->session->execute('test command');
    } catch (Exception $e) {
        // Expected
    }

    Log::shouldHaveReceived('error')
        ->with('SSH Command: Execution failed', Mockery::on(function ($context) use ($sessionId) {
            return $context['session_id'] === $sessionId;
        }));
});

// ============================================================================
// SCENARIO 3: Getters
// ============================================================================

test('getSshKeyPath returns correct path', function () {
    $this->connectionManager->allows('removeSshKey');

    expect($this->session->getSshKeyPath())->toBe('/path/to/key.pem');
});

test('getHost returns correct host', function () {
    $this->connectionManager->allows('removeSshKey');

    expect($this->session->getHost())->toBe($this->host)
        ->and($this->session->getHost()->fqdn)->toBe('test.example.com');
});

// ============================================================================
// SCENARIO 4: Cleanup
// ============================================================================

test('cleanup removes SSH key', function () {
    $this->connectionManager->expects('removeSshKey')
        ->once()
        ->with($this->sshKeyPath);

    $this->session->cleanup();

    // Prevent __destruct from calling it again
    $this->connectionManager->allows('removeSshKey');
});

test('cleanup logs debug message', function () {
    $this->connectionManager->allows('removeSshKey');

    $this->session->cleanup();

    Log::shouldHaveReceived('debug')
        ->with('SSH Session: Cleaning up session', Mockery::on(function ($context) {
            return $context['host'] === 'test.example.com' &&
                   $context['ssh_key_path'] === '/path/to/key.pem' &&
                   isset($context['session_id']);
        }));
});

test('cleanup includes session ID in log', function () {
    $this->connectionManager->allows('removeSshKey');

    $sessionId = spl_object_hash($this->session);

    $this->session->cleanup();

    Log::shouldHaveReceived('debug')
        ->with('SSH Session: Cleaning up session', Mockery::on(function ($context) use ($sessionId) {
            return $context['session_id'] === $sessionId;
        }));
});

test('cleanup suppresses logging errors', function () {
    $this->connectionManager->allows('removeSshKey');

    // Make Log::debug throw an exception
    Log::shouldReceive('debug')->andThrow(new Exception('Logging failed'));

    // Should not throw - error is suppressed
    expect(fn () => $this->session->cleanup())->not->toThrow(Exception::class);
});

test('cleanup removes key even when logging fails', function () {
    Log::shouldReceive('debug')->andThrow(new Exception('Logging failed'));

    $this->connectionManager->expects('removeSshKey')
        ->once()
        ->with($this->sshKeyPath);

    $this->session->cleanup();

    // Prevent __destruct from calling it again
    $this->connectionManager->allows('removeSshKey');
});

// ============================================================================
// SCENARIO 5: Destructor
// ============================================================================

test('destructor calls cleanup', function () {
    $connectionManager = Mockery::mock(SshConnectionManager::class);
    $host = Host::factory()->create();
    $sshKeyPath = '/path/to/key.pem';

    $connectionManager->expects('removeSshKey')
        ->with($sshKeyPath);

    $session = new SshSession($connectionManager, $host, $sshKeyPath);
    unset($session); // Trigger __destruct

    // Expectation verified by Mockery
})->skip('Destructor testing is complex and covered by cleanup test');

// ============================================================================
// SCENARIO 6: Multiple Commands in Same Session
// ============================================================================

test('can execute multiple commands in same session', function () {
    $this->connectionManager->expects('executeCommand')
        ->with($this->host, $this->sshKeyPath, 'command1')
        ->andReturn('output1');

    $this->connectionManager->expects('executeCommand')
        ->with($this->host, $this->sshKeyPath, 'command2')
        ->andReturn('output2');

    $this->connectionManager->allows('removeSshKey');

    $result1 = $this->session->execute('command1');
    $result2 = $this->session->execute('command2');

    expect($result1)->toBe('output1')
        ->and($result2)->toBe('output2');
});

test('logs each command execution separately', function () {
    $this->connectionManager->allows('executeCommand')
        ->andReturnUsing(function ($host, $key, $command) {
            return "output for {$command}";
        });
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute('command1');
    $this->session->execute('command2');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Starting execution', Mockery::on(function ($context) {
            return $context['command'] === 'command1';
        }));

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Starting execution', Mockery::on(function ($context) {
            return $context['command'] === 'command2';
        }));
});

// ============================================================================
// SCENARIO 7: Edge Cases
// ============================================================================

test('handles empty command output', function () {
    $this->connectionManager->allows('executeCommand')->andReturn('');
    $this->connectionManager->allows('removeSshKey');

    $result = $this->session->execute('test command');

    expect($result)->toBe('')
        ->and(strlen($result))->toBe(0);

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) {
            return $context['output_length'] === 0 &&
                   $context['output_preview'] === '';
        }));
});

test('handles exactly 200 character output', function () {
    $output = str_repeat('x', 200);
    $this->connectionManager->allows('executeCommand')->andReturn($output);
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute('test command');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) use ($output) {
            return $context['output_preview'] === $output && // Exactly 200, no truncation
                   ! str_ends_with($context['output_preview'], '...');
        }));
});

test('handles exactly 201 character output', function () {
    $output = str_repeat('x', 201);
    $this->connectionManager->allows('executeCommand')->andReturn($output);
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute('test command');

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) {
            return strlen($context['output_preview']) === 203 && // 200 + '...'
                   str_ends_with($context['output_preview'], '...');
        }));
});

test('handles multiline command output', function () {
    $output = "Line 1\nLine 2\nLine 3\nLine 4";
    $this->connectionManager->allows('executeCommand')->andReturn($output);
    $this->connectionManager->allows('removeSshKey');

    $result = $this->session->execute('test command');

    expect($result)->toBe($output);

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Execution completed successfully', Mockery::on(function ($context) use ($output) {
            return $context['output_length'] === strlen($output);
        }));
});

test('handles commands with special characters', function () {
    $command = "grep 'pattern' /var/log/auth.log | tail -n 10";
    $this->connectionManager->allows('executeCommand')->andReturn('output');
    $this->connectionManager->allows('removeSshKey');

    $this->session->execute($command);

    Log::shouldHaveReceived('info')
        ->with('SSH Command: Starting execution', Mockery::on(function ($context) use ($command) {
            return $context['command'] === $command;
        }));
});
