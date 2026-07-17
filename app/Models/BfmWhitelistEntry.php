<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\BfmWhitelistEntryFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DirectAdmin BFM Whitelist Entry
 *
 * Tracks IPs added to DirectAdmin BFM whitelist for automatic removal
 * after the configured TTL period
 *
 * @property int $id
 * @property int $host_id
 * @property string $ip_address
 * @property Carbon $added_at
 * @property Carbon $expires_at
 * @property bool $removed
 * @property Carbon|null $removed_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Host $host
 */
class BfmWhitelistEntry extends Model
{
    /** @use HasFactory<BfmWhitelistEntryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'host_id',
        'ip_address',
        'added_at',
        'expires_at',
        'removed',
        'removed_at',
        'notes',
    ];

    /** @var array<string, bool> */
    protected $attributes = [
        'removed' => false,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'added_at' => 'datetime',
        'expires_at' => 'datetime',
        'removed' => 'boolean',
        'removed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    /**
     * Check if this entry has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if this entry is still active (not removed and not expired)
     */
    public function isActive(): bool
    {
        return ! $this->removed && ! $this->isExpired();
    }

    /**
     * Mark this entry as removed
     */
    public function markAsRemoved(): void
    {
        $this->update([
            'removed' => true,
            'removed_at' => now(),
        ]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeActive($query)
    {
        return $query->where('removed', false)
            ->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeExpired($query)
    {
        return $query->where('removed', false)
            ->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeForHost($query, int $hostId)
    {
        return $query->where('host_id', $hostId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeForIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }
}
