<?php

use App\Enums\PriceMode;
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

it('crea un producto en modo automático calculando el precio', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'sku' => 'HE-0001',
            'name' => 'Heladera 300L',
            'last_cost' => 500000,
            'margin_percent' => 40,
            'price_mode' => PriceMode::Auto->value,
            'min_stock' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('sku', 'HE-0001')->firstOrFail()->sale_price)->toBe('700000.00');
});

it('exige precio en modo manual', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'sku' => 'LV-0001',
            'name' => 'Lavarropas',
            'last_cost' => 300000,
            'margin_percent' => 30,
            'price_mode' => PriceMode::Manual->value,
            'sale_price' => 450000,
            'min_stock' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('sku', 'LV-0001')->firstOrFail()->sale_price)->toBe('450000.00');
});

it('crea un plan de financiación', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateFinancingPlan::class)
        ->fillForm(['name' => '12 cuotas', 'installments_count' => 12, 'surcharge_percent' => 60])
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
