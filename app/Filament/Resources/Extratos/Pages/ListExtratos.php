<?php

namespace App\Filament\Resources\Extratos\Pages;

use App\Filament\Resources\Extratos\ExtratoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtratos extends ListRecords
{
    protected static string $resource = ExtratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make(),
        ];
    }
}
