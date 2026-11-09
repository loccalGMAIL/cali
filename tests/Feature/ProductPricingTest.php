<?php

use App\Actions\CreatePurchase;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('el último costo sale de la factura de compra y se sugiere al vender', function () {
    $product = Product::factory()->create(['last_cost' => 500]);
    $order = issuedOrder($this->admin, Supplier::factory()->create(), [soldItem($this->admin, $product)]);

    app(CreatePurchase::class)->handle(
        ['purchase_order_id' => $order->id, 'date' => '2026-11-05', 'invoice_number' => 'A-1'],
        [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 720]],
        $this->admin,
    );

    Livewire::test(CreateSale::class)
        ->fillForm(['items' => [['product_id' => null, 'quantity' => 1, 'unit_margin' => 0]]])
        ->fillForm(['items.0.product_id' => $product->id])
        ->assertSchemaStateSet(['items.0.unit_cost' => '720.00']);
});

it('el último costo no se edita a mano desde el producto', function () {
    $product = Product::factory()->create(['last_cost' => 500]);

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])
        ->fillForm(['name' => 'Otro nombre', 'last_cost' => 9999])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh())
        ->name->toBe('Otro nombre')
        ->last_cost->toBe('500.00');
});

it('el producto guarda su proveedor habitual', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])
        ->fillForm(['supplier_id' => $supplier->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->supplier->is($supplier))->toBeTrue();
});
