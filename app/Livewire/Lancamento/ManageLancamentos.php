<?php

namespace App\Livewire\Lancamento;

use Livewire\Component;

class ManageLancamentos extends Component
{
    public function render()
    {
        return view('livewire.lancamento.manage-lancamentos')
                ->layout('layouts.app');
    }
}
