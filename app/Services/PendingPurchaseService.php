<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lo vendido queda "pendiente de compra" (`sale_items.pending_quantity`) hasta que se incluye
 * en una orden de compra. Toda variación de lo pendiente por órdenes pasa por acá.
 */
class PendingPurchaseService
{
    /**
     * Incluye ítems vendidos en una orden: descuenta lo pendiente y crea las líneas de la orden.
     * Bloquea los ítems vendidos en orden de id para evitar deadlocks.
     *
     * @param  list<array{sale_item_id: int|string, quantity: int|string}>  $items
     *
     * @throws ValidationException
     */
    public function reserve(PurchaseOrder $order, array $items): void
    {
        $quantities = [];

        foreach ($items as $item) {
            $saleItemId = (int) $item['sale_item_id'];
            $quantities[$saleItemId] = ($quantities[$saleItemId] ?? 0) + (int) $item['quantity'];
        }

        if ($quantities === []) {
            throw ValidationException::withMessages(['items' => 'La orden necesita al menos un producto.']);
        }

        ksort($quantities);

        DB::transaction(function () use ($order, $quantities): void {
            $saleItems = SaleItem::query()
                ->with(['product', 'sale'])
                ->whereKey(array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($quantities as $saleItemId => $quantity) {
                $saleItem = $saleItems[$saleItemId] ?? null;

                if ($saleItem === null) {
                    throw ValidationException::withMessages(['items' => 'Uno de los ítems vendidos ya no existe.']);
                }

                if ($saleItem->sale->isCancelled()) {
                    throw ValidationException::withMessages([
                        'items' => "La venta {$saleItem->sale->number()} está anulada: quitá «{$saleItem->product->name}» de la orden.",
                    ]);
                }

                if ($quantity < 1) {
                    throw ValidationException::withMessages(['items' => 'La cantidad de cada producto debe ser al menos 1.']);
                }

                if ($quantity > $saleItem->pending_quantity) {
                    throw ValidationException::withMessages([
                        'items' => "«{$saleItem->product->name}» (venta {$saleItem->sale->number()}): quedan {$saleItem->pending_quantity} pendientes y se piden {$quantity}.",
                    ]);
                }

                $saleItem->decrement('pending_quantity', $quantity);

                $order->items()->create([
                    'sale_item_id' => $saleItem->getKey(),
                    'product_id' => $saleItem->product_id,
                    'quantity' => $quantity,
                ]);
            }
        });
    }

    /**
     * Devuelve a pendiente todo lo que tenía la orden (salvo lo de ventas anuladas, que ya no
     * hace falta comprar). Si `$deleteItems` es true, borra las líneas (al editar la orden);
     * si no, las deja como historial (al anularla).
     */
    public function release(PurchaseOrder $order, bool $deleteItems): void
    {
        DB::transaction(function () use ($order, $deleteItems): void {
            $orderItems = $order->items()->with('saleItem.sale')->get();

            $this->lockSaleItems($orderItems->pluck('sale_item_id')->all());

            foreach ($orderItems as $orderItem) {
                if (! $orderItem->saleItem->sale->isCancelled()) {
                    $orderItem->saleItem->increment('pending_quantity', $orderItem->quantity);
                }
            }

            if ($deleteItems) {
                $order->items()->delete();
            }
        });
    }

    /**
     * Reparte lo que llegó de un producto entre las líneas de la orden, completando primero
     * las ventas más antiguas. Lo que falta de cada línea vuelve a pendiente de compra.
     *
     * @param  iterable<PurchaseOrderItem>  $orderItems  líneas de un mismo producto
     */
    public function receive(iterable $orderItems, int $receivedQuantity): void
    {
        $remaining = $receivedQuantity;

        foreach ($orderItems as $orderItem) {
            $received = min($remaining, $orderItem->quantity);
            $remaining -= $received;

            $orderItem->update(['received_quantity' => $received]);

            $missing = $orderItem->quantity - $received;

            if ($missing > 0 && ! $orderItem->saleItem->sale->isCancelled()) {
                $orderItem->saleItem->increment('pending_quantity', $missing);
            }
        }
    }

    /**
     * Deshace la recepción: lo que había vuelto a pendiente por faltante se vuelve a descontar.
     * Si ese faltante ya se incluyó en otra orden no se puede deshacer.
     *
     * @param  iterable<PurchaseOrderItem>  $orderItems
     *
     * @throws ValidationException
     */
    public function undoReceive(iterable $orderItems): void
    {
        foreach ($orderItems as $orderItem) {
            $missing = $orderItem->missingQuantity();
            $saleItem = $orderItem->saleItem;

            if ($missing > 0 && ! $saleItem->sale->isCancelled()) {
                if ($saleItem->pending_quantity < $missing) {
                    throw ValidationException::withMessages([
                        'purchase' => "Lo que faltó de «{$orderItem->product->name}» (venta {$saleItem->sale->number()}) ya se incluyó en otra orden de compra. Anulá o editá primero esa orden.",
                    ]);
                }

                $saleItem->decrement('pending_quantity', $missing);
            }

            $orderItem->update(['received_quantity' => null]);
        }
    }

    /**
     * @param  list<int>  $saleItemIds
     */
    public function lockSaleItems(array $saleItemIds): void
    {
        SaleItem::query()->whereKey($saleItemIds)->orderBy('id')->lockForUpdate()->get();
    }
}
