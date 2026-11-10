<?php

use App\Services\SaleCalculator;

function calc(array $lines, $down, int $count, string $firstDue = '2026-12-10'): array
{
    return (new SaleCalculator)->calculate($lines, $down, $count, $firstDue);
}

function line(int $quantity, $price, $cost): array
{
    return ['quantity' => $quantity, 'unit_price' => $price, 'unit_cost' => $cost];
}

it('el precio unitario es costo más margen', function () {
    expect((new SaleCalculator)->unitPrice(600, '400.50'))->toBe('1000.50')
        ->and((new SaleCalculator)->unitPrice('0.10', 0))->toBe('0.10');
});

it('el precio unitario suma el flete al costo y al margen', function () {
    expect((new SaleCalculator)->unitPrice(600, 400, 50))->toBe('1050.00')
        ->and((new SaleCalculator)->unitPrice('600.10', '0', '0.25'))->toBe('600.35');
});

it('el flete integra el total y se divide en las cuotas junto con el resto', function () {
    // (costo 600 + flete 100 + margen 500) × 2 = 2400; anticipo 300; quedan 2100 en 3 cuotas de 700.
    $result = calc([['quantity' => 2, 'unit_price' => 1200, 'unit_cost' => 600, 'unit_freight' => 100]], 300, 3);

    expect($result['total'])->toBe('2400.00')
        ->and($result['total_cost'])->toBe('1200.00')
        ->and($result['total_freight'])->toBe('200.00')
        ->and($result['financed_amount'])->toBe('2100.00')
        ->and(array_column($result['installments'], 'amount'))->toBe(['700.00', '700.00', '700.00']);
});

it('el flete no es ganancia: se reparte como costo entre anticipo y cuotas', function () {
    // total 1200, costo 600 + flete 100 = 700 a recuperar; anticipo 300 -> 175; cuotas de 300 -> 175 cada una.
    $result = calc([['quantity' => 1, 'unit_price' => 1200, 'unit_cost' => 600, 'unit_freight' => 100]], 300, 3);

    $costSum = array_reduce($result['installments'], fn ($carry, $i) => bcadd($carry, $i['cost_portion'], 2), '0.00');

    expect($result['down_payment_cost'])->toBe('175.00')
        ->and(array_column($result['installments'], 'cost_portion'))->toBe(['175.00', '175.00', '175.00'])
        ->and(bcadd($costSum, $result['down_payment_cost'], 2))->toBe(bcadd($result['total_cost'], $result['total_freight'], 2));
});

it('sin flete el total de flete es cero', function () {
    expect(calc([line(1, 1200, 600)], 0, 3)['total_freight'])->toBe('0.00');
});

it('divide lo que queda después del anticipo en cuotas iguales, sin recargo', function () {
    // costo 600 + margen 600 = 1200; anticipo 300; quedan 900 en 3 cuotas de 300.
    $result = calc([line(1, 1200, 600)], 300, 3);

    expect($result['total'])->toBe('1200.00')
        ->and($result['total_cost'])->toBe('600.00')
        ->and($result['down_payment'])->toBe('300.00')
        ->and($result['financed_amount'])->toBe('900.00')
        ->and($result['installment_amount'])->toBe('300.00')
        ->and($result['installments'])->toHaveCount(3);
});

it('suma varias líneas con cantidades', function () {
    $result = calc([line(2, '1500.50', 900), line(3, 100, 40)], 0, 1);

    expect($result['total'])->toBe('3301.00')
        ->and($result['total_cost'])->toBe('1920.00');
});

it('redondea la cuota hacia abajo y la última absorbe la diferencia', function () {
    $result = calc([line(1, 1100, 600)], 0, 3);

    $amounts = array_column($result['installments'], 'amount');

    expect($result['financed_amount'])->toBe('1100.00')
        ->and($amounts)->toBe(['366.66', '366.66', '366.68'])
        ->and(array_reduce($amounts, fn ($carry, $a) => bcadd($carry, $a, 2), '0.00'))->toBe('1100.00');
});

it('el total de cuotas más el anticipo siempre da el total de la venta', function (string $price, string $down, int $count) {
    $result = calc([line(1, $price, 100)], $down, $count);

    $sum = array_reduce($result['installments'], fn ($carry, $i) => bcadd($carry, $i['amount'], 2), '0.00');

    expect(bcadd($sum, $result['down_payment'], 2))->toBe($result['total']);
})->with([
    ['999.99', '0', 7],
    ['1234.56', '100.10', 8],
    ['50000', '12345.67', 6],
    ['0.10', '0', 3],
]);

it('reparte el costo entre cuotas y anticipo sin perder centavos', function (string $price, string $cost, string $down, int $count) {
    $result = calc([line(1, $price, $cost)], $down, $count);

    $costSum = array_reduce($result['installments'], fn ($carry, $i) => bcadd($carry, $i['cost_portion'], 2), '0.00');

    expect(bcadd($costSum, $result['down_payment_cost'], 2))->toBe($result['total_cost']);
})->with([
    ['1000', '600', '0', 3],
    ['1000', '600', '250', 4],
    ['777.77', '333.33', '100', 7],
]);

it('calcula la porción de costo proporcional a cada cuota', function () {
    $result = calc([line(1, 1160, 600)], 200, 3);

    // total 1160, costo 600: anticipo 200/1160 -> 103.45; cuota 320/1160 -> 165.52
    expect($result['down_payment_cost'])->toBe('103.45')
        ->and($result['installments'][0]['cost_portion'])->toBe('165.52')
        ->and($result['installments'][1]['cost_portion'])->toBe('165.52')
        ->and($result['installments'][2]['cost_portion'])->toBe('165.51');
});

it('vence cada cuota un mes después sin desbordar el fin de mes', function () {
    $result = calc([line(1, 900, 500)], 0, 3, '2026-01-31');

    $dates = array_map(fn ($i) => $i['due_date']->toDateString(), $result['installments']);

    expect($dates)->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

it('un plan de una cuota equivale a contado', function () {
    $result = calc([line(1, 1000, 600)], 0, 1);

    expect($result['total'])->toBe('1000.00')
        ->and($result['installments'])->toHaveCount(1)
        ->and($result['installments'][0]['amount'])->toBe('1000.00');
});

it('no genera cuotas si el anticipo cubre todo el total', function () {
    $result = calc([line(1, 1000, 600)], 1000, 6);

    expect($result['financed_amount'])->toBe('0.00')
        ->and($result['total'])->toBe('1000.00')
        ->and($result['installments'])->toBe([])
        ->and($result['down_payment_cost'])->toBe('600.00');
});

it('rechaza un anticipo mayor al total', function () {
    calc([line(1, 1000, 600)], 1000.01, 3);
})->throws(InvalidArgumentException::class);

it('rechaza un anticipo negativo', function () {
    calc([line(1, 1000, 600)], -1, 3);
})->throws(InvalidArgumentException::class);

it('rechaza una cantidad de cuotas menor a 1', function () {
    calc([line(1, 1000, 600)], 0, 0);
})->throws(InvalidArgumentException::class);

it('no se rompe con una venta sin líneas', function () {
    $result = calc([], 0, 3);

    expect($result['total'])->toBe('0.00')
        ->and($result['installments'])->toBe([]);
});
