<?php

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PendingPurchaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPurchaseOrder
{
    public function __construct(
        private readonly PendingPurchaseService $pending,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Anula una orden que todavía no se recibió: todo lo que incluía vuelve a pendiente de compra.
     * La orden no se borra: queda como historial con quién, cuándo y por qué.
     *
     * @throws ValidationException
     */
    public function handle(PurchaseOrder $order, string $reason, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $reason, $user): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages([
                    'order' => $locked->isCancelled()
                        ? 'Esta orden ya está anulada.'
                        : 'La orden ya se recibió: anulá primero la factura de compra.',
                ]);
            }

            $this->pending->release($locked, deleteItems: false);

            $locked->update([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->getKey(),
                'cancellation_reason' => $reason,
            ]);

            $this->activity->log(
                ActivityEvent::Cancelled,
                "Anuló la orden de compra {$locked->number()} a {$locked->supplier->name}: {$reason}",
                $locked,
                ['reason' => $reason],
                $user,
            );

            return $locked->refresh();
        });
    }
}
