<?php

namespace App\Filament\Resources\FinancingPlans;

use App\Filament\Resources\FinancingPlans\Pages\CreateFinancingPlan;
use App\Filament\Resources\FinancingPlans\Pages\EditFinancingPlan;
use App\Filament\Resources\FinancingPlans\Pages\ListFinancingPlans;
use App\Filament\Resources\FinancingPlans\Schemas\FinancingPlanForm;
use App\Filament\Resources\FinancingPlans\Tables\FinancingPlansTable;
use App\Models\FinancingPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FinancingPlanResource extends Resource
{
    protected static ?string $model = FinancingPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Plan de financiación';

    protected static ?string $pluralModelLabel = 'Planes de financiación';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FinancingPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancingPlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFinancingPlans::route('/'),
            'create' => CreateFinancingPlan::route('/create'),
            'edit' => EditFinancingPlan::route('/{record}/edit'),
        ];
    }
}
