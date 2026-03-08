@extends('layouts.layout')

@section('content')
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4 text-base-content">{{ __('Informe para la IP') }}: {{ $report->ip }}</h1>

        <h2 class="text-xl font-semibold mb-4 text-base-content">{{ __('Detalles del Reporte') }}:</h2>

        @if(!empty($report->logs))
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-2 text-base-content">{{ __('firewall.reports.logs') }}</h3>
                <div class="bg-base-200 p-4 rounded">
                    @foreach ($report->logs as $key => $log)
                        @if (!empty($log))
                            @php
                                // Normalize the key (remove prefixes like DA_, etc)
                                $normalizedKey = strtolower(str_replace(['da_', 'DA_'], '', $key));
                                $logDescription = __('firewall.logs.descriptions.' . $normalizedKey, [], null);
                            @endphp
                            <div class="mb-6 border-l-4 border-primary pl-4">
                                <div class="mb-2">
                                    @if($logDescription && is_array($logDescription))
                                        <h4 class="font-bold text-lg text-primary">
                                            {{ is_array($logDescription['title'] ?? '') ? json_encode($logDescription['title']) : ($logDescription['title'] ?? Str::upper($key)) }}
                                        </h4>
                                        <p class="text-sm text-base-content/70 mb-2">
                                            {{ is_array($logDescription['description'] ?? '') ? json_encode($logDescription['description']) : ($logDescription['description'] ?? '') }}
                                        </p>
                                        @if(isset($logDescription['wiki_link']) && !is_array($logDescription['wiki_link']))
                                            <p class="text-xs">
                                                <a href="{{ $logDescription['wiki_link'] }}"
                                                   class="text-primary hover:underline"
                                                   target="_blank">
                                                    📖 {{ __('firewall.help.more_info_wiki') }}
                                                </a>
                                            </p>
                                        @endif
                                    @else
                                        <h4 class="font-semibold text-base-content">{{ Str::upper($key) }}</h4>
                                    @endif
                                </div>
                                <div class="pl-4 bg-base-100 p-3 rounded border border-base-300">
                                    @if (str_contains($key, 'mod_security') && is_array($log))
                                        @foreach ($log as $entry)
                                            <div class="mod-security-entry mb-2 border-b border-base-300 pb-2 last:border-b-0">
                                                <p class="text-sm text-base-content">
                                                    <strong>{{ __('Cliente IP') }}:</strong>
                                                    @php
                                                        $clienteIp = $entry['cliente_ip'] ?? 'N/A';
                                                        echo is_array($clienteIp) ? e(json_encode($clienteIp)) : e($clienteIp);
                                                    @endphp
                                                </p>
                                                <p class="text-sm text-base-content">
                                                    <strong>URI:</strong>
                                                    @php
                                                        $uri = $entry['uri'] ?? 'N/A';
                                                        echo is_array($uri) ? e(json_encode($uri)) : e($uri);
                                                    @endphp
                                                </p>
                                                <p class="text-sm text-base-content">
                                                    <strong>{{ __('Mensaje') }}:</strong>
                                                    @php
                                                        $message = $entry['message'] ?? 'N/A';
                                                        echo is_array($message) ? e(json_encode($message)) : e($message);
                                                    @endphp
                                                </p>
                                                <p class="text-sm text-base-content">
                                                    <strong>Rule ID:</strong>
                                                    @php
                                                        $ruleId = $entry['ruleId'] ?? 'N/A';
                                                        echo is_array($ruleId) ? e(json_encode($ruleId)) : e($ruleId);
                                                    @endphp
                                                </p>
                                            </div>
                                        @endforeach
                                    @elseif (is_array($log))
                                        @foreach ($log as $item)
                                            <p class="font-mono text-sm mb-1 text-base-content break-all">
                                                @php echo is_array($item) ? e(json_encode($item)) : e($item); @endphp
                                            </p>
                                        @endforeach
                                    @else
                                        <p class="font-mono text-sm text-base-content break-all">
                                            @php echo is_array($log) ? e(json_encode($log)) : e($log); @endphp
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if(!empty($report->analysis))
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-2 text-base-content">{{ __('firewall.reports.analysis') }}</h3>
                <div class="bg-base-200 p-4 rounded space-y-4">
                    {{-- Was Blocked --}}
                    @if(isset($report->analysis['was_blocked']))
                        <div class="text-base-content">
                            <strong>{{ __('¿Estaba Bloqueada?') }}:</strong>
                            <span class="ml-2 {{ $report->analysis['was_blocked'] ? 'text-error font-semibold' : 'text-success font-semibold' }}">
                                {{ $report->analysis['was_blocked'] ? __('Sí') : __('No') }}
                            </span>
                        </div>
                    @endif

                    {{-- Block Sources --}}
                    @if(isset($report->analysis['block_sources']) && !empty($report->analysis['block_sources']))
                        <div class="text-base-content">
                            <strong>{{ __('Servicios que Bloquearon:') }}:</strong>
                            <ul class="list-disc pl-5 mt-1">
                                @foreach($report->analysis['block_sources'] as $source)
                                    <li class="text-error">{{ strtoupper($source) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Unblock Performed --}}
                    @if(isset($report->analysis['unblock_performed']))
                        <div class="text-base-content">
                            <strong>{{ __('Desbloqueo Realizado') }}:</strong>
                            <span class="ml-2 {{ $report->analysis['unblock_performed'] ? 'text-success font-semibold' : 'text-base-content/70' }}">
                                {{ $report->analysis['unblock_performed'] ? __('✓ Sí') : __('✗ No') }}
                            </span>
                        </div>
                    @endif

                    {{-- Unblock Status --}}
                    @if(isset($report->analysis['unblock_status']))
                        @php
                            $status = $report->analysis['unblock_status'];
                            $success = is_array($status) ? ($status['success'] ?? false) : (bool)$status;
                            $message = is_array($status) && isset($status['message']) ? $status['message'] : '';
                        @endphp
                        <div class="text-base-content">
                            <strong>{{ __('Estado del Desbloqueo') }}:</strong>
                            <div class="mt-2 p-3 rounded {{ $success ? 'bg-success/10 border border-success/20' : 'bg-error/10 border border-error/20' }}">
                                <p class="{{ $success ? 'text-success' : 'text-error' }}">
                                    {{ $success ? '✓' : '✗' }}
                                    @if($message && str_starts_with($message, 'messages.'))
                                        {{ __($message) }}
                                    @elseif($message)
                                        {{ $message }}
                                    @else
                                        {{ $success ? __('IP desbloqueada correctamente') : __('No se pudo desbloquear la IP') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Analysis Timestamp --}}
                    @if(isset($report->analysis['analysis_timestamp']))
                        <div class="text-base-content text-sm">
                            <strong>{{ __('Fecha del Análisis') }}:</strong>
                            <span class="ml-2">{{ \Carbon\Carbon::parse($report->analysis['analysis_timestamp'])->format('d/m/Y H:i:s') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="mt-8 p-4 bg-info/10 rounded-lg border border-info/20">
            <h3 class="text-lg font-semibold mt-0 mb-3 text-base-content">{{ __('firewall.help.need_help') }}</h3>
            <p class="mb-2 text-base-content/80">{{ __('Consulte la documentación para más información sobre desbloqueo de IPs') }}</p>
            <p class="text-base-content/80">{{ __('Si tiene alguna duda, contacte con soporte') }}</p>
        </div>

        <div class="mt-6 text-center text-base-content/70">
            <p>
                {{ __('Gracias') }},<br>
                {{ __('Equipo de Soporte de') }} {{ config('company.name') }}
            </p>
        </div>

        <div class="mt-12 pt-6 border-t border-base-300 text-center text-sm text-base-content/60">
            <p class="space-x-4">
                <a href="{{ config('company.legal.privacy_policy_url') }}" class="text-base-content/60 hover:text-base-content" target="_blank">{{ __('Política de Privacidad') }}</a>
                <span>|</span>
                <a href="{{ config('company.legal.terms_url') }}" class="text-base-content/60 hover:text-base-content" target="_blank">{{ __('Términos de Servicio') }}</a>
                <span>|</span>
                <a href="{{ config('company.legal.data_protection_url') }}" class="text-base-content/60 hover:text-base-content" target="_blank">{{ __('Protección de Datos') }}</a>
            </p>
            <p class="mt-2">&copy; {{ date('Y') }} {{ config('company.name') }}. {{ __('Todos los derechos reservados') }}.</p>
        </div>
    </div>
@endsection
