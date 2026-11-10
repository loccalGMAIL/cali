<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SaleStatus $status
 * @property string $down_payment
 * @property string $financed_amount
 * @property string $total
 * @property string $total_cost
 * @property string $total_freight
 */
#[Fillable([
    'customer_id', 'seller_id', 'date', 'financing_plan_id', 'installments_count',
    'down_payment', 'financed_amount', 'total', 'total_cost', 'total_freight', 'first_due_date', 'status', 'notes',
    'cancelled_at', 'cancelled_by', 'cancellation_reason',
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
            'down_payment' => 'decimal:2',
            'financed_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'total_freight' => 'decimal:2',
            'status' => SaleStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->status === SaleStatus::Cancelled;
    }

    /**
     * Ventas vigentes (excluye las anuladas), para totales y reportes.
     *
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    #[Scope]
    protected function notCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', SaleStatus::Cancelled->value);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
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
}
