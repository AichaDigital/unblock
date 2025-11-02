<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CSF Output Parser
 *
 * Parses CSF (ConfigServer Security & Firewall) command output
 * to extract human-readable information about IP blocks.
 */
class CsfOutputParser
{
    /**
     * Parse a CSF deny line to extract structured information
     *
     * Example line:
     * "158.173.23.58 # lfd: (smtpauth) Failed SMTP AUTH login from 158.173.23.58 (GB/United Kingdom/-): 5 in the last 3600 secs - Thu Oct 30 06:27:30 2025"
     */
    public function parseDenyLine(string $line): array
    {
        $result = [
            'ip' => null,
            'reason_type' => null,
            'reason' => null,
            'location' => null,
            'attempts' => null,
            'timeframe' => null,
            'timestamp' => null,
        ];

        // Extract IP (first part before #)
        if (preg_match('/^([0-9\.]+)\s*#/', $line, $matches)) {
            $result['ip'] = $matches[1];
        }

        // Extract reason type (e.g., "smtpauth", "imapd")
        if (preg_match('/lfd:\s*\(([^\)]+)\)/', $line, $matches)) {
            $result['reason_type'] = $matches[1];
        }

        // Extract location (e.g., "GB/United Kingdom/-")
        if (preg_match('/\(([A-Z]{2}\/[^)]+)\)/', $line, $matches)) {
            $result['location'] = $matches[1];
        }

        // Extract attempts and timeframe (e.g., "5 in the last 3600 secs")
        if (preg_match('/:\s*(\d+)\s+in\s+the\s+last\s+(\d+)\s+secs/', $line, $matches)) {
            $result['attempts'] = (int) $matches[1];
            $result['timeframe'] = (int) $matches[2];
        }

        // Extract timestamp (last part after " - ")
        if (preg_match('/- (.+)$/', $line, $matches)) {
            $result['timestamp'] = trim($matches[1]);
        }

        // Extract reason (description between reason_type and either location or attempts)
        if (preg_match('/lfd:\s*\([^\)]+\)\s+([^(]+?)(?:\s+from\s+[0-9\.]+)?(?:\s+\([A-Z]{2}\/|:\s*\d+\s+in\s+the\s+last)/', $line, $matches)) {
            $result['reason'] = trim($matches[1]);
        } elseif (preg_match('/#\s+(.+?)\s+-\s+[A-Z][a-z]{2}/', $line, $matches)) {
            // Simple format: "# Reason - Date"
            $result['reason'] = trim($matches[1]);
        }

        return $result;
    }

    /**
     * Extract human-readable summary from full CSF output
     *
     * Returns:
     * [
     *   'blocked' => bool,
     *   'block_type' => 'csf.deny' | 'firewall_rules' | null,
     *   'reason_short' => string | null,
     *   'attempts' => int | null,
     *   'location' => string | null,
     *   'blocked_since' => string | null,
     * ]
     */
    public function extractHumanReadableSummary(string $csfOutput): array
    {
        $summary = [
            'blocked' => false,
            'block_type' => null,
            'reason_short' => null,
            'attempts' => null,
            'location' => null,
            'blocked_since' => null,
        ];

        // Check if IP is blocked
        $summary['blocked'] = $this->isBlocked($csfOutput);

        if (! $summary['blocked']) {
            return $summary;
        }

        // Try to extract deny line information
        if (preg_match('/csf\.deny:\s+(.+)$/m', $csfOutput, $matches)) {
            $summary['block_type'] = 'csf.deny';
            $denyInfo = $this->parseDenyLine($matches[1]);

            $summary['reason_short'] = $denyInfo['reason'];
            $summary['attempts'] = $denyInfo['attempts'];
            $summary['location'] = $denyInfo['location'];
            $summary['blocked_since'] = $denyInfo['timestamp'];
        } else {
            // Blocked by firewall rules but no deny line found
            $summary['block_type'] = 'firewall_rules';
        }

        return $summary;
    }

    /**
     * Check if CSF output indicates IP is blocked
     */
    private function isBlocked(string $output): bool
    {
        // Check for common blocking patterns
        $blockPatterns = [
            'DENYIN',
            'DENYOUT',
            'DROP',
            'LOGDROPOUT',
            'csf.deny:',
        ];

        foreach ($blockPatterns as $pattern) {
            if (stripos($output, $pattern) !== false) {
                return true;
            }
        }

        // Check for "No matches found" (means not blocked)
        if (stripos($output, 'No matches found') !== false) {
            return false;
        }

        return false;
    }
}
