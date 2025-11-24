<?php

namespace App\Filament\Resources\Cotas\Pages;

use App\Filament\Resources\Cotas\CotaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCotas extends ListRecords
{
    protected static string $resource = CotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToDashboard')
                ->label('Voltar ao Painel Administrativo')
                ->icon('heroicon-o-arrow-left')
                ->url(url('/admin'))
                ->color('gray'),
            CreateAction::make(),
        ];
    }
}
