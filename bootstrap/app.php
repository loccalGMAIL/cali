<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En producción el SSL termina en el proxy (Traefik): sin confiar en sus cabeceras X-Forwarded-*
        // la aplicación creería que es http y generaría enlaces, redirecciones y assets con http://.
        // TRUSTED_PROXIES acepta `*` (por defecto) o una lista de IPs/CIDR separadas por coma.
        $proxies = (string) env('TRUSTED_PROXIES', '*');

        $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
