<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Actions\UpdatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ver'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var PurchaseOrder $order */
        $order = $this->getRecord();

        $data['items'] = $order->items()->orderBy('id')->get()
            ->mapWithKeys(fn (PurchaseOrderItem $item): array => [
                (string) str()->uuid() => ['sale_item_id' => $item->sale_item_id, 'quantity' => $item->quantity],
            ])
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $items = array_values($data['items'] ?? []);
        unset($data['items']);

        try {
            /** @var PurchaseOrder $record */
            /** @var array{supplier_id: int, date: string, notes?: string|null} $data */
            return app(UpdatePurchaseOrder::class)->handle($record, $data, $items, auth()->user());
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
