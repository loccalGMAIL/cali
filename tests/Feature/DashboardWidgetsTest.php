<?php

use App\Actions\CancelPurchase;
use App\Actions\CancelSale;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Widgets\CollectionsOverview;
use App\Filament\Widgets\LatestPurchases;
use App\Filament\Widgets\OverdueInstallments;
use App\Filament\Widgets\PendingPurchaseItems;
use App\Filament\Widgets\ProjectedIncomeChart;
use App\Filament\Widgets\PurchasesOverview;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;

function dashboardSale(Customer $customer, User $user, string $firstDue): void
{
    $plan = FinancingPlan::factory()->create(['installments_count' => 3]);
    $product = Product::factory()->create(['last_cost' => 600]);

    app(CreateSale::class)->handle([
        'customer_id' => $customer->id,
        'seller_id' => $user->id,
        'financing_plan_id' => $plan->id,
        'date' => today()->toDateString(),
        'first_due_date' => $firstDue,
        'down_payment' => 0,
    ], [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 600]], $user);
}

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create();
});

it('el dashboard se muestra al admin con todos los widgets', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dashboard::class)->assertSuccessful();

    foreach ([CollectionsOverview::class, ProjectedIncomeChart::class, OverdueInstallments::class, PurchasesOverview::class, PendingPurchaseItems::class, LatestPurchases::class] as $widget) {
        expect($widget::canView())->toBeTrue();
    }
});

it('un vendedor no ve los widgets financieros', function () {
    $this->actingAs(User::factory()->seller()->create());

    foreach ([CollectionsOverview::class, ProjectedIncomeChart::class, OverdueInstallments::class, PurchasesOverview::class, PendingPurchaseItems::class, LatestPurchases::class] as $widget) {
        expect($widget::canView())->toBeFalse();
    }
});

it('las estadísticas de cobranza muestran los totales', function () {
    $this->actingAs($this->admin);
    dashboardSale($this->customer, $this->admin, today()->subMonths(2)->toDateString());

    Livewire::test(CollectionsOverview::class)
        ->assertSee('Total por cobrar')->assertSee('1.200')->assertSee('3 cuotas pendientes')
        ->assertSee('Ganancia futura')->assertSee('600')
        ->assertSee('Cuotas vencidas')->assertSee('800')->assertSee('2 cuotas vencidas')
        ->assertSee('Cobrado este mes');
});

it('compara lo cobrado contra el mes anterior', function () {
    expect(CollectionsOverview::compare('150.00', '100.00'))->toBe(['+50,0%', 'success', Heroicon::OutlinedArrowTrendingUp])
        ->and(CollectionsOverview::compare('50.00', '100.00'))->toBe(['-50,0%', 'danger', Heroicon::OutlinedArrowTrendingDown])
        ->and(CollectionsOverview::compare('100.00', '100.00'))->toBe(['Sin cambios', 'gray', Heroicon::OutlinedMinus])
        ->and(CollectionsOverview::compare('80.00', '0.00'))->toBe(['Sin cobros el mes anterior', 'gray', Heroicon::OutlinedMinus])
        ->and(CollectionsOverview::compare('0.00', '0.00'))->toBe(['Sin cobros', 'gray', Heroicon::OutlinedMinus]);
});

it('el gráfico tiene 12 meses con las dos series', function () {
    $this->actingAs($this->admin);
    dashboardSale($this->customer, $this->admin, today()->addMonth()->toDateString());

    $component = Livewire::test(ProjectedIncomeChart::class);
    $data = (fn () => $this->getData())->call($component->instance());

    expect($data['labels'])->toHaveCount(12)
        ->and($data['datasets'])->toHaveCount(2)
        ->and($data['datasets'][0]['label'])->toBe('Monto a cobrar')
        ->and($data['datasets'][1]['label'])->toBe('Ganancia')
        ->and(array_sum($data['datasets'][0]['data']))->toBe(1200.0)
        ->and(array_sum($data['datasets'][1]['data']))->toBe(600.0);
});

it('la tabla de vencidas lista las cuotas vencidas con sus días de atraso y saldo', function () {
    $this->actingAs($this->admin);
    dashboardSale($this->customer, $this->admin, today()->subMonths(2)->toDateString());
    dashboardSale(Customer::factory()->create(), $this->admin, today()->addMonths(2)->toDateString());

    $overdue = Installment::overdue()->get();

    Livewire::test(OverdueInstallments::class)
        ->assertCanSeeTableRecords($overdue)
        ->assertCountTableRecords(2)
        ->assertTableColumnStateSet('balance', '400.00', $overdue->first())
        ->assertTableColumnStateSet('days_late', (int) $overdue->first()->due_date->diffInDays(today()), $overdue->first());
});

