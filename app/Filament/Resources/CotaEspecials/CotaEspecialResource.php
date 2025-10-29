<?php

namespace App\Filament\Resources\CotaEspecials;

use App\Filament\Resources\CotaEspecials\Pages\CreateCotaEspecial;
use App\Filament\Resources\CotaEspecials\Pages\EditCotaEspecial;
use App\Filament\Resources\CotaEspecials\Pages\ListCotaEspecials;
use App\Filament\Resources\CotaEspecials\Schemas\CotaEspecialForm;
use App\Filament\Resources\CotaEspecials\Tables\CotaEspecialsTable;
use App\Models\CotaEspecial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CotaEspecialResource extends Resource
{
    protected static ?string $model = CotaEspecial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CotaEspecialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CotaEspecialsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCotaEspecials::route('/'),
            'create' => CreateCotaEspecial::route('/create'),
            'edit' => EditCotaEspecial::route('/{record}/edit'),
        ];
    }
}
