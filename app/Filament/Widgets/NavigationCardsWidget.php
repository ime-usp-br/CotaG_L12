<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AuditResource;
use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Cotas\CotaResource;
use App\Filament\Resources\CotaEspecials\CotaEspecialResource;
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
                'icon' => 'heroicon-o-document', 
                'url' => CotaResource::getUrl('index'), 
                'color' => 'danger', 
                'stats' => \App\Models\Cota::count(),
            ],
            [
                'title' => 'Cotas Especiais',
                'description' => 'Gerenciar cotas de exceção por pessoa',
                'icon' => 'heroicon-o-star', // Ícone definido no Resource
                'url' => CotaEspecialResource::getUrl('index'), // Link para o novo resource
                'color' => 'warning', // Cor diferente para destacar
                'stats' => \App\Models\CotaEspecial::count(), // Contagem de cotas especiais
            ],
        ];
    }
}
