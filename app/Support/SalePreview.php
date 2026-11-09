<?php

namespace App\Support;

use App\Models\FinancingPlan;
use App\Services\SaleCalculator;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Vista previa de la venta en el formulario: totales, monto de cuota y cronograma.
 * Usa el mismo SaleCalculator que la venta real, así lo que se ve es lo que se guarda.
 */
class SalePreview
{
    /**
     * @param  array<array-key, array{product_id?: mixed, quantity?: mixed, unit_cost?: mixed, unit_margin?: mixed}>  $items
     */
    public static function render(array $items, mixed $planId, mixed $firstDueDate, mixed $downPayment): HtmlString
    {
        $items = array_values(array_filter($items, fn (array $item): bool => filled($item['product_id'] ?? null)));

        if ($items === [] || blank($planId)) {
            return new HtmlString('<span class="text-sm text-gray-500">Elegí al menos un producto y un plan para ver el resumen.</span>');
        }

        $plan = FinancingPlan::find($planId);

        if ($plan === null) {
            return new HtmlString('');
        }

        $calculator = new SaleCalculator;

        $lines = array_map(fn (array $item): array => [
            'quantity' => (int) ($item['quantity'] ?? 0),
            'unit_cost' => self::number($item['unit_cost'] ?? 0),
            'unit_price' => $calculator->unitPrice(self::number($item['unit_cost'] ?? 0), self::number($item['unit_margin'] ?? 0)),
        ], $items);

        try {
            $result = $calculator->calculate(
                $lines,
                self::number($downPayment),
                $plan->installments_count,
                filled($firstDueDate) ? $firstDueDate : today()->addMonth(),
            );
        } catch (InvalidArgumentException $exception) {
            return new HtmlString('<span class="text-sm" style="color: var(--danger-600, #dc2626)">'.e($exception->getMessage()).'</span>');
        }

        return new HtmlString(self::html($result, $plan));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function html(array $result, FinancingPlan $plan): string
    {
        $rows = '';

        foreach ($result['installments'] as $installment) {
            $rows .= '<tr>'
                .'<td style="padding:2px 12px 2px 0">'.$installment['number'].'</td>'
                .'<td style="padding:2px 12px">'.$installment['due_date']->format('d/m/Y').'</td>'
                .'<td style="padding:2px 0 2px 12px; text-align:right">'.Money::format($installment['amount']).'</td>'
                .'</tr>';
        }

        $schedule = $rows === ''
            ? '<p class="text-sm text-gray-500">Sin cuotas: el anticipo cubre el total.</p>'
            : '<table class="text-sm" style="margin-top:8px"><thead><tr style="text-align:left">'
                .'<th style="padding:2px 12px 2px 0">Cuota</th><th style="padding:2px 12px">Vence</th><th style="padding:2px 0 2px 12px; text-align:right">Monto</th>'
                .'</tr></thead><tbody>'.$rows.'</tbody></table>';

        $summary = [
            'Costo' => Money::format($result['total_cost']),
            'Margen' => Money::format(bcsub($result['total'], $result['total_cost'], 2)),
            'Total de la venta (costo + margen)' => Money::format($result['total']),
            'Anticipo' => Money::format($result['down_payment']),
        ];

        $lines = '';

        foreach ($summary as $label => $value) {
            $lines .= '<div style="display:flex; justify-content:space-between; gap:24px"><span>'.e($label).'</span><span>'.$value.'</span></div>';
        }

        $cuota = $result['installments'] === []
            ? ''
            : '<div style="margin-top:8px; font-weight:600">'.count($result['installments']).' cuotas de '.Money::format($result['installment_amount']).'</div>';

        return '<div class="text-sm" style="max-width:420px">'.$lines
            .'<div style="display:flex; justify-content:space-between; gap:24px; margin-top:6px; font-weight:700; font-size:1.05rem"><span>A financiar en '.e($plan->name).'</span><span>'.Money::format($result['financed_amount']).'</span></div>'
            .$cuota.$schedule.'</div>';
    }

    private static function number(mixed $value): string
    {
        return is_numeric($value) ? (string) $value : '0';
    }
}
