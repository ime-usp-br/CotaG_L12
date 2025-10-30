<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Lancamento\ManageLancamentos;

Route::view('/', 'welcome');

Route::get('/lancamento', ManageLancamentos::class)
    ->middleware([
        'auth', // Protege contra visitantes 
        'verified', // Garante que o e-mail do usuário foi verificado
        'can:operar-sistema' // Protege por permissão 
    ])
    ->name('lancamento');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
