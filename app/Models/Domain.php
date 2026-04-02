<?php

namespace App\Models;

use Carbon\Carbon;
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'domain_name',
        'type',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDomain($query, string $domainName)
    {
        return $query->where('domain_name', strtolower(trim($domainName)));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePrimary($query)
    {
        return $query->where('type', 'primary');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAddon($query)
    {
        return $query->where('type', 'addon');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSubdomain($query)
    {
        return $query->where('type', 'subdomain');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAlias($query)
    {
        return $query->where('type', 'alias');
    }
}
