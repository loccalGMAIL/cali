<?php

use App\Filament\Pages\Reports\MonthlyCollection;
use App\Filament\Pages\Reports\Payments;
use App\Filament\Pages\Reports\PendingInstallments;
use App\Filament\Pages\Reports\Sales;
use App\Models\Customer;
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
        ->assertSee('Beto Farías')->assertSee('Anticipo venta #')->assertSee('Efectivo')->assertSee('$ 200,00');

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
