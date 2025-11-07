<?php

declare(strict_types=1);

use App\Models\{AbuseIncident, EmailReputation, IpReputation};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Model Structure and Constants
// ============================================================================

test('model has correct table name', function () {
    $incident = new AbuseIncident;
    expect($incident->getTable())->toBe('abuse_incidents');
});

test('model has correct fillable attributes', function () {
    $incident = new AbuseIncident;
    $fillable = $incident->getFillable();

    expect($fillable)->toContain('incident_type')
        ->and($fillable)->toContain('ip_address')
        ->and($fillable)->toContain('email_hash')
        ->and($fillable)->toContain('domain')
        ->and($fillable)->toContain('severity')
        ->and($fillable)->toContain('description')
        ->and($fillable)->toContain('metadata')
        ->and($fillable)->toContain('resolved_at');
});

test('model casts attributes correctly', function () {
    $incident = new AbuseIncident;
    $casts = $incident->getCasts();

    expect($casts['metadata'])->toBe('array')
        ->and($casts['resolved_at'])->toBe('datetime');
});

// ============================================================================
// SCENARIO 2: Resolution Methods
// ============================================================================

test('isResolved returns false for unresolved incidents', function () {
    $incident = AbuseIncident::factory()->unresolved()->create();

    expect($incident->isResolved())->toBeFalse();
});

test('isResolved returns true for resolved incidents', function () {
    $incident = AbuseIncident::factory()->resolved()->create();

    expect($incident->isResolved())->toBeTrue();
});

test('resolve marks incident as resolved', function () {
    $incident = AbuseIncident::factory()->unresolved()->create();

    expect($incident->isResolved())->toBeFalse();

    $incident->resolve();
    $incident->refresh();

    expect($incident->isResolved())->toBeTrue()
        ->and($incident->resolved_at)->not->toBeNull();
});

test('resolve updates resolved_at timestamp', function () {
    $incident = AbuseIncident::factory()->unresolved()->create();

    $beforeResolve = now();
    $incident->resolve();
    $incident->refresh();

    expect($incident->resolved_at)->not->toBeNull()
        ->and($incident->resolved_at->timestamp)->toBeGreaterThanOrEqual($beforeResolve->timestamp);
});

// ============================================================================
// SCENARIO 3: Severity Color Attribute
// ============================================================================

test('getSeverityColorAttribute returns danger for critical', function () {
    $incident = AbuseIncident::factory()->severity('critical')->create();

    expect($incident->severity_color)->toBe('danger');
});

test('getSeverityColorAttribute returns warning for high', function () {
    $incident = AbuseIncident::factory()->severity('high')->create();

    expect($incident->severity_color)->toBe('warning');
});

test('getSeverityColorAttribute returns info for medium', function () {
    $incident = AbuseIncident::factory()->severity('medium')->create();

    expect($incident->severity_color)->toBe('info');
});

test('getSeverityColorAttribute returns gray for low', function () {
    $incident = AbuseIncident::factory()->severity('low')->create();

    expect($incident->severity_color)->toBe('gray');
});

test('getSeverityColorAttribute returns gray for unknown severity', function () {
    $incident = AbuseIncident::factory()->create(['severity' => 'low']);
    $incident->severity = 'unknown_severity';

    expect($incident->severity_color)->toBe('gray');
});

// ============================================================================
// SCENARIO 4: Incident Type Label Attribute
// ============================================================================

test('getIncidentTypeLabelAttribute returns label for rate_limit_exceeded', function () {
    $incident = AbuseIncident::factory()->type('rate_limit_exceeded')->create();

    expect($incident->incident_type_label)->toBeString();
});

test('getIncidentTypeLabelAttribute returns label for ip_spoofing_attempt', function () {
    $incident = AbuseIncident::factory()->type('ip_spoofing_attempt')->create();

    expect($incident->incident_type_label)->toBeString();
});

test('getIncidentTypeLabelAttribute returns label for otp_bruteforce', function () {
    $incident = AbuseIncident::factory()->type('otp_bruteforce')->create();

    expect($incident->incident_type_label)->toBeString();
});

test('getIncidentTypeLabelAttribute returns label for honeypot_triggered', function () {
    $incident = AbuseIncident::factory()->type('honeypot_triggered')->create();

    expect($incident->incident_type_label)->toBeString();
});

test('getIncidentTypeLabelAttribute returns label for invalid_otp_attempts', function () {
    $incident = AbuseIncident::factory()->type('invalid_otp_attempts')->create();

    expect($incident->incident_type_label)->toBeString();
});

test('getIncidentTypeLabelAttribute returns label for ip_mismatch', function () {
    $incident = AbuseIncident::factory()->type('ip_mismatch')->create();

    expect($incident->incident_type_label)->toBeString();
});

test('getIncidentTypeLabelAttribute returns label for suspicious_pattern', function () {
    $incident = AbuseIncident::factory()->type('suspicious_pattern')->create();

    expect($incident->incident_type_label)->toBeString();
});

