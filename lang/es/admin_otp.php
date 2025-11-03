<?php

return [
    // Admin OTP Verification
    'title' => 'Verificación de Administrador',
    'subtitle' => 'Por seguridad, verifica tu identidad con el código enviado a tu email',

    'code_label' => 'Código de Verificación',
    'code_help' => 'Introduce el código de 6 dígitos que hemos enviado a tu email',

    'verify_button' => 'Verificar',
    'verifying' => 'Verificando...',
    'resend_button' => 'Reenviar Código',
    'resend_wait' => 'Espera para reenviar',
    'cancel_button' => 'Cancelar y Cerrar Sesión',

    // Messages
    'otp_sent' => 'Código de verificación enviado a tu email',
    'otp_resent' => 'Código reenviado correctamente',
    'verification_success' => '¡Verificación exitosa! Redirigiendo...',
    'verification_error' => 'Error al verificar el código. Por favor, inténtalo de nuevo.',
    'resend_error' => 'Error al reenviar el código. Por favor, inténtalo de nuevo.',

    // Rate limiting
    'too_many_attempts' => 'Demasiados intentos. Por favor, espera :seconds segundos.',
    'resend_too_many' => 'Has reenviado el código demasiadas veces. Espera :seconds segundos.',

    // Help
    'help_text' => '¿No recibiste el código? Verifica tu carpeta de spam o reenvíalo.',

    // Session
    'session_expired' => 'Tu sesión ha expirado por seguridad. Por favor, inicia sesión nuevamente con tu contraseña y verifica el nuevo código OTP.',
];
