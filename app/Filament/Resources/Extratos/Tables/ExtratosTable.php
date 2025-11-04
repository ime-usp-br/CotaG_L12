<?php

namespace App\Filament\Resources\Extratos\Tables;

use App\Models\Lancamento; // Modelo base
use App\Models\Pessoa; // Para o filtro
use App\Models\User; // Para o filtro
use Filament\Forms\Components\DatePicker; // Para o filtro de data
use Filament\Tables;
use Filament\Tables\Filters\Filter; // Filtro customizado
use Filament\Tables\Filters\SelectFilter; // Filtro de Select
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; // Para a query do filtro
use Filament\Actions\ViewAction;

class ExtratosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Ordenação padrão pela data mais recente
            ->defaultSort('data', 'desc')
            ->columns([
                // Pessoa (Nome e NUSP)
                Tables\Columns\TextColumn::make('pessoa.nome_pessoa')
                    ->label('Pessoa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pessoa.codigo_pessoa')
                    ->label('NUSP')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Usuário (Operador)
                Tables\Columns\TextColumn::make('usuario.name')
                    ->label('Operador')
                    ->searchable()
                    ->sortable(),

                // Valor
                Tables\Columns\TextColumn::make('valor')
                    ->numeric()
                    ->sortable(),

                // Tipo (Deb/Cred) - Formatado com Badges
                Tables\Columns\TextColumn::make('tipo_lancamento')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? 'Débito' : 'Crédito')
                    ->color(fn (int $state): string => $state === 1 ? 'danger' : 'success')
                    ->sortable(),

                // Data
                Tables\Columns\TextColumn::make('data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtro por Pessoa (Select pesquisável)
                SelectFilter::make('pessoa')
                    ->relationship('pessoa', 'nome_pessoa')
                    ->searchable()
                    ->preload()
                    ->label('Pessoa'),

                // Filtro por Usuário (Operador)
                SelectFilter::make('usuario')
                    ->relationship('usuario', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Operador'),
                
                // Filtro por Intervalo de Data
                Filter::make('data_intervalo')
                    ->form([
                        DatePicker::make('data_de')
                            ->label('Lançamentos de'),
                        DatePicker::make('data_ate')
                            ->label('Lançamentos até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['data_de'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data', '>=', $date),
                            )
                            ->when(
                                $data['data_ate'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data', '<=', $date),
                            );
                    })
                    ->label('Período'),

            ])
            ->actions([
                // Nenhuma ação por linha (read-only)
            ])
            ->bulkActions([
                // Nenhuma ação em massa (read-only)
            ])
            ->headerActions([
                 // Nenhuma ação no cabeçalho (sem botão Create)
            ]);
    }
}
