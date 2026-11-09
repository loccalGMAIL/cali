<?php

use App\Actions\CancelPayment;
use App\Actions\CancelSale;
use App\Actions\CreatePurchaseOrder;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\SaleStatus;
use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create();
    $this->plan = FinancingPlan::factory()->create(['installments_count' => 3]);
    $this->product = Product::factory()->create(['last_cost' => 600]);

    $this->sell = fn (int $down = 0, int $quantity = 1): Sale => app(CreateSale::class)->handle([
        'customer_id' => $this->customer->id,
        'seller_id' => $this->admin->id,
        'financing_plan_id' => $this->plan->id,
        'date' => '2026-11-01',
        'first_due_date' => '2026-12-01',
        'down_payment' => $down,
    ], [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_cost' => 600, 'unit_margin' => 600]], $this->admin);
});

it('quita de pendientes de compra lo que todavía no se pidió', function () {
    $sale = ($this->sell)(quantity: 3);
    expect($sale->items->first()->pending_quantity)->toBe(3);

    app(CancelSale::class)->handle($sale, 'El cliente se arrepintió', $this->admin);

    expect($sale->items->first()->fresh()->pending_quantity)->toBe(0);
});

it('lo que ya estaba en una orden de compra queda en la orden', function () {
    $sale = ($this->sell)(quantity: 3);
    $item = $sale->items->first();

    $order = app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => Supplier::factory()->create()->id, 'date' => '2026-11-02'],
        [['sale_item_id' => $item->id, 'quantity' => 2]],
        $this->admin,
    );

    app(CancelSale::class)->handle($sale, 'Error', $this->admin);

    expect($item->fresh()->pending_quantity)->toBe(0)
        ->and($order->items()->sum('quantity'))->toBe(2);
});

it('anula la venta con quién, cuándo y por qué', function () {
    $sale = ($this->sell)();
    $canceller = User::factory()->admin()->create();

    $cancelled = app(CancelSale::class)->handle($sale, 'Error de carga', $canceller);

    expect($cancelled->status)->toBe(SaleStatus::Cancelled)
        ->and($cancelled->isCancelled())->toBeTrue()
        ->and($cancelled->cancelled_by)->toBe($canceller->id)
        ->and($cancelled->cancellation_reason)->toBe('Error de carga')
        ->and(Sale::notCancelled()->count())->toBe(0)
        ->and(Sale::count())->toBe(1);
});

it('anula las cuotas y el cliente deja de deberlas', function () {
    $sale = ($this->sell)();

    app(CancelSale::class)->handle($sale, 'Error', $this->admin);

    expect($sale->installments()->pluck('status')->unique()->all())->toBe([InstallmentStatus::Cancelled])
        ->and($this->customer->debt())->toBe('0.00')
        ->and(Installment::unpaid()->count())->toBe(0)
        ->and(Installment::overdue()->count())->toBe(0);
});

it('revierte el débito de la venta en la cuenta corriente', function () {
    $sale = ($this->sell)();

    app(CancelSale::class)->handle($sale, 'Error', $this->admin);

    $movement = AccountMovement::where('type', AccountMovementType::Adjustment)->firstOrFail();
    $balance = AccountMovement::where('customer_id', $this->customer->id)->latest('id')->value('balance');

    expect($movement->credit)->toBe('1200.00')
        ->and($movement->reference->is($sale))->toBeTrue()
        ->and($balance)->toBe('0.00');
});

it('anula el anticipo junto con la venta y deja la cuenta en cero', function () {
    $sale = ($this->sell)(down: 300);
    $downPayment = Payment::firstOrFail();

    app(CancelSale::class)->handle($sale, 'Error', $this->admin);

    $downPayment->refresh();
    $balance = AccountMovement::where('customer_id', $this->customer->id)->latest('id')->value('balance');

    expect($downPayment->isCancelled())->toBeTrue()
        ->and($downPayment->cancellation_reason)->toContain("venta #{$sale->id}")
        ->and(Payment::notCancelled()->count())->toBe(0)
        ->and($balance)->toBe('0.00');
});

it('rechaza anular una venta con cobros imputados y avisa cuáles anular', function () {
    $sale = ($this->sell)();
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    expect(fn () => app(CancelSale::class)->handle($sale, 'Error', $this->admin))
        ->toThrow(ValidationException::class, "#{$payment->id}");

    expect($sale->fresh()->status)->toBe(SaleStatus::Active)
        ->and($sale->items->first()->fresh()->pending_quantity)->toBe(1)
        ->and($this->customer->debt())->toBe('800.00');
});

it('permite anular la venta después de anular sus cobros', function () {
    $sale = ($this->sell)(down: 200);
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    app(CancelPayment::class)->handle($payment, 'Error', $this->admin);
    app(CancelSale::class)->handle($sale, 'Error', $this->admin);

    $balance = AccountMovement::where('customer_id', $this->customer->id)->latest('id')->value('balance');

    expect($sale->fresh()->isCancelled())->toBeTrue()
        ->and($this->customer->debt())->toBe('0.00')
        ->and($balance)->toBe('0.00');
});

it('rechaza anular dos veces la misma venta', function () {
    $sale = ($this->sell)();
    app(CancelSale::class)->handle($sale, 'Error', $this->admin);

    expect(fn () => app(CancelSale::class)->handle($sale, 'Otra vez', $this->admin))
        ->toThrow(ValidationException::class);

    expect(AccountMovement::where('type', AccountMovementType::Adjustment)->count())->toBe(1);
});

it('no afecta las otras ventas del cliente', function () {
    $first = ($this->sell)();
    $second = ($this->sell)();

    app(CancelSale::class)->handle($first, 'Error', $this->admin);

    $balance = AccountMovement::where('customer_id', $this->customer->id)->latest('id')->value('balance');

    expect($second->fresh()->status)->toBe(SaleStatus::Active)
        ->and($this->customer->debt())->toBe('1200.00')
        ->and($balance)->toBe('1200.00')
        ->and($second->items->first()->fresh()->pending_quantity)->toBe(1);
});

it('el anticipo ya no se puede anular por separado y el aviso indica la venta', function () {
    $sale = ($this->sell)(down: 300);

    expect(fn () => app(CancelPayment::class)->handle(Payment::firstOrFail(), 'Error', $this->admin))
        ->toThrow(ValidationException::class, "venta #{$sale->id}");
});
