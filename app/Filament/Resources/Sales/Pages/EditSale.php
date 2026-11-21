<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Actions\UpdateSale;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

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
        /** @var Sale $sale */
        $sale = $this->getRecord();

        $data['items'] = $sale->items()->orderBy('id')->get()
            ->mapWithKeys(fn (SaleItem $item): array => [
                (string) str()->uuid() => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'unit_freight' => $item->unit_freight,
                    'unit_margin' => $item->unit_margin,
                ],
            ])
            ->all();

        $data['first_due_date'] = $sale->first_due_date->toDateString();
        $data['down_payment_method'] = $sale->activeDownPayment()->method ?? PaymentMethod::Cash;

        return $data;
    }

    /**
     * La lógica de negocio vive en la acción; el recurso solo la invoca. Lo que el formulario
     * tiene bloqueado no llega en `$data` y queda como está.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $items = array_key_exists('items', $data) ? array_values($data['items']) : null;
        unset($data['items']);

        // El vendedor no puede pasarle la venta a otro, aunque el formulario sea manipulado.
        if (auth()->user()?->isSeller()) {
            $data['seller_id'] = auth()->id();
        }

        try {
            /** @var Sale $record */
            return app(UpdateSale::class)->handle($record, $data, $items, auth()->user());
        } catch (ValidationException $exception) {
            // Los errores que no son de un campo (p. ej. por qué no se puede cambiar) van en un aviso.
            Notification::make()->danger()->title('No se pudo guardar la venta')
                ->body(collect($exception->errors())->flatten()->implode(' '))->send();

            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["data.{$key}" => $messages])->all(),
            );
        }
    }

    protected function getRedirectUrl(): string
    {
        return SaleResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
