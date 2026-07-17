<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HostingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class Hosting extends Model
{
    /** @use HasFactory<HostingFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'host_id',
        'domain',
        'username',
        'hosting_manual',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'hosting_manual' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    /**
     * @return HasMany<UserHostingPermission, $this>
     */
    public function hostingPermissions(): HasMany
    {
        return $this->hasMany(UserHostingPermission::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_hosting_permissions')
            ->withPivot(['is_active'])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }
}
