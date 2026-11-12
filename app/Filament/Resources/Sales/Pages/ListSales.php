<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Concerns\PersistsTableColumnsPerUser;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    use PersistsTableColumnsPerUser;

    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
