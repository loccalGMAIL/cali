<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('borra un usuario sin operaciones desde la tabla', function () {
    $seller = User::factory()->seller()->create();

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('delete')->table($seller))
        ->callAction(TestAction::make('delete')->table($seller));

    expect(User::find($seller->id))->toBeNull();
});

it('borra un usuario desde su edición', function () {
    $seller = User::factory()->seller()->create();

    Livewire::test(EditUser::class, ['record' => $seller->getKey()])
        ->callAction('delete');

    expect(User::find($seller->id))->toBeNull();
});

it('no borra a un usuario con ventas y avisa que hay que desactivarlo', function () {
    $seller = User::factory()->seller()->create();
    Sale::factory()->create(['seller_id' => $seller->id]);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('delete')->table($seller))
        ->assertNotified('No se puede borrar el usuario');

    expect(User::find($seller->id))->not->toBeNull();
});

it('no borra a un usuario con cobros registrados', function () {
    $seller = User::factory()->seller()->create();
    Payment::factory()->create(['collected_by' => $seller->id]);

    expect($seller->hasRegisteredOperations())->toBeTrue();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('delete')->table($seller))
        ->assertNotified('No se puede borrar el usuario');

    expect(User::find($seller->id))->not->toBeNull();
});

it('no permite que un usuario se borre a sí mismo', function () {
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($this->admin));
});

it('un vendedor no puede borrar usuarios', function () {
    $other = User::factory()->seller()->create();

    expect(User::factory()->seller()->create()->can('delete', $other))->toBeFalse();
});

it('marca un vendedor por defecto desde el ícono de la tabla y desmarca al anterior', function () {
    $previous = User::factory()->seller()->create(['is_default_seller' => true]);
    $seller = User::factory()->seller()->create();

    Livewire::test(ListUsers::class)
        ->callTableColumnAction('is_default_seller', $seller)
        ->assertNotified();

    expect($seller->fresh()->is_default_seller)->toBeTrue()
        ->and($previous->fresh()->is_default_seller)->toBeFalse()
        ->and(User::defaultSeller()?->is($seller))->toBeTrue();
});

it('no marca como vendedor por defecto a un usuario inactivo desde el ícono', function () {
    $inactive = User::factory()->seller()->inactive()->create();

    Livewire::test(ListUsers::class)->callTableColumnAction('is_default_seller', $inactive);

    expect($inactive->fresh()->is_default_seller)->toBeFalse();
});

it('al crear un usuario como vendedor por defecto deja uno solo marcado', function () {
    $previous = User::factory()->seller()->create(['is_default_seller' => true]);

    $new = User::factory()->seller()->create(['is_default_seller' => true]);

    expect(User::where('is_default_seller', true)->pluck('id')->all())->toBe([$new->id])
        ->and($previous->fresh()->is_default_seller)->toBeFalse();
});
