<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Actions\CreatePurchaseOrder as CreatePurchaseOrderAction;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

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

        try {
            /** @var array{supplier_id: int, date: string, notes?: string|null} $data */
            return app(CreatePurchaseOrderAction::class)->handle($data, $items, auth()->user());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["data.{$key}" => $messages])->all(),
            );
        }
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseOrderResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
