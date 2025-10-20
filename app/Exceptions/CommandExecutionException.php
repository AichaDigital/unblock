<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommandExecutionException extends Exception
{
    /**
     * Create a new command execution exception
     *
     * @param  string  $command  The command that failed
     * @param  string  $output  Command output
     * @param  string  $errorOutput  Error output
     * @param  string|null  $message  The error message
     * @param  string|null  $hostName  The hostname where the error occurred
     * @param  string|null  $ipAddress  The IP address involved
     * @param  array  $context  Additional context data
     * @param  int  $code  The error code
     * @param  Throwable|null  $previous  The previous exception
     */
    public function __construct(
        private readonly string $command,
        private readonly ?string $output = null,
        private readonly ?string $errorOutput = null,
        string $message = 'Command execution failed',
        private readonly ?string $hostName = null,
        private readonly ?string $ipAddress = null,
        private readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get context data for reporting
     *
     * @return array<string, mixed>
     */
    private function getReportContext(): array
    {
        return array_merge([
            'host' => $this->hostName,
            'ip' => $this->ipAddress,
        ], $this->context);
    }

    /**
     * Report the exception with command details
     */
    public function report(): void
    {
        $context = $this->getReportContext();
        $context['command'] = $this->command;

        if ($this->output) {
            $context['output'] = $this->output;
        }

        if ($this->errorOutput) {
            $context['error_output'] = $this->errorOutput;
        }

        Log::channel('firewall')->error("Command execution failed: {$this->getMessage()}", $context);
    }

    /**
     * Get the command that failed
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get command output
     */
    public function getOutput(): ?string
    {
        return $this->output;
    }

    /**
     * Get error output
     */
    public function getErrorOutput(): ?string
    {
        return $this->errorOutput;
    }
}
