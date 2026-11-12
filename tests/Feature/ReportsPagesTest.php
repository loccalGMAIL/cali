<?php

use App\Actions\RegisterPayment;
use App\Filament\Pages\Reports\MonthlyCollection;
use App\Filament\Pages\Reports\Payments;
use App\Filament\Pages\Reports\PendingInstallments;
use App\Filament\Pages\Reports\Sales;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Hoy es 12/11/2026. Ana tiene dos cuotas vencidas y una del mes; Beto empieza a pagar en enero
 * y dejó un anticipo de 200 al comprar.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00'));
    Filament::setCurrentPanel('admin');

    $this->admin = User::factory()->admin()->create(['name' => 'Admin']);
    $this->ana = Customer::factory()->create(['name' => 'Ana Ledesma', 'city' => 'Córdoba']);
    $this->beto = Customer::factory()->create(['name' => 'Beto Farías', 'city' => 'Rosario']);

    reportSale($this->ana, $this->admin, '2026-09-01', date: '2026-08-15');
    reportSale($this->beto, $this->admin, '2027-01-01', date: '2026-11-10', downPayment: 200);
});

it('el admin ve los cuatro informes con sus datos', function () {
    $this->actingAs($this->admin);

    Livewire::test(PendingInstallments::class)->assertSuccessful()
        ->assertSee('Cuotas pendientes por cliente')->assertSee('Ana Ledesma')->assertSee('Beto Farías')
        ->assertSee('Vencido')->assertSee('Nov-26')->assertSee('Más adelante')->assertSee('2.200');

    Livewire::test(MonthlyCollection::class)->assertSuccessful()
        ->assertSee('Ana Ledesma')->assertDontSee('Beto Farías')->assertSee('30/09/2026')->assertSee('$ 1.200,00');

    Livewire::test(Payments::class)->assertSuccessful()
        ->assertSee('Beto Farías')->assertSee('Anticipo venta V-')->assertSee('Efectivo')->assertSee('$ 200,00');

    Livewire::test(Sales::class)->assertSuccessful()
        ->assertSee('Beto Farías')->assertDontSee('Ana Ledesma')->assertSee('$ 600,00')->assertSee('50,0%');
});

it('los filtros cambian la tabla y el enlace de impresión', function () {
    $this->actingAs($this->admin);

    Livewire::test(PendingInstallments::class)
        ->assertActionHasUrl('print', route('filament.admin.reports.print', ['report' => 'pending-installments', 'months' => 12, 'only_overdue' => 0]))
        ->fillForm(['only_overdue' => true, 'months' => 6])
        ->assertSee('Ana Ledesma')
        ->assertDontSee('Beto Farías')
        ->assertActionHasUrl('print', route('filament.admin.reports.print', ['report' => 'pending-installments', 'months' => 6, 'only_overdue' => 1]));

    Livewire::test(Sales::class)
        ->fillForm(['from' => '2026-08-01', 'group_by' => 'seller'])
        ->assertSee('Admin')
        ->assertSee('$ 2.400,00');

    Livewire::test(MonthlyCollection::class)
        ->fillForm(['month' => '2027-01', 'city' => 'Rosario'])
        ->assertSee('Beto Farías')
        ->assertDontSee('Ana Ledesma');
});

it('la versión imprimible muestra cada informe con los filtros aplicados', function () {
    $this->actingAs($this->admin);

    $this->get(route('filament.admin.reports.print', ['report' => 'pending-installments']))
        ->assertOk()
        ->assertSee('Cuotas pendientes por cliente')
        ->assertSee('Ana Ledesma')->assertSee('Beto Farías')->assertSee('Imprimir');

    $this->get(route('filament.admin.reports.print', ['report' => 'pending-installments', 'only_overdue' => 1]))
        ->assertOk()
        ->assertSee('Solo con cuotas vencidas')
        ->assertSee('Ana Ledesma')->assertDontSee('Beto Farías');

    $this->get(route('filament.admin.reports.print', ['report' => 'monthly-collection', 'month' => '2027-01', 'include_overdue' => 0]))
        ->assertOk()
        ->assertSee('Ene 2027')
        ->assertSee('Beto Farías')->assertDontSee('Ana Ledesma');

    $this->get(route('filament.admin.reports.print', ['report' => 'payments']))
        ->assertOk()
        ->assertSee('01/11/2026 al 30/11/2026')
        ->assertSee('Por medio de pago')->assertSee('$ 200,00');

    $this->get(route('filament.admin.reports.print', ['report' => 'sales', 'from' => '2026-08-01', 'group_by' => 'product']))
        ->assertOk()
        ->assertSee('Producto')->assertSee('Unidades')->assertSee('$ 2.400,00');
});

