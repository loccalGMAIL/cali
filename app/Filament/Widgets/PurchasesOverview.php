<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PendingPurchases\PendingPurchaseResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Services\DashboardMetrics;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * No hay stock: lo que importa es qué falta pedirle a los proveedores, qué órdenes están
 * esperando factura y cuánto se compró en el mes.
 */
class PurchasesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Compras';

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

        $pending = $metrics->pendingPurchase();
        $openOrders = $metrics->openPurchaseOrdersCount();

        return [
            Stat::make('Pendiente de compra', Money::whole($pending['cost']))
                ->description($pending['units'] === 0 ? 'Todo pedido' : ($pending['units'] === 1 ? '1 unidad sin pedir' : "{$pending['units']} unidades sin pedir"))
                ->descriptionIcon($pending['units'] === 0 ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedInboxStack)
                ->color($pending['units'] === 0 ? 'success' : 'warning')
                ->url(PendingPurchaseResource::getUrl('index')),
            Stat::make('Órdenes sin recibir', (string) $openOrders)
                ->description($openOrders === 0 ? 'Ninguna esperando factura' : 'Esperando la factura del proveedor')
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentList)
                ->color($openOrders === 0 ? 'gray' : 'primary')
                ->url(PurchaseOrderResource::getUrl('index')),
            Stat::make('Compras del mes', Money::whole($thisMonth))
                ->description(str_replace('Sin cobros', 'Sin compras', $comparison).' · mes anterior '.Money::whole($lastMonth))
                ->descriptionIcon($icon)
                ->color('gray'),
        ];
    }
}
