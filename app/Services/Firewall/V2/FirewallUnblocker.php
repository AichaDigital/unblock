<?php

declare(strict_types=1);

namespace App\Services\Firewall\V2;

use App\Enums\PanelType;
use App\Exceptions\{CommandExecutionException, CsfServiceException};
use App\Models\Host;
use App\Services\{FirewallService, SshConnectionManager};
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Firewall Unblocker V2 - Single Responsibility Pattern
 *
 * Responsabilidad única: Desbloquear IPs de firewall
 * Compatible con la lógica actual pero separada
 */
class FirewallUnblocker
{
    public function __construct(
        private FirewallService $firewallService,
        private SshConnectionManager $sshManager
    ) {}

    /**
     * Unblock an IP address from CSF firewall
     * (Mantiene la lógica actual de CSF)
     */
    public function unblockFromCsf(string $ipAddress, Host $host): array
    {
        $session = $this->sshManager->createSession($host);

        try {
            $results = [];

            // 1. Check if IP is in permanent deny list (csf.deny)
            $denyCheck = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), 'csf_deny_check', $ipAddress);
            if (! empty(trim($denyCheck))) {
                // IP is in permanent deny list - remove it
                $unblockPermanentOutput = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), 'unblock_permanent', $ipAddress);
                $results['unblock_permanent'] = [
                    'command' => "csf -dr {$ipAddress}",
                    'output' => $unblockPermanentOutput,
                    'success' => true,
                ];
                Log::info('Removed IP from permanent deny list', ['ip' => $ipAddress, 'host' => $host->fqdn]);
            }

            // 2. Check if IP is in temporary deny list (csf.tempip)
            $tempDenyCheck = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), 'csf_tempip_check', $ipAddress);
            if (! empty(trim($tempDenyCheck))) {
                // IP is in temporary deny list - remove it
                $unblockTemporaryOutput = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), 'unblock_temporary', $ipAddress);
                $results['unblock_temporary'] = [
                    'command' => "csf -tr {$ipAddress}",
                    'output' => $unblockTemporaryOutput,
                    'success' => true,
                ];
                Log::info('Removed IP from temporary deny list', ['ip' => $ipAddress, 'host' => $host->fqdn]);
            }

            // 3. Agregar a whitelist por 24 horas (csf -ta IP TTL) - siempre después de remover denies
            $whitelistOutput = $this->firewallService->checkProblems($host, $session->getSshKeyPath(), 'whitelist', $ipAddress);
            $results['whitelist'] = [
                'command' => "csf -ta {$ipAddress} ".config('unblock.whitelist_ttl', 86400),
                'output' => $whitelistOutput,
                'success' => true,
            ];

            $operations = array_keys(array_filter($results, fn ($key) => $key !== 'whitelist', ARRAY_FILTER_USE_KEY));
            if (! in_array('whitelist', $operations)) {
                $operations[] = 'whitelist';
            }

            Log::info('CSF unblock operations completed successfully', [
                'ip' => $ipAddress,
                'host' => $host->fqdn,
                'operations' => $operations,
            ]);

            return $results;

        } catch (Exception $e) {
            Log::error('CSF unblock operations failed', [
                'ip' => $ipAddress,
                'host' => $host->fqdn,
                'error' => $e->getMessage(),
            ]);

            throw new CsfServiceException(
                "Failed to unblock IP {$ipAddress} from CSF: ".$e->getMessage(),
                previous: $e
            );
        } finally {
            $session->cleanup();
        }
    }

    /**
     * Remove IP from BFM blacklist (DirectAdmin only)
     * (Responsabilidad separada del desbloqueo CSF)
     */
    public function removeFromBfmBlacklist(string $ipAddress, Host $host): array
    {
        if ($host->panel !== PanelType::DIRECTADMIN) {
            return ['skipped' => true, 'reason' => 'Host is not DirectAdmin'];
        }

        $session = $this->sshManager->createSession($host);

        try {
            $results = [];

            // Remover IP de la blacklist BFM
            $bfmCommand = $this->buildBfmRemovalCommand($ipAddress);
            $bfmOutput = $session->execute($bfmCommand);

            $results['removal'] = [
                'command' => $bfmCommand,
                'output' => $bfmOutput,
                'success' => true,
            ];

            // Verificar que se removió correctamente
            $verifyCommand = "cat /usr/local/directadmin/data/admin/ip_blacklist | grep -w '{$ipAddress}' || echo 'IP not found in blacklist'";
            $verifyOutput = $session->execute($verifyCommand);

            $results['verification'] = [
                'command' => $verifyCommand,
                'output' => $verifyOutput,
                'removed' => str_contains($verifyOutput, 'IP not found in blacklist'),
            ];

            Log::info('BFM removal operations completed successfully', [
                'ip' => $ipAddress,
                'host' => $host->fqdn,
                'removed' => $results['verification']['removed'],
            ]);

            return $results;

        } catch (Exception $e) {
            Log::error('BFM removal operations failed', [
                'ip' => $ipAddress,
                'host' => $host->fqdn,
                'error' => $e->getMessage(),
            ]);

            throw new CommandExecutionException(
                "Failed to remove IP {$ipAddress} from BFM blacklist: ".$e->getMessage(),
                previous: $e
            );
        } finally {
            $session->cleanup();
        }
    }

    /**
     * Perform complete unblock operation (CSF + BFM for DirectAdmin)
     */
    public function performCompleteUnblock(string $ipAddress, Host $host): array
    {
        $results = [];

        // 1. Unblock from CSF (always)
        $results['csf'] = $this->unblockFromCsf($ipAddress, $host);

        // 2. Remove from BFM (DirectAdmin only)
        if ($host->panel === PanelType::DIRECTADMIN) {
            $results['bfm'] = $this->removeFromBfmBlacklist($ipAddress, $host);
        }

        return $results;
    }

    /**
     * Build the command to remove IP from BFM blacklist
     * (Mantiene la lógica actual pero separada)
     */
    private function buildBfmRemovalCommand(string $ipAddress): string
    {
        // Escapar la IP para uso en comando sed
        $escapedIp = preg_quote($ipAddress, '/');

        // Remover líneas que empiecen con la IP seguida de espacio o fin de línea
        return "sed -i '/^{$escapedIp}\\(\\s\\|$\\)/d' /usr/local/directadmin/data/admin/ip_blacklist";
    }

    /**
     * Get the status of unblock operations
     */
    public function getUnblockStatus(array $results): array
    {
        $status = [
            'overall_success' => true,
            'csf_success' => false,
            'bfm_success' => true, // Default true for non-DirectAdmin
            'operations_performed' => [],
        ];

        // Check CSF status
        if (isset($results['csf'])) {
            $status['csf_success'] =
                ($results['csf']['unblock']['success'] ?? false) &&
                ($results['csf']['whitelist']['success'] ?? false);

            $status['operations_performed'][] = 'csf_unblock';
            $status['operations_performed'][] = 'csf_whitelist';
        }

        // Check BFM status
        if (isset($results['bfm'])) {
            $status['bfm_success'] = $results['bfm']['removal']['success'] ?? false;
            $status['operations_performed'][] = 'bfm_removal';
        }

        $status['overall_success'] = $status['csf_success'] && $status['bfm_success'];

        return $status;
    }

    /**
     * Validate if an IP is properly formatted before unblocking
     */
    public function validateIpAddress(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP) !== false;
    }
}
