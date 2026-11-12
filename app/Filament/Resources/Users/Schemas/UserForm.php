<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Usuario')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                        TextInput::make('email')->label('Email')->email()->required()
                            ->unique(ignoreRecord: true)->maxLength(255),
                        TextInput::make('password')->label('Contraseña')->password()->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(8)
                            ->helperText('Al editar, dejala vacía para no cambiarla.'),
                        Select::make('role')->label('Rol')->options(UserRole::class)
                            ->default(UserRole::Seller)->required(),
                        Toggle::make('active')->label('Activo')->default(true),
                        Toggle::make('is_default_seller')->label('Vendedor por defecto')->default(false),
                    ]),
            ]);
    }
}
