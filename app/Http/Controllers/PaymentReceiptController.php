<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\BusinessSettings;
use App\Services\PaymentReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprobante de pago en PDF, para entregarle al cliente. No es un comprobante fiscal.
 */
class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment, PaymentReceipt $receipt, BusinessSettings $settings): Response
    {
        Gate::authorize('view', $payment);

        $business = $settings->all();

        return Pdf::loadView('payments.receipt', [
            'receipt' => $receipt->for($payment),
            'business' => $business,
            'logo' => $business['receipt_shows_business_header'] ? $settings->logoDataUri() : null,
        ])
            ->setPaper('a4')
            // Solo se incrustan los caracteres usados de la fuente: el PDF pasa de ~900 KB a unos pocos KB.
            ->setOption('isFontSubsettingEnabled', true)
            ->stream('comprobante-'.$payment->number().'.pdf');
    }
}
