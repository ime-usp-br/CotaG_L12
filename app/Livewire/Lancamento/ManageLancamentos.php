<?php

namespace App\Livewire\Lancamento;
use App\Services\ReplicadoService;
use App\Models\Pessoa;
use App\Models\Vinculo;
use App\Models\Lancamento;
use App\Services\CotaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ReplicadoServiceException;
use Illuminate\Database\Eloquent\Builder;

use Livewire\Component;

class ManageLancamentos extends Component
{
    // Propriedades da Busca
    public string $termoBusca = '';
    public string $termoBuscado = '';
    public ?Collection $resultadosBusca = null;
    
    // Propriedades da Pessoa Selecionada (Layout da Imagem)
    public ?Pessoa $pessoaSelecionada = null; 
    public ?int $saldoAtual = null;
    public ?int $cotaBase = null;
    public ?Collection $vinculos = null;
    public ?Collection $lancamentosMes = null;
    
    // Propriedades do Formulário de Lançamento
    public ?int $valorLancamento = null;

    /**
     * Limpa toda a seleção e volta para a tela de busca.
     */
    public function limparBusca(): void
    {
        // Limpa tudo
        $this->reset(
            'termoBusca', 'termoBuscado', 'resultadosBusca', 
            'pessoaSelecionada', 'saldoAtual', 'cotaBase', 
            'vinculos', 'lancamentosMes', 'valorLancamento'
        );
        $this->resetErrorBag(); // Limpa erros de validação
    }

    /**
     * Ação principal: Seleciona a pessoa e carrega todos os seus dados.
     */
    public function selecionarPessoa(int $codigoPessoa, CotaService $cotaService): void
    {
        // Encontra a pessoa no banco local
        $this->pessoaSelecionada = Pessoa::find($codigoPessoa);
        
        if ($this->pessoaSelecionada) {
            // Carrega os dados para o layout da imagem
            $this->saldoAtual = $this->pessoaSelecionada->saldo; // Usa o Accessor
            $this->cotaBase = $cotaService->getCotaBase($this->pessoaSelecionada);
            $this->vinculos = $this->pessoaSelecionada->vinculos; // Carrega a relação
            
            // Carrega o histórico de lançamentos do mês para a tabela
            $this->lancamentosMes = $this->pessoaSelecionada->lancamentos()
                                        ->whereYear('data', now()->year)
                                        ->whereMonth('data', now()->month)
                                        ->with('usuario') // Otimiza para pegar o nome do operador
                                        ->orderBy('data', 'desc')
                                        ->get();

            // Limpa a UI de busca
            $this->resultadosBusca = null;
            $this->termoBusca = '';
            $this->termoBuscado = $this->pessoaSelecionada->nome_pessoa; // Mostra quem está selecionado
        } else {
            $this->dispatch('alert', 'Erro: Pessoa não encontrada localmente após a seleção.');
            $this->limparBusca();
        }
    }

    /**
     * Importa/Atualiza dados do Replicado para o banco local.
     */
    private function importarPessoa(array $dadosReplicado): Pessoa
    {
        $codpes = $dadosReplicado['codpes'];
        $nome = $dadosReplicado['nompes'];

        // Cria ou atualiza a Pessoa
        $pessoaLocal = Pessoa::firstOrCreate(
            ['codigo_pessoa' => $codpes],
            ['nome_pessoa' => $nome]
        );

        // Sincroniza os vínculos
        $vinculosReplicado = $dadosReplicado['vinculos'] ?? [];
        $vinculosAtuais = [];
        if (!empty($vinculosReplicado)) {
            foreach ($vinculosReplicado as $vinculo) {
                if (is_string($vinculo) && !empty($vinculo)) {
                    $vinculosAtuais[] = $vinculo;
                    Vinculo::firstOrCreate(
                        ['codigo_pessoa' => $codpes, 'tipo_vinculo' => $vinculo]
                    );
                }
            }
        }
        // Remove vínculos locais que não estão mais ativos no Replicado
        Vinculo::where('codigo_pessoa', $codpes)->whereNotIn('tipo_vinculo', $vinculosAtuais)->delete();
        
        return $pessoaLocal;
    }

