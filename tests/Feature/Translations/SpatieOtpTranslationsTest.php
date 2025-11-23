<?php

declare(strict_types=1);

namespace Tests\Feature\Translations;

use Spatie\OneTimePasswords\Enums\ConsumeOneTimePasswordResult;

it('has Spanish translations for all Spatie OTP validation messages', function () {
    app()->setLocale('es');

    $cases = [
        ConsumeOneTimePasswordResult::NoOneTimePasswordsFound,
        ConsumeOneTimePasswordResult::IncorrectOneTimePassword,
        ConsumeOneTimePasswordResult::DifferentOrigin,
        ConsumeOneTimePasswordResult::OneTimePasswordExpired,
    ];

    foreach ($cases as $case) {
        $message = $case->validationMessage();

        // Ensure message is translated and not using English fallback
        expect($message)
            ->not->toContain('The given code is not correct')
            ->not->toContain('The given code is not valid anymore')
            ->toContain('código');
    }
});

it('has Spanish translations for Spatie OTP notifications', function () {
    app()->setLocale('es');

    expect(__('one-time-passwords::notifications.mail_subject', ['oneTimePassword' => '123456']))
        ->toContain('código')
        ->not->toContain('one-time login code');

    expect(__('one-time-passwords::notifications.title'))
        ->toContain('acceso')
        ->not->toContain('One time login code');

    expect(__('one-time-passwords::notifications.intro', ['url' => 'https://example.com']))
        ->toContain('código')
        ->not->toContain('one-time login code');

    expect(__('one-time-passwords::notifications.outro'))
        ->toContain('cuenta')
        ->not->toContain('account');
});

it('has Spanish translations for Spatie OTP form', function () {
    app()->setLocale('es');

    expect(__('one-time-passwords::form.email_form_title'))
        ->toContain('código')
        ->not->toContain('One-Time Login Code');

    expect(__('one-time-passwords::form.email_label'))
        ->toContain('Correo')
        ->not->toContain('E-mail');

    expect(__('one-time-passwords::form.send_login_code_button'))
        ->toContain('código')
        ->not->toContain('Send one-time login code');
});

it('uses Spanish translations in AdminOtpVerification component messages', function () {
    app()->setLocale('es');

    $incorrectCodeCase = ConsumeOneTimePasswordResult::IncorrectOneTimePassword;
    $expiredCodeCase = ConsumeOneTimePasswordResult::OneTimePasswordExpired;

    expect($incorrectCodeCase->validationMessage())
        ->toBe('El código proporcionado no es correcto');

    expect($expiredCodeCase->validationMessage())
        ->toBe('El código proporcionado ya no es válido');
});

it('uses Spanish translations in OtpLogin component messages', function () {
    app()->setLocale('es');

    expect(__('auth.ip_mismatch'))
        ->toBe('Este código solo puede usarse desde la misma IP que lo solicitó.');

    expect(__('admin_otp.verification_success'))
        ->toBe('¡Verificación exitosa! Redirigiendo...');

    expect(__('admin_otp.verification_error'))
        ->toBe('Error al verificar el código. Por favor, inténtalo de nuevo.');
});
