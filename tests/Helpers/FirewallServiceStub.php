<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Host;
use App\Services\FirewallService;

/**
 * Helper class to create FirewallService instances that return stub data
 * instead of executing real SSH commands.
 *
 * This allows tests to use real FirewallService logic while avoiding
 * actual SSH connections.
 *
 * Uses PHP 8.4 features:
 * - Typed properties with array shape hints
 * - Fluent interface with return type self
 * - Static factory methods
 * - Immutable pattern with new instances
 */
final class FirewallServiceStub extends FirewallService
{
    /**
     * Command responses indexed by command type
     *
     * @var array<string, string>
     */
    private readonly array $stubData;

    /**
     * @param  array<string, string>  $stubData  Command responses indexed by command type
     */
    private function __construct(array $stubData = [])
    {
        $this->stubData = $stubData;
    }

    /**
     * Create a stub from a stub file
     *
     * @param  non-empty-string  $stubPath  Path to stub file relative to base_path
     */
    public static function fromStubFile(string $stubPath): self
    {
        $stub = require base_path($stubPath);

        return new self($stub);
    }

    /**
     * Set stub data directly
     *
     * @param  array<string, string>  $data  Command responses
     */
    public function setStubData(array $data): self
    {
        return new self([...$this->stubData, ...$data]);
    }

    /**
     * Set specific command response
     *
     * @param  non-empty-string  $command  Command type (e.g., 'csf_deny_check')
     * @param  string  $response  Response to return for this command
     */
    public function setCommandResponse(string $command, string $response): self
    {
        return new self([...$this->stubData, $command => $response]);
    }

    /**
     * Override checkProblems to return stub data instead of executing SSH
     *
     * @param  non-empty-string  $command  Command type
     * @param  non-empty-string  $ip  IP address
     */
    public function checkProblems(Host $host, string $keyPath, string $command, string $ip): string
    {
        return $this->stubData[$command] ?? '';
    }

    /**
     * Create a stub that returns empty for deny checks (IP not blocked)
     */
    public static function ipNotBlocked(): self
    {
        return (new self([]))
            ->setCommandResponse('csf_deny_check', '')
            ->setCommandResponse('csf_tempip_check', '');
    }

    /**
     * Create a stub that returns IP in permanent deny
     *
     * @param  non-empty-string  $ip  IP address found in deny list
     */
    public static function ipInPermanentDeny(string $ip): self
    {
        return (new self([]))
            ->setCommandResponse('csf_deny_check', $ip)
            ->setCommandResponse('csf_tempip_check', '')
            ->setCommandResponse('unblock_permanent', 'IP removed from permanent deny');
    }

    /**
     * Create a stub that returns IP in temporary deny
     *
     * @param  non-empty-string  $ip  IP address found in temporary deny list
     */
    public static function ipInTemporaryDeny(string $ip): self
    {
        return (new self([]))
            ->setCommandResponse('csf_deny_check', '')
            ->setCommandResponse('csf_tempip_check', $ip)
            ->setCommandResponse('unblock_temporary', 'IP removed from temporary deny');
    }

    /**
     * Create a stub that returns IP in both deny lists
     *
     * @param  non-empty-string  $ip  IP address found in both deny lists
     */
    public static function ipInBothDenies(string $ip): self
    {
        return (new self([]))
            ->setCommandResponse('csf_deny_check', $ip)
            ->setCommandResponse('csf_tempip_check', $ip)
            ->setCommandResponse('unblock_permanent', 'IP removed from permanent deny')
            ->setCommandResponse('unblock_temporary', 'IP removed from temporary deny');
    }

    /**
     * Create a stub that throws exception for specific command
     * Useful for testing error handling
     *
     * @param  non-empty-string  $command  Command that should throw exception
     * @param  \Throwable  $exception  Exception to throw
     */
    public function withExceptionFor(string $command, \Throwable $exception): FirewallServiceWithException
    {
        return new FirewallServiceWithException($this, $command, $exception);
    }
}

/**
 * Wrapper for FirewallServiceStub that throws exceptions for specific commands
 * Uses composition instead of inheritance (since FirewallServiceStub is final)
 */
final class FirewallServiceWithException extends FirewallService
{
    /**
     * @param  non-empty-string  $exceptionCommand  Command that should throw exception
     */
    public function __construct(
        private readonly FirewallServiceStub $base,
        private readonly string $exceptionCommand,
        private readonly \Throwable $exception
    ) {}

    public function checkProblems($host, $keyPath, $command, $ip): string
    {
        if ($command === $this->exceptionCommand) {
            throw $this->exception;
        }

        return $this->base->checkProblems($host, $keyPath, $command, $ip);
    }
}
