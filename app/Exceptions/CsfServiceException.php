<?php

namespace App\Exceptions;

use App\Notifications\Admin\ErrorParsingNotification;
use Exception;
use Illuminate\Support\Facades\{Log, Notification};
use Throwable;

class CsfServiceException extends Exception
{
    private readonly array $mergedContext;

    public function __construct(
        private readonly string $operation,
        ?string $message = null,
        private readonly ?string $hostName = null,
        private readonly ?string $ipAddress = null,
        private readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->mergedContext = array_merge([
            'operation' => $this->operation,
            'host' => $this->hostName,
            'ip' => $this->ipAddress,
        ], $context);

        $message ??= __('exceptions.csf.default');

        parent::__construct($message, $code, $previous);

        $this->logError();
        $this->notifyAdmins();
    }

    private function logError(): void
    {
        Log::channel('firewall')->error($this->getMessage(), $this->mergedContext);
    }

    private function notifyAdmins(): void
    {
        $ipInfo = $this->ipAddress
            ? __('exceptions.csf.ip_info', ['ip' => $this->ipAddress])
            : '';

        Notification::route('mail', config('unblock.admin_email'))
            ->notify(new ErrorParsingNotification(
                __('exceptions.csf.message', [
                    'host' => $this->hostName,
                    'ip' => $ipInfo,
                    'operation' => $this->operation,
                    'message' => $this->getMessage(),
                ])
            ));
    }

    public function render()
    {
        return response()->json([
            'error' => $this->getMessage(),
            'operation' => $this->operation,
            'host' => $this->hostName,
            'ip' => $this->ipAddress,
        ], 500);
    }

    public function getOperationType(): string
    {
        return $this->operation;
    }

    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    protected function isCriticalFailure(): bool
    {
        $criticalHosts = config('unblock.critical_hosts', []);

        return in_array($this->hostName, $criticalHosts);
    }
}
