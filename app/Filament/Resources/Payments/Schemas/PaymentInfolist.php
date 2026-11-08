<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cobro')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('customer.name')->label('Cliente'),
                        TextEntry::make('date')->label('Fecha')->date('d/m/Y'),
                        TextEntry::make('amount')->label('Monto')->money('ARS', locale: 'es_AR')->weight('bold'),
                        TextEntry::make('method')->label('Medio de pago')->badge(),
                        TextEntry::make('collector.name')->label('Cobrado por'),
                        TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpan(3),
                    ]),
                Section::make('Imputación')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('installments')->label('')->columns(4)
                            ->placeholder('Este cobro no se imputó a cuotas (por ejemplo, un anticipo).')
                            ->schema([
                                TextEntry::make('sale_id')->label('Venta')->prefix('#'),
                                TextEntry::make('number')->label('Cuota'),
                                TextEntry::make('due_date')->label('Vence')->date('d/m/Y'),
                                TextEntry::make('pivot.amount')->label('Monto imputado')->money('ARS', locale: 'es_AR'),
                            ]),
                    ]),
            ]);
    }
}
