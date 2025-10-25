<?php

return [
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
