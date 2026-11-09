<?php

use App\Actions\CancelPayment;
use App\Actions\CancelPurchase;
use App\Actions\CancelSale;
use App\Actions\CreatePurchase;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Enums\ActivityEvent;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ActivityDetails;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'secreto-123']);
    ActivityLog::query()->delete(); // arranca limpio: solo interesa lo que pase en cada test
});

/**
 * Venta de costo 600 + margen 600 = 1200 en 3 cuotas de 400.
 */
function logSale(Customer $customer, User $user): Sale
{
    $plan = FinancingPlan::factory()->create(['installments_count' => 3]);
    $product = Product::factory()->create(['last_cost' => 600]);

    return app(CreateSale::class)->handle([
        'customer_id' => $customer->id,
        'seller_id' => $user->id,
        'financing_plan_id' => $plan->id,
        'date' => today()->toDateString(),
        'first_due_date' => today()->addMonth()->toDateString(),
        'down_payment' => 0,
    ], [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 600]], $user);
}

it('registra el ingreso de un usuario', function () {
    Auth::attempt(['email' => 'admin@example.com', 'password' => 'secreto-123']);

    $log = ActivityLog::where('event', ActivityEvent::Login)->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->description)->toContain($this->admin->name)
        ->and($log->subject_id)->toBe($this->admin->id);
});

it('registra la salida de un usuario', function () {
    $this->actingAs($this->admin);
    Auth::logout();

    $log = ActivityLog::where('event', ActivityEvent::Logout)->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id);
});

it('registra un intento de ingreso fallido con el email y sin la contraseña', function () {
    Auth::attempt(['email' => 'admin@example.com', 'password' => 'clave-equivocada']);

    $log = ActivityLog::where('event', ActivityEvent::LoginFailed)->firstOrFail();

    expect($log->user_id)->toBeNull()
        ->and($log->properties)->toBe(['email' => 'admin@example.com'])
        ->and($log->description)->toContain('admin@example.com')
        ->and(json_encode($log->toArray()))->not->toContain('clave-equivocada')->not->toContain('secreto-123');
});

it('registra el alta de un maestro con quién lo hizo', function () {
    $this->actingAs($this->admin);

    $customer = Customer::factory()->create(['name' => 'Ana Pérez']);

    $log = ActivityLog::where('event', ActivityEvent::Created)->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->description)->toBe('Creó el cliente «Ana Pérez»')
        ->and($log->subject->is($customer))->toBeTrue()
        ->and($log->properties['attributes']['name'])->toBe('Ana Pérez');
});

it('registra qué campos cambiaron con su valor anterior y el nuevo', function () {
    $customer = Customer::factory()->create(['name' => 'Ana', 'phone' => '111']);
    ActivityLog::query()->delete();
    $this->actingAs($this->admin);

    $customer->update(['phone' => '222']);

    $log = ActivityLog::where('event', ActivityEvent::Updated)->firstOrFail();

    expect($log->description)->toBe('Modificó el cliente «Ana»')
        ->and($log->properties['changes'])->toBe(['phone' => ['old' => '111', 'new' => '222']]);
});

it('no deja registro si una modificación no cambia nada', function () {
    $customer = Customer::factory()->create();
    ActivityLog::query()->delete();

    $customer->update(['name' => $customer->name]);

    expect(ActivityLog::count())->toBe(0);
});

it('registra la baja de un maestro', function () {
    $customer = Customer::factory()->create(['name' => 'Ana']);
    ActivityLog::query()->delete();
    $this->actingAs($this->admin);

    $customer->delete();

    expect(ActivityLog::where('event', ActivityEvent::Deleted)->firstOrFail()->description)->toBe('Eliminó el cliente «Ana»');
});

it('nunca guarda contraseñas: solo deja constancia de que cambiaron', function () {
    $this->actingAs($this->admin);
    $user = User::factory()->seller()->create(['password' => 'otra-clave-secreta']);

    $created = ActivityLog::where('subject_id', $user->id)->where('event', ActivityEvent::Created)->firstOrFail();
    expect($created->properties['attributes']['password'])->toBe('[oculto]');

    ActivityLog::query()->delete();
    $user->update(['password' => 'nueva-clave-secreta']);

    $updated = ActivityLog::where('event', ActivityEvent::Updated)->firstOrFail();

    expect($updated->properties['changes']['password'])->toBe(['old' => '[oculto]', 'new' => '[oculto]'])
        ->and(json_encode(ActivityLog::all()->toArray()))->not->toContain('otra-clave-secreta')->not->toContain('nueva-clave-secreta');
});

