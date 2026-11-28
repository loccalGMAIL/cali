<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentReceipt;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprobante de pago en PDF, para entregarle al cliente. No es un comprobante fiscal.
 */
class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment, PaymentReceipt $receipt): Response
    {
        Gate::authorize('view', $payment);

        return $receipt->pdf($payment)->stream(PaymentReceipt::fileName($payment));
    }
}
