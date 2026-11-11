<?php

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('deja ver las categorías al vendedor pero solo el admin las administra', function () {
    $this->actingAs(User::factory()->seller()->create());
    expect(CategoryResource::canViewAny())->toBeTrue()
        ->and(CategoryResource::canCreate())->toBeFalse();

    $this->actingAs(User::factory()->admin()->create());
    expect(CategoryResource::canViewAny())->toBeTrue()
        ->and(CategoryResource::canCreate())->toBeTrue();
});

it('crea una categoría y valida que el nombre sea único', function () {
    $this->actingAs(User::factory()->admin()->create());
    Category::factory()->create(['name' => 'Línea blanca']);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'Línea blanca'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'Climatización'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::where('name', 'Climatización')->exists())->toBeTrue();
});

it('crea un producto con categoría', function () {
    $this->actingAs(User::factory()->admin()->create());
    $category = Category::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Heladera 300L', 'sku' => 'HE-300', 'category_id' => $category->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('sku', 'HE-300')->firstOrFail();
    expect($product->category->is($category))->toBeTrue();
});

it('deja el producto sin categoría al borrar la categoría', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $category->delete();

    expect($product->fresh()->category_id)->toBeNull();
});

it('respeta el código cargado a mano', function () {
    $product = Product::factory()->create(['sku' => 'TV-055']);

    expect($product->fresh()->sku)->toBe('TV-055');
});

it('agrega un sufijo si el código automático ya fue cargado a mano', function () {
    $nextId = (int) Product::max('id') + 2;
    $taken = 'PRD-'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    Product::factory()->create(['sku' => $taken]);

    $product = Product::factory()->create(['sku' => null]);

    expect($product->id)->toBe($nextId)
        ->and($product->fresh()->sku)->toBe($taken.'-2');
});

it('vuelve a generar el código si se borra al editar', function () {
    $this->actingAs(User::factory()->admin()->create());
    $product = Product::factory()->create(['sku' => 'HE-360']);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['sku' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->sku)->toBe('PRD-'.str_pad((string) $product->id, 6, '0', STR_PAD_LEFT));
});
