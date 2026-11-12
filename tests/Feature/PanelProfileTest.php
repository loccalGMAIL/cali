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

it('ordena el menú en Ventas, Compras, Informes y Configuración con etiquetas en español', function () {
    $this->actingAs(User::factory()->admin()->create());

    $groups = collect(Filament::getNavigation());

    expect($groups->map(fn ($group) => $group->getLabel())->filter()->values()->all())
        ->toBe(['Ventas', 'Compras', 'Informes', 'Configuración']);

    $labels = $groups->flatMap(fn ($group) => $group->getItems())->map(fn ($item) => $item->getLabel());
    expect($labels)->toContain('Planes de financiación')->toContain('Usuarios y vendedores')
        ->toContain('Pendientes de compra')->toContain('Órdenes de compra')->toContain('Facturas de compra')
        ->toContain('Cuotas pendientes por cliente')->toContain('Cobranza del mes')
        ->toContain('Cobros realizados')->toContain('Ventas y ganancia');
});

it('el dashboard no muestra la tarjeta de bienvenida con el botón Salir', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('http://'.config('domains.panel').'/')
        ->assertOk()
        ->assertDontSee('Bienvenid');
});

it('muestra el logo de Cali dentro del panel y en el login', function () {
    $this->get('http://'.config('domains.panel').'/login')->assertOk()->assertSee('cali-login-logo', false);

    $this->actingAs(User::factory()->admin()->create())
        ->get('http://'.config('domains.panel').'/')
        ->assertOk()
        ->assertSee('cali-login-logo', false);
});

it('el login dice Ingrese a su cuenta', function () {
    $this->get('http://'.config('domains.panel').'/login')
        ->assertOk()
        ->assertSee('Ingrese a su cuenta')
        ->assertDontSee('Entre a su cuenta');
});
