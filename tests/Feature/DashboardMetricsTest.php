<?php

use App\Actions\CancelPayment;
use App\Actions\CancelPurchase;
use App\Actions\CancelSale;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DashboardMetrics;
use Carbon\CarbonImmutable;

/**
 * Venta de costo 600 + margen 600 = 1200 en 3 cuotas de 400, cada una con
 * 200 de costo y 200 de ganancia.
 */
function metricSale(Customer $customer, User $user, string $firstDue, int $down = 0): Sale
{
    $plan = FinancingPlan::factory()->create(['installments_count' => 3]);
    $product = Product::factory()->create(['last_cost' => 600]);

    return app(CreateSale::class)->handle([
        'customer_id' => $customer->id,
        'seller_id' => $user->id,
        'financing_plan_id' => $plan->id,
        'date' => today()->toDateString(),
        'first_due_date' => $firstDue,
        'down_payment' => $down,
    ], [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 600, 'unit_margin' => 600]], $user);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create();
    $this->metrics = app(DashboardMetrics::class);
});

it('sin datos todo da cero', function () {
    expect($this->metrics->receivable())->toBe(['amount' => '0.00', 'count' => 0])
        ->and($this->metrics->overdue())->toBe(['amount' => '0.00', 'count' => 0])
        ->and($this->metrics->futureProfit())->toBe('0.00')
        ->and($this->metrics->pendingPurchase())->toBe(['units' => 0, 'cost' => '0.00'])
        ->and($this->metrics->openPurchaseOrdersCount())->toBe(0)
        ->and(array_sum(array_column($this->metrics->projection(), 'amount')))->toEqual(0);
});

it('calcula el total por cobrar y la ganancia futura', function () {
    metricSale($this->customer, $this->admin, today()->addMonth()->toDateString());

    expect($this->metrics->receivable())->toBe(['amount' => '1200.00', 'count' => 3])
        ->and($this->metrics->futureProfit())->toBe('600.00');
});

it('la ganancia futura baja de forma proporcional al cobrar', function () {
    metricSale($this->customer, $this->admin, today()->addMonth()->toDateString());
    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 100], $this->admin);

    // Queda 300 de la 1ª cuota (ganancia 150) + 2 cuotas completas (ganancia 200 c/u).
    expect($this->metrics->receivable()['amount'])->toBe('1100.00')
        ->and($this->metrics->futureProfit())->toBe('550.00');
});

it('al cobrar una cuota completa sale de la deuda y de la ganancia', function () {
    metricSale($this->customer, $this->admin, today()->addMonth()->toDateString());
    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 400], $this->admin);

    expect($this->metrics->receivable())->toBe(['amount' => '800.00', 'count' => 2])
        ->and($this->metrics->futureProfit())->toBe('400.00');
});

it('separa lo vencido en monto y cantidad', function () {
    metricSale($this->customer, $this->admin, today()->subMonths(2)->toDateString());

    // Vencen hace 2 meses, hace 1 mes y hoy: hoy todavía no está vencida.
    expect($this->metrics->overdue())->toBe(['amount' => '800.00', 'count' => 2])
        ->and($this->metrics->receivable()['count'])->toBe(3);
});

it('excluye lo anulado de la deuda, la ganancia y lo vencido', function () {
    $sale = metricSale($this->customer, $this->admin, today()->subMonths(2)->toDateString());
    app(CancelSale::class)->handle($sale, 'Error', $this->admin);

    expect($this->metrics->receivable())->toBe(['amount' => '0.00', 'count' => 0])
        ->and($this->metrics->overdue())->toBe(['amount' => '0.00', 'count' => 0])
        ->and($this->metrics->futureProfit())->toBe('0.00');
});

it('proyecta por mes el monto y la ganancia, y suma lo vencido al mes actual', function () {
    $today = CarbonImmutable::parse('2026-11-15');
    metricSale($this->customer, $this->admin, '2026-10-20'); // vencidas: oct (vencida), nov, dic

    $projection = $this->metrics->projection(12, $today);

    expect($projection)->toHaveCount(12)
        ->and($projection[0]['month']->format('Y-m'))->toBe('2026-11')
        // oct (vencida) + nov caen en el mes actual
        ->and($projection[0]['amount'])->toBe('800.00')
        ->and($projection[0]['profit'])->toBe('400.00')
        ->and($projection[1]['month']->format('Y-m'))->toBe('2026-12')
        ->and($projection[1]['amount'])->toBe('400.00')
        ->and($projection[1]['profit'])->toBe('200.00')
        ->and($projection[2]['amount'])->toBe('0.00');
});

