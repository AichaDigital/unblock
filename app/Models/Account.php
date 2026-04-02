<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property Carbon|null $suspended_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $last_synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
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
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->whereNull('suspended_at')
            ->whereNull('deleted_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSuspended($query)
    {
        return $query->whereNotNull('suspended_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeMarkedAsDeleted($query)
    {
        return $query->whereNotNull('deleted_at');
    }
}
