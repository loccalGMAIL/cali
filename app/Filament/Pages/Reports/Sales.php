<?php

namespace App\Filament\Pages\Reports;

use App\Models\Sale;
use App\Models\User;
use App\Services\Reports\SalesReport;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

class Sales extends ReportPage
{
    protected static ?string $slug = 'reports/sales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 4;

    protected static function reportClass(): string
    {
        return SalesReport::class;
    }

    protected function filterComponents(): array
    {
        return [
            DatePicker::make('from')->label('Desde')->live(),
            DatePicker::make('to')->label('Hasta')->live(),
            Select::make('seller_id')->label('Vendedor')
                ->options(fn (): array => User::query()
                    ->whereIn('id', Sale::query()->select('seller_id'))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('Todos')
                ->live(),
            Select::make('group_by')->label('Agrupar por')
                ->options(SalesReport::GROUP_OPTIONS)
                ->selectablePlaceholder(false)
                ->live(),
        ];
    }
}
