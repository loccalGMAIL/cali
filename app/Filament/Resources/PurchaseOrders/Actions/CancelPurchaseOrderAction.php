<?php

namespace App\Filament\Resources\PurchaseOrders\Actions;

use App\Actions\CancelPurchaseOrder;
use App\Filament\Actions\CancelRecordAction;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;

class CancelPurchaseOrderAction extends CancelRecordAction
{
    protected function modalTitle(Model $record): string
    {
        return "Anular la orden de compra #{$record->getKey()}";
    }

    protected function modalExplanation(): string
    {
        return 'Todo lo que incluía la orden vuelve a pendientes de compra, para pedirlo en otra orden.';
    }

    protected function canCancel(Model $record): bool
    {
        return $record instanceof PurchaseOrder
            && $record->isOpen()
            && (auth()->user()?->can('cancel', $record) ?? false);
    }

    protected function performCancellation(Model $record, string $reason): void
    {
        /** @var PurchaseOrder $record */
        app(CancelPurchaseOrder::class)->handle($record, $reason, auth()->user());
    }
}
