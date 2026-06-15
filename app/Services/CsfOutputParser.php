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
     * Check if CSF output indicates IP is blocked.
     *
     * Backward-compatible wrapper (no per-IP filtering). Delegates to
     * isEffectivelyBlocked so a coexisting whitelist is honoured.
     */
    private function isBlocked(string $output): bool
    {
        return $this->isEffectivelyBlocked($output);
    }

    /**
     * Determine whether an IP is *effectively* blocked in `csf -g` output.
     *
     * CSF evaluates ALLOW chains before DENY chains, so a Temporary Allow /
     * ALLOWIN rule that covers the denied ports wins the traffic — the IP is not
     * actually blocked even when DENYIN / Temporary Blocks lines are also present
     * (a stale temp ban that was never cleared). See AID-171.
     *
     * Rules:
     * - No deny lines                                   -> not blocked.
     * - An allow covering ALL ports (no dpt / empty Port:) -> not blocked.
     * - Otherwise -> blocked if any denied port is not present in the allow set.
     *
     * @param  string  $csfOutput  Raw output of `csf -g <ip>`
     * @param  string|null  $ip  When given, only lines mentioning this IP are considered
     */
    public function isEffectivelyBlocked(string $csfOutput, ?string $ip = null): bool
    {
        $hasDeny = false;
        $allowAllPorts = false;
        $allowPorts = [];
        $denyPorts = [];

        foreach (preg_split('/\r?\n/', $csfOutput) ?: [] as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }
            if ($ip !== null && ! str_contains($line, $ip)) {
                continue;
            }

            $isAllow = str_contains($line, 'ALLOWIN')
                || str_contains($line, 'ALLOWOUT')
                || stripos($line, 'Temporary Allows') !== false;

            $isDeny = str_contains($line, 'DENYIN')
                || str_contains($line, 'DENYOUT')
                || str_contains($line, 'LOGDROPOUT')
                || str_contains($line, 'csf.deny:')
                || str_contains($line, 'chain_DENY')
                || stripos($line, 'Temporary Blocks') !== false
                || (str_contains($line, 'DROP') && ! $isAllow);

            if (! $isAllow && ! $isDeny) {
                continue;
            }

            $ports = $this->extractPorts($line);

            if ($isAllow) {
                if ($ports === null) {
                    $allowAllPorts = true;
                } else {
                    foreach ($ports as $port) {
                        $allowPorts[$port] = true;
                    }
                }

                continue;
            }

            // Deny line
            $hasDeny = true;
            if ($ports === null) {
                $denyPorts['all'] = true;
            } else {
                foreach ($ports as $port) {
                    $denyPorts[$port] = true;
                }
            }
        }

        if (! $hasDeny) {
            return false;
        }

        if ($allowAllPorts) {
            return false;
        }

        foreach (array_keys($denyPorts) as $port) {
            if ($port === 'all') {
                return true; // denies every port; a port-specific allow cannot cover it
            }
            if (! isset($allowPorts[$port])) {
                return true; // this denied port is not whitelisted
            }
        }

        return false;
    }

    /**
     * Extract destination ports referenced in a `csf -g` line.
     *
     * @return array<int,int>|null List of ports, or null when the line targets ALL ports
     */
    private function extractPorts(string $line): ?array
    {
        // "Temporary Allows/Blocks: ... Port:25,465,587" or "Port: " (empty = all ports)
        if (preg_match('/Port:\s*([0-9,]*)/', $line, $matches)) {
            $portsStr = trim($matches[1]);
            if ($portsStr === '') {
                return null;
            }
            $ports = array_filter(
                array_map('trim', explode(',', $portsStr)),
                static fn (string $p): bool => $p !== ''
            );

            return array_map('intval', array_values($ports));
        }

        // iptables rule: one or more "dpt:NN"
        if (preg_match_all('/dpt:(\d+)/', $line, $matches)) {
            return array_map('intval', $matches[1]);
        }

        // iptables rule with no port restriction -> all ports
        return null;
    }
}
