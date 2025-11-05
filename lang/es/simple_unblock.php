<?php

declare(strict_types=1);

return [
    'title' => 'Desbloqueo Rápido de IP',
    'subtitle' => 'Introduce tu IP y dominio para verificar y desbloquear si es necesario',

    'ip_label' => 'Dirección IP',
    'ip_placeholder' => '192.168.1.1',
    'domain_label' => 'Dominio',
    'domain_placeholder' => 'ejemplo.com (sin www)',
    'email_label' => 'Email',
    'email_placeholder' => 'tu@email.com',

    // Flujo de 2 pasos (v1.2.0)
    'step1_label' => 'Solicitar',
    'step2_label' => 'Verificar',
    'send_otp_button' => 'Enviar Código de Verificación',
    'otp_sent' => '¡Código de verificación enviado! Revisa tu email.',
    'otp_label' => 'Código de Verificación',
    'otp_help' => 'Introduce el código de 6 dígitos enviado a tu email',
    'verify_button' => 'Verificar y Desbloquear',
    'back_button' => 'Volver',
    'sending' => 'Enviando...',
    'verifying' => 'Verificando...',

    'submit_button' => 'Verificar y Desbloquear',
    'processing' => 'Procesando...',
    'process_button' => 'Procesar Desbloqueo',

    'processing_message' => 'Tu solicitud está siendo procesada. Recibirás un email con los resultados en breve.',
    'success_message' => '¡Solicitud procesada! Recibirás un email con los detalles del sistema y el posible desbloqueo.',
    'request_submitted' => '¡Solicitud enviada correctamente! Recibirás un email con el resultado del análisis en unos minutos. Revisa tu bandeja de entrada.',
    'error_message' => 'Ha ocurrido un error. Por favor, intenta de nuevo más tarde.',
    'cooldown_active' => 'Por favor espera :seconds segundos antes de enviar otra solicitud.',

    'rate_limit_exceeded' => 'Has excedido el límite de solicitudes. Por favor, espera :seconds segundos.',

    // Validación de dominio (Fase 3 - v1.5.0)
    'domain_not_found' => 'Dominio no encontrado en nuestro sistema. Por favor, verifica que lo hayas introducido correctamente.',
    'account_suspended' => 'La cuenta asociada a este dominio está actualmente suspendida. Por favor, contacta con soporte.',
    'account_deleted' => 'La cuenta asociada a este dominio ya no existe. Por favor, contacta con soporte.',

    'generic_response' => 'Hemos recibido tu solicitud y la estamos procesando.',
    'no_information_found' => 'No se encontró información para los datos proporcionados.',
    'help_text' => 'Verifica tu identidad con el código recibido para procesar el desbloqueo de tu IP.',
    'otp_verified_ready' => '¡Código verificado correctamente!',

    'mail' => [
        'success_subject' => 'IP Desbloqueada - :domain',
        'admin_alert_subject' => 'Alerta: Intento Simple Unblock - :domain',

        // Traducciones del cuerpo del email
        'admin_copy_badge' => '[COPIA ADMIN]',
        'title_success' => 'IP Desbloqueada Exitosamente',
        'title_admin_alert' => 'Alerta de Intento de Desbloqueo Simple',

        'admin_info_intro' => 'Esta es una copia administrativa de la notificación al usuario.',
        'admin_info_user_email' => 'Email del Usuario',
        'admin_info_domain' => 'Dominio',
        'admin_info_ip' => 'Dirección IP',
        'admin_info_host' => 'Servidor',
        'admin_info_report_id' => 'ID del Reporte',

        'greeting' => 'Hola,',
        'success_message' => 'Tu dirección IP <strong>:ip</strong> para el dominio <strong>:domain</strong> ha sido analizada y desbloqueada exitosamente.',

        'analysis_results_title' => 'Resultados del Análisis',
        'analysis_status_label' => 'Estado',
        'analysis_status_value' => 'La IP estaba bloqueada y ha sido desbloqueada',
        'analysis_server_label' => 'Servidor',
        'analysis_timestamp_label' => 'Fecha y Hora',

        'firewall_logs_title' => 'Registros del Firewall',
        'firewall_logs_intro' => 'Los siguientes servicios tenían bloqueos:',
        'firewall_logs_multiple' => 'Múltiples entradas',

        'block_details_title' => 'Detalles del Bloqueo',
        'block_reason' => 'Motivo',
        'block_attempts' => 'Intentos',
        'block_location' => 'Ubicación',
        'block_since' => 'Bloqueado desde',
        'block_timeframe' => 'en las últimas :seconds segundos',

        'next_steps_title' => '¿Qué Sigue?',
        'next_steps_message' => 'Tu IP debería tener acceso al servidor ahora. Si continúas experimentando problemas, por favor contacta con nuestro equipo de soporte.',

        'admin_notes_title' => 'Notas del Administrador',
        'admin_note_anonymous' => 'Esta fue una solicitud anónima de desbloqueo simple',
        'admin_note_email' => 'Email proporcionado: :email',
        'admin_note_domain_validated' => 'Dominio validado en los registros del servidor',
        'admin_note_ip_confirmed' => 'Se confirmó que la IP estaba bloqueada antes de desbloquear',

        'footer_thanks' => 'Gracias,',
        'footer_company' => ':company',
        'footer_security_notice' => 'Si no solicitaste este desbloqueo, por favor contacta con :supportEmail inmediatamente.',

        // Traducciones específicas para alertas de administrador
        'admin_alert' => [
            'reason_label' => 'Motivo',
            'unknown' => 'Desconocido',
            'multiple_hosts' => 'Múltiples servidores verificados',
            'request_details_title' => 'Detalles de la Solicitud',
            'reason_details_title' => 'Detalles del Motivo',
            'action_label' => 'Acción',

            'blocked_no_domain_title' => 'La IP estaba bloqueada pero el dominio NO se encontró en los registros del servidor',
            'blocked_no_domain_description' => 'Esto podría indicar: El usuario proporcionó un dominio incorrecto, El dominio no existe en este servidor, o Posible intento de abuso.',
            'no_unblock_silent' => 'No se realizó desbloqueo (silencioso)',

            'domain_found_not_blocked_title' => 'Se encontró el dominio pero la IP NO estaba bloqueada',
            'domain_found_not_blocked_description' => 'La IP del usuario no está actualmente bloqueada en el firewall.',
            'no_unblock_needed' => 'No se necesita desbloqueo (silencioso)',

            'no_match_title' => 'No se encontró coincidencia',
            'no_match_description' => 'Ni la IP bloqueada ni el dominio se encontraron en ningún servidor.',
            'no_action_silent' => 'No se realizó ninguna acción (silencioso)',

            'job_failure_title' => 'Falló la ejecución del trabajo',
            'error_label' => 'Error',
            'unknown_error' => 'Error desconocido',
            'review_logs' => 'Revisar logs e investigar',

            'unknown_reason' => 'Motivo desconocido',
            'analysis_data_title' => 'Datos del Análisis',
            'ip_blocked_label' => 'IP Bloqueada',
            'yes' => 'Sí',
            'no' => 'No',
            'logs_preview_title' => 'Vista Previa de Logs',
            'note_label' => 'Nota',
            'user_not_notified' => 'El usuario NO fue notificado sobre este intento (registro silencioso).',
            'system_suffix' => 'Sistema',
        ],
    ],
];
