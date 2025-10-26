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

    'processing_message' => 'Tu solicitud está siendo procesada. Recibirás un email con los resultados en breve.',
    'error_message' => 'Ha ocurrido un error. Por favor, intenta de nuevo más tarde.',

    'rate_limit_exceeded' => 'Has excedido el límite de solicitudes. Por favor, espera :seconds segundos.',

    'generic_response' => 'Hemos recibido tu solicitud y la estamos procesando.',
    'no_information_found' => 'No se encontró información para los datos proporcionados.',
    'help_text' => 'Este formulario te permite desbloquear tu IP de forma rápida y segura. Solo necesitas proporcionar tu IP, dominio y email para recibir un código de verificación.',
    'otp_verified_ready' => '¡Código verificado! Ahora puedes procesar tu solicitud de desbloqueo.',

    'mail' => [
        'success_subject' => 'IP Desbloqueada - :domain',
        'admin_alert_subject' => 'Alerta: Intento Simple Unblock - :domain',
    ],
];
