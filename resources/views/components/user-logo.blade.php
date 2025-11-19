@props(['class' => ''])

@php
    $logoUrl = null;

    // Try to get logo from authenticated user
    if (auth()->check() && auth()->user()->logo_url) {
        $logoUrl = auth()->user()->logo_url;
    }
    // If not authenticated, try to get logo from any admin user (for guest pages)
    elseif (!auth()->check()) {
        $adminUser = \App\Models\User::where('is_admin', true)
            ->whereNotNull('logo_path')
            ->first();

        if ($adminUser && $adminUser->logo_url) {
            $logoUrl = $adminUser->logo_url;
        }
    }
@endphp

@if($logoUrl)
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center '.$class]) }}>
        <img
            src="{{ $logoUrl }}"
            alt="Logo"
            class="max-w-full max-h-full object-contain"
        >
    </div>
@endif
