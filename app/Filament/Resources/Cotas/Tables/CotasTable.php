<?php

namespace App\Filament\Resources\Cotas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CotasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Coluna "Tipo Vínculo"
                TextColumn::make('tipo_vinculo')
                    ->label('Tipo Vínculo')
                    ->searchable()
                    ->sortable(),

                // Coluna "Cota"
                TextColumn::make('valor')
                    ->label('Cota')
                    ->numeric()
                    ->sortable(),

                // Coluna de data (bom ter)
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
