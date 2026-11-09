<?php

use App\Actions\CreatePurchase;
use App\Actions\CreatePurchaseOrder;
use App\Actions\CreateSale;
use App\Actions\IssuePurchaseOrder;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Vende un producto (costo + margen) a un cliente nuevo y devuelve el ítem vendido,
 * que queda pendiente de compra.
 */
function soldItem(User $user, Product $product, int $quantity = 1, float $cost = 600, string $date = '2026-11-01'): SaleItem
{
    $sale = app(CreateSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'seller_id' => $user->id,
        'financing_plan_id' => FinancingPlan::factory()->create(['installments_count' => 3])->id,
        'date' => $date,
        'first_due_date' => '2026-12-01',
        'down_payment' => 0,
    ], [['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $cost, 'unit_margin' => $cost]], $user);

    return $sale->items->first();
}

/**
 * Orden de compra emitida con todo lo pendiente de los ítems vendidos indicados.
 *
 * @param  list<SaleItem>  $saleItems
 */
function issuedOrder(User $user, Supplier $supplier, array $saleItems): PurchaseOrder
{
    $order = app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $supplier->id, 'date' => '2026-11-02'],
        array_map(fn (SaleItem $item): array => ['sale_item_id' => $item->id, 'quantity' => $item->fresh()->pending_quantity], $saleItems),
        $user,
    );

    return app(IssuePurchaseOrder::class)->handle($order, $user);
}

/**
 * Factura de compra completa: vende, arma la orden al proveedor y la recibe entera.
 */
function receivedPurchase(User $user, Supplier $supplier, Product $product, int $quantity, float $unitCost, string $invoice, ?string $date = null): Purchase
{
    $order = issuedOrder($user, $supplier, [soldItem($user, $product, $quantity)]);

    return app(CreatePurchase::class)->handle([
        'purchase_order_id' => $order->id,
        'date' => $date ?? today()->toDateString(),
        'invoice_number' => $invoice,
    ], [['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $unitCost]], $user);
}
