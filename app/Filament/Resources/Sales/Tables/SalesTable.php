<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\Actions\CancelSaleAction;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'seller', 'items.product']))
            ->columns([
                TextColumn::make('id')->label('N°')->sortable(),
                TextColumn::make('date')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('customer.name')->label('Cliente')->searchable(),
                TextColumn::make('items.product.name')->label('Producto')->listWithLineBreaks()->limitList(2)->expandableLimitedList()->searchable(),
                TextColumn::make('seller.name')->label('Vendedor'),
                TextColumn::make('installments_count')->label('Plan')
                    ->formatStateUsing(fn (Sale $record): string => $record->installments_count === 1 ? '1 cuota' : "{$record->installments_count} cuotas"),
                TextColumn::make('total')->label('Total')->money('ARS', locale: 'es_AR')->sortable(),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(SaleStatus::class),
                SelectFilter::make('seller_id')->label('Vendedor')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                CancelSaleAction::make(),
            ]);
    }
}
