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
                    ->exists('pessoas', 'codigo_pessoa') // VALIDAÇÃO IMPORTANTE: Garante que o NUSP existe na tabela 'pessoas'
                    ->validationMessages([
                        'exists' => 'O Número USP digitado não foi encontrado na base de dados.',
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
