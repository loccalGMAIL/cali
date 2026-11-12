<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\PersistsTableColumnsPerUser;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Purchase;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestPurchases extends TableWidget
{
    use PersistsTableColumnsPerUser;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * Filament ignora el limit() de la consulta de un widget, así que se toman primero los ids
     * de las 10 más recientes (MySQL no admite LIMIT dentro de un IN) y la tabla las muestra.
     *
     * @return Builder<Purchase>
     */
    private function latestTen(): Builder
    {
        $ids = Purchase::query()->notCancelled()->latest('date')->latest('id')->limit(10)->pluck('id');

        return Purchase::query()->whereKey($ids)->with('supplier')->latest('date')->latest('id');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Últimas compras')
            ->query(fn (): Builder => $this->latestTen())
            ->columns([
                TextColumn::make('date')->label('Fecha')->date('d/m/Y'),
                TextColumn::make('supplier.name')->label('Proveedor'),
                TextColumn::make('invoice_number')->label('Factura'),
                TextColumn::make('total')->label('Total')->money('ARS', locale: 'es_AR'),
            ])
            ->recordUrl(fn (Purchase $record): string => PurchaseResource::getUrl('view', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Todavía no hay compras')
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart);
    }
}
