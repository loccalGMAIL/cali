<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Botón "Anular" compartido por cobros, ventas y compras: pide el motivo, confirma y delega
 * en la acción de negocio correspondiente. Un registro anulado no se borra, queda en el historial.
 */
abstract class CancelRecordAction extends Action
{
    /**
     * Texto del título del modal, por ejemplo "Anular la venta #12".
     */
    abstract protected function modalTitle(Model $record): string;

    /**
     * Qué se revierte, explicado para quien confirma.
     */
    abstract protected function modalExplanation(): string;

    /**
     * Si el registro admite anulación (no está anulado y el usuario tiene permiso).
     */
    abstract protected function canCancel(Model $record): bool;

    /**
     * Ejecuta la anulación de negocio.
     *
     * @throws ValidationException
     */
    abstract protected function performCancellation(Model $record, string $reason): void;

    public static function getDefaultName(): ?string
    {
        return 'cancel';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Anular')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Model $record): string => $this->modalTitle($record))
            ->modalDescription($this->modalExplanation())
            ->modalSubmitActionLabel('Anular')
            ->schema([
                Textarea::make('reason')->label('Motivo de la anulación')->required()->maxLength(500),
            ])
            ->visible(fn (Model $record): bool => $this->canCancel($record))
            ->action(function (Model $record, array $data): void {
                try {
                    $this->performCancellation($record, $data['reason']);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('No se pudo anular')
                        ->body(collect($exception->errors())->flatten()->implode(' '))->send();

                    return;
                }

                Notification::make()->success()->title('Anulación registrada')->send();
            });
    }
}
