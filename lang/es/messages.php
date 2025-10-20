<?php

return [
    'hq_whitelist' => [
        'subject' => 'Detección ModSecurity HQ - IP añadida temporalmente a lista blanca',
        'greeting' => 'Hola :name,',
        'intro' => 'Se detectó actividad de ModSecurity desde la IP :ip en la plataforma central (HQ).',
        'whitelisted' => 'Hemos añadido temporalmente la IP a la lista blanca durante :hours horas para reducir fricción mientras revisamos.',
        'logs_title' => 'Logs de ModSecurity',
        'advice' => 'Revisaremos la regla para añadirla lo más estrictamente posible si es necesario.',
        'signature' => 'Un saludo, :company - Equipo de Soporte',
    ],
    'firewall' => [
        'develop_check_completed' => 'Modo desarrollo: verificación de firewall completada',
    ],
];
