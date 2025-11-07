<?php

declare(strict_types=1);

use App\Models\{AbuseIncident, PatternDetection};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================================================
// SCENARIO 1: Model Structure and Constants
// ============================================================================

test('model has correct table name', function () {
    $pattern = new PatternDetection;

    expect($pattern->getTable())->toBe('pattern_detections');
});

test('model has correct fillable attributes', function () {
    $fillable = [
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

    expect((new PatternDetection)->getFillable())->toBe($fillable);
});

test('model casts attributes correctly', function () {
    $pattern = PatternDetection::factory()->create([
        'confidence_score' => '75',
        'affected_ips_count' => '10',
        'affected_emails_count' => '5',
        'time_window_minutes' => '60',
        'pattern_data' => ['key' => 'value'],
        'related_incidents' => [1, 2, 3],
    ]);

    expect($pattern->confidence_score)->toBeInt()
        ->and($pattern->affected_ips_count)->toBeInt()
        ->and($pattern->affected_emails_count)->toBeInt()
        ->and($pattern->time_window_minutes)->toBeInt()
        ->and($pattern->pattern_data)->toBeArray()
        ->and($pattern->related_incidents)->toBeArray()
        ->and($pattern->detected_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('pattern type constants are defined', function () {
    expect(PatternDetection::TYPE_DISTRIBUTED_ATTACK)->toBe('distributed_attack')
        ->and(PatternDetection::TYPE_SUBNET_SCAN)->toBe('subnet_scan')
        ->and(PatternDetection::TYPE_COORDINATED_ATTACK)->toBe('coordinated_attack')
        ->and(PatternDetection::TYPE_ANOMALY)->toBe('anomaly')
        ->and(PatternDetection::TYPE_ANOMALY_SPIKE)->toBe('anomaly_spike')
        ->and(PatternDetection::TYPE_OTHER)->toBe('other');
});

test('severity constants are defined', function () {
    expect(PatternDetection::SEVERITY_LOW)->toBe('low')
        ->and(PatternDetection::SEVERITY_MEDIUM)->toBe('medium')
        ->and(PatternDetection::SEVERITY_HIGH)->toBe('high')
        ->and(PatternDetection::SEVERITY_CRITICAL)->toBe('critical');
});

// ============================================================================
// SCENARIO 2: Pattern Resolution
// ============================================================================

test('isResolved returns false for unresolved patterns', function () {
    $pattern = PatternDetection::factory()->create(['resolved_at' => null]);

    expect($pattern->isResolved())->toBeFalse();
});

test('isResolved returns true for resolved patterns', function () {
    $pattern = PatternDetection::factory()->create(['resolved_at' => now()]);

    expect($pattern->isResolved())->toBeTrue();
});

test('resolve marks pattern as resolved', function () {
    $pattern = PatternDetection::factory()->create(['resolved_at' => null]);

    expect($pattern->isResolved())->toBeFalse();

    $pattern->resolve();
    $pattern->refresh();

    expect($pattern->isResolved())->toBeTrue()
        ->and($pattern->resolved_at)->not->toBeNull();
});

test('resolve accepts resolution notes', function () {
    $pattern = PatternDetection::factory()->create(['resolved_at' => null]);

    $pattern->resolve('Manual investigation completed');
    $pattern->refresh();

    expect($pattern->resolution_notes)->toBe('Manual investigation completed')
        ->and($pattern->isResolved())->toBeTrue();
});

// ============================================================================
// SCENARIO 3: Severity Color Attribute
// ============================================================================

test('getSeverityColorAttribute returns danger for critical', function () {
    $pattern = PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_CRITICAL]);

    expect($pattern->severity_color)->toBe('danger');
});

test('getSeverityColorAttribute returns warning for high', function () {
    $pattern = PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_HIGH]);

    expect($pattern->severity_color)->toBe('warning');
});

test('getSeverityColorAttribute returns info for medium', function () {
    $pattern = PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_MEDIUM]);

    expect($pattern->severity_color)->toBe('info');
});

