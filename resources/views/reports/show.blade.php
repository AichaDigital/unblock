@extends('layouts.layout')

@section('content')
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">{{ __('Informe para la IP') }}: {{ $report->ip }}</h1>

        <h2 class="text-xl font-semibold mb-4">{{ __('Detalles del Reporte') }}:</h2>

        @if(!empty($report->logs))
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-2">{{ __('firewall.logs') }}</h3>
                <div class="bg-gray-100 p-4 rounded">
                    @foreach ($report->logs as $key => $log)
                        @if (!empty($log))
                            @php
                                // Normalize the key (remove prefixes like DA_, etc)
                                $normalizedKey = strtolower(str_replace(['da_', 'DA_'], '', $key));
                                $logDescription = __('firewall.logs.descriptions.' . $normalizedKey, [], null);
                            @endphp
                            <div class="mb-6 border-l-4 border-blue-500 pl-4">
                                <div class="mb-2">
                                    @if($logDescription && is_array($logDescription))
                                        <h4 class="font-bold text-lg text-blue-700">{{ $logDescription['title'] }}</h4>
                                        <p class="text-sm text-gray-600 mb-2">{{ $logDescription['description'] }}</p>
                                        @if(isset($logDescription['wiki_link']))
                                            <p class="text-xs">
                                                <a href="{{ $logDescription['wiki_link'] }}"
                                                   class="text-blue-600 hover:underline"
                                                   target="_blank">
                                                    📖 {{ __('firewall.help.more_info_wiki') }}
                                                </a>
                                            </p>
                                        @endif
                                    @else
                                        <h4 class="font-semibold">{{ Str::upper($key) }}</h4>
                                    @endif
                                </div>
                                <div class="pl-4 bg-white p-3 rounded border">
                                    @if (str_contains($key, 'mod_security') && is_array($log))
                                        @foreach ($log as $entry)
                                            <div class="mod-security-entry mb-2 border-b pb-2">
                                                <p><strong>{{ __('Cliente IP') }}:</strong> {{ is_array($entry['cliente_ip'] ?? '') ? json_encode($entry['cliente_ip']) : ($entry['cliente_ip'] ?? 'N/A') }}</p>
                                                <p><strong>URI:</strong> {{ is_array($entry['uri'] ?? '') ? json_encode($entry['uri']) : ($entry['uri'] ?? 'N/A') }}</p>
                                                <p><strong>{{ __('Mensaje') }}:</strong> {{ is_array($entry['message'] ?? '') ? json_encode($entry['message']) : ($entry['message'] ?? 'N/A') }}</p>
                                                <p><strong>Rule ID:</strong> {{ is_array($entry['ruleId'] ?? '') ? json_encode($entry['ruleId']) : ($entry['ruleId'] ?? 'N/A') }}</p>
                                            </div>
                                        @endforeach
                                    @elseif (is_array($log))
                                        @foreach ($log as $item)
                                            <p class="font-mono text-sm mb-1">{{ is_array($item) ? json_encode($item) : $item }}</p>
                                        @endforeach
                                    @else
                                        <p class="font-mono text-sm">{{ is_array($log) ? json_encode($log) : $log }}</p>
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
                <h3 class="text-lg font-semibold mb-2">{{ __('firewall.analysis') }}</h3>
                <div class="bg-gray-100 p-4 rounded">
                    @foreach ($report->analysis as $key => $value)
                        <div class="mb-2">
                            <strong>{{ Str::title(str_replace('_', ' ', $key)) }}:</strong>
                            @if(is_array($value))
                                <ul class="list-disc pl-5">
                                    @foreach($value as $item)
                                        <li>{{ is_array($item) ? json_encode($item) : $item }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <span>{{ $value }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <h3 class="text-lg font-semibold mt-0 mb-3 text-blue-800">{{ __('firewall.help.need_help') }}</h3>
            <p class="mb-2">{{ __('Consulte la documentación para más información sobre desbloqueo de IPs') }}</p>
            <p>{{ __('Si tiene alguna duda, contacte con soporte') }}</p>
        </div>

        <div class="mt-6 text-center text-gray-600">
            <p>{{ __('firewall.email.thank_you') }}<br>{{ __('firewall.email.support_team', ['company' => config('company.name')]) }}</p>
        </div>

        <div class="mt-12 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
            <p class="space-x-4">
                <a href="{{ config('company.legal.privacy_policy_url') }}" class="text-gray-500 hover:text-gray-700" target="_blank">{{ __('Política de Privacidad') }}</a>
                <span>|</span>
                <a href="{{ config('company.legal.terms_url') }}" class="text-gray-500 hover:text-gray-700" target="_blank">{{ __('Términos de Servicio') }}</a>
                <span>|</span>
                <a href="{{ config('company.legal.data_protection_url') }}" class="text-gray-500 hover:text-gray-700" target="_blank">{{ __('Protección de Datos') }}</a>
            </p>
            <p class="mt-2">&copy; {{ date('Y') }} {{ config('company.name') }}. {{ __('Todos los derechos reservados') }}.</p>
        </div>
    </div>
@endsection
