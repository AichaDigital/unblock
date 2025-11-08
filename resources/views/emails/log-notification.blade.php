@extends('emails.layouts.email')

@section('title', 'Informe de IP')

@section('content')
    {{-- Main heading: text-2xl (24px) --}}
    <h1 style="color: #1f2937; font-size: 24px; font-weight: 600; margin: 0 0 20px 0;">Hola, {{ $userName }}!</h1>

    {{-- Subheading: text-xl (20px) --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">Informe sobre la IP {{ $ip }}</h2>

    @if (!empty($report['host']))
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 10px 0;"><strong>Servidor:</strong> {{ $report['host'] }}</p>
    @endif

    @if (!empty($report['requested_by']))
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;"><strong>Solicitado por:</strong> {{ $report['requested_by'] }}</p>
    @endif

    @if ($is_unblock)
        {{-- Success box --}}
        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h3 style="color: #065f46; margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">✅ IP desbloqueada correctamente</h3>
            <p style="color: #065f46; margin: 0; font-size: 16px; line-height: 1.6;">La IP {{ $ip }} ha sido desbloqueada y ya puede conectarse al servidor.</p>
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
            <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 25px 0 12px 0;">{{ __('firewall.email.block_origin_title') }}</h3>
            <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">{{ __('La IP estaba bloqueada en') }}: <strong>{{ implode(', ', array_unique($blockOrigins)) }}</strong></p>
        @endif

        @if(!empty($report['analysis']['block_sources'] ?? []))
            <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 25px 0 12px 0;">{{ __('firewall.email.actions_taken') }}</h3>
            <ul style="margin: 0 0 20px 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #555555;">
                @if(in_array('csf', $report['analysis']['block_sources'] ?? []))
                    <li style="margin: 5px 0;">{{ __('firewall.email.action_csf_remove') }}</li>
                    <li style="margin: 5px 0;">{{ __('firewall.email.action_csf_whitelist') }}</li>
                @endif
                @if(in_array('da_bfm', $report['analysis']['block_sources'] ?? []))
                    <li style="margin: 5px 0;">{{ __('firewall.email.action_bfm_remove') }}</li>
                @endif
                @if(in_array('exim', $report['analysis']['block_sources'] ?? []) || in_array('dovecot', $report['analysis']['block_sources'] ?? []))
                    <li style="margin: 5px 0;">{{ __('firewall.email.action_mail_remove') }}</li>
                @endif
                @if(in_array('modsecurity', $report['analysis']['block_sources'] ?? []))
                    <li style="margin: 5px 0;">{{ __('firewall.email.action_web_remove') }}</li>
                @endif
            </ul>
        @endif

        @if(!empty($report['logs']))
            <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('firewall.email.technical_details') }}</h3>

            @foreach($report['logs'] as $logType => $logContent)
                @if(!empty($logContent))
                    @php
                        // Normalize the key (remove prefixes like DA_, etc)
                        $normalizedKey = strtolower(str_replace(['da_', 'DA_'], '', $logType));
                        $translationKey = 'firewall.logs.descriptions.' . $normalizedKey;

                        // Get translation as array if exists, null otherwise
                        $logDescription = trans()->has($translationKey) ? __($translationKey) : null;
                    @endphp

                    {{-- Log container with proper word-wrap --}}
                    <div style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                        {{-- Log header --}}
                        <div style="background-color: #f1f5f9; padding: 10px; border-bottom: 1px solid #e2e8f0;">
                            @if($logDescription && is_array($logDescription))
                                <h4 style="margin: 0; font-size: 16px; color: #1e40af; font-weight: 600;">{{ $logDescription['title'] }}</h4>
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
                                    <h4 style="margin: 0; font-size: 16px; color: #1f2937; font-weight: 600;">MOD_SECURITY</h4>
                                @else
                                    <h4 style="margin: 0; font-size: 16px; color: #1f2937; font-weight: 600;">{{ strtoupper($logType) }}</h4>
                                @endif
                            @endif
                        </div>

                        {{-- Log content: CRITICAL word-wrap properties for long lines --}}
                        <div style="padding: 15px; background-color: #ffffff; font-family: 'Courier New', Courier, monospace; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; font-size: 12px; max-width: 100%; overflow-x: auto; color: #1f2937; line-height: 1.5;">
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
            <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('firewall.email.analysis_title') }}</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: 600; width: 40%; font-size: 14px; color: #374151;">{{ __('firewall.email.was_blocked') }}</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; font-size: 14px;">
                        @if(isset($report['analysis']['was_blocked']) && $report['analysis']['was_blocked'])
                            <span style="color: #ef4444; font-weight: 600;">{{ __('firewall.email.yes') }}</span>
                        @else
                            <span style="color: #22c55e; font-weight: 600;">{{ __('firewall.email.no') }}</span>
                        @endif
                    </td>
                </tr>

                @if(!empty($report['analysis']['unblock_performed']))
                <tr>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: 600; font-size: 14px; color: #374151;">{{ __('firewall.email.unblock_performed') }}</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; font-size: 14px;">
                        @if($report['analysis']['unblock_performed'])
                            <span style="color: #22c55e; font-weight: 600;">{{ __('firewall.email.yes') }}</span>
                        @else
                            <span style="color: #ef4444; font-weight: 600;">{{ __('firewall.email.no') }}</span>
                        @endif
                    </td>
                </tr>
                @endif

                @if(!empty($report['analysis']['timestamp']))
                <tr>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-weight: 600; font-size: 14px; color: #374151;">{{ __('firewall.email.analysis_timestamp') }}</td>
                    <td style="padding: 10px; border: 1px solid #e2e8f0; font-size: 12px; font-family: 'Courier New', Courier, monospace; color: #64748b;">{{ $report['analysis']['timestamp'] }}</td>
                </tr>
                @endif
            </table>
        @endif

        <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('firewall.email.web_report') }}</h3>
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">{{ __('firewall.email.web_report_available') }} <a href="{{ route('report.show', ['id' => $report_uuid]) }}" style="color: #3b82f6; text-decoration: none;">{{ __('firewall.email.web_report_link') }}</a></p>
    @else
        {{-- Not blocked box --}}
        <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h3 style="color: #991b1b; margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">❌ No se encontró ningún bloqueo</h3>
            <p style="color: #991b1b; margin: 0; font-size: 16px; line-height: 1.6;">No se ha detectado ningún bloqueo para la IP {{ $ip }} en este servidor.</p>
        </div>

        <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 25px 0 15px 0;">¿Sigue teniendo problemas de conexión?</h3>
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 15px 0;">Si desde esa IP se están experimentando problemas de conexión con alguno o todos los servicios, por favor:</p>
        <ol style="margin: 0 0 20px 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #555555;">
            <li style="margin: 8px 0;">Verifique que está intentando conectarse al servidor correcto</li>
            <li style="margin: 8px 0;">Compruebe sus credenciales de acceso</li>
            <li style="margin: 8px 0;">Si el problema persiste, contacte con soporte indicando la IP, el servidor y el servicio de hosting afectado</li>
        </ol>
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 5px 0;">Añada esta ID a su ticket de soporte: <strong style="font-family: 'Courier New', Courier, monospace;">{{$report_uuid}}</strong></p>
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">Lo atenderemos lo antes posible.</p>
    @endif

    {{-- Help section --}}
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
        <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 0 0 12px 0;">¿Necesita ayuda?</h3>
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 10px 0;">Consulte la documentación sobre desbloqueo de IPs.</p>
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 10px 0;">Si tiene alguna duda, contacte con soporte.</p>
        <p style="font-size: 14px; line-height: 1.6; color: #64748b; margin: 0;">ID de referencia: <strong style="font-family: 'Courier New', Courier, monospace;">{{$report_uuid}}</strong></p>
    </div>

    {{-- Signature --}}
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 30px 0 0 0;">Gracias,<br>El equipo de soporte</p>

    {{-- Note: Layout footer is automatically added - NO DUPLICATE --}}
@endsection
