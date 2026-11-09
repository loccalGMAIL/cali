<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Contracts\ActivityLoggable;
use Database\Factories\FinancingPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'installments_count', 'active'])]
class FinancingPlan extends Model implements ActivityLoggable
{
    /** @use HasFactory<FinancingPlanFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installments_count' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<FinancingPlan>  $query
     * @return Builder<FinancingPlan>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('installments_count');
    }

    public function activityNoun(): string
    {
        return 'el plan de financiación';
    }

    public function activityTitle(): string
    {
        return (string) $this->name;
    }
}
