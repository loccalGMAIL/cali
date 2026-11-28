<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Models\Payment;
use App\Services\PaymentReceipt;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

/**
 * Comparte el PDF del comprobante con el menú de compartir del dispositivo, para mandarlo como
 * archivo por WhatsApp (o por donde se elija). Funciona sobre todo en el celular; el contacto se
 * elige en WhatsApp. El script está en `filament.admin.share-receipt`.
 */
class ShareReceiptAction
{
    public static function make(): Action
    {
        return Action::make('share')->label('Compartir PDF')
            ->icon(Heroicon::OutlinedShare)
            ->color('gray')
            ->alpineClickHandler(fn (Payment $record): string => static::clickHandler($record))
            ->visible(fn (Payment $record): bool => ! $record->isCancelled() && (auth()->user()?->can('view', $record) ?? false));
    }

    public static function clickHandler(Payment $payment): string
    {
        return 'window.caliShareReceipt('
            .Js::from(DownloadReceiptAction::url($payment)).', '
            .Js::from(PaymentReceipt::fileName($payment)).')';
    }
}
