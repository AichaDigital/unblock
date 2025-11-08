@extends('emails.layouts.email')

@section('title', '[CRÍTICO] Error de Conexión SSH - Sistema Firewall')

@section('content')
    {{-- Critical header --}}
    <div style="background-color: #fee2e2; border-left: 4px solid #dc2626; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h1 style="color: #991b1b; font-size: 24px; font-weight: 600; margin: 0;">🚨 Error Crítico de Conexión SSH</h1>
    </div>

    <p style="font-size: 16px; line-height: 1.6; color: #1f2937; margin: 0 0 20px 0;"><strong>Se ha producido un error crítico de conexión SSH en el sistema de firewall.</strong></p>

    {{-- Error details section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">📋 Detalles del Error</h2>

    {{-- Code block with word-wrap --}}
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #1f2937; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; line-height: 1.8; margin-bottom: 20px;">
<strong>Servidor:</strong> {{ $host->fqdn }}:{{ $host->port_ssh ?? 22 }}
<strong>Panel:</strong> {{ $host->panel ?? 'N/A' }}
<strong>IP Verificada:</strong> {{ $ip }}
<strong>Usuario:</strong> {{ $user->full_name }} ({{ $user->email }})
<strong>Timestamp:</strong> {{ now()->format('Y-m-d H:i:s T') }}

<strong>Error:</strong> {{ $errorMessage }}

<strong>Tipo de Excepción:</strong> {{ get_class($exception) }}
<strong>Archivo:</strong> {{ $exception->getFile() }}:{{ $exception->getLine() }}
    </div>

    {{-- Auto-diagnostic section --}}
    @php
        $diagnostics = app(\App\Services\FirewallConnectionErrorService::class)->getDiagnosticInfo($exception);
    @endphp

    @if(isset($diagnostics['likely_cause']))
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 30px 0 15px 0;">🔍 Diagnóstico Automático</h2>
    <div style="background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 15px; font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #92400e; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; line-height: 1.8; margin-bottom: 20px;">
<strong>Causa Probable:</strong> {{ $diagnostics['likely_cause'] }}
<strong>Acción Sugerida:</strong> {{ $diagnostics['suggested_action'] }}
    </div>
    @endif

    {{-- Required actions section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 30px 0 15px 0;">🛠️ Acciones Requeridas</h2>
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 15px 0;">Este error requiere atención inmediata del administrador:</p>
    <ul style="margin: 0 0 20px 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #555555;">
        <li style="margin: 5px 0;">Verificar conectividad SSH al servidor {{ $host->fqdn }}</li>
        <li style="margin: 5px 0;">Comprobar estado del servicio SSH en el puerto {{ $host->port_ssh ?? 22 }}</li>
        <li style="margin: 5px 0;">Validar claves SSH y permisos de acceso</li>
        <li style="margin: 5px 0;">Revisar logs del sistema para más detalles</li>
    </ul>

    {{-- User notification section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 30px 0 15px 0;">👤 Notificación al Usuario</h2>
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">El usuario <strong>{{ $user->full_name }}</strong> ha sido notificado automáticamente sobre el error del sistema y se le ha indicado que contacte con soporte.</p>

    {{-- System message footer --}}
    <div style="margin-top: 30px; padding: 15px; background-color: #f1f5f9; border-radius: 6px;">
        <p style="font-size: 14px; line-height: 1.6; color: #64748b; margin: 0;">
            Este es un mensaje automático del sistema de firewall Unblock.<br>
            Para más información, revisa los logs del sistema o contacta con el equipo técnico.
        </p>
    </div>

    {{-- Note: Layout footer is automatically added --}}
@endsection
