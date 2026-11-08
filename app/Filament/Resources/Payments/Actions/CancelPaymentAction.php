<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Actions\CancelPayment;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Botón "Anular" de un cobro, compartido por la tabla y la vista de detalle.
 * La lógica vive en la acción CancelPayment; esto solo pide el motivo y confirma.
 */
class CancelPaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancel';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Anular')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Payment $record): string => "Anular el cobro #{$record->getKey()}")
            ->modalDescription('Se devuelve a cada cuota lo que se le había imputado y se registra un débito en la cuenta corriente. El cobro queda en el historial como anulado.')
            ->modalSubmitActionLabel('Anular cobro')
            ->schema([
                Textarea::make('reason')->label('Motivo de la anulación')->required()->maxLength(500),
            ])
            ->visible(fn (Payment $record): bool => ! $record->isCancelled()
                && ! $record->isDownPayment()
                && (auth()->user()?->can('cancel', $record) ?? false))
            ->action(function (Payment $record, array $data): void {
                try {
                    app(CancelPayment::class)->handle($record, $data['reason'], auth()->user());
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('No se pudo anular el cobro')
                        ->body(collect($exception->errors())->flatten()->implode(' '))->send();

                    return;
                }

                Notification::make()->success()->title('Cobro anulado')->send();
            });
    }
}
