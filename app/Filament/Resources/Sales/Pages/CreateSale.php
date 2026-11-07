<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Actions\CreateSale as CreateSaleAction;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

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

        // El vendedor solo puede registrar ventas a su nombre, aunque el formulario sea manipulado.
        if (auth()->user()?->isSeller()) {
            $data['seller_id'] = auth()->id();
        }

        try {
            /** @var array{customer_id: int, seller_id: int, financing_plan_id: int, date: string, first_due_date: string} $data */
            return app(CreateSaleAction::class)->handle($data, $items, auth()->user());
        } catch (ValidationException $exception) {
            // Los errores de la acción se muestran sobre los campos del formulario.
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["data.{$key}" => $messages])->all(),
            );
        }
    }
}
