<?php

declare(strict_types=1);

// Minimal Clover XML coverage gate.
// Usage: php scripts/coverage-check.php <minPercent:int> <cloverPath>

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/coverage-check.php <minPercent> <cloverPath>\n");
    exit(2);
}

$minPercent = (int) $argv[1];
$cloverPath = $argv[2];

if (! file_exists($cloverPath)) {
    fwrite(STDERR, "Clover report not found: {$cloverPath}\n");
    exit(3);
}

$xml = new SimpleXMLElement((string) file_get_contents($cloverPath));
$total = $xml->xpath('/coverage/project/metrics');
if (! $total || ! isset($total[0])) {
    fwrite(STDERR, "Invalid clover file structure\n");
    exit(4);
}

$metrics = $total[0];
$covered = (int) $metrics['coveredstatements'] + (int) $metrics['coveredmethods'] + (int) $metrics['coveredconditionals'];
$all = (int) $metrics['statements'] + (int) $metrics['methods'] + (int) $metrics['conditionals'];

$percent = $all > 0 ? (int) floor(($covered / $all) * 100) : 0;

if ($percent < $minPercent) {
    fwrite(STDERR, sprintf("Coverage %.d%% is below required %d%%\n", $percent, $minPercent));
    exit(1);
}

fwrite(STDOUT, sprintf("Coverage OK: %d%% >= %d%%\n", $percent, $minPercent));
