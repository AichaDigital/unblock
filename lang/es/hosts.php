<?php

return [
    'ssh_keys' => [
        'title' => 'Claves SSH',
        'private_key' => 'Clave Privada',
        'public_key' => 'Clave Pública',
        'private_key_help' => 'Se encripta automáticamente al guardar. Puedes generar claves con el botón de arriba o pegar claves existentes.',
        'public_key_help' => 'Se encripta automáticamente al guardar. Esta clave debe añadirse al servidor remoto.',
        'generate' => 'Generar Claves SSH',
        'generate_confirm_title' => 'Generar Claves SSH',
        'generate_confirm_description' => 'Esto generará un nuevo par de claves SSH (ed25519) para este host. Si ya existen claves, serán reemplazadas. ¿Estás seguro?',
        'generate_confirm' => 'Sí, generar claves',
        'generated_success' => 'Claves SSH generadas',
        'generated_success_body' => 'Las claves SSH han sido generadas correctamente. Recuerda copiar la clave pública al servidor remoto.',
        'generation_failed' => 'Error al generar claves',
        'fqdn_required' => 'Por favor, completa el campo FQDN antes de generar las claves SSH',
        'inline_help_title' => '¿No tienes claves SSH?',
        'inline_help_description' => 'Puedes generar un nuevo par de claves automáticamente. Solo necesitas completar el campo FQDN primero.',
        'or_paste_existing' => 'o pega claves existentes abajo',
        // Legacy keys for backwards compatibility
        'generate_action' => 'Generar Claves SSH',
        'generate_confirmation_title' => 'Generar Claves SSH',
        'generate_confirmation_description' => 'Esto generará un nuevo par de claves SSH (ed25519) para este host. Si ya existen claves, serán reemplazadas. ¿Estás seguro?',
        'generate_confirmation_submit' => 'Sí, generar claves',
        'generate_success_title' => 'Claves SSH generadas correctamente',
        'generate_success_body' => 'Las claves SSH han sido generadas y guardadas. La clave pública es: :public_key',
        'generate_error_title' => 'Error al generar claves SSH',
        'generate_error_body' => ':message',
    ],
];
