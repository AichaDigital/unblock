<?php

namespace App\Models;

use App\Observers\ReportObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Report Model
 *
 * @property string $id
 * @property int|null $user_id
 * @property int $host_id
 * @property string $ip
 * @property array|null $logs
 * @property array|null $analysis
 * @property bool $was_unblocked
 * @property \Carbon\Carbon|null $last_read
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User|null $user
 * @property-read Host $host
 */
#[ObservedBy([ReportObserver::class])]
class Report extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'host_id',
        'ip',
        'logs',
        'analysis',
        'was_unblocked',
        'last_read',
    ];

    protected $casts = [
        'logs' => 'array',
        'analysis' => 'array',
        'was_unblocked' => 'boolean',
        'last_read' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
