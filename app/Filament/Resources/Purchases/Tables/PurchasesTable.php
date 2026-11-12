<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Filament\Resources\Purchases\Actions\CancelPurchaseAction;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('supplier'))
            ->columns([
                TextColumn::make('date')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('supplier.name')->label('Proveedor')->searchable(),
                TextColumn::make('invoice_number')->label('Factura')->searchable(),
                TextColumn::make('purchase_order_id')->label('Orden')->formatStateUsing(fn ($state): string => PurchaseOrder::numberFor($state))->sortable(),
                TextColumn::make('total')->label('Total')->money('ARS', locale: 'es_AR')->sortable()
                    ->color(fn (Purchase $record): ?string => $record->isCancelled() ? 'gray' : null)
                    ->extraAttributes(fn (Purchase $record): array => $record->isCancelled() ? ['style' => 'text-decoration: line-through'] : []),
                TextColumn::make('status')->label('Estado')->badge()
                    ->state(fn (Purchase $record): string => $record->isCancelled() ? 'Anulada' : 'Vigente')
                    ->color(fn (string $state): string => $state === 'Anulada' ? 'danger' : 'success'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('supplier_id')->label('Proveedor')
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('cancelled')->label('Anuladas')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('cancelled_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('cancelled_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                CancelPurchaseAction::make(),
            ]);
    }
}
