<?php

use App\Actions\CancelPayment;
use App\Actions\CancelPurchaseOrder;
use App\Actions\CreatePurchaseOrder;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Actions\UpdateSale;
use App\Enums\ActivityEvent;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\AccountMovement;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->canEditSales()->create();
    $this->customer = Customer::factory()->create();
    $this->plan = FinancingPlan::factory()->create(['installments_count' => 3]);
    $this->product = Product::factory()->create(['last_cost' => 600]);

    // 600 + 600 = 1200 en 3 cuotas.
    $this->sell = fn (int $down = 0, int $quantity = 1): Sale => app(CreateSale::class)->handle([
        'customer_id' => $this->customer->id,
        'seller_id' => $this->admin->id,
        'financing_plan_id' => $this->plan->id,
        'date' => '2026-11-01',
        'first_due_date' => '2026-12-01',
        'down_payment' => $down,
    ], [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_cost' => 600, 'unit_margin' => 600]], $this->admin);

    $this->update = fn (Sale $sale, array $data, ?array $items = null): Sale => app(UpdateSale::class)->handle($sale, $data, $items, $this->admin);

    $this->balance = fn (Customer $customer): string => (string) AccountMovement::where('customer_id', $customer->id)->latest('id')->value('balance');
});

it('cambia vendedor y notas sin tocar cuotas ni cuenta corriente', function () {
    $sale = ($this->sell)();
    $seller = User::factory()->seller()->create();
    $installmentIds = $sale->installments->modelKeys();
    $movements = AccountMovement::count();

    $updated = ($this->update)($sale, ['seller_id' => $seller->id, 'notes' => 'Entrega en la semana']);

    expect($updated->seller_id)->toBe($seller->id)
        ->and($updated->notes)->toBe('Entrega en la semana')
        ->and($updated->installments->modelKeys())->toBe($installmentIds)
        ->and(AccountMovement::count())->toBe($movements);
});

it('cambiar la fecha mueve la del anticipo y la de los movimientos, sin cambiar saldos', function () {
    $sale = ($this->sell)(down: 300);

    ($this->update)($sale, ['date' => '2026-11-03']);

    $downPayment = Payment::firstOrFail();

    expect($sale->fresh()->date->toDateString())->toBe('2026-11-03')
        ->and($downPayment->date->toDateString())->toBe('2026-11-03')
        ->and(AccountMovement::pluck('date')->map->toDateString()->unique()->all())->toBe(['2026-11-03'])
        ->and(($this->balance)($this->customer))->toBe('900.00');
});

it('cambia los productos, recalcula cuotas y ajusta la cuenta corriente conservando el número', function () {
    $sale = ($this->sell)();
    $other = Product::factory()->create();

    $updated = ($this->update)($sale, [], [
        ['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 600],
        ['product_id' => $other->id, 'quantity' => 2, 'unit_cost' => 100, 'unit_margin' => 50],
    ]);

    // 1200 + 2 × 150 = 1500 en 3 cuotas de 500.
    expect($updated->getKey())->toBe($sale->getKey())
        ->and($updated->total)->toBe('1500.00')
        ->and($updated->items)->toHaveCount(2)
        ->and($updated->items->sum('pending_quantity'))->toBe(3)
        ->and($updated->installments->pluck('amount')->all())->toBe(['500.00', '500.00', '500.00'])
        ->and(($this->balance)($this->customer))->toBe('1500.00')
        ->and($this->customer->debt())->toBe('1500.00');
});

it('cambia el plan y el primer vencimiento', function () {
    $sale = ($this->sell)();
    $sixPayments = FinancingPlan::factory()->create(['installments_count' => 6]);

    $updated = ($this->update)($sale, ['financing_plan_id' => $sixPayments->id, 'first_due_date' => '2027-01-15']);

    expect($updated->installments_count)->toBe(6)
        ->and($updated->installments)->toHaveCount(6)
        ->and($updated->installments->first()->amount)->toBe('200.00')
        ->and($updated->installments->first()->due_date->toDateString())->toBe('2027-01-30')
        ->and($updated->first_due_date->toDateString())->toBe('2027-01-30')
        ->and(AccountMovement::count())->toBe(1);
});

it('al cambiar el anticipo anula el anterior y registra el nuevo cobro', function () {
    $sale = ($this->sell)(down: 300);
    $previous = Payment::firstOrFail();

    $updated = ($this->update)($sale, ['down_payment' => 600, 'down_payment_method' => PaymentMethod::Transfer->value]);

    $current = $updated->activeDownPayment();

    expect($previous->fresh()->isCancelled())->toBeTrue()
        ->and($current->amount)->toBe('600.00')
        ->and($current->method)->toBe(PaymentMethod::Transfer)
        ->and($updated->installments->pluck('amount')->all())->toBe(['200.00', '200.00', '200.00'])
        ->and(($this->balance)($this->customer))->toBe('600.00');
});

it('un anticipo por el total deja la venta pagada y sin cuotas', function () {
    $sale = ($this->sell)();

    $updated = ($this->update)($sale, ['down_payment' => 1200]);

    expect($updated->status)->toBe(SaleStatus::Paid)
        ->and($updated->installments)->toHaveCount(0)
        ->and(($this->balance)($this->customer))->toBe('0.00');
});

it('al cambiar el cliente pasa la deuda, las cuotas y el anticipo al nuevo', function () {
    $sale = ($this->sell)(down: 300);
    $newCustomer = Customer::factory()->create();

    $updated = ($this->update)($sale, ['customer_id' => $newCustomer->id]);

    expect($updated->customer_id)->toBe($newCustomer->id)
        ->and($updated->installments->pluck('customer_id')->unique()->all())->toBe([$newCustomer->id])
        ->and($updated->activeDownPayment()->customer_id)->toBe($newCustomer->id)
        ->and(($this->balance)($this->customer))->toBe('0.00')
        ->and(($this->balance)($newCustomer))->toBe('900.00')
        ->and($this->customer->debt())->toBe('0.00')
        ->and($newCustomer->debt())->toBe('900.00');
});

it('no deja cambiar las condiciones si hay cobros imputados, pero sí los datos generales', function () {
    $sale = ($this->sell)();
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    expect(fn () => ($this->update)($sale, ['down_payment' => 100]))
        ->toThrow(ValidationException::class, $payment->number());

    $updated = ($this->update)($sale, ['notes' => 'Corregida']);

    expect($updated->notes)->toBe('Corregida')
        ->and($updated->down_payment)->toBe('0.00');
});

it('permite cambiar las condiciones después de anular los cobros', function () {
    $sale = ($this->sell)();
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);
    app(CancelPayment::class)->handle($payment, 'Error', $this->admin);

    $updated = ($this->update)($sale, ['down_payment' => 300]);

    expect($updated->installments->pluck('amount')->all())->toBe(['300.00', '300.00', '300.00'])
        ->and($payment->installments()->count())->toBe(1);
});

