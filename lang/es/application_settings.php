<?php

return [
    'navigation_label' => 'Configuración de Aplicación',
    'navigation_group' => 'Sistema',
    'title' => 'Configuración de Aplicación',

    'tabs' => [
        'settings' => 'Configuración',
        'branding' => 'Marca',
        'contact' => 'Contacto',
        'legal' => 'Legal',
    ],

    'sections' => [
        'company_branding' => [
            'title' => 'Marca de la Empresa',
            'description' => 'Personaliza la apariencia de tu empresa en la aplicación',
        ],
        'support_information' => [
            'title' => 'Información de Soporte',
            'description' => 'Configura los detalles de contacto de soporte',
        ],
        'legal_links' => [
            'title' => 'Enlaces Legales',
            'description' => 'Configura las URLs de cumplimiento legal',
        ],
    ],

    'fields' => [
        'company_logo_light' => [
            'label' => 'Logo para Tema Claro',
            'helper' => 'Logo que se mostrará en modo claro (light mode). Formatos: PNG, JPG, SVG, WEBP. Máx. 2MB. Recomendado: logo oscuro o coloreado sobre fondo transparente.',
        ],
        'company_logo_dark' => [
            'label' => 'Logo para Tema Oscuro',
            'helper' => 'Logo que se mostrará en modo oscuro (dark mode). Formatos: PNG, JPG, SVG, WEBP. Máx. 2MB. Recomendado: logo claro o blanco sobre fondo transparente.',
        ],
        'company_name' => [
            'label' => 'Nombre de la Empresa',
            'helper' => 'Nombre de la empresa mostrado en la interfaz y correos electrónicos',
        ],
        'support_email' => [
            'label' => 'Email de Soporte',
            'helper' => 'Dirección de correo electrónico para soporte al cliente',
        ],
        'support_url' => [
            'label' => 'URL de Soporte',
            'helper' => 'URL a tu sistema de soporte/help desk',
        ],
        'privacy_policy_url' => [
            'label' => 'URL de Política de Privacidad',
            'helper' => 'Enlace a tu página de política de privacidad',
        ],
        'terms_url' => [
            'label' => 'URL de Términos de Servicio',
            'helper' => 'Enlace a tu página de términos de servicio',
        ],
        'data_protection_url' => [
            'label' => 'URL de Protección de Datos',
            'helper' => 'Enlace a tu información de protección de datos',
        ],
    ],

    'actions' => [
        'save' => 'Guardar Configuración',
    ],

    'notifications' => [
        'saved' => [
            'title' => 'Configuración guardada',
            'body' => 'La configuración de la aplicación se ha actualizado correctamente.',
        ],
        'error' => [
            'title' => 'Error',
            'body' => 'Error al guardar la configuración: :message',
        ],
    ],
];
