<?php

/**
 * Stub que reproduce el problema específico reportado con la IP 5.102.173.71
 *
 * Este stub muestra el problema donde:
 * 1. Hay múltiples reglas DENYIN que bloquean la IP
 * 2. IPSET dice "No matches found"
 * 3. El sistema debería detectar que está bloqueada por las reglas DENYIN
 *    pero actualmente falla porque busca de forma muy rígida
 *
 * Comando simulado: csf -g 5.102.173.71
 */

return [
    'csf' => <<<'EOD'

Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

filter DENYIN           256      0     0 DROP       tcp  --  !lo    *       5.102.173.71         0.0.0.0/0            multiport dports 80,443
filter DENYIN           295      0     0 DROP       tcp  --  !lo    *       5.102.173.71         0.0.0.0/0            multiport dports 80,443
filter DENYIN           310      0     0 DROP       tcp  --  !lo    *       5.102.173.71         0.0.0.0/0            multiport dports 80,443

IPSET: No matches found for 5.102.173.71


ip6tables:

Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
No matches found for 5.102.173.71 in ip6tables

EOD,
];
