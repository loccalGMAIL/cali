<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Actions\CancelPurchaseOrderAction;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('supplier')->withSum('items', 'quantity'))
            ->columns([
                TextColumn::make('id')->label('N°')->formatStateUsing(fn ($state): string => PurchaseOrder::numberFor($state))->sortable(),
                TextColumn::make('date')->label('Fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('supplier.name')->label('Proveedor')->searchable(),
                TextColumn::make('items_sum_quantity')->label('Unidades')->numeric(),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(PurchaseOrderStatus::class),
                SelectFilter::make('supplier_id')->label('Proveedor')
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                EditAction::make()->label('Editar'),
                CancelPurchaseOrderAction::make(),
            ]);
    }
}
