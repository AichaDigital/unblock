<?php

use Tests\Stubs\FirewallTestConstants as TC;

/**
 * Stub que simula la salida del comando 'grep IP /etc/csf/csf.deny' en cPanel
 *
 * Comando simulado: grep IP /etc/csf/csf.deny
 */

return [
    "in_deny_file" => <<<EOD
    {TC::BLOCKED_IP} # lfd: (PERMBLOCK) {TC::BLOCKED_IP} (XX/Unknown/-) has had more than 4 temp blocks in the last 86400 secs - {TC::TEST_FUTURE_DATE}
    EOD,
];
