<?php

declare(strict_types=1);

namespace App\Services\Firewall;

use App\Models\{BfmWhitelistEntry, Host};
use App\Services\FirewallService;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * DirectAdmin Firewall Analyzer
 *
 * Analyzes firewall logs and performs actions for DirectAdmin panel servers.
 * Handles CSF, Exim, Dovecot and ModSecurity services.
 *
 * Implements FirewallAnalyzerInterface behavior
 */
readonly class DirectAdminFirewallAnalyzer implements FirewallAnalyzerInterface
{
    private const PANEL_TYPE = 'directadmin';

    /**
     * Service definitions with commands and patterns
     */
    private const SERVICES = [
        'csf' => [
            'name' => 'CSF Firewall',
            'patterns' => ['csf.deny:', 'DROP', 'DENYIN', 'Temporary Blocks'],
            'log_key' => 'csf',
        ],
        'exim_directadmin' => [
            'name' => 'Exim Mail Server',
            'patterns' => ['dovecot_login authenticator failed', 'Incorrect authentication data'],
            'log_key' => 'exim',
        ],
        'dovecot_directadmin' => [
            'name' => 'Dovecot Mail Server',
            'patterns' => ['auth failed', 'Authentication failure'],
            'log_key' => 'dovecot',
        ],
        'mod_security_da' => [
            'name' => 'ModSecurity',
            'patterns' => ['Custom WAF Rules:', 'Rule:', 'IP:', 'URI:'],
            'log_key' => 'mod_security',
        ],
    ];

    /**
     * Default service configuration
     */
    private const DEFAULT_SERVICE_CHECKS = [
        'csf' => true,
        'csf_deny_check' => true,
        'csf_tempip_check' => true,
        'exim_directadmin' => true,
        'dovecot_directadmin' => true,
        'mod_security_da' => true,
        'da_bfm_check' => true,
    ];

    /**
     * @var array<string, bool> Service check configuration
     */
    private array $serviceChecks;

    /**
     * @param  FirewallService  $firewallService  Service for checking firewall problems
     * @param  Host  $host  Host to analyze
     * @param  array<string, bool>|null  $serviceChecks  Optional service checks configuration
     */
    public function __construct(
        private FirewallService $firewallService,
        private Host $host,
        ?array $serviceChecks = null
    ) {
        $this->serviceChecks = $serviceChecks ?? self::DEFAULT_SERVICE_CHECKS;
    }

    /**
     * Analyze firewall logs for a specific IP address
     *
     * @param  string  $ipAddress  IP address to analyze
     * @param  mixed  $session  SSH session for command execution (object) or SSH key path (string) for compatibility
     * @return FirewallAnalysisResult Analysis result with block status and logs
     */
    public function analyze(string $ipAddress, mixed $session): FirewallAnalysisResult
    {
        // Extraer SSH key path del session (compatible con ambas implementaciones)
        $sshKeyName = method_exists($session, 'getSshKeyPath')
            ? $session->getSshKeyPath()
            : (string) $session; // fallback para compatibilidad

        $results = [];
        $logs = [];
        $wasBlocked = false;
        $bfmBlocked = false; // Track BFM blocking separately
        $blockSources = []; // Tracking de fuentes de bloqueo para debug

        try {
            // STEP 1: Check primary CSF command (csf -g IP)
            if ($this->serviceChecks['csf']) {
                $csfOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'csf', $ipAddress);
                $logs['csf'] = $csfOutput;
                $csfResult = $this->analyzeServiceOutput('csf', $csfOutput);
                $results[] = $csfResult;

                if ($csfResult->isBlocked()) {
                    $wasBlocked = true;
                    $blockSources[] = 'csf_primary';
                    Log::debug("IP {$ipAddress} blocked in CSF (primary check)", [
                        'host' => $this->host->fqdn,
                        'output_sample' => substr($csfOutput, 0, 200).'...',
                    ]);
                }
            }

            // STEP 2: If primary CSF check didn't find blocks, perform deep CSF analysis
            if (! $wasBlocked) {
                // Check csf.deny file (permanent blocks)
                if ($this->serviceChecks['csf_deny_check'] ?? false) {
                    $csfDenyOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'csf_deny_check', $ipAddress);
                    if (! empty(trim($csfDenyOutput))) {
                        $logs['csf_deny'] = $csfDenyOutput;
                        $csfDenyResult = $this->analyzeCsfDenyOutput($csfDenyOutput);
                        $results[] = $csfDenyResult;
                        if ($csfDenyResult->isBlocked()) {
                            $wasBlocked = true;
                            $blockSources[] = 'csf_deny';
                            Log::debug("IP {$ipAddress} found in CSF deny file", [
                                'host' => $this->host->fqdn,
                                'output' => $csfDenyOutput,
                            ]);
                        }
                    }
                }

                // Check csf.tempip file (temporary blocks)
                if (! $wasBlocked && ($this->serviceChecks['csf_tempip_check'] ?? false)) {
                    $csfTempOutput = $this->firewallService->checkProblems($this->host, $sshKeyName, 'csf_tempip_check', $ipAddress);
                    if (! empty(trim($csfTempOutput))) {
                        $logs['csf_tempip'] = $csfTempOutput;
                        $csfTempResult = $this->analyzeCsfTempOutput($csfTempOutput);
                        $results[] = $csfTempResult;
                        if ($csfTempResult->isBlocked()) {
                            $wasBlocked = true;
                            $blockSources[] = 'csf_tempip';
                            Log::debug("IP {$ipAddress} found in CSF tempip file", [
                                'host' => $this->host->fqdn,
                                'output' => $csfTempOutput,
                            ]);
                        }
                    }
                }
            }

            // STEP 3: Check DirectAdmin BFM blacklist (ALWAYS - independent of CSF status)
            if ($this->serviceChecks['da_bfm_check'] ?? false) {
                $bfmResult = $this->checkAndRemoveFromBfmBlacklist($ipAddress, $sshKeyName);
                if ($bfmResult) {
                    $logs['da_bfm'] = $bfmResult->getLogs()['da_bfm'] ?? '';
                    $results[] = $bfmResult;
                    $bfmBlocked = $bfmResult->isBlocked();
                    if ($bfmBlocked) {
                        $wasBlocked = true;
                        $blockSources[] = 'da_bfm';
                        Log::debug("IP {$ipAddress} blocked in DirectAdmin BFM", [
                            'host' => $this->host->fqdn,
                            'output' => $logs['da_bfm'],
                        ]);
                    }
                }
            }

            // STEP 4: Check service logs ONLY if blocked in CSF or BFM
            // STEP 5: Check additional services for CONTEXT ONLY (not for blocking determination)
            foreach (self::SERVICES as $service => $config) {
                if ($service === 'csf') {
                    continue; // Already checked
                }

                if ($this->serviceChecks[$service] ?? false) {
                    $output = $this->firewallService->checkProblems($this->host, $sshKeyName, $service, $ipAddress);
                    $logs[$config['log_key']] = $output;

                    // CRITICAL FIX: Service logs provide CONTEXT only, not blocking status
                    // Only ModSecurity can indicate actual blocks from these services
                    if ($service === 'mod_security_da') {
                        $serviceResult = $this->analyzeServiceOutput($service, $output);
                        $results[] = $serviceResult;

                        // Only ModSecurity logs can determine blocking status
                        if ($serviceResult->isBlocked()) {
                            $wasBlocked = true;
                            $blockSources[] = $config['log_key'];
                        }
                    } else {
                        // For Dovecot and Exim: create result but DON'T mark as blocked
                        // These logs show failed attempts, not active blocks
                        $contextResult = new FirewallAnalysisResult(false, [$config['log_key'] => $output]);
                        $results[] = $contextResult;
                    }
                }
            }

            // STEP 6: Execute blocking actions only for CSF-related blocks
            if ($wasBlocked) {

                // Only unblock/whitelist for CSF-related blocks, not for ModSecurity
                if (in_array('csf_primary', $blockSources) || in_array('csf_deny', $blockSources) || in_array('csf_tempip', $blockSources)) {
                    Log::debug("Unblocking IP {$ipAddress} from CSF", ['host' => $this->host->fqdn]);
                    $this->unblock($ipAddress, $sshKeyName);
                    $this->addToWhitelist($ipAddress, $sshKeyName);
                }
            }

            // Log resultado final para debug
            Log::debug("Firewall analysis completed for {$ipAddress}", [
                'host' => $this->host->fqdn,
                'was_blocked' => $wasBlocked,
                'block_sources' => $blockSources,
                'service_checks' => $this->serviceChecks,
            ]);

            // GARANTIZAR ESTRUCTURA JSON COMPLETA - SIEMPRE incluir todas las claves
            $completeLogsStructure = [
                'csf' => $logs['csf'] ?? '',
                'csf_deny' => $logs['csf_deny'] ?? '',
                'csf_tempip' => $logs['csf_tempip'] ?? '',
                'da_bfm' => $logs['da_bfm'] ?? '',
                'exim' => $logs['exim'] ?? '',
                'dovecot' => $logs['dovecot'] ?? '',
                'mod_security' => $logs['mod_security'] ?? '',
            ];

            // Combinar todos los resultados con estructura completa garantizada
            $finalResult = FirewallAnalysisResult::combine(...$results);

            // CORRECCION CRITICA: El estado de bloqueo debe coincidir con las fuentes detectadas
            $actualBlockStatus = $wasBlocked || count($blockSources) > 0;

            $finalResult = new FirewallAnalysisResult(
                $actualBlockStatus,
                $completeLogsStructure,
                ['block_sources' => $blockSources] // Agregar fuentes de bloqueo al análisis
            );

            return $finalResult;
        } catch (Throwable $e) {
            Log::error(__('messages.firewall.logs.error_checking', [
                'unity' => self::PANEL_TYPE,
                'server' => $this->host->fqdn,
                'error' => $e->getMessage(),
            ]), [
                'exception' => $e,
                'ip' => $ipAddress,
                'host' => $this->host->toArray(),
            ]);

            throw $e;
        }
    }

    /**
     * Unblock an IP from the firewall
     *
     * @param  string  $ip  IP address to unblock
     * @param  string  $sshKeyName  SSH key name for authentication
     */
    public function unblock(string $ip, string $sshKeyName): void
    {
        $this->firewallService->checkProblems($this->host, $sshKeyName, 'unblock', $ip);
    }

    /**
     * Add IP to whitelist for 24 hours
     *
     * @param  string  $ip  IP address to whitelist
     * @param  string  $sshKeyName  SSH key name for authentication
     */
    private function addToWhitelist(string $ip, string $sshKeyName): void
    {
        $this->firewallService->checkProblems($this->host, $sshKeyName, 'whitelist', $ip);
    }

    /**
     * Check if this analyzer supports the given panel type
     *
     * @param  string  $panelType  Panel type to check
     * @return bool True if supported, false otherwise
     */
    public function supports(string $panelType): bool
    {
        return $panelType === self::PANEL_TYPE;
    }

    /**
     * Create new instance with updated service checks
     *
     * @param  array<string, bool>  $services  Service check configuration to merge
     * @return self New instance with updated configuration
     */
    public function withServiceChecks(array $services): self
    {
        return new self($this->firewallService, $this->host, array_merge($this->serviceChecks, $services));
    }

    /**
     * Analyze service output for blocks using defined patterns
     *
     * @param  string  $service  Service name to analyze
     * @param  string  $output  Command output to analyze
     * @return FirewallAnalysisResult Analysis result with block status and logs
     */
    private function analyzeServiceOutput(string $service, string $output): FirewallAnalysisResult
    {
        if (! isset(self::SERVICES[$service])) {
            // Since we removed 'csf_specials', we need to handle the case where it might still be passed
            // This can be removed after confirming no other part of the code calls this with the old key
            if ($service === 'csf_specials') {
                return new FirewallAnalysisResult(false, []);
            }
            throw new InvalidArgumentException("Unknown service: {$service}");
        }

        $config = self::SERVICES[$service];
        $isBlocked = false;

        // Buscar cualquier forma de DENY primero (solución para el problema específico)
        if ($service === 'csf') {
            // CORRECCION: La IP está bloqueada si aparece en csf.deny o en chain_DENY
            // INDEPENDIENTEMENTE de si hay "matches found" en iptables
            // NUEVO: También detectar "Temporary Blocks" y DENYIN patterns
            if (str_contains($output, 'csf.deny:') ||
                str_contains($output, 'chain_DENY') ||
                str_contains($output, 'Temporary Blocks:') ||
                str_contains($output, 'DENYIN') ||
                (str_contains($output, 'DENY') && ! str_contains($output, 'No matches found for') && ! str_contains($output, 'No blocked:'))) {
                $isBlocked = true;
            }
        } elseif ($service === 'mod_security_da') {
            // Para ModSecurity: cualquier contenido procesado indica bloqueo
            // El JSON ya fue procesado y formateado por el servicio
            $isBlocked = ! empty(trim($output));
        } else {
            // Seguir usando los patrones existentes como respaldo
            foreach ($config['patterns'] as $pattern) {
                if (str_contains($output, $pattern)) {
                    $isBlocked = true;
                    break;
                }
            }
        }

        return new FirewallAnalysisResult($isBlocked, [$config['log_key'] => $output]);
    }

    /**
     * Analyze CSF deny file output (permanent blocks)
     */
    private function analyzeCsfDenyOutput(string $output): FirewallAnalysisResult
    {
        // Para csf.deny, la existencia de contenido (después de grep) ya indica bloqueo
        // No necesitamos palabras clave adicionales aquí
        $isBlocked = ! empty(trim($output));

        return new FirewallAnalysisResult($isBlocked, ['csf_deny' => $output]);
    }

    /**
     * Analyze CSF temporary IP file output (temporary blocks)
     */
    private function analyzeCsfTempOutput(string $output): FirewallAnalysisResult
    {
        // Para csf.tempip, la existencia de contenido (después de grep) ya indica bloqueo
        // No necesitamos palabras clave adicionales aquí
        $isBlocked = ! empty(trim($output));

        return new FirewallAnalysisResult($isBlocked, ['csf_tempip' => $output]);
    }

    /**
     * Filter BFM output to find exact IP matches only
     *
     * @param  string  $output  Raw BFM file content
     * @param  string  $targetIp  IP to search for exactly
     * @return string Filtered content with only exact IP matches
     */
    private function filterBfmOutput(string $output, string $targetIp): string
    {
        $lines = explode("\n", $output);
        $validLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Split line by spaces to get IP and other data
            $parts = preg_split('/\s+/', $line);
            $lineIp = $parts[0] ?? '';

            // Only include lines where first part is exactly our target IP
            if ($lineIp === $targetIp) {
                $validLines[] = $line;
            }
        }

        return implode("\n", $validLines);
    }

    /**
     * Check DirectAdmin BFM (Brute Force Manager) blacklist and remove IP if found
     *
     * This method checks the DirectAdmin internal blacklist (/usr/local/directadmin/data/admin/ip_blacklist)
     * and removes the IP if found. This blacklist is maintained by DirectAdmin's BFM system for IPs
     * that show persistent abusive behavior, even if they are whitelisted in CSF.
     *
     * When an IP is found in the blacklist:
     * 1. Remove from blacklist
     * 2. Add to whitelist for the configured TTL period
     * 3. Track in database for automatic removal after TTL expires
     *
     * @param  string  $ip  IP address to check and remove
     * @param  string  $sshKeyName  SSH key name for authentication
     * @return FirewallAnalysisResult|null Analysis result or null if not found in blacklist
     */
    private function checkAndRemoveFromBfmBlacklist(string $ip, string $sshKeyName): ?FirewallAnalysisResult
    {
        try {
            // IMPORTANTE: Usar checkProblems del FirewallService que ya implementa multiplexing
            // en lugar de crear nuevas conexiones SSH
            $output = $this->firewallService->checkProblems($this->host, $sshKeyName, 'da_bfm_check', $ip);

            if (empty(trim($output))) {
                return null;
            }

            // Filtrar el output para asegurar que solo tenemos coincidencias exactas de IP
            $filteredOutput = $this->filterBfmOutput($output, $ip);

            if (empty(trim($filteredOutput))) {
                return null;
            }

            // IP found in BFM blacklist - proceed to remove it and add to whitelist

            // 1. Remove IP from BFM blacklist
            $this->firewallService->checkProblems($this->host, $sshKeyName, 'da_bfm_remove', $ip);

            // 2. Add IP to BFM whitelist
            $this->firewallService->checkProblems($this->host, $sshKeyName, 'da_bfm_whitelist_add', $ip);

            // 3. Track in database for automatic removal after TTL
            $ttl = (int) (config('unblock.hq.ttl') ?? 7200); // Default 2 hours
            BfmWhitelistEntry::create([
                'host_id' => $this->host->id,
                'ip_address' => $ip,
                'added_at' => now(),
                'expires_at' => now()->addSeconds($ttl),
                'notes' => 'Auto-added from BFM blacklist removal',
            ]);

            // Create result with BFM-specific information
            $logs = [
                'da_bfm' => $filteredOutput,
                'da_bfm_action' => __('firewall.bfm.removed'),
                'da_bfm_whitelist_added' => __('firewall.bfm.whitelist_added'),
                'da_bfm_whitelist_ttl' => gmdate('H:i:s', $ttl),
                'da_bfm_warning' => __('firewall.bfm.warning_message'),
                'da_bfm_path' => '/usr/local/directadmin/data/admin/ip_blacklist',
            ];

            return new FirewallAnalysisResult(true, $logs);

        } catch (Exception $e) {
            // Return error result
            return new FirewallAnalysisResult(false, [
                'da_bfm' => '',
                'da_bfm_error' => __('firewall.bfm.removal_failed'),
            ]);
        }
    }
}
