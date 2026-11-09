<?php

namespace App\Filament\Resources\PendingPurchases;

use App\Filament\Resources\PendingPurchases\Pages\ListPendingPurchases;
use App\Filament\Resources\PendingPurchases\Tables\PendingPurchasesTable;
use App\Models\SaleItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Lo vendido que todavía no se incluyó en una orden de compra. No hay stock: de acá sale
 * lo que hay que pedirle a cada proveedor.
 */
class PendingPurchaseResource extends Resource
{
    protected static ?string $model = SaleItem::class;

    protected static ?string $slug = 'pending-purchases';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Compras';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pendientes de compra';

    protected static ?string $modelLabel = 'Pendiente de compra';

    protected static ?string $pluralModelLabel = 'Pendientes de compra';

    /**
     * @return Builder<SaleItem>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->pendingPurchase()
            ->whereHas('sale', fn ($query) => $query->notCancelled());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return PendingPurchasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendingPurchases::route('/'),
        ];
    }
}
