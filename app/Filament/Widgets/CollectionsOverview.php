<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo que el dueño quiere ver primero: cuánto hay por cobrar, cuánto de eso es ganancia,
 * qué está vencido y cuánto se cobró este mes frente al anterior.
 */
class CollectionsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Cobranzas';

    protected int|array|null $columns = 4;

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

        $receivable = $metrics->receivable();
        $overdue = $metrics->overdue();

        $thisMonth = $metrics->collectedBetween(today()->startOfMonth(), today()->endOfMonth());
        $lastMonthDate = today()->subMonthNoOverflow();
        $lastMonth = $metrics->collectedBetween($lastMonthDate->copy()->startOfMonth(), $lastMonthDate->copy()->endOfMonth());

        [$comparison, $comparisonColor, $comparisonIcon] = self::compare($thisMonth, $lastMonth);

        return [
            Stat::make('Total por cobrar', Money::format($receivable['amount']))
                ->description(self::quantity($receivable['count'], 'cuota pendiente', 'cuotas pendientes'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            Stat::make('Ganancia futura', Money::format($metrics->futureProfit()))
                ->description('Sobre lo que falta cobrar')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color('success'),
            Stat::make('Cuotas vencidas', Money::format($overdue['amount']))
                ->description(self::quantity($overdue['count'], 'cuota vencida', 'cuotas vencidas'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($overdue['count'] > 0 ? 'danger' : 'gray'),
            Stat::make('Cobrado este mes', Money::format($thisMonth))
                ->description($comparison.' · mes anterior '.Money::format($lastMonth))
                ->descriptionIcon($comparisonIcon)
                ->color($comparisonColor),
        ];
    }

    /**
     * Compara dos montos: variación porcentual, color e ícono.
     *
     * @return array{0: string, 1: string, 2: Heroicon}
     */
    public static function compare(string $current, string $previous): array
    {
        if (bccomp($previous, '0', 2) === 0) {
            return [bccomp($current, '0', 2) === 0 ? 'Sin cobros' : 'Sin cobros el mes anterior', 'gray', Heroicon::OutlinedMinus];
        }

        $change = bcmul(bcdiv(bcsub($current, $previous, 2), $previous, 4), '100', 1);
        $text = (bccomp($change, '0', 1) > 0 ? '+' : '').number_format((float) $change, 1, ',', '.').'%';

        return match (bccomp($change, '0', 1)) {
            1 => [$text, 'success', Heroicon::OutlinedArrowTrendingUp],
            -1 => [$text, 'danger', Heroicon::OutlinedArrowTrendingDown],
            default => ['Sin cambios', 'gray', Heroicon::OutlinedMinus],
        };
    }

    private static function quantity(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? "1 {$singular}" : "{$count} {$plural}";
    }
}
