<?php

return [
    // Service (Unified Dashboard)
    'service' => [
        'title' => 'Servicio de Firewall',
        'admin_title' => 'Gestión de Firewall (Admin)',
        'description' => 'Consulta y gestiona el estado de IPs en el firewall',
    ],

    // Search & Selection
    'search' => [
        'select_target' => 'Selecciona dominio o servidor',
        'placeholder' => 'Buscar dominio o servidor...',
        'no_results' => 'No se encontraron resultados',
    ],

    // Groups
    'groups' => [
        'hostings' => 'Dominios',
        'servers' => 'Servidores',
    ],

    // IP Address
    'ip' => [
        'label' => 'Dirección IP',
    ],

    // Actions
    'actions' => [
        'check' => 'Consultar Firewall',
        'cancel' => 'Cancelar',
    ],

    // Help System
    'help' => [
        'need_help' => '¿Necesitas ayuda?',
        'domain_explanation' => [
            'title' => '¿Qué es un dominio?',
            'description' => 'Un dominio es el nombre de tu sitio web (ej: miempresa.com)',
            'examples' => 'Ejemplos: tudominio.com, blog.ejemplo.es',
            'note' => 'Selecciona el dominio donde tienes el problema de acceso',
        ],
        'server_explanation' => [
            'title' => '¿Qué es un servidor?',
            'description' => 'Un servidor es la máquina donde están alojados tus sitios web',
            'examples' => 'Ejemplos: servidor1.hosting.com, vps-madrid-01',
            'note' => 'Selecciona el servidor si gestionas múltiples dominios en él',
        ],
        'ip_explanation' => [
            'title' => '¿Qué dirección IP debo usar?',
            'description' => 'La IP es la dirección desde la que intentas acceder a tu sitio',
            'what_is_ip' => '¿Qué es una dirección IP?',
            'why_default' => 'Por defecto mostramos tu IP actual, que es desde donde estás navegando ahora',
            'current_ip' => 'Tu IP actual: Desde este dispositivo y red',
            'problem_ip' => 'IP con problemas: Si el bloqueo es desde otro lugar, cámbiala',
            'example' => 'Ejemplo: Si trabajas desde casa pero el bloqueo es en la oficina, usa la IP de la oficina',
            'note' => '⚠️ Importante: Usa la IP desde donde NO puedes acceder, no desde donde SÍ funciona',
        ],
        'ip_detection' => [
            'title' => '¿Cómo saber mi dirección IP?',
            'current_device' => 'Automático: Ya detectamos tu IP actual',
            'how_to_find' => 'Manual: Visita {url} desde el dispositivo bloqueado',
            'common_issues' => 'Si usas VPN o proxy, la IP puede cambiar',
        ],
    ],

    // Validation
    'validation' => [
        'selection_required' => 'Debes seleccionar un dominio o servidor',
        'ip_required' => 'La dirección IP es obligatoria',
        'invalid_ip' => 'La dirección IP no es válida',
    ],

    // Errors
    'errors' => [
        'invalid_target' => 'El objetivo seleccionado no es válido',
        'process_error' => 'Error al procesar la solicitud',
    ],

    // Notifications
    'notifications' => [
        'query_sent_title' => 'Consulta enviada',
        'query_sent_async' => 'La consulta se está procesando. Recibirás una notificación cuando termine.',
        'error_title' => 'Error',
    ],

    'bfm' => [
        'removed' => 'IP eliminada de la lista negra BFM de DirectAdmin',
        'whitelist_added' => 'IP añadida a la lista blanca BFM de DirectAdmin',
        'warning_message' => 'DirectAdmin BFM tenía esta IP en su lista negra interna. Ha sido eliminada y añadida a la lista blanca por el período de gracia configurado.',
        'removal_failed' => 'Fallo al eliminar IP de la lista negra BFM de DirectAdmin',
    ],

    // Abuse Incidents Resource
    'abuse_incidents' => [
        'navigation_label' => 'Incidentes de Abuso',
        'navigation_group' => 'Seguridad Simple Unblock',
        'singular' => 'Incidente de Abuso',
        'plural' => 'Incidentes de Abuso',

        // Sections
        'incident_information' => 'Información del Incidente',
        'target_information' => 'Información del Objetivo',
        'details' => 'Detalles',
        'resolution' => 'Resolución',

        // Fields
        'incident_type' => 'Tipo de Incidente',
        'severity' => 'Severidad',
        'ip_address' => 'Dirección IP',
        'email_hash' => 'Hash de Email',
        'email_hash_helper' => 'Hash SHA-256 (cumple GDPR)',
        'domain' => 'Dominio',
        'description' => 'Descripción',
        'metadata' => 'Metadatos (JSON)',
        'resolved_at' => 'Resuelto el',
        'occurred_at' => 'Ocurrió el',

        // Incident Types
        'types' => [
            'rate_limit_exceeded' => 'Límite de Tasa Excedido',
            'ip_spoofing_attempt' => 'Intento de Suplantación de IP',
            'otp_bruteforce' => 'Fuerza Bruta OTP',
            'honeypot_triggered' => 'Honeypot Activado',
            'invalid_otp_attempts' => 'Intentos de OTP Inválidos',
            'ip_mismatch' => 'Discrepancia de IP',
            'suspicious_pattern' => 'Patrón Sospechoso',
            'other' => 'Otro',
        ],

        // Severity Levels
        'severity_levels' => [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ],

        // Table Columns
        'type' => 'Tipo',
        'ip' => 'IP',
        'resolved' => 'Resuelto',

        // Filters
        'status' => 'Estado',
        'all_incidents' => 'Todos los incidentes',
        'resolved' => 'Resueltos',
        'unresolved' => 'Sin resolver',
        'from' => 'Desde',
        'until' => 'Hasta',

        // Actions
        'resolve' => 'Resolver',
        'unresolve' => 'Marcar como no resuelto',
        'resolve_heading' => 'Resolver Incidente',
        'resolve_description' => '¿Marcar este incidente como resuelto?',
        'mark_as_resolved' => 'Marcar como Resueltos',

        // Notifications
        'incident_resolved' => 'Incidente resuelto',
        'incident_unresolved' => 'Incidente marcado como no resuelto',
        'incidents_resolved' => 'Incidentes resueltos',
    ],

    // IP Reputation Resource
    'ip_reputation' => [
        'navigation_label' => 'Reputación de IP',
        'navigation_group' => 'Seguridad Simple Unblock',
        'singular' => 'Reputación de IP',
        'plural' => 'Reputación de IP',

        // Sections
        'ip_information' => 'Información de IP',
        'reputation_statistics' => 'Reputación y Estadísticas',
        'notes' => 'Notas',

        // Fields
        'ip_address' => 'Dirección IP',
        'subnet' => 'Subred',
        'reputation_score' => 'Puntuación de Reputación',
        'total_requests' => 'Peticiones Totales',
        'failed_requests' => 'Peticiones Fallidas',
        'blocked_count' => 'Bloqueos',
        'last_seen' => 'Última Vez Visto',
        'first_seen' => 'Primera Vez Visto',
        'admin_notes' => 'Notas del Administrador',
        'admin_notes_helper' => 'Añadir notas de investigación o contexto sobre esta dirección IP.',

        // Table Columns
        'score' => 'Puntuación',
        'total' => 'Total',
        'failed' => 'Fallidas',
        'blocked' => 'Bloqueadas',
        'success_rate' => 'Tasa de Éxito %',

        // Filters
        'reputation' => 'Reputación',
        'high_reputation' => 'Alta (80-100)',
        'medium_reputation' => 'Media (50-79)',
        'low_reputation' => 'Baja (0-49)',
        'subnet_search' => 'Búsqueda de Subred',
        'from' => 'Desde',
        'until' => 'Hasta',

        // Actions
        'incidents' => 'Incidentes',
    ],

    // Email Reputation Resource
    'email_reputation' => [
        'navigation_label' => 'Reputación de Email',
        'navigation_group' => 'Seguridad Simple Unblock',
        'singular' => 'Reputación de Email',
        'plural' => 'Reputación de Email',

        // Sections
        'email_information' => 'Información de Email',
        'reputation_statistics' => 'Reputación y Estadísticas',
        'notes' => 'Notas',

        // Fields
        'email_hash' => 'Hash de Email (SHA-256)',
        'email_hash_helper' => 'Cumple GDPR - almacena hash, no texto plano',
        'email_domain' => 'Dominio de Email',
        'reputation_score' => 'Puntuación de Reputación',
        'total_requests' => 'Peticiones Totales',
        'verified_requests' => 'Peticiones Verificadas',
        'failed_requests' => 'Peticiones Fallidas',
        'last_seen' => 'Última Vez Visto',
        'first_seen' => 'Primera Vez Visto',
        'admin_notes' => 'Notas del Administrador',
        'admin_notes_helper' => 'Añadir notas de investigación o contexto sobre esta dirección de email.',

        // Table Columns
        'domain' => 'Dominio',
        'score' => 'Puntuación',
        'total' => 'Total',
        'verified' => 'Verificadas',
        'failed' => 'Fallidas',
        'verification_rate' => 'Tasa de Verificación %',

        // Filters
        'reputation' => 'Reputación',
        'high_reputation' => 'Alta (80-100)',
        'medium_reputation' => 'Media (50-79)',
        'low_reputation' => 'Baja (0-49)',
        'from' => 'Desde',
        'until' => 'Hasta',

        // Actions
        'incidents' => 'Incidentes',

        // Messages
        'hash_copied' => '¡Hash completo copiado!',
    ],

    // Reports Resource
    'reports' => [
        'navigation_label' => 'Informes de Firewall',
        'title' => 'Informes de Firewall',
        'user' => 'Usuario',
        'host' => 'Host',
        'ip' => 'IP',
        'created_at' => 'Creado el',
        'last_read' => 'Última lectura',
        'unassigned' => 'Sin asignar',
        'logs' => 'Registros',
        'analysis' => 'Análisis',
        'created_from' => 'Desde',
        'created_until' => 'Hasta',
        'empty_state' => 'No hay informes de firewall todavía.',
    ],
];
