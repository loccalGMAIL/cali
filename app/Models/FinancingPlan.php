<?php

namespace App\Models;

use Database\Factories\FinancingPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'installments_count', 'surcharge_percent', 'active', 'sort_order'])]
class FinancingPlan extends Model
{
    /** @use HasFactory<FinancingPlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installments_count' => 'integer',
            'surcharge_percent' => 'decimal:2',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<FinancingPlan>  $query
     * @return Builder<FinancingPlan>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('sort_order')->orderBy('installments_count');
    }
}