test('getSeverityColorAttribute returns gray for low', function () {
    $pattern = PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_LOW]);

    expect($pattern->severity_color)->toBe('gray');
});

test('getSeverityColorAttribute returns gray for unknown severity', function () {
    $pattern = PatternDetection::factory()->create(['severity' => 'unknown']);

    expect($pattern->severity_color)->toBe('gray');
});

// ============================================================================
// SCENARIO 4: Pattern Type Label Attribute
// ============================================================================

test('getPatternTypeLabelAttribute returns label for distributed_attack', function () {
    $pattern = PatternDetection::factory()->create(['pattern_type' => PatternDetection::TYPE_DISTRIBUTED_ATTACK]);

    expect($pattern->pattern_type_label)->toBe('Distributed Attack');
});

test('getPatternTypeLabelAttribute returns label for subnet_scan', function () {
    $pattern = PatternDetection::factory()->create(['pattern_type' => PatternDetection::TYPE_SUBNET_SCAN]);

    expect($pattern->pattern_type_label)->toBe('Subnet Scan');
});

test('getPatternTypeLabelAttribute returns label for coordinated_attack', function () {
    $pattern = PatternDetection::factory()->create(['pattern_type' => PatternDetection::TYPE_COORDINATED_ATTACK]);

    expect($pattern->pattern_type_label)->toBe('Coordinated Attack');
});

test('getPatternTypeLabelAttribute returns label for anomaly', function () {
    $pattern = PatternDetection::factory()->create(['pattern_type' => PatternDetection::TYPE_ANOMALY]);

    expect($pattern->pattern_type_label)->toBe('Traffic Anomaly');
});

test('getPatternTypeLabelAttribute returns label for anomaly_spike', function () {
    $pattern = PatternDetection::factory()->create(['pattern_type' => PatternDetection::TYPE_ANOMALY_SPIKE]);

    expect($pattern->pattern_type_label)->toBe('Anomaly Spike');
});

test('getPatternTypeLabelAttribute returns label for other', function () {
    $pattern = PatternDetection::factory()->create(['pattern_type' => PatternDetection::TYPE_OTHER]);

    expect($pattern->pattern_type_label)->toBe('Other');
});

test('getPatternTypeLabelAttribute handles unknown pattern types', function () {
    $pattern = PatternDetection::factory()->create(['pattern_type' => 'custom_pattern']);

    expect($pattern->pattern_type_label)->toBe('Custom pattern');
});

// ============================================================================
// SCENARIO 5: Confidence Color Attribute
// ============================================================================

test('getConfidenceColorAttribute returns success for high confidence', function () {
    $pattern = PatternDetection::factory()->create(['confidence_score' => 90]);

    expect($pattern->confidence_color)->toBe('success');
});

test('getConfidenceColorAttribute returns success for 75', function () {
    $pattern = PatternDetection::factory()->create(['confidence_score' => 75]);

    expect($pattern->confidence_color)->toBe('success');
});

test('getConfidenceColorAttribute returns warning for medium confidence', function () {
    $pattern = PatternDetection::factory()->create(['confidence_score' => 60]);

    expect($pattern->confidence_color)->toBe('warning');
});

test('getConfidenceColorAttribute returns warning for 50', function () {
    $pattern = PatternDetection::factory()->create(['confidence_score' => 50]);

    expect($pattern->confidence_color)->toBe('warning');
});

test('getConfidenceColorAttribute returns danger for low confidence', function () {
    $pattern = PatternDetection::factory()->create(['confidence_score' => 30]);

    expect($pattern->confidence_color)->toBe('danger');
});

// ============================================================================
// SCENARIO 6: Abuse Incidents Relationship
// ============================================================================

test('abuseIncidents returns empty collection when no related incidents', function () {
    $pattern = PatternDetection::factory()->create(['related_incidents' => null]);

    $incidents = $pattern->abuseIncidents();

    expect($incidents)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($incidents)->toBeEmpty();
});

