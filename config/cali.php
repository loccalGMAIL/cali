<?php

return [

    // Usuario administrador inicial (lo crea DatabaseSeeder).
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL', 'admin@cali.localhost'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
