{{-- resources/views/layouts/guest.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    </head>
    <body class="font-sans text-gray-900 antialiased">

        {{-- *** INÍCIO DA MODIFICAÇÃO *** --}}
        {{-- Inclui o cabeçalho USP --}}
        <x-usp.header />
        {{-- *** FIM DA MODIFICAÇÃO *** --}}

        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
            {{-- O conteúdo das páginas (login, register, etc.) virá aqui --}}
            {{-- A seção do logo padrão foi removida daqui e será colocada individualmente --}}
            {{-- ou mantida nas páginas específicas que ainda o usam (register, forgot-password) --}}

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>