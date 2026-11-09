<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Reglas de cálculo de una venta en cuotas. Es una clase pura (sin base de datos):
 * las ventas reales y la vista previa del formulario usan exactamente el mismo cálculo.
 *
 * Cada ítem vale costo + margen (los dos se cargan al vender). La cuota es
 * (total − anticipo) ÷ cantidad de cuotas: no hay recargo por plan.
 *
 * Todos los montos viajan como strings decimales con 2 decimales y se operan con bcmath
 * para evitar errores de punto flotante.
 *
 * @phpstan-type Line array{quantity: int, unit_price: string|int|float, unit_cost: string|int|float}
 * @phpstan-type Installment array{number: int, due_date: CarbonImmutable, amount: string, cost_portion: string}
 * @phpstan-type Result array{
 *     total: string,
 *     total_cost: string,
 *     financed_amount: string,
 *     down_payment: string,
 *     down_payment_cost: string,
 *     installment_amount: string,
 *     installments: list<Installment>
 * }
 */
class SaleCalculator
{
    /**
     * @param  list<Line>  $lines
     * @return Result
     *
     * @throws InvalidArgumentException
     */
    public function calculate(
        array $lines,
        string|int|float $downPayment,
        int $installmentsCount,
        CarbonInterface|string $firstDueDate,
    ): array {
        if ($installmentsCount < 1) {
            throw new InvalidArgumentException('La cantidad de cuotas debe ser al menos 1.');
        }

        $total = '0.00';
        $totalCost = '0.00';

        foreach ($lines as $line) {
            $quantity = (string) max(0, (int) $line['quantity']);
            $total = bcadd($total, bcmul($quantity, $this->money($line['unit_price']), 2), 2);
            $totalCost = bcadd($totalCost, bcmul($quantity, $this->money($line['unit_cost']), 2), 2);
        }

        $downPayment = $this->money($downPayment);

        if (bccomp($downPayment, '0', 2) < 0) {
            throw new InvalidArgumentException('El anticipo no puede ser negativo.');
        }

        if (bccomp($downPayment, $total, 2) > 0) {
            throw new InvalidArgumentException('El anticipo no puede superar el total de la venta.');
        }

        // Sin recargos: lo que queda después del anticipo se divide en cuotas iguales.
        $financed = bcsub($total, $downPayment, 2);

        $installments = $this->buildInstallments($financed, $installmentsCount, $firstDueDate, $total, $totalCost, $downPayment);

        return [
            'total' => $total,
            'total_cost' => $totalCost,
            'financed_amount' => $financed,
            'down_payment' => $downPayment,
            'down_payment_cost' => $this->costShare($totalCost, $downPayment, $total),
            'installment_amount' => $installments[0]['amount'] ?? '0.00',
            'installments' => $installments,
        ];
    }

    /**
     * Precio unitario de un ítem: costo + margen.
     */
    public function unitPrice(string|int|float $unitCost, string|int|float $unitMargin): string
    {
        return bcadd($this->money($unitCost), $this->money($unitMargin), 2);
    }

    /**
     * @return list<Installment>
     */
    private function buildInstallments(
        string $financed,
        int $count,
        CarbonInterface|string $firstDueDate,
        string $total,
        string $totalCost,
        string $downPayment,
    ): array {
        if (bccomp($financed, '0', 2) === 0) {
            return [];
        }

        // Monto de cuota redondeado hacia abajo; la última absorbe el redondeo.
        $base = bcdiv($financed, (string) $count, 2);
        $lastAmount = bcsub($financed, bcmul($base, (string) ($count - 1), 2), 2);
        $firstDue = CarbonImmutable::parse($firstDueDate)->startOfDay();

        // El costo no financiado por el anticipo se reparte entre las cuotas; la última absorbe el redondeo.
        $costToSpread = bcsub($totalCost, $this->costShare($totalCost, $downPayment, $total), 2);
        $assignedCost = '0.00';

        $installments = [];

        for ($number = 1; $number <= $count; $number++) {
            $amount = $number === $count ? $lastAmount : $base;

            if ($number === $count) {
                $costPortion = bcsub($costToSpread, $assignedCost, 2);
            } else {
                $costPortion = $this->costShare($totalCost, $amount, $total);
                $assignedCost = bcadd($assignedCost, $costPortion, 2);
            }

            $installments[] = [
                'number' => $number,
                'due_date' => $firstDue->addMonthsNoOverflow($number - 1),
                'amount' => $amount,
                'cost_portion' => $costPortion,
            ];
        }

        return $installments;
    }

    /**
     * Parte proporcional del costo total que le corresponde a un monto: costo × (monto / total).
     */
    private function costShare(string $totalCost, string $amount, string $total): string
    {
        if (bccomp($total, '0', 2) === 0) {
            return '0.00';
        }

        return $this->round(bcdiv(bcmul($totalCost, $amount, 6), $total, 6));
    }

    /**
     * Normaliza cualquier número a un string con 2 decimales.
     */
    private function money(string|int|float $value): string
    {
        return $this->round(is_float($value) ? number_format($value, 6, '.', '') : (string) $value);
    }

    /**
     * Redondeo comercial (mitad hacia arriba) a 2 decimales; los montos no son negativos.
     */
    private function round(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-');
        $rounded = bcadd(bcadd($absolute, '0.005', 3), '0', 2);

        return $negative && bccomp($rounded, '0', 2) !== 0 ? '-'.$rounded : $rounded;
    }
}
