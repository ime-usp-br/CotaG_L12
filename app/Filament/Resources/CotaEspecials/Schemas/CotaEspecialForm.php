<?php

namespace App\Filament\Resources\CotaEspecials\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CotaEspecialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Campo de Texto (Numérico) para digitar o Número USP
                TextInput::make('codigo_pessoa')
                    ->label('Número USP')
                    ->numeric() // Garante que apenas números sejam digitados
                    ->required()
                    ->rules([
                        function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                // 1. Verifica se existe no banco local
                                if (\App\Models\Pessoa::where('codigo_pessoa', $value)->exists()) {
                                    return;
                                }

                                // 2. Se não existe, busca no Replicado
                                try {
                                    /** @var \App\Services\ReplicadoService $replicadoService */
                                    $replicadoService = app(\App\Services\ReplicadoService::class);
                                    $pessoaReplicado = $replicadoService->buscarPessoaPorCodpes((string) $value);

                                    if ($pessoaReplicado) {
                                        // 3. Se achou no Replicado, importa para o banco local
                                        \DB::transaction(function () use ($pessoaReplicado) {
                                            $pessoa = \App\Models\Pessoa::create([
                                                'codigo_pessoa' => $pessoaReplicado['codpes'],
                                                'nome_pessoa' => $pessoaReplicado['nompes'],
                                            ]);

                                            foreach ($pessoaReplicado['vinculos'] as $vinculoTipo) {
                                                \App\Models\Vinculo::create([
                                                    'codigo_pessoa' => $pessoa->codigo_pessoa,
                                                    'tipo_vinculo' => $vinculoTipo,
                                                ]);
                                            }
                                        });

                                        return; // Sucesso, pessoa importada e validada
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Erro na integração Replicado (CotaEspecialForm): '.$e->getMessage());
                                    $fail('Erro ao consultar o Replicado. Tente novamente mais tarde.');

                                    return;
                                }

                                // 4. Se não achou em lugar nenhum
                                $fail('O Número USP digitado não foi encontrado na base de dados local nem no Replicado.');
                            };
                        },
                    ]),

                // TextInput numérico para Cota
                TextInput::make('valor')
                    ->label('Valor da Cota')
                    ->numeric()
                    ->required()
                    ->minValue(1),
            ]);
    }
}
