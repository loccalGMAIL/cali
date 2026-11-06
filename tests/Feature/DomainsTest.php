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
    $this->get(landingUrl())->assertOk()->assertSee('Ingresar');
});

it('sirve el login del panel en el host del panel', function () {
    $this->get(panelUrl('/login'))->assertOk();
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