it('no registra los cambios del último costo, que vienen de las facturas', function () {
    $product = Product::factory()->create(['last_cost' => 500]);
    ActivityLog::query()->delete();

    $product->update(['last_cost' => 600]);

    expect(ActivityLog::count())->toBe(0);

    $product->update(['name' => 'Otro nombre']);

    expect(ActivityLog::count())->toBe(1);
});

it('registra una venta con cliente, total y cuotas', function () {
    $customer = Customer::factory()->create(['name' => 'Ana']);
    ActivityLog::query()->delete();

    $sale = logSale($customer, $this->admin);

    $log = ActivityLog::where('subject_type', $sale->getMorphClass())->where('event', ActivityEvent::Created)->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->description)->toContain("venta #{$sale->id}")->toContain('Ana')->toContain('1.200,00')->toContain('3 cuotas')
        ->and($log->properties['total'])->toBe('1200.00');
});

it('registra un cobro y su anulación con el motivo', function () {
    $customer = Customer::factory()->create(['name' => 'Ana']);
    logSale($customer, $this->admin);
    ActivityLog::query()->delete();

    $payment = app(RegisterPayment::class)->handle(['customer_id' => $customer->id, 'amount' => 400], $this->admin);
    app(CancelPayment::class)->handle($payment, 'Se cargó dos veces', $this->admin);

    $logs = ActivityLog::where('subject_type', $payment->getMorphClass())->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->event)->toBe(ActivityEvent::Created)
        ->and($logs[0]->description)->toContain("cobro #{$payment->id}")->toContain('400,00')->toContain('Ana')
        ->and($logs[1]->event)->toBe(ActivityEvent::Cancelled)
        ->and($logs[1]->description)->toContain('Se cargó dos veces')
        ->and($logs[1]->properties['reason'])->toBe('Se cargó dos veces');
});

it('registra la anulación de una venta', function () {
    $customer = Customer::factory()->create(['name' => 'Ana']);
    $sale = logSale($customer, $this->admin);
    ActivityLog::query()->delete();

    app(CancelSale::class)->handle($sale, 'El cliente se arrepintió', $this->admin);

    $log = ActivityLog::where('event', ActivityEvent::Cancelled)->firstOrFail();

    expect($log->subject->is($sale))->toBeTrue()
        ->and($log->description)->toContain("venta #{$sale->id}")->toContain('El cliente se arrepintió');
});

it('registra la orden de compra, su factura y la anulación', function () {
    $supplier = Supplier::factory()->create(['name' => 'Distribuidora Sur']);
    $product = Product::factory()->create();
    $item = soldItem($this->admin, $product, 2);
    ActivityLog::query()->delete();

    $order = issuedOrder($this->admin, $supplier, [$item]);
    $purchase = app(CreatePurchase::class)->handle([
        'purchase_order_id' => $order->id,
        'date' => today()->toDateString(),
        'invoice_number' => 'A-1',
    ], [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 500]], $this->admin);

    $orderLogs = ActivityLog::where('subject_type', $order->getMorphClass())->orderBy('id')->get();

    expect($orderLogs)->toHaveCount(2)
        ->and($orderLogs[0]->description)->toContain("orden de compra #{$order->id}")->toContain('Distribuidora Sur')->toContain('2 unidades')
        ->and($orderLogs[1]->description)->toContain('Emitió');

    app(CancelPurchase::class)->handle($purchase, 'Factura equivocada', $this->admin);

    $logs = ActivityLog::where('subject_type', $purchase->getMorphClass())->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->description)->toContain("compra #{$purchase->id}")->toContain('Distribuidora Sur')->toContain('1.000,00')
        ->and($logs[1]->event)->toBe(ActivityEvent::Cancelled)
        ->and($logs[1]->description)->toContain('Factura equivocada');
});

