<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Models\Installment;
use App\Models\Sale;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Venta')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('customer.name')->label('Cliente'),
                        TextEntry::make('seller.name')->label('Vendedor'),
                        TextEntry::make('date')->label('Fecha')->date('d/m/Y'),
                        TextEntry::make('status')->label('Estado')->badge(),
                        TextEntry::make('financingPlan.name')->label('Plan')
                            ->formatStateUsing(fn ($state, $record): string => "{$state} ({$record->installments_count} ".($record->installments_count === 1 ? 'cuota' : 'cuotas').')'),
                        TextEntry::make('total_cost')->label('Costo')->money('ARS', locale: 'es_AR'),
                        TextEntry::make('total_freight')->label('Flete')->money('ARS', locale: 'es_AR'),
                        TextEntry::make('margin')->label('Margen')->money('ARS', locale: 'es_AR')
                            ->state(fn (Sale $record): string => bcsub(bcsub($record->total, $record->total_cost, 2), $record->total_freight, 2)),
                        TextEntry::make('total')->label('Total')->money('ARS', locale: 'es_AR')->weight('bold'),
                        TextEntry::make('down_payment')->label('Anticipo')->money('ARS', locale: 'es_AR'),
                        TextEntry::make('financed_amount')->label('Financiado')->money('ARS', locale: 'es_AR'),
                        TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Anulación')
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (?Sale $record): bool => $record?->isCancelled() ?? false)
                    ->schema([
                        TextEntry::make('cancelled_at')->label('Anulada el')->dateTime('d/m/Y H:i'),
                        TextEntry::make('canceller.name')->label('Anulada por'),
                        TextEntry::make('cancellation_reason')->label('Motivo'),
                    ]),
                Section::make('Productos')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')->label('')->columns(8)
                            ->schema([
                                TextEntry::make('product.name')->label('Producto'),
                                TextEntry::make('quantity')->label('Cantidad'),
                                TextEntry::make('unit_cost')->label('Costo unitario')->money('ARS', locale: 'es_AR'),
                                TextEntry::make('unit_freight')->label('Flete unitario')->money('ARS', locale: 'es_AR'),
                                TextEntry::make('unit_margin')->label('Margen unitario')->money('ARS', locale: 'es_AR'),
                                TextEntry::make('unit_price')->label('Precio unitario')->money('ARS', locale: 'es_AR'),
                                TextEntry::make('subtotal')->label('Subtotal')->money('ARS', locale: 'es_AR'),
                                TextEntry::make('pending_quantity')->label('Pendiente de compra')
                                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Pedido' : "{$state} sin pedir")
                                    ->badge()->color(fn (int $state): string => $state === 0 ? 'success' : 'warning'),
                            ]),
                    ]),
                Section::make('Cuotas')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('installments')->label('')->columns(5)
                            ->schema([
                                TextEntry::make('number')->label('Cuota'),
                                TextEntry::make('due_date')->label('Vence')->date('d/m/Y')
                                    ->color(fn (Installment $record): ?string => $record->isOverdue() ? 'danger' : null),
                                TextEntry::make('amount')->label('Monto')->money('ARS', locale: 'es_AR'),
                                TextEntry::make('paid_amount')->label('Pagado')->money('ARS', locale: 'es_AR'),
                                TextEntry::make('status')->label('Estado')->badge(),
                            ]),
                    ]),
            ]);
    }
}
