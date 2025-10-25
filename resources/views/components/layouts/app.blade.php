<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="winter" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

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

    <!-- Estilos -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative min-h-screen overflow-hidden bg-emerald-100 font-sans antialiased">

    <x-emerald-background />

    <!-- Contenido principal -->
    <div class="relative z-10 min-h-screen">
        <main>
            {{ $slot }} <!-- Este es el lugar donde se insertará el contenido de las vistas Livewire -->
        </main>
    </div>

    <!-- Notificaciones DaisyUI (reemplaza WireUI) -->
    <x-notifications />



<footer>
    <!-- Pie de página -->
</footer>

</body>
</html>
