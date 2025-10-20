<?php

namespace App\Services\Firewall;

interface FirewallAnalyzerInterface
{
    /**
     * Analyze firewall logs for a specific IP address
     *
     * @param  string  $ipAddress  The IP address to analyze
     * @param  mixed  $session  SSH session for command execution (object) or SSH key path (string) for compatibility
     * @return FirewallAnalysisResult Analysis result with blocking status and logs
     */
    public function analyze(string $ipAddress, mixed $session): FirewallAnalysisResult;

    /**
     * Desbloquea una IP del firewall
     */
    public function unblock(string $ip, string $sshKeyName): void;

    /**
     * Check if this analyzer supports the given panel type
     *
     * @param  string  $panelType  Panel type (e.g., 'directadmin', 'cpanel')
     * @return bool True if this analyzer supports the panel type
     */
    public function supports(string $panelType): bool;
}
