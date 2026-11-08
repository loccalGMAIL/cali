<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // El cobro se registra en el formulario de Cobros, con el cliente ya elegido.
            Action::make('collect')
                ->label('Cobrar')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(fn (Customer $record): string => PaymentResource::getUrl('create', ['customer' => $record->getKey()]))
                ->visible(fn (Customer $record): bool => PaymentResource::canCreate() && bccomp($record->debt(), '0', 2) > 0),
            EditAction::make()->label('Editar'),
        ];
    }
}
