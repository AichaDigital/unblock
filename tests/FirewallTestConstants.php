<?php

declare(strict_types=1);

namespace Tests;

/**
 * Constants used in firewall-related tests
 */
class FirewallTestConstants
{
    // Server configuration
    public const HOSTNAME = 'test.example.com';

    public const SERVER_IP = '192.168.1.100';

    public const SSH_PORT = 22;

    public const ADMIN_USER = 'admin';

    public const SSH_KEY = 'test_key';

    // Test data
    public const TEST_IP = '192.168.1.100';

    public const TEST_HOST_FQDN = 'host.example.com';

    public const TEST_HOST_IP = '192.168.1.200';

    public const TEST_SSH_PORT = 2222;

    public const TEST_ADMIN_USER = 'testadmin';

    public const TEST_SSH_KEY = 'test_key';

    public const TEST_BLOCKED_IP = '10.0.0.1';

    public const BLOCKED_IP = '192.0.2.123';

    // Service patterns
    public const CSF_BLOCK_PATTERN = 'csf.deny:';

    public const EXIM_BLOCK_PATTERN = 'authentication failure';

    public const DOVECOT_BLOCK_PATTERN = 'auth failed';

    public const MOD_SECURITY_PATTERN = '[msg "Access denied"]';

    // Error messages
    public const INVALID_IP_ERROR = 'IP address is not valid';

    public const CONNECTION_ERROR = 'Could not connect to server';

    public const COMMAND_ERROR = 'Command execution failed';

    /**
     * Test firewall command
     */
    public const TEST_COMMAND = 'csf -dr';

    /**
     * Test error message
     */
    public const TEST_ERROR = 'Test error message';

    /**
     * Test success message
     */
    public const TEST_SUCCESS = 'Test success message';

    /**
     * Test log data
     */
    public const TEST_LOG_DATA = [
        'csf' => [
            'blocked' => true,
            'reason' => 'Test block reason',
            'details' => 'Test block details',
        ],
    ];
}
