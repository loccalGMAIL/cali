<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\InstallmentStatus;
use App\Models\Installment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Cuotas';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('sale_id')->label('Venta')->prefix('#')->sortable(),
                TextColumn::make('number')->label('Cuota')->sortable(),
                TextColumn::make('due_date')->label('Vencimiento')->date('d/m/Y')->sortable()
                    ->color(fn (Installment $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->weight(fn (Installment $record) => $record->isOverdue() ? 'bold' : null)
                    ->description(fn (Installment $record): ?string => $record->isOverdue()
                        ? 'Vencida hace '.$record->due_date->diffInDays(today()).' días'
                        : null),
                TextColumn::make('amount')->label('Monto')->money('ARS', locale: 'es_AR'),
                TextColumn::make('paid_amount')->label('Pagado')->money('ARS', locale: 'es_AR'),
                TextColumn::make('balance')->label('Saldo')->money('ARS', locale: 'es_AR')
                    ->state(fn (Installment $record): string => $record->status === InstallmentStatus::Cancelled ? '0.00' : $record->balance()),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->defaultSort('due_date')
            ->recordClasses(fn (Installment $record): ?string => $record->isOverdue() ? 'bg-danger-50 dark:bg-danger-400/10' : null)
            ->filters([
                Filter::make('overdue')->label('Vencidas')
                    ->query(fn (Builder $query): Builder => $query->scopes(['overdue'])),
                SelectFilter::make('status')->label('Estado')->options(InstallmentStatus::class),
            ]);
    }
}
