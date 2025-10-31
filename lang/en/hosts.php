<?php

return [
    'ssh_keys' => [
        'title' => 'SSH Keys',
        'private_key' => 'Private Key',
        'public_key' => 'Public Key',
        'private_key_help' => 'Automatically encrypted on save. Use the "Generate SSH Keys" button on the edit page to generate a new key pair.',
        'public_key_help' => 'Automatically encrypted on save. This key must be added to the remote server.',
        'generate_action' => 'Generate SSH Keys',
        'generate_confirmation_title' => 'Generate SSH Keys',
        'generate_confirmation_description' => 'This will generate a new SSH key pair (ed25519) for this host. If keys already exist, they will be replaced. Are you sure?',
        'generate_confirmation_submit' => 'Yes, generate keys',
        'generate_success_title' => 'SSH keys generated successfully',
        'generate_success_body' => 'The SSH keys have been generated and saved. The public key is: :public_key',
        'generate_error_title' => 'Failed to generate SSH keys',
        'generate_error_body' => ':message',
    ],
];
