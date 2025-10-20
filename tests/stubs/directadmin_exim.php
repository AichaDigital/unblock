<?php

/**
 * Stub que simula la salida del comando 'grep IP /var/log/exim/mainlog' en DirectAdmin
 *
 * Este stub muestra:
 * 1. Intentos fallidos de autenticación
 * 2. Bloqueos por exceso de intentos
 *
 * Comando simulado: grep IP /var/log/exim/mainlog
 */

return [
    'exim_directadmin' => <<<'EOD'
    {TC::TEST_DATE} {TC::TEST_TIME}:35 dovecot_login authenticator failed for ({TC::HOSTNAME}) [{TC::BLOCKED_IP}]: 535 Incorrect authentication data
    {TC::TEST_DATE} {TC::TEST_TIME}:36 dovecot_login authenticator failed for ({TC::HOSTNAME}) [{TC::BLOCKED_IP}]: 535 Incorrect authentication data
    {TC::TEST_DATE} {TC::TEST_TIME}:37 dovecot_login authenticator failed for ({TC::HOSTNAME}) [{TC::BLOCKED_IP}]: 535 Incorrect authentication data
    {TC::TEST_DATE} {TC::TEST_TIME}:38 dovecot_login authenticator failed for ({TC::HOSTNAME}) [{TC::BLOCKED_IP}]: 535 Incorrect authentication data
    {TC::TEST_DATE} {TC::TEST_TIME}:39 dovecot_login authenticator failed for ({TC::HOSTNAME}) [{TC::BLOCKED_IP}]: 535 Incorrect authentication data
    {TC::TEST_DATE} {TC::TEST_TIME}:40 H=({TC::HOSTNAME}) [{TC::BLOCKED_IP}] F=<{TC::TEST_EMAIL}> temporarily rejected RCPT <{TC::ADMIN_EMAIL}>: Too many authentication failures
    EOD,
];
