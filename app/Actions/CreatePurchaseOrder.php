<?php

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PendingPurchaseService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrder
{
    public function __construct(
        private readonly PendingPurchaseService $pending,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Arma una orden de compra (en borrador) para un proveedor con ítems vendidos que estaban
     * pendientes de compra. Lo incluido deja de estar pendiente.
     *
     * @param  array{supplier_id: int|string, date: CarbonInterface|string, notes?: string|null}  $data
     * @param  list<array{sale_item_id: int|string, quantity: int|string}>  $items
     *
     * @throws ValidationException
     */
    public function handle(array $data, array $items, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items, $user): PurchaseOrder {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);

            $order = PurchaseOrder::create([
                'supplier_id' => $supplier->getKey(),
                'date' => $data['date'],
                'status' => PurchaseOrderStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'user_id' => $user->getKey(),
            ]);

            $this->pending->reserve($order, $items);

            $units = (int) $order->items()->sum('quantity');

            $this->activity->log(
                ActivityEvent::Created,
                "Armó la orden de compra {$order->number()} a {$supplier->name} con {$units} unidad".($units === 1 ? '' : 'es'),
                $order,
                ['items' => $order->items()->count()],
                $user,
            );

            return $order->load('items');
        });
    }
}
