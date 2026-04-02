<?php

namespace App\Models;

use App\Observers\ReportObserver;
use Carbon\Carbon;
use Database\Factories\ReportFactory;
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
 * @property array<string, mixed>|null $logs
 * @property array<string, mixed>|null $analysis
 * @property bool $was_unblocked
 * @property Carbon|null $last_read
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $user
 * @property-read Host $host
 */
#[ObservedBy([ReportObserver::class])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'host_id',
        'ip',
        'logs',
        'analysis',
        'was_unblocked',
        'last_read',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'logs' => 'array',
        'analysis' => 'array',
        'was_unblocked' => 'boolean',
        'last_read' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Host, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
