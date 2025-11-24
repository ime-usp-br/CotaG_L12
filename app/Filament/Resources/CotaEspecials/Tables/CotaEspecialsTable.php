<?php

namespace App\Filament\Resources\CotaEspecials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CotaEspecialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // NUSP (vem do relacionamento)
                TextColumn::make('pessoa.codigo_pessoa')
                    ->label('NUSP')
                    ->searchable()
                    ->sortable(),

                // NOME (vem do relacionamento)
                TextColumn::make('pessoa.nome_pessoa')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                // VALOR (local)
                TextColumn::make('valor')
                    ->label('Valor')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
