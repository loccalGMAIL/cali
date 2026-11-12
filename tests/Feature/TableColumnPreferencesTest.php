<?php

use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\TableColumnPreference;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

/**
 * Estado del administrador de columnas de Ventas con el vendedor oculto.
 *
 * @return array<int, array<string, mixed>>
 */
function salesColumnsWithoutSeller(): array
{
    $component = Livewire::test(ListSales::class)->instance();

    return collect($component->tableColumns)
        ->map(fn (array $column): array => $column['name'] === 'seller.name' ? [...$column, 'isToggled' => false] : $column)
        ->all();
}

it('guarda las columnas que el usuario oculta y se las devuelve en otra sesión', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ListSales::class)->call('applyTableColumnManager', salesColumnsWithoutSeller());

    expect(TableColumnPreference::where('user_id', $admin->id)->where('table', ListSales::class)->exists())->toBeTrue();

    session()->flush();

    Livewire::test(ListSales::class)
        ->assertTableColumnVisible('customer.name')
        ->tap(fn ($test) => expect($test->instance()->isTableColumnToggledHidden('customer.name'))->toBeFalse())
        ->tap(fn ($test) => expect($test->instance()->isTableColumnToggledHidden('seller.name'))->toBeTrue());
});

it('no le cambia las columnas a otro usuario', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Livewire::test(ListSales::class)->call('applyTableColumnManager', salesColumnsWithoutSeller());

    $other = User::factory()->admin()->create();
    session()->flush();
    $this->actingAs($other);

    expect(Livewire::test(ListSales::class)->instance()->isTableColumnToggledHidden('seller.name'))->toBeFalse();
});

it('no escribe en la base si el usuario no tocó las columnas', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListSales::class)->assertSuccessful();

    expect(TableColumnPreference::count())->toBe(0);
});

it('muestra el producto vendido en el listado de ventas y permite buscar por él', function () {
    $this->actingAs(User::factory()->admin()->create());
    $heladera = Product::factory()->create(['name' => 'Heladera Gafa']);
    $lavarropas = Product::factory()->create(['name' => 'Lavarropas Drean']);
    $saleA = Sale::factory()->create();
    $saleB = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $saleA->id, 'product_id' => $heladera->id]);
    SaleItem::factory()->create(['sale_id' => $saleB->id, 'product_id' => $lavarropas->id]);

    Livewire::test(ListSales::class)
        ->assertSee('Heladera Gafa')
        ->searchTable('Heladera')
        ->assertCanSeeTableRecords([$saleA])
        ->assertCanNotSeeTableRecords([$saleB]);
});
