<?php

use App\Actions\CreateSale;
use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function saleData(Customer $customer, User $seller, FinancingPlan $plan, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $customer->id,
        'seller_id' => $seller->id,
        'financing_plan_id' => $plan->id,
        'date' => '2026-11-07',
        'first_due_date' => '2026-12-07',
        'down_payment' => 0,
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->customer = Customer::factory()->create();
    $this->plan = FinancingPlan::factory()->create(['installments_count' => 3, 'surcharge_percent' => 20]);
    $this->product = Product::factory()->create(['stock' => 10, 'last_cost' => 600, 'margin_percent' => 0]);
});

it('genera la venta, las cuotas y el débito en cuenta corriente', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan, ['down_payment' => 200]),
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        $this->admin,
    );

    expect($sale->subtotal)->toBe('1000.00')
        ->and($sale->financed_amount)->toBe('960.00')
        ->and($sale->total)->toBe('1160.00')
        ->and($sale->total_cost)->toBe('600.00')
        ->and($sale->status)->toBe(SaleStatus::Active)
        ->and($sale->installments)->toHaveCount(3)
        ->and($sale->installments->pluck('amount')->all())->toBe(['320.00', '320.00', '320.00'])
        ->and($sale->installments->first()->status)->toBe(InstallmentStatus::Pending)
        ->and($sale->installments->first()->customer_id)->toBe($this->customer->id)
        ->and($sale->installments->first()->due_date->toDateString())->toBe('2026-12-07');

    $debit = AccountMovement::where('type', AccountMovementType::Sale)->firstOrFail();
    expect($debit->debit)->toBe('1160.00')->and($debit->reference->is($sale))->toBeTrue();
});

it('copia el plan a la venta y no se ve afectada por cambios posteriores', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        $this->admin,
    );

    $this->plan->update(['installments_count' => 12, 'surcharge_percent' => 99]);

    expect($sale->fresh())
        ->installments_count->toBe(3)
        ->surcharge_percent->toBe('20.00');
});

it('registra el costo del producto al momento de la venta', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 1000]],
        $this->admin,
    );

    $this->product->update(['last_cost' => 5000]);

    $item = $sale->items->first();
    expect($item->unit_cost)->toBe('600.00')
        ->and($item->subtotal)->toBe('2000.00')
        ->and($sale->fresh()->total_cost)->toBe('1200.00');
});

it('descuenta el stock y registra el movimiento de venta', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [['product_id' => $this->product->id, 'quantity' => 4, 'unit_price' => 1000]],
        $this->admin,
    );

    $movement = $sale->stockMovements()->firstOrFail();

    expect($this->product->fresh()->stock)->toBe(6)
        ->and($movement->type)->toBe(StockMovementType::Sale)
        ->and($movement->quantity)->toBe(-4);
});

it('bloquea la venta si no hay stock suficiente y no deja nada guardado', function () {
    expect(fn () => app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [['product_id' => $this->product->id, 'quantity' => 11, 'unit_price' => 1000]],
        $this->admin,
    ))->toThrow(ValidationException::class);

    expect(Sale::count())->toBe(0)
        ->and(Installment::count())->toBe(0)
        ->and(AccountMovement::count())->toBe(0)
        ->and($this->product->fresh()->stock)->toBe(10);
});

it('suma las cantidades de un mismo producto repetido al validar el stock', function () {
    expect(fn () => app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [
            ['product_id' => $this->product->id, 'quantity' => 6, 'unit_price' => 1000],
            ['product_id' => $this->product->id, 'quantity' => 6, 'unit_price' => 1000],
        ],
        $this->admin,
    ))->toThrow(ValidationException::class);

    expect($this->product->fresh()->stock)->toBe(10);
});

it('registra el anticipo como cobro con su crédito en cuenta corriente', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan, [
            'down_payment' => 300,
            'down_payment_method' => PaymentMethod::Transfer->value,
        ]),
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        $this->admin,
    );

    $payment = Payment::firstOrFail();
    expect($payment->amount)->toBe('300.00')
        ->and($payment->method)->toBe(PaymentMethod::Transfer)
        ->and($payment->collected_by)->toBe($this->admin->id)
        ->and($payment->customer_id)->toBe($this->customer->id);

    $movements = AccountMovement::where('customer_id', $this->customer->id)->orderBy('id')->get();
    expect($movements)->toHaveCount(2)
        ->and($movements[0]->balance)->toBe('1140.00')   // 300 + 700 * 1.2 = 1140 debe
        ->and($movements[1]->credit)->toBe('300.00')
        ->and($movements[1]->balance)->toBe('840.00');
});

it('no registra cobro si no hay anticipo', function () {
    app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        $this->admin,
    );

    expect(Payment::count())->toBe(0)->and(AccountMovement::count())->toBe(1);
});

it('deja la venta pagada y sin cuotas si el anticipo cubre todo', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan, ['down_payment' => 1000]),
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        $this->admin,
    );

    expect($sale->status)->toBe(SaleStatus::Paid)
        ->and($sale->installments)->toHaveCount(0)
        ->and(AccountMovement::where('customer_id', $this->customer->id)->latest('id')->first()->balance)->toBe('0.00');
});

it('rechaza un anticipo mayor al precio de contado', function () {
    expect(fn () => app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan, ['down_payment' => 1500]),
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        $this->admin,
    ))->toThrow(ValidationException::class);

    expect(Sale::count())->toBe(0)->and($this->product->fresh()->stock)->toBe(10);
});

it('exige al menos un producto', function () {
    expect(fn () => app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [],
        $this->admin,
    ))->toThrow(ValidationException::class);
});

it('acumula el saldo de la cuenta corriente entre ventas', function () {
    $items = [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]];

    app(CreateSale::class)->handle(saleData($this->customer, $this->seller, $this->plan), $items, $this->admin);
    app(CreateSale::class)->handle(saleData($this->customer, $this->seller, $this->plan), $items, $this->admin);

    expect(AccountMovement::where('customer_id', $this->customer->id)->latest('id')->first()->balance)->toBe('2400.00');
});
