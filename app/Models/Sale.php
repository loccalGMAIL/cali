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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * Número con el que se identifica la venta: `V-00012`.
     */
    public static function numberFor(int|string|null $id): string
    {
        return $id === null ? '—' : 'V-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public function number(): string
    {
        return static::numberFor($this->getKey());
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
     * Cobros del anticipo (los que se hicieron al vender, sin imputar a cuotas).
     *
     * @return HasMany<Payment, $this>
     */
    public function downPayments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Cobros vigentes de la venta, del más viejo al más nuevo: el anticipo y lo imputado a cada
     * cuota. Necesita `downPayments` e `installments.payments` cargados.
     *
     * @return Collection<int, array{date: Carbon, amount: string, concept: string}>
     */
    public function paymentLines(): Collection
    {
        $lines = collect();

        foreach ($this->downPayments as $payment) {
            if (! $payment->isCancelled()) {
                $lines->push(['date' => $payment->date, 'amount' => $payment->amount, 'concept' => 'Anticipo']);
            }
        }

        foreach ($this->installments as $installment) {
            foreach ($installment->payments as $payment) {
                if (! $payment->isCancelled()) {
                    $lines->push([
                        'date' => $payment->date,
                        'amount' => $payment->pivot->amount,
                        'concept' => "Cuota {$installment->number}/{$this->installments_count}",
                    ]);
                }
            }
        }

        return $lines->sortBy(fn (array $line): string => $line['date']->toDateString())->values();
    }

    public function paidAmount(): string
    {
        return $this->paymentLines()->reduce(fn (string $total, array $line): string => bcadd($total, $line['amount'], 2), '0.00');
    }

    /**
     * Lo que falta cobrar: el saldo de las cuotas (una venta anulada no debe nada).
     */
    public function balanceAmount(): string
    {
        if ($this->isCancelled()) {
            return '0.00';
        }

        return $this->installments->reduce(fn (string $total, Installment $installment): string => bcadd($total, $installment->balance(), 2), '0.00');
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }
}
