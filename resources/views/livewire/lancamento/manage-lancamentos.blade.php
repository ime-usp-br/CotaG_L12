<div>
    {{-- Slot do Cabeçalho (Padrão do Breeze) --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Operação da Gráfica') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <div wire:key="aba-lancamento">
                        
                        {{-- **** SEÇÃO DE BUSCA **** --}}
                        {{-- Esta seção some se uma pessoa for selecionada --}}
                        @if (!$pessoaSelecionada)
                            <div wire:key="busca-container">
                                <form wire:submit="buscarPessoa">
                                    <x-input-label for="termoBusca" value="Buscar Pessoa (NUSP ou Nome)" />
                                    <div class="flex items-center mt-1">
                                        <x-text-input wire:model="termoBusca" id="termoBusca" class="block w-full" type="text" />
                                        
                                        <x-primary-button class="ml-4" type="submit" wire:loading.attr="disabled">
                                            {{-- Mostra "Buscando..." durante a busca --}}
                                            <span wire:loading.remove wire:target="buscarPessoa">
                                                Buscar
                                            </span>
                                            <span wire:loading wire:target="buscarPessoa">
                                                Buscando...
                                            </span>
                                        </x-primary-button>
                                        
                                        {{-- Botão Limpar só aparece se tiver algo digitado --}}
                                        @if($termoBusca)
                                        <x-secondary-button class="ml-2" type="button" wire:click="limparBusca">
                                            Limpar
                                        </x-secondary-button>
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('termoBusca')" class="mt-2" />
                                </form>

                                {{-- Exibição dos Resultados (Lista de Seleção) --}}
                                @if ($resultadosBusca !== null)
                                    <div class="mt-6">
                                        <h3 class="text-lg font-medium">Resultados da Busca ({{ $resultadosBusca->count() }})</h3>
                                        <ul class="mt-2 border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
                                            @forelse ($resultadosBusca as $pessoa)
                                                <li wire:key="pessoa-{{ $pessoa->codigo_pessoa }}" class="px-4 py-3 flex items-center justify-between">
                                                    <div>
                                                        <div class="font-medium">{{ $pessoa->nome_pessoa }}</div>
                                                        <div class="text-sm text-gray-500">{{ $pessoa->codigo_pessoa }}</div>
                                                    </div>
                                                    <x-primary-button type="button" wire:click="selecionarPessoa({{ $pessoa->codigo_pessoa }})">
                                                        Selecionar
                                                    </x-primary-button>
                                                </li>
                                            @empty
                                                <li class="px-4 py-3 text-gray-500">
                                                    Nenhuma pessoa encontrada com o critério "{{ $termoBuscado }}".
                                                </li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endif
                        {{-- **** FIM DA SEÇÃO DE BUSCA **** --}}


                        {{-- **** INÍCIO DA EXIBIÇÃO DA PESSOA SELECIONADA (Layout da Imagem) **** --}}
                        @if ($pessoaSelecionada)
                            <div wire:key="pessoa-selecionada-{{ $pessoaSelecionada->codigo_pessoa }}">
                                
                                {{-- Botão para "Voltar" / Limpar seleção --}}
                                <x-secondary-button type="button" wire:click="limparBusca" class="mb-4">
                                    &larr; Voltar para Busca
                                </x-secondary-button>

                                {{-- Layout principal (Grid 2 colunas) --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                    
                                    {{-- Coluna da Esquerda: Perfil e Formulário --}}
                                    <div class="space-y-6">
                                        
                                        {{-- Card: Perfil do Usuário --}}
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">Perfil do Usuário</h3>
                                            <div class="mt-2 space-y-2 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg shadow-sm">
                                                <p><span class="font-semibold text-gray-700 dark:text-gray-300">Nome:</span> {{ $pessoaSelecionada->nome_pessoa }}</p>
                                                <p><span class="font-semibold text-gray-700 dark:text-gray-300">NUSP:</span> {{ $pessoaSelecionada->codigo_pessoa }}</p>
                                                
                                                <p><span class="font-semibold text-gray-700 dark:text-gray-300">Vínculos:</span>
                                                    @forelse($vinculos as $vinculo)
                                                        <span class="inline-block bg-gray-200 dark:bg-gray-600 rounded-full px-2 py-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $vinculo->tipo_vinculo }}</span>
                                                    @empty
                                                        <span class="text-sm text-gray-500">Nenhum vínculo ativo</span>
                                                    @endforelse
                                                </p>
                                                
                                                <p><span class="font-semibold text-gray-700 dark:text-gray-300">Cota Base:</span> {{ $cotaBase }}</p>
                                                
                                                <p class="text-2xl font-bold {{ $saldoAtual >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                    Saldo: {{ $saldoAtual }}
                                                </p>
                                            </div>
                                        </div>

                                        {{-- Card: Ações de Lançamento (Novos Botões) --}}
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">Ações de Lançamento</h3>
                                            <div class="mt-2 space-y-4">
                                                <div class="flex space-x-4">
                                                    {{-- Botão Débito --}}
                                                    <x-danger-button type="button" class="flex-1 justify-center py-3" wire:click="abrirModalLancamento(1)">
                                                        Registrar Débito
                                                    </x-danger-button>
                                                    {{-- Botão Crédito --}}
                                                    <x-primary-button type="button" class="flex-1 justify-center py-3" wire:click="abrirModalLancamento(0)" style="background-color: #16a34a; --tw-shadow-color: #15803d;">
                                                        Registrar Crédito
                                                    </x-primary-button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Coluna da Direita: Histórico --}}
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">Histórico de Lançamentos do Mês</h3>
                                        <div class="mt-2 flow-root">
                                            <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                                                
                                                {{-- Caso 1: Não há NENHUM lançamento no total --}}
                                                @if ($lancamentosMes->total() == 0)
                                                    <li class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                        Nenhum lançamento este mês.
                                                    </li>
                                                
                                                {{-- Caso 2: Há lançamentos (pode ter 1, 2, 5, etc.) --}}
                                                @else
                                                    {{-- Renderiza os lançamentos reais desta página --}}
                                                    @foreach ($lancamentosMes as $lancamento)
                                                        <li class="py-3" wire:key="lanc-{{ $lancamento->id }}">
                                                            <div class="flex items-center space-x-4">
                                                                <div class="flex-shrink-0">
                                                                    @if($lancamento->tipo_lancamento == 1) {{-- Débito --}}
                                                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-red-100 dark:bg-red-900/50">
                                                                            <svg class="h-5 w-5 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                                <path fill-rule="evenodd" d="M4 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H4.75A.75.75 0 014 10z" clip-rule="evenodd" />
                                                                            </svg>
                                                                        </span>
                                                                    @else {{-- Crédito --}}
                                                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-green-100 dark:bg-green-900/50">
                                                                            <svg class="h-5 w-5 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                                                                            </svg>
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                                        {{ $lancamento->tipo_lancamento == 1 ? 'Débito' : 'Crédito' }} de {{ $lancamento->valor }}
                                                                    </p>
                                                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                                                        Operador: {{ $lancamento->usuario->name ?? 'Sistema' }}
                                                                    </p>
                                                                </div>
                                                                <div class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400">
                                                                    {{ \Carbon\Carbon::parse($lancamento->data)->format('d/m/y H:i') }}
                                                                </div>
                                                            </div>
                                                        </li>
                                                    @endforeach

                                                    {{-- **** AQUI ESTÁ A MÁGICA **** --}}
                                                    {{-- Preenche os slots vazios para manter a altura fixa de 5 itens --}}
                                                    @for ($i = $lancamentosMes->count(); $i < 5; $i++)
                                                        <li class="py-3 border-t-transparent dark:border-t-transparent" wire:key="placeholder-{{ $i }}">
                                                            
                                                            <div class="flex items-center space-x-4 invisible" aria-hidden="true">
                                                                <div class="flex-shrink-0">
                                                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-gray-100 dark:bg-gray-700"></span>
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <p class="text-sm font-medium text-gray-900 truncate">&nbsp;</p>
                                                                    <p class="text-sm text-gray-500 truncate">&nbsp;</p>
                                                                </div>
                                                                <div class="inline-flex items-center text-sm text-gray-500">
                                                                    &nbsp;
                                                                </div>
                                                            </div>
                                                        </li>
                                                    @endfor
                                                @endif
                                            </ul>

                                            {{-- Renderiza os links de paginação (1, 2, Próximo...) --}}
                                            @if ($lancamentosMes && $lancamentosMes->hasPages())
                                                <div class="mt-10">
                                                    {{ $lancamentosMes->links() }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        {{-- **** FIM DA EXIBIÇÃO DA PESSOA SELECIONADA **** --}}
                                
                    </div>
                </div>
            </div>
        </div>
    </div>

{{-- **** INÍCIO DO MODAL DE LANÇAMENTO (POP-UP) - VERSÃO CORRIGIDA (Sem Alpine.js) **** --}}
    @if($showLancamentoModal)
    <div 
        class="fixed inset-0 z-50 flex items-center justify-center p-4" 
        style="background-color: rgba(0, 0, 0, 0.75);"
    >
        {{-- Fundo do Modal (clicar para fechar) --}}
        <div class="fixed inset-0" wire:click="fecharModal"></div>

        {{-- Conteúdo do Modal --}}
        <div class
            ="relative w-full max-w-lg p-6 bg-white rounded-lg shadow-xl dark:bg-gray-800"
            {{-- Todos os atributos 'x-data', 'x-show' e 'x-transition' foram removidos --}}
            {{-- A visibilidade agora é controlada 100% pelo @if($showLancamentoModal) --}}
        >
            <form wire:submit.prevent="salvarLancamento">
                {{-- Cabeçalho do Modal --}}
                <div class="flex items-start justify-between">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                        Registrar {{ $modalLancamentoTipo }}
                    </h3>
                    <button 
                        type="button" 
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white" 
                        wire:click="fecharModal"
                    >
                        {{-- Ícone X (fechar) --}}
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                    </button>
                </div>

                {{-- Corpo do Modal (Formulário) --}}
                <div class="mt-4">
                    <x-input-label for="valorLancamentoModal" value="Valor:" />
                    <x-text-input 
                        wire:model="valorLancamento" 
                        id="valorLancamentoModal" 
                        class="block mt-1 w-full" 
                        type="number" 
                        min="1" 
                        placeholder="Ex: 100" 
                        autofocus {{-- Foca no campo ao abrir --}}
                    />
                    <x-input-error :messages="$errors->get('valorLancamento')" class="mt-2" />
                </div>

                {{-- Rodapé do Modal (Botões) --}}
                <div class="mt-6 flex justify-end space-x-4">
                    <x-secondary-button type="button" wire:click="fecharModal">
                        Cancelar
                    </x-secondary-button>
                    
                    <x-primary-button 
                        type="submit" 
                        class="{{ $tipoLancamento == 1 ? 'bg-red-600 hover:bg-red-700 focus:bg-red-700 active:bg-red-800' : 'bg-green-600 hover:bg-green-700 focus:bg-green-700 active:bg-green-800' }}"
                    >
                        Salvar {{ $modalLancamentoTipo }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
    @endif
    {{-- **** FIM DO MODAL DE LANÇAMENTO **** --}}
</div>