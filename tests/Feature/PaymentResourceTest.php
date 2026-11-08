<?php

use App\Actions\CancelPayment;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Enums\InstallmentStatus;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\PaymentPreview;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create();

    $plan = FinancingPlan::factory()->create(['installments_count' => 3, 'surcharge_percent' => 20]);
    $product = Product::factory()->create(['stock' => 10, 'last_cost' => 600]);

    $this->sale = app(CreateSale::class)->handle([
        'customer_id' => $this->customer->id,
        'seller_id' => $this->admin->id,
        'financing_plan_id' => $plan->id,
        'date' => '2026-11-01',
        'first_due_date' => '2026-12-01',
        'down_payment' => 0,
    ], [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]], $this->admin);
});

it('registra un cobro desde el panel y lo imputa a las cuotas', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'amount' => 500,
            'method' => 'cash',
            'date' => '2026-11-08',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $installments = $this->sale->installments()->get();

    expect(Payment::count())->toBe(1)
        ->and($installments[0]->status)->toBe(InstallmentStatus::Paid)
        ->and($installments[1]->status)->toBe(InstallmentStatus::Partial)
        ->and($installments[1]->paid_amount)->toBe('100.00');
});

it('muestra el error cuando el monto supera la deuda', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePayment::class)
        ->fillForm(['customer_id' => $this->customer->id, 'amount' => 5000, 'date' => '2026-11-08'])
        ->call('create')
        ->assertHasFormErrors(['amount']);

    expect(Payment::count())->toBe(0)
        ->and(Installment::where('paid_amount', '>', 0)->count())->toBe(0);
});

it('precarga el cliente cuando viene en la URL', function () {
    $this->actingAs($this->admin);

    $this->get('http://'.config('domains.panel').'/payments/create?customer='.$this->customer->id)->assertOk();
});

it('lista un cobro y muestra su detalle con la imputación', function () {
    $this->actingAs($this->admin);
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    Livewire::test(ListPayments::class)->assertCanSeeTableRecords([$payment]);
    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])->assertSuccessful();
});

it('no permite editar ni borrar cobros', function () {
    $this->actingAs($this->admin);
    $payment = Payment::factory()->create();

    expect(PaymentResource::canEdit($payment))->toBeFalse()
        ->and(PaymentResource::canDelete($payment))->toBeFalse();
});

it('no permite a un vendedor acceder a los cobros', function () {
    $this->actingAs(User::factory()->seller()->create());

    expect(PaymentResource::canViewAny())->toBeFalse()
        ->and(PaymentResource::canCreate())->toBeFalse();
});

it('la vista previa muestra la deuda total y marca las cuotas vencidas', function () {
    Installment::query()->where('sale_id', $this->sale->id)->where('number', 1)->update(['due_date' => today()->subDays(5)]);

    $html = (string) PaymentPreview::debt($this->customer->id);

    expect($html)->toContain('Deuda total: $ 1.200,00')
        ->and($html)->toContain('cuota 1')
        ->and($html)->toContain('(vencida)');
});

it('la vista previa muestra a qué cuotas se imputa el monto', function () {
    $html = (string) PaymentPreview::allocation($this->customer->id, 500);

    expect($html)->toContain('cuota 1')->toContain('queda pagada')
        ->and($html)->toContain('cuota 2')->toContain('queda con saldo $ 300,00');
});

it('la vista previa avisa si el monto supera la deuda', function () {
    expect((string) PaymentPreview::allocation($this->customer->id, 9999))->toContain('supera la deuda');
});

it('la vista previa pide datos si faltan', function () {
    expect((string) PaymentPreview::debt(null))->toContain('Elegí un cliente')
        ->and((string) PaymentPreview::allocation($this->customer->id, null))->toContain('Ingresá un monto');
});

it('anula un cobro desde la tabla pidiendo el motivo', function () {
    $this->actingAs($this->admin);
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    Livewire::test(ListPayments::class)
        ->callAction(TestAction::make('cancel')->table($payment), data: ['reason' => 'Se cargó dos veces'])
        ->assertNotified('Anulación registrada');

    expect($payment->fresh()->isCancelled())->toBeTrue()
        ->and($payment->fresh()->cancellation_reason)->toBe('Se cargó dos veces')
        ->and($this->customer->debt())->toBe('1200.00');
});

it('exige el motivo para anular', function () {
    $this->actingAs($this->admin);
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    Livewire::test(ListPayments::class)
        ->callAction(TestAction::make('cancel')->table($payment), data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect($payment->fresh()->isCancelled())->toBeFalse();
});

it('anula desde la vista de detalle y muestra los datos de la anulación', function () {
    $this->actingAs($this->admin);
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->callAction('cancel', data: ['reason' => 'Error de monto'])
        ->assertNotified('Anulación registrada');

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->assertSee('Error de monto')
        ->assertActionHidden('cancel');
});

it('no ofrece anular un cobro ya anulado ni un anticipo', function () {
    $this->actingAs($this->admin);
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);
    app(CancelPayment::class)->handle($payment, 'Error', $this->admin);
    $downPayment = Payment::factory()->downPayment()->create();

    Livewire::test(ListPayments::class)
        ->assertActionHidden(TestAction::make('cancel')->table($payment))
        ->assertActionHidden(TestAction::make('cancel')->table($downPayment));
});

it('un vendedor no puede anular cobros', function () {
    $seller = User::factory()->seller()->create();
    $payment = Payment::factory()->create();

    expect($seller->can('cancel', $payment))->toBeFalse()
        ->and($this->admin->can('cancel', $payment))->toBeTrue();
});