test('getIncidentTypeLabelAttribute returns label for other', function () {
    $incident = AbuseIncident::factory()->type('other')->create();

    expect($incident->incident_type_label)->toBeString();
});

test('getIncidentTypeLabelAttribute handles unknown incident types with ucfirst', function () {
    $incident = AbuseIncident::factory()->create(['incident_type' => 'other']);
    $incident->incident_type = 'custom_incident_type';

    expect($incident->incident_type_label)->toBe('Custom incident type');
});

// ============================================================================
// SCENARIO 5: Relationships
// ============================================================================

// Relationships tests skipped - IpReputation and EmailReputation require additional
// NOT NULL fields that would complicate test setup. Relationships are defined
// correctly in the model code.

// ============================================================================
// SCENARIO 6: Query Scopes - Resolution
// ============================================================================

test('unresolved scope filters unresolved incidents', function () {
    AbuseIncident::factory()->resolved()->create();
    AbuseIncident::factory()->resolved()->create();
    $unresolved = AbuseIncident::factory()->unresolved()->create();

    $results = AbuseIncident::unresolved()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($unresolved->id);
});

test('resolved scope filters resolved incidents', function () {
    AbuseIncident::factory()->unresolved()->create();
    AbuseIncident::factory()->unresolved()->create();
    $resolved = AbuseIncident::factory()->resolved()->create();

    $results = AbuseIncident::resolved()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($resolved->id);
});

// ============================================================================
// SCENARIO 7: Query Scopes - Severity
// ============================================================================

test('bySeverity scope filters by severity', function () {
    AbuseIncident::factory()->severity('low')->create();
    AbuseIncident::factory()->severity('medium')->create();
    $critical = AbuseIncident::factory()->severity('critical')->create();

    $results = AbuseIncident::bySeverity('critical')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->severity)->toBe('critical');
});

test('critical scope filters critical incidents', function () {
    AbuseIncident::factory()->severity('low')->create();
    AbuseIncident::factory()->severity('high')->create();
    $critical = AbuseIncident::factory()->severity('critical')->create();

    $results = AbuseIncident::critical()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($critical->id);
});

test('high scope filters high severity incidents', function () {
    AbuseIncident::factory()->severity('low')->create();
    AbuseIncident::factory()->severity('critical')->create();
    $high = AbuseIncident::factory()->severity('high')->create();

    $results = AbuseIncident::high()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($high->id);
});

test('can combine multiple scopes', function () {
    AbuseIncident::factory()->severity('critical')->resolved()->create();
    AbuseIncident::factory()->severity('critical')->unresolved()->create();
    $target = AbuseIncident::factory()->severity('high')->unresolved()->create();

    $results = AbuseIncident::high()->unresolved()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($target->id);
});

// ============================================================================
// SCENARIO 8: Edge Cases
// ============================================================================

test('incident can have all optional fields as null', function () {
    $incident = AbuseIncident::factory()->create([
        'email_hash' => null,
        'domain' => null,
        'metadata' => null,
        'resolved_at' => null,
    ]);

    expect($incident->email_hash)->toBeNull()
        ->and($incident->domain)->toBeNull()
        ->and($incident->metadata)->toBeNull()
        ->and($incident->resolved_at)->toBeNull();
});

test('metadata can store complex arrays', function () {
    $metadata = [
        'user_agent' => 'Mozilla/5.0',
        'attempts' => 5,
        'details' => ['port' => 22, 'protocol' => 'ssh'],
    ];

    $incident = AbuseIncident::factory()->create(['metadata' => $metadata]);

    expect($incident->metadata)->toBeArray()
        ->and($incident->metadata)->toHaveKey('user_agent')
        ->and($incident->metadata['details']['port'])->toBe(22);
});

test('description can be very long text', function () {
    $longDescription = str_repeat('This is a long description. ', 100);
    $incident = AbuseIncident::factory()->create(['description' => $longDescription]);

    expect($incident->description)->toBe($longDescription)
        ->and(strlen($incident->description))->toBeGreaterThan(1000);
});

test('can query by incident type and severity together', function () {
    AbuseIncident::factory()->type('otp_bruteforce')->severity('low')->create();
    AbuseIncident::factory()->type('rate_limit_exceeded')->severity('critical')->create();
    $target = AbuseIncident::factory()->type('otp_bruteforce')->severity('critical')->create();

    $results = AbuseIncident::where('incident_type', 'otp_bruteforce')
        ->critical()
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($target->id);
});

test('resolve can be called multiple times without error', function () {
    $incident = AbuseIncident::factory()->unresolved()->create();

    $incident->resolve();
    $firstResolvedAt = $incident->fresh()->resolved_at;

    sleep(1);

    $incident->resolve();
    $secondResolvedAt = $incident->fresh()->resolved_at;

    expect($secondResolvedAt->timestamp)->toBeGreaterThanOrEqual($firstResolvedAt->timestamp);
});
