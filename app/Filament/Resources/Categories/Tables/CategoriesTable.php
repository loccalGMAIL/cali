<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('products_count')->label('Productos')->counts('products')->sortable(),
                IconColumn::make('active')->label('Activa')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('active')->label('Activa'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }
}
