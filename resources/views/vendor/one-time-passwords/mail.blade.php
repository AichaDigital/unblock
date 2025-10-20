@extends('emails.layouts.email')

@section('title', __('Código de acceso - Unblock Firewall'))

@section('content')
    <h1>Código de Acceso</h1>

    <p>Has solicitado acceder a Unblock Firewall. Usa el siguiente código de 6 dígitos para completar tu autenticación:</p>

    <div class="button-container">
        <div style="font-size: 32px; font-weight: bold; color: #059669; letter-spacing: 4px; background-color: #f0fdf4; padding: 20px; border-radius: 8px; border: 2px solid #059669;">
            {{ $oneTimePassword->password }}
        </div>
    </div>

    <div class="expiry-notice">
        <strong>Tiempo limitado:</strong> Este código expira en <strong>5 minutos</strong> por seguridad.
    </div>

    <p>Si no solicitaste este código, puedes ignorar este mensaje de forma segura.</p>

    <div class="footer">
        <p><strong>Sistema Unblock Firewall</strong><br>
        Gestión de accesos y análisis de firewall<br>
        <small>Este mensaje fue enviado desde {{ config('app.url') }}</small></p>
    </div>
@endsection
