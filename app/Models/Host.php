<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PanelType;
use Database\Factories\HostFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{HasMany, HasManyThrough};
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Host extends Model
{
    /** @use HasFactory<HostFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'whmcs_id',
        'fqdn',
        'alias',
        'ip',
        'port_ssh',
        'hash',
        'hash_public',
        'panel',
        'admin',
        'whmcs_server_id',
        'hosting_manual',
        'ssh_host_key_verified',
        'ssh_host_key_verified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'panel' => PanelType::class,
        'hosting_manual' => 'boolean',
        'ssh_host_key_verified' => 'boolean',
        'ssh_host_key_verified_at' => 'datetime',
        'port_ssh' => 'integer',
        'whmcs_server_id' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'hash',
        'hash_public',
    ];

    /**
     * Encrypted private SSH key. Decrypts on read, with a plaintext fallback for
     * legacy data; never overwrites the stored value when set to null/empty.
     *
     * @return Attribute<string, string|null>
     */
    protected function hash(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): string {
                if (! $value) {
                    return '';
                }

                try {
                    return Crypt::decrypt($value);
                } catch (Throwable) {
                    // Fallback: value might already be plaintext (legacy data)
                    $trimmed = trim($value);

                    return str_contains($trimmed, 'BEGIN OPENSSH PRIVATE KEY') ? $trimmed : '';
                }
            },
            set: fn (?string $value): array => (! is_null($value) && $value !== '')
                ? ['hash' => Crypt::encrypt($value)]
                : [],
        );
    }

    /**
     * Encrypted public SSH key. Same decrypt/fallback/no-overwrite semantics as {@see hash()}.
     *
     * @return Attribute<string, string|null>
     */
    protected function hashPublic(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): string {
                if (! $value) {
                    return '';
                }

                try {
                    return Crypt::decrypt($value);
                } catch (Throwable) {
                    // Fallback: value might already be plaintext (legacy data)
                    $trimmed = trim($value);

                    return str_starts_with($trimmed, 'ssh-') ? $trimmed : '';
                }
            },
            set: fn (?string $value): array => (! is_null($value) && $value !== '')
                ? ['hash_public' => Crypt::encrypt($value)]
                : [],
        );
    }

    public function isHostKeyVerified(): bool
    {
        return (bool) $this->ssh_host_key_verified;
    }

    /**
     * @return HasMany<Hosting, $this>
     */
    public function hostings(): HasMany
    {
        return $this->hasMany(Hosting::class);
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasManyThrough<Domain, Account, $this>
     */
    public function domains(): HasManyThrough
    {
        return $this->hasManyThrough(Domain::class, Account::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get array representation safe for logging (excludes sensitive data)
     *
     * NEVER log $host directly - always use this method to prevent
     * SSH keys from being exposed in logs.
     *
     * @return array<string, mixed>
     */
    public function toSafeLogArray(): array
    {
        return [
            'id' => $this->id,
            'fqdn' => $this->fqdn,
            'alias' => $this->alias,
            'ip' => $this->ip,
            'port_ssh' => $this->port_ssh,
            'panel' => $this->panel,
            'admin' => $this->admin,
            'whmcs_server_id' => $this->whmcs_server_id,
            'hosting_manual' => $this->hosting_manual,
            // Explicitly EXCLUDE:
            // - hash (private SSH key)
            // - hash_public (public SSH key)
        ];
    }
}
