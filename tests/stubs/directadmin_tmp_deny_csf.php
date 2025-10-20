<?php

/**
 * Stub que simula la salida del comando 'grep IP /etc/csf/csf.deny.* | grep temp' en DirectAdmin
 *
 * Comando simulado: grep IP /etc/csf/csf.deny.* | grep temp
 */

return [
    'tmp_deny_csf' => <<<'EOD'
    /etc/csf/csf.deny.{TC::TEMP_FILE_PREFIX}: {TC::BLOCKED_IP} # lfd: (temp) {TC::BLOCKED_IP} (XX/Unknown/-) - {TC::TEST_FUTURE_DATE}
    /etc/csf/csf.deny.{TC::TEMP_FILE_PREFIX_2}: {TC::BLOCKED_IP} # lfd: (temp) {TC::BLOCKED_IP} (XX/Unknown/-) - {TC::TEST_FUTURE_DATE}
    /etc/csf/csf.deny.{TC::TEMP_FILE_PREFIX_3}: {TC::BLOCKED_IP} # lfd: (temp) {TC::BLOCKED_IP} (XX/Unknown/-) - {TC::TEST_FUTURE_DATE}
    EOD,
];
