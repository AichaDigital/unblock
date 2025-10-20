<?php

declare(strict_types=1);

namespace App\Services\Firewall;

final readonly class FirewallAnalysisResult
{
    /**
     * @param  bool  $blocked  Indica si la IP está bloqueada
     * @param  array  $logs  Registros de los análisis realizados
     * @param  array  $analysis  Datos adicionales de análisis (fuentes de bloqueo, etc.)
     */
    public function __construct(
        private bool $blocked,
        private array $logs = [],
        private array $analysis = []
    ) {}

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function getAnalysis(): array
    {
        return $this->analysis;
    }

    /**
     * Combina resultados de múltiples análisis
     */
    public static function combine(FirewallAnalysisResult ...$results): static
    {
        $isBlocked = false;
        $combinedLogs = [];
        $combinedAnalysis = [];

        foreach ($results as $result) {
            $isBlocked = $isBlocked || $result->isBlocked();
            $combinedLogs = array_merge($combinedLogs, $result->getLogs());
            $combinedAnalysis = array_merge($combinedAnalysis, $result->getAnalysis());
        }

        return new self($isBlocked, $combinedLogs, $combinedAnalysis);
    }
}
