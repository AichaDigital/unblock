<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\ProcessSimpleUnblockJob;
use App\Models\Host;
use Illuminate\Support\Facades\{Log, RateLimiter};
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Simple Unblock Action (v1.2.0)
 *
 * Orchestrator for anonymous IP unblock requests.
 * Implements multi-vector rate limiting and privacy-compliant logging.
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

        // Rate limiting: Email vector (5 req/hour)
        $this->checkEmailRateLimit($email);

        // Rate limiting: Domain vector (10 req/hour)
        $this->checkDomainRateLimit($normalizedDomain);

        $emailDomain = $this->getEmailDomain($email);
        $emailHash = hash('sha256', $email);

        Log::info('Simple unblock request received', [
            'ip' => $ip,
            'domain' => $normalizedDomain,
            'email_hash' => $emailHash,
            'email_domain' => $emailDomain,
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

        // Log activity (GDPR compliant - email hashed)
        activity()
            ->withProperties([
                'ip' => $ip,
                'domain' => $normalizedDomain,
                'email_hash' => $emailHash,
                'email_domain' => $emailDomain,
                'hosts_count' => $hosts->count(),
                'request_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('simple_unblock_request');
    }

    /**
     * Check rate limit for email (5 req/hour)
     */
    private function checkEmailRateLimit(string $email): void
    {
        $emailHash = hash('sha256', $email);
        $key = "simple_unblock:email:{$emailHash}";
        $maxAttempts = config('unblock.simple_mode.throttle_email_per_hour', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            activity()
                ->withProperties([
                    'email_hash' => $emailHash,
                    'email_domain' => $this->getEmailDomain($email),
                    'vector' => 'Email',
                    'retry_after' => $seconds,
                ])
                ->log('simple_unblock_rate_limit_exceeded');

            Log::warning('Simple unblock rate limit exceeded [Email]', [
                'email_hash' => $emailHash,
                'max_attempts' => $maxAttempts,
                'retry_after' => $seconds,
            ]);

            throw new \RuntimeException(__('simple_unblock.rate_limit_exceeded', ['seconds' => $seconds]));
        }

        RateLimiter::hit($key, 3600); // 1 hour
    }

    /**
     * Check rate limit for domain (10 req/hour)
     */
    private function checkDomainRateLimit(string $domain): void
    {
        $key = "simple_unblock:domain:{$domain}";
        $maxAttempts = config('unblock.simple_mode.throttle_domain_per_hour', 10);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            activity()
                ->withProperties([
                    'domain' => $domain,
                    'vector' => 'Domain',
                    'retry_after' => $seconds,
                ])
                ->log('simple_unblock_rate_limit_exceeded');

            Log::warning('Simple unblock rate limit exceeded [Domain]', [
                'domain' => $domain,
                'max_attempts' => $maxAttempts,
                'retry_after' => $seconds,
            ]);

            throw new \RuntimeException(__('simple_unblock.rate_limit_exceeded', ['seconds' => $seconds]));
        }

        RateLimiter::hit($key, 3600); // 1 hour
    }

    /**
     * Extract domain from email address
     */
    private function getEmailDomain(string $email): string
    {
        $parts = explode('@', $email);

        return $parts[1] ?? 'unknown';
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
