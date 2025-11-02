<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Domain Model
 *
 * Represents a domain (primary, addon, subdomain, or alias) associated with a hosting account.
 * Used for fast domain validation in Simple Mode without SSH connections.
 *
 * @property int $id
 * @property int $account_id
 * @property string $domain_name
 * @property string $type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Domain extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'account_id',
        'domain_name',
        'type',
    ];

    /**
     * Get the account that owns the domain.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope a query to search for a specific domain name.
     */
    public function scopeForDomain($query, string $domainName)
    {
        return $query->where('domain_name', strtolower(trim($domainName)));
    }

    /**
     * Scope a query to only include primary domains.
     */
    public function scopePrimary($query)
    {
        return $query->where('type', 'primary');
    }

    /**
     * Scope a query to only include addon domains.
     */
    public function scopeAddon($query)
    {
        return $query->where('type', 'addon');
    }

    /**
     * Scope a query to only include subdomains.
     */
    public function scopeSubdomain($query)
    {
        return $query->where('type', 'subdomain');
    }

    /**
     * Scope a query to only include alias domains.
     */
    public function scopeAlias($query)
    {
        return $query->where('type', 'alias');
    }
}
