<?php

declare(strict_types=1);

namespace App\Actions\Firewall;

use App\Models\{Host, User};
use Exception;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Validate User Access To Host Action
 *
 * Validates that a user has permission to access a specific host.
 * Admins have access to all hosts, regular users must have explicit access.
 */
class ValidateUserAccessToHostAction
{
    use AsAction;

    /**
     * Validate user access to host
     *
     * @throws Exception if user doesn't have access
     */
    public function handle(User $user, Host $host): void
    {
        // Admins have access to all hosts
        if ($user->is_admin) {
            Log::debug('User has admin access to all hosts', [
                'user_id' => $user->id,
                'host_id' => $host->id,
            ]);

            return;
        }

        // Check if user has explicit access to this host
        if (! $user->hasAccessToHost($host->id)) {
            Log::warning('User access denied to host', [
                'user_id' => $user->id,
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
            ]);

            throw new Exception(
                "Access denied: User {$user->id} does not have permission for host {$host->id}"
            );
        }

        Log::debug('User has access to host', [
            'user_id' => $user->id,
            'host_id' => $host->id,
        ]);
    }
}
