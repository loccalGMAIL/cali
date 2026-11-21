<?php

use App\Actions\CreateSale as CreateSaleAction;
use App\Actions\RegisterPayment;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\SalePreview;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->customer = Customer::factory()->create();
    $this->plan = FinancingPlan::factory()->create(['installments_count' => 3]);
    $this->product = Product::factory()->create(['last_cost' => 600]);
});

it('carga una venta desde el panel con costo y margen, y deja cuotas y pendientes de compra', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'seller_id' => $admin->id,
            'financing_plan_id' => $this->plan->id,
            'date' => '2026-11-07',
            'first_due_date' => '2026-12-30',
            'down_payment' => 200,
            'down_payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 500]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // (600 + 500 − 200) ÷ 3 = 300
    $sale = Sale::firstOrFail();
    expect($sale->total)->toBe('1100.00')
        ->and($sale->total_cost)->toBe('600.00')
        ->and($sale->installments->pluck('amount')->all())->toBe(['300.00', '300.00', '300.00'])
        ->and($sale->items->first()->pending_quantity)->toBe(1);
});

it('carga una venta desde el panel con flete y lo divide en las cuotas', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'seller_id' => $admin->id,
            'financing_plan_id' => $this->plan->id,
            'date' => '2026-11-07',
            'first_due_date' => '2026-12-30',
            'down_payment' => 0,
            'down_payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_freight' => 300, 'unit_margin' => 300]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // (600 + 300 + 300) ÷ 3 = 400
    $sale = Sale::firstOrFail();
    expect($sale->total)->toBe('1200.00')
        ->and($sale->total_freight)->toBe('300.00')
        ->and($sale->items->first()->unit_freight)->toBe('300.00')
        ->and($sale->installments->pluck('amount')->all())->toBe(['400.00', '400.00', '400.00']);
});

it('muestra en el formulario el error de un anticipo mayor al total', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'seller_id' => $admin->id,
            'financing_plan_id' => $this->plan->id,
            'date' => '2026-11-07',
            'first_due_date' => '2026-12-30',
            'down_payment' => 5000,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 400]],
        ])
        ->call('create')
        ->assertHasFormErrors(['down_payment']);

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
            'first_due_date' => '2026-12-30',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 400]],
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

it('sin el permiso no se editan ventas, aunque sea admin, y nunca se borran', function () {
    $this->actingAs(User::factory()->admin()->create());
    $sale = Sale::factory()->create();

    expect(SaleResource::canEdit($sale))->toBeFalse()
        ->and(SaleResource::canDelete($sale))->toBeFalse();

    $this->get(SaleResource::getUrl('edit', ['record' => $sale]))->assertForbidden();
});

it('con el permiso el admin edita cualquier venta vigente pero no las anuladas', function () {
    $this->actingAs(User::factory()->admin()->canEditSales()->create());

    expect(SaleResource::canEdit(Sale::factory()->create()))->toBeTrue()
        ->and(SaleResource::canEdit(Sale::factory()->create(['status' => SaleStatus::Cancelled])))->toBeFalse()
        ->and(SaleResource::canDelete(Sale::factory()->create()))->toBeFalse();
});

it('con el permiso el vendedor edita solo sus ventas', function () {
    $seller = User::factory()->seller()->canEditSales()->create();
    $this->actingAs($seller);

    expect(SaleResource::canEdit(Sale::factory()->create(['seller_id' => $seller->id])))->toBeTrue()
        ->and(SaleResource::canEdit(Sale::factory()->create()))->toBeFalse();
});