test('abuseIncidents returns empty collection for empty array', function () {
    $pattern = PatternDetection::factory()->create(['related_incidents' => []]);

    $incidents = $pattern->abuseIncidents();

    expect($incidents)->toBeEmpty();
});

test('abuseIncidents returns related incidents', function () {
    $incident1 = AbuseIncident::factory()->create();
    $incident2 = AbuseIncident::factory()->create();

    $pattern = PatternDetection::factory()->create([
        'related_incidents' => [$incident1->id, $incident2->id],
    ]);

    $incidents = $pattern->abuseIncidents();

    expect($incidents)->toHaveCount(2)
        ->and($incidents->pluck('id')->toArray())->toContain($incident1->id)
        ->and($incidents->pluck('id')->toArray())->toContain($incident2->id);
});

// ============================================================================
// SCENARIO 7: Query Scopes - Resolution
// ============================================================================

test('unresolved scope filters unresolved patterns', function () {
    PatternDetection::factory()->create(['resolved_at' => null]);
    PatternDetection::factory()->create(['resolved_at' => null]);
    PatternDetection::factory()->create(['resolved_at' => now()]);

    $unresolved = PatternDetection::unresolved()->get();

    expect($unresolved)->toHaveCount(2);
});

test('resolved scope filters resolved patterns', function () {
    PatternDetection::factory()->create(['resolved_at' => null]);
    PatternDetection::factory()->create(['resolved_at' => now()]);
    PatternDetection::factory()->create(['resolved_at' => now()]);

    $resolved = PatternDetection::resolved()->get();

    expect($resolved)->toHaveCount(2);
});

// ============================================================================
// SCENARIO 8: Query Scopes - Type and Severity
// ============================================================================

test('byType scope filters by pattern type', function () {
    PatternDetection::factory()->create(['pattern_type' => PatternDetection::TYPE_DISTRIBUTED_ATTACK]);
    PatternDetection::factory()->create(['pattern_type' => PatternDetection::TYPE_DISTRIBUTED_ATTACK]);
    PatternDetection::factory()->create(['pattern_type' => PatternDetection::TYPE_SUBNET_SCAN]);

    $distributed = PatternDetection::byType(PatternDetection::TYPE_DISTRIBUTED_ATTACK)->get();

    expect($distributed)->toHaveCount(2);
});

test('bySeverity scope filters by severity', function () {
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_CRITICAL]);
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_CRITICAL]);
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_LOW]);

    $critical = PatternDetection::bySeverity(PatternDetection::SEVERITY_CRITICAL)->get();

    expect($critical)->toHaveCount(2);
});

test('critical scope filters critical patterns', function () {
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_CRITICAL]);
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_CRITICAL]);
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_HIGH]);
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_LOW]);

    $critical = PatternDetection::critical()->get();

    expect($critical)->toHaveCount(2)
        ->and($critical->every(fn ($p) => $p->severity === PatternDetection::SEVERITY_CRITICAL))->toBeTrue();
});

test('high scope filters high severity patterns', function () {
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_HIGH]);
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_HIGH]);
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_CRITICAL]);
    PatternDetection::factory()->create(['severity' => PatternDetection::SEVERITY_LOW]);

    $high = PatternDetection::high()->get();

    expect($high)->toHaveCount(2)
        ->and($high->every(fn ($p) => $p->severity === PatternDetection::SEVERITY_HIGH))->toBeTrue();
});

// ============================================================================
// SCENARIO 9: Recent Scope
// ============================================================================

test('recent scope filters patterns from last 24 hours', function () {
    // Recent (within 24h)
    PatternDetection::factory()->create(['detected_at' => now()->subHours(12)]);
    PatternDetection::factory()->create(['detected_at' => now()->subHours(6)]);

    // Old (more than 24h)
    PatternDetection::factory()->create(['detected_at' => now()->subHours(30)]);
    PatternDetection::factory()->create(['detected_at' => now()->subDays(2)]);

    $recent = PatternDetection::recent()->get();

    expect($recent)->toHaveCount(2);
});

