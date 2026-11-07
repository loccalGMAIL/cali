<?php

namespace App\Filament\Resources\FinancingPlans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FinancingPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('installments_count')->label('Cuotas')->numeric()->sortable(),
                TextColumn::make('surcharge_percent')->label('Recargo')->suffix('%')->numeric(2, ',', '.')->sortable(),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->defaultSort('installments_count')
            ->filters([
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }
}
