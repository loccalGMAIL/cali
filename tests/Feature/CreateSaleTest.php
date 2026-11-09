<?php

use App\Actions\CreateSale;
use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
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
    $this->plan = FinancingPlan::factory()->create(['installments_count' => 3]);
    $this->product = Product::factory()->create(['last_cost' => 600]);
    // Costo 600 + margen 600 = 1200 por unidad.
    $this->item = fn (int $quantity = 1): array => ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_cost' => 600, 'unit_margin' => 600];
});

it('genera la venta, las cuotas y el débito en cuenta corriente: (costo + margen − anticipo) ÷ cuotas', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan, ['down_payment' => 300]),
        [($this->item)()],
        $this->admin,
    );

    expect($sale->total)->toBe('1200.00')
        ->and($sale->total_cost)->toBe('600.00')
        ->and($sale->down_payment)->toBe('300.00')
        ->and($sale->financed_amount)->toBe('900.00')
        ->and($sale->status)->toBe(SaleStatus::Active)
        ->and($sale->installments)->toHaveCount(3)
        ->and($sale->installments->pluck('amount')->all())->toBe(['300.00', '300.00', '300.00'])
        ->and($sale->installments->first()->status)->toBe(InstallmentStatus::Pending)
        ->and($sale->installments->first()->customer_id)->toBe($this->customer->id)
        ->and($sale->installments->first()->due_date->toDateString())->toBe('2026-12-07');

    $debit = AccountMovement::where('type', AccountMovementType::Sale)->firstOrFail();
    expect($debit->debit)->toBe('1200.00')->and($debit->reference->is($sale))->toBeTrue();
});

it('guarda costo, margen y precio de cada ítem', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => '650.50', 'unit_margin' => '349.50']],
        $this->admin,
    );

    $item = $sale->items->first();

    expect($item->unit_cost)->toBe('650.50')
        ->and($item->unit_margin)->toBe('349.50')
        ->and($item->unit_price)->toBe('1000.00')
        ->and($item->subtotal)->toBe('2000.00')
        ->and($sale->total)->toBe('2000.00')
        ->and($sale->total_cost)->toBe('1301.00');
});

it('el costo cargado en la venta no cambia si después cambia el último costo del producto', function () {
    $sale = app(CreateSale::class)->handle(saleData($this->customer, $this->seller, $this->plan), [($this->item)()], $this->admin);

    $this->product->update(['last_cost' => 5000]);

    expect($sale->items->first()->fresh()->unit_cost)->toBe('600.00')
        ->and($sale->fresh()->total_cost)->toBe('600.00');
});

it('copia la cantidad de cuotas del plan y no se ve afectada por cambios posteriores', function () {
    $sale = app(CreateSale::class)->handle(saleData($this->customer, $this->seller, $this->plan), [($this->item)()], $this->admin);

    $this->plan->update(['installments_count' => 12]);

    expect($sale->fresh()->installments_count)->toBe(3)
        ->and($sale->installments()->count())->toBe(3);
});

it('lo vendido queda pendiente de compra: no hay stock que controlar', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [($this->item)(25), ($this->item)(3)],
        $this->admin,
    );

    expect($sale->items->pluck('pending_quantity')->all())->toBe([25, 3]);
});

it('rechaza costo o margen negativos y no deja nada guardado', function (array $overrides) {
    expect(fn () => app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [array_merge(($this->item)(), $overrides)],
        $this->admin,
    ))->toThrow(ValidationException::class);

    expect(Sale::count())->toBe(0)
        ->and(Installment::count())->toBe(0)
        ->and(AccountMovement::count())->toBe(0);
})->with([
    'costo negativo' => [['unit_cost' => -1]],
    'margen negativo' => [['unit_margin' => -1]],
    'cantidad cero' => [['quantity' => 0]],
]);

it('rechaza un producto inactivo', function () {
    $this->product->update(['active' => false]);

    expect(fn () => app(CreateSale::class)->handle(saleData($this->customer, $this->seller, $this->plan), [($this->item)()], $this->admin))
        ->toThrow(ValidationException::class);

    expect(Sale::count())->toBe(0);
});

it('registra el anticipo como cobro con su crédito en cuenta corriente', function () {
    app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan, [
            'down_payment' => 300,
            'down_payment_method' => PaymentMethod::Transfer->value,
        ]),
        [($this->item)()],
        $this->admin,
    );

    $payment = Payment::firstOrFail();
    expect($payment->amount)->toBe('300.00')
        ->and($payment->method)->toBe(PaymentMethod::Transfer)
        ->and($payment->collected_by)->toBe($this->admin->id)
        ->and($payment->customer_id)->toBe($this->customer->id);

    $movements = AccountMovement::where('customer_id', $this->customer->id)->orderBy('id')->get();
    expect($movements)->toHaveCount(2)
        ->and($movements[0]->balance)->toBe('1200.00')
        ->and($movements[1]->credit)->toBe('300.00')
        ->and($movements[1]->balance)->toBe('900.00');
});

it('no registra cobro si no hay anticipo', function () {
    app(CreateSale::class)->handle(saleData($this->customer, $this->seller, $this->plan), [($this->item)()], $this->admin);

    expect(Payment::count())->toBe(0)->and(AccountMovement::count())->toBe(1);
});

it('deja la venta pagada y sin cuotas si el anticipo cubre todo', function () {
    $sale = app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan, ['down_payment' => 1200]),
        [($this->item)()],
        $this->admin,
    );

    expect($sale->status)->toBe(SaleStatus::Paid)
        ->and($sale->installments)->toHaveCount(0)
        ->and(AccountMovement::where('customer_id', $this->customer->id)->latest('id')->first()->balance)->toBe('0.00');
});

it('rechaza un anticipo mayor al total', function () {
    expect(fn () => app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan, ['down_payment' => 1500]),
        [($this->item)()],
        $this->admin,
    ))->toThrow(ValidationException::class);

    expect(Sale::count())->toBe(0);
});

it('exige al menos un producto', function () {
    expect(fn () => app(CreateSale::class)->handle(
        saleData($this->customer, $this->seller, $this->plan),
        [],
        $this->admin,
    ))->toThrow(ValidationException::class);
});

it('acumula el saldo de la cuenta corriente entre ventas', function () {
    app(CreateSale::class)->handle(saleData($this->customer, $this->seller, $this->plan), [($this->item)()], $this->admin);
    app(CreateSale::class)->handle(saleData($this->customer, $this->seller, $this->plan), [($this->item)()], $this->admin);

    expect(AccountMovement::where('customer_id', $this->customer->id)->latest('id')->first()->balance)->toBe('2400.00');
});
