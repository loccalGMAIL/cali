<?php

use App\Actions\CreatePurchase;
use App\Enums\PriceMode;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function purchaseData(Supplier $supplier, string $invoice = '0001-00000001'): array
{
    return [
        'supplier_id' => $supplier->id,
        'date' => '2026-11-01',
        'invoice_number' => $invoice,
        'notes' => null,
    ];
}

it('suma stock, registra movimientos y calcula el total', function () {
    $user = User::factory()->admin()->create();
    $supplier = Supplier::factory()->create();
    $a = Product::factory()->create(['stock' => 5]);
    $b = Product::factory()->create(['stock' => 0]);

    $purchase = app(CreatePurchase::class)->handle(purchaseData($supplier), [
        ['product_id' => $a->id, 'quantity' => 3, 'unit_cost' => 1000],
        ['product_id' => $b->id, 'quantity' => 2, 'unit_cost' => 250.50],
    ], $user);

    expect($purchase->total)->toBe('3501.00')
        ->and($purchase->items)->toHaveCount(2)
        ->and($a->fresh()->stock)->toBe(8)
        ->and($b->fresh()->stock)->toBe(2);

    $movement = $purchase->stockMovements()->where('product_id', $a->id)->first();
    expect($movement->type)->toBe(StockMovementType::Purchase)
        ->and($movement->quantity)->toBe(3)
        ->and($movement->user_id)->toBe($user->id);
});

it('actualiza el último costo y recalcula el precio en modo automático', function () {
    $product = Product::factory()->create(['last_cost' => 1000, 'margin_percent' => 50, 'price_mode' => PriceMode::Auto]);

    app(CreatePurchase::class)->handle(purchaseData(Supplier::factory()->create()), [
        ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 2000],
    ], User::factory()->admin()->create());

    $product->refresh();
    expect($product->last_cost)->toBe('2000.00')
        ->and($product->sale_price)->toBe('3000.00');
});

it('actualiza el último costo pero no pisa el precio en modo manual', function () {
    $product = Product::factory()->manualPrice(5000)->create(['last_cost' => 1000, 'margin_percent' => 50]);

    app(CreatePurchase::class)->handle(purchaseData(Supplier::factory()->create()), [
        ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 2000],
    ], User::factory()->admin()->create());

    $product->refresh();
    expect($product->last_cost)->toBe('2000.00')
        ->and($product->sale_price)->toBe('5000.00');
});

it('rechaza un número de factura repetido para el mismo proveedor', function () {
    $user = User::factory()->admin()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['stock' => 0]);
    $items = [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 100]];

    app(CreatePurchase::class)->handle(purchaseData($supplier), $items, $user);

    expect(fn () => app(CreatePurchase::class)->handle(purchaseData($supplier), $items, $user))
        ->toThrow(ValidationException::class);

    expect(Purchase::count())->toBe(1)
        ->and($product->fresh()->stock)->toBe(1);
});

it('permite el mismo número de factura en otro proveedor', function () {
    $user = User::factory()->admin()->create();
    $product = Product::factory()->create();
    $items = [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 100]];

    app(CreatePurchase::class)->handle(purchaseData(Supplier::factory()->create()), $items, $user);
    app(CreatePurchase::class)->handle(purchaseData(Supplier::factory()->create()), $items, $user);

    expect(Purchase::count())->toBe(2);
});

it('no deja nada guardado si falla a mitad de la compra', function () {
    $user = User::factory()->admin()->create();
    $product = Product::factory()->create(['stock' => 4]);

    expect(fn () => app(CreatePurchase::class)->handle(purchaseData(Supplier::factory()->create()), [
        ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 100],
        ['product_id' => 999999, 'quantity' => 1, 'unit_cost' => 100],
    ], $user))->toThrow(Exception::class);

    expect(Purchase::count())->toBe(0)
        ->and($product->fresh()->stock)->toBe(4)
        ->and($product->stockMovements()->count())->toBe(0);
});

it('exige al menos un ítem', function () {
    expect(fn () => app(CreatePurchase::class)->handle(
        purchaseData(Supplier::factory()->create()),
        [],
        User::factory()->admin()->create(),
    ))->toThrow(ValidationException::class);
});
