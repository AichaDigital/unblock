<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="winter">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Initialize theme from localStorage before render --}}
        <script>
            (function() {
                const theme = localStorage.getItem('theme');

                if (theme && theme !== 'auto') {
                    // Usuario tiene preferencia manual guardada
                    document.documentElement.setAttribute('data-theme', theme);
                    document.documentElement.className = theme === 'dark' ? 'dark' : 'light';
                } else {
                    // Auto-detect system preference (DaisyUI --prefersdark will handle this)
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const actualTheme = prefersDark ? 'dark' : 'winter';
                    document.documentElement.setAttribute('data-theme', actualTheme);
                    document.documentElement.className = prefersDark ? 'dark' : 'light';
                }
            })();
        </script>

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-base-200">
            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-base-100 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        <!-- Notificaciones DaisyUI -->
        <x-notifications />
    </body>
</html>
