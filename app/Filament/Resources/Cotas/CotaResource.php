<?php

namespace App\Filament\Resources\Cotas;

use App\Filament\Resources\RelationManagers\AuditsRelationManager;
use App\Filament\Resources\Cotas\Pages\CreateCota;
use App\Filament\Resources\Cotas\Pages\EditCota;
use App\Filament\Resources\Cotas\Pages\ListCotas;
use App\Filament\Resources\Cotas\Schemas\CotaForm;
use App\Filament\Resources\Cotas\Tables\CotasTable;
use App\Models\Cota;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CotaResource extends Resource
{
    protected static ?string $model = Cota::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CotaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CotasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCotas::route('/'),
            'create' => CreateCota::route('/create'),
            'edit' => EditCota::route('/{record}/edit'),
        ];
    }
}
