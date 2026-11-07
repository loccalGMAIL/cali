<?php

use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\Pages\ViewPurchase;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('carga una compra desde el panel y actualiza stock, costo y precio', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['stock' => 1, 'last_cost' => 1000, 'margin_percent' => 20]);

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'A-0001',
            'date' => '2026-11-02',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 1500],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $purchase = Purchase::where('invoice_number', 'A-0001')->firstOrFail();
    $product->refresh();

    expect($purchase->total)->toBe('6000.00')
        ->and($purchase->user_id)->toBe($admin->id)
        ->and($product->stock)->toBe(5)
        ->and($product->last_cost)->toBe('1500.00')
        ->and($product->sale_price)->toBe('1800.00');
});

it('muestra error de formulario con factura repetida del mismo proveedor', function () {
    $this->actingAs(User::factory()->admin()->create());
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    Purchase::factory()->create(['supplier_id' => $supplier->id, 'invoice_number' => 'A-0001']);

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'A-0001',
            'date' => '2026-11-02',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 100]],
        ])
        ->call('create')
        ->assertHasFormErrors(['invoice_number' => 'unique']);
});

it('exige al menos un producto', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'supplier_id' => Supplier::factory()->create()->id,
            'invoice_number' => 'A-0002',
            'date' => '2026-11-02',
            'items' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['items']);
});

it('lista y muestra el detalle de una compra', function () {
    $this->actingAs(User::factory()->admin()->create());
    $purchase = Purchase::factory()->create(['invoice_number' => 'B-0001']);

    Livewire::test(ListPurchases::class)->assertCanSeeTableRecords([$purchase]);
    Livewire::test(ViewPurchase::class, ['record' => $purchase->getKey()])->assertSuccessful();
});

it('no permite editar ni borrar compras, ni al admin', function () {
    $this->actingAs(User::factory()->admin()->create());
    $purchase = Purchase::factory()->create();

    expect(PurchaseResource::canEdit($purchase))->toBeFalse()
        ->and(PurchaseResource::canDelete($purchase))->toBeFalse();
});

it('no permite a un vendedor acceder a compras', function () {
    $this->actingAs(User::factory()->seller()->create());

    expect(PurchaseResource::canViewAny())->toBeFalse()
        ->and(PurchaseResource::canCreate())->toBeFalse();
});
