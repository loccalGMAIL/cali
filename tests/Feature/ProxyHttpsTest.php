<?php

use App\Models\User;

/**
 * En producción el SSL termina en Traefik y la aplicación recibe http con cabeceras X-Forwarded-*.
 * Estos tests simulan esa situación.
 */
function behindTls(): array
{
    return ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'];
}

it('detrás del proxy con SSL, la landing enlaza al panel con https y sin puerto', function () {
    $this->withHeaders(behindTls())
        ->get('http://'.config('domains.landing').'/')
        ->assertOk()
        ->assertSee('https://'.config('domains.panel'), false)
        ->assertDontSee('http://'.config('domains.panel'), false);
});

it('sin proxy, la landing enlaza al panel con el esquema de la petición', function () {
    $this->get('http://'.config('domains.landing').'/')
        ->assertOk()
        ->assertSee('http://'.config('domains.panel'), false);
});

it('detrás del proxy con SSL, los assets del panel salen con https', function () {
    $response = $this->withHeaders(behindTls())->get('http://'.config('domains.panel').'/login')->assertOk();

    $response->assertSee('https://'.config('domains.panel').'/build/assets', false)
        ->assertDontSee('http://'.config('domains.panel').'/build/assets', false);
});

it('detrás del proxy con SSL, las redirecciones del panel usan https', function () {
    $this->withHeaders(behindTls())
        ->get('http://'.config('domains.panel').'/')
        ->assertRedirect('https://'.config('domains.panel').'/login');
});

it('el panel sigue funcionando para un usuario con sesión detrás del proxy', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->withHeaders(behindTls())
        ->get('http://'.config('domains.panel').'/')
        ->assertOk();
});

it('un host que no es ni la landing ni el panel sigue devolviendo 404 detrás del proxy', function () {
    $this->withHeaders(behindTls())->get('http://otro.ejemplo.com/')->assertNotFound();
});
