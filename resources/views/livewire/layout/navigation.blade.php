<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    @role('Operador')
                        <a href="{{ route('lancamento') }}" wire:navigate>
                    @else
                        <a href="{{ route('dashboard') }}" wire:navigate>
                    @endrole
                        <img src="{{ Vite::asset('resources/images/ime/logo-horizontal-simplificada-padrao.png') }}" alt="Logo IME-USP" class="w-20 h-auto block dark:hidden" dusk="ime-logo-light">
                        <img src="{{ Vite::asset('resources/images/ime/logo-horizontal-simplificada-branca.png') }}" alt="Logo IME-USP" class="w-20 h-auto hidden dark:block" dusk="ime-logo-dark">
                    </a>
                </div>

                {{-- **** BLOCO MODIFICADO (Links removidos) **** --}}
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @role('Operador')
                        {{-- Deixamos apenas o link principal --}}
                        <x-nav-link :href="route('lancamento')" :active="request()->routeIs('lancamento')" wire:navigate>
                            {{ __('Realizar Lançamento') }}
                        </x-nav-link>
                        {{-- Cotas, Cota Especial e Extrato foram REMOVIDOS daqui --}}
                    @else
                        {{-- Navegação padrão para Admin e outros --}}
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </x-nav-link>
                        @role('Admin')
                            <x-nav-link :href="url('/admin')" :active="request()->is('admin*')" wire:navigate>
                                {{ __('Painel Admin') }}
                            </x-nav-link>
                        @endrole
                    @endrole
                </div>
                {{-- **** FIM DO BLOCO **** --}}

            </div>

            {{-- **** BLOCO MODIFICADO (Link "Painel de Admin" adicionado) **** --}}
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        {{-- ADICIONADO: Link para o painel admin (Filament) --}}
                        {{-- Visível para Admin E Operador (para o Operador ver Cotas, etc.) --}}
                        @if(auth()->user()->hasRole(['Admin', 'Operador']))
                            <x-dropdown-link :href="url('/admin')">
                                {{ __('Painel de Admin') }}
                            </x-dropdown-link>
                        @endif

                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>
            {{-- **** FIM DO BLOCO **** --}}


            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        
        {{-- **** BLOCO MODIFICADO (Links removidos) **** --}}
        <div class="pt-2 pb-3 space-y-1">
            @role('Operador')
                {{-- Deixamos apenas o link principal --}}
                <x-responsive-nav-link :href="route('lancamento')" :active="request()->routeIs('lancamento')" wire:navigate>
                    {{ __('Realizar Lançamento') }}
                </x-responsive-nav-link>
                {{-- Cotas, Cota Especial e Extrato foram REMOVIDOS daqui --}}
            @else
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                @role('Admin')
                    <x-responsive-nav-link :href="url('/admin')" :active="request()->is('admin*')" wire:navigate>
                        {{ __('Painel Admin') }}
                    </x-responsive-nav-link>
                @endrole
            @endrole
        </div>
        {{-- **** FIM DO BLOCO **** --}}


        {{-- **** BLOCO MODIFICADO (Link "Painel de Admin" adicionado) **** --}}
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                {{-- ADICIONADO: Link para o painel admin (Filament) --}}
                @if(auth()->user()->hasRole(['Admin', 'Operador']))
                    <x-responsive-nav-link :href="url('/admin')">
                        {{ __('Painel de Admin') }}
                    </x-responsive-nav-link>
                @endif

                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
        {{-- **** FIM DO BLOCO **** --}}
    </div>
</nav>