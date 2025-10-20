<?php

declare(strict_types=1);

test('verifies modsecurity json line by line processing', function () {
    $stub = require base_path('tests/stubs/directadmin_mod_security_da.php');
    $jsonLines = $stub['mod_security_da'];

    // Split into lines and process each one
    $lines = explode("\n", $jsonLines);
    $validJsonCount = 0;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        try {
            $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

            // Verify required structure
            expect($data)->toHaveKey('transaction');
            expect($data['transaction'])->toHaveKey('client_ip');

            $validJsonCount++;
        } catch (\JsonException $e) {
            // Skip invalid JSON lines
        }
    }

    // Verify we processed at least some valid JSON lines
    expect($validJsonCount)->toBeGreaterThan(0);
})->group('debug-json');