it('si la operación se revierte, el registro también', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $item = soldItem($this->admin, $product, 5);
    $order = issuedOrder($this->admin, $supplier, [$item]);
    $purchase = app(CreatePurchase::class)->handle([
        'purchase_order_id' => $order->id,
        'date' => today()->toDateString(),
        'invoice_number' => 'A-1',
    ], [['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 100]], $this->admin);
    issuedOrder($this->admin, $supplier, [$item]); // lo que faltó ya se volvió a pedir: no se puede anular
    ActivityLog::query()->delete();

    expect(fn () => app(CancelPurchase::class)->handle($purchase, 'Error', $this->admin))->toThrow(ValidationException::class);

    expect(ActivityLog::where('event', ActivityEvent::Cancelled)->count())->toBe(0);
});

it('no registra nada dentro de withoutLogging', function () {
    ActivityLogger::withoutLogging(function () {
        Customer::factory()->create();
        Product::factory()->create();
    });

    expect(ActivityLog::count())->toBe(0);

    Customer::factory()->create();

    expect(ActivityLog::count())->toBe(1);
});

it('el admin ve el registro de actividad y nadie puede modificarlo', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);
    $log = app(ActivityLogger::class)->log(ActivityEvent::Created, 'Algo', user: $this->admin);

    expect(ActivityLogResource::canViewAny())->toBeTrue()
        ->and($this->admin->can('create', ActivityLog::class))->toBeFalse()
        ->and($this->admin->can('update', $log))->toBeFalse()
        ->and($this->admin->can('delete', $log))->toBeFalse();

    Livewire::test(ListActivityLogs::class)->assertCanSeeTableRecords([$log]);
    Livewire::test(ViewActivityLog::class, ['record' => $log->getKey()])->assertSuccessful()->assertSee('Algo');
});

it('un vendedor no puede ver el registro de actividad', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->seller()->create());

    expect(ActivityLogResource::canViewAny())->toBeFalse();
});

it('se puede filtrar por usuario, evento y período', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin);
    $other = User::factory()->seller()->create();
    $logger = app(ActivityLogger::class);

    $a = $logger->log(ActivityEvent::Login, 'Ingreso admin', user: $this->admin);
    $b = $logger->log(ActivityEvent::Created, 'Alta del vendedor', user: $other);
    $old = $logger->log(ActivityEvent::Created, 'Alta antigua', user: $this->admin);
    $old->forceFill(['created_at' => now()->subDays(10)])->save();

    Livewire::test(ListActivityLogs::class)
        ->filterTable('user_id', $other->id)
        ->assertCanSeeTableRecords([$b])
        ->assertCanNotSeeTableRecords([$a, $old]);

    Livewire::test(ListActivityLogs::class)
        ->filterTable('event', [ActivityEvent::Login->value])
        ->assertCanSeeTableRecords([$a])
        ->assertCanNotSeeTableRecords([$b, $old]);

    Livewire::test(ListActivityLogs::class)
        ->filterTable('period', ['from' => now()->subDays(2)->toDateString(), 'until' => null])
        ->assertCanSeeTableRecords([$a, $b])
        ->assertCanNotSeeTableRecords([$old]);
});

it('el detalle muestra los cambios antes y después con nombres en español', function () {
    $log = ActivityLog::create([
        'event' => ActivityEvent::Updated,
        'description' => 'Modificó el cliente «Ana»',
        'properties' => ['changes' => ['phone' => ['old' => '111', 'new' => '<b>222</b>']]],
    ]);

    $html = (string) ActivityDetails::render($log);

    expect($html)->toContain('Teléfono')->toContain('111')
        ->and($html)->toContain('&lt;b&gt;222&lt;/b&gt;')   // escapado: nada del registro se interpreta como HTML
        ->and((string) ActivityDetails::render(new ActivityLog))->toContain('Sin detalle');
});

it('el ingreso por la pantalla de login de Filament queda registrado', function () {
    Filament::setCurrentPanel('admin');

    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin@example.com', 'password' => 'secreto-123'])
        ->call('authenticate');

    expect(ActivityLog::where('event', ActivityEvent::Login)->count())->toBe(1)
        ->and(Hash::check('secreto-123', $this->admin->password))->toBeTrue();
});
