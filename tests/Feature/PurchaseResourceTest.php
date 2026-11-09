<?php

use App\Actions\CreatePurchase as CreatePurchaseAction;
use App\Actions\CreatePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\Pages\ViewPurchase;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create(['last_cost' => 1000]);
});

it('carga la factura desde la orden con lo pedido precargado y corrige lo que llegó', function () {
    $this->actingAs($this->admin);
    $item = soldItem($this->admin, $this->product, 4, 1000);
    $order = issuedOrder($this->admin, $this->supplier, [$item]);

    $page = Livewire::test(CreatePurchase::class, ['orderId' => (string) $order->id])
        ->assertSchemaStateSet(['purchase_order_id' => (string) $order->id]);

    $key = array_key_first($page->get('data.items'));

    $page->assertSchemaStateSet([
        "items.{$key}.product_id" => $this->product->id,
        "items.{$key}.ordered_quantity" => 4,
        "items.{$key}.quantity" => 4,
        "items.{$key}.unit_cost" => '1000.00',
    ])
        ->fillForm([
            'invoice_number' => 'A-0001',
            'date' => '2026-11-05',
            "items.{$key}.quantity" => 3,
            "items.{$key}.unit_cost" => 1500,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $purchase = Purchase::where('invoice_number', 'A-0001')->firstOrFail();

    expect($purchase->total)->toBe('4500.00')
        ->and($purchase->user_id)->toBe($this->admin->id)
        ->and($purchase->supplier_id)->toBe($this->supplier->id)
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($item->fresh()->pending_quantity)->toBe(1)
        ->and($this->product->fresh()->last_cost)->toBe('1500.00');
});

it('al elegir la orden se cargan sus productos', function () {
    $this->actingAs($this->admin);
    $order = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product, 2)]);

    $page = Livewire::test(CreatePurchase::class)->fillForm(['purchase_order_id' => $order->id]);

    expect(array_values($page->get('data.items')))->toHaveCount(1)
        ->and(array_values($page->get('data.items'))[0]['ordered_quantity'])->toBe(2);
});

it('muestra error de formulario si llega más de lo pedido', function () {
    $this->actingAs($this->admin);
    $order = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product, 2)]);

    $page = Livewire::test(CreatePurchase::class, ['orderId' => (string) $order->id]);
    $key = array_key_first($page->get('data.items'));

    $page->fillForm(['invoice_number' => 'A-1', "items.{$key}.quantity" => 3])
        ->call('create')
        ->assertHasFormErrors(["items.{$key}.quantity"]);

    expect(Purchase::count())->toBe(0);
});

it('muestra error de formulario con factura repetida del mismo proveedor', function () {
    $this->actingAs($this->admin);
    $first = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product)]);
    $second = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product)]);
    app(CreatePurchaseAction::class)->handle(
        ['purchase_order_id' => $first->id, 'date' => '2026-11-05', 'invoice_number' => 'A-0001'],
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 100]],
        $this->admin,
    );

    Livewire::test(CreatePurchase::class, ['orderId' => (string) $second->id])
        ->fillForm(['invoice_number' => 'A-0001'])
        ->call('create')
        ->assertHasFormErrors(['invoice_number' => 'unique']);
});

it('solo ofrece las órdenes abiertas', function () {
    $this->actingAs($this->admin);
    $open = app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-02'],
        [['sale_item_id' => soldItem($this->admin, $this->product)->id, 'quantity' => 1]],
        $this->admin,
    );
    $received = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product)]);
    app(CreatePurchaseAction::class)->handle(
        ['purchase_order_id' => $received->id, 'date' => '2026-11-05', 'invoice_number' => 'X-1'],
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 100]],
        $this->admin,
    );

    Livewire::test(CreatePurchase::class)
        ->fillForm(['purchase_order_id' => $received->id, 'invoice_number' => 'X-2'])
        ->call('create')
        ->assertHasFormErrors(['purchase_order_id']);

    Livewire::test(CreatePurchase::class)
        ->fillForm(['purchase_order_id' => $open->id])
        ->assertHasNoFormErrors();
});

it('lista y muestra el detalle de una compra', function () {
    $this->actingAs($this->admin);
    $purchase = Purchase::factory()->create(['invoice_number' => 'B-0001']);

    Livewire::test(ListPurchases::class)->assertCanSeeTableRecords([$purchase]);
    Livewire::test(ViewPurchase::class, ['record' => $purchase->getKey()])->assertSuccessful();
});

it('no permite editar ni borrar compras, ni al admin', function () {
    $this->actingAs($this->admin);
    $purchase = Purchase::factory()->create();

    expect(PurchaseResource::canEdit($purchase))->toBeFalse()
        ->and(PurchaseResource::canDelete($purchase))->toBeFalse();
});

it('no permite a un vendedor acceder a compras', function () {
    $this->actingAs(User::factory()->seller()->create());

    expect(PurchaseResource::canViewAny())->toBeFalse()
        ->and(PurchaseResource::canCreate())->toBeFalse();
});

it('anula una compra desde la tabla y la orden vuelve a quedar emitida', function () {
    $this->actingAs($this->admin);
    $order = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product, 3)]);
    $purchase = app(CreatePurchaseAction::class)->handle(
        ['purchase_order_id' => $order->id, 'date' => '2026-11-05', 'invoice_number' => 'Z-1'],
        [['product_id' => $this->product->id, 'quantity' => 3, 'unit_cost' => 100]],
        $this->admin,
    );

    Livewire::test(ListPurchases::class)
        ->callAction(TestAction::make('cancel')->table($purchase), data: ['reason' => 'Factura equivocada'])
        ->assertNotified('Anulación registrada');

    expect($purchase->fresh()->isCancelled())->toBeTrue()
        ->and($order->fresh()->status)->toBe(PurchaseOrderStatus::Issued);
});

it('avisa en pantalla si lo que faltó ya se volvió a pedir', function () {
    $this->actingAs($this->admin);
    $item = soldItem($this->admin, $this->product, 3);
    $order = issuedOrder($this->admin, $this->supplier, [$item]);
    $purchase = app(CreatePurchaseAction::class)->handle(
        ['purchase_order_id' => $order->id, 'date' => '2026-11-05', 'invoice_number' => 'Z-2'],
        [['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => 100]],
        $this->admin,
    );
    issuedOrder($this->admin, $this->supplier, [$item]);

    Livewire::test(ViewPurchase::class, ['record' => $purchase->getKey()])
        ->callAction('cancel', data: ['reason' => 'Error'])
        ->assertNotified('No se pudo anular');

    expect($purchase->fresh()->isCancelled())->toBeFalse();
});

it('no ofrece anular una compra ya anulada', function () {
    $this->actingAs($this->admin);
    $purchase = Purchase::factory()->create(['cancelled_at' => now()]);

    Livewire::test(ListPurchases::class)->assertActionHidden(TestAction::make('cancel')->table($purchase));
});
