<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Concerns\PersistsTableColumnsPerUser;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    use PersistsTableColumnsPerUser;

    protected static string $resource = ActivityLogResource::class;
}
