<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Orden de compra')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('id')->label('Número')->formatStateUsing(fn ($state): string => PurchaseOrder::numberFor($state)),
                        TextEntry::make('supplier.name')->label('Proveedor'),
                        TextEntry::make('date')->label('Fecha')->date('d/m/Y'),
                        TextEntry::make('status')->label('Estado')->badge(),
                        TextEntry::make('user.name')->label('Armada por'),
                        TextEntry::make('issued_at')->label('Emitida el')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('purchase.invoice_number')->label('Factura')->placeholder('—')
                            ->url(fn (PurchaseOrder $record): ?string => $record->purchase === null ? null : PurchaseResource::getUrl('view', ['record' => $record->purchase])),
                        TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Anulación')
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (?PurchaseOrder $record): bool => $record?->isCancelled() ?? false)
                    ->schema([
                        TextEntry::make('cancelled_at')->label('Anulada el')->dateTime('d/m/Y H:i'),
                        TextEntry::make('canceller.name')->label('Anulada por'),
                        TextEntry::make('cancellation_reason')->label('Motivo'),
                    ]),
                Section::make('Productos')
                    ->description('Lo que se le pide al proveedor, agrupado por producto. El costo de referencia es el cargado en las ventas.')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('lines')->label('')->columns(4)
                            ->state(fn (PurchaseOrder $record): array => $record->linesByProduct()
                                ->map(fn (array $line): array => [
                                    'product' => $line['product']->name,
                                    'quantity' => $line['quantity'],
                                    'received_quantity' => $line['received_quantity'],
                                    'reference_cost' => $line['reference_cost'],
                                ])
                                ->all())
                            ->schema([
                                TextEntry::make('product')->label('Producto'),
                                TextEntry::make('quantity')->label('Cantidad pedida'),
                                TextEntry::make('reference_cost')->label('Costo de referencia')->money('ARS', locale: 'es_AR'),
                                TextEntry::make('received_quantity')->label('Recibido')->placeholder('—'),
                            ]),
                    ]),
                Section::make('Detalle por venta')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')->label('')->columns(4)
                            ->schema([
                                TextEntry::make('product.name')->label('Producto'),
                                TextEntry::make('sale')->label('Venta')
                                    ->state(fn (PurchaseOrderItem $record): string => "{$record->saleItem->sale->number()} · {$record->saleItem->sale->customer->name}".($record->saleItem->sale->isCancelled() ? ' (anulada)' : ''))
                                    ->color(fn (PurchaseOrderItem $record): ?string => $record->saleItem->sale->isCancelled() ? 'danger' : null)
                                    ->url(fn (PurchaseOrderItem $record): string => SaleResource::getUrl('view', ['record' => $record->saleItem->sale_id])),
                                TextEntry::make('quantity')->label('Cantidad'),
                                TextEntry::make('received_quantity')->label('Recibido')->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
