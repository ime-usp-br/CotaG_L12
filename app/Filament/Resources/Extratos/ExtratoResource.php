<?php

namespace App\Filament\Resources\Extratos;

use App\Filament\Resources\Extratos\Pages\CreateExtrato;
use App\Filament\Resources\Extratos\Pages\EditExtrato;
use App\Filament\Resources\Extratos\Pages\ListExtratos;
use App\Filament\Resources\Extratos\Schemas\ExtratoForm;
use App\Filament\Resources\Extratos\Tables\ExtratosTable;
use App\Models\Extrato;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Models\Lancamento;

class ExtratoResource extends Resource
{
    protected static ?string $model = Lancamento::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    /**
     * Como é read-only, não precisamos de um formulário.
     * REMOVA ou comente este método.
     */
    // public static function form(Form $form): Form
    // {
    //     return $form->schema([]); // Ou simplesmente remova o método
    // }

    /**
     * Delega a configuração da tabela para a classe ExtratosTable.
     */
    public static function table(Table $table): Table
    {
        return ExtratosTable::configure($table); // <-- Chama a classe da tabela
    }

    /**
     * Define as páginas do resource (APENAS a de listagem).
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExtratos::route('/'),
        ];
    }
}