it('un vendedor no accede a los informes ni a su versión imprimible', function () {
    $this->actingAs(User::factory()->seller()->create());

    foreach ([PendingInstallments::class, MonthlyCollection::class, Payments::class, Sales::class] as $page) {
        expect($page::canAccess())->toBeFalse();

        $this->get($page::getUrl())->assertForbidden();
    }

    foreach (['pending-installments', 'monthly-collection', 'payments', 'sales'] as $report) {
        $this->get(route('filament.admin.reports.print', ['report' => $report]))->assertForbidden();
    }
});

it('un informe inexistente devuelve 404 y sin sesión no se puede imprimir', function () {
    $this->get(route('filament.admin.reports.print', ['report' => 'payments']))->assertRedirect();

    $this->actingAs($this->admin);

    $this->get(route('filament.admin.reports.print', ['report' => 'no-existe']))->assertNotFound();
});

it('la tabla de cobros busca y ordena', function () {
    $this->actingAs($this->admin);

    Livewire::test(Payments::class)
        ->searchTable('beto')->assertSee('Beto Farías')
        ->searchTable('farias')->assertSee('Beto Farías')
        ->searchTable('inexistente')->assertDontSee('Beto Farías')
        ->searchTable('')
        ->sortTable('amount', 'desc')->assertSee('Beto Farías');
});

it('en ventas las columnas cambian según cómo se agrupa', function () {
    $this->actingAs($this->admin);

    $detail = Livewire::test(Sales::class)->instance();
    expect($detail->getTable()->getColumn('customer')->isVisible())->toBeTrue()
        ->and($detail->getTable()->getColumn('quantity')->isVisible())->toBeFalse();

    $grouped = Livewire::test(Sales::class)->fillForm(['from' => '2026-08-01', 'group_by' => 'product']);

    expect($grouped->instance()->getTable()->getColumn('customer')->isVisible())->toBeFalse()
        ->and($grouped->instance()->getTable()->getColumn('quantity')->isVisible())->toBeTrue();
    $grouped->assertSee('Unidades');
});

it('en cuotas pendientes se muestran tantos meses como pide el filtro', function () {
    $this->actingAs($this->admin);

    $six = Livewire::test(PendingInstallments::class)->fillForm(['months' => 6]);

    expect($six->instance()->getTable()->getColumn('month_5')->isVisible())->toBeTrue()
        ->and($six->instance()->getTable()->getColumn('month_6')->isVisible())->toBeFalse();
});

it('las columnas ocultas de un informe quedan guardadas por usuario', function () {
    $this->actingAs($this->admin);

    $columns = collect(Livewire::test(Payments::class)->instance()->tableColumns)
        ->map(fn (array $column): array => $column['name'] === 'collector' ? [...$column, 'isToggled' => false] : $column)
        ->all();

    Livewire::test(Payments::class)->call('applyTableColumnManager', $columns);

    session()->flush();

    expect(Livewire::test(Payments::class)->instance()->isTableColumnToggledHidden('collector'))->toBeTrue();
});

it('los cobros muestran a qué cuota se imputaron, como 2/3', function () {
    $this->actingAs($this->admin);

    // 500 sobre cuotas de 400: paga la cuota 1 y parte de la 2.
    app(RegisterPayment::class)->handle(['customer_id' => $this->ana->id, 'amount' => 500], $this->admin);

    Livewire::test(Payments::class)
        ->assertSee('1/3, 2/3')
        ->searchTable('2/3')->assertSee('Cobro de cuotas')->assertDontSee('Anticipo venta V-');

    $this->get(route('filament.admin.reports.print', ['report' => 'payments']))
        ->assertOk()->assertSee('Cuota')->assertSee('1/3, 2/3');
});

it('los cobros muestran el producto de la venta, tanto de un anticipo como de una cuota', function () {
    $this->actingAs($this->admin);

    $heladera = Product::factory()->create(['name' => 'Heladera Gafa']);
    $cocina = Product::factory()->create(['name' => 'Cocina Longvie']);
    $heladeraSale = reportSale($this->ana, $this->admin, '2026-12-01', product: $heladera);
    reportSale($this->beto, $this->admin, '2027-02-01', downPayment: 100, product: $cocina);

    app(RegisterPayment::class)->handle(['customer_id' => $this->ana->id, 'amount' => 100, 'installment_ids' => [$heladeraSale->installments->first()->id]], $this->admin);

    Livewire::test(Payments::class)
        ->assertSee('Cocina Longvie')
        ->assertSee('Heladera Gafa')
        ->searchTable('cocina')->assertSee('Cocina Longvie')->assertDontSee('Heladera Gafa');

    $this->get(route('filament.admin.reports.print', ['report' => 'payments']))
        ->assertOk()->assertSee('Heladera Gafa')->assertSee('Cocina Longvie');
});
