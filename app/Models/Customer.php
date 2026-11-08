<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'document_number', 'phone', 'email', 'address', 'city', 'notes', 'active'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<AccountMovement, $this>
     */
    public function accountMovements(): HasMany
    {
        return $this->hasMany(AccountMovement::class);
    }

    /**
     * Deuda vencida: saldo de las cuotas no pagadas cuya fecha de vencimiento ya pasó.
     */
    public function overdueDebt(): string
    {
        $total = $this->installments()->overdue()->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as debt')->value('debt') ?? 0;

        return number_format((float) $total, 2, '.', '');
    }

    public function overdueCount(): int
    {
        return $this->installments()->overdue()->count();
    }

    /**
     * Vencimiento de la cuota impaga más próxima (o más vieja, si hay vencidas).
     */
    public function nextDueDate(): ?Carbon
    {
        $date = $this->installments()->unpaid()->min('due_date');

        return $date === null ? null : Carbon::parse($date);
    }

    /**
     * Deuda total: saldo de todas las cuotas no pagadas.
     */
    public function debt(): string
    {
        $total = (string) ($this->installments()->unpaid()->selectRaw('COALESCE(SUM(amount - paid_amount), 0) as debt')->value('debt') ?? '0');

        return number_format((float) $total, 2, '.', '');
    }
}
