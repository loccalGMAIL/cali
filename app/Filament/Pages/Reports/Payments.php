<?php

namespace App\Filament\Pages\Reports;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reports\PaymentsReport;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

class Payments extends ReportPage
{
    protected static ?string $slug = 'reports/payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?int $navigationSort = 3;

    protected static function reportClass(): string
    {
        return PaymentsReport::class;
    }

    protected function filterComponents(): array
    {
        return [
            DatePicker::make('from')->label('Desde')->live(),
            DatePicker::make('to')->label('Hasta')->live(),
            Select::make('method')->label('Medio de pago')
                ->options(PaymentMethod::class)
                ->placeholder('Todos')
                ->live(),
            Select::make('collected_by')->label('Cobró')
                ->options(fn (): array => User::query()
                    ->whereIn('id', Payment::query()->select('collected_by'))
                    ->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('Todos')
                ->live(),
        ];
    }
}
