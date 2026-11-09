<?php

namespace App\Filament\Resources\Purchases\Actions;

use App\Actions\CancelPurchase;
use App\Filament\Actions\CancelRecordAction;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Model;

class CancelPurchaseAction extends CancelRecordAction
{
    protected function modalTitle(Model $record): string
    {
        return "Anular la compra #{$record->getKey()}";
    }

    protected function modalExplanation(): string
    {
        return 'La orden de compra vuelve a quedar emitida para cargar la factura correcta, lo que había vuelto a pendiente por faltante se descuenta y, si la factura fijó el último costo de un producto, se vuelve al de la compra anterior.';
    }

    protected function canCancel(Model $record): bool
    {
        return $record instanceof Purchase
            && ! $record->isCancelled()
            && (auth()->user()?->can('cancel', $record) ?? false);
    }

    protected function performCancellation(Model $record, string $reason): void
    {
        /** @var Purchase $record */
        app(CancelPurchase::class)->handle($record, $reason, auth()->user());
    }
}