    /**
     * Lógica de busca principal (Busca Local, depois Replicado).
     */
    public function buscarPessoa(ReplicadoService $replicadoService, CotaService $cotaService): void
    {
        $this->termoBuscado = $this->termoBusca;
        $this->validate(
            ['termoBusca' => 'required|string|min:3'],
            [
                'termoBusca.required' => 'O campo de busca é obrigatório.',
                'termoBusca.min' => 'Digite pelo menos 3 caracteres para buscar.',
            ]
        );
        
        $this->reset('resultadosBusca', 'pessoaSelecionada', 'saldoAtual');
        $criterio = trim($this->termoBusca);

        // --- ETAPA 1: BUSCA LOCAL PRIMEIRO ---
        $pessoasLocais = Pessoa::query()
            ->where(function (Builder $query) use ($criterio) {
                $query->where('codigo_pessoa', $criterio)
                    ->orWhere('nome_pessoa', 'like', '%' . $criterio . '%');
            })
            ->get();

        if ($pessoasLocais->isNotEmpty()) {
            if ($pessoasLocais->count() === 1) {
                $this->selecionarPessoa($pessoasLocais->first()->codigo_pessoa, $cotaService);
            } else {
                $this->resultadosBusca = $pessoasLocais;
            }
            return;
        }

        // --- ETAPA 2: BUSCA NO REPLICADO (Fallback) ---
        $pessoasEncontradasReplicado = [];
        try {
            if (is_numeric($criterio)) {
                $pessoaDados = $replicadoService->buscarPessoaPorCodpes($criterio);
                if ($pessoaDados) $pessoasEncontradasReplicado[] = $pessoaDados;
            } elseif (str_contains($criterio, '@')) {
                $pessoaDados = $replicadoService->buscarPessoaPorEmail($criterio);
                if ($pessoaDados) $pessoasEncontradasReplicado[] = $pessoaDados;
            } else {
                $pessoasEncontradasReplicado = $replicadoService->buscarPessoasPorNome($criterio);
            }
        } catch (ReplicadoServiceException $e) {
            Log::error('Erro ao buscar no Replicado: ' . $e->getMessage());
            $this->addError('termoBusca', 'Falha na comunicação com o banco de dados Replicado. Tente novamente mais tarde.');
            return;
        }

        if (empty($pessoasEncontradasReplicado)) {
            $this->addError('termoBusca', 'Nenhuma pessoa encontrada com este critério (nem local, nem no Replicado).');
            return;
        }

        // --- ETAPA 3: IMPORTAÇÃO E EXIBIÇÃO ---
        if (count($pessoasEncontradasReplicado) === 1) {
            $pessoaLocal = $this->importarPessoa($pessoasEncontradasReplicado[0]);
            $this->selecionarPessoa($pessoaLocal->codigo_pessoa, $cotaService);
            return;
        }
        
        if (count($pessoasEncontradasReplicado) > 1) {
            $pessoasLocais = new Collection();
            foreach ($pessoasEncontradasReplicado as $dadosPessoa) {
                $pessoaLocal = $this->importarPessoa($dadosPessoa);
                $pessoasLocais->push($pessoaLocal);
            }
            $this->resultadosBusca = $pessoasLocais;
        }
    }

    /**
     * Ação dos botões "Débito" (1) e "Crédito" (0).
     */
    public function realizarLancamento(int $tipoLancamento, CotaService $cotaService)
    {
        // Valida apenas o valor do lançamento
        $this->validate(
            ['valorLancamento' => 'required|integer|min:1'],
            [
                'valorLancamento.required' => 'O campo Valor é obrigatório.',
                'valorLancamento.integer' => 'O valor deve ser um número inteiro.',
                'valorLancamento.min' => 'O valor deve ser pelo menos 1.',
            ]
        );

        if (!$this->pessoaSelecionada) {
            $this->dispatch('alert', 'Erro: Nenhuma pessoa selecionada.');
            return;
        }

        // Cria o lançamento
        Lancamento::create([
            'data' => now(),
            'tipo_lancamento' => $tipoLancamento, // 0 = Crédito, 1 = Débito
            'valor' => $this->valorLancamento,
            'codigo_pessoa' => $this->pessoaSelecionada->codigo_pessoa,
            'usuario_id' => auth()->id(), // Pega o ID do operador logado
        ]);

        // Recarrega todos os dados da pessoa (saldo, histórico)
        $this->selecionarPessoa($this->pessoaSelecionada->codigo_pessoa, $cotaService);
        
        // Limpa o campo de valor
        $this->reset('valorLancamento');
        
        // Mostra uma notificação de sucesso
        $this->dispatch('alert', 'Lançamento realizado com sucesso!');
    }
    
    public function render()
    {
        return view('livewire.lancamento.manage-lancamentos')
                ->layout('layouts.app');
    }
}
