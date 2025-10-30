<?php

namespace App\Livewire\Lancamento;
use App\Services\ReplicadoService;
use App\Models\Pessoa;
use App\Services\CotaService;
use Illuminate\Database\Eloquent\Collection;

use Livewire\Component;

class ManageLancamentos extends Component
{
    public string $termoBusca = '';
    public string $termoBuscado = '';
    public ?Collection $resultadosBusca = null;
    
    // Armazena a pessoa que foi selecionada da busca
    public ?Pessoa $pessoaSelecionada = null; 
    public ?int $saldoAtual = null;

    /**
     * (Critério 2 e 3)
     * Executa a busca de pessoas usando o ReplicadoService.
     */
    public function buscarPessoa(ReplicadoService $replicadoService): void
    {
        $this->termoBuscado = $this->termoBusca;
        $this->validate([
            'termoBusca' => 'required|string|min:3',
        ], [
            'termoBusca.required' => 'O campo de busca é obrigatório.',
            'termoBusca.min' => 'Digite pelo menos 3 caracteres para buscar.',
        ]);
        
        // Limpa a seleção anterior antes de uma nova busca
        $this->limparBusca();
        $this->resultadosBusca = $replicadoService->buscarPessoas($this->termoBuscado);
    }

    /**
     * Ação chamada quando o operador clica em "Selecionar" na lista.
     */
    public function selecionarPessoa(int $codigoPessoa, CotaService $cotaService): void
    {
        // Encontra a pessoa e a armazena
        $this->pessoaSelecionada = Pessoa::find($codigoPessoa);
        
        // Calcula o saldo (usando o Accessor que criamos!)
        $this->saldoAtual = $this->pessoaSelecionada?->saldo;

        // Limpa os resultados da busca para "limpar" a UI
        $this->resultadosBusca = null;
        $this->termoBusca = ''; // Limpa o campo de busca
    }

    /**
     * Limpa a busca e a seleção.
     */
    public function limparBusca(): void
    {
        $this->reset('termoBusca', 'resultadosBusca', 'pessoaSelecionada', 'saldoAtual');
    }
    public function render()
    {
        return view('livewire.lancamento.manage-lancamentos')
                ->layout('layouts.app');
    }
}
