<?php

use App\Actions\CancelPurchase;
use App\Actions\CreatePurchase;
use App\Actions\CreatePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->supplier = Supplier::factory()->create();

    // Vende, arma la orden y carga la factura con lo recibido de cada producto.
    $this->buy = function (Product $product, int $ordered, int $received, float $cost, string $invoice): Purchase {
        $order = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $product, $ordered)]);

        return app(CreatePurchase::class)->handle([
            'purchase_order_id' => $order->id,
            'date' => '2026-11-05',
            'invoice_number' => $invoice,
        ], [['product_id' => $product->id, 'quantity' => $received, 'unit_cost' => $cost]], $this->admin);
    };
});

it('anula la compra con quién, cuándo y por qué, y la orden vuelve a quedar emitida', function () {
    $purchase = ($this->buy)(Product::factory()->create(), 1, 1, 100, 'A-1');
    $canceller = User::factory()->admin()->create();

    $cancelled = app(CancelPurchase::class)->handle($purchase, 'Factura equivocada', $canceller);
    $order = $purchase->purchaseOrder->fresh();

    expect($cancelled->isCancelled())->toBeTrue()
        ->and($cancelled->cancelled_by)->toBe($canceller->id)
        ->and($cancelled->cancellation_reason)->toBe('Factura equivocada')
        ->and(Purchase::notCancelled()->count())->toBe(0)
        ->and($order->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($order->purchase)->toBeNull()
        ->and($order->items()->pluck('received_quantity')->all())->toBe([null]);
});

it('vuelve a descontar de pendientes lo que había faltado', function () {
    $purchase = ($this->buy)(Product::factory()->create(), 3, 2, 100, 'A-1');
    $saleItem = $purchase->purchaseOrder->items->first()->saleItem;
    expect($saleItem->fresh()->pending_quantity)->toBe(1);

    app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin);

    expect($saleItem->fresh()->pending_quantity)->toBe(0);
});

it('rechaza anular si lo que faltó ya se pidió en otra orden', function () {
    $purchase = ($this->buy)(Product::factory()->create(), 3, 2, 100, 'A-1');
    $saleItem = $purchase->purchaseOrder->items->first()->saleItem;

    app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-06'],
        [['sale_item_id' => $saleItem->id, 'quantity' => 1]],
        $this->admin,
    );

    expect(fn () => app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin))
        ->toThrow(ValidationException::class, 'ya se incluyó en otra orden');

    expect($purchase->fresh()->isCancelled())->toBeFalse()
        ->and($purchase->purchaseOrder->fresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('devuelve el último costo al de la compra anterior', function () {
    $product = Product::factory()->create(['last_cost' => 1000]);
    ($this->buy)($product, 1, 1, 1200, 'A-1');
    $second = ($this->buy)($product, 1, 1, 1500, 'A-2');

    app(CancelPurchase::class)->handle($second, 'Error', $this->admin);

    expect($product->fresh()->last_cost)->toBe('1200.00');
});

it('mantiene el último costo si existe una compra posterior vigente', function () {
    $product = Product::factory()->create(['last_cost' => 1000]);
    $first = ($this->buy)($product, 1, 1, 1200, 'A-1');
    ($this->buy)($product, 1, 1, 1500, 'A-2');

    app(CancelPurchase::class)->handle($first, 'Error', $this->admin);

    expect($product->fresh()->last_cost)->toBe('1500.00');
});

it('deja el último costo si no hay una compra anterior a la cual volver', function () {
    $product = Product::factory()->create(['last_cost' => 1000]);
    $purchase = ($this->buy)($product, 1, 1, 1200, 'A-1');

    app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin);

    expect($product->fresh()->last_cost)->toBe('1200.00');
});

it('permite volver a cargar la misma factura después de anular la compra', function () {
    $product = Product::factory()->create();
    $purchase = ($this->buy)($product, 1, 1, 100, 'A-1');

    app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin);

    $again = app(CreatePurchase::class)->handle([
        'purchase_order_id' => $purchase->purchase_order_id,
        'date' => '2026-11-05',
        'invoice_number' => 'A-1',
    ], [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 110]], $this->admin);

    expect($again->isCancelled())->toBeFalse()
        ->and(PurchaseOrder::findOrFail($purchase->purchase_order_id)->status)->toBe(PurchaseOrderStatus::Received)
        ->and(Purchase::where('invoice_number', 'A-1')->count())->toBe(2);
});

it('rechaza anular dos veces la misma compra', function () {
    $purchase = ($this->buy)(Product::factory()->create(), 1, 1, 100, 'A-1');
    app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin);

    expect(fn () => app(CancelPurchase::class)->handle($purchase, 'Otra vez', $this->admin))
        ->toThrow(ValidationException::class, 'ya está anulada');
});
