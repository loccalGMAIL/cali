<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\Actions\CancelPaymentAction;
use App\Filament\Resources\Payments\Actions\DownloadReceiptAction;
use App\Filament\Resources\Payments\Actions\SendReceiptByWhatsAppAction;
use App\Filament\Resources\Payments\Actions\ShareReceiptAction;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadReceiptAction::make(),
            SendReceiptByWhatsAppAction::make(),
            ShareReceiptAction::make(),
            CancelPaymentAction::make(),
        ];
    }
}
