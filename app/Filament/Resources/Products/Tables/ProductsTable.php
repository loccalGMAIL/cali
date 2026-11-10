<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Supplier;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('supplier'))
            ->columns([
                TextColumn::make('sku')->label('Código')->searchable()->sortable(),
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('brand')->label('Marca')->searchable()->toggleable(),
                TextColumn::make('supplier.name')->label('Proveedor habitual')->placeholder('—')->toggleable(),
                TextColumn::make('last_cost')->label('Último costo')->money('ARS', locale: 'es_AR')->sortable(),
                TextColumn::make('freight')->label('Flete')->money('ARS', locale: 'es_AR')->sortable()->toggleable(),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('supplier_id')->label('Proveedor habitual')
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }
}
