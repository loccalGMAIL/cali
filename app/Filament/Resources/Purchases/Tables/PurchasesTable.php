<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Models\Supplier;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('supplier'))
            ->columns([
                TextColumn::make('date')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('supplier.name')->label('Proveedor')->searchable(),
                TextColumn::make('invoice_number')->label('Factura')->searchable(),
                TextColumn::make('total')->label('Total')->money('ARS', locale: 'es_AR')->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('supplier_id')->label('Proveedor')
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
            ]);
    }
}
