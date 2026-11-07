<?php

namespace App\Filament\Resources\FinancingPlans\Pages;

use App\Filament\Resources\FinancingPlans\FinancingPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFinancingPlan extends EditRecord
{
    protected static string $resource = FinancingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
