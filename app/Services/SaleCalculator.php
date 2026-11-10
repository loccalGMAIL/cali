<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Reglas de cálculo de una venta en cuotas. Es una clase pura (sin base de datos):
 * las ventas reales y la vista previa del formulario usan exactamente el mismo cálculo.
 *
 * Cada ítem vale costo + flete + margen (los tres se cargan al vender; el flete es opcional).
 * La cuota es (total − anticipo) ÷ cantidad de cuotas: no hay recargo por plan, y el flete
 * se divide en las cuotas junto con el resto.
 *
 * Para la porción de costo de cada cuota (lo que no es ganancia) se toma costo + flete.
 *
 * Todos los montos viajan como strings decimales con 2 decimales y se operan con bcmath
 * para evitar errores de punto flotante.
 *
 * @phpstan-type Line array{quantity: int, unit_price: string|int|float, unit_cost: string|int|float, unit_freight?: string|int|float}
 * @phpstan-type Installment array{number: int, due_date: CarbonImmutable, amount: string, cost_portion: string}
 * @phpstan-type Result array{
 *     total: string,
 *     total_cost: string,
 *     total_freight: string,
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
        $totalFreight = '0.00';

        foreach ($lines as $line) {
            $quantity = (string) max(0, (int) $line['quantity']);
            $total = bcadd($total, bcmul($quantity, $this->money($line['unit_price']), 2), 2);
            $totalCost = bcadd($totalCost, bcmul($quantity, $this->money($line['unit_cost']), 2), 2);
            $totalFreight = bcadd($totalFreight, bcmul($quantity, $this->money($line['unit_freight'] ?? 0), 2), 2);
        }

        // El flete no es ganancia: para repartir el costo entre anticipo y cuotas cuenta como costo.
        $costToRecover = bcadd($totalCost, $totalFreight, 2);

        $downPayment = $this->money($downPayment);

        if (bccomp($downPayment, '0', 2) < 0) {
            throw new InvalidArgumentException('El anticipo no puede ser negativo.');
        }

        if (bccomp($downPayment, $total, 2) > 0) {
            throw new InvalidArgumentException('El anticipo no puede superar el total de la venta.');
        }

        // Sin recargos: lo que queda después del anticipo se divide en cuotas iguales.
        $financed = bcsub($total, $downPayment, 2);

        $installments = $this->buildInstallments($financed, $installmentsCount, $firstDueDate, $total, $costToRecover, $downPayment);

        return [
            'total' => $total,
            'total_cost' => $totalCost,
            'total_freight' => $totalFreight,
            'financed_amount' => $financed,
            'down_payment' => $downPayment,
            'down_payment_cost' => $this->costShare($costToRecover, $downPayment, $total),
            'installment_amount' => $installments[0]['amount'] ?? '0.00',
            'installments' => $installments,
        ];
    }

    /**
     * Precio unitario de un ítem: costo + flete + margen.
     */
    public function unitPrice(string|int|float $unitCost, string|int|float $unitMargin, string|int|float $unitFreight = 0): string
    {
        return bcadd(bcadd($this->money($unitCost), $this->money($unitFreight), 2), $this->money($unitMargin), 2);
    }

    /**
     * @return list<Installment>
     */
    private function buildInstallments(
        string $financed,
        int $count,
        CarbonInterface|string $firstDueDate,
        string $total,
        string $costToRecover,
        string $downPayment,
    ): array {
        if (bccomp($financed, '0', 2) === 0) {
            return [];
        }

        // Monto de cuota redondeado hacia abajo; la última absorbe el redondeo.
        $base = bcdiv($financed, (string) $count, 2);
        $lastAmount = bcsub($financed, bcmul($base, (string) ($count - 1), 2), 2);
        $firstDue = CarbonImmutable::parse($firstDueDate)->startOfDay();

        // El costo (con flete) no cubierto por el anticipo se reparte entre las cuotas; la última absorbe el redondeo.
        $costToSpread = bcsub($costToRecover, $this->costShare($costToRecover, $downPayment, $total), 2);
        $assignedCost = '0.00';

        $installments = [];

        for ($number = 1; $number <= $count; $number++) {
            $amount = $number === $count ? $lastAmount : $base;

            if ($number === $count) {
                $costPortion = bcsub($costToSpread, $assignedCost, 2);
            } else {
                $costPortion = $this->costShare($costToRecover, $amount, $total);
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
