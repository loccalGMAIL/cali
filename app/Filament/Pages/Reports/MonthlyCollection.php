<?php

namespace App\Filament\Pages\Reports;

use App\Services\Reports\MonthlyCollectionReport;
use App\Services\Reports\ReportFilters;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

class MonthlyCollection extends ReportPage
{
    protected static ?string $slug = 'reports/monthly-collection';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    protected static function reportClass(): string
    {
        return MonthlyCollectionReport::class;
    }

    protected function filterComponents(): array
    {
        return [
            Select::make('month')->label('Mes')
                ->options(fn (): array => self::monthOptions())
                ->selectablePlaceholder(false)
                ->live(),
            $this->cityFilter(),
            Toggle::make('include_overdue')->label('Incluir atrasadas de meses anteriores')->inline(false)->live(),
        ];
    }

    /**
     * Desde tres meses atrás hasta un año adelante.
     *
     * @return array<string, string>
     */
    private static function monthOptions(): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths(3);
        $options = [];

        for ($i = 0; $i < 16; $i++) {
            $month = $start->addMonths($i);
            $options[$month->format('Y-m')] = ReportFilters::monthTitle($month);
        }

        return $options;
    }
}
