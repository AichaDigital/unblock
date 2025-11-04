<?php

return [
    'ssh_keys' => [
        'title' => 'SSH Keys',
        'private_key' => 'Private Key',
        'public_key' => 'Public Key',
        'private_key_help' => 'Automatically encrypted when saved. You can generate keys after creating the host.',
        'public_key_help' => 'Automatically encrypted when saved. This key must be added to the remote server.',
        'generation_notice_title' => 'SSH Key Generation',
        'generation_notice_body' => 'Save the host first. Then you can generate SSH keys automatically from the edit page using the "Generate SSH Keys" button.',
        'generate' => 'Generate SSH Keys',
        'generate_confirm_title' => 'Generate SSH Keys',
        'generate_confirm_description' => 'This will generate a new SSH key pair (ed25519) for this host. If keys already exist, they will be replaced. Are you sure?',
        'generate_confirm' => 'Yes, generate keys',
        'generated_success' => 'SSH Keys Generated',
        'generated_success_body' => 'SSH keys have been generated successfully. Remember to copy the public key to the remote server.',
        'generation_failed' => 'Key Generation Failed',
        'fqdn_required' => 'Please complete the FQDN field before generating SSH keys',
        'inline_help_title' => 'Don\'t have SSH keys?',
        'inline_help_description' => 'You can generate a new key pair automatically. Just complete the FQDN field first.',
        'or_paste_existing' => 'or paste existing keys below',
        // Legacy keys for backwards compatibility
        'generate_action' => 'Generate SSH Keys',
        'generate_confirmation_title' => 'Generate SSH Keys',
        'generate_confirmation_description' => 'This will generate a new SSH key pair (ed25519) for this host. If keys already exist, they will be replaced. Are you sure?',
        'generate_confirmation_submit' => 'Yes, generate keys',
        'generate_success_title' => 'SSH Keys Generated Successfully',
        'generate_success_body' => 'SSH keys have been generated and saved. Public key is: :public_key',
        'generate_error_title' => 'SSH Key Generation Error',
        'generate_error_body' => ':message',
    ],

    'actions' => [
        'test_connection' => 'Test Connection',
        'test_connection_modal_title' => 'Test SSH Connection',
        'test_connection_modal_description' => 'This will test the SSH connection to the host using the configured keys. The test will execute the "whoami" command on the remote server.',
        'test_connection_submit' => 'Test Now',
    ],

    'notifications' => [
        'test_success_title' => '✅ Connection Successful',
        'test_success_body' => 'SSH connection to host :fqdn is working correctly.',
        'test_failed_title' => '❌ Connection Failed',
        'test_failed_body' => 'Could not connect to host. Please consult system administrator for shell debugging.',
        'test_error_title' => '⚠️ Test Error',
        'test_error_body' => 'Error executing test. Please check system logs.',
    ],
];
