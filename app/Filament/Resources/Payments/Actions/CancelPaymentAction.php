<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Actions\CancelPayment;
use App\Filament\Actions\CancelRecordAction;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class CancelPaymentAction extends CancelRecordAction
{
    protected function modalTitle(Model $record): string
    {
        return "Anular el cobro {$record->number()}";
    }

    protected function modalExplanation(): string
    {
        return 'Se devuelve a cada cuota lo que se le había imputado y se registra un débito en la cuenta corriente. El cobro queda en el historial como anulado.';
    }

    protected function canCancel(Model $record): bool
    {
        return $record instanceof Payment
            && ! $record->isCancelled()
            && ! $record->isDownPayment()
            && (auth()->user()?->can('cancel', $record) ?? false);
    }

    protected function performCancellation(Model $record, string $reason): void
    {
        /** @var Payment $record */
        app(CancelPayment::class)->handle($record, $reason, auth()->user());
    }
}
