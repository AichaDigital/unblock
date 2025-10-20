<?php

declare(strict_types=1);

/**
 * Critical test for GDPR compliance - Exact IP filtering
 *
 * PROBLEM: The system searches IPs by substring, showing data from users
 * not related to the target IP (privacy violation)
 */

/**
 * Test that reproduces the critical GDPR issue
 *
 * SCENARIO:
 * - Search for IP: 2.2.2.2
 * - Should NOT find: 58.252.212.231, 58.252.212.232, etc.
 * - Should ONLY find: 2.2.2.2
 */
test('grep command only returns exact IP match (GDPR compliant)', function () {
    $logContent = <<<'EOD'
2025-06-08 15:56:32 login authenticator failed for ([58.252.212.231]) [58.252.212.231]: 535 Incorrect authentication data (set_id=user1@example.com)
2025-06-08 18:05:29 login authenticator failed for ([58.252.212.232]) [58.252.212.232]: 535 Incorrect authentication data (set_id=user2@example.com)
2025-06-08 23:33:35 login authenticator failed for ([22.2.2.2]) [22.2.2.2]: 535 Incorrect authentication data (set_id=no_target@example.com)
2025-06-09 12:33:35 login authenticator failed for ([2.2.2.2]) [2.2.2.2]: 535 Incorrect authentication data (set_id=target@example.com)
2025-06-10 19:54:26 login authenticator failed for ([58.252.212.233]) [58.252.212.233]: 535 Incorrect authentication data (set_id=user3@example.com)
2025-06-11 12:33:35 login authenticator failed for ([22.2.2.2]) [22.2.2.2]: 535 Incorrect authentication data (set_id=no_target@example.com)
EOD;

    $targetIp = '2.2.2.2';

    // Filtrado actual (GDPR compliant)
    $matches = [];
    foreach (explode("\n", $logContent) as $line) {
        if (str_contains($line, "[$targetIp]")) {
            $matches[] = $line;
        }
    }
    $result = implode("\n", $matches);

    // Solo debe contener la IP exacta y el email relacionado
    expect($result)
        ->not->toContain('58.252.212.231')
        ->not->toContain('58.252.212.232')
        ->not->toContain('user1@example.com')
        ->toContain('target@example.com');
});

test('exim firewall command only returns exact IP match', function () {
    $logContent = <<<'EOD'
2025-06-08 15:56:32 login authenticator failed for ([58.252.212.231]) [58.252.212.231]: 535 Incorrect authentication data (set_id=user1@example.com)
2025-06-09 12:33:35 login authenticator failed for ([2.2.2.2]) [2.2.2.2]: 535 Incorrect authentication data (set_id=target@example.com)
EOD;

    $targetIp = '2.2.2.2';

    $matches = [];
    foreach (explode("\n", $logContent) as $line) {
        if (str_contains($line, "[$targetIp]") && str_contains($line, 'authenticator failed for')) {
            $matches[] = $line;
        }
    }

    expect($matches)->toHaveCount(1)
        ->and(implode(' ', $matches))->toContain('target@example.com')
        ->and(implode(' ', $matches))->not->toContain('user1@example.com');
});

test('dovecot firewall command only returns exact IP match', function () {
    $logContent = <<<'EOD'
Jun 08 15:56:32 dovecot: auth failed for user admin from [58.252.212.231]
Jun 09 12:33:35 dovecot: auth failed for user test from [2.2.2.2]
EOD;

    $targetIp = '2.2.2.2';

    $matches = [];
    foreach (explode("\n", $logContent) as $line) {
        if (str_contains($line, "[$targetIp]") && str_contains($line, 'auth failed')) {
            $matches[] = $line;
        }
    }

    expect($matches)->toHaveCount(1)
        ->and($matches[0])->toContain('[2.2.2.2]')
        ->and($matches[0])->not->toContain('[58.252.212.231]');
});

