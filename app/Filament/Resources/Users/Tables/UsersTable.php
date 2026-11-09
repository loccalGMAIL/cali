<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Actions\DeleteUserAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
                IconColumn::make('is_default_seller')->label('Vendedor por defecto')
                    ->icon(fn (bool $state): ?Heroicon => $state ? Heroicon::Star : null)
                    ->color('warning'),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')->label('Rol')->options(UserRole::class),
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                Action::make('makeDefaultSeller')
                    ->label('Vendedor por defecto')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('warning')
                    ->authorize('update')
                    ->visible(fn (User $record): bool => $record->active && ! $record->is_default_seller)
                    ->action(function (User $record): void {
                        $record->update(['is_default_seller' => true]);

                        Notification::make()->success()->title("{$record->name} es ahora el vendedor por defecto")->send();
                    }),
                EditAction::make()->label('Editar'),
                DeleteUserAction::make(),
            ]);
    }
}
