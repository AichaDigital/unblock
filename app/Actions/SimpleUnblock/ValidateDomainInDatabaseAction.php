<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use App\Models\Domain;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Validate Domain In Database Action
 *
 * Validates that a domain exists in the database for a specific host
 * and that the associated account is active (not suspended/deleted).
 *
 * This is a critical security validation to prevent abuse attempts.
 */
class ValidateDomainInDatabaseAction
{
    use AsAction;

    /**
     * Validate domain existence and status in database
     */
    public function handle(string $domain, int $hostId): DomainValidationResult
    {
        Log::info('Validating domain in database', [
            'domain' => $domain,
            'host_id' => $hostId,
        ]);

        // Search for domain in database with eager loading
        $domainRecord = Domain::forDomain($domain)
            ->with('account')
            ->whereHas('account', function ($query) use ($hostId) {
                $query->where('host_id', $hostId);
            })
            ->first();

        // Domain not found
        if (! $domainRecord) {
            Log::info('Domain validation failed: not found in database', [
                'domain' => $domain,
                'host_id' => $hostId,
            ]);

            return DomainValidationResult::failure('domain_not_found');
        }

        // Check if account is suspended
        if ($domainRecord->account->suspended_at) {
            Log::info('Domain validation failed: account suspended', [
                'domain' => $domain,
                'account_id' => $domainRecord->account_id,
                'suspended_at' => $domainRecord->account->suspended_at,
            ]);

            return DomainValidationResult::failure('account_suspended');
        }

        // Check if account is deleted
        if ($domainRecord->account->deleted_at) {
            Log::info('Domain validation failed: account deleted', [
                'domain' => $domain,
                'account_id' => $domainRecord->account_id,
                'deleted_at' => $domainRecord->account->deleted_at,
            ]);

            return DomainValidationResult::failure('account_deleted');
        }

        // All validations passed
        Log::info('Domain validation passed', [
            'domain' => $domain,
            'domain_id' => $domainRecord->id,
            'account_id' => $domainRecord->account_id,
            'host_id' => $hostId,
        ]);

        return DomainValidationResult::success($domainRecord);
    }
}
