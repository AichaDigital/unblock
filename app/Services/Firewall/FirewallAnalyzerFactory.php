<?php

namespace App\Services\Firewall;

use App\Models\Host;
use App\Services\FirewallService;
use InvalidArgumentException;

class FirewallAnalyzerFactory
{
    /**
     * @var array<string, class-string<FirewallAnalyzerInterface>>
     */
    private array $analyzers = [
        'directadmin' => DirectAdminFirewallAnalyzer::class,
        'cpanel' => CpanelFirewallAnalyzer::class,
    ];

    public function __construct(
        private FirewallService $firewallService
    ) {}

    /**
     * Registra un nuevo analizador para un tipo de panel
     *
     * @param  class-string<FirewallAnalyzerInterface>  $analyzerClass
     */
    public function registerAnalyzer(string $panelType, string $analyzerClass): void
    {
        if (! is_subclass_of($analyzerClass, FirewallAnalyzerInterface::class)) {
            throw new InvalidArgumentException(
                'Analyzer class must implement FirewallAnalyzerInterface'
            );
        }

        $this->analyzers[$panelType] = $analyzerClass;
    }

    /**
     * Crea un analizador para un host específico
     *
     * @throws InvalidArgumentException
     */
    public function createForHost(Host $host): FirewallAnalyzerInterface
    {
        if (! isset($this->analyzers[$host->panel])) {
            throw new InvalidArgumentException(
                "No analyzer available for panel type: {$host->panel}"
            );
        }

        $analyzerClass = $this->analyzers[$host->panel];

        return new $analyzerClass($this->firewallService, $host);
    }
}
