@props(['class' => ''])

@php
    $logoLightPath = setting('company_logo_light');
    $logoDarkPath = setting('company_logo_dark');
    $logoLightUrl = $logoLightPath ? asset('storage/' . $logoLightPath) : null;
    $logoDarkUrl = $logoDarkPath ? asset('storage/' . $logoDarkPath) : null;
    $companyName = setting('company_name') ?: config('company.name', config('app.name'));
@endphp

@if($logoLightUrl || $logoDarkUrl)
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center ' . $class]) }}>
        @if($logoLightUrl)
            <img
                src="{{ $logoLightUrl }}"
                alt="{{ $companyName }}"
                class="max-w-full max-h-full object-contain logo-light"
            >
        @endif
        @if($logoDarkUrl)
            <img
                src="{{ $logoDarkUrl }}"
                alt="{{ $companyName }}"
                class="max-w-full max-h-full object-contain logo-dark"
            >
        @endif
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center ' . $class]) }}>
        <span class="text-2xl font-bold text-base-content">
            {{ $companyName }}
        </span>
    </div>
@endif

