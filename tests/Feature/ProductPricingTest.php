<?php

use App\Enums\PriceMode;
use App\Models\Product;

it('calcula el precio en modo automático como costo más margen', function () {
    $product = Product::factory()->create([
        'last_cost' => 100000,
        'margin_percent' => 30,
        'price_mode' => PriceMode::Auto,
    ]);

    expect($product->sale_price)->toBe('130000.00');
});

it('recalcula el precio automático al cambiar el costo o el margen', function () {
    $product = Product::factory()->create(['last_cost' => 100000, 'margin_percent' => 30]);

    $product->update(['last_cost' => 120000]);
    expect($product->fresh()->sale_price)->toBe('156000.00');

    $product->update(['margin_percent' => 50]);
    expect($product->fresh()->sale_price)->toBe('180000.00');
});

it('redondea el precio automático a dos decimales', function () {
    $product = Product::factory()->create(['last_cost' => 100.10, 'margin_percent' => 33.33]);

    expect($product->sale_price)->toBe('133.46');
});

it('no pisa el precio en modo manual', function () {
    $product = Product::factory()->manualPrice(250000)->create(['last_cost' => 100000, 'margin_percent' => 30]);

    $product->update(['last_cost' => 200000]);

    expect($product->fresh()->sale_price)->toBe('250000.00');
});

it('marca el stock bajo cuando está por debajo del mínimo', function () {
    $low = Product::factory()->create(['stock' => 1, 'min_stock' => 3]);
    $ok = Product::factory()->create(['stock' => 3, 'min_stock' => 3]);

    expect($low->isLowStock())->toBeTrue()
        ->and($ok->isLowStock())->toBeFalse()
        ->and(Product::lowStock()->pluck('id')->all())->toBe([$low->id]);
});