it('no deja bajar las cuotas por debajo de una que tuvo cobros anulados', function () {
    $sale = ($this->sell)();
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 1200], $this->admin);
    app(CancelPayment::class)->handle($payment, 'Error', $this->admin);
    $twoPayments = FinancingPlan::factory()->create(['installments_count' => 2]);

    expect(fn () => ($this->update)($sale, ['financing_plan_id' => $twoPayments->id]))
        ->toThrow(ValidationException::class, 'menos de 3 cuotas');

    expect($sale->fresh()->installments)->toHaveCount(3);
});

it('no deja cambiar los productos si ya están en una orden de compra', function () {
    $sale = ($this->sell)();
    $order = app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => Supplier::factory()->create()->id, 'date' => '2026-11-02'],
        [['sale_item_id' => $sale->items->first()->id, 'quantity' => 1]],
        $this->admin,
    );

    expect(fn () => ($this->update)($sale, [], [['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => 600, 'unit_margin' => 600]]))
        ->toThrow(ValidationException::class, $order->number());

    // Las condiciones de pago sí se pueden cambiar: lo pedido al proveedor no cambia.
    $updated = ($this->update)($sale, ['down_payment' => 300]);

    expect($updated->items->first()->pending_quantity)->toBe(0)
        ->and($updated->total)->toBe('1200.00');
});

it('con la orden anulada se pueden cambiar los productos y el ítem pedido sigue en la venta', function () {
    $sale = ($this->sell)();
    $item = $sale->items->first();
    $order = app(CreatePurchaseOrder::class)->handle(
        ['supplier_id' => Supplier::factory()->create()->id, 'date' => '2026-11-02'],
        [['sale_item_id' => $item->id, 'quantity' => 1]],
        $this->admin,
    );
    app(CancelPurchaseOrder::class)->handle($order, 'Error', $this->admin);

    $updated = ($this->update)($sale, [], [['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => 600, 'unit_margin' => 600]]);

    expect($updated->items->modelKeys())->toBe([$item->id])
        ->and($updated->items->first()->quantity)->toBe(2)
        ->and($updated->items->first()->pending_quantity)->toBe(2);
});

it('acepta productos inactivos que la venta ya tenía, pero no agregar otros', function () {
    $sale = ($this->sell)();
    $this->product->update(['active' => false]);
    $inactive = Product::factory()->create(['active' => false]);

    $updated = ($this->update)($sale, [], [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 700, 'unit_margin' => 600]]);

    expect($updated->total)->toBe('1300.00');

    expect(fn () => ($this->update)($sale, [], [['product_id' => $inactive->id, 'quantity' => 1, 'unit_cost' => 700, 'unit_margin' => 600]]))
        ->toThrow(ValidationException::class, 'inactivo');
});

it('no edita una venta anulada', function () {
    $sale = Sale::factory()->create(['status' => SaleStatus::Cancelled]);

    expect(fn () => ($this->update)($sale, ['notes' => 'x']))->toThrow(ValidationException::class, 'anulada');
});

it('registra en la actividad qué cambió', function () {
    $sale = ($this->sell)();

    ($this->update)($sale, ['down_payment' => 300, 'notes' => 'Nota']);

    $log = ActivityLog::where('event', ActivityEvent::Updated)->firstOrFail();

    expect($log->subject->is($sale))->toBeTrue()
        ->and($log->description)->toContain($sale->number())
        ->and($log->properties['changes']['down_payment'])->toBe(['old' => '0.00', 'new' => '300.00'])
        ->and($log->properties['changes']['notes'])->toBe(['old' => null, 'new' => 'Nota'])
        ->and($log->properties['changes'])->not->toHaveKey('customer');
});

it('guardar sin cambios no toca nada ni deja registro', function () {
    $sale = ($this->sell)(down: 300);
    $movements = AccountMovement::count();

    ($this->update)($sale, [
        'customer_id' => $this->customer->id,
        'seller_id' => $this->admin->id,
        'financing_plan_id' => $this->plan->id,
        'date' => '2026-11-01',
        'first_due_date' => '2026-12-30',
        'down_payment' => '300',
        'down_payment_method' => 'cash',
    ], [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => '600.00', 'unit_freight' => 0, 'unit_margin' => '600']]);

    expect(AccountMovement::count())->toBe($movements)
        ->and(Payment::count())->toBe(1)
        ->and(ActivityLog::where('event', ActivityEvent::Updated)->count())->toBe(0);
});
