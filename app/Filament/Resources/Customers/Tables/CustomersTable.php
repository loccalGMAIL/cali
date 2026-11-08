<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Models\Installment;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // La deuda y la deuda vencida se calculan en la misma consulta del listado (sin N+1).
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->addSelect([
                'customers.*',
                'debt' => Installment::query()
                    ->selectRaw('COALESCE(SUM(amount - paid_amount), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->unpaid(),
                'overdue_debt' => Installment::query()
                    ->selectRaw('COALESCE(SUM(amount - paid_amount), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->overdue(),
            ]))
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('document_number')->label('DNI / CUIT')->searchable(),
                TextColumn::make('phone')->label('Teléfono')->searchable(),
                TextColumn::make('city')->label('Localidad')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('debt')->label('Deuda')->money('ARS', locale: 'es_AR')->sortable(),
                TextColumn::make('overdue_debt')->label('Vencido')->money('ARS', locale: 'es_AR')->sortable()
                    ->color(fn (Customer $record): ?string => (float) $record->getAttribute('overdue_debt') > 0 ? 'danger' : null)
                    ->weight(fn (Customer $record) => (float) $record->getAttribute('overdue_debt') > 0 ? 'bold' : null),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('with_debt')->label('Con deuda')
                    ->query(fn (Builder $query): Builder => $query->whereExists(
                        Installment::query()->selectRaw('1')->whereColumn('customer_id', 'customers.id')->unpaid(),
                    )),
                Filter::make('overdue')->label('Con cuotas vencidas')
                    ->query(fn (Builder $query): Builder => $query->whereExists(
                        Installment::query()->selectRaw('1')->whereColumn('customer_id', 'customers.id')->overdue(),
                    )),
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                ViewAction::make()->label('Ficha'),
                EditAction::make()->label('Editar'),
            ]);
    }
}
