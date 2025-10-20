<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvalidIpException extends Exception
{
    public function __construct(
        private readonly string $ipAddress,
        ?string $message = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $message ??= __('exceptions.invalid_ip.default');

        parent::__construct($message, $code, $previous);
    }

    public function report(): void
    {
        Log::channel('firewall')->error($this->getMessage(), [
            'ip' => $this->ipAddress,
            'exception' => static::class,
        ]);
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getValidationErrorDescription(): string
    {
        return __('exceptions.invalid_ip.message', ['ip' => $this->ipAddress]);
    }

    public function render()
    {
        return response()->json([
            'error' => __('exceptions.invalid_ip.message', ['ip' => $this->ipAddress]),
        ], 400);
    }
}
