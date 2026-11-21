<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Abre el comprobante del cobro en PDF, en otra pestaña.
 */
class DownloadReceiptAction
{
    public static function make(): Action
    {
        return Action::make('receipt')->label('Comprobante')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->url(fn (Payment $record): string => static::url($record))
            ->openUrlInNewTab()
            ->visible(fn (Payment $record): bool => auth()->user()?->can('view', $record) ?? false);
    }

    public static function url(Payment $payment): string
    {
        return route('filament.admin.payments.receipt', $payment);
    }
}
