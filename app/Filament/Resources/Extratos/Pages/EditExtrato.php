<?php

namespace App\Filament\Resources\Extratos\Pages;

use App\Filament\Resources\Extratos\ExtratoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExtrato extends EditRecord
{
    protected static string $resource = ExtratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
