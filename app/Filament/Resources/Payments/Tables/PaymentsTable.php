<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Payments\Actions\CancelPaymentAction;
use App\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'collector']))
            ->columns([
                TextColumn::make('id')->label('N°')->sortable(),
                TextColumn::make('date')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('customer.name')->label('Cliente')->searchable(),
                TextColumn::make('amount')->label('Monto')->money('ARS', locale: 'es_AR')->sortable()
                    ->color(fn (Payment $record): ?string => $record->isCancelled() ? 'gray' : null)
                    ->extraAttributes(fn (Payment $record): array => $record->isCancelled() ? ['style' => 'text-decoration: line-through'] : []),
                TextColumn::make('method')->label('Medio')->badge(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->state(fn (Payment $record): string => $record->isCancelled() ? 'Anulado' : 'Vigente')
                    ->color(fn (string $state): string => $state === 'Anulado' ? 'danger' : 'success'),
                TextColumn::make('collector.name')->label('Cobrado por'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('method')->label('Medio de pago')->options(PaymentMethod::class),
                TernaryFilter::make('cancelled')->label('Anulados')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('cancelled_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('cancelled_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                CancelPaymentAction::make(),
            ]);
    }
}
