<?php

namespace App\Exceptions;

use App\Notifications\Admin\ErrorParsingNotification;
use Exception;
use Illuminate\Support\Facades\{Log, Notification};
use Throwable;

class ConnectionFailedException extends Exception
{
    public function __construct(
        string $message,
        private readonly ?string $hostName = null,
        private readonly int $attempts = 1,
        private readonly ?string $ipAddress = null,
        private readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->logError();
        $this->notifyAdmins();
    }

    private function logError(): void
    {
        $channel = Log::channel('firewall');
        if ($channel) {
            $channel->error($this->getMessage(), $this->context);
        }
    }

    private function notifyAdmins(): void
    {
        if ($this->attempts >= 3) {
            $hostInfo = $this->hostName
                ? __('exceptions.connection.host_info', ['name' => $this->hostName])
                : 'un servidor';

            Notification::route('mail', config('unblock.admin_email'))
                ->notify(new ErrorParsingNotification(
                    __('exceptions.connection.message', [
                        'host' => $hostInfo,
                        'message' => $this->getMessage(),
                    ])
                ));
        }
    }

    public function render()
    {
        return response()->json([
            'error' => $this->getMessage(),
            'host' => $this->hostName,
            'attempts' => $this->attempts,
        ], 503);
    }

    public function getHostName(): ?string
    {
        return $this->hostName;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }
}
