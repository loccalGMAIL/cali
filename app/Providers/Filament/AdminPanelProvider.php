<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Controllers\PrintPurchaseOrderController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->domain(config('domains.panel'))
            ->path('')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandLogo(fn (): View => view('filament.admin.logo'))
            ->brandLogoHeight(fn (): string => auth()->guest() ? '3.25rem' : '2rem')
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): View => view('filament.admin.version', ['isLogin' => true]))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): View => view('filament.admin.version'))
            ->navigationGroups([
                NavigationGroup::make('Ventas'),
                NavigationGroup::make('Compras'),
                NavigationGroup::make('Configuración'),
            ])
            ->login()
            ->profile()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('purchase-orders/{purchaseOrder}/print', PrintPurchaseOrderController::class)
                    ->name('purchase-orders.print');
            });
    }
}
