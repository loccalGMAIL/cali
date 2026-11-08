<?php

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchase
{
    public function __construct(
        private readonly StockService $stock,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Registra una factura de compra: suma el stock, actualiza el último costo de
     * cada producto y, si está en modo automático, recalcula su precio de venta.
     *
     * @param  array{supplier_id: int, date: string, invoice_number: string, notes?: string|null}  $data
     * @param  list<array{product_id: int, quantity: int, unit_cost: float|int|string}>  $items
     *
     * @throws ValidationException
     */
    public function handle(array $data, array $items, User $user): Purchase
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'La compra necesita al menos un ítem.']);
        }

        return DB::transaction(function () use ($data, $items, $user): Purchase {
            $this->ensureInvoiceIsUnique((int) $data['supplier_id'], $data['invoice_number']);

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'date' => $data['date'],
                'invoice_number' => $data['invoice_number'],
                'notes' => $data['notes'] ?? null,
                'user_id' => $user->getKey(),
                'total' => 0,
            ]);

            $total = '0.00';

            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];
                $unitCost = number_format(round((float) $item['unit_cost'], 2), 2, '.', '');
                $subtotal = bcmul((string) $quantity, $unitCost, 2);

                $purchase->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                ]);

                $product = Product::query()->findOrFail($item['product_id']);

                $this->stock->record($product, StockMovementType::Purchase, $quantity, $purchase, $user);

                // Criterio: último costo. El precio se recalcula solo si el modo es automático.
                $product->last_cost = $unitCost;
                $product->save();

                $total = bcadd($total, $subtotal, 2);
            }

            $purchase->update(['total' => $total]);

            $this->activity->log(
                ActivityEvent::Created,
                "Registró la compra #{$purchase->getKey()} de {$purchase->supplier->name} por ".Money::format($total),
                $purchase,
                ['invoice_number' => $purchase->invoice_number, 'total' => $total, 'items' => count($items)],
                $user,
            );

            return $purchase->load('items');
        });
    }

    /**
     * La factura no puede repetirse para un mismo proveedor entre las compras vigentes
     * (la de una compra anulada se puede volver a cargar). Se bloquea al proveedor para
     * que dos cargas simultáneas no pasen la validación a la vez.
     */
    private function ensureInvoiceIsUnique(int $supplierId, string $invoiceNumber): void
    {
        Supplier::query()->lockForUpdate()->findOrFail($supplierId);

        $exists = Purchase::query()
            ->notCancelled()
            ->where('supplier_id', $supplierId)
            ->where('invoice_number', $invoiceNumber)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'invoice_number' => 'Ya existe una compra con ese número de factura para este proveedor.',
            ]);
        }
    }
}
