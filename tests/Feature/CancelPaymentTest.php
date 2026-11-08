<?php

use App\Actions\CancelPayment;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\SaleStatus;
use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function saleFor(Customer $customer, User $user, int $down = 0): Sale
{
    $plan = FinancingPlan::factory()->create(['installments_count' => 3, 'surcharge_percent' => 20]);
    $product = Product::factory()->create(['stock' => 10, 'last_cost' => 600]);

    return app(CreateSale::class)->handle([
        'customer_id' => $customer->id,
        'seller_id' => $user->id,
        'financing_plan_id' => $plan->id,
        'date' => '2026-11-01',
        'first_due_date' => '2026-12-01',
        'down_payment' => $down,
    ], [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]], $user);
}

function collect_(Customer $customer, User $user, float $amount): Payment
{
    return app(RegisterPayment::class)->handle(['customer_id' => $customer->id, 'amount' => $amount], $user);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create();
});

it('devuelve a cada cuota lo imputado y vuelve a dejar la deuda original', function () {
    $sale = saleFor($this->customer, $this->admin);
    $payment = collect_($this->customer, $this->admin, 500);

    app(CancelPayment::class)->handle($payment, 'Error de carga', $this->admin);

    $installments = $sale->installments()->get();

    expect($installments->pluck('status')->all())->toBe([InstallmentStatus::Pending, InstallmentStatus::Pending, InstallmentStatus::Pending])
        ->and($installments->pluck('paid_amount')->all())->toBe(['0.00', '0.00', '0.00'])
        ->and($this->customer->debt())->toBe('1200.00');
});

it('deja la cuota parcial si hubo otro cobro posterior sobre la misma cuota', function () {
    $sale = saleFor($this->customer, $this->admin);
    $first = collect_($this->customer, $this->admin, 150);
    collect_($this->customer, $this->admin, 100);

    app(CancelPayment::class)->handle($first, 'Duplicado', $this->admin);

    $installment = $sale->installments()->first();
    expect($installment->paid_amount)->toBe('100.00')
        ->and($installment->status)->toBe(InstallmentStatus::Partial);
});

it('reabre la venta que había quedado pagada', function () {
    $sale = saleFor($this->customer, $this->admin);
    $payment = collect_($this->customer, $this->admin, 1200);

    expect($sale->fresh()->status)->toBe(SaleStatus::Paid);

    app(CancelPayment::class)->handle($payment, 'Cheque rechazado', $this->admin);

    expect($sale->fresh()->status)->toBe(SaleStatus::Active);
});

it('marca el cobro como anulado con quién, cuándo y por qué', function () {
    saleFor($this->customer, $this->admin);
    $payment = collect_($this->customer, $this->admin, 400);
    $canceller = User::factory()->admin()->create();

    $cancelled = app(CancelPayment::class)->handle($payment, 'Monto equivocado', $canceller);

    expect($cancelled->isCancelled())->toBeTrue()
        ->and($cancelled->cancelled_by)->toBe($canceller->id)
        ->and($cancelled->cancellation_reason)->toBe('Monto equivocado')
        ->and(Payment::notCancelled()->count())->toBe(0)
        ->and(Payment::count())->toBe(1);
});

it('registra un débito en la cuenta corriente y el saldo coincide con la deuda', function () {
    saleFor($this->customer, $this->admin);
    $payment = collect_($this->customer, $this->admin, 500);

    app(CancelPayment::class)->handle($payment, 'Error', $this->admin);

    $movement = AccountMovement::where('type', AccountMovementType::Adjustment)->firstOrFail();
    $balance = AccountMovement::where('customer_id', $this->customer->id)->latest('id')->value('balance');

    expect($movement->debit)->toBe('500.00')
        ->and($movement->reference->is($payment))->toBeTrue()
        ->and($balance)->toBe('1200.00')->toBe($this->customer->debt());
});

it('permite volver a cobrar después de anular', function () {
    saleFor($this->customer, $this->admin);
    $payment = collect_($this->customer, $this->admin, 1200);
    app(CancelPayment::class)->handle($payment, 'Error', $this->admin);

    collect_($this->customer, $this->admin, 400);

    expect($this->customer->debt())->toBe('800.00');
});

it('rechaza anular dos veces el mismo cobro', function () {
    saleFor($this->customer, $this->admin);
    $payment = collect_($this->customer, $this->admin, 400);
    app(CancelPayment::class)->handle($payment, 'Error', $this->admin);

    expect(fn () => app(CancelPayment::class)->handle($payment, 'Otra vez', $this->admin))
        ->toThrow(ValidationException::class);

    expect(AccountMovement::where('type', AccountMovementType::Adjustment)->count())->toBe(1)
        ->and($this->customer->debt())->toBe('1200.00');
});

it('rechaza anular un anticipo', function () {
    saleFor($this->customer, $this->admin, down: 300);
    $downPayment = Payment::firstOrFail();

    expect(fn () => app(CancelPayment::class)->handle($downPayment, 'Error', $this->admin))
        ->toThrow(ValidationException::class);

    expect($downPayment->fresh()->isCancelled())->toBeFalse();
});
