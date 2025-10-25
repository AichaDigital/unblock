<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Host extends Model
{
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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'hosting_manual' => 'boolean',
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

    public function setHashAttribute($value): void
    {
        if (! is_null($value) && $value !== '') {
            $this->attributes['hash'] = Crypt::encrypt($value);
        }
    }

    public function getHashAttribute($value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Crypt::decrypt($value);
        } catch (Throwable $exception) {
            // Fallback: value might already be plaintext (legacy data)
            $trimmed = trim((string) $value);
            if (str_contains($trimmed, 'BEGIN OPENSSH PRIVATE KEY')) {
                return $trimmed;
            }

            return '';
        }
    }

    public function setHashPublicAttribute($value): void
    {
        if (! is_null($value) && $value !== '') {
            $this->attributes['hash_public'] = Crypt::encrypt($value);
        }
    }

    public function getHashPublicAttribute($value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Crypt::decrypt($value);
        } catch (Throwable $exception) {
            // Fallback: value might already be plaintext (legacy data)
            $trimmed = trim((string) $value);
            if (str_starts_with($trimmed, 'ssh-')) {
                return $trimmed;
            }

            return '';
        }
    }

    /**
     * Get the hostings for the host.
     */
    public function hostings(): HasMany
    {
        return $this->hasMany(Hosting::class);
    }

    /**
     * Get the users that have access to this host.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
