<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('document_number')->label('DNI / CUIT')->searchable(),
                TextColumn::make('phone')->label('Teléfono')->searchable(),
                TextColumn::make('city')->label('Localidad')->searchable()->toggleable(),
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
