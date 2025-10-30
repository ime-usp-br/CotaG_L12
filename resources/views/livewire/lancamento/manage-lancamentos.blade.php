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
                    

                    {{-- CONTEÚDO DAS ABAS --}}
                    <div class="mt-6">
                        {{-- Conteúdo da Aba 1: Realizar Lançamento --}}
                            <div wire:key="aba-lancamento">
                                
                                {{-- **** INÍCIO DA IMPLEMENTAÇÃO DA BUSCA (Critérios de busca) **** --}}

                                {{-- Formulário de Busca (Critério 1 e 2) --}}
                                <form wire:submit="buscarPessoa">
                                    <x-input-label for="termoBusca" value="Buscar Pessoa (NUSP ou Nome)" />
                                    <div class="flex items-center mt-1">
                                        <x-text-input wire:model="termoBusca" id="termoBusca" class="block w-full" type="text" />
                                        <x-primary-button class="ml-4" type="submit">
                                            Buscar
                                        </x-primary-button>
                                        <x-secondary-button class="ml-2" type="button" wire:click="limparBusca">
                                            Limpar
                                        </x-secondary-button>
                                    </div>
                                    <x-input-error :messages="$errors->get('termoBusca')" class="mt-2" />
                                </form>

                                {{-- Indicador de Carregamento (Loading) --}}
                                <div wire:loading wire:target="buscarPessoa" class="mt-4 text-gray-500">
                                    Buscando...
                                </div>

                                {{-- Exibição dos Resultados (Critério 4 e 5) --}}
                                @if ($resultadosBusca !== null)
                                    <div class="mt-6">
                                        <h3 class="text-lg font-medium">Resultados da Busca ({{ $resultadosBusca->count() }})</h3>
                                        <ul class="mt-2 border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
                                            @forelse ($resultadosBusca as $pessoa)
                                                <li class="px-4 py-3 flex items-center justify-between">
                                                    <div>
                                                        {{-- Nomes de coluna corrigidos para 'Pessoa' --}}
                                                        <div class="font-medium">{{ $pessoa->nome_pessoa }}</div>
                                                        <div class="text-sm text-gray-500">{{ $pessoa->codigo_pessoa }}</div>
                                                    </div>
                                                    <x-primary-button type="button" wire:click="selecionarPessoa({{ $pessoa->codigo_pessoa }})">
                                                        Selecionar
                                                    </x-primary-button>
                                                </li>
                                            @empty
                                                {{-- Critério 5: Mensagem de Nenhum Resultado (usando $termoBuscado) --}}
                                                <li class="px-4 py-3 text-gray-500">
                                                    Nenhuma pessoa encontrada com o critério "{{ $termoBuscado }}".
                                                </li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endif

                                {{-- **** FIM DA IMPLEMENTAÇÃO DA BUSCA **** --}}


                                {{-- Placeholders (Critério 6) --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                                    <div>
                                        <h3 class="text-lg font-medium">Perfil e Saldo</h3>
                                        <p class="text-gray-500">... (Aqui entrará a exibição do saldo da pessoa selecionada)</p>
                                        
                                        <h3 class="text-lg font-medium mt-4">Formulário de Lançamento</h3>
                                        <p class="text-gray-500">... (Aqui entrarão os campos de valor e os botões de Débito/Crédito)</p>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-medium">Histórico Específico</h3>
                                        <p class="text-gray-500">... (Aqui entrará a tabela de lançamentos do mês APENAS desta pessoa)</p>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
