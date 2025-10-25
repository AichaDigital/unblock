<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pattern Detection Model
 *
 * Records detected attack patterns and anomalies
 * Created automatically by pattern detection services (v1.4.0)
 *
 * @property int $id
 * @property string $pattern_type
 * @property string $severity
 * @property int $confidence_score
 * @property string|null $email_hash
 * @property string|null $ip_address
 * @property string|null $subnet
 * @property string|null $domain
 * @property int $affected_ips_count
 * @property int $affected_emails_count
 * @property int $time_window_minutes
 * @property string $detection_algorithm
 * @property Carbon $detected_at
 * @property Carbon|null $first_incident_at
 * @property Carbon|null $last_incident_at
 * @property array|null $pattern_data
 * @property array|null $related_incidents
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PatternDetection extends Model
{
    protected $table = 'pattern_detections';

    protected $fillable = [
        'pattern_type',
        'severity',
        'confidence_score',
        'email_hash',
        'ip_address',
        'subnet',
        'domain',
        'affected_ips_count',
        'affected_emails_count',
        'time_window_minutes',
        'detection_algorithm',
        'detected_at',
        'first_incident_at',
        'last_incident_at',
        'pattern_data',
        'related_incidents',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'confidence_score' => 'integer',
        'affected_ips_count' => 'integer',
        'affected_emails_count' => 'integer',
        'time_window_minutes' => 'integer',
        'detected_at' => 'datetime',
        'first_incident_at' => 'datetime',
        'last_incident_at' => 'datetime',
        'pattern_data' => 'array',
        'related_incidents' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Pattern type constants
     */
    public const TYPE_DISTRIBUTED_ATTACK = 'distributed_attack';

    public const TYPE_SUBNET_SCAN = 'subnet_scan';

    public const TYPE_COORDINATED_ATTACK = 'coordinated_attack';

    public const TYPE_ANOMALY_SPIKE = 'anomaly_spike';

    /**
     * Severity constants
     */
    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Get related abuse incidents
     */
    public function abuseIncidents()
    {
        if (! $this->related_incidents) {
            return collect();
        }

        return AbuseIncident::whereIn('id', $this->related_incidents)->get();
    }

    /**
     * Check if pattern is resolved
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Mark pattern as resolved
     */
    public function resolve(?string $notes = null): void
    {
        $this->update([
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Get severity badge color for Filament
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'danger',
            self::SEVERITY_HIGH => 'warning',
            self::SEVERITY_MEDIUM => 'info',
            self::SEVERITY_LOW => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get pattern type label for display
     */
    public function getPatternTypeLabelAttribute(): string
    {
        return match ($this->pattern_type) {
            self::TYPE_DISTRIBUTED_ATTACK => 'Distributed Attack',
            self::TYPE_SUBNET_SCAN => 'Subnet Scan',
            self::TYPE_COORDINATED_ATTACK => 'Coordinated Attack',
            self::TYPE_ANOMALY_SPIKE => 'Anomaly Spike',
            default => ucfirst(str_replace('_', ' ', $this->pattern_type)),
        };
    }

    /**
     * Get confidence badge color
     */
    public function getConfidenceColorAttribute(): string
    {
        return match (true) {
            $this->confidence_score >= 75 => 'success',
            $this->confidence_score >= 50 => 'warning',
            default => 'danger',
        };
    }

    /**
     * Scope to get only unresolved patterns
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope to get only resolved patterns
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Scope to filter by pattern type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('pattern_type', $type);
    }

    /**
     * Scope to filter by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to get critical patterns
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    /**
     * Scope to get high severity patterns
     */
    public function scopeHigh($query)
    {
        return $query->where('severity', self::SEVERITY_HIGH);
    }

    /**
     * Scope for recent detections (last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('detected_at', '>=', now()->subDay());
    }
}
