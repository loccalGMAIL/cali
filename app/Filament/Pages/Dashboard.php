<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    /**
     * Accesos directos a lo que más se hace. Cada uno aparece solo si el usuario tiene permiso.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('collect')
                ->label('Cobrar')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(fn (): string => PaymentResource::getUrl('create'))
                ->visible(fn (): bool => PaymentResource::canCreate()),
            Action::make('newCustomer')
                ->label('Nuevo Cliente')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->url(fn (): string => CustomerResource::getUrl('create'))
                ->visible(fn (): bool => CustomerResource::canCreate()),
            Action::make('newPurchase')
                ->label('Nueva Compra')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->color('gray')
                ->url(fn (): string => PurchaseResource::getUrl('create'))
                ->visible(fn (): bool => PurchaseResource::canCreate()),
        ];
    }
}
