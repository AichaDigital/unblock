<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\{FirewallException, InvalidIpException};
use App\Jobs\{ProcessFirewallCheckJob, ProcessHqWhitelistJob};
use App\Models\{Host, User};
use App\Services\{
    FirewallUnblocker,
    ReportGenerator,
    SshConnectionManager
};
use App\Services\Firewall\{FirewallAnalyzerFactory};
use Exception;
use Illuminate\Support\Facades\{Log};
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Check Firewall Action - Orchestrator Pattern (SOLID Compliant)
 *
 * This action orchestrates the complete firewall check and unblock process:
 * 1. Validates input parameters
 * 2. Delegates SSH connection management to SshConnectionManager
 * 3. Uses FirewallAnalyzerFactory to get appropriate analyzer
 * 4. Performs analysis via the analyzer
 * 5. Optionally unblocks IPs via FirewallUnblocker
 * 6. Generates reports via ReportGenerator
 * 7. Dispatches notifications
 *
 * This follows the Single Responsibility Principle - it only orchestrates,
 * delegating each specific responsibility to dedicated services.
 */
class CheckFirewallAction
{
    use AsAction;

    /**
     * Handle the firewall check process by dispatching a job.
     */
    public function handle(string $ip, int $userId, int $hostId, ?int $copyUserId = null, ?string $develop = null): array
    {
        // Development mode check remains for backward compatibility.
        if ($develop !== null) {
            return [
                'success' => true,
                'message' => __('messages.firewall.develop_check_completed'),
                'data' => ['develop_command' => $develop],
            ];
        }

        try {
            // Validate and load models before dispatching the job
            $user = $this->loadUser($userId);
            $host = $this->loadHost($hostId);

            // Validate IP address
            $this->validateIpAddress($ip);

            // Validate user access permissions to host
            $this->validateUserAccess($user, $host);

            // Dispatch the job to the queue using named arguments for clarity
            ProcessFirewallCheckJob::dispatch(
                ip: $ip,
                userId: $userId,
                hostId: $hostId,
                copyUserId: $copyUserId
            );

            // ALWAYS dispatch HQ whitelist check in parallel (non-blocking)
            ProcessHqWhitelistJob::dispatch(
                ip: $ip,
                userId: $userId
            );

            Log::info('Firewall check job dispatched', [
                'ip_address' => $ip,
                'user_id' => $userId,
                'host_id' => $hostId,
            ]);

            // Return an immediate success response to the user
            return [
                'success' => true,
                'message' => __('messages.firewall.check_started'),
            ];
        } catch (Exception $e) {
            Log::error('Failed to dispatch firewall check job', [
                'ip_address' => $ip,
                'user_id' => $userId,
                'host_id' => $hostId,
                'error' => $e->getMessage(),
            ]);

            // Re-throw or return an error response
            throw new FirewallException(
                "Failed to start firewall check for IP {$ip}: ".$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Load and validate user
     */
    private function loadUser(int $userId): User
    {
        $user = User::find($userId);

        if (! $user) {
            throw new InvalidArgumentException("User with ID {$userId} not found");
        }

        return $user;
    }

    /**
     * Load and validate host
     */
    private function loadHost(int $hostId): Host
    {
        $host = Host::find($hostId);

        if (! $host) {
            throw new InvalidArgumentException("Host with ID {$hostId} not found");
        }

        return $host;
    }

    /**
     * Validate IP address format
     */
    private function validateIpAddress(string $ip): void
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidIpException("Invalid IP address format: {$ip}");
        }
    }

    /**
     * Validate user access permissions to host
     */
    private function validateUserAccess(User $user, Host $host): void
    {
        // Admin users have access to everything
        if ($user->is_admin) {
            return;
        }

        // Check if user has access to this host
        if (! $user->hasAccessToHost($host->id)) {
            throw new Exception("Access denied: User {$user->id} does not have permission to access host {$host->id}");
        }
    }

    /**
     * Configure the action for AsAction trait
     */
    public function asCommand(): void
    {
        // This method satisfies the AsAction trait requirement
    }

    /**
     * Static dispatcher method for backward compatibility
     */
    public static function dispatch(string $ip, int $userId, int $hostId): void
    {
        // This static dispatcher might need to be re-evaluated or adapted,
        // for now we make it dispatch the new job.
        ProcessFirewallCheckJob::dispatch(ip: $ip, userId: $userId, hostId: $hostId);
    }
}
