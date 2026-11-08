<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property PaymentMethod $method
 * @property string $amount
 */
#[Fillable(['customer_id', 'sale_id', 'date', 'amount', 'method', 'collected_by', 'notes', 'cancelled_at', 'cancelled_by', 'cancellation_reason'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Un anticipo se cobra al vender y no se imputa a cuotas: forma parte de la venta
     * y se anula junto con ella.
     */
    public function isDownPayment(): bool
    {
        return $this->sale_id !== null;
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Cobros vigentes (excluye los anulados), para totales y reportes.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    #[Scope]
    protected function notCancelled(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
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
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * Cuotas a las que se imputó el cobro (con el monto aplicado en el pivot).
     *
     * @return BelongsToMany<Installment, $this>
     */
    public function installments(): BelongsToMany
    {
        return $this->belongsToMany(Installment::class)->withPivot('amount')->withTimestamps();
    }
}