it('edita una venta desde el panel y vuelve al detalle', function () {
    $admin = User::factory()->admin()->canEditSales()->create();
    $this->actingAs($admin);
    $sale = app(CreateSaleAction::class)->handle([
        'customer_id' => $this->customer->id,
        'seller_id' => $admin->id,
        'financing_plan_id' => $this->plan->id,
        'date' => '2026-11-07',
        'first_due_date' => '2026-12-30',
        'down_payment' => 200,
    ], [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 500]], $admin);

    Livewire::test(EditSale::class, ['record' => $sale->getKey()])
        ->assertSchemaStateSet([
            'customer_id' => $this->customer->id,
            'financing_plan_id' => $this->plan->id,
            'first_due_date' => '2026-12-30',
            'down_payment_method' => PaymentMethod::Cash,
        ])
        ->fillForm([
            'down_payment' => 500,
            'notes' => 'Ajuste de anticipo',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(SaleResource::getUrl('view', ['record' => $sale]));

    // (1100 − 500) ÷ 3 = 200
    $sale->refresh();
    expect($sale->down_payment)->toBe('500.00')
        ->and($sale->notes)->toBe('Ajuste de anticipo')
        ->and($sale->installments->pluck('amount')->all())->toBe(['200.00', '200.00', '200.00']);
});

it('con cobros imputados el formulario bloquea las condiciones y explica por qué', function () {
    $admin = User::factory()->admin()->canEditSales()->create();
    $this->actingAs($admin);
    $sale = app(CreateSaleAction::class)->handle([
        'customer_id' => $this->customer->id,
        'seller_id' => $admin->id,
        'financing_plan_id' => $this->plan->id,
        'date' => '2026-11-07',
        'first_due_date' => '2026-12-30',
    ], [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 300]], $admin);
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 300], $admin);

    Livewire::test(EditSale::class, ['record' => $sale->getKey()])
        ->assertSee($payment->number())
        ->assertFormFieldDisabled('down_payment')
        ->assertFormFieldDisabled('customer_id')
        ->assertFormFieldDisabled('items')
        ->assertFormFieldEnabled('notes')
        ->fillForm(['notes' => 'Solo la nota'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($sale->fresh()->notes)->toBe('Solo la nota')
        ->and($sale->fresh()->installments->pluck('paid_amount')->first())->toBe('300.00');
});

it('el vendedor no puede pasarle su venta a otro al editarla', function () {
    $seller = User::factory()->seller()->canEditSales()->create();
    $other = User::factory()->seller()->create();
    $this->actingAs($seller);
    $sale = app(CreateSaleAction::class)->handle([
        'customer_id' => $this->customer->id,
        'seller_id' => $seller->id,
        'financing_plan_id' => $this->plan->id,
        'date' => '2026-11-07',
        'first_due_date' => '2026-12-30',
    ], [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 300]], $seller);

    Livewire::test(EditSale::class, ['record' => $sale->getKey()])
        ->fillForm(['seller_id' => $other->id, 'notes' => 'x'])
        ->call('save');

    expect($sale->fresh()->seller_id)->toBe($seller->id);
});

it('la vista previa muestra total, cuota y cronograma', function () {
    $html = (string) SalePreview::render(
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 560]],
        $this->plan->id,
        '2026-12-07',
        200,
    );

    // (600 + 560 − 200) ÷ 3 = 320
    expect($html)->toContain('$ 1.160,00')
        ->and($html)->toContain('$ 560,00')
        ->and($html)->toContain('$ 960,00')
        ->and($html)->toContain('3 cuotas de $ 320,00')
        ->and($html)->toContain('30/12/2026')
        ->and($html)->toContain('28/02/2027');
});

it('la vista previa muestra el flete y lo incluye en las cuotas', function () {
    $html = (string) SalePreview::render(
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_freight' => 240, 'unit_margin' => 360]],
        $this->plan->id,
        '2026-12-07',
        0,
    );

    // (600 + 240 + 360) ÷ 3 = 400
    expect($html)->toContain('Flete')
        ->and($html)->toContain('$ 240,00')
        ->and($html)->toContain('$ 360,00')
        ->and($html)->toContain('$ 1.200,00')
        ->and($html)->toContain('3 cuotas de $ 400,00');
});

it('la vista previa avisa si el anticipo supera el total', function () {
    $html = (string) SalePreview::render(
        [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 400]],
        $this->plan->id,
        '2026-12-07',
        5000,
    );

    expect($html)->toContain('anticipo no puede superar');
});

it('la vista previa pide completar datos si faltan', function () {
    expect((string) SalePreview::render([], null, null, 0))->toContain('Elegí al menos un producto');
});

