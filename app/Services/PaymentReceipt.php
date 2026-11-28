<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Arma los datos del comprobante de un cobro: a qué cuotas de qué venta se imputó y cuántas cuotas
 * le quedaban al cliente. No incluye saldos ni el total adeudado.
 *
 * Las cuotas restantes se calculan a la fecha del cobro, no a la de impresión: solo cuentan este
 * cobro y los anteriores que estaban vigentes cuando se registró. Así el comprobante dice siempre
 * lo mismo, aunque se reimprima después de otros cobros o anulaciones.
 *
 * @phpstan-type ReceiptLine array{number: int, amount: string, partial: bool}
 * @phpstan-type ReceiptSale array{number: string, products: string, installments_count: int, lines: list<ReceiptLine>, remaining: int}
 * @phpstan-type Receipt array{
 *     number: string,
 *     date: string,
 *     method: string,
 *     amount: string,
 *     customer_name: string,
 *     customer_document: string|null,
 *     is_down_payment: bool,
 *     is_cancelled: bool,
 *     sales: list<ReceiptSale>
 * }
 */
class PaymentReceipt
{
    public function __construct(private BusinessSettings $settings) {}

    /**
     * El comprobante en PDF (A4), con el encabezado del negocio si está activado.
     */
    public function pdf(Payment $payment): Document
    {
        $business = $this->settings->all();

        return Pdf::loadView('payments.receipt', [
            'receipt' => $this->for($payment),
            'business' => $business,
            'logo' => $business['receipt_shows_business_header'] ? $this->settings->logoDataUri() : null,
        ])
            ->setPaper('a4')
            // Solo se incrustan los caracteres usados de la fuente: el PDF pasa de ~900 KB a unos pocos KB.
            ->setOption('isFontSubsettingEnabled', true);
    }

    /**
     * Nombre del archivo: `comprobante-C-00012.pdf`.
     */
    public static function fileName(Payment $payment): string
    {
        return 'comprobante-'.$payment->number().'.pdf';
    }

    /**
     * Enlace al PDF para mandarle al cliente: se abre sin usuario, pero va firmado (no se puede
     * cambiar el número de cobro para ver otro) y vence a los días que indica
     * `cali.shared_receipt_days`.
     */
    public static function sharedUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute(
            'payments.receipt.shared',
            now()->addDays((int) config('cali.shared_receipt_days')),
            ['payment' => $payment],
        );
    }

    /**
     * @return Receipt
     */
    public function for(Payment $payment): array
    {
        $payment->loadMissing(['customer', 'sale.items.product', 'installments.sale.items.product']);

        $sales = $payment->isDownPayment()
            ? collect([$payment->sale])->filter()
            : $payment->installments->map(fn (Installment $installment): ?Sale => $installment->sale)->filter()->unique('id');

        $paidAsOfPayment = $this->paidAsOf($payment, $sales->pluck('id')->all());

        return [
            'number' => $payment->number(),
            'date' => $payment->date->format('d/m/Y'),
            'method' => $payment->method->getLabel(),
            'amount' => $payment->amount,
            'customer_name' => $payment->customer->name,
            'customer_document' => $payment->customer->document_number,
            'is_down_payment' => $payment->isDownPayment(),
            'is_cancelled' => $payment->isCancelled(),
            'sales' => $sales->sortBy('id')
                ->map(fn (Sale $sale): array => $this->saleSection($payment, $sale, $paidAsOfPayment))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array{amount: string, paid: string}>  $paidAsOfPayment
     * @return ReceiptSale
     */
    private function saleSection(Payment $payment, Sale $sale, Collection $paidAsOfPayment): array
    {
        $isSettled = fn (int $installmentId): bool => ($state = $paidAsOfPayment->get($installmentId)) !== null
            && bccomp($state['paid'], $state['amount'], 2) >= 0;

        $lines = $payment->installments
            ->where('sale_id', $sale->getKey())
            ->sortBy('number')
            ->map(fn (Installment $installment): array => [
                'number' => $installment->number,
                'amount' => number_format((float) $installment->pivot->getAttribute('amount'), 2, '.', ''),
                'partial' => ! $isSettled($installment->getKey()),
            ])
            ->values()
            ->all();

        $saleInstallmentIds = Installment::query()->where('sale_id', $sale->getKey())->pluck('id');

        return [
            'number' => $sale->number(),
            'products' => $sale->items
                ->map(fn (SaleItem $item): string => ($item->quantity > 1 ? "{$item->quantity} × " : '').($item->product->name ?? '—'))
                ->implode(', '),
            'installments_count' => $sale->installments_count,
            'lines' => $lines,
            'remaining' => $saleInstallmentIds->reject(fn (int $id): bool => $isSettled($id))->count(),
        ];
    }

    /**
     * Monto de cada cuota de las ventas y cuánto se le había pagado con este cobro y los anteriores
     * que seguían vigentes en ese momento (los anulados después cuentan; los anulados antes, no).
     *
     * @param  list<int>  $saleIds
     * @return Collection<int, array{amount: string, paid: string}>
     */
    private function paidAsOf(Payment $payment, array $saleIds): Collection
    {
        return Installment::query()
            ->whereIn('sale_id', $saleIds)
            ->with(['payments' => fn (BelongsToMany $query): BelongsToMany => $query
                ->where('payments.id', '<=', $payment->getKey())
                ->where(fn (Builder $query): Builder => $query
                    ->whereKey($payment->getKey())
                    ->orWhereNull('payments.cancelled_at')
                    ->orWhere('payments.cancelled_at', '>', $payment->created_at)),
            ])
            ->get()
            ->mapWithKeys(fn (Installment $installment): array => [
                $installment->getKey() => [
                    'amount' => $installment->amount,
                    'paid' => $installment->payments->reduce(
                        fn (string $carry, Payment $applied): string => bcadd($carry, (string) $applied->pivot->getAttribute('amount'), 2),
                        '0.00',
                    ),
                ],
            ]);
    }
}
