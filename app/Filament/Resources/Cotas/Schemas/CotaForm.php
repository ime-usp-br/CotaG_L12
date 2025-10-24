<?php

namespace App\Filament\Resources\Cotas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CotaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Campo de Texto para "Tipo Vínculo"
            TextInput::make('tipo_vinculo')
                ->label('Tipo Vínculo')
                ->required()
                ->maxLength(255),

            // Campo de Texto (Numérico) para "Cota"
            TextInput::make('valor')
                ->label('Cota')
                ->numeric()
                ->required(),
            ]);
    }
}
