<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Permission\Middleware\RoleMiddleware;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->profile()
            ->favicon(asset('favicon.png'))
            ->colors([
                'primary' => Color::hex('#1094AB'), // USP Blue Primary
                'warning' => Color::hex('#FCB421'), // USP Yellow
            ])
            ->darkMode(true)
            ->maxContentWidth(Width::SevenExtraLarge)
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('components.usp.filament-header')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => '<style>
                    /* Fundo principal */
                    .dark .fi-body {
                        background-color: #111827 !important;
                    }
                    .dark .fi-sidebar {
                        background-color: #1f2937 !important;
                    }
                    .dark .fi-topbar {
                        background-color: #1f2937 !important;
                    }

                    /* Headers e títulos */
                    .dark .fi-header {
                        background-color: #111827 !important;
                    }
                    .dark .fi-section-header {
                        background-color: transparent !important;
                    }
                    .fi-header {
                        background-color: #f9fafb;
                    }
                    .dark .fi-page {
                        background-color: transparent !important;
                    }

                    /* Cards das páginas de gestão */
                    .dark .fi-section {
                        background-color: rgba(31, 41, 55, 0.5) !important;
                    }
                    .dark .fi-ta-ctn {
                        background-color: rgba(31, 41, 55, 0.5) !important;
                    }
                    .dark .fi-fo-section {
                        background-color: rgba(31, 41, 55, 0.5) !important;
                    }

                    /* Esconder topbar e sidebar padrão do Filament */
                    .fi-topbar {
                        display: none !important;
                    }
                    .fi-sidebar {
                        display: none !important;
                    }
                    .fi-main {
                        margin-left: 0 !important;
                    }

                    /* Centralizar conteúdo do painel */
                    .fi-main {
                        max-width: 80rem !important;
                        margin-left: auto !important;
                        margin-right: auto !important;
                        padding-left: 1rem !important;
                        padding-right: 1rem !important;
                    }

                    /* Padding responsivo igual ao header: px-4 padrão, px-8 em lg+ */
                    @media (min-width: 1024px) {
                        .fi-main {
                            padding-left: 2rem !important;
                            padding-right: 2rem !important;
                        }
                    }
                </style>',
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RoleMiddleware::using('Admin|Operador'),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s');
        // ->strictAuthorization();
    }
}
