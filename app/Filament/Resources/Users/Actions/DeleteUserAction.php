<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;

/**
 * Borra un usuario. Si tiene ventas, cobros u operaciones de compra a su nombre no se borra
 * (se perdería el historial): se avisa que hay que desactivarlo. Nadie puede borrarse a sí mismo.
 */
class DeleteUserAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Borrar')
            ->authorize('delete')
            ->modalHeading(fn (User $record): string => "Borrar a {$record->name}")
            ->modalDescription('El usuario se elimina definitivamente. Si ya tiene ventas, cobros u operaciones de compra registradas no se puede borrar: en ese caso desactivalo.')
            ->before(function (DeleteUserAction $action, User $record): void {
                if ($record->hasRegisteredOperations()) {
                    Notification::make()->danger()->title('No se puede borrar el usuario')
                        ->body("{$record->name} tiene ventas, cobros u operaciones de compra registradas. Desactivalo para que no pueda ingresar.")
                        ->send();

                    $action->cancel();
                }
            });
    }
}
