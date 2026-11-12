<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\AccountMovementType;
use App\Filament\Concerns\PersistsTableColumnsPerUser;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AccountMovementsRelationManager extends RelationManager
{
    use PersistsTableColumnsPerUser;

    protected static string $relationship = 'accountMovements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Cuenta corriente';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('date')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('description')->label('Detalle'),
                TextColumn::make('debit')->label('Debe')->money('ARS', locale: 'es_AR')
                    ->placeholder('—')->state(fn ($record): ?string => bccomp($record->debit, '0', 2) > 0 ? $record->debit : null),
                TextColumn::make('credit')->label('Haber')->money('ARS', locale: 'es_AR')
                    ->placeholder('—')->state(fn ($record): ?string => bccomp($record->credit, '0', 2) > 0 ? $record->credit : null),
                TextColumn::make('balance')->label('Saldo')->money('ARS', locale: 'es_AR')->weight('bold'),
            ])
            // Del más nuevo al más viejo; el id desempata los movimientos de un mismo día.
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(AccountMovementType::class),
            ]);
    }
}
