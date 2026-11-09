<?php

use App\Actions\CancelSale;
use App\Actions\CreatePurchase;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function invoiceData(PurchaseOrder $order, string $invoice = '0001-00000001'): array
{
    return [
        'purchase_order_id' => $order->id,
        'date' => '2026-11-05',
        'invoice_number' => $invoice,
    ];
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create(['last_cost' => 500]);
});

it('registra la factura de la orden, calcula el total y marca la orden recibida', function () {
    $other = Product::factory()->create();
    $order = issuedOrder($this->admin, $this->supplier, [
        soldItem($this->admin, $this->product, 2),
        soldItem($this->admin, $other, 1),
    ]);

    $purchase = app(CreatePurchase::class)->handle(invoiceData($order), [
        ['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => 650],
        ['product_id' => $other->id, 'quantity' => 1, 'unit_cost' => '99.99'],
    ], $this->admin);

    expect($purchase->total)->toBe('1399.99')
        ->and($purchase->supplier_id)->toBe($this->supplier->id)
        ->and($purchase->purchase_order_id)->toBe($order->id)
        ->and($purchase->items)->toHaveCount(2)
        ->and($purchase->items->firstWhere('product_id', $this->product->id)->ordered_quantity)->toBe(2)
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->fresh()->purchase->is($purchase))->toBeTrue()
        ->and($order->items()->pluck('received_quantity')->all())->toBe([2, 1]);
});

it('el costo de la factura queda como último costo del producto, sin tocar las ventas', function () {
    $item = soldItem($this->admin, $this->product, 1, 600);
    $order = issuedOrder($this->admin, $this->supplier, [$item]);

    app(CreatePurchase::class)->handle(invoiceData($order), [
        ['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 640],
    ], $this->admin);

    expect($this->product->fresh()->last_cost)->toBe('640.00')
        ->and($item->fresh()->unit_cost)->toBe('600.00')
        ->and($item->sale->fresh()->total_cost)->toBe('600.00');
});

it('lo que no llegó vuelve a pendiente, completando primero las ventas más antiguas', function () {
    $older = soldItem($this->admin, $this->product, 2, date: '2026-10-20');
    $newer = soldItem($this->admin, $this->product, 2, date: '2026-10-25');
    $order = issuedOrder($this->admin, $this->supplier, [$newer, $older]);

    $purchase = app(CreatePurchase::class)->handle(invoiceData($order), [
        ['product_id' => $this->product->id, 'quantity' => 3, 'unit_cost' => 600],
    ], $this->admin);

    expect($purchase->items->first()->quantity)->toBe(3)
        ->and($purchase->items->first()->ordered_quantity)->toBe(4)
        ->and($purchase->total)->toBe('1800.00')
        ->and($older->fresh()->pending_quantity)->toBe(0)
        ->and($newer->fresh()->pending_quantity)->toBe(1);
});

it('un producto de la orden que no figura en la factura cuenta como no recibido', function () {
    $missing = Product::factory()->create(['last_cost' => 100]);
    $missingItem = soldItem($this->admin, $missing, 1);
    $order = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product), $missingItem]);

    $purchase = app(CreatePurchase::class)->handle(invoiceData($order), [
        ['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600],
    ], $this->admin);

    expect($purchase->items)->toHaveCount(2)
        ->and($purchase->items->firstWhere('product_id', $missing->id)->quantity)->toBe(0)
        ->and($missingItem->fresh()->pending_quantity)->toBe(1)
        ->and($missing->fresh()->last_cost)->toBe('100.00');
});

it('lo que falta de una venta anulada no vuelve a pendiente', function () {
    $item = soldItem($this->admin, $this->product, 2);
    $order = issuedOrder($this->admin, $this->supplier, [$item]);
    app(CancelSale::class)->handle($item->sale, 'Error', $this->admin);

    app(CreatePurchase::class)->handle(invoiceData($order), [
        ['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600],
    ], $this->admin);

    expect($item->fresh()->pending_quantity)->toBe(0);
});

it('rechaza recibir más de lo pedido o un producto que no está en la orden', function (Closure $items) {
    $order = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product, 2)]);

    expect(fn () => app(CreatePurchase::class)->handle(invoiceData($order), $items($this), $this->admin))
        ->toThrow(ValidationException::class);

    expect(Purchase::count())->toBe(0)
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::Issued);
})->with([
    'más de lo pedido' => [fn ($test) => [['product_id' => $test->product->id, 'quantity' => 3, 'unit_cost' => 600]]],
    'producto ajeno' => [fn ($test) => [['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'unit_cost' => 600]]],
    'no llegó nada' => [fn ($test) => [['product_id' => $test->product->id, 'quantity' => 0, 'unit_cost' => 600]]],
]);

it('rechaza cargar la factura de una orden ya recibida o anulada', function () {
    $order = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product)]);
    $items = [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600]];

    app(CreatePurchase::class)->handle(invoiceData($order), $items, $this->admin);

    expect(fn () => app(CreatePurchase::class)->handle(invoiceData($order, 'B-1'), $items, $this->admin))
        ->toThrow(ValidationException::class, 'ya se recibió');
});

it('rechaza un número de factura repetido para el mismo proveedor', function () {
    $first = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product)]);
    $second = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product)]);
    $items = [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600]];

    app(CreatePurchase::class)->handle(invoiceData($first), $items, $this->admin);

    expect(fn () => app(CreatePurchase::class)->handle(invoiceData($second), $items, $this->admin))
        ->toThrow(ValidationException::class);

    expect(Purchase::count())->toBe(1)
        ->and($second->fresh()->status)->toBe(PurchaseOrderStatus::Issued);
});

it('permite el mismo número de factura en otro proveedor', function () {
    $first = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product)]);
    $second = issuedOrder($this->admin, Supplier::factory()->create(), [soldItem($this->admin, $this->product)]);
    $items = [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600]];

    app(CreatePurchase::class)->handle(invoiceData($first), $items, $this->admin);
    app(CreatePurchase::class)->handle(invoiceData($second), $items, $this->admin);

    expect(Purchase::count())->toBe(2);
});
