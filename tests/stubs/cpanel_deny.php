<?php

/**
 * Stub que simula la salida del comando 'csf -g IP' en cPanel
 *
 * Este stub muestra:
 * 1. La tabla de iptables con la regla de bloqueo
 * 2. La entrada en csf.deny con el motivo del bloqueo
 *
 * Comando simulado: csf -g 192.0.2.123
 */

return [
    'cpanel' => <<<'EOD'
    Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

    filter DENYIN           1353   938 48776 DROP       all  --  !lo    *       192.0.2.123          0.0.0.0/0

    csf.deny: 192.0.2.123 # lfd: (PERMBLOCK) 192.0.2.123 (XX/Unknown/-) has had more than 4 temp blocks in the last 86400 secs - Thu May 22 00:21:11 2025
    EOD,
];
