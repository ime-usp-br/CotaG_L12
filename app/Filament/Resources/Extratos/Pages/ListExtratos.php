<?php

namespace App\Filament\Resources\Extratos\Pages;

use App\Filament\Resources\Extratos\ExtratoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtratos extends ListRecords
{
    protected static string $resource = ExtratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToDashboard')
                ->label('Voltar ao Painel Administrativo')
                ->icon('heroicon-o-arrow-left')
                ->url(url('/admin'))
                ->color('gray'),
            // CreateAction::make(),
        ];
    }
}
