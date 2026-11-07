<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Actions\CreatePurchase as CreatePurchaseAction;
use App\Filament\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * La lógica de negocio vive en la acción; el recurso solo la invoca.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $items = array_values($data['items'] ?? []);
        unset($data['items']);

        /** @var array{supplier_id: int, date: string, invoice_number: string, notes?: string|null} $data */
        return app(CreatePurchaseAction::class)->handle($data, $items, auth()->user());
    }
}
