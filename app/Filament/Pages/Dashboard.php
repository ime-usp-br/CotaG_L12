<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\NavigationCardsWidget;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Painel Administrativo';

    public function getWidgets(): array
    {
        return [
            NavigationCardsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 1,
            'lg' => 1,
            'xl' => 1,
            '2xl' => 1,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToDashboard')
                ->label('Voltar ao Dashboard')
                ->icon('heroicon-o-arrow-left')
                ->url(route('dashboard'))
                ->color('gray'),
        ];
    }
}
