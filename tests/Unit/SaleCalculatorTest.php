<?php

use App\Services\SaleCalculator;

function calc(array $lines, $down, $surcharge, int $count, string $firstDue = '2026-12-10'): array
{
    return (new SaleCalculator)->calculate($lines, $down, $surcharge, $count, $firstDue);
}

function line(int $quantity, $price, $cost): array
{
    return ['quantity' => $quantity, 'unit_price' => $price, 'unit_cost' => $cost];
}

it('calcula subtotal, financiado y total con anticipo y recargo', function () {
    $result = calc([line(1, 1000, 600)], 200, 20, 3);

    expect($result['subtotal'])->toBe('1000.00')
        ->and($result['total_cost'])->toBe('600.00')
        ->and($result['balance_to_finance'])->toBe('800.00')
        ->and($result['financed_amount'])->toBe('960.00')
        ->and($result['total'])->toBe('1160.00')
        ->and($result['installment_amount'])->toBe('320.00')
        ->and($result['installments'])->toHaveCount(3);
});

it('suma varias líneas con cantidades', function () {
    $result = calc([line(2, '1500.50', 900), line(3, 100, 40)], 0, 0, 1);

    expect($result['subtotal'])->toBe('3301.00')
        ->and($result['total_cost'])->toBe('1920.00');
});

it('redondea la cuota hacia abajo y la última absorbe la diferencia', function () {
    $result = calc([line(1, 1000, 600)], 0, 10, 3);

    $amounts = array_column($result['installments'], 'amount');

    expect($result['financed_amount'])->toBe('1100.00')
        ->and($amounts)->toBe(['366.66', '366.66', '366.68'])
        ->and(array_reduce($amounts, fn ($carry, $a) => bcadd($carry, $a, 2), '0.00'))->toBe('1100.00');
});

it('el total de cuotas más el anticipo siempre da el total de la venta', function (string $price, string $down, string $surcharge, int $count) {
    $result = calc([line(1, $price, 100)], $down, $surcharge, $count);

    $sum = array_reduce($result['installments'], fn ($carry, $i) => bcadd($carry, $i['amount'], 2), '0.00');

    expect(bcadd($sum, $result['down_payment'], 2))->toBe($result['total']);
})->with([
    ['999.99', '0', '0', 7],
    ['1234.56', '100.10', '33.33', 8],
    ['50000', '12345.67', '18.5', 6],
    ['0.10', '0', '15', 3],
]);

it('reparte el costo entre cuotas y anticipo sin perder centavos', function (string $price, string $cost, string $down, string $surcharge, int $count) {
    $result = calc([line(1, $price, $cost)], $down, $surcharge, $count);

    $costSum = array_reduce($result['installments'], fn ($carry, $i) => bcadd($carry, $i['cost_portion'], 2), '0.00');

    expect(bcadd($costSum, $result['down_payment_cost'], 2))->toBe($result['total_cost']);
})->with([
    ['1000', '600', '0', '10', 3],
    ['1000', '600', '250', '20', 4],
    ['777.77', '333.33', '100', '33.33', 7],
]);

it('calcula la porción de costo proporcional a cada cuota', function () {
    $result = calc([line(1, 1000, 600)], 200, 20, 3);

    // total 1160, costo 600: anticipo 200/1160 -> 103.45; cuota 320/1160 -> 165.52
    expect($result['down_payment_cost'])->toBe('103.45')
        ->and($result['installments'][0]['cost_portion'])->toBe('165.52')
        ->and($result['installments'][1]['cost_portion'])->toBe('165.52')
        ->and($result['installments'][2]['cost_portion'])->toBe('165.51');
});

it('vence cada cuota un mes después sin desbordar el fin de mes', function () {
    $result = calc([line(1, 900, 500)], 0, 0, 3, '2026-01-31');

    $dates = array_map(fn ($i) => $i['due_date']->toDateString(), $result['installments']);

    expect($dates)->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

it('un plan de una cuota al 0% equivale a contado', function () {
    $result = calc([line(1, 1000, 600)], 0, 0, 1);

    expect($result['total'])->toBe('1000.00')
        ->and($result['installments'])->toHaveCount(1)
        ->and($result['installments'][0]['amount'])->toBe('1000.00');
});

it('no genera cuotas si el anticipo cubre todo el precio', function () {
    $result = calc([line(1, 1000, 600)], 1000, 30, 6);

    expect($result['financed_amount'])->toBe('0.00')
        ->and($result['total'])->toBe('1000.00')
        ->and($result['installments'])->toBe([])
        ->and($result['down_payment_cost'])->toBe('600.00');
});

it('rechaza un anticipo mayor al precio de contado', function () {
    calc([line(1, 1000, 600)], 1000.01, 10, 3);
})->throws(InvalidArgumentException::class);

it('rechaza un anticipo negativo', function () {
    calc([line(1, 1000, 600)], -1, 10, 3);
})->throws(InvalidArgumentException::class);

it('rechaza una cantidad de cuotas menor a 1', function () {
    calc([line(1, 1000, 600)], 0, 10, 0);
})->throws(InvalidArgumentException::class);

it('no se rompe con una venta sin líneas', function () {
    $result = calc([], 0, 10, 3);

    expect($result['total'])->toBe('0.00')
        ->and($result['installments'])->toBe([]);
});
