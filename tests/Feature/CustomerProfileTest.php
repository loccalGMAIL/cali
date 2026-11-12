<?php

use App\Actions\CancelSale;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\AccountMovementsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\InstallmentsRelationManager;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountService;
use App\Services\SaleCalculator;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create();
    $this->plan = FinancingPlan::factory()->create(['installments_count' => 3]);
    $this->product = Product::factory()->create(['last_cost' => 600]);

    $this->sell = fn (Customer $customer, string $firstDue = '2026-12-01', int $down = 0): Sale => app(CreateSale::class)->handle([
        'customer_id' => $customer->id,
        'seller_id' => $this->admin->id,
        'financing_plan_id' => $this->plan->id,
        'date' => '2026-11-01',
        'first_due_date' => $firstDue,
        'down_payment' => $down,
    ], [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 600]], $this->admin);
});

it('calcula la deuda, la deuda vencida y el próximo vencimiento del cliente', function () {
    ($this->sell)($this->customer, firstDue: today()->subMonths(2)->toDateString());

    // Con vencimiento hace 2 meses, hace 1 mes y hoy+0 meses: las dos primeras están vencidas.
    expect($this->customer->debt())->toBe('1200.00')
        ->and($this->customer->overdueCount())->toBe(2)
        ->and($this->customer->overdueDebt())->toBe('800.00')
        ->and($this->customer->nextDueDate()->toDateString())->toBe(SaleCalculator::dueDateFor(today(), -2)->toDateString());
});

it('un cliente sin deuda devuelve ceros', function () {
    expect($this->customer->debt())->toBe('0.00')
        ->and($this->customer->overdueDebt())->toBe('0.00')
        ->and($this->customer->overdueCount())->toBe(0)
        ->and($this->customer->nextDueDate())->toBeNull();
});

it('las cuotas de una venta anulada no cuentan como deuda ni como vencidas', function () {
    $sale = ($this->sell)($this->customer, firstDue: today()->subMonths(2)->toDateString());
    app(CancelSale::class)->handle($sale, 'Error', $this->admin);

    expect($this->customer->debt())->toBe('0.00')
        ->and($this->customer->overdueCount())->toBe(0)
        ->and($this->customer->nextDueDate())->toBeNull();
});

it('el listado muestra la deuda y lo vencido de cada cliente', function () {
    $this->actingAs($this->admin);
    ($this->sell)($this->customer, firstDue: today()->subMonths(2)->toDateString());
    $clean = Customer::factory()->create();

    Livewire::test(ListCustomers::class)
        ->assertCanSeeTableRecords([$this->customer, $clean])
        ->assertTableColumnStateSet('debt', 1200, $this->customer)
        ->assertTableColumnStateSet('overdue_debt', 800, $this->customer)
        ->assertTableColumnStateSet('debt', 0, $clean);
});

it('filtra los clientes con deuda y con cuotas vencidas', function () {
    $this->actingAs($this->admin);
    $withDebt = Customer::factory()->create();
    $overdue = Customer::factory()->create();
    $clean = Customer::factory()->create();
    ($this->sell)($withDebt, firstDue: today()->addMonth()->toDateString());
    ($this->sell)($overdue, firstDue: today()->subMonths(2)->toDateString());

    Livewire::test(ListCustomers::class)
        ->filterTable('with_debt')
        ->assertCanSeeTableRecords([$withDebt, $overdue])
        ->assertCanNotSeeTableRecords([$clean]);

    Livewire::test(ListCustomers::class)
        ->filterTable('overdue')
        ->assertCanSeeTableRecords([$overdue])
        ->assertCanNotSeeTableRecords([$withDebt, $clean]);
});

it('la ficha se muestra con la situación de la cuenta', function () {
    $this->actingAs($this->admin);
    ($this->sell)($this->customer, firstDue: today()->subMonth()->toDateString(), down: 200);

    Livewire::test(ViewCustomer::class, ['record' => $this->customer->getKey()])
        ->assertSuccessful()
        ->assertSee('Saldo de cuenta corriente')
        ->assertSee('1.000,00');   // 1200 de venta - 200 de anticipo = 1000 de deuda
});

it('la pestaña de cuotas lista las cuotas del cliente y marca las vencidas', function () {
    $this->actingAs($this->admin);
    ($this->sell)($this->customer, firstDue: today()->subMonth()->toDateString());
    ($this->sell)(Customer::factory()->create());

    $manager = fn () => Livewire::test(InstallmentsRelationManager::class, [
        'ownerRecord' => $this->customer,
        'pageClass' => ViewCustomer::class,
    ]);

    $manager()->assertCanSeeTableRecords($this->customer->installments)
        ->assertCountTableRecords(3);

    $overdue = $this->customer->installments()->overdue()->get();
    $manager()->filterTable('overdue')->assertCanSeeTableRecords($overdue)->assertCountTableRecords($overdue->count());
});

it('la pestaña de cuenta corriente muestra los movimientos con su saldo', function () {
    $this->actingAs($this->admin);
    ($this->sell)($this->customer, down: 200);
    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 300], $this->admin);

    $movements = $this->customer->accountMovements()->orderBy('id')->get();

    Livewire::test(AccountMovementsRelationManager::class, [
        'ownerRecord' => $this->customer,
        'pageClass' => ViewCustomer::class,
    ])->assertCanSeeTableRecords($movements)->assertCountTableRecords(3);

    expect($movements->pluck('balance')->all())->toBe(['1200.00', '1000.00', '700.00'])
        ->and(app(AccountService::class)->balance($this->customer))->toBe($this->customer->debt())->toBe('700.00');
});

it('ofrece cobrar solo si hay deuda y apunta al formulario con el cliente elegido', function () {
    $this->actingAs($this->admin);

    Livewire::test(ViewCustomer::class, ['record' => $this->customer->getKey()])->assertActionHidden('collect');

    ($this->sell)($this->customer);

    Livewire::test(ViewCustomer::class, ['record' => $this->customer->getKey()])
        ->assertActionVisible('collect')
        ->assertActionHasUrl('collect', PaymentResource::getUrl('create', ['customer' => $this->customer->getKey()]));
});

it('no ofrece cobrar a un vendedor', function () {
    ($this->sell)($this->customer);
    $this->actingAs(User::factory()->seller()->create());

    Livewire::test(ViewCustomer::class, ['record' => $this->customer->getKey()])->assertActionHidden('collect');
});

it('un cliente con ventas o cobros no se puede borrar', function () {
    $withSale = Customer::factory()->create();
    ($this->sell)($withSale);
    $free = Customer::factory()->create();

    expect($this->admin->can('delete', $withSale))->toBeFalse()
        ->and($this->admin->can('delete', $free))->toBeTrue()
        ->and(User::factory()->seller()->create()->can('delete', $free))->toBeFalse();
});

it('el cobro desde la ficha llega con el cliente precargado y se imputa', function () {
    $this->actingAs($this->admin);
    ($this->sell)($this->customer);

    $this->get(PaymentResource::getUrl('create', ['customer' => $this->customer->getKey()]))
        ->assertOk()
        ->assertSee($this->customer->name);

    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    expect(Installment::where('customer_id', $this->customer->id)->unpaid()->count())->toBe(2);
});
