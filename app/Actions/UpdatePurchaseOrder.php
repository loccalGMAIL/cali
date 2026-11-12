<?php

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PendingPurchaseService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePurchaseOrder
{
    public function __construct(
        private readonly PendingPurchaseService $pending,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Edita una orden abierta (borrador o emitida): cambia el proveedor, la fecha, las notas y qué
     * ítems vendidos incluye. Lo que se saca vuelve a pendiente de compra; lo que se agrega deja de estarlo.
     *
     * @param  array{supplier_id: int|string, date: CarbonInterface|string, notes?: string|null}  $data
     * @param  list<array{sale_item_id: int|string, quantity: int|string}>  $items
     *
     * @throws ValidationException
     */
    public function handle(PurchaseOrder $order, array $data, array $items, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $data, $items, $user): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['order' => 'Solo se puede editar una orden en borrador o emitida.']);
            }

            $this->pending->release($locked, deleteItems: true);
            $this->pending->reserve($locked, $items);

            $locked->update([
                'supplier_id' => $data['supplier_id'],
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->activity->log(
                ActivityEvent::Updated,
                "Editó la orden de compra {$locked->number()} a {$locked->supplier->name}",
                $locked,
                ['items' => $locked->items()->count()],
                $user,
            );

            return $locked->refresh()->load('items');
        });
    }
}
