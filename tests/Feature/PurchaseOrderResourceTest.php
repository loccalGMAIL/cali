<?php

use App\Actions\CreatePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PendingPurchases\Pages\ListPendingPurchases;
use App\Filament\Resources\PendingPurchases\PendingPurchaseResource;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder as CreatePurchaseOrderPage;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->supplier = Supplier::factory()->create(['name' => 'Distribuidora Sur']);
    $this->product = Product::factory()->create(['name' => 'Heladera 360', 'sku' => 'HE-360', 'supplier_id' => $this->supplier->id]);
});

it('arma una orden desde el panel eligiendo qué ítems vendidos comprar', function () {
    $this->actingAs($this->admin);
    $first = soldItem($this->admin, $this->product, 3);
    $second = soldItem($this->admin, $this->product, 2);

    Livewire::test(CreatePurchaseOrderPage::class)
        ->fillForm([
            'supplier_id' => $this->supplier->id,
            'date' => '2026-11-09',
            'items' => [
                ['sale_item_id' => $first->id, 'quantity' => 2],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = PurchaseOrder::firstOrFail();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->items()->sum('quantity'))->toBe(2)
        ->and($first->fresh()->pending_quantity)->toBe(1)
        ->and($second->fresh()->pending_quantity)->toBe(2);
});

it('muestra en el formulario si se pide más de lo pendiente', function () {
    $this->actingAs($this->admin);
    $item = soldItem($this->admin, $this->product, 2);

    Livewire::test(CreatePurchaseOrderPage::class)
        ->fillForm([
            'supplier_id' => $this->supplier->id,
            'date' => '2026-11-09',
            'items' => [['sale_item_id' => $item->id, 'quantity' => 5]],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(PurchaseOrder::count())->toBe(0);
});

it('agrega de una vez los pendientes del proveedor habitual', function () {
    $this->actingAs($this->admin);
    $own = soldItem($this->admin, $this->product, 2);
    $foreign = soldItem($this->admin, Product::factory()->create(['supplier_id' => Supplier::factory()->create()->id]));

    $page = Livewire::test(CreatePurchaseOrderPage::class)
        ->fillForm(['supplier_id' => $this->supplier->id])
        ->callAction(TestAction::make('addSupplierPending')->schemaComponent('items'));

    expect(array_values($page->get('data.items')))->toBe([['sale_item_id' => $own->id, 'quantity' => 2]])
        ->and(collect($page->get('data.items'))->pluck('sale_item_id'))->not->toContain($foreign->id);
});

it('edita una orden: lo que se saca vuelve a pendiente', function () {
    $this->actingAs($this->admin);
    $kept = soldItem($this->admin, $this->product, 2);
    $removed = soldItem($this->admin, $this->product, 1);
    $order = issuedOrder($this->admin, $this->supplier, [$kept, $removed]);

    $page = Livewire::test(EditPurchaseOrder::class, ['record' => $order->getKey()]);

    expect(collect($page->get('data.items'))->pluck('sale_item_id')->map(fn ($id): int => (int) $id)->sort()->values()->all())->toBe([$kept->id, $removed->id]);

    $page->fillForm(['items' => [['sale_item_id' => $kept->id, 'quantity' => 2]]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($order->items()->pluck('sale_item_id')->all())->toBe([$kept->id])
        ->and($removed->fresh()->pending_quantity)->toBe(1)
        ->and($kept->fresh()->pending_quantity)->toBe(0);
});

it('emite, imprime y anula una orden desde su ficha', function () {
    $this->actingAs($this->admin);
    $item = soldItem($this->admin, $this->product, 2);
    $order = app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-09'],
        [['sale_item_id' => $item->id, 'quantity' => 2]],
        $this->admin,
    );

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getKey()])
        ->assertSuccessful()
        ->assertSee('Heladera 360')
        ->assertActionHasUrl('print', route('filament.admin.purchase-orders.print', $order))
        ->assertActionVisible('receive')
        ->callAction('issue')
        ->assertNotified('Orden emitida');

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Issued);

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getKey()])
        ->assertActionHidden('issue')
        ->callAction('cancel', data: ['reason' => 'El proveedor no tiene'])
        ->assertNotified('Anulación registrada');

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($item->fresh()->pending_quantity)->toBe(2);

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getKey()])
        ->assertActionHidden('receive')
        ->assertActionHidden('cancel')
        ->assertActionHidden('edit');
});

it('la versión imprimible lista los productos agrupados y no muestra costos', function () {
    $this->actingAs($this->admin);
    $order = issuedOrder($this->admin, $this->supplier, [
        soldItem($this->admin, $this->product, 2, 123456),
        soldItem($this->admin, $this->product, 1, 123456),
    ]);

    $this->get(route('filament.admin.purchase-orders.print', $order))
        ->assertOk()
        ->assertSee("Orden de compra #{$order->id}")
        ->assertSee('Distribuidora Sur')
        ->assertSee('HE-360')
        ->assertSee('Heladera 360')
        ->assertDontSee('123.456');
});

it('un vendedor no ve las órdenes de compra ni los pendientes, ni puede imprimir', function () {
    $order = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product)]);
    $this->actingAs(User::factory()->seller()->create());

    expect(PurchaseOrderResource::canViewAny())->toBeFalse()
        ->and(PurchaseOrderResource::canCreate())->toBeFalse()
        ->and(PendingPurchaseResource::canViewAny())->toBeFalse();

    $this->get(route('filament.admin.purchase-orders.print', $order))->assertForbidden();
});

it('lista las órdenes de compra', function () {
    $this->actingAs($this->admin);
    $order = issuedOrder($this->admin, $this->supplier, [soldItem($this->admin, $this->product)]);

    Livewire::test(ListPurchaseOrders::class)->assertCanSeeTableRecords([$order]);
});

it('la lista de pendientes muestra lo vendido sin pedir y arma una orden con lo elegido', function () {
    $this->actingAs($this->admin);
    $first = soldItem($this->admin, $this->product, 2);
    $second = soldItem($this->admin, $this->product, 1);
    $ordered = soldItem($this->admin, $this->product);
    issuedOrder($this->admin, $this->supplier, [$ordered]);

    Livewire::test(ListPendingPurchases::class)
        ->assertCanSeeTableRecords([$first, $second])
        ->assertCanNotSeeTableRecords([$ordered])
        ->selectTableRecords([$first, $second])
        ->callAction(TestAction::make('createOrder')->table()->bulk(), data: ['supplier_id' => $this->supplier->id])
        ->assertNotified();

    $order = PurchaseOrder::latest('id')->firstOrFail();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->items()->sum('quantity'))->toBe(3)
        ->and($first->fresh()->pending_quantity)->toBe(0)
        ->and($second->fresh()->pending_quantity)->toBe(0);
});

it('la ficha de la venta muestra costo, margen y si ya se pidió', function () {
    $this->actingAs($this->admin);
    $item = soldItem($this->admin, $this->product, 2, 600);

    Livewire::test(ViewSale::class, ['record' => $item->sale_id])
        ->assertSuccessful()
        ->assertSee('Margen unitario')
        ->assertSee('2 sin pedir');
});
