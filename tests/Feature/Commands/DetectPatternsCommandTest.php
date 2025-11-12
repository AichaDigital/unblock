<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('detect patterns command runs successfully with no detections', function () {
    // Since detectors are instantiated directly, we test the command execution
    // The actual detection logic is tested in their respective test files
    $this->artisan('patterns:detect')
        ->expectsOutput('🔍 Starting pattern detection...')
        ->expectsOutput('→ Running Distributed Attack Detector...')
        ->expectsOutput('→ Running Subnet Scan Detector...')
        ->expectsOutput('→ Running Anomaly Detector...')
        ->expectsOutputToContain('✓ Detection complete! Total patterns found:')
        ->assertSuccessful();
});

test('detect patterns command executes without errors', function () {
    // Test that command executes successfully
    // Actual detection logic is tested in detector service tests
    $this->artisan('patterns:detect')
        ->assertSuccessful();
});
