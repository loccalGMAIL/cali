<?php

use App\Http\Controllers\SharedPaymentReceiptController;
use Illuminate\Support\Facades\Route;

// Landing pública. Cualquier otro host que no sea landing ni panel devuelve 404.
Route::domain(config('domains.landing'))->group(function () {
    Route::view('/', 'landing')->name('landing');
});

// Comprobante de pago que el cliente abre desde WhatsApp, sin usuario: el enlace va firmado y vence.
Route::domain(config('domains.panel'))->middleware(['signed', 'throttle:60,1'])->group(function () {
    Route::get('comprobante/{payment}', SharedPaymentReceiptController::class)->name('payments.receipt.shared');
});
