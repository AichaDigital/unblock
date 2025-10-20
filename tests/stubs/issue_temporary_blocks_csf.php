<?php

/**
 * Stub que reproduce el bug específico reportado
 *
 * Este stub muestra el problema donde:
 * 1. Hay una regla DENYIN que bloquea la IP
 * 2. IPSET dice "No matches found"
 * 3. Temporary Blocks muestra que la IP está bloqueada temporalmente
 *
 * El problema es que el verificador ve "No matches found" y asume que no está bloqueada
 * cuando en realidad SÍ está bloqueada según DENYIN y Temporary Blocks
 *
 * Comando simulado: csf -g 2.2.2.2
 */

return [
    'csf' => <<<'EOD'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

filter DENYIN           189      0     0 DROP       all  --  !lo    *       2.2.2.2              0.0.0.0/0

IPSET: No matches found for 2.2.2.2

Temporary Blocks: IP:2.2.2.2 Port: Dir:in TTL:3600 (Manually added: 2.2.2.2 (FR/France/-))
EOD,
];
