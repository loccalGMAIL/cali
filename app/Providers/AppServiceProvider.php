<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use Filament\Tables\Columns\Column;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, [LogAuthenticationActivity::class, 'login']);
        Event::listen(Logout::class, [LogAuthenticationActivity::class, 'logout']);
        Event::listen(Failed::class, [LogAuthenticationActivity::class, 'failed']);

        // Todas las columnas de las tablas se pueden ocultar desde el administrador de columnas.
        Column::configureUsing(fn (Column $column): Column => $column->toggleable());
    }
}
