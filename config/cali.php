<?php

return [

    // Versión vigente (semántica). Debe coincidir con el CHANGELOG.md y con el tag de git.
    'version' => '0.9.0',

    // Días que sigue abriendo el enlace al comprobante que se le manda al cliente por WhatsApp.
    'shared_receipt_days' => (int) env('SHARED_RECEIPT_DAYS', 30),

    // Usuario administrador inicial (lo crea DatabaseSeeder).
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL', 'admin@cali.localhost'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
