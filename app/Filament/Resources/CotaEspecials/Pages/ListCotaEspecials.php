<?php

namespace App\Filament\Resources\CotaEspecials\Pages;

use App\Filament\Resources\CotaEspecials\CotaEspecialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCotaEspecials extends ListRecords
{
    protected static string $resource = CotaEspecialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
