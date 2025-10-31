<?php

return [
    'ssh_keys' => [
        'title' => 'Claves SSH',
        'private_key' => 'Clave Privada',
        'public_key' => 'Clave Pública',
        'private_key_help' => 'Se encripta automáticamente al guardar. Usa el botón "Generate SSH Keys" en la página de edición para generar un nuevo par de claves.',
        'public_key_help' => 'Se encripta automáticamente al guardar. Esta clave debe añadirse al servidor remoto.',
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
