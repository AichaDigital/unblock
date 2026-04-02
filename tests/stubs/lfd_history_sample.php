<?php

/**
 * Stub que simula la salida del comando 'grep IP /var/log/lfd.log' para historial LFD
 *
 * Este stub muestra:
 * 1. Bloqueos temporales por LFD (login failures)
 * 2. Desbloqueos automáticos (TTL expirado)
 * 3. Bloqueos permanentes por BFM
 *
 * Comando simulado: grep IP /var/log/lfd.log /var/log/lfd.log.1 2>/dev/null | tail -20 || true
 */

return [
    'lfd_history_with_blocks' => <<<'EOD'
/var/log/lfd.log:Feb 14 08:23:15 server lfd[1234]: (smtpauth) 192.0.2.123 has had 5 failed logins in the last 3600 secs - *Blocked in csf* [LF_SMTPAUTH]
/var/log/lfd.log:Feb 14 08:23:15 server lfd[1234]: *Blocked* 192.0.2.123 - 5 login failures in the last 3600 seconds from SMTP
/var/log/lfd.log:Feb 14 10:23:15 server lfd[1234]: 192.0.2.123 temporary ban removed - TTL expired
/var/log/lfd.log:Feb 15 14:10:22 server lfd[1234]: (pop3d) 192.0.2.123 has had 10 failed logins in the last 3600 secs - *Blocked in csf* [LF_POP3D]
/var/log/lfd.log:Feb 15 14:10:22 server lfd[1234]: *Blocked* 192.0.2.123 - 10 login failures in the last 3600 seconds from POP3
/var/log/lfd.log.1:Feb 10 06:45:33 server lfd[1234]: (imapd) 192.0.2.123 has had 3 failed logins in the last 3600 secs - *Blocked in csf* [LF_IMAPD]
EOD,

    'lfd_history_empty' => '',

    'lfd_history_unblocks_only' => <<<'EOD'
/var/log/lfd.log:Feb 14 10:23:15 server lfd[1234]: 192.0.2.123 temporary ban removed - TTL expired
/var/log/lfd.log:Feb 15 16:10:22 server lfd[1234]: 192.0.2.123 temporary ban removed - TTL expired
EOD,
];
