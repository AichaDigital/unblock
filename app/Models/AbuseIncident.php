<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Abuse Incident Model
 *
 * Records security incidents and abuse attempts
 * Created automatically by event listeners (v1.3.0)
 *
 * @property int $id
 * @property string $incident_type
 * @property string $ip_address
 * @property string|null $email_hash
 * @property string|null $domain
 * @property string $severity
 * @property string $description
 * @property array|null $metadata
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AbuseIncident extends Model
{
    protected $table = 'abuse_incidents';

    protected $fillable = [
        'incident_type',
        'ip_address',
        'email_hash',
        'domain',
        'severity',
        'description',
        'metadata',
        'resolved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get IP reputation record
     */
    public function ipReputation()
    {
        return $this->belongsTo(IpReputation::class, 'ip_address', 'ip');
    }

    /**
     * Get email reputation record
     */
    public function emailReputation()
    {
        return $this->belongsTo(EmailReputation::class, 'email_hash', 'email_hash');
    }

    /**
     * Check if incident is resolved
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Mark incident as resolved
     */
    public function resolve(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    /**
     * Get severity badge color for Filament
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get incident type label for display
     */
    public function getIncidentTypeLabelAttribute(): string
    {
        return match ($this->incident_type) {
            'rate_limit_exceeded' => __('firewall.abuse_incidents.types.rate_limit_exceeded'),
            'ip_spoofing_attempt' => __('firewall.abuse_incidents.types.ip_spoofing_attempt'),
            'otp_bruteforce' => __('firewall.abuse_incidents.types.otp_bruteforce'),
            'honeypot_triggered' => __('firewall.abuse_incidents.types.honeypot_triggered'),
            'invalid_otp_attempts' => __('firewall.abuse_incidents.types.invalid_otp_attempts'),
            'ip_mismatch' => __('firewall.abuse_incidents.types.ip_mismatch'),
            'suspicious_pattern' => __('firewall.abuse_incidents.types.suspicious_pattern'),
            'other' => __('firewall.abuse_incidents.types.other'),
            default => ucfirst(str_replace('_', ' ', $this->incident_type)),
        };
    }

    /**
     * Scope to get only unresolved incidents
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope to get only resolved incidents
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Scope to filter by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to get critical incidents
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope to get high severity incidents
     */
    public function scopeHigh($query)
    {
        return $query->where('severity', 'high');
    }
}
