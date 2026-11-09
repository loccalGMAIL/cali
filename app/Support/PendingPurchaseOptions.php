<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use App\Models\SaleItem;
use Illuminate\Support\Collection;

/**
 * Opciones del formulario de órdenes de compra: qué ítems vendidos se pueden incluir y cuántas
 * unidades de cada uno. Al editar una orden, lo que ya tiene cuenta como disponible.
 */
class PendingPurchaseOptions
{
    /**
     * Ítems vendidos que se pueden incluir en la orden: los pendientes de compra más los que ya tiene.
     *
     * @return array<int, string>
     */
    public static function options(?PurchaseOrder $order): array
    {
        return self::available($order)
            ->map(fn (array $entry): string => $entry['item']->label()." ({$entry['available']} disponible".($entry['available'] === 1 ? '' : 's').')')
            ->all();
    }

    /**
     * Cuántas unidades de un ítem vendido se pueden incluir en la orden.
     */
    public static function availableQuantity(mixed $saleItemId, ?PurchaseOrder $order): int
    {
        if (blank($saleItemId)) {
            return 0;
        }

        $saleItem = SaleItem::query()->find($saleItemId);

        if ($saleItem === null) {
            return 0;
        }

        return $saleItem->pending_quantity + self::inOrder($order)->get((int) $saleItemId, 0);
    }

    /**
     * Ítems pendientes cuyo producto tiene como proveedor habitual al indicado, con lo disponible
     * de cada uno: para agregarlos de una vez a la orden de ese proveedor.
     *
     * @return list<array{sale_item_id: int, quantity: int}>
     */
    public static function forSupplier(mixed $supplierId, ?PurchaseOrder $order): array
    {
        return self::available($order)
            ->filter(fn (array $entry): bool => (int) $entry['item']->product->supplier_id === (int) $supplierId)
            ->map(fn (array $entry, int $id): array => ['sale_item_id' => $id, 'quantity' => $entry['available']])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{item: SaleItem, available: int}>
     */
    private static function available(?PurchaseOrder $order): Collection
    {
        $inOrder = self::inOrder($order);

        return SaleItem::query()
            ->with(['product', 'sale.customer'])
            ->where(fn ($query) => $query->pendingPurchase()->orWhereKey($inOrder->keys()->all()))
            ->whereHas('sale', fn ($query) => $query->notCancelled())
            ->orderBy('sale_id')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (SaleItem $item): array => [
                $item->getKey() => ['item' => $item, 'available' => $item->pending_quantity + $inOrder->get($item->getKey(), 0)],
            ])
            ->filter(fn (array $entry): bool => $entry['available'] > 0);
    }

    /**
     * Unidades que la orden ya tiene de cada ítem vendido (solo si sigue abierta).
     *
     * @return Collection<int, int>
     */
    private static function inOrder(?PurchaseOrder $order): Collection
    {
        if ($order === null || ! $order->isOpen()) {
            return collect();
        }

        return $order->items()->get()->groupBy('sale_item_id')->map(fn (Collection $items): int => (int) $items->sum('quantity'));
    }
}
