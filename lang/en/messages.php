<?php

return [
    'hq_whitelist' => [
        'subject' => 'HQ ModSecurity detection - IP temporarily whitelisted',
        'greeting' => 'Hello :name,',
        'intro' => 'ModSecurity activity was detected from IP :ip on the HQ platform.',
        'whitelisted' => 'The IP has been temporarily whitelisted for :hours hours to reduce friction while we review.',
        'logs_title' => 'ModSecurity logs',
        'advice' => 'We will review the rule to whitelist as strictly as possible if needed.',
        'signature' => 'Best regards, :company - Support Team',
    ],
    'firewall' => [
        'develop_check_completed' => 'Development mode: firewall check completed',
    ],
];
