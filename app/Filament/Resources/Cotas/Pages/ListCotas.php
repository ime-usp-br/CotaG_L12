<?php

namespace App\Filament\Resources\Cotas\Pages;

use App\Filament\Resources\Cotas\CotaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCotas extends ListRecords
{
    protected static string $resource = CotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
