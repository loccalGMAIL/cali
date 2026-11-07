<?php

namespace App\Filament\Resources\FinancingPlans\Pages;

use App\Filament\Resources\FinancingPlans\FinancingPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinancingPlan extends CreateRecord
{
    protected static string $resource = FinancingPlanResource::class;

    protected static bool $canCreateAnother = false;
}
