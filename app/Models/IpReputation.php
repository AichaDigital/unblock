<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * IP Reputation Model
 *
 * Tracks reputation score and request statistics for IP addresses
 * Used by reputation tracking system (v1.3.0+)
 *
 * @property int $id
 * @property string $ip
 * @property string $subnet
 * @property int $reputation_score
 * @property int $total_requests
 * @property int $failed_requests
 * @property int $blocked_count
 * @property Carbon|null $last_seen_at
 * @property string|null $notes
 * @property string|null $country_code
 * @property string|null $country_name
 * @property string|null $city
 * @property string|null $postal_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $timezone
 * @property string|null $continent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IpReputation extends Model
{
    protected $table = 'ip_reputation';

    /** @var list<string> */
    protected $fillable = [
        'ip',
        'subnet',
        'reputation_score',
        'total_requests',
        'failed_requests',
        'blocked_count',
        'last_seen_at',
        'notes',
        'country_code',
        'country_name',
        'city',
        'postal_code',
        'latitude',
        'longitude',
        'timezone',
        'continent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'reputation_score' => 'integer',
        'total_requests' => 'integer',
        'failed_requests' => 'integer',
        'blocked_count' => 'integer',
        'last_seen_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * @return HasMany<AbuseIncident, $this>
     */
    public function abuseIncidents()
    {
        return $this->hasMany(AbuseIncident::class, 'ip_address', 'ip');
    }

    /**
     * Calculate success rate percentage.
     *
     * @return Attribute<float, never>
     */
    protected function successRate(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->total_requests === 0
                ? 100.0
                : round((1 - ($this->failed_requests / $this->total_requests)) * 100, 2),
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
}