it('la tabla de vencidas ofrece cobrar con el cliente elegido', function () {
    $this->actingAs($this->admin);
    dashboardSale($this->customer, $this->admin, today()->subMonth()->toDateString());
    $installment = Installment::overdue()->firstOrFail();

    Livewire::test(OverdueInstallments::class)
        ->assertActionVisible(TestAction::make('collect')->table($installment))
        ->assertActionHasUrl(TestAction::make('collect')->table($installment), PaymentResource::getUrl('create', ['customer' => $this->customer->id, 'installment' => $installment->id]));
});

it('la tabla de vencidas está vacía si no hay deuda atrasada', function () {
    $this->actingAs($this->admin);
    dashboardSale($this->customer, $this->admin, today()->addMonth()->toDateString());

    Livewire::test(OverdueInstallments::class)->assertCountTableRecords(0);
});

it('una cuota cobrada sale de la tabla de vencidas', function () {
    $this->actingAs($this->admin);
    dashboardSale($this->customer, $this->admin, today()->subMonth()->toDateString());
    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    Livewire::test(OverdueInstallments::class)->assertCountTableRecords(0);
});

it('las estadísticas de compras muestran lo pendiente, las órdenes abiertas y las compras del mes', function () {
    $this->actingAs($this->admin);
    $product = Product::factory()->create();
    $supplier = Supplier::factory()->create();
    soldItem($this->admin, $product, 2, 1500);
    issuedOrder($this->admin, $supplier, [soldItem($this->admin, $product)]);
    receivedPurchase($this->admin, $supplier, $product, 2, 500, 'A-1');

    Livewire::test(PurchasesOverview::class)
        ->assertSee('Pendiente de compra')->assertSee('3.000')->assertSee('2 unidades sin pedir')
        ->assertSee('Órdenes sin recibir')
        ->assertSee('Compras del mes')->assertSee('1.000');
});

it('la tabla de pendientes de compra lista lo vendido sin pedir y no lo de ventas anuladas', function () {
    $this->actingAs($this->admin);
    $product = Product::factory()->create();
    $pending = soldItem($this->admin, $product);
    $ordered = soldItem($this->admin, $product);
    $cancelled = soldItem($this->admin, $product);
    issuedOrder($this->admin, Supplier::factory()->create(), [$ordered]);
    app(CancelSale::class)->handle($cancelled->sale, 'Error', $this->admin);

    Livewire::test(PendingPurchaseItems::class)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$ordered, $cancelled]);
});

it('las últimas compras muestran las 10 más recientes y sin anuladas', function () {
    $this->actingAs($this->admin);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    $purchases = collect(range(1, 12))->map(fn (int $i): Purchase => receivedPurchase(
        $this->admin, $supplier, $product, 1, 100, "A-{$i}", today()->subDays(20 - $i)->toDateString(),
    ));

    app(CancelPurchase::class)->handle($purchases[11], 'Error', $this->admin); // la más reciente, anulada

    Livewire::test(LatestPurchases::class)
        ->assertCountTableRecords(10)
        ->assertCanSeeTableRecords($purchases->slice(1, 10))      // de la 2 a la 11
        ->assertCanNotSeeTableRecords([$purchases[0], $purchases[11]]);
});

it('el dashboard tiene accesos directos a cobrar, nuevo cliente y nueva orden de compra', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dashboard::class)
        ->assertActionVisible('collect')
        ->assertActionHasUrl('collect', PaymentResource::getUrl('create'))
        ->assertActionVisible('newCustomer')
        ->assertActionHasUrl('newCustomer', CustomerResource::getUrl('create'))
        ->assertActionVisible('newPurchaseOrder')
        ->assertActionHasUrl('newPurchaseOrder', PurchaseOrderResource::getUrl('create'));
});

it('los accesos directos respetan los permisos: un vendedor solo ve el de cliente', function () {
    $this->actingAs(User::factory()->seller()->create());

    Livewire::test(Dashboard::class)
        ->assertActionHidden('collect')
        ->assertActionVisible('newCustomer')
        ->assertActionHidden('newPurchaseOrder');
});

it('los accesos directos del dashboard se llaman Cobrar, Nuevo Cliente y Nueva Orden de Compra', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dashboard::class)
        ->assertSee('Cobrar')
        ->assertSee('Nuevo Cliente')
        ->assertSee('Nueva Orden de Compra');
});
