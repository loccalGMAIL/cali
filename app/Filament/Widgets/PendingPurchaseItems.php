<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PendingPurchases\PendingPurchaseResource;
use App\Models\SaleItem;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingPurchaseItems extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pendientes de compra')
            ->query(fn (): Builder => PendingPurchaseResource::getEloquentQuery()->with(['product', 'sale.customer']))
            ->defaultSort('sale_id')
            ->columns([
                TextColumn::make('product.name')->label('Producto')
                    ->url(fn (): string => PendingPurchaseResource::getUrl('index')),
                TextColumn::make('pending_quantity')->label('Pendiente')->numeric()->weight('bold'),
                TextColumn::make('sale_id')->label('Venta')->prefix('#')
                    ->description(fn (SaleItem $record): string => $record->sale->customer->name),
            ])
            ->emptyStateHeading('Todo lo vendido ya está pedido')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
