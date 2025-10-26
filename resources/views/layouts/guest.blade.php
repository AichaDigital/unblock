<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="winter">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Initialize theme from localStorage before render --}}
        <script>
            (function() {
                const theme = localStorage.getItem('theme') || 'auto';
                let actualTheme;
                if (theme === 'auto') {
                    actualTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'winter';
                } else {
                    actualTheme = theme;
                }
                document.documentElement.setAttribute('data-theme', actualTheme);
                document.documentElement.className = actualTheme === 'dark' ? 'dark' : 'light';
            })();
        </script>

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="relative min-h-screen overflow-hidden bg-base-200 font-sans antialiased">

        <!-- Theme Switcher (fixed top-right) -->
        <div class="fixed top-4 right-4 z-50">
            <x-theme-switcher />
        </div>

        <!-- Contenido principal -->
        <div class="relative z-10 min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4">
            <div class="w-full sm:max-w-lg mt-6 px-6 py-6 bg-base-100 backdrop-blur-sm shadow-2xl ring-1 ring-base-300 overflow-hidden rounded-2xl">
                {{ $slot }}
            </div>
        </div>

        <!-- Notificaciones DaisyUI (reemplaza WireUI) -->
        <x-notifications />
    </body>
</html>