test('recent scope includes patterns detected exactly 24 hours ago', function () {
    PatternDetection::factory()->create(['detected_at' => now()->subDay()]);
    PatternDetection::factory()->create(['detected_at' => now()->subHours(25)]);

    $recent = PatternDetection::recent()->get();

    expect($recent)->toHaveCount(1);
});

// ============================================================================
// SCENARIO 10: Combining Scopes
// ============================================================================

test('scopes can be combined', function () {
    // Create various patterns
    PatternDetection::factory()->create([
        'pattern_type' => PatternDetection::TYPE_DISTRIBUTED_ATTACK,
        'severity' => PatternDetection::SEVERITY_CRITICAL,
        'resolved_at' => null,
        'detected_at' => now()->subHours(6),
    ]);

    PatternDetection::factory()->create([
        'pattern_type' => PatternDetection::TYPE_DISTRIBUTED_ATTACK,
        'severity' => PatternDetection::SEVERITY_CRITICAL,
        'resolved_at' => now(),
        'detected_at' => now()->subHours(6),
    ]);

    PatternDetection::factory()->create([
        'pattern_type' => PatternDetection::TYPE_DISTRIBUTED_ATTACK,
        'severity' => PatternDetection::SEVERITY_LOW,
        'resolved_at' => null,
        'detected_at' => now()->subHours(6),
    ]);

    $results = PatternDetection::unresolved()
        ->byType(PatternDetection::TYPE_DISTRIBUTED_ATTACK)
        ->critical()
        ->recent()
        ->get();

    expect($results)->toHaveCount(1);
});

// ============================================================================
// SCENARIO 11: Edge Cases
// ============================================================================

test('pattern can have all optional fields as null', function () {
    $pattern = PatternDetection::factory()->create([
        'email_hash' => null,
        'ip_address' => null,
        'subnet' => null,
        'domain' => null,
        'pattern_data' => null,
        'related_incidents' => null,
        'resolved_at' => null,
        'resolution_notes' => null,
        'first_incident_at' => null,
        'last_incident_at' => null,
    ]);

    expect($pattern->email_hash)->toBeNull()
        ->and($pattern->ip_address)->toBeNull()
        ->and($pattern->subnet)->toBeNull()
        ->and($pattern->domain)->toBeNull();
});

test('pattern data can store complex arrays', function () {
    $complexData = [
        'ips' => ['192.168.1.1', '192.168.1.2'],
        'timestamps' => [now()->toDateTimeString(), now()->subHour()->toDateTimeString()],
        'metadata' => [
            'source' => 'detector_v1',
            'version' => '1.0.0',
        ],
    ];

    $pattern = PatternDetection::factory()->create(['pattern_data' => $complexData]);

    expect($pattern->pattern_data)->toBeArray()
        ->and($pattern->pattern_data['ips'])->toHaveCount(2)
        ->and($pattern->pattern_data['metadata']['source'])->toBe('detector_v1');
});

test('confidence score can be 0', function () {
    $pattern = PatternDetection::factory()->create(['confidence_score' => 0]);

    expect($pattern->confidence_score)->toBe(0)
        ->and($pattern->confidence_color)->toBe('danger');
});

test('confidence score can be 100', function () {
    $pattern = PatternDetection::factory()->create(['confidence_score' => 100]);

    expect($pattern->confidence_score)->toBe(100)
        ->and($pattern->confidence_color)->toBe('success');
});

test('affected counts can be zero', function () {
    $pattern = PatternDetection::factory()->create([
        'affected_ips_count' => 0,
        'affected_emails_count' => 0,
    ]);

    expect($pattern->affected_ips_count)->toBe(0)
        ->and($pattern->affected_emails_count)->toBe(0);
});
