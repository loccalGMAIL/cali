<?php

namespace App\Filament\Pages\Reports;

use App\Services\Reports\PendingInstallmentsReport;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

class PendingInstallments extends ReportPage
{
    protected static ?string $slug = 'reports/pending-installments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?int $navigationSort = 1;

    protected static function reportClass(): string
    {
        return PendingInstallmentsReport::class;
    }

    protected function filterComponents(): array
    {
        return [
            Select::make('months')->label('Meses a mostrar')
                ->options(array_combine(
                    PendingInstallmentsReport::MONTH_OPTIONS,
                    array_map(fn (int $months): string => "{$months} meses", PendingInstallmentsReport::MONTH_OPTIONS),
                ))
                ->selectablePlaceholder(false)
                ->live(),
            $this->cityFilter(),
            Toggle::make('only_overdue')->label('Solo clientes con cuotas vencidas')->inline(false)->live(),
        ];
    }
}
