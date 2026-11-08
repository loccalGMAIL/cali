<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Purchase;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Factura')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('supplier.name')->label('Proveedor'),
                        TextEntry::make('invoice_number')->label('Número de factura'),
                        TextEntry::make('date')->label('Fecha')->date('d/m/Y'),
                        TextEntry::make('total')->label('Total')->money('ARS', locale: 'es_AR')->weight('bold'),
                        TextEntry::make('user.name')->label('Cargada por'),
                        TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Anulación')
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (?Purchase $record): bool => $record?->isCancelled() ?? false)
                    ->schema([
                        TextEntry::make('cancelled_at')->label('Anulada el')->dateTime('d/m/Y H:i'),
                        TextEntry::make('canceller.name')->label('Anulada por'),
                        TextEntry::make('cancellation_reason')->label('Motivo'),
                    ]),
                Section::make('Ítems')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')->label('')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('product.name')->label('Producto'),
                                TextEntry::make('quantity')->label('Cantidad'),
                                TextEntry::make('unit_cost')->label('Costo unitario')->money('ARS', locale: 'es_AR'),
                                TextEntry::make('subtotal')->label('Subtotal')->money('ARS', locale: 'es_AR'),
                            ]),
                    ]),
            ]);
    }
}