it('la proyección no incluye cuotas más allá de los 12 meses ni cuotas pagadas', function () {
    $today = CarbonImmutable::parse('2026-11-15');
    metricSale($this->customer, $this->admin, '2027-11-10'); // cuota 1 en nov 2027 (mes 13): afuera
    $paid = Customer::factory()->create();
    metricSale($paid, $this->admin, '2026-12-01');
    app(RegisterPayment::class)->handle(['customer_id' => $paid->id, 'amount' => 1200], $this->admin);

    $total = array_reduce($this->metrics->projection(12, $today), fn ($c, $m) => bcadd($c, $m['amount'], 2), '0.00');

    expect($total)->toBe('0.00');
});

it('el total proyectado más lo lejano coincide con el total por cobrar', function () {
    metricSale($this->customer, $this->admin, today()->subMonth()->toDateString());
    app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 250], $this->admin);

    $total = array_reduce($this->metrics->projection(12), fn ($c, $m) => bcadd($c, $m['amount'], 2), '0.00');

    expect($total)->toBe($this->metrics->receivable()['amount'])->toBe('950.00');
});

it('el cobrado del mes incluye el anticipo y excluye los cobros anulados', function () {
    metricSale($this->customer, $this->admin, today()->addMonth()->toDateString(), down: 200);
    $payment = app(RegisterPayment::class)->handle(['customer_id' => $this->customer->id, 'amount' => 300], $this->admin);

    $from = today()->startOfMonth();
    $to = today()->endOfMonth();

    expect($this->metrics->collectedBetween($from, $to))->toBe('500.00');

    app(CancelPayment::class)->handle($payment, 'Error', $this->admin);

    expect($this->metrics->collectedBetween($from, $to))->toBe('200.00');
});

it('el cobrado del mes anterior se mide aparte', function () {
    metricSale($this->customer, $this->admin, today()->addMonth()->toDateString());
    app(RegisterPayment::class)->handle([
        'customer_id' => $this->customer->id,
        'amount' => 400,
        'date' => today()->subMonthNoOverflow()->toDateString(),
    ], $this->admin);

    $lastMonth = today()->subMonthNoOverflow();

    expect($this->metrics->collectedBetween($lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()))->toBe('400.00')
        ->and($this->metrics->collectedBetween(today()->startOfMonth(), today()->endOfMonth()))->toBe('0.00');
});

it('suma lo pendiente de compra en unidades y a costo de venta', function () {
    $product = Product::factory()->create();
    $item = soldItem($this->admin, $product, 3, 1000);
    soldItem($this->admin, $product, 1, '250.50');

    expect($this->metrics->pendingPurchase())->toBe(['units' => 4, 'cost' => '3250.50']);

    issuedOrder($this->admin, Supplier::factory()->create(), [$item]);

    expect($this->metrics->pendingPurchase())->toBe(['units' => 1, 'cost' => '250.50']);
});

it('cuenta las órdenes de compra abiertas', function () {
    $product = Product::factory()->create();
    $supplier = Supplier::factory()->create();
    issuedOrder($this->admin, $supplier, [soldItem($this->admin, $product)]);
    receivedPurchase($this->admin, $supplier, $product, 1, 100, 'A-1');

    expect($this->metrics->openPurchaseOrdersCount())->toBe(1);
});

it('las compras del mes excluyen las anuladas', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $buy = fn (string $invoice) => receivedPurchase($this->admin, $supplier, $product, 1, 1000, $invoice);

    $buy('A-1');
    $second = $buy('A-2');

    expect($this->metrics->purchasedBetween(today()->startOfMonth(), today()->endOfMonth()))->toBe('2000.00');

    app(CancelPurchase::class)->handle($second, 'Error', $this->admin);

    expect($this->metrics->purchasedBetween(today()->startOfMonth(), today()->endOfMonth()))->toBe('1000.00');
});
