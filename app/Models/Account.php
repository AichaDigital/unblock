<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Account Model
 *
 * Represents a hosting account in a remote server (cPanel/DirectAdmin).
 * Acts as a local cache/mirror of server data for fast validation without SSH.
 *
 * Note: Does NOT use SoftDeletes trait. The deleted_at column indicates
 * when the account was deleted from the remote server, not Laravel soft deletes.
 *
 * @property int $id
 * @property int $host_id
 * @property int|null $user_id
 * @property string $username
 * @property string $domain
 * @property string|null $owner
 * @property \Carbon\Carbon|null $suspended_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon|null $last_synced_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Account extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'host_id',
        'user_id',
        'username',
        'domain',
        'owner',
        'suspended_at',
        'deleted_at',
        'last_synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'suspended_at' => 'datetime',
        'deleted_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the host that owns the account.
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    /**
     * Get the user that owns the account (nullable, WHMCS integration).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the domains for the account.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Scope a query to only include active accounts.
     * Active means not suspended and not deleted.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('suspended_at')
            ->whereNull('deleted_at');
    }

    /**
     * Scope a query to only include suspended accounts.
     */
    public function scopeSuspended($query)
    {
        return $query->whereNotNull('suspended_at');
    }

    /**
     * Scope a query to only include accounts marked as deleted from the remote server.
     * Named "markedAsDeleted" to avoid conflict with Laravel's deleted() event method.
     */
    public function scopeMarkedAsDeleted($query)
    {
        return $query->whereNotNull('deleted_at');
    }
}
