<?php

use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
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

/**
 * Crea una venta real de 1000 contado, 3 cuotas con 20% de recargo: 3 cuotas de 400.
 */
function makeSale(Customer $customer, User $user, string $firstDue = '2026-12-01'): Sale
{
    $plan = FinancingPlan::factory()->create(['installments_count' => 3, 'surcharge_percent' => 20]);
    $product = Product::factory()->create(['stock' => 10, 'last_cost' => 600]);

    return app(CreateSale::class)->handle([
        'customer_id' => $customer->id,
        'seller_id' => $user->id,
        'financing_plan_id' => $plan->id,
        'date' => '2026-11-01',
        'first_due_date' => $firstDue,
        'down_payment' => 0,
    ], [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]], $user);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create();
});

it('imputa el cobro a la cuota más vieja y la marca pagada', function () {
    $sale = makeSale($this->customer, $this->admin);

    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    $installments = $sale->installments()->get();

    expect($installments[0]->status)->toBe(InstallmentStatus::Paid)
        ->and($installments[0]->paid_amount)->toBe('400.00')
        ->and($installments[1]->status)->toBe(InstallmentStatus::Pending)
        ->and($payment->installments)->toHaveCount(1)
        ->and((float) $payment->installments->first()->pivot->amount)->toBe(400.0);
});

it('admite un pago parcial y deja la cuota en estado parcial', function () {
    $sale = makeSale($this->customer, $this->admin);

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 150.50], $this->admin);

    $first = $sale->installments()->first();
    expect($first->status)->toBe(InstallmentStatus::Partial)
        ->and($first->paid_amount)->toBe('150.50')
        ->and($first->balance())->toBe('249.50');
});

it('completa una cuota parcial con el cobro siguiente y sigue con la próxima', function () {
    $sale = makeSale($this->customer, $this->admin);

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 150], $this->admin);
    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 300], $this->admin);

    $installments = $sale->installments()->get();

    expect($installments[0]->status)->toBe(InstallmentStatus::Paid)
        ->and($installments[1]->paid_amount)->toBe('50.00')
        ->and($installments[1]->status)->toBe(InstallmentStatus::Partial)
        ->and($installments[2]->status)->toBe(InstallmentStatus::Pending);
});

it('admite un pago adelantado que cubre varias cuotas futuras', function () {
    $sale = makeSale($this->customer, $this->admin, firstDue: now()->addMonths(2)->toDateString());

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 800], $this->admin);

    expect($sale->installments()->pluck('status')->all())
        ->toBe([InstallmentStatus::Paid, InstallmentStatus::Paid, InstallmentStatus::Pending]);
});

it('imputa primero a la cuota más vieja entre varias ventas', function () {
    $newer = makeSale($this->customer, $this->admin, firstDue: '2027-03-01');
    $older = makeSale($this->customer, $this->admin, firstDue: '2026-12-01');

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    expect($older->installments()->first()->status)->toBe(InstallmentStatus::Paid)
        ->and($newer->installments()->first()->status)->toBe(InstallmentStatus::Pending);
});

it('rechaza cobrar más que la deuda total y no cambia nada', function () {
    $sale = makeSale($this->customer, $this->admin);
    $balanceBefore = AccountMovement::where('customer_id', $this->customer->id)->latest('id')->value('balance');

    expect(fn () => app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 1200.01], $this->admin))
        ->toThrow(ValidationException::class);

    expect(Payment::count())->toBe(0)
        ->and($sale->installments()->where('paid_amount', '>', 0)->count())->toBe(0)
        ->and(AccountMovement::where('customer_id', $this->customer->id)->latest('id')->value('balance'))->toBe($balanceBefore);
});

it('rechaza un cobro en cero', function () {
    makeSale($this->customer, $this->admin);

    expect(fn () => app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 0], $this->admin))
        ->toThrow(ValidationException::class);
});

it('rechaza cobrar a un cliente sin deuda', function () {
    expect(fn () => app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 10], $this->admin))
        ->toThrow(ValidationException::class);
});

it('deja la venta pagada cuando se saldan todas las cuotas', function () {
    $sale = makeSale($this->customer, $this->admin);

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 1200], $this->admin);

    expect($sale->fresh()->status)->toBe(SaleStatus::Paid)
        ->and(Installment::where('customer_id', $this->customer->id)->unpaid()->count())->toBe(0)
        ->and($this->customer->debt())->toBe('0.00');
});

it('mantiene la venta en curso mientras queden cuotas', function () {
    $sale = makeSale($this->customer, $this->admin);

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 800], $this->admin);

    expect($sale->fresh()->status)->toBe(SaleStatus::Active);
});

it('registra el crédito en la cuenta corriente y baja el saldo', function () {
    makeSale($this->customer, $this->admin);

    $payment = app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 500,
        'method' => PaymentMethod::Transfer->value,
    ], $this->admin);

    $movement = AccountMovement::where('type', AccountMovementType::Payment)->firstOrFail();

    expect($movement->credit)->toBe('500.00')
        ->and($movement->balance)->toBe('700.00')   // 1200 - 500
        ->and($movement->reference->is($payment))->toBeTrue()
        ->and($payment->method)->toBe(PaymentMethod::Transfer)
        ->and($payment->collected_by)->toBe($this->admin->id);
});

