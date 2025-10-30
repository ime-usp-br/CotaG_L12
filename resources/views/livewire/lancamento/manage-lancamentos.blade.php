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
                                {{-- Critério 6: Placeholders --}}
                                <h3 class="text-lg font-medium">Buscar Pessoa</h3>
                                <p class="text-gray-500">... (Aqui entrará o componente de busca que usa o ReplicadoService)</p>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                    <div>
                                        <h3 class="text-lg font-medium">Perfil e Saldo</h3>
                                        <p class="text-gray-500">... (Aqui entrará a exibição do saldo da pessoa, usando $pessoa->saldo)</p>
                                        
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
