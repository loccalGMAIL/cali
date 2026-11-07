<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del cliente')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->label('Nombre y apellido')->required()->maxLength(255),
                        TextInput::make('document_number')->label('DNI / CUIT')->unique(ignoreRecord: true)->maxLength(20),
                        TextInput::make('phone')->label('Teléfono')->tel()->maxLength(50),
                        TextInput::make('email')->label('Email')->email()->maxLength(255),
                        TextInput::make('address')->label('Dirección')->maxLength(255),
                        TextInput::make('city')->label('Localidad')->maxLength(255),
                        Textarea::make('notes')->label('Notas')->columnSpanFull(),
                        Toggle::make('active')->label('Activo')->default(true),
                    ]),
            ]);
    }
}
