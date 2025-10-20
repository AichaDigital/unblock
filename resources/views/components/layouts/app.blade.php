<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Estilos -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @wireUiScripts
</head>
<body class="relative min-h-screen overflow-hidden bg-emerald-100 font-sans antialiased">

    <x-emerald-background />

    <!-- Contenido principal -->
    <div class="relative z-10 min-h-screen">
        <main>
            {{ $slot }} <!-- Este es el lugar donde se insertará el contenido de las vistas Livewire -->
        </main>
    </div>

    <!-- Notificaciones con posicionamiento fijo -->
    <div class="fixed top-4 right-4 z-50 max-w-sm w-full sm:max-w-md">
        <x-notifications />
    </div>



<footer>
    <!-- Pie de página -->
</footer>

</body>
</html>
