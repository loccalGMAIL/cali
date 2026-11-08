<?php

use App\Models\User;

function landingUrl(string $path = '/'): string
{
    return 'http://'.config('domains.landing').$path;
}

function panelUrl(string $path = '/'): string
{
    return 'http://'.config('domains.panel').$path;
}

it('sirve la landing en el host de la landing', function () {
    $this->get(landingUrl())
        ->assertOk()
        ->assertSee('Cali — Electrodomésticos para tu hogar')
        ->assertSee('Ingresar al sistema')
        ->assertSee('href="'.panelUrl('').'"', false)
        ->assertSee('Cocina y refrigeración')
        ->assertSee('Pequeños electrodomésticos')
        ->assertSee('¿Puedo comprar desde esta página?');
});

it('conserva el esquema y el puerto al enlazar al dominio configurado del sistema', function () {
    config(['domains.panel' => 'sistema.ejemplo.com']);

    $this->get('https://'.config('domains.landing').':8443/')
        ->assertOk()
        ->assertSee('href="https://sistema.ejemplo.com:8443"', false);
});

it('sirve el login del panel en el host del panel', function () {
    $this->get(panelUrl('/login'))
        ->assertOk()
        ->assertSee('aria-label="Cali"', false)
        ->assertSee('cali-login-logo');
});

it('devuelve 404 en un host desconocido', function () {
    $this->get('http://otro.ejemplo.com/')->assertNotFound();
    $this->get('http://otro.ejemplo.com/login')->assertNotFound();
});

it('no sirve la landing en el host del panel', function () {
    $this->get(panelUrl('/'))->assertRedirect(panelUrl('/login'));
});

it('permite entrar al panel solo a usuarios activos', function () {
    $this->actingAs(User::factory()->admin()->create())->get(panelUrl('/'))->assertOk();
    $this->actingAs(User::factory()->inactive()->create())->get(panelUrl('/'))->assertForbidden();
});
