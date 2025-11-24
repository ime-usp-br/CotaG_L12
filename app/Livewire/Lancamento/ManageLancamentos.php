<?php

namespace App\Livewire\Lancamento;

use App\Exceptions\ReplicadoServiceException;
use App\Models\Pessoa;
use App\Models\Vinculo;
use App\Services\CotaService;
use App\Services\ReplicadoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageLancamentos extends Component
{
    use WithPagination;

    // Propriedades da Busca
    public string $termoBusca = '';

    public string $termoBuscado = '';

    public ?Collection $resultadosBusca = null;

    // Propriedades da Pessoa Selecionada (Layout da Imagem)
    public ?Pessoa $pessoaSelecionada = null;

    public ?int $saldoAtual = null;

    public ?int $cotaBase = null;

    public ?int $cotaEspecial = null;

    public ?Collection $vinculos = null;
    // public ?Collection $lancamentosMes = null;

    // Controla a visibilidade do modal
    public bool $showLancamentoModal = false;

    // Título do modal (ex: "Registrar Débito")
    public string $modalLancamentoTipo = '';

    /**
     * Critério 1 e 4: Propriedade para o valor, com validação nativa.
     */
    #[Rule('required|integer|min:1', as: 'Valor')]
    public ?int $valorLancamento = null;

    /**
     * Critério 1 e 4: Propriedade para o tipo, com validação nativa.
     * 0 = Crédito, 1 = Débito (conforme migração)
     */
    #[Rule('required|integer|in:0,1', as: 'Tipo de Lançamento')]
    public ?int $tipoLancamento = null;

    /**
     * Limpa toda a seleção e volta para a tela de busca.
     */
    public function limparBusca(): void
    {
        // Limpa tudo
        $this->reset(
            'termoBusca', 'termoBuscado', 'resultadosBusca',
            'pessoaSelecionada', 'saldoAtual', 'cotaBase',
            'cotaEspecial', 'vinculos', 'valorLancamento'
        );
        $this->resetErrorBag(); // Limpa erros de validação
        $this->resetPage();
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
            $this->cotaEspecial = $cotaService->getCotaEspecial($this->pessoaSelecionada);
            $this->vinculos = $this->pessoaSelecionada->vinculos; // Carrega a relação

            // Limpa a UI de busca
            $this->resultadosBusca = null;
            $this->termoBusca = '';
            $this->termoBuscado = $this->pessoaSelecionada->nome_pessoa; // Mostra quem está selecionado

            $this->resetPage();

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
        if (! empty($vinculosReplicado)) {
            foreach ($vinculosReplicado as $vinculo) {
                if (is_string($vinculo) && ! empty($vinculo)) {
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
                    ->orWhere('nome_pessoa', 'like', '%'.$criterio.'%');
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
                if ($pessoaDados) {
                    $pessoasEncontradasReplicado[] = $pessoaDados;
                }
            } elseif (str_contains($criterio, '@')) {
                $pessoaDados = $replicadoService->buscarPessoaPorEmail($criterio);
                if ($pessoaDados) {
                    $pessoasEncontradasReplicado[] = $pessoaDados;
                }
            } else {
                $pessoasEncontradasReplicado = $replicadoService->buscarPessoasPorNome($criterio);
            }
        } catch (ReplicadoServiceException $e) {
            Log::error('Erro ao buscar no Replicado: '.$e->getMessage());
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
            $pessoasLocais = new Collection;
            foreach ($pessoasEncontradasReplicado as $dadosPessoa) {
                $pessoaLocal = $this->importarPessoa($dadosPessoa);
                $pessoasLocais->push($pessoaLocal);
            }
            $this->resultadosBusca = $pessoasLocais;
        }
    }

    /**
     * Critério 2 e 5: Salva o novo lançamento (Débito/Crédito).
     *
     * Este método é acionado pelo 'wire:submit' do formulário.
     * Ele valida os dados usando os atributos #[Rule], cria o lançamento
     * e atualiza a interface.
     */
    /**
     * NOVO: Abre o modal de lançamento.
     * Define o tipo (Débito/Crédito) e prepara o formulário.
     */
    public function abrirModalLancamento(int $tipo)
    {
        $this->resetErrorBag(); // Limpa erros de validação anteriores
        $this->reset('valorLancamento'); // Limpa o valor

        $this->tipoLancamento = $tipo;
        $this->modalLancamentoTipo = ($tipo == 1) ? 'Débito' : 'Crédito'; // 1 = Débito, 0 = Crédito

        $this->showLancamentoModal = true;
    }

    /**
     * NOVO: Fecha o modal.
     */
    public function fecharModal()
    {
        $this->showLancamentoModal = false;
        $this->reset('valorLancamento', 'tipoLancamento', 'modalLancamentoTipo');
    }

    /**
     * MODIFICADO: Salva o lançamento (agora chamado pelo modal).
     *
     * Este método agora DELEGA a lógica de criação para o CotaService,
     * que sabe como lidar com o consumo de cotas especiais.
     */
    public function salvarLancamento(CotaService $cotaService)
    {
        // 1. Valida APENAS o valor (o tipo já foi definido)
        $this->validateOnly('valorLancamento');

        // 2. Verifica se a pessoa e o tipo estão definidos
        if (! $this->pessoaSelecionada || $this->tipoLancamento === null) {
            $this->dispatch('alert', 'Erro: Sessão inválida ou pessoa não selecionada.');

            return;
        }

        // 3. CHAMA O SERVIÇO PARA REGISTRAR O LANÇAMENTO
        // A lógica de "consumir cota especial" agora está dentro deste método.
        $cotaService->registrarDebitoOuCredito(
            $this->pessoaSelecionada,
            $this->valorLancamento,
            $this->tipoLancamento,
            auth()->id() // Passa o ID do operador logado
        );

        // 4. Recarrega todos os dados da pessoa (saldo, histórico)
        $this->selecionarPessoa($this->pessoaSelecionada->codigo_pessoa, $cotaService);

        // 5. Fecha o modal
        $this->fecharModal();

        // 6. Mostra uma notificação de sucesso
        $this->dispatch('alert', 'Lançamento realizado com sucesso!');
    }

    public function render()
    {
        return view('livewire.lancamento.manage-lancamentos', [
            // Passa os lançamentos paginados para a view
            'lancamentosMes' => $this->pessoaSelecionada
                ? $this->pessoaSelecionada->lancamentos()
                    ->whereYear('data', now()->year)
                    ->whereMonth('data', now()->month)
                    ->with('usuario')
                    ->orderBy('data', 'desc')
                    ->paginate(5) // <-- Paginar por 5
                : null, // Passa null se ninguém estiver selecionado
        ])->layout('layouts.app');
    }
}
