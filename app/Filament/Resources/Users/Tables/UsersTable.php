<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Actions\DeleteUserAction;
use App\Models\User;
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
                    ->icon(fn (bool $state, User $record): ?Heroicon => match (true) {
                        $state => Heroicon::Star,
                        $record->active => Heroicon::OutlinedStar,
                        default => null,
                    })
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                    ->tooltip(fn (bool $state, User $record): ?string => ! $state && $record->active ? 'Marcar como vendedor por defecto' : null)
                    ->action(function (User $record): void {
                        if ($record->is_default_seller || ! $record->active || auth()->user()?->cannot('update', $record)) {
                            return;
                        }

                        $record->update(['is_default_seller' => true]);

                        Notification::make()->success()->title("{$record->name} es ahora el vendedor por defecto")->send();
                    }),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')->label('Rol')->options(UserRole::class),
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteUserAction::make(),
            ]);
    }
}
