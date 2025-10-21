<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\ProcessSimpleUnblockJob;
use App\Models\Host;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Simple Unblock Action
 *
 * Orchestrator for anonymous IP unblock requests.
 * Dispatches jobs for each host to find and unblock the IP.
 */
class SimpleUnblockAction
{
    use AsAction;

    /**
     * Handle the simple unblock request
     */
    public function handle(string $ip, string $domain, string $email): void
    {
        // Normalize domain
        $normalizedDomain = $this->normalizeDomain($domain);

        Log::info('Simple unblock request received', [
            'ip' => $ip,
            'domain' => $normalizedDomain,
            'email' => $email,
            'user_agent' => request()->userAgent(),
            'request_ip' => request()->ip(),
        ]);

        // Get all hosts
        $hosts = Host::all();

        if ($hosts->isEmpty()) {
            Log::warning('No hosts available for simple unblock');

            return;
        }

        // Dispatch job for EACH host (will stop at first match)
        foreach ($hosts as $host) {
            ProcessSimpleUnblockJob::dispatch(
                ip: $ip,
                domain: $normalizedDomain,
                email: $email,
                hostId: $host->id
            );
        }

        // Log activity
        activity()
            ->withProperties([
                'ip' => $ip,
                'domain' => $normalizedDomain,
                'email' => $email,
                'hosts_count' => $hosts->count(),
                'request_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('simple_unblock_request');
    }

    /**
     * Normalize domain (lowercase, remove www)
     */
    private function normalizeDomain(string $domain): string
    {
        // Convert to lowercase
        $domain = strtolower(trim($domain));

        // Remove www. prefix
        $normalized = preg_replace('/^www\./i', '', $domain);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Domain normalization failed');
        }

        // Validate format
        if (! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $normalized)) {
            throw new \InvalidArgumentException('Invalid domain format');
        }

        return $normalized;
    }
}
