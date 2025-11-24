<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AuditResource;
use App\Filament\Resources\CotaEspecials\CotaEspecialResource;
use App\Filament\Resources\Cotas\CotaResource;
use App\Filament\Resources\Extratos\ExtratoResource;
use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Widgets\Widget;

class NavigationCardsWidget extends Widget
{
    protected string $view = 'filament.widgets.navigation-cards-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<int, array{title: string, description: string, icon: string, url: string, color: string, stats: int}>
     */
    public function getNavigationCards(): array
    {
        return [
            [
                'title' => 'Usuários',
                'description' => 'Gerenciar usuários do sistema',
                'icon' => 'heroicon-o-users',
                'url' => UserResource::getUrl('index'),
                'color' => 'primary',
                'stats' => \App\Models\User::count(),
            ],
            [
                'title' => 'Perfis',
                'description' => 'Gerenciar perfis e permissões',
                'icon' => 'heroicon-o-shield-check',
                'url' => RoleResource::getUrl('index'),
                'color' => 'success',
                'stats' => \Spatie\Permission\Models\Role::count(),
            ],
            [
                'title' => 'Permissões',
                'description' => 'Gerenciar permissões do sistema',
                'icon' => 'heroicon-o-key',
                'url' => PermissionResource::getUrl('index'),
                'color' => 'warning',
                'stats' => \Spatie\Permission\Models\Permission::count(),
            ],
            [
                'title' => 'Logs de Auditoria',
                'description' => 'Visualizar histórico de alterações',
                'icon' => 'heroicon-o-document-text',
                'url' => AuditResource::getUrl('index'),
                'color' => 'info',
                'stats' => \OwenIt\Auditing\Models\Audit::count(),
            ],
            [
                'title' => 'Cotas',
                'description' => 'Gerenciar cotas padrão de impressão',
                'icon' => 'heroicon-o-rectangle-stack',
                'url' => CotaResource::getUrl('index'),
                'color' => 'gray', // <-- Cor neutra (cinzento)
                'stats' => \App\Models\Cota::count(),
            ],
            [
                'title' => 'Cotas Especiais',
                'description' => 'Gerenciar cotas de exceção por pessoa',
                'icon' => 'heroicon-o-star',
                'url' => CotaEspecialResource::getUrl('index'),
                'color' => 'fuchsia', // <-- Cor de destaque (rosa/roxo)
                'stats' => \App\Models\CotaEspecial::count(),
            ],
            [
                'title' => 'Extrato',
                'description' => 'Gerenciar extratos gerais e/ou mensais',
                'icon' => 'heroicon-o-clipboard-document-list',
                'url' => ExtratoResource::getUrl('index'),
                'color' => 'teal', // <-- Cor de informação (verde-azulado)
                'stats' => \App\Models\Lancamento::count(),
            ],
        ];
    }
}
