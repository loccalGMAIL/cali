<?php

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\PendingPurchaseService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchase
{
    public function __construct(
        private readonly PendingPurchaseService $pending,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Registra la factura de compra de una orden: lo que llegó y a qué costo. Lo que faltó vuelve
     * a "pendiente de compra" para pedirlo en otra orden (se completan primero las ventas más
     * antiguas). El costo de la factura queda como último costo del producto, que se sugiere
     * en las próximas ventas. Las ventas ya hechas no cambian.
     *
     * @param  array{purchase_order_id: int|string, date: string, invoice_number: string, notes?: string|null}  $data
     * @param  list<array{product_id: int|string, quantity: int|string|null, unit_cost: float|int|string|null}>  $items  lo recibido por producto
     *
     * @throws ValidationException
     */
    public function handle(array $data, array $items, User $user): Purchase
    {
        return DB::transaction(function () use ($data, $items, $user): Purchase {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($data['purchase_order_id']);

            if (! $order->isOpen()) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'La orden de compra ya se recibió o está anulada.',
                ]);
            }

            $this->ensureInvoiceIsUnique((int) $order->supplier_id, $data['invoice_number']);

            $this->pending->lockSaleItems($order->items()->pluck('sale_item_id')->all());

            $orderItems = $order->items()
                ->with(['product', 'saleItem.sale'])
                ->get()
                ->sortBy(fn ($item): string => sprintf('%s-%010d', $item->saleItem->sale->date->format('Ymd'), $item->sale_item_id))
                ->groupBy('product_id');

            $received = $this->receivedByProduct($items, $orderItems->map->sum('quantity')->all());

            $purchase = Purchase::create([
                'purchase_order_id' => $order->getKey(),
                'supplier_id' => $order->supplier_id,
                'date' => $data['date'],
                'invoice_number' => $data['invoice_number'],
                'notes' => $data['notes'] ?? null,
                'user_id' => $user->getKey(),
                'total' => 0,
            ]);

            $total = '0.00';

            foreach ($orderItems as $productId => $lines) {
                $quantity = $received[$productId]['quantity'];
                $unitCost = $received[$productId]['unit_cost'];
                $subtotal = bcmul((string) $quantity, $unitCost, 2);

                $purchase->items()->create([
                    'product_id' => $productId,
                    'ordered_quantity' => (int) $lines->sum('quantity'),
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                ]);

                $this->pending->receive($lines, $quantity);

                if ($quantity > 0) {
                    Product::query()->whereKey($productId)->update(['last_cost' => $unitCost]);
                }

                $total = bcadd($total, $subtotal, 2);
            }

            $purchase->update(['total' => $total]);
            $order->update(['status' => PurchaseOrderStatus::Received]);

            $this->activity->log(
                ActivityEvent::Created,
                "Registró la compra #{$purchase->getKey()} de {$purchase->supplier->name} por ".Money::format($total)." (orden #{$order->getKey()})",
                $purchase,
                ['invoice_number' => $purchase->invoice_number, 'total' => $total, 'items' => $orderItems->count()],
                $user,
            );

            return $purchase->load('items');
        });
    }

    /**
     * Normaliza lo recibido por producto y lo valida contra lo pedido: no puede llegar más de lo
     * pedido ni un producto que no estaba en la orden, y tiene que haber llegado algo.
     * Un producto de la orden que no figura en la factura cuenta como no recibido.
     *
     * @param  list<array{product_id: int|string, quantity: int|string|null, unit_cost: float|int|string|null}>  $items
     * @param  array<int, int>  $ordered  producto => cantidad pedida
     * @return array<int, array{quantity: int, unit_cost: string}>
     *
     * @throws ValidationException
     */
    private function receivedByProduct(array $items, array $ordered): array
    {
        $received = array_map(fn (): array => ['quantity' => 0, 'unit_cost' => '0.00'], $ordered);

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];

            if (! array_key_exists($productId, $ordered)) {
                throw ValidationException::withMessages(['items' => 'La factura tiene un producto que no está en la orden de compra.']);
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);

            if ($quantity < 0 || $unitCost < 0) {
                throw ValidationException::withMessages(['items' => 'La cantidad y el costo no pueden ser negativos.']);
            }

            $received[$productId] = [
                'quantity' => $received[$productId]['quantity'] + $quantity,
                'unit_cost' => number_format(round($unitCost, 2), 2, '.', ''),
            ];

            if ($received[$productId]['quantity'] > $ordered[$productId]) {
                $name = Product::query()->whereKey($productId)->value('name');

                throw ValidationException::withMessages([
                    'items' => "«{$name}»: se pidieron {$ordered[$productId]} y la factura dice {$received[$productId]['quantity']}.",
                ]);
            }
        }

        if (array_sum(array_column($received, 'quantity')) === 0) {
            throw ValidationException::withMessages([
                'items' => 'La factura no tiene ningún producto recibido. Si no llegó nada, anulá la orden de compra.',
            ]);
        }

        return $received;
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
