<?php

use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\SalePreview;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->customer = Customer::factory()->create();
    $this->plan = FinancingPlan::factory()->create(['installments_count' => 3, 'surcharge_percent' => 20]);
    $this->product = Product::factory()->manualPrice(1000)->create(['stock' => 5, 'last_cost' => 600]);
});

it('carga una venta desde el panel y deja cuotas, stock y cuenta corriente', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'seller_id' => $admin->id,
            'financing_plan_id' => $this->plan->id,
            'date' => '2026-11-07',
            'first_due_date' => '2026-12-07',
            'down_payment' => 200,
            'down_payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::firstOrFail();
    expect($sale->total)->toBe('1160.00')
        ->and($sale->installments)->toHaveCount(3)
        ->and($this->product->fresh()->stock)->toBe(4);
});

it('muestra el error de stock insuficiente en el formulario', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'seller_id' => $admin->id,
            'financing_plan_id' => $this->plan->id,
            'date' => '2026-11-07',
            'first_due_date' => '2026-12-07',
            'items' => [['product_id' => $this->product->id, 'quantity' => 99, 'unit_price' => 1000]],
        ])
        ->call('create')
        ->assertHasFormErrors(['items']);

    expect(Sale::count())->toBe(0);
});

it('fuerza al vendedor a registrar la venta a su nombre', function () {
    $seller = User::factory()->seller()->create();
    $other = User::factory()->seller()->create();
    $this->actingAs($seller);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'seller_id' => $other->id,
            'financing_plan_id' => $this->plan->id,
            'date' => '2026-11-07',
            'first_due_date' => '2026-12-07',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        ])
        ->call('create');

    expect(Sale::firstOrFail()->seller_id)->toBe($seller->id);
});

it('el vendedor solo ve sus propias ventas', function () {
    $seller = User::factory()->seller()->create();
    $mine = Sale::factory()->create(['seller_id' => $seller->id]);
    $theirs = Sale::factory()->create();
    $this->actingAs($seller);

    Livewire::test(ListSales::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);

    expect(SaleResource::canView($theirs))->toBeFalse();
});

it('el admin ve todas las ventas y su detalle', function () {
    $this->actingAs(User::factory()->admin()->create());
    $sale = Sale::factory()->create();

    Livewire::test(ListSales::class)->assertCanSeeTableRecords([$sale]);
    Livewire::test(ViewSale::class, ['record' => $sale->getKey()])->assertSuccessful();
});

it('no permite editar ni borrar ventas confirmadas', function () {
    $this->actingAs(User::factory()->admin()->create());
    $sale = Sale::factory()->create();

    expect(SaleResource::canEdit($sale))->toBeFalse()
        ->and(SaleResource::canDelete($sale))->toBeFalse();
});

it('la vista previa muestra total, cuota y cronograma', function () {
    $html = (string) SalePreview::render(
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        $this->plan->id,
        '2026-12-07',
        200,
    );

    expect($html)->toContain('$ 1.160,00')
        ->and($html)->toContain('3 cuotas de $ 320,00')
        ->and($html)->toContain('07/12/2026')
        ->and($html)->toContain('07/02/2027');
});

it('la vista previa avisa si el anticipo supera el precio', function () {
    $html = (string) SalePreview::render(
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000]],
        $this->plan->id,
        '2026-12-07',
        5000,
    );

    expect($html)->toContain('anticipo no puede superar');
});

it('la vista previa pide completar datos si faltan', function () {
    expect((string) SalePreview::render([], null, null, 0))->toContain('Elegí al menos un producto');
});
