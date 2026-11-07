<?php

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('muestra Dashboard en lugar de Escritorio', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('http://'.config('domains.panel').'/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertDontSee('Escritorio');
});

it('permite al usuario editar su nombre', function () {
    $user = User::factory()->seller()->create();
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Nombre Nuevo'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('Nombre Nuevo');
});

it('exige la contraseña actual para cambiar el email', function () {
    $user = User::factory()->seller()->create();
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm(['email' => 'nuevo@example.com'])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    Livewire::test(EditProfile::class)
        ->fillForm(['email' => 'nuevo@example.com', 'currentPassword' => 'password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->email)->toBe('nuevo@example.com');
});
