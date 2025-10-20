@extends('emails.layouts.email')

@section('title', 'Error Temporal del Sistema - Verificación de Firewall')

@section('content')
    <h1>⚠️ Error Temporal del Sistema</h1>

    <p>Estimado/a <strong>{{ $user->full_name }}</strong>,</p>

    <p>Lamentamos informarle que se ha producido un error temporal en nuestro sistema durante el procesamiento de su solicitud de verificación de firewall.</p>

    <h2>📋 Detalles de su Solicitud</h2>
    <div class="code">
        <p><strong>IP Verificada:</strong> {{ $ip }}
<strong>Servidor:</strong> {{ $host->fqdn }}
<strong>Fecha y Hora:</strong> {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <h2>🔧 ¿Qué ha Ocurrido?</h2>
    <p>Nuestro sistema ha experimentado dificultades técnicas temporales al conectarse con el servidor para procesar su solicitud. Este tipo de errores suelen resolverse rápidamente.</p>

    <h2>✅ ¿Qué Estamos Haciendo?</h2>
    <p>Nuestro equipo técnico ha sido notificado automáticamente y está trabajando para resolver el problema lo antes posible.</p>

    <h2>📞 ¿Qué Puede Hacer Usted?</h2>
    <p>Puede intentar realizar la verificación nuevamente en unos minutos. Si el problema persiste, no dude en contactar con nuestro equipo de soporte.</p>

    <div class="expiry-notice">
        <strong>Nota:</strong> Al contactar con soporte, por favor mencione la fecha y hora de este error para una asistencia más rápida.
    </div>

    <p>Disculpe las molestias ocasionadas y gracias por su comprensión.</p>

    <div class="footer">
        <p>Atentamente,<br>
        <strong>Equipo Técnico</strong><br>
        Sistema de Gestión de Firewall Unblock</p>
    </div>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center;">
        <p>
            <a href="{{ config('company.legal.privacy_policy_url') }}" style="color: #64748b; text-decoration: none;">{{ __('Política de Privacidad') }}</a> |
            <a href="{{ config('company.legal.terms_url') }}" style="color: #64748b; text-decoration: none;">{{ __('Términos de Servicio') }}</a> |
            <a href="{{ config('company.legal.data_protection_url') }}" style="color: #64748b; text-decoration: none;">{{ __('Protección de Datos') }}</a>
        </p>
        <p>&copy; {{ date('Y') }} {{ config('company.name') }}. {{ __('Todos los derechos reservados') }}.</p>
    </div>
@endsection
