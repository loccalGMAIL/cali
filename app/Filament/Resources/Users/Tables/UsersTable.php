<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('role')->label('Rol')->badge(),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')->label('Rol')->options(UserRole::class),
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }
}
