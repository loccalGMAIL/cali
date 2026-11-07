<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del proveedor')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                        TextInput::make('cuit')->label('CUIT')->maxLength(20),
                        TextInput::make('phone')->label('Teléfono')->tel()->maxLength(50),
                        TextInput::make('email')->label('Email')->email()->maxLength(255),
                        TextInput::make('address')->label('Dirección')->maxLength(255)->columnSpanFull(),
                        Toggle::make('active')->label('Activo')->default(true),
                    ]),
                Section::make('Notas')
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('notes')->label('Notas')->hiddenLabel(),
                    ]),
            ]);
    }
}
