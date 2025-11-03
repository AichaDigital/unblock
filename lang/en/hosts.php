<?php

return [
    'ssh_keys' => [
        'title' => 'SSH Keys',
        'private_key' => 'Private Key',
        'public_key' => 'Public Key',
        'private_key_help' => 'Automatically encrypted when saved. You can generate keys with the button above or paste existing keys.',
        'public_key_help' => 'Automatically encrypted when saved. This key must be added to the remote server.',
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
];
