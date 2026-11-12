<?php

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Sale;

it('numera ventas, cobros y órdenes de compra con prefijo y cinco dígitos', function () {
    expect(Sale::numberFor(1))->toBe('V-00001')
        ->and(Payment::numberFor(42))->toBe('C-00042')
        ->and(PurchaseOrder::numberFor(7))->toBe('OC-00007')
        ->and(Sale::numberFor(123456))->toBe('V-123456')
        ->and(Sale::numberFor(null))->toBe('—');

    $sale = new Sale;
    $sale->id = 12;

    expect($sale->number())->toBe('V-00012');
});
