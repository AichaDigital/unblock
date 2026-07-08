<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Email Reputation Model
 *
 * Tracks reputation score and OTP statistics for email addresses
 * GDPR compliant: stores SHA-256 hash, not plaintext email
 * Used by reputation tracking system (v1.3.0)
 *
 * @property int $id
 * @property string $email_hash
 * @property string $email_domain
 * @property int $reputation_score
 * @property int $total_requests
 * @property int $failed_requests
 * @property int $verified_requests
 * @property Carbon|null $last_seen_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmailReputation extends Model
{
    protected $table = 'email_reputation';

    /** @var list<string> */
    protected $fillable = [
        'email_hash',
        'email_domain',
        'reputation_score',
        'total_requests',
        'failed_requests',
        'verified_requests',
        'last_seen_at',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'reputation_score' => 'integer',
        'total_requests' => 'integer',
        'failed_requests' => 'integer',
        'verified_requests' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    /**
     * @return HasMany<AbuseIncident, $this>
     */
    public function abuseIncidents()
    {
        return $this->hasMany(AbuseIncident::class, 'email_hash', 'email_hash');
    }

    /**
     * Calculate verification success rate percentage.
     *
     * @return Attribute<float, never>
     */
    protected function verificationRate(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->total_requests === 0
                ? 0.0
                : round(($this->verified_requests / $this->total_requests) * 100, 2),
        );
    }

    /**
     * Calculate failure rate percentage.
     *
     * @return Attribute<float, never>
     */
    protected function failureRate(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->total_requests === 0
                ? 0.0
                : round(($this->failed_requests / $this->total_requests) * 100, 2),
        );
    }

    /**
     * Get reputation badge color for Filament.
     *
     * @return Attribute<string, never>
     */
    protected function reputationColor(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match (true) {
                $this->reputation_score >= 80 => 'success',
                $this->reputation_score >= 50 => 'warning',
                default => 'danger',
            },
        );
    }

    /**
     * Get truncated email hash for display (first 16 chars).
     *
     * @return Attribute<string, never>
     */
    protected function truncatedHash(): Attribute
    {
        return Attribute::make(
            get: fn (): string => substr($this->email_hash, 0, 16).'...',
        );
    }
}
