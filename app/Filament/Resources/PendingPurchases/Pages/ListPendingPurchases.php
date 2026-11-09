<?php

namespace App\Filament\Resources\PendingPurchases\Pages;

use App\Filament\Resources\PendingPurchases\PendingPurchaseResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPendingPurchases extends ListRecords
{
    protected static string $resource = PendingPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newOrder')->label('Nueva orden de compra')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->url(fn (): string => PurchaseOrderResource::getUrl('create'))
                ->visible(fn (): bool => PurchaseOrderResource::canCreate()),
        ];
    }
}