it('anula una venta desde la tabla y la saca de pendientes de compra', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $sale = app(CreateSaleAction::class)->handle([
        'customer_id' => $this->customer->id,
        'seller_id' => $admin->id,
        'financing_plan_id' => $this->plan->id,
        'date' => '2026-11-07',
        'first_due_date' => '2026-12-07',
        'down_payment' => 0,
    ], [['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => 600, 'unit_margin' => 400]], $admin);

    Livewire::test(ListSales::class)
        ->callAction(TestAction::make('cancel')->table($sale), data: ['reason' => 'Error de carga'])
        ->assertNotified('Anulación registrada');

    expect($sale->fresh()->isCancelled())->toBeTrue()
        ->and($sale->items->first()->fresh()->pending_quantity)->toBe(0);
});

it('exige el motivo para anular una venta', function () {
    $this->actingAs(User::factory()->admin()->create());
    $sale = Sale::factory()->create();

    Livewire::test(ListSales::class)
        ->callAction(TestAction::make('cancel')->table($sale), data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);
});

it('no ofrece anular una venta ya anulada ni a un vendedor', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->seller()->create();
    $mine = Sale::factory()->create(['seller_id' => $seller->id]);
    $cancelled = Sale::factory()->create(['status' => SaleStatus::Cancelled]);

    $this->actingAs($admin);
    Livewire::test(ListSales::class)->assertActionHidden(TestAction::make('cancel')->table($cancelled));

    $this->actingAs($seller);
    Livewire::test(ListSales::class)->assertActionHidden(TestAction::make('cancel')->table($mine));

    expect($seller->can('cancel', $mine))->toBeFalse();
});

it('avisa en pantalla si la venta tiene cobros imputados', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $sale = app(CreateSaleAction::class)->handle([
        'customer_id' => $this->customer->id,
        'seller_id' => $admin->id,
        'financing_plan_id' => $this->plan->id,
        'date' => '2026-11-07',
        'first_due_date' => '2026-12-07',
        'down_payment' => 0,
    ], [['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 400]], $admin);
    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 100], $admin);

    Livewire::test(ViewSale::class, ['record' => $sale->getKey()])
        ->callAction('cancel', data: ['reason' => 'Error'])
        ->assertNotified('No se pudo anular');

    expect($sale->fresh()->isCancelled())->toBeFalse();
});

it('propone el vendedor por defecto al cargar una venta', function () {
    $admin = User::factory()->admin()->create();
    $defaultSeller = User::factory()->seller()->create(['is_default_seller' => true]);
    $this->actingAs($admin);

    Livewire::test(CreateSale::class)
        ->assertSchemaStateSet(['seller_id' => $defaultSeller->id]);
});

it('sin vendedor por defecto activo propone a quien carga la venta', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->seller()->inactive()->create(['is_default_seller' => true]);
    $this->actingAs($admin);

    Livewire::test(CreateSale::class)
        ->assertSchemaStateSet(['seller_id' => $admin->id]);
});

it('el vendedor que carga la venta queda propuesto aunque haya otro por defecto', function () {
    $seller = User::factory()->seller()->create();
    User::factory()->seller()->create(['is_default_seller' => true]);
    $this->actingAs($seller);

    Livewire::test(CreateSale::class)
        ->assertSchemaStateSet(['seller_id' => $seller->id]);
});

it('el formulario de venta pide fecha, cliente y vendedor; después productos; y en pago el plan y la primera cuota', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateSale::class)
        ->assertSeeInOrder(['Fecha de la venta', 'Cliente', 'Vendedor', 'Productos', 'Pago', 'Plan de financiación', 'Vencimiento de la primera cuota', 'Anticipo']);
});

it('crea un producto nuevo desde el selector de productos de la venta', function () {
    $this->actingAs(User::factory()->admin()->create());
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(CreateSale::class)
        ->fillForm(['items' => [['product_id' => null, 'quantity' => 1, 'unit_cost' => null, 'unit_margin' => 0]]])
        ->assertActionVisible(TestAction::make('createOption')->schemaComponent('items.0.product_id', schema: 'form'))
        ->callAction(
            TestAction::make('createOption')->schemaComponent('items.0.product_id', schema: 'form'),
            data: ['name' => 'Heladera nueva', 'brand' => 'Marca X', 'active' => true],
        )
        ->assertHasNoFormErrors()
        ->assertSchemaStateSet(['items.0.product_id' => Product::where('name', 'Heladera nueva')->value('id')]);

    $undoRepeaterFake();

    expect(Product::where('name', 'Heladera nueva')->where('brand', 'Marca X')->exists())->toBeTrue();
});

it('el vendedor no ve la opción de crear productos desde la venta', function () {
    $this->actingAs(User::factory()->seller()->create());
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(CreateSale::class)
        ->fillForm(['items' => [['product_id' => null, 'quantity' => 1, 'unit_cost' => null, 'unit_margin' => 0]]])
        ->assertActionHidden(TestAction::make('createOption')->schemaComponent('items.0.product_id', schema: 'form'));

    $undoRepeaterFake();
});
