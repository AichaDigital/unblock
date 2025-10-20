<?php

/**
 * Stub que simula la salida del comando 'grep IP /var/log/apache2/modsec_audit.log' en CPANEL
 *
 * Este stub muestra:
 * 1. Intentos de acceso bloqueados por ModSecurity en Apache (cPanel)
 * 2. Detalles de las reglas que causaron el bloqueo
 *
 * Comando simulado: grep IP /var/log/apache2/modsec_audit.log
 * NOTA: Este es el path CORRECTO para cPanel, NO nginx
 */

return [
    'mod_security_cpanel' => <<<'EOD'
    --e6454---A--
    [{TC::TEST_DATE}:10:33:35 +0200] {TC::BLOCKED_IP} TLSv1.2 "POST /wp-login.php HTTP/1.1" "-" "-" /wp-login.php "-" 403 289 "-" "Mozilla/5.0"
    --e6454---B--
    POST /wp-login.php HTTP/1.1
    Host: {TC::HOSTNAME}
    User-Agent: Mozilla/5.0
    Accept: */*
    Content-Length: 112
    Content-Type: application/x-www-form-urlencoded

    log={TC::ADMIN_USER}&pwd={TC::TEST_PASSWORD}&wp-submit=Log+In&redirect_to=http%3A%2F%2F{TC::HOSTNAME}%2Fwp-admin%2F&testcookie=1
    --e6454---H--
    Message: Access denied with code 403 (phase 2). Pattern match "^[\\d.:]+$" at REQUEST_HEADERS:Host [file "/etc/httpd/modsecurity.d/activated_rules/modsecurity_crs_21_protocol_anomalies.conf"] [line "47"] [id "960017"] [rev "1"] [msg "Host header is a numeric IP address"] [severity "WARNING"] [ver "OWASP_CRS/2.2.9"] [maturity "9"] [accuracy "9"]
    Action: Intercepted (phase 2)
    Stopwatch: 1621581215000000 2814 (- - -)
    Stopwatch2: 1621581215000000 2814; combined=1395, p1=285, p2=853, p3=0, p4=0, p5=257, sr=196, sw=0, l=0, gc=0
    Response-Body-Transformed: Dechunked
    Producer: ModSecurity for Apache/2.9.3 (http://www.modsecurity.org/); OWASP_CRS/2.2.9.
    Server: Apache
    --e6454---Z--

    --e6455---A--
    [{TC::TEST_DATE}:10:33:36 +0200] {TC::BLOCKED_IP} TLSv1.2 "POST /wp-login.php HTTP/1.1" "-" "-" /wp-login.php "-" 403 289 "-" "Mozilla/5.0"
    --e6455---B--
    POST /wp-login.php HTTP/1.1
    Host: {TC::HOSTNAME}
    User-Agent: Mozilla/5.0
    Accept: */*
    Content-Length: 112
    Content-Type: application/x-www-form-urlencoded

    log={TC::ADMIN_USER}&pwd={TC::TEST_PASSWORD}&wp-submit=Log+In&redirect_to=http%3A%2F%2F{TC::HOSTNAME}%2Fwp-admin%2F&testcookie=1
    --e6455---H--
    Message: Access denied with code 403 (phase 2). Pattern match "^[\\d.:]+$" at REQUEST_HEADERS:Host [file "/etc/httpd/modsecurity.d/activated_rules/modsecurity_crs_21_protocol_anomalies.conf"] [line "47"] [id "960017"] [rev "1"] [msg "Host header is a numeric IP address"] [severity "WARNING"] [ver "OWASP_CRS/2.2.9"] [maturity "9"] [accuracy "9"]
    Action: Intercepted (phase 2)
    Stopwatch: 1621581216000000 2814 (- - -)
    Stopwatch2: 1621581216000000 2814; combined=1395, p1=285, p2=853, p3=0, p4=0, p5=257, sr=196, sw=0, l=0, gc=0
    Response-Body-Transformed: Dechunked
    Producer: ModSecurity for Apache/2.9.3 (http://www.modsecurity.org/); OWASP_CRS/2.2.9.
    Server: Apache
    --e6455---Z--
    EOD,
];
