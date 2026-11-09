<?php

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\FinancingPlans\FinancingPlanResource;
use App\Filament\Resources\FinancingPlans\Pages\CreateFinancingPlan;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('permite al admin ver todos los listados', function () {
    $this->actingAs(User::factory()->admin()->create());

    foreach ([CustomerResource::class, SupplierResource::class, ProductResource::class, FinancingPlanResource::class, UserResource::class] as $resource) {
        expect($resource::canViewAny())->toBeTrue();
    }
});

it('limita al vendedor a clientes y productos', function () {
    $this->actingAs(User::factory()->seller()->create());

    expect(CustomerResource::canViewAny())->toBeTrue()
        ->and(ProductResource::canViewAny())->toBeTrue()
        ->and(ProductResource::canCreate())->toBeFalse()
        ->and(SupplierResource::canViewAny())->toBeFalse()
        ->and(FinancingPlanResource::canViewAny())->toBeFalse()
        ->and(UserResource::canViewAny())->toBeFalse();
});

it('crea un cliente y valida el documento único', function () {
    $this->actingAs(User::factory()->admin()->create());
    Customer::factory()->create(['document_number' => '30111222']);

    Livewire::test(CreateCustomer::class)
        ->fillForm(['name' => 'Ana Pérez', 'document_number' => '30111222'])
        ->call('create')
        ->assertHasFormErrors(['document_number' => 'unique']);

    Livewire::test(CreateCustomer::class)
        ->fillForm(['name' => 'Ana Pérez', 'document_number' => '30999888'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Customer::where('document_number', '30999888')->exists())->toBeTrue();
});

it('crea un proveedor', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Distribuidora Sur', 'cuit' => '30-12345678-9'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Supplier::where('name', 'Distribuidora Sur')->exists())->toBeTrue();
});

it('crea un producto con su proveedor habitual y sin costo cargado a mano', function () {
    $this->actingAs(User::factory()->admin()->create());
    $supplier = Supplier::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'sku' => 'HE-0001',
            'name' => 'Heladera 300L',
            'supplier_id' => $supplier->id,
            'last_cost' => 500000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('sku', 'HE-0001')->firstOrFail();

    expect($product->supplier_id)->toBe($supplier->id)
        ->and($product->last_cost)->toBe('0.00'); // el costo lo fija la factura de compra
});

it('crea un plan de financiación', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateFinancingPlan::class)
        ->fillForm(['name' => '12 cuotas', 'installments_count' => 12])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FinancingPlan::where('installments_count', 12)->exists())->toBeTrue();
});

it('crea un vendedor con contraseña hasheada', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Vendedor Uno',
            'email' => 'vendedor@example.com',
            'password' => 'secreto123',
            'role' => 'seller',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'vendedor@example.com')->firstOrFail();
    expect($user->isSeller())->toBeTrue()
        ->and($user->password)->not->toBe('secreto123')
        ->and(Hash::check('secreto123', $user->password))->toBeTrue();
});

it('crea un producto sin código ni proveedor habitual', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Microondas',
            'sku' => null,
            'supplier_id' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('name', 'Microondas')->firstOrFail();
    expect($product->sku)->toBeNull()
        ->and($product->supplier_id)->toBeNull();

    Product::factory()->create(['sku' => null]);
    expect(Product::whereNull('sku')->count())->toBe(2);
});
