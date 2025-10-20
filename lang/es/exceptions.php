<?php

return [
    'invalid_ip' => [
        'message' => 'La dirección IP ":ip" no tiene un formato válido o no es permitida.',
        'default' => 'Formato de dirección IP inválido',
    ],
    'connection' => [
        'message' => "Atención administradores,\n\nHemos detectado problemas persistentes de conexión SSH con :host.\n\nDetalles del error:\n:message\n\nPor favor, revise el estado del servidor y sus credenciales de acceso.",
        'host_info' => 'servidor :name',
    ],
    'command' => [
        'message' => 'La ejecución del comando falló',
        'log' => 'La ejecución del comando falló: :message',
    ],
    'csf' => [
        'message' => "Atención administradores,\n\nHa ocurrido un error crítico con CSF en el servidor :host:ip.\n\nOperación: :operation\nMensaje de error: :message\n\nEste problema podría afectar la seguridad o disponibilidad del servidor.",
        'default' => 'La operación del servicio CSF falló',
        'ip_info' => ' (:ip)',
    ],
    'auth' => [
        'message' => 'Demasiados intentos fallidos de autenticación desde la IP',
        'log' => ':ip :email Demasiados intentos fallidos de autenticación desde la IP :ip',
        'notification' => 'Se han detectado múltiples intentos fallidos de autenticación. IP: :ip, para el email: :email.',
    ],
];
