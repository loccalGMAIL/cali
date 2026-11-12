<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\PersistsTableColumnsPerUser;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    use PersistsTableColumnsPerUser;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
