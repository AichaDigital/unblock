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

    // Simple Unblock Mode (v1.2.0+)
    'simple_mode' => [
        'enabled' => env('UNBLOCK_SIMPLE_MODE', false),

        // Multi-vector rate limiting (defense against botnets)
        'throttle_per_minute' => env('UNBLOCK_SIMPLE_THROTTLE_PER_MINUTE', 3),
        'throttle_email_per_hour' => env('UNBLOCK_SIMPLE_THROTTLE_EMAIL_PER_HOUR', 5),
        'throttle_domain_per_hour' => env('UNBLOCK_SIMPLE_THROTTLE_DOMAIN_PER_HOUR', 10),
        'throttle_subnet_per_hour' => env('UNBLOCK_SIMPLE_THROTTLE_SUBNET_PER_HOUR', 20),
        'throttle_global_per_hour' => env('UNBLOCK_SIMPLE_THROTTLE_GLOBAL_PER_HOUR', 500),

        'block_duration_minutes' => env('UNBLOCK_SIMPLE_BLOCK_DURATION', 15),
        'strict_match' => env('UNBLOCK_SIMPLE_STRICT_MATCH', true),
        'silent_log' => env('UNBLOCK_SIMPLE_SILENT_LOG', true),

        // OTP Settings (v1.2.0+)
        'otp_enabled' => env('UNBLOCK_SIMPLE_OTP_ENABLED', true),
        'otp_expires_minutes' => env('UNBLOCK_SIMPLE_OTP_EXPIRES', 5),
        'otp_length' => env('UNBLOCK_SIMPLE_OTP_LENGTH', 6),
    ],
];
