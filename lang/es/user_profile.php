<?php

return [
    'navigation_label' => 'Mi Perfil',
    'navigation_group' => 'Cuenta',
    'title' => 'Mi Perfil',

    'fields' => [
        'preferred_locale' => [
            'label' => 'Idioma Preferido',
            'helper' => 'Selecciona tu idioma preferido para el panel de administración',
        ],
    ],

    'actions' => [
        'save' => 'Guardar Preferencias',
    ],

    'notifications' => [
        'saved' => [
            'title' => 'Preferencia de idioma actualizada',
            'body' => 'Tu preferencia de idioma se ha guardado correctamente.',
        ],
    ],

    'info' => [
        'note' => 'Nota: La página se actualizará automáticamente después de guardar para aplicar el nuevo idioma.',
    ],
];
