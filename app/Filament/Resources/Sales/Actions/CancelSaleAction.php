<?php

namespace App\Filament\Resources\Sales\Actions;

use App\Actions\CancelSale;
use App\Filament\Actions\CancelRecordAction;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;

class CancelSaleAction extends CancelRecordAction
{
    protected function modalTitle(Model $record): string
    {
        return "Anular la venta #{$record->getKey()}";
    }

    protected function modalExplanation(): string
    {
        return 'Se quita de pendientes de compra lo que todavía no se pidió (lo que ya está en una orden de compra queda en la orden), se anulan las cuotas y el anticipo, y se revierte la venta en la cuenta corriente. Si la venta tiene cobros imputados a sus cuotas, hay que anularlos primero.';
    }

    protected function canCancel(Model $record): bool
    {
        return $record instanceof Sale
            && ! $record->isCancelled()
            && (auth()->user()?->can('cancel', $record) ?? false);
    }

    protected function performCancellation(Model $record, string $reason): void
    {
        /** @var Sale $record */
        app(CancelSale::class)->handle($record, $reason, auth()->user());
    }
}
