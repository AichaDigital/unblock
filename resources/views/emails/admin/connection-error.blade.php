@extends('emails.layouts.email')

@section('title', '[CRÍTICO] Error de Conexión SSH - Sistema Firewall')

@section('content')
    <h1>🚨 Error Crítico de Conexión SSH</h1>

    <p><strong>Se ha producido un error crítico de conexión SSH en el sistema de firewall.</strong></p>

    <h2>📋 Detalles del Error</h2>
    <div class="code">
        <p><strong>Servidor:</strong> {{ $host->fqdn }}:{{ $host->port_ssh ?? 22 }}
<strong>Panel:</strong> {{ $host->panel ?? 'N/A' }}
<strong>IP Verificada:</strong> {{ $ip }}
<strong>Usuario:</strong> {{ $user->full_name }} ({{ $user->email }})
<strong>Timestamp:</strong> {{ now()->format('Y-m-d H:i:s T') }}

<strong>Error:</strong> {{ $errorMessage }}

<strong>Tipo de Excepción:</strong> {{ get_class($exception) }}
<strong>Archivo:</strong> {{ $exception->getFile() }}:{{ $exception->getLine() }}</p>
    </div>

    @php
        $diagnostics = app(\App\Services\FirewallConnectionErrorService::class)->getDiagnosticInfo($exception);
    @endphp

    @if(isset($diagnostics['likely_cause']))
    <h2>🔍 Diagnóstico Automático</h2>
    <div class="code">
        <p><strong>Causa Probable:</strong> {{ $diagnostics['likely_cause'] }}
<strong>Acción Sugerida:</strong> {{ $diagnostics['suggested_action'] }}</p>
    </div>
    @endif

    <h2>🛠️ Acciones Requeridas</h2>
    <p>Este error requiere atención inmediata del administrador:</p>
    <ul>
        <li>Verificar conectividad SSH al servidor {{ $host->fqdn }}</li>
        <li>Comprobar estado del servicio SSH en el puerto {{ $host->port_ssh ?? 22 }}</li>
        <li>Validar claves SSH y permisos de acceso</li>
        <li>Revisar logs del sistema para más detalles</li>
    </ul>

    <h2>👤 Notificación al Usuario</h2>
    <p>El usuario <strong>{{ $user->full_name }}</strong> ha sido notificado automáticamente sobre el error del sistema y se le ha indicado que contacte con soporte.</p>

    <div class="footer">
        <p>Este es un mensaje automático del sistema de firewall Unblock.<br>
        Para más información, revisa los logs del sistema o contacta con el equipo técnico.</p>
    </div>
@endsection
