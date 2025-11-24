<?php

namespace App\Filament\Resources\Permissions\Pages;

use App\Filament\Resources\Permissions\PermissionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePermissions extends ManageRecords
{
    protected static string $resource = PermissionResource::class;

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
