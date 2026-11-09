<?php

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PendingPurchaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPurchase
{
    public function __construct(
        private readonly PendingPurchaseService $pending,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Anula una factura de compra: la orden vuelve a quedar emitida (para cargar la factura
     * correcta), lo que había vuelto a pendiente por faltante se vuelve a descontar y, si esa
     * compra fijó el último costo de un producto, se vuelve al de la compra vigente anterior.
     * La compra no se borra: queda como historial.
     *
     * No se puede anular si un faltante de esta factura ya se incluyó en otra orden de compra.
     *
     * @throws ValidationException
     */
    public function handle(Purchase $purchase, string $reason, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason, $user): Purchase {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->getKey());

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['purchase' => 'Esta compra ya está anulada.']);
            }

            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($locked->purchase_order_id);

            $this->pending->lockSaleItems($order->items()->pluck('sale_item_id')->all());

            $this->pending->undoReceive($order->items()->with(['product', 'saleItem.sale'])->get());

            $order->update(['status' => PurchaseOrderStatus::Issued]);

            $locked->update([
                'cancelled_at' => now(),
                'cancelled_by' => $user->getKey(),
                'cancellation_reason' => $reason,
            ]);

            // Solo los productos que llegaron: esos son los que fijaron un último costo.
            $products = Product::query()
                ->whereIn('id', $locked->items()->where('quantity', '>', 0)->pluck('product_id'))
                ->orderBy('id')
                ->get();

            foreach ($products as $product) {
                $this->restoreLastCost($product, $locked);
            }

            $this->activity->log(
                ActivityEvent::Cancelled,
                "Anuló la compra #{$locked->getKey()} (factura {$locked->invoice_number}): {$reason}",
                $locked,
                ['reason' => $reason, 'total' => $locked->total],
                $user,
            );

            return $locked->refresh();
        });
    }

    /**
     * El último costo solo se corrige si esta compra era la más reciente del producto;
     * si hay una posterior vigente, ese costo sigue siendo el último.
     */
    private function restoreLastCost(Product $product, Purchase $cancelled): void
    {
        $hasLaterPurchase = PurchaseItem::query()
            ->where('product_id', $product->getKey())
            ->where('quantity', '>', 0)
            ->whereHas('purchase', fn ($query) => $query->notCancelled()->where('id', '>', $cancelled->getKey()))
            ->exists();

        if ($hasLaterPurchase) {
            return;
        }

        $previous = PurchaseItem::query()
            ->where('product_id', $product->getKey())
            ->where('quantity', '>', 0)
            ->whereHas('purchase', fn ($query) => $query->notCancelled()->where('id', '<', $cancelled->getKey()))
            ->orderByDesc('purchase_id')
            ->orderByDesc('id')
            ->first();

        // Sin una compra anterior no hay costo al cual volver: se deja el último conocido.
        if ($previous !== null) {
            $product->last_cost = $previous->unit_cost;
            $product->save();
        }
    }
}
