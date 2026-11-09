<?php

namespace App\Filament\Resources\FinancingPlans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FinancingPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan de financiación')
                    ->description('La cuota es (costo + margen − anticipo) ÷ cantidad de cuotas. Un plan de 1 cuota funciona como venta de contado.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                        TextInput::make('installments_count')->label('Cantidad de cuotas')
                            ->required()->integer()->minValue(1)->default(1),
                        Toggle::make('active')->label('Activo')->default(true),
                    ]),
            ]);
    }
}
