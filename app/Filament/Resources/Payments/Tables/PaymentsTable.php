<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'collector']))
            ->columns([
                TextColumn::make('id')->label('N°')->sortable(),
                TextColumn::make('date')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('customer.name')->label('Cliente')->searchable(),
                TextColumn::make('amount')->label('Monto')->money('ARS', locale: 'es_AR')->sortable(),
                TextColumn::make('method')->label('Medio')->badge(),
                TextColumn::make('collector.name')->label('Cobrado por')->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('method')->label('Medio de pago')->options(PaymentMethod::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
            ]);
    }
}
