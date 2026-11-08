<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use Database\Factories\InstallmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property InstallmentStatus $status
 * @property Carbon $due_date
 * @property string $amount
 * @property string $paid_amount
 * @property string $cost_portion
 */
#[Fillable(['sale_id', 'customer_id', 'number', 'due_date', 'amount', 'paid_amount', 'cost_portion', 'status'])]
class Installment extends Model
{
    /** @use HasFactory<InstallmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'cost_portion' => 'decimal:2',
            'status' => InstallmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsToMany<Payment, $this>
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class)->withPivot('amount')->withTimestamps();
    }

    /**
     * La condición de vencida no se guarda: se calcula contra la fecha actual.
     *
     * @param  Builder<Installment>  $query
     * @return Builder<Installment>
     */
    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query->whereDate('due_date', '<', today())->whereIn('status', InstallmentStatus::unpaidValues());
    }

    /**
     * @param  Builder<Installment>  $query
     * @return Builder<Installment>
     */
    #[Scope]
    protected function unpaid(Builder $query): Builder
    {
        return $query->whereIn('status', InstallmentStatus::unpaidValues());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status->value, InstallmentStatus::unpaidValues(), true) && $this->due_date->lt(today());
    }

    public function balance(): string
    {
        return bcsub($this->amount, $this->paid_amount, 2);
    }
}
