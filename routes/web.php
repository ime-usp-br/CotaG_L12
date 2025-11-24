<?php

use App\Livewire\Lancamento\ManageLancamentos;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->hasRole('Operador') || $user->hasRole('Admin')) {
            return redirect()->route('lancamento');
        }

        return redirect()->route('dashboard');
    }

    return view('welcome');
});

Route::get('/lancamento', ManageLancamentos::class)
    ->middleware([
        'auth', // Protege contra visitantes
        'verified', // Garante que o e-mail do usuário foi verificado
        'can:operar-sistema', // Protege por permissão
    ])
    ->name('lancamento');

Route::get('/dashboard', function () {
    /** @var \App\Models\User $user */
    $user = auth()->user();

    // Se o usuário for um Operador ou Admin, redireciona para a ferramenta principal.
    if ($user->hasRole('Operador') || $user->hasRole('Admin')) {
        return redirect()->route('lancamento');
    }

    // Se for Admin ou outro, mostra o dashboard normal.
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
