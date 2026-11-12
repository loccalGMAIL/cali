<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
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

        // Los botones con ícono se muestran solo con el ícono y el texto queda como tooltip. Los que no
        // tienen ícono (guardar, cancelar, confirmar) conservan su texto.
        $icon = fn (Action $action): mixed => $action->getIcon(default: $action->getTable() ? $action->getTableIcon() : null);

        Action::configureUsing(fn (Action $action): Action => $action
            ->hiddenLabel(fn (Action $action): bool => filled($icon($action)))
            ->tooltip(fn (Action $action): ?string => filled($icon($action)) ? $action->getLabel() : null));

        // Las acciones estándar llevan ícono también fuera de las tablas (encabezados de página).
        CreateAction::configureUsing(fn (CreateAction $action): CreateAction => $action->icon(Heroicon::OutlinedPlus));
        EditAction::configureUsing(fn (EditAction $action): EditAction => $action->icon(Heroicon::OutlinedPencilSquare));
        ViewAction::configureUsing(fn (ViewAction $action): ViewAction => $action->icon(Heroicon::OutlinedEye));
        DeleteAction::configureUsing(fn (DeleteAction $action): DeleteAction => $action->icon(Heroicon::OutlinedTrash));

        // Todas las columnas de las tablas se pueden ocultar desde el administrador de columnas.
        Column::configureUsing(fn (Column $column): Column => $column->toggleable());
    }
}
