<?php

namespace App\Services;

use App\Models\{Host, User};
use App\Traits\AuditLoginTrait;
use Illuminate\Support\Facades\Log;

class AuditService
{
    use AuditLoginTrait;

    /**
     * Log a firewall check operation
     */
    public function logFirewallCheck(User $user, Host $host, string $ipAddress, bool $wasBlocked): void
    {
        Log::info('Firewall check operation audited', [
            'user_id' => $user->id,
            'host_id' => $host->id,
            'host_fqdn' => $host->fqdn,
            'ip_address' => $ipAddress,
            'was_blocked' => $wasBlocked,
            'operation' => 'firewall_check',
        ]);
    }

    /**
     * Log a firewall check failure
     */
    public function logFirewallCheckFailure(User $user, Host $host, string $ipAddress, string $errorMessage): void
    {
        Log::error('Firewall check operation failed', [
            'user_id' => $user->id,
            'host_id' => $host->id,
            'host_fqdn' => $host->fqdn,
            'ip_address' => $ipAddress,
            'error_message' => $errorMessage,
            'operation' => 'firewall_check_failure',
        ]);
    }
}
