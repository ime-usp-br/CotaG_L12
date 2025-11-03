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

                                        {{-- Card: Formulário de Lançamento --}}
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">Formulário de Lançamento</h3>
                                            <div class="mt-2 space-y-4">
                                                <div>
                                                    <x-input-label for="valorLancamento" value="Valor:" />
                                                    <x-text-input wire:model="valorLancamento" id="valorLancamento" class="block mt-1 w-full" type="number" min="1" placeholder="Ex: 100" />
                                                    <x-input-error :messages="$errors->get('valorLancamento')" class="mt-2" />
                                                </div>
                                                <div class="flex space-x-4">
                                                    {{-- Botão Débito --}}
                                                    <x-danger-button type="button" class="flex-1 justify-center py-3" wire:click="realizarLancamento(1)" wire:loading.attr="disabled">
                                                        Débito
                                                    </x-danger-button>
                                                    {{-- Botão Crédito --}}
                                                    <x-primary-button type="button" class="flex-1 justify-center py-3" wire:click="realizarLancamento(0)" style="background-color: #16a34a; --tw-shadow-color: #15803d;" wire:loading.attr="disabled">
                                                        Crédito
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
                                                @forelse($lancamentosMes as $lancamento)
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
                                                @empty
                                                    <li class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                        Nenhum lançamento este mês.
                                                    </li>
                                                @endforelse
                                            </ul>
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
</div>