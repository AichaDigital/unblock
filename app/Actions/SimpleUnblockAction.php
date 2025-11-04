<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\SimpleUnblock\SimpleUnblockRequestProcessed;
use App\Jobs\{ProcessSimpleUnblockJob, SendSimpleUnblockNotificationJob};
use App\Models\Domain;
use Exception;
use Illuminate\Support\Facades\{Log, RateLimiter};
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

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
     * Handle the simple unblock request (Phase 3 - Optimized with local DB)
     *
     * Uses local accounts/domains cache to instantly identify the target host,
     * eliminating the need for SSH loops across all servers.
     */
    public function handle(string $ip, string $domain, string $email): void
    {
        // Do not run if simple mode is disabled in config
        if (! config('unblock.simple_mode.enabled', false)) {
            Log::warning('SimpleUnblockAction called while simple mode is disabled.');

            return;
        }

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

        // Find domain in local database with eager loading
        /** @var \App\Models\Domain|null $domainRecord */
        $domainRecord = Domain::with('account.host')
            ->where('domain_name', $normalizedDomain)
            ->first();

        if (! $domainRecord) {
            Log::warning('Simple unblock: Domain not found in local database', [
                'ip' => $ip,
                'domain' => $normalizedDomain,
                'email_hash' => $emailHash,
            ]);

            // Notify admin about domain not found
            $this->notifyAdminSilentAttempt($ip, $normalizedDomain, $email, 'domain_not_found');

            return;
        }

        // Get account and host from relationships
        /** @var \App\Models\Account $account */
        $account = $domainRecord->account;

        if (! $account) {
            Log::error('Simple unblock: Domain found but account is NULL', [
                'ip' => $ip,
                'domain' => $normalizedDomain,
                'domain_id' => $domainRecord->id,
                'email_hash' => $emailHash,
            ]);

            $this->notifyAdminSilentAttempt($ip, $normalizedDomain, $email, 'account_null');

            return;
        }

        /** @var \App\Models\Host $host */
        $host = $account->host;

        if (! $host) {
            Log::error('Simple unblock: Account found but host is NULL', [
                'ip' => $ip,
                'domain' => $normalizedDomain,
                'account_id' => $account->id,
                'email_hash' => $emailHash,
            ]);

            $this->notifyAdminSilentAttempt($ip, $normalizedDomain, $email, 'host_null');

            return;
        }

        // Dispatch unblock job for the specific host
        Log::info('Dispatching SimpleUnblock job to queue', [
            'ip' => $ip,
            'domain' => $normalizedDomain,
            'host_id' => $host->id,
            'host_fqdn' => $host->fqdn,
        ]);

        ProcessSimpleUnblockJob::dispatch(
            ip: $ip,
            domain: $normalizedDomain,
            email: $email,
            hostId: $host->id
        );

        // Log activity (GDPR compliant - email hashed)
        activity()
            ->withProperties([
                'ip' => $ip,
                'domain' => $normalizedDomain,
                'email_hash' => $emailHash,
                'email_domain' => $emailDomain,
                'host_id' => $host->id,
                'host_fqdn' => $host->fqdn,
                'account_id' => $account->id,
                'request_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('simple_unblock_request');

        // Dispatch event for reputation tracking (v1.3.0)
        SimpleUnblockRequestProcessed::dispatch($ip, $normalizedDomain, $email, true);
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

            throw new RuntimeException(__('simple_unblock.rate_limit_exceeded', ['seconds' => $seconds]));
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

            throw new RuntimeException(__('simple_unblock.rate_limit_exceeded', ['seconds' => $seconds]));
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
            throw new InvalidArgumentException('Domain normalization failed');
        }

        // Validate format
        if (! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $normalized)) {
            throw new InvalidArgumentException('Invalid domain format');
        }

        return $normalized;
    }

    /**
     * Notify admin about silent attempt (no user notification)
     */
    private function notifyAdminSilentAttempt(string $ip, string $domain, string $email, string $reason): void
    {
        try {
            SendSimpleUnblockNotificationJob::dispatch(
                reportId: null,
                email: $email,
                domain: $domain,
                adminOnly: true,
                reason: $reason
            );
        } catch (Exception $e) {
            Log::error('Failed to send admin notification for silent attempt', [
                'ip' => $ip,
                'domain' => $domain,
                'email' => $email,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
