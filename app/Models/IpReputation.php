<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * IP Reputation Model
 *
 * Tracks reputation score and request statistics for IP addresses
 * Used by reputation tracking system (v1.3.0)
 *
 * @property int $id
 * @property string $ip
 * @property string $subnet
 * @property int $reputation_score
 * @property int $total_requests
 * @property int $failed_requests
 * @property int $blocked_count
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IpReputation extends Model
{
    protected $table = 'ip_reputation';

    protected $fillable = [
        'ip',
        'subnet',
        'reputation_score',
        'total_requests',
        'failed_requests',
        'blocked_count',
        'last_seen_at',
        'notes',
    ];

    protected $casts = [
        'reputation_score' => 'integer',
        'total_requests' => 'integer',
        'failed_requests' => 'integer',
        'blocked_count' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    /**
     * Get abuse incidents for this IP
     */
    public function abuseIncidents()
    {
        return $this->hasMany(AbuseIncident::class, 'ip_address', 'ip');
    }

    /**
     * Calculate success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_requests === 0) {
            return 100.0;
        }

        return round((1 - ($this->failed_requests / $this->total_requests)) * 100, 2);
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
}
