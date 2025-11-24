<?php

namespace App\Filament\Resources\AuditResource\Pages;

use App\Filament\Resources\AuditResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAudits extends ListRecords
{
    protected static string $resource = AuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToDashboard')
                ->label('Voltar ao Painel Administrativo')
                ->icon('heroicon-o-arrow-left')
                ->url(url('/admin'))
                ->color('gray'),
        ];
    }
}
