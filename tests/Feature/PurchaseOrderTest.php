<?php

use App\Actions\CancelPurchaseOrder;
use App\Actions\CancelSale;
use App\Actions\CreatePurchaseOrder;
use App\Actions\IssuePurchaseOrder;
use App\Actions\UpdatePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
});

it('arma una orden en borrador con lo pendiente y lo saca de pendientes', function () {
    $first = soldItem($this->admin, $this->product, 3);
    $second = soldItem($this->admin, $this->product, 2);

    $order = app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-02', 'notes' => 'Retirar el jueves'],
        [['sale_item_id' => $first->id, 'quantity' => 3], ['sale_item_id' => $second->id, 'quantity' => 1]],
        $this->admin,
    );

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->supplier_id)->toBe($this->supplier->id)
        ->and($order->user_id)->toBe($this->admin->id)
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->first()->product_id)->toBe($this->product->id)
        ->and($first->fresh()->pending_quantity)->toBe(0)
        ->and($second->fresh()->pending_quantity)->toBe(1);
});

it('agrupa lo pedido por producto con el costo de referencia de las ventas', function () {
    $other = Product::factory()->create(['name' => 'Aaa primero']);
    $order = issuedOrder($this->admin, $this->supplier, [
        soldItem($this->admin, $this->product, 1, 600),
        soldItem($this->admin, $this->product, 3, 700),
        soldItem($this->admin, $other, 2, 100),
    ]);

    $lines = $order->linesByProduct();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]['product']->is($other))->toBeTrue()
        ->and($lines[1]['quantity'])->toBe(4)
        ->and($lines[1]['reference_cost'])->toBe('675.00') // (600 + 3 × 700) / 4
        ->and($lines[1]['received_quantity'])->toBeNull();
});

it('no deja pedir más de lo pendiente', function () {
    $item = soldItem($this->admin, $this->product, 2);

    expect(fn () => app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-02'],
        [['sale_item_id' => $item->id, 'quantity' => 3]],
        $this->admin,
    ))->toThrow(ValidationException::class, 'quedan 2 pendientes');

    expect(PurchaseOrder::count())->toBe(0)
        ->and($item->fresh()->pending_quantity)->toBe(2);
});

it('suma las cantidades de un mismo ítem repetido al validar lo pendiente', function () {
    $item = soldItem($this->admin, $this->product, 2);

    expect(fn () => app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-02'],
        [['sale_item_id' => $item->id, 'quantity' => 2], ['sale_item_id' => $item->id, 'quantity' => 1]],
        $this->admin,
    ))->toThrow(ValidationException::class);

    expect($item->fresh()->pending_quantity)->toBe(2);
});

it('no deja pedir lo de una venta anulada', function () {
    $item = soldItem($this->admin, $this->product);
    app(CancelSale::class)->handle($item->sale, 'Error', $this->admin);
    $item->update(['pending_quantity' => 1]); // aun si quedara pendiente por error

    expect(fn () => app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-02'],
        [['sale_item_id' => $item->id, 'quantity' => 1]],
        $this->admin,
    ))->toThrow(ValidationException::class, 'está anulada');
});

it('exige al menos un ítem', function () {
    expect(fn () => app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-02'],
        [],
        $this->admin,
    ))->toThrow(ValidationException::class);

    expect(PurchaseOrder::count())->toBe(0);
});

it('al editar, lo que se saca vuelve a pendiente y lo que se agrega deja de estarlo', function () {
    $kept = soldItem($this->admin, $this->product, 2);
    $removed = soldItem($this->admin, $this->product, 1);
    $added = soldItem($this->admin, $this->product, 4);
    $order = issuedOrder($this->admin, $this->supplier, [$kept, $removed]);
    $otherSupplier = Supplier::factory()->create();

    $updated = app(UpdatePurchaseOrder::class)->handle(
        $order,
        ['supplier_id' => $otherSupplier->id, 'date' => '2026-11-03'],
        [['sale_item_id' => $kept->id, 'quantity' => 1], ['sale_item_id' => $added->id, 'quantity' => 4]],
        $this->admin,
    );

    expect($updated->supplier_id)->toBe($otherSupplier->id)
        ->and($updated->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($updated->items()->sum('quantity'))->toBe(5)
        ->and($kept->fresh()->pending_quantity)->toBe(1)
        ->and($removed->fresh()->pending_quantity)->toBe(1)
        ->and($added->fresh()->pending_quantity)->toBe(0);
});

it('si la edición falla no cambia nada', function () {
    $item = soldItem($this->admin, $this->product, 2);
    $order = issuedOrder($this->admin, $this->supplier, [$item]);

    expect(fn () => app(UpdatePurchaseOrder::class)->handle(
        $order,
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-03'],
        [['sale_item_id' => $item->id, 'quantity' => 5]],
        $this->admin,
    ))->toThrow(ValidationException::class);

    expect($order->items()->sum('quantity'))->toBe(2)
        ->and($item->fresh()->pending_quantity)->toBe(0);
});

it('emite una orden en borrador', function () {
    $order = app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-02'],
        [['sale_item_id' => soldItem($this->admin, $this->product)->id, 'quantity' => 1]],
        $this->admin,
    );

    $issued = app(IssuePurchaseOrder::class)->handle($order, $this->admin);

    expect($issued->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($issued->issued_at)->not->toBeNull();

    expect(fn () => app(IssuePurchaseOrder::class)->handle($issued, $this->admin))->toThrow(ValidationException::class);
});

it('al anular la orden todo vuelve a pendiente y queda el historial', function () {
    $item = soldItem($this->admin, $this->product, 3);
    $order = issuedOrder($this->admin, $this->supplier, [$item]);

    $cancelled = app(CancelPurchaseOrder::class)->handle($order, 'El proveedor no tiene', $this->admin);

    expect($cancelled->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($cancelled->cancelled_by)->toBe($this->admin->id)
        ->and($cancelled->cancellation_reason)->toBe('El proveedor no tiene')
        ->and($cancelled->items)->toHaveCount(1)
        ->and($item->fresh()->pending_quantity)->toBe(3);

    expect(fn () => app(CancelPurchaseOrder::class)->handle($cancelled, 'Otra vez', $this->admin))
        ->toThrow(ValidationException::class, 'ya está anulada');
});

it('al anular la orden no vuelve a pendiente lo de una venta anulada', function () {
    $item = soldItem($this->admin, $this->product, 2);
    $order = issuedOrder($this->admin, $this->supplier, [$item]);
    app(CancelSale::class)->handle($item->sale, 'Error', $this->admin);

    app(CancelPurchaseOrder::class)->handle($order, 'Ya no hace falta', $this->admin);

    expect($item->fresh()->pending_quantity)->toBe(0);
});

it('una orden con una venta anulada no se puede guardar sin quitar ese ítem', function () {
    $item = soldItem($this->admin, $this->product);
    $order = issuedOrder($this->admin, $this->supplier, [$item]);
    app(CancelSale::class)->handle($item->sale, 'Error', $this->admin);

    expect(fn () => app(UpdatePurchaseOrder::class)->handle(
        $order,
        ['supplier_id' => $this->supplier->id, 'date' => '2026-11-03'],
        [['sale_item_id' => $item->id, 'quantity' => 1]],
        $this->admin,
    ))->toThrow(ValidationException::class, 'está anulada');
});
