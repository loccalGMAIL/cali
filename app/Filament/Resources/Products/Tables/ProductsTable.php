<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\PriceMode;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->label('Código')->searchable()->sortable(),
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('brand')->label('Marca')->searchable()->toggleable(),
                TextColumn::make('stock')->label('Stock')->numeric()->sortable()
                    ->color(fn (Product $record): ?string => $record->isLowStock() ? 'danger' : null)
                    ->weight(fn (Product $record) => $record->isLowStock() ? 'bold' : null),
                TextColumn::make('min_stock')->label('Mínimo')->numeric()->toggleable(),
                TextColumn::make('last_cost')->label('Último costo')->money('ARS', locale: 'es_AR')->sortable(),
                TextColumn::make('margin_percent')->label('Margen')->suffix('%')->numeric(2, ',', '.')->sortable(),
                TextColumn::make('price_mode')->label('Modo')->badge(),
                TextColumn::make('sale_price')->label('Precio')->money('ARS', locale: 'es_AR')->sortable(),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('low_stock')->label('Stock bajo')
                    ->query(fn (Builder $query): Builder => $query->whereColumn('stock', '<', 'min_stock')),
                SelectFilter::make('price_mode')->label('Modo de precio')->options(PriceMode::class),
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }
}