it('el saldo de la cuenta corriente coincide con la deuda de las cuotas', function () {
    makeSale($this->customer, $this->admin);
    makeSale($this->customer, $this->admin, firstDue: '2027-01-15');

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 777.77], $this->admin);

    $balance = AccountMovement::where('customer_id', $this->customer->id)->latest('id')->value('balance');

    expect($balance)->toBe($this->customer->debt())->toBe('1622.23');
});

it('no toca las cuotas de otro cliente', function () {
    makeSale($this->customer, $this->admin);
    $other = Customer::factory()->create();
    $otherSale = makeSale($other, $this->admin);

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 1200], $this->admin);

    expect($otherSale->installments()->where('paid_amount', '>', 0)->count())->toBe(0);
});

it('imputa solo a las cuotas elegidas, aunque haya otras más viejas', function () {
    $sale = makeSale($this->customer, $this->admin);
    $installments = $sale->installments()->get();

    $payment = app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 400,
        'installment_ids' => [$installments[2]->id],
    ], $this->admin);

    $fresh = $sale->installments()->get();

    expect($fresh[0]->status)->toBe(InstallmentStatus::Pending)
        ->and($fresh[1]->status)->toBe(InstallmentStatus::Pending)
        ->and($fresh[2]->status)->toBe(InstallmentStatus::Paid)
        ->and($payment->installments)->toHaveCount(1)
        ->and($payment->installments->first()->id)->toBe($installments[2]->id);
});

it('reparte un cobro entre las cuotas elegidas, de la más vieja a la más nueva', function () {
    $sale = makeSale($this->customer, $this->admin);
    $installments = $sale->installments()->get();

    app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 500,
        // en desorden a propósito: el orden lo define el vencimiento, no el orden de la lista
        'installment_ids' => [$installments[2]->id, $installments[1]->id],
    ], $this->admin);

    $fresh = $sale->installments()->get();

    expect($fresh[0]->paid_amount)->toBe('0.00')
        ->and($fresh[1]->status)->toBe(InstallmentStatus::Paid)
        ->and($fresh[2]->paid_amount)->toBe('100.00')
        ->and($fresh[2]->status)->toBe(InstallmentStatus::Partial);
});

it('admite un pago parcial dentro de una sola cuota elegida', function () {
    $sale = makeSale($this->customer, $this->admin);
    $installments = $sale->installments()->get();

    app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 150,
        'installment_ids' => [$installments[1]->id],
    ], $this->admin);

    expect($sale->installments()->get()[1]->paid_amount)->toBe('150.00');
});

it('rechaza un monto mayor al saldo de las cuotas elegidas aunque el cliente deba más', function () {
    $sale = makeSale($this->customer, $this->admin);

    expect(fn () => app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 400.01,
        'installment_ids' => [$sale->installments()->first()->id],
    ], $this->admin))->toThrow(ValidationException::class, 'saldo de las cuotas elegidas');

    expect(Payment::count())->toBe(0)->and($this->customer->debt())->toBe('1200.00');
});

it('rechaza cuotas de otro cliente', function () {
    makeSale($this->customer, $this->admin);
    $other = Customer::factory()->create();
    $foreign = makeSale($other, $this->admin)->installments()->first();

    expect(fn () => app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 100,
        'installment_ids' => [$foreign->id],
    ], $this->admin))->toThrow(ValidationException::class, 'no es de este cliente');

    expect($foreign->fresh()->paid_amount)->toBe('0.00');
});

it('rechaza una cuota que ya está paga', function () {
    $sale = makeSale($this->customer, $this->admin);
    $first = $sale->installments()->first();
    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400, 'installment_ids' => [$first->id]], $this->admin);

    expect(fn () => app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 100,
        'installment_ids' => [$first->id],
    ], $this->admin))->toThrow(ValidationException::class, 'ya no está pendiente');
});

it('ignora ids repetidos y acepta ids como texto', function () {
    $sale = makeSale($this->customer, $this->admin);
    $first = $sale->installments()->first();

    app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 400,
        'installment_ids' => [(string) $first->id, $first->id],
    ], $this->admin);

    expect($first->fresh()->status)->toBe(InstallmentStatus::Paid);
});

it('con la selección, la cuenta corriente baja lo cobrado y coincide con la deuda', function () {
    $sale = makeSale($this->customer, $this->admin);

    app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 400,
        'installment_ids' => [$sale->installments()->get()[2]->id],
    ], $this->admin);

    $balance = AccountMovement::where('customer_id', $this->customer->id)->latest('id')->value('balance');

    expect($balance)->toBe('800.00')->toBe($this->customer->debt());
});

it('sin cuotas elegidas sigue imputando a toda la deuda, la más vieja primero', function () {
    $sale = makeSale($this->customer, $this->admin);

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400, 'installment_ids' => []], $this->admin);

    expect($sale->installments()->first()->status)->toBe(InstallmentStatus::Paid);
});
