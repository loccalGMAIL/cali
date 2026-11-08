<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\RegisterPayment;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * La lógica de negocio vive en la acción; el recurso solo la invoca.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            /** @var array{customer_id: int, amount: string|int|float} $data */
            return app(RegisterPayment::class)->handle($data, auth()->user());
        } catch (ValidationException $exception) {
            // Los errores de la acción se muestran sobre los campos del formulario.
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["data.{$key}" => $messages])->all(),
            );
        }
    }
}
