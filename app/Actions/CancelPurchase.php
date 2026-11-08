<?php

namespace App\Actions;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPurchase
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Anula una compra: descuenta del stock lo que había sumado y, si esa compra fijó el último
     * costo de un producto, lo devuelve al de la compra vigente anterior (recalculando el precio
     * si el producto está en modo automático). La compra no se borra: queda como historial.
     *
     * No se puede anular si parte de la mercadería ya se vendió (el stock no alcanzaría).
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

            $quantities = $locked->items()->get()
                ->groupBy('product_id')
                ->map(fn ($items): int => (int) $items->sum('quantity'))
                ->sortKeys();

            // Bloqueo en orden de id para no cruzarse con otra operación sobre los mismos productos.
            $products = Product::query()->whereIn('id', $quantities->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $errors = [];

            foreach ($quantities as $productId => $quantity) {
                $product = $products[$productId];

                if ($product->stock < $quantity) {
                    $errors[] = "«{$product->name}»: se compraron {$quantity} y solo quedan {$product->stock} en stock";
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'purchase' => 'No se puede anular la compra porque parte de la mercadería ya se vendió. '.implode('; ', $errors).'.',
                ]);
            }

            foreach ($quantities as $productId => $quantity) {
                $product = $products[$productId];

                $this->stock->record($product, StockMovementType::Adjustment, -$quantity, $locked, $user, "Anulación de la compra #{$locked->getKey()}");

                $this->restoreLastCost($product, $locked);
            }

            $locked->update([
                'cancelled_at' => now(),
                'cancelled_by' => $user->getKey(),
                'cancellation_reason' => $reason,
            ]);

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
            ->whereHas('purchase', fn ($query) => $query->notCancelled()->where('id', '>', $cancelled->getKey()))
            ->exists();

        if ($hasLaterPurchase) {
            return;
        }

        $previous = PurchaseItem::query()
            ->where('product_id', $product->getKey())
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
