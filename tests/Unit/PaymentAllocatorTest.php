<?php

use App\Services\PaymentAllocator;

function pending(array $balances): array
{
    $list = [];

    foreach (array_values($balances) as $index => $balance) {
        $list[] = ['id' => $index + 1, 'balance' => $balance];
    }

    return $list;
}

it('imputa primero a la cuota más vieja', function () {
    $allocations = (new PaymentAllocator)->allocate(pending(['300.00', '300.00', '300.00']), '300.00');

    expect($allocations)->toBe([
        ['installment_id' => 1, 'applied' => '300.00', 'remaining' => '0.00'],
    ]);
});

it('reparte un cobro entre varias cuotas en orden', function () {
    $allocations = (new PaymentAllocator)->allocate(pending(['300.00', '300.00', '300.00']), '700.00');

    expect($allocations)->toBe([
        ['installment_id' => 1, 'applied' => '300.00', 'remaining' => '0.00'],
        ['installment_id' => 2, 'applied' => '300.00', 'remaining' => '0.00'],
        ['installment_id' => 3, 'applied' => '100.00', 'remaining' => '200.00'],
    ]);
});

it('admite un pago parcial sobre una sola cuota', function () {
    $allocations = (new PaymentAllocator)->allocate(pending(['300.00', '300.00']), '120.50');

    expect($allocations)->toBe([
        ['installment_id' => 1, 'applied' => '120.50', 'remaining' => '179.50'],
    ]);
});

it('respeta el saldo ya pagado de una cuota parcial', function () {
    $allocations = (new PaymentAllocator)->allocate(pending(['80.00', '300.00']), '100.00');

    expect($allocations)->toBe([
        ['installment_id' => 1, 'applied' => '80.00', 'remaining' => '0.00'],
        ['installment_id' => 2, 'applied' => '20.00', 'remaining' => '280.00'],
    ]);
});

it('permite saldar toda la deuda', function () {
    $allocations = (new PaymentAllocator)->allocate(pending(['300.00', '150.25']), '450.25');

    expect($allocations)->toHaveCount(2)
        ->and(array_column($allocations, 'remaining'))->toBe(['0.00', '0.00']);
});

it('rechaza un cobro mayor a la deuda total', function () {
    (new PaymentAllocator)->allocate(pending(['300.00', '150.25']), '450.26');
})->throws(InvalidArgumentException::class, 'supera la deuda');

it('rechaza un cobro en cero o negativo', function (string $amount) {
    (new PaymentAllocator)->allocate(pending(['300.00']), $amount);
})->with(['0', '0.00', '-10'])->throws(InvalidArgumentException::class);

it('rechaza cualquier cobro si el cliente no tiene deuda', function () {
    (new PaymentAllocator)->allocate([], '10.00');
})->throws(InvalidArgumentException::class);

it('lo imputado siempre suma el monto cobrado', function (array $balances, string $amount) {
    $allocations = (new PaymentAllocator)->allocate(pending($balances), $amount);

    $sum = array_reduce($allocations, fn ($carry, $a) => bcadd($carry, $a['applied'], 2), '0.00');

    expect($sum)->toBe(number_format((float) $amount, 2, '.', ''));
})->with([
    [['333.33', '333.33', '333.34'], '500.01'],
    [['0.01', '0.02', '0.03'], '0.05'],
    [['1000.00'], '999.99'],
]);
