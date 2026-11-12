<?php

return [

    // Versión vigente (semántica). Debe coincidir con el CHANGELOG.md y con el tag de git.
    'version' => '0.6.1',

    // Usuario administrador inicial (lo crea DatabaseSeeder).
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL', 'admin@cali.localhost'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
