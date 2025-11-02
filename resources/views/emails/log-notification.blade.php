@extends('emails.layouts.email')

@section('title', 'Informe de IP')

@section('content')
    <h1>Hola, {{ $userName }}!</h1>

    <h2>Informe sobre la IP {{ $ip }}</h2>
    @if (!empty($report['host']))
        <p><strong>Servidor:</strong> {{ $report['host'] }}</p>
    @endif

    @if (!empty($report['requested_by']))
        <p><strong>Solicitado por:</strong> {{ $report['requested_by'] }}</p>
    @endif

    @if ($is_unblock)
        <div style="background-color: #d1e7dd; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h3 style="color: #0f5132; margin-top: 0;">✅ IP desbloqueada correctamente</h3>
            <p style="color: #0f5132; margin-bottom: 0;">La IP {{ $ip }} ha sido desbloqueada y ya puede conectarse al servidor.</p>
        </div>

        @php
            // CRITICAL FIX: Determinar origen del bloqueo SOLO basándose en block_sources del análisis
            // NO usar existencia de logs como indicador de bloqueo (eso era el bug)
            $blockOrigins = [];
            $blockSources = $report['analysis']['block_sources'] ?? [];

            // Solo los servicios que REALMENTE causaron bloqueos según el análisis
            foreach ($blockSources as $source) {
                switch ($source) {
                    case 'csf_primary':
                    case 'csf_deny':
                    case 'csf_tempip':
                        $blockOrigins[] = __('firewall.logs.descriptions.csf.title');
                        break;
                    case 'da_bfm':
                        $blockOrigins[] = __('firewall.logs.descriptions.bfm.title');
                        break;
                    case 'mod_security':
                        $blockOrigins[] = __('firewall.logs.descriptions.mod_security.title');
                        break;
                    // Dovecot/Exim ya NO pueden ser fuentes de bloqueo (solo contexto)
                    // Esta es la corrección del bug de "IP desbloqueada correctamente"
                }
            }

            // Remover duplicados (por si CSF aparece múltiples veces)
            $blockOrigins = array_unique($blockOrigins);
        @endphp

        @if(count($blockOrigins) > 0)
            <h3>{{ __('firewall.email.block_origin_title') }}</h3>
            <p>{{ __('La IP estaba bloqueada en') }}: <strong>{{ implode(', ', array_unique($blockOrigins)) }}</strong></p>
        @endif

        @if(!empty($report['analysis']['block_sources'] ?? []))
            <h3>{{ __('firewall.email.actions_taken') }}</h3>
            <ul>
                @if(in_array('csf', $report['analysis']['block_sources'] ?? []))
                    <li>{{ __('firewall.email.action_csf_remove') }}</li>
                    <li>{{ __('firewall.email.action_csf_whitelist') }}</li>
                @endif
                @if(in_array('da_bfm', $report['analysis']['block_sources'] ?? []))
                    <li>{{ __('firewall.email.action_bfm_remove') }}</li>
                @endif
                @if(in_array('exim', $report['analysis']['block_sources'] ?? []) || in_array('dovecot', $report['analysis']['block_sources'] ?? []))
                    <li>{{ __('firewall.email.action_mail_remove') }}</li>
                @endif
                @if(in_array('modsecurity', $report['analysis']['block_sources'] ?? []))
                    <li>{{ __('firewall.email.action_web_remove') }}</li>
                @endif
            </ul>
        @endif

        @if(!empty($report['logs']))
            <h3>{{ __('firewall.email.technical_details') }}</h3>

            @foreach($report['logs'] as $logType => $logContent)
                @if(!empty($logContent))
                    @php
                        // Normalize the key (remove prefixes like DA_, etc)
                        $normalizedKey = strtolower(str_replace(['da_', 'DA_'], '', $logType));
                        $logDescription = __('firewall.logs.descriptions.' . $normalizedKey, [], null);
                    @endphp
                    <div style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                        <div style="background-color: #f1f5f9; padding: 10px; border-bottom: 1px solid #e2e8f0;">
                            @if($logDescription && is_array($logDescription))
                                <h4 style="margin: 0; font-size: 16px; color: #1e40af;">{{ $logDescription['title'] }}</h4>
                                <p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">{{ $logDescription['description'] }}</p>
                                @if(isset($logDescription['wiki_link']))
                                    <p style="margin: 5px 0 0 0; font-size: 11px;">
                                        <a href="{{ $logDescription['wiki_link'] }}" style="color: #1d4ed8; text-decoration: none;">
                                            📖 {{ __('firewall.help.more_info_wiki') }}
                                        </a>
                                    </p>
                                @endif
                            @else
                                @if($logType === 'mod_security')
                                    <h4 style="margin: 0; font-size: 16px;">MOD_SECURITY</h4>
                                @else
                                    <h4 style="margin: 0; font-size: 16px;">{{ strtoupper($logType) }}</h4>
                                @endif
                            @endif
                        </div>
                        <div style="padding: 15px; background-color: #ffffff; font-family: monospace; white-space: pre-wrap; font-size: 14px;">
                            @if(is_string($logContent))
                                {{ $logContent }}
                            @elseif(is_array($logContent))
                                @foreach($logContent as $line)
                                    @if(is_string($line))
                                        {{ $line }}<br>
                                    @endif
                                @endforeach
                            @else
                                {{ json_encode($logContent) }}
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        @endif

        @if(!empty($report['analysis']))
            <h3>{{ __('firewall.email.analysis_title') }}</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: bold; width: 40%;">{{ __('firewall.email.was_blocked') }}</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0;">
                        @if(isset($report['analysis']['was_blocked']) && $report['analysis']['was_blocked'])
                            <span style="color: #ef4444;">{{ __('firewall.email.yes') }}</span>
                        @else
                            <span style="color: #22c55e;">{{ __('firewall.email.no') }}</span>
                        @endif
                    </td>
                </tr>

                @if(!empty($report['analysis']['unblock_performed']))
                <tr>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: bold;">{{ __('firewall.email.unblock_performed') }}</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0;">
                        @if($report['analysis']['unblock_performed'])
                            <span style="color: #22c55e;">{{ __('firewall.email.yes') }}</span>
                        @else
                            <span style="color: #ef4444;">{{ __('firewall.email.no') }}</span>
                        @endif
                    </td>
                </tr>
                @endif

                @if(!empty($report['analysis']['timestamp']))
                <tr>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: bold;">{{ __('firewall.email.analysis_timestamp') }}</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0;">{{ $report['analysis']['timestamp'] }}</td>
                </tr>
                @endif
            </table>
        @endif

        <h3>{{ __('firewall.email.web_report') }}</h3>
        <p>{{ __('firewall.email.web_report_available') }} <a href="{{ route('report.show', ['id' => $report_uuid]) }}">{{ __('firewall.email.web_report_link') }}</a></p>
    @else
        <div style="background-color: #f8d7da; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h3 style="color: #842029; margin-top: 0;">❌ No se encontró ningún bloqueo</h3>
            <p style="color: #842029; margin-bottom: 0;">No se ha detectado ningún bloqueo para la IP {{ $ip }} en este servidor.</p>
        </div>

        <h3>¿Sigue teniendo problemas de conexión?</h3>
        <p>Si desde esa IP se están experimentando problemas de conexión con alguno o todos los servicios, por favor:</p>
        <ol>
            <li>Verifique que está intentando conectarse al servidor correcto</li>
            <li>Compruebe sus credenciales de acceso</li>
            <li>Si el problema persiste, contacte con soporte indicando la IP, el servidor y el servicio de hosting afectado</li>
        </ol>
        <p>Añada esta ID a su ticket de soporte: <strong>{{$report_uuid}}</strong></p>
        <p>Lo atenderemos lo antes posible.</p>
    @endif

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
        <h3>¿Necesita ayuda?</h3>
        <p>Consulte la documentación sobre desbloqueo de IPs.</p>
        <p>Si tiene alguna duda, contacte con soporte.</p>
        <p>ID de referencia: <strong>{{$report_uuid}}</strong></p>
    </div>

    <p>Gracias,<br>El equipo de soporte</p>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center;">
        <p>
            <a href="{{ config('company.legal.privacy_policy_url') }}" style="color: #64748b; text-decoration: none;">{{ __('Política de Privacidad') }}</a> |
            <a href="{{ config('company.legal.terms_url') }}" style="color: #64748b; text-decoration: none;">{{ __('Términos de Servicio') }}</a> |
            <a href="{{ config('company.legal.data_protection_url') }}" style="color: #64748b; text-decoration: none;">{{ __('Protección de Datos') }}</a>
        </p>
        <p>&copy; {{ date('Y') }} {{ config('company.name') }}. {{ __('Todos los derechos reservados') }}.</p>
    </div>
@endsection
