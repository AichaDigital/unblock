<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasManyThrough};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasOneTimePasswords, LogsActivity, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'company_name',
        'email',
        'password',
        'password_whmcs',
        'is_admin',
        'parent_user_id',
        'whmcs_client_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_whmcs',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'whmcs_client_id' => 'integer',
    ];

    /**
     * The "booted" method of the model.
     *
     * When a parent user is deleted, their authorized users must also be deleted
     * as they lose access rights to the system.
     *
     * When a parent user is restored, their authorized users are also restored
     * thus recovering their access rights.
     */
    protected static function booted(): void
    {
        // When a parent user is deleted, delete their authorized users
        static::deleted(function (User $user) {
            if (! $user->parent_user_id) {
                // If it's a parent user, delete all their authorized users
                $user->authorizedUsers()->each(function (User $authorizedUser) {
                    // Delete the authorized user's hostings
                    $authorizedUser->hostings()->each(function (Hosting $hosting) {
                        $hosting->delete();  // Soft delete
                    });
                    $authorizedUser->delete();  // Soft delete
                });
            }
        });

        // When a parent user is restored, restore their authorized users
        static::restored(function (User $user) {
            if (! $user->parent_user_id) {
                $user->authorizedUsers()->onlyTrashed()->each(function (User $authorizedUser) {
                    // Restore the authorized user's hostings
                    $authorizedUser->hostings()->onlyTrashed()->each(function (Hosting $hosting) {
                        $hosting->restore();
                    });
                    $authorizedUser->restore();
                });
            }
        });
    }

    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /**
     * Get the activity log options for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email', 'company_name', 'is_admin', 'is_active', 'parent_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => 'User created',
                'updated' => 'User updated',
                'deleted' => 'User deleted',
                'restored' => 'User restored',
                default => "User {$eventName}"
            });
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function getNameAttribute(): string
    {
        return $this->getFullNameAttribute();
    }

    // Relationship with parent user (for delegated users)
    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    // Relationship with authorized users
    public function authorizedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }

    // Relationship with hostings
    public function hostings(): HasMany
    {
        return $this->hasMany(Hosting::class);
    }

    // Relationship with hosts through hostings
    public function hosts(): BelongsToMany
    {
        return $this->belongsToMany(Host::class, 'user_host_permissions')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    // Relationship with authorized active hosts (more specific for testing and business logic)
    public function authorizedHosts(): BelongsToMany
    {
        return $this->belongsToMany(Host::class, 'user_host_permissions')
            ->withPivot('is_active')
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    // New hybrid permission system relations
    public function hostingPermissions(): HasMany
    {
        return $this->hasMany(UserHostingPermission::class);
    }

    // Relation to show hosting permissions assigned to authorized users by this parent user
    public function authorizedUserHostingPermissions(): HasManyThrough
    {
        return $this->hasManyThrough(
            UserHostingPermission::class,
            User::class,
            'parent_user_id', // Foreign key on User table (authorized users)
            'user_id',        // Foreign key on UserHostingPermission table
            'id',             // Local key on this User table (parent)
            'id'              // Local key on User table (authorized user)
        );
    }

    public function authorizedHostings(): BelongsToMany
    {
        return $this->belongsToMany(Hosting::class, 'user_hosting_permissions')
            ->withPivot(['is_active'])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    /**
     * Check if user has access to a specific host (hybrid permission system + parent inheritance)
     *
     * This method checks:
     * 1. Direct host permissions (user_host_permissions) - for technical admins
     * 2. Hosting-specific permissions (user_hosting_permissions) - for domain owners
     * 3. Parent user permissions - for authorized/delegated users
     */
    public function hasAccessToHost(int $hostId): bool
    {
        // Admin users have access to everything
        if ($this->is_admin) {
            return true;
        }

        // Get effective user IDs (current + parent if exists)
        $effectiveUserIds = [$this->id];
        if ($this->parent_user_id) {
            $effectiveUserIds[] = $this->parent_user_id;
        }

        // Check direct host permissions (existing system)
        $hasDirectAccess = $this->hosts()
            ->where('host_id', $hostId)
            ->wherePivot('is_active', true)
            ->exists();

        if ($hasDirectAccess) {
            return true;
        }

        // Check parent's direct host permissions if user is authorized
        if ($this->parent_user_id) {
            $parentHasDirectAccess = $this->parentUser->hosts()
                ->where('host_id', $hostId)
                ->wherePivot('is_active', true)
                ->exists();

            if ($parentHasDirectAccess) {
                return true;
            }
        }

        // Check hosting-specific permissions (new hybrid system) for user and parent
        $hasHostingAccess = UserHostingPermission::whereIn('user_id', $effectiveUserIds)
            ->whereHas('hosting', function ($query) use ($hostId) {
                $query->where('host_id', $hostId);
            })
            ->where('is_active', true)
            ->exists();

        return $hasHostingAccess;
    }

    /**
     * Check if user has access to a specific hosting (including parent permissions)
     */
    public function hasAccessToHosting(int $hostingId): bool
    {
        // Admin users have access to everything
        if ($this->is_admin) {
            return true;
        }

        // If this is a principal user (no parent), check ownership
        if (! $this->parent_user_id) {
            // Check if user owns the hosting
            if (Hosting::where('id', $hostingId)->where('user_id', $this->id)->exists()) {
                return true;
            }
        }

        // For all users (principal and authorized), check specific permissions
        return UserHostingPermission::where('user_id', $this->id)
            ->where('hosting_id', $hostingId)
            ->where('is_active', true)
            ->exists();
    }
}
