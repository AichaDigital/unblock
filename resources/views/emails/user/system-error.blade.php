@extends('emails.layouts.email')

@section('title', 'Error Temporal del Sistema - Verificación de Firewall')

@section('content')
    {{-- Warning header --}}
    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h1 style="color: #92400e; font-size: 24px; font-weight: 600; margin: 0;">⚠️ Error Temporal del Sistema</h1>
    </div>

    {{-- Greeting --}}
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 15px 0;">Estimado/a <strong>{{ $user->full_name }}</strong>,</p>

    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">Lamentamos informarle que se ha producido un error temporal en nuestro sistema durante el procesamiento de su solicitud de verificación de firewall.</p>

    {{-- Request details section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">📋 Detalles de su Solicitud</h2>

    {{-- Details box with word-wrap --}}
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #1f2937; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; line-height: 1.8; margin-bottom: 20px;">
<strong>IP Verificada:</strong> {{ $ip }}
<strong>Servidor:</strong> {{ $host->fqdn }}
<strong>Fecha y Hora:</strong> {{ now()->format('d/m/Y H:i:s') }}
    </div>

    {{-- What happened section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">🔧 ¿Qué ha Ocurrido?</h2>
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">Nuestro sistema ha experimentado dificultades técnicas temporales al conectarse con el servidor para procesar su solicitud. Este tipo de errores suelen resolverse rápidamente.</p>

    {{-- What we're doing section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">✅ ¿Qué Estamos Haciendo?</h2>
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">Nuestro equipo técnico ha sido notificado automáticamente y está trabajando para resolver el problema lo antes posible.</p>

    {{-- What user can do section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">📞 ¿Qué Puede Hacer Usted?</h2>
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">Puede intentar realizar la verificación nuevamente en unos minutos. Si el problema persiste, no dude en contactar con nuestro equipo de soporte.</p>

    {{-- Notice box --}}
    <div style="background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px 16px; color: #92400e; font-size: 14px; margin: 20px 0; line-height: 1.6;">
        <strong>Nota:</strong> Al contactar con soporte, por favor mencione la fecha y hora de este error para una asistencia más rápida.
    </div>

    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 20px 0;">Disculpe las molestias ocasionadas y gracias por su comprensión.</p>

    {{-- Signature --}}
    <div style="margin-top: 30px; padding: 15px; background-color: #f1f5f9; border-radius: 6px;">
        <p style="font-size: 14px; line-height: 1.6; color: #555555; margin: 0;">
            Atentamente,<br>
            <strong>Equipo Técnico</strong><br>
            Sistema de Gestión de Firewall Unblock
        </p>
    </div>

    {{-- Note: Layout footer is automatically added - NO DUPLICATE --}}
@endsection
