<?php

return [
    'invalid_ip' => [
        'message' => 'The IP address ":ip" has an invalid format or is not allowed.',
        'default' => 'Invalid IP address format',
    ],
    'connection' => [
        'message' => "Attention administrators,\n\nWe have detected persistent SSH connection problems with :host.\n\nError details:\n:message\n\nPlease check the server status and access credentials.",
        'host_info' => 'server :name',
    ],
    'command' => [
        'message' => 'Command execution failed',
        'log' => 'Command execution failed: :message',
    ],
    'csf' => [
        'message' => "Attention administrators,\n\nA critical CSF error has occurred on server :host:ip.\n\nOperation: :operation\nError message: :message\n\nThis issue could affect server security or availability.",
        'default' => 'CSF service operation failed',
        'ip_info' => ' (:ip)',
    ],
    'auth' => [
        'message' => 'Excessive failed authentication attempts from IP',
        'log' => ':ip :email Excessive failed authentication attempts from IP :ip',
        'notification' => 'Multiple failed authentication attempts detected. IP: :ip, for email: :email.',
    ],
];
