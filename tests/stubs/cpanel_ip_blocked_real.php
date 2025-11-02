<?php

/**
 * Stub REAL de servidor20.xerintel.com
 *
 * Comando ejecutado: ssh root@servidor20.xerintel.com -p 51514 "csf -g 62.57.40.116"
 * Fecha: 2025-11-01
 * IP: 62.57.40.116 (BLOQUEADA)
 * Razón: Failed IMAP login (10 intentos en 3600 segundos)
 */

return [
    'cpanel' => <<<'EOD'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

filter DENYIN           10       0     0 DROP       all  --  !lo    *       62.57.40.116         0.0.0.0/0

filter DENYOUT          10       0     0 LOGDROPOUT  all  --  *      !lo     0.0.0.0/0            62.57.40.116


ip6tables:

Table  Chain            num   pkts bytes target     prot opt in     out     source               destination
No matches found for 62.57.40.116 in ip6tables

csf.deny: 62.57.40.116 # lfd: (imapd) Failed IMAP login from 62.57.40.116 (62.57.40.116.dyn.user.ono.com): 10 in the last 3600 secs - Mon Aug  4 16:59:13 2025
EOD,
];
