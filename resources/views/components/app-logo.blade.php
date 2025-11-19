@props(['class' => ''])

@php
    $logoPath = setting('company_logo');
    $logoUrl = $logoPath ? asset('storage/' . $logoPath) : null;
    $companyName = setting('company_name') ?: config('company.name', config('app.name'));
@endphp

@if($logoUrl)
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center ' . $class]) }}>
        <img
            src="{{ $logoUrl }}"
            alt="{{ $companyName }}"
            class="max-w-full max-h-full object-contain"
        >
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center ' . $class]) }}>
        <span class="text-2xl font-bold text-base-content">
            {{ $companyName }}
        </span>
    </div>
@endif

