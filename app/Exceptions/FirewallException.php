<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirewallException extends Exception
{
    /**
     * The host where the error occurred
     */
    protected ?string $hostName = null;

    /**
     * The IP address involved in the error
     */
    protected ?string $ipAddress = null;

    /**
     * Additional context data for the error
     */
    protected array $context = [];

    /**
     * Create a new firewall exception
     *
     * @param  string  $message  The error message
     * @param  string|null  $hostName  The server hostname where the error occurred
     * @param  string|null  $ipAddress  The IP address involved in the error
     * @param  array  $context  Additional context data
     * @param  int  $code  The error code
     * @param  Throwable|null  $previous  The previous exception
     */
    public function __construct(
        string $message,
        ?string $hostName = null,
        ?string $ipAddress = null,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->hostName = $hostName;
        $this->ipAddress = $ipAddress;
        $this->context = $context;
    }

    /**
     * Report the exception
     */
    public function report(): void
    {
        $context = $this->getReportContext();
        Log::channel('firewall')->error($this->getMessage(), $context);
    }

    /**
     * Get the host name where the error occurred
     */
    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    /**
     * Get the IP address involved in the error
     */
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * Get the context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get context data for reporting
     */
    protected function getReportContext(): array
    {
        $context = $this->context;

        if ($this->hostName) {
            $context['host'] = $this->hostName;
        }

        if ($this->ipAddress) {
            $context['ip_address'] = $this->ipAddress;
        }

        $context['exception'] = get_class($this);

        return $context;
    }
}
