<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentReceipt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprobante de pago que abre el cliente desde el enlace que le llega por WhatsApp. No pide
 * usuario: la ruta va firmada y vence (ver {@see PaymentReceipt::sharedUrl()}), así que solo se
 * puede abrir con el enlace que se le mandó.
 */
class SharedPaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment, PaymentReceipt $receipt): Response
    {
        return $receipt->pdf($payment)->stream(PaymentReceipt::fileName($payment));
    }
}
