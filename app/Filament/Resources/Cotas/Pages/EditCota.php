<?php

namespace App\Filament\Resources\Cotas\Pages;

use App\Filament\Resources\Cotas\CotaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCota extends EditRecord
{
    protected static string $resource = CotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
