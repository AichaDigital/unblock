<?php

/**
 * Stub que simula la salida del comando 'grep IP /var/log/dovecot.log' en DirectAdmin
 *
 * Este stub muestra:
 * 1. Intentos fallidos de autenticación
 * 2. Bloqueos por exceso de intentos
 *
 * Comando simulado: grep IP /var/log/dovecot.log
 */

return [
    'dovecot' => <<<'EOD'
    May 21 {TC::TEST_TIME}:35 test dovecot: auth-worker(32026): Info: sql({TC::TEST_EMAIL},{TC::BLOCKED_IP},<{TC::TEST_EMAIL}>): unknown user
    May 21 {TC::TEST_TIME}:36 test dovecot: auth-worker(32026): Info: sql({TC::TEST_EMAIL},{TC::BLOCKED_IP},<{TC::TEST_EMAIL}>): unknown user
    May 21 {TC::TEST_TIME}:37 test dovecot: auth-worker(32026): Info: sql({TC::TEST_EMAIL},{TC::BLOCKED_IP},<{TC::TEST_EMAIL}>): unknown user
    May 21 {TC::TEST_TIME}:38 test dovecot: auth-worker(32026): Info: sql({TC::TEST_EMAIL},{TC::BLOCKED_IP},<{TC::TEST_EMAIL}>): unknown user
    May 21 {TC::TEST_TIME}:39 test dovecot: auth-worker(32026): Info: sql({TC::TEST_EMAIL},{TC::BLOCKED_IP},<{TC::TEST_EMAIL}>): unknown user
    May 21 {TC::TEST_TIME}:40 test dovecot: auth: Info: Disconnected (auth failed, 5 attempts in 5 secs): user=<{TC::TEST_EMAIL}>, method=PLAIN, rip={TC::BLOCKED_IP}, lip={TC::SERVER_IP}
    EOD,
];
