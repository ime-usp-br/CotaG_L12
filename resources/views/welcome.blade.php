<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'CotaG') }} - Gestão de Cotas de Impressão</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts e Estilos via Vite -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100">

        {{-- Cabeçalho USP --}}
        <x-usp.header />

        <div class="relative min-h-screen flex flex-col">
            
            {{-- Hero Section --}}
            <div class="relative isolate overflow-hidden bg-gradient-to-b from-indigo-100/20 pt-14 dark:from-indigo-950/40">
                <div class="mx-auto max-w-7xl px-6 py-24 sm:py-32 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">
                            Sistema de Gestão de Cotas de Impressão
                        </h1>
                        <p class="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">
                            O <strong>CotaG</strong> é o sistema institucional do IME-USP para controle e gerenciamento de cotas de impressão e cópias da comunidade acadêmica.
                        </p>
                        <div class="mt-10 flex items-center justify-center gap-x-6">
                            @auth
                                <a href="{{ route('dashboard') }}" class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    Acessar Painel
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    Entrar com Senha Única
                                </a>
                                <a href="{{ route('login.local') }}" class="text-sm font-semibold leading-6 text-gray-900 dark:text-white">
                                    Login Local <span aria-hidden="true">→</span>
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>

            {{-- Features Section --}}
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-12">
                <div class="mx-auto max-w-2xl lg:max-w-none">
                    <dl class="grid max-w-xl grid-cols-1 gap-x-8 gap-y-16 lg:max-w-none lg:grid-cols-3">
                        <div class="flex flex-col">
                            <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-gray-900 dark:text-white">
                                <svg class="h-5 w-5 flex-none text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                </svg>
                                Saldo em Tempo Real
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                <p class="flex-auto">Cálculo automático e dinâmico de saldos de cotas para docentes, funcionários e alunos.</p>
                            </dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-gray-900 dark:text-white">
                                <svg class="h-5 w-5 flex-none text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                Histórico de Operações
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                <p class="flex-auto">Registro detalhado de todos os lançamentos de débitos e créditos de impressão realizados.</p>
                            </dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-gray-900 dark:text-white">
                                <svg class="h-5 w-5 flex-none text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                                </svg>
                                Integração Institucional
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600 dark:text-gray-400">
                                <p class="flex-auto">Sincronização com bases de dados da USP e autenticação centralizada via Senha Única.</p>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Footer --}}
            <footer class="mt-auto py-8 text-center text-sm text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'CotaG') }}. Todos os direitos reservados.</p>
                <p class="mt-1">Desenvolvido pelo IME-USP.</p>
            </footer>
        </div>
    </body>
</html>