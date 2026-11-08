<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Services\DashboardMetrics;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockAndPurchasesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Stock y compras';

    protected int|array|null $columns = 3;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $metrics = app(DashboardMetrics::class);

        $thisMonth = $metrics->purchasedBetween(today()->startOfMonth(), today()->endOfMonth());
        $lastMonthDate = today()->subMonthNoOverflow();
        $lastMonth = $metrics->purchasedBetween($lastMonthDate->copy()->startOfMonth(), $lastMonthDate->copy()->endOfMonth());

        // Comprar más no es bueno ni malo por sí mismo: la variación se muestra sin color de alarma.
        [$comparison, , $icon] = CollectionsOverview::compare($thisMonth, $lastMonth);

        $lowStock = $metrics->lowStockCount();

        return [
            Stat::make('Valor del stock', Money::format($metrics->stockValue()))
                ->description('A último costo')
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color('primary'),
            Stat::make('Compras del mes', Money::format($thisMonth))
                ->description(str_replace('Sin cobros', 'Sin compras', $comparison).' · mes anterior '.Money::format($lastMonth))
                ->descriptionIcon($icon)
                ->color('gray'),
            Stat::make('Productos con stock bajo', (string) $lowStock)
                ->description($lowStock === 0 ? 'Todo en orden' : 'Por debajo del mínimo')
                ->descriptionIcon($lowStock === 0 ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedExclamationTriangle)
                ->color($lowStock === 0 ? 'success' : 'warning')
                ->url($lowStock === 0 ? null : ProductResource::getUrl('index')),
        ];
    }
}
