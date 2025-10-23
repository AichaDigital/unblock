<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class EmailReputation extends Model
{
    protected $table = 'email_reputation';

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

    protected $casts = [
        'reputation_score' => 'integer',
        'total_requests' => 'integer',
        'failed_requests' => 'integer',
        'verified_requests' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Get abuse incidents for this email
     */
    public function abuseIncidents()
    {
        return $this->hasMany(AbuseIncident::class, 'email_hash', 'email_hash');
    }

    /**
     * Calculate verification success rate percentage
     */
    public function getVerificationRateAttribute(): float
    {
        if ($this->total_requests === 0) {
            return 0.0;
        }

        return round(($this->verified_requests / $this->total_requests) * 100, 2);
    }

    /**
     * Calculate failure rate percentage
     */
    public function getFailureRateAttribute(): float
    {
        if ($this->total_requests === 0) {
            return 0.0;
        }

        return round(($this->failed_requests / $this->total_requests) * 100, 2);
    }

    /**
     * Get reputation badge color for Filament
     */
    public function getReputationColorAttribute(): string
    {
        return match (true) {
            $this->reputation_score >= 80 => 'success',
            $this->reputation_score >= 50 => 'warning',
            default => 'danger',
        };
    }

    /**
     * Get truncated email hash for display (first 16 chars)
     */
    public function getTruncatedHashAttribute(): string
    {
        return substr($this->email_hash, 0, 16).'...';
    }
}
