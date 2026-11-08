<?php

use App\Actions\CancelPurchase;
use App\Actions\CreatePurchase;
use App\Enums\PriceMode;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->supplier = Supplier::factory()->create();

    $this->buy = fn (Product $product, int $quantity, float $cost, string $invoice): Purchase => app(CreatePurchase::class)->handle([
        'supplier_id' => $this->supplier->id,
        'date' => '2026-11-01',
        'invoice_number' => $invoice,
    ], [['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $cost]], $this->admin);
});

it('descuenta el stock y registra el movimiento de ajuste', function () {
    $product = Product::factory()->create(['stock' => 2, 'last_cost' => 1000]);
    $purchase = ($this->buy)($product, 5, 1500, 'A-1');

    app(CancelPurchase::class)->handle($purchase, 'Error de carga', $this->admin);

    $movement = $purchase->stockMovements()->where('type', StockMovementType::Adjustment)->firstOrFail();

    expect($product->fresh()->stock)->toBe(2)
        ->and($movement->quantity)->toBe(-5);
});

it('anula la compra con quién, cuándo y por qué', function () {
    $product = Product::factory()->create(['stock' => 0]);
    $purchase = ($this->buy)($product, 1, 100, 'A-1');
    $canceller = User::factory()->admin()->create();

    $cancelled = app(CancelPurchase::class)->handle($purchase, 'Factura equivocada', $canceller);

    expect($cancelled->isCancelled())->toBeTrue()
        ->and($cancelled->cancelled_by)->toBe($canceller->id)
        ->and($cancelled->cancellation_reason)->toBe('Factura equivocada')
        ->and(Purchase::notCancelled()->count())->toBe(0)
        ->and(Purchase::count())->toBe(1);
});

it('rechaza anular si parte de la mercadería ya se vendió', function () {
    $product = Product::factory()->create(['stock' => 0]);
    $purchase = ($this->buy)($product, 5, 100, 'A-1');
    $product->update(['stock' => 3]); // se vendieron 2

    expect(fn () => app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin))
        ->toThrow(ValidationException::class, 'ya se vendió');

    expect($purchase->fresh()->isCancelled())->toBeFalse()
        ->and($product->fresh()->stock)->toBe(3);
});

it('devuelve el último costo al de la compra anterior y recalcula el precio automático', function () {
    $product = Product::factory()->create(['stock' => 0, 'last_cost' => 100, 'margin_percent' => 50, 'price_mode' => PriceMode::Auto]);
    ($this->buy)($product, 1, 1000, 'A-1');
    $second = ($this->buy)($product, 1, 2000, 'A-2');

    expect($product->fresh()->last_cost)->toBe('2000.00')
        ->and($product->fresh()->sale_price)->toBe('3000.00');

    app(CancelPurchase::class)->handle($second, 'Error', $this->admin);

    $product->refresh();
    expect($product->last_cost)->toBe('1000.00')
        ->and($product->sale_price)->toBe('1500.00');
});

it('mantiene el último costo si existe una compra posterior vigente', function () {
    $product = Product::factory()->create(['stock' => 0]);
    $first = ($this->buy)($product, 1, 1000, 'A-1');
    ($this->buy)($product, 1, 2000, 'A-2');

    app(CancelPurchase::class)->handle($first, 'Error', $this->admin);

    expect($product->fresh()->last_cost)->toBe('2000.00');
});

it('no toca el precio de un producto en modo manual', function () {
    $product = Product::factory()->manualPrice(9999)->create(['stock' => 0, 'last_cost' => 100]);
    ($this->buy)($product, 1, 1000, 'A-1');
    $second = ($this->buy)($product, 1, 2000, 'A-2');

    app(CancelPurchase::class)->handle($second, 'Error', $this->admin);

    $product->refresh();
    expect($product->last_cost)->toBe('1000.00')
        ->and($product->sale_price)->toBe('9999.00');
});

it('deja el último costo si no hay una compra anterior a la cual volver', function () {
    $product = Product::factory()->create(['stock' => 0, 'last_cost' => 100]);
    $purchase = ($this->buy)($product, 1, 1000, 'A-1');

    app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin);

    expect($product->fresh()->last_cost)->toBe('1000.00');
});

it('permite volver a cargar la misma factura después de anular la compra', function () {
    $product = Product::factory()->create(['stock' => 0]);
    $purchase = ($this->buy)($product, 1, 1000, 'A-1');
    app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin);

    ($this->buy)($product, 2, 1100, 'A-1');

    expect(Purchase::notCancelled()->count())->toBe(1)
        ->and($product->fresh()->stock)->toBe(2);
});

it('sigue rechazando la misma factura entre compras vigentes', function () {
    $product = Product::factory()->create(['stock' => 0]);
    ($this->buy)($product, 1, 1000, 'A-1');

    expect(fn () => ($this->buy)($product, 1, 1000, 'A-1'))->toThrow(ValidationException::class);
});

it('rechaza anular dos veces la misma compra', function () {
    $product = Product::factory()->create(['stock' => 0]);
    $purchase = ($this->buy)($product, 3, 1000, 'A-1');
    app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin);

    expect(fn () => app(CancelPurchase::class)->handle($purchase, 'Otra vez', $this->admin))
        ->toThrow(ValidationException::class);

    expect($product->fresh()->stock)->toBe(0);
});

it('anula una compra con varios productos de forma atómica', function () {
    $a = Product::factory()->create(['stock' => 0]);
    $b = Product::factory()->create(['stock' => 0]);
    $purchase = app(CreatePurchase::class)->handle([
        'supplier_id' => $this->supplier->id,
        'date' => '2026-11-01',
        'invoice_number' => 'M-1',
    ], [
        ['product_id' => $a->id, 'quantity' => 2, 'unit_cost' => 100],
        ['product_id' => $b->id, 'quantity' => 4, 'unit_cost' => 200],
    ], $this->admin);
    $b->update(['stock' => 1]); // se vendieron 3 de b: no alcanza

    expect(fn () => app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin))
        ->toThrow(ValidationException::class);

    expect($a->fresh()->stock)->toBe(2)
        ->and($purchase->fresh()->isCancelled())->toBeFalse();
});
