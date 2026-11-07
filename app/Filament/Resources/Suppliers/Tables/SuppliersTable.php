<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('cuit')->label('CUIT')->searchable(),
                TextColumn::make('phone')->label('Teléfono'),
                TextColumn::make('email')->label('Email')->toggleable(),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }
}
