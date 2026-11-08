<?php

use App\Actions\CancelPurchase;
use App\Actions\CreatePurchase;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Widgets\CollectionsOverview;
use App\Filament\Widgets\LatestPurchases;
use App\Filament\Widgets\LowStockProducts;
use App\Filament\Widgets\OverdueInstallments;
use App\Filament\Widgets\ProjectedIncomeChart;
use App\Filament\Widgets\StockAndPurchasesOverview;
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
    $plan = FinancingPlan::factory()->create(['installments_count' => 3, 'surcharge_percent' => 20]);
    $product = Product::factory()->create(['stock' => 10, 'last_cost' => 600]);

    app(CreateSale::class)->handle([
        'customer_id' => $customer->id,
        'seller_id' => $user->id,
        'financing_plan_id' => $plan->id,
        'date' => today()->toDateString(),
        'first_due_date' => $firstDue,
        'down_payment' => 0,
    ], [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]], $user);
}

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create();
});

it('el dashboard se muestra al admin con todos los widgets', function () {
    $this->actingAs($this->admin);

    Livewire::test(Dashboard::class)->assertSuccessful();

    foreach ([CollectionsOverview::class, ProjectedIncomeChart::class, OverdueInstallments::class, StockAndPurchasesOverview::class, LowStockProducts::class, LatestPurchases::class] as $widget) {
        expect($widget::canView())->toBeTrue();
    }
});

it('un vendedor no ve los widgets financieros', function () {
    $this->actingAs(User::factory()->seller()->create());

    foreach ([CollectionsOverview::class, ProjectedIncomeChart::class, OverdueInstallments::class, StockAndPurchasesOverview::class, LowStockProducts::class, LatestPurchases::class] as $widget) {
        expect($widget::canView())->toBeFalse();
    }
});

it('las estadísticas de cobranza muestran los totales', function () {
    $this->actingAs($this->admin);
    dashboardSale($this->customer, $this->admin, today()->subMonths(2)->toDateString());

    Livewire::test(CollectionsOverview::class)
        ->assertSee('Total por cobrar')->assertSee('1.200,00')->assertSee('3 cuotas pendientes')
        ->assertSee('Ganancia futura')->assertSee('600,00')
        ->assertSee('Cuotas vencidas')->assertSee('800,00')->assertSee('2 cuotas vencidas')
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
        ->assertActionHasUrl(TestAction::make('collect')->table($installment), PaymentResource::getUrl('create', ['customer' => $this->customer->id]));
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

it('las estadísticas de stock y compras muestran valor, compras del mes y stock bajo', function () {
    $this->actingAs($this->admin);
    Product::factory()->create(['stock' => 1, 'min_stock' => 5, 'last_cost' => 1000]);
    $supplier = Supplier::factory()->create();
    app(CreatePurchase::class)->handle([
        'supplier_id' => $supplier->id,
        'date' => today()->toDateString(),
        'invoice_number' => 'A-1',
    ], [['product_id' => Product::factory()->create(['stock' => 0])->id, 'quantity' => 2, 'unit_cost' => 500]], $this->admin);

    Livewire::test(StockAndPurchasesOverview::class)
        ->assertSee('Valor del stock')->assertSee('2.000,00')   // 1 × 1000 + 2 × 500
        ->assertSee('Compras del mes')->assertSee('1.000,00')
        ->assertSee('Productos con stock bajo');
});

it('la tabla de stock bajo lista solo los productos activos por debajo del mínimo', function () {
    $this->actingAs($this->admin);
    $low = Product::factory()->create(['stock' => 1, 'min_stock' => 3]);
    $ok = Product::factory()->create(['stock' => 5, 'min_stock' => 3]);
    $inactive = Product::factory()->create(['stock' => 0, 'min_stock' => 3, 'active' => false]);

    Livewire::test(LowStockProducts::class)
        ->assertCanSeeTableRecords([$low])
        ->assertCanNotSeeTableRecords([$ok, $inactive]);
});

it('las últimas compras muestran las 10 más recientes y sin anuladas', function () {
    $this->actingAs($this->admin);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['stock' => 0]);

    $purchases = collect(range(1, 12))->map(fn (int $i): Purchase => app(CreatePurchase::class)->handle([
        'supplier_id' => $supplier->id,
        'date' => today()->subDays(20 - $i)->toDateString(),
        'invoice_number' => "A-{$i}",
    ], [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 100]], $this->admin));

    app(CancelPurchase::class)->handle($purchases[11], 'Error', $this->admin); // la más reciente, anulada

    Livewire::test(LatestPurchases::class)
        ->assertCountTableRecords(10)
        ->assertCanSeeTableRecords($purchases->slice(1, 10))      // de la 2 a la 11
        ->assertCanNotSeeTableRecords([$purchases[0], $purchases[11]]);
});
