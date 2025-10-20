<?php

/**
 * Stub que simula la salida del comando 'csf -t' en DirectAdmin
 *
 * Este stub muestra:
 * 1. Bloqueos temporales activos
 * 2. Tiempo restante de bloqueo
 *
 * Comando simulado: csf -t
 */

return [
    'tmp_csf' => <<<'EOD'
    Chain            num   pkts bytes target     prot opt in     out     source               destination
    DENYIN           1353   938 48776 DROP       all  --  !lo    *       {TC::BLOCKED_IP}          0.0.0.0/0

    Temporary Blocks: IP:{TC::BLOCKED_IP} Port: Dir:in TTL:3600 (lfd - (sshd) Failed SSH login from {TC::BLOCKED_IP} (XX/Unknown/-): 5 in the last 300 secs)
    EOD,
];
