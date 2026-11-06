<?php

/*
|--------------------------------------------------------------------------
| Dominios de la aplicación
|--------------------------------------------------------------------------
|
| Ningún host va hardcodeado: todo sale de estas variables de entorno.
| Cuando exista el dominio definitivo solo cambian el .env y el DNS.
|
*/

return [

    // Sitio público (landing).
    'landing' => env('LANDING_DOMAIN', 'cali.localhost'),

    // Sistema (panel Filament).
    'panel' => env('PANEL_DOMAIN', 'app.cali.localhost'),

    // Reservados para etapas futuras (no implementados):
    // 'seller' => env('SELLER_DOMAIN'),     // app del vendedor (PWA)
    // 'customers' => env('CUSTOMERS_DOMAIN'), // portal de clientes

];
