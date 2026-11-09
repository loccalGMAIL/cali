<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Actions\IssuePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Actions\CancelPurchaseOrderAction;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')->label('Emitir')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Emitir la orden de compra')
                ->modalDescription('La orden queda lista para llevarle al proveedor. Se puede seguir editando hasta cargar la factura.')
                ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Draft && (auth()->user()?->can('update', $record) ?? false))
                ->action(function (PurchaseOrder $record): void {
                    try {
                        app(IssuePurchaseOrder::class)->handle($record, auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title('No se pudo emitir')
                            ->body(collect($exception->errors())->flatten()->implode(' '))->send();

                        return;
                    }

                    $this->refreshFormData(['status', 'issued_at']);
                    Notification::make()->success()->title('Orden emitida')->send();
                }),
            Action::make('print')->label('Imprimir')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (PurchaseOrder $record): string => route('filament.admin.purchase-orders.print', $record))
                ->openUrlInNewTab()
                ->visible(fn (PurchaseOrder $record): bool => ! $record->isCancelled()),
            Action::make('receive')->label('Cargar factura')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('success')
                ->url(fn (PurchaseOrder $record): string => PurchaseResource::getUrl('create', ['order' => $record->getKey()]))
                ->visible(fn (PurchaseOrder $record): bool => $record->isOpen() && PurchaseResource::canCreate()),
            EditAction::make()->label('Editar'),
            CancelPurchaseOrderAction::make(),
        ];
    }
}
