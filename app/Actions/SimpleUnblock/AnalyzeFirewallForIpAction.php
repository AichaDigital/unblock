<?php

declare(strict_types=1);

namespace App\Actions\SimpleUnblock;

use App\Models\Host;
use App\Services\Firewall\FirewallAnalysisResult;
use App\Services\{SshConnectionManager};
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Analyze Firewall For IP Action
 *
 * Performs firewall analysis for a specific IP on a host.
 * Wraps the FirewallAnalyzer with proper SSH session management.
 */
class AnalyzeFirewallForIpAction
{
    use AsAction;

    public function __construct(
        private readonly SshConnectionManager $sshManager,
    ) {}

    /**
     * Analyze firewall status for IP on host
     */
    public function handle(string $ip, Host $host): FirewallAnalysisResult
    {
        Log::info('Starting firewall analysis', [
            'ip' => $ip,
            'host_fqdn' => $host->fqdn,
            'host_panel' => $host->panel,
        ]);

        $session = $this->sshManager->createSession($host);

        try {
            // Get appropriate analyzer for host panel
            $analyzer = app(\App\Services\Firewall\FirewallAnalyzerFactory::class)
                ->createForHost($host);

            // Perform analysis
            $analysisResult = $analyzer->analyze($ip, $session);

            Log::info('Firewall analysis completed', [
                'ip' => $ip,
                'host_fqdn' => $host->fqdn,
                'blocked' => $analysisResult->isBlocked(),
            ]);

            return $analysisResult;

        } finally {
            $session->cleanup();
        }
    }
}
