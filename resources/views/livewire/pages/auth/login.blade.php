{{-- resources/views/livewire/pages/auth/login.blade.php --}}
<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component // Garante que usa o layout guest modificado
{
    public LoginForm $form;

    /**
     * Processa uma tentativa de autenticação recebida.
     * (...) // restante do código PHP permanece igual
     */
    public function login(): void
    {
        $this->validate(); // Valida o LoginForm

        $this->form->authenticate(); // Tenta autenticar (pode lançar ValidationException em caso de falha)

        Session::regenerate();

        $this->redirect(route('lancamento', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="flex justify-center mb-4">
        <a href="/" wire:navigate>
            <img src="{{ Vite::asset('resources/images/ime/logo-vertical-simplificada-padrao.png') }}" alt="Logo IME-USP" class="w-20 h-auto block dark:hidden" dusk="ime-logo-light">
            <img src="{{ Vite::asset('resources/images/ime/logo-vertical-simplificada-branca.png') }}" alt="Logo IME-USP" class="w-20 h-auto hidden dark:block" dusk="ime-logo-dark">
        </a>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            {{-- AC8: Adicionado seletor dusk --}}
            {{-- AC10: Added aria-describedby --}}
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" dusk="email-input" aria-describedby="email-error" />
            {{-- AC10: Added dusk selector and id for error --}}
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" dusk="email-error" id="email-error"/>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            {{-- AC8: Adicionado seletor dusk --}}
            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" dusk="password-input" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('register'))
                {{-- AC8: Adicionado seletor dusk --}}
                <a href="{{ route('register') }}" wire:navigate class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" dusk="register-link">
                    {{ __('Register') }}
                </a>
            @endif

            @if (Route::has('password.request'))
                {{-- AC8: Adicionado seletor dusk --}}
                <a class="ms-4 underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}" wire:navigate dusk="forgot-password-link">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            {{-- AC8: Adicionado seletor dusk --}}
            <x-primary-button class="ms-4" dusk="login-button">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    {{-- Botão Login com Senha Única USP --}}
    <div class="flex items-center justify-center mt-4">
        {{-- AC8: Adicionado seletor dusk --}}
        <a href="{{ route('login') }}" class="w-full inline-flex items-center justify-center px-4 py-2 bg-yellow-500 dark:bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-900 uppercase tracking-widest hover:bg-yellow-400 dark:hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150" dusk="senhaunica-login-button">
            {{ __('Login with Senha Única USP') }}
        </a>
    </div>
</div>