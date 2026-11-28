<?php

use App\Support\WhatsApp;

it('pasa los celulares argentinos al formato de WhatsApp', function (string $phone, string $expected) {
    expect(WhatsApp::phone($phone))->toBe($expected);
})->with([
    'característica de 2 dígitos' => ['11 5555-1234', '5491155551234'],
    'con 0 y 15' => ['011 15 5555-1234', '5491155551234'],
    'característica de 3 dígitos con 15' => ['0351 15-555-1234', '5493515551234'],
    'característica de 4 dígitos con 15' => ['(02954) 15 44-1234', '5492954441234'],
    'sin 15' => ['351 5551234', '5493515551234'],
    'internacional con 9' => ['+54 9 351 555-1234', '5493515551234'],
    'internacional sin 9' => ['+54 351 555 1234', '5493515551234'],
    'internacional con 15' => ['+54 351 15 555 1234', '5493515551234'],
    'con 00' => ['00 54 9 11 5555 1234', '5491155551234'],
    'ya normalizado' => ['5491155551234', '5491155551234'],
]);

it('deja como están los números de otros países', function () {
    expect(WhatsApp::phone('+598 99 123 456'))->toBe('59899123456')
        ->and(WhatsApp::phone('0034 612 345 678'))->toBe('34612345678');
});

it('no adivina los números incompletos', function (?string $phone) {
    expect(WhatsApp::phone($phone))->toBeNull();
})->with([
    'vacío' => [null],
    'sin dígitos' => ['no tiene'],
    'sin característica' => ['15 5555-1234'],
    'número local corto' => ['4555-1234'],
    'demasiados dígitos' => ['0351 15 555-12345'],
]);

it('arma el enlace con el mensaje codificado', function () {
    expect(WhatsApp::url('11 5555-1234', "Hola Juan,\nte mando el link: https://a.com/x?y=1&z=2"))
        ->toBe('https://wa.me/5491155551234?text=Hola%20Juan%2C%0Ate%20mando%20el%20link%3A%20https%3A%2F%2Fa.com%2Fx%3Fy%3D1%26z%3D2');
});

it('sin un teléfono válido el enlace deja elegir el contacto en WhatsApp', function () {
    expect(WhatsApp::url(null, 'Hola'))->toBe('https://wa.me/?text=Hola');
});
