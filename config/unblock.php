<?php

return [
    'special' => env('SPECIAL'),
    'admin_email' => env('ADMIN_EMAIL'),
    'send_admin_report_email' => env('SEND_ADMIN_REPORT_EMAIL', true),
    'attempts' => env('ATTEMPTS', 10),
    'report_expiration' => env('REPORT_EXPIRATION', 604800), // 10 days
    'cron_active' => env('CRON_ACTIVE', false),

    // Notification settings
    'notify_connection_failures' => env('NOTIFY_CONNECTION_FAILURES', true),
    'notify_critical_errors' => env('NOTIFY_CRITICAL_ERRORS', true),

    // Critical hosts that require immediate notification on failure
    'critical_hosts' => explode(',', env('CRITICAL_HOSTS', '')),

    // Error retry settings
    'max_retry_attempts' => env('MAX_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('RETRY_DELAY', 5), // seconds

    // WHMCS Integration
    'whmcs' => [
        'enabled' => env('WHMCS_SYNC_ENABLED', true),
        'schedule' => env('WHMCS_SYNC_SCHEDULE', '02:03'),
        'sync' => [
            'users' => [
                'enabled' => true,
                'create_if_not_exists' => true,
                'update_only_status' => true,  // Solo actualiza is_active basado en status WHMCS
                'preserve_internal_users' => true, // Mantener usuarios que no existen en WHMCS
            ],
            'hostings' => [
                'enabled' => true,
                'sync_with_user' => true,  // Sincronizar hostings al sincronizar usuario
            ],
            'hosts' => [
                'enabled' => false,  // No sincronizar hosts automáticamente
            ],
        ],
        'panels' => [
            'cpanel',
            'directadmin',
            'da'
        ],
        'cache' => [
            'users' => 600,      // 10 minutos
            'hosts' => 14400,    // 4 horas
        ],
    ],

    // HQ host configuration (Headquarters)
    'hq' => [
        // Prefer host_id; fallback to fqdn
        'host_id' => env('HQ_HOST_ID'),
        'fqdn' => env('HQ_HOST_FQDN', ''),
        // Temporary whitelist TTL in seconds
        'ttl' => env('HQ_WHITELIST_TTL', 7200),
    ],
];
