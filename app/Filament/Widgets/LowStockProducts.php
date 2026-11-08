<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockProducts extends TableWidget
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
            ->heading('Productos con stock bajo')
            ->query(fn (): Builder => Product::query()->active()->lowStock())
            ->defaultSort('stock')
            ->columns([
                TextColumn::make('name')->label('Producto')->searchable()
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('stock')->label('Stock')->numeric()->color('danger')->weight('bold'),
                TextColumn::make('min_stock')->label('Mínimo')->numeric(),
            ])
            ->emptyStateHeading('Ningún producto por debajo del mínimo')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
