<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ProjectedIncomeChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Ingresos proyectados';

    protected ?string $description = 'Próximos 12 meses. Lo vencido se suma al mes actual.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $projection = app(DashboardMetrics::class)->projection(12);

        return [
            'datasets' => [
                [
                    'label' => 'Monto a cobrar',
                    'data' => array_map(fn (array $month): float => (float) $month['amount'], $projection),
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'Ganancia',
                    'data' => array_map(fn (array $month): float => (float) $month['profit'], $projection),
                    'backgroundColor' => '#16a34a',
                ],
            ],
            'labels' => array_map(fn (array $month): string => $month['month']->translatedFormat('M Y'), $projection),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (value) => '$ ' + value.toLocaleString('es-AR') },
                },
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (context) => context.dataset.label + ': $ ' + context.parsed.y.toLocaleString('es-AR', { minimumFractionDigits: 2 }),
                    },
                },
            },
        }
        JS);
    }
}
