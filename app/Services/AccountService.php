<?php

namespace App\Services;

use App\Enums\AccountMovementType;
use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountService
{
    /**
     * Registra un movimiento en la cuenta corriente del cliente y calcula el saldo acumulado
     * (positivo = el cliente debe). Bloquea al cliente para serializar los movimientos
     * concurrentes y que el saldo quede consistente.
     */
    public function record(
        Customer $customer,
        AccountMovementType $type,
        string $description,
        string $debit = '0.00',
        string $credit = '0.00',
        ?Model $reference = null,
        ?User $user = null,
        CarbonInterface|string|null $date = null,
    ): AccountMovement {
        return DB::transaction(function () use ($customer, $type, $description, $debit, $credit, $reference, $user, $date): AccountMovement {
            Customer::query()->lockForUpdate()->findOrFail($customer->getKey());

            $previous = AccountMovement::query()
                ->where('customer_id', $customer->getKey())
                ->latest('id')
                ->value('balance') ?? '0.00';

            return AccountMovement::create([
                'customer_id' => $customer->getKey(),
                'date' => $date ?? today(),
                'type' => $type,
                'description' => $description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => bcsub(bcadd((string) $previous, $debit, 2), $credit, 2),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $user?->getKey(),
            ]);
        });
    }

    public function balance(Customer $customer): string
    {
        return (string) (AccountMovement::query()
            ->where('customer_id', $customer->getKey())
            ->latest('id')
            ->value('balance') ?? '0.00');
    }
}
