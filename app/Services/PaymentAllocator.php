<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Imputa un cobro a las cuotas de un cliente, empezando por la de vencimiento más viejo (FIFO).
 * Es una clase pura: recibe las cuotas ya ordenadas y devuelve cuánto se aplica a cada una.
 *
 * @phpstan-type Pending array{id: int, balance: string}
 * @phpstan-type Allocation array{installment_id: int, applied: string, remaining: string}
 */
class PaymentAllocator
{
    /**
     * @param  list<Pending>  $installments  Cuotas con saldo pendiente, ya ordenadas por vencimiento (la más vieja primero).
     * @return list<Allocation>
     *
     * @throws InvalidArgumentException
     */
    public function allocate(array $installments, string|int|float $amount): array
    {
        $left = number_format((float) $amount, 2, '.', '');

        if (bccomp($left, '0', 2) <= 0) {
            throw new InvalidArgumentException('El monto del cobro debe ser mayor a cero.');
        }

        if (bccomp($left, $this->totalBalance($installments), 2) > 0) {
            throw new InvalidArgumentException('El monto supera la deuda total del cliente.');
        }

        $allocations = [];

        foreach ($installments as $installment) {
            if (bccomp($left, '0', 2) === 0) {
                break;
            }

            if (bccomp($installment['balance'], '0', 2) <= 0) {
                continue;
            }

            $applied = bccomp($left, $installment['balance'], 2) >= 0 ? $installment['balance'] : $left;

            $allocations[] = [
                'installment_id' => $installment['id'],
                'applied' => $applied,
                'remaining' => bcsub($installment['balance'], $applied, 2),
            ];

            $left = bcsub($left, $applied, 2);
        }

        return $allocations;
    }

    /**
     * @param  list<Pending>  $installments
     */
    public function totalBalance(array $installments): string
    {
        return array_reduce(
            $installments,
            fn (string $carry, array $installment): string => bcadd($carry, $installment['balance'], 2),
            '0.00',
        );
    }
}
