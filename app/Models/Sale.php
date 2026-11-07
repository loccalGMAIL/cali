<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property SaleStatus $status
 * @property string $subtotal
 * @property string $down_payment
 * @property string $financed_amount
 * @property string $total
 * @property string $total_cost
 * @property string $surcharge_percent
 */
#[Fillable([
    'customer_id', 'seller_id', 'date', 'financing_plan_id', 'installments_count', 'surcharge_percent',
    'subtotal', 'down_payment', 'financed_amount', 'total', 'total_cost', 'first_due_date', 'status', 'notes',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'first_due_date' => 'date',
            'installments_count' => 'integer',
            'surcharge_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'financed_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'status' => SaleStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return BelongsTo<FinancingPlan, $this>
     */
    public function financingPlan(): BelongsTo
    {
        return $this->belongsTo(FinancingPlan::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }

    /**
     * @return MorphMany<StockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
