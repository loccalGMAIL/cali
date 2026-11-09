<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Actions\CreatePurchase as CreatePurchaseAction;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Models\PurchaseOrder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * Orden de compra elegida desde la orden ("Cargar factura").
     */
    #[Url(as: 'order')]
    public ?string $orderId = null;

    protected function afterFill(): void
    {
        if (blank($this->orderId) || ! PurchaseOrder::query()->open()->whereKey($this->orderId)->exists()) {
            return;
        }

        $this->form->fill([
            'purchase_order_id' => $this->orderId,
            'date' => now()->toDateString(),
            'items' => PurchaseForm::itemsFor($this->orderId),
        ]);
    }

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
            /** @var array{purchase_order_id: int, date: string, invoice_number: string, notes?: string|null} $data */
            return app(CreatePurchaseAction::class)->handle($data, $items, auth()->user());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["data.{$key}" => $messages])->all(),
            );
        }
    }
}