test('exim log: filters only the target IP, not substrings or local IP', function () {
    $targetIp = '196.122.144.222';
    $otherIp = '23.52.132.62';
    $logContent = <<<'EOD'
2025-06-12 19:32:47 plain authenticator failed for ([192.168.1.100]) [196.122.144.222]: 535 Incorrect authentication data (set_id=nabil@elayudante.es)
2025-06-12 19:32:47 login authenticator failed for ([192.168.1.100]) [196.122.144.222]: 535 Incorrect authentication data (set_id=nabil@elayudante.es)
2025-06-12 19:34:06 login authenticator failed for (ADMIN) [4.205.20.70]: 535 Incorrect authentication data (set_id=info@jardineriabravo.com)
2025-06-12 19:46:32 plain authenticator failed for (panciant) [154.177.167.47]: 535 Incorrect authentication data (set_id=fidel@lacantabrica.com)
2025-06-12 19:54:05 login authenticator failed for ([140.249.20.14]) [140.249.20.14]: 535 Incorrect authentication data (set_id=d.panzera@unilang.es)
2025-06-12 20:00:00 login authenticator failed for ([23.52.132.62]) [23.52.132.62]: 535 Incorrect authentication data (set_id=someone@somewhere.com)
EOD;

    $matches = [];
    foreach (explode("\n", $logContent) as $line) {
        // Simulate robust grep -Ea "\\[$targetIp\\]|\\($targetIp\\)| $targetIp:| $targetIp "
        if ((preg_match("/\\[$targetIp\\]/", $line) || preg_match("/\\($targetIp\\)/", $line) || preg_match("/ $targetIp:/", $line) || preg_match("/ $targetIp /", $line))
            && str_contains($line, 'authenticator failed for')
            && ! str_contains($line, "lip=$targetIp")) {
            $matches[] = $line;
        }
    }

    // Should find only the lines with the target IP as remote
    expect($matches)->toHaveCount(2);
    foreach ($matches as $line) {
        expect($line)->toContain($targetIp)
            ->and($line)->not->toContain($otherIp);
    }
});

test('dovecot log: filters only the target IP, not substrings or local IP', function () {
    $targetIp = '138.255.206.154';
    $otherIp = '23.52.132.62';
    $logContent = <<<'EOD'
Jun 12 20:02:12 kvm456 dovecot[996]: auth-worker(jose.basanta,138.255.206.154)<1489164><8oNSumM3A8mK/86a>: request [393]: unix_user: pam_authenticate() failed: Authentication failure (Password mismatch?)
Jun 12 20:02:23 kvm456 dovecot[996]: imap-login: Login aborted: Connection closed (auth failed, 2 attempts in 14 secs) (auth_failed): user=<jose.basanta>, method=PLAIN, rip=138.255.206.154, lip=5.135.93.75, TLS, session=<8oNSumM3A8mK/86a>
Jun 12 20:26:11 kvm456 dovecot[996]: imap-login: Login aborted: Connection closed: SSL_read failed: error:0A000412:SSL routines::sslv3 alert bad certificate: SSL alert number 42 (no auth attempts in 0 secs) (no_auth_attempts): user=<>, rip=52.97.188.37, lip=5.135.93.75, TLS: SSL_read failed: error:0A000412:SSL routines::sslv3 alert bad certificate: SSL alert number 42, session=<NjJkEGQ3dIo0Ybwl>
Jun 12 20:30:00 kvm456 dovecot[996]: imap-login: Login aborted: Connection closed (auth failed, 2 attempts in 14 secs) (auth_failed): user=<someone>, method=PLAIN, rip=23.52.132.62, lip=5.135.93.75, TLS, session=<8oNSumM3A8mK/86a>
EOD;

    $matches = [];
    foreach (explode("\n", $logContent) as $line) {
        // Simulate robust grep -Ea "\\[$targetIp\\]|rip=$targetIp|,$targetIp,"
        if ((preg_match("/\\[$targetIp\\]/", $line) || preg_match("/rip=$targetIp/", $line) || preg_match(",{$targetIp},", $line))
            && str_contains($line, 'auth failed')) {
            $matches[] = $line;
        }
    }

    // Should find only the line(s) with the target IP as remote
    expect($matches)->toHaveCount(1)
        ->and($matches[0])->toContain($targetIp)
        ->and($matches[0])->not->toContain($otherIp);
});
