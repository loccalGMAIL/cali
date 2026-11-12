<?php

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssuePurchaseOrder
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * "Corta" la orden: pasa de borrador a emitida, lista para llevarle al proveedor.
     *
     * @throws ValidationException
     */
    public function handle(PurchaseOrder $order, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $user): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->status !== PurchaseOrderStatus::Draft) {
                throw ValidationException::withMessages(['order' => 'Solo se puede emitir una orden en borrador.']);
            }

            $locked->update([
                'status' => PurchaseOrderStatus::Issued,
                'issued_at' => now(),
            ]);

            $this->activity->log(
                ActivityEvent::Updated,
                "Emitió la orden de compra {$locked->number()} a {$locked->supplier->name}",
                $locked,
                [],
                $user,
            );

            return $locked->refresh();
        });
    }
}
