<?php

/**
 * Stub: `csf -g <ip>` where a temporary ALLOW and a temporary BLOCK coexist.
 *
 * Reproduces the AID-171 incident state: the unblock flow added a full-port
 * Temporary Allow (csf -ta IP 86400) on top of a LF_SMTPAUTH Temporary Block
 * that was never cleared. The ALLOWIN rule (chain position 1, ACCEPT) wins the
 * traffic, so the IP is NOT effectively blocked — but a naive `str_contains
 * DENYIN` check flags it as blocked, causing the report loop.
 *
 * IP uses RFC 5737 documentation range (no real client data).
 *
 * Simulated command: csf -g 203.0.113.27
 */

return [
    'csf' => <<<'EOD'
Table  Chain            num   pkts bytes target     prot opt in     out     source               destination

filter ALLOWIN          1      293 45379 ACCEPT     0    --  !lo    *       203.0.113.27         0.0.0.0/0

filter ALLOWOUT         1      383  181K ACCEPT     0    --  *      !lo     0.0.0.0/0            203.0.113.27

filter DENYIN           195      0     0 DROP       6    --  !lo    *       203.0.113.27         0.0.0.0/0            tcp dpt:25
filter DENYIN           196      0     0 DROP       6    --  !lo    *       203.0.113.27         0.0.0.0/0            tcp dpt:465
filter DENYIN           197     11   656 DROP       6    --  !lo    *       203.0.113.27         0.0.0.0/0            tcp dpt:587

IPSET: No matches found for 203.0.113.27

Temporary Allows: IP:203.0.113.27 Port: Dir:inout TTL:86400 (Manually added: 203.0.113.27 (ES/Spain/-))

Temporary Blocks: IP:203.0.113.27 Port:25,465,587 Dir:in TTL:3600 (lfd - (smtpauth) Failed SMTP AUTH login from 203.0.113.27 (ES/Spain/-): 5 in the last 3600 secs)
EOD,
];
