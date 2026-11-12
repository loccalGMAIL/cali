<?php

use App\Actions\CancelPayment;
use App\Actions\CancelSale;
use App\Actions\RegisterPayment;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Services\Reports\MonthlyCollectionReport;
use App\Services\Reports\PaymentsReport;
use App\Services\Reports\PendingInstallmentsReport;
use App\Services\Reports\SalesReport;
use Carbon\CarbonImmutable;

/**
 * Hoy es 12/11/2026. Ana compró con primera cuota en septiembre: debe dos cuotas vencidas
 * (30/09 y 30/10) y una que vence el 30/11. Beto empieza a pagar en enero.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00'));

    $this->admin = User::factory()->admin()->create(['name' => 'Admin']);
    $this->seller = User::factory()->seller()->create(['name' => 'Vendedor']);
    $this->ana = Customer::factory()->create(['name' => 'Ana', 'city' => 'Córdoba']);
    $this->beto = Customer::factory()->create(['name' => 'Beto', 'city' => 'Rosario']);
});

describe('cuotas pendientes por cliente', function () {
    beforeEach(function () {
        $this->report = app(PendingInstallmentsReport::class);
        reportSale($this->ana, $this->admin, '2026-09-01');
        reportSale($this->beto, $this->admin, '2027-01-01');
    });

    it('reparte la deuda de cada cliente en vencido y una columna por mes', function () {
        $data = $this->report->build($this->report->normalize(['months' => 6]));

        expect(array_keys($data['columns']))->toBe(['overdue', '2026-11', '2026-12', '2027-01', '2027-02', '2027-03', '2027-04', 'later'])
            ->and($data['columns']['2026-11'])->toBe('Nov-26')
            ->and($data['rows'])->toHaveCount(2)
            ->and($data['rows'][0]['customer']->name)->toBe('Ana')
            ->and($data['rows'][0]['cells']['overdue'])->toBe('800.00')
            ->and($data['rows'][0]['cells']['2026-11'])->toBe('400.00')
            ->and($data['rows'][0]['total'])->toBe('1200.00')
            ->and($data['rows'][1]['cells']['overdue'])->toBe('0.00')
            ->and($data['rows'][1]['cells']['2027-01'])->toBe('400.00')
            ->and($data['rows'][1]['cells']['2027-03'])->toBe('400.00')
            ->and($data['totals']['overdue'])->toBe('800.00')
            ->and($data['total'])->toBe('2400.00');
    });

    it('lo que vence después del último mes mostrado va a "más adelante"', function () {
        reportSale($this->beto, $this->admin, '2027-04-01');

        $data = $this->report->build($this->report->normalize(['months' => 6]));

        expect($data['rows'][1]['cells']['2027-04'])->toBe('400.00')
            ->and($data['rows'][1]['cells']['later'])->toBe('800.00')
            ->and($data['rows'][1]['total'])->toBe('2400.00');
    });

    it('descuenta los cobros parciales y coincide con el total por cobrar del dashboard', function () {
        app(RegisterPayment::class)->handle(['customer_id' => $this->ana->id, 'amount' => 500], $this->admin);

        $data = $this->report->build($this->report->normalize([]));

        expect($data['rows'][0]['cells']['overdue'])->toBe('300.00')
            ->and($data['total'])->toBe('1900.00')
            ->and($data['total'])->toBe(app(DashboardMetrics::class)->receivable()['amount']);
    });

    it('no incluye ventas anuladas ni clientes sin deuda', function () {
        $cancelled = reportSale(Customer::factory()->create(['name' => 'Carla']), $this->admin, '2026-12-01');
        app(CancelSale::class)->handle($cancelled, 'Error', $this->admin);
        Customer::factory()->create(['name' => 'Sin deuda']);

        $data = $this->report->build($this->report->normalize([]));

        expect(array_map(fn (array $row): string => $row['customer']->name, $data['rows']))->toBe(['Ana', 'Beto']);
    });

    it('filtra por clientes con vencido y por localidad', function () {
        $onlyOverdue = $this->report->build($this->report->normalize(['only_overdue' => '1']));
        $rosario = $this->report->build($this->report->normalize(['city' => 'Rosario']));

        expect($onlyOverdue['rows'])->toHaveCount(1)
            ->and($onlyOverdue['rows'][0]['customer']->name)->toBe('Ana')
            ->and($rosario['rows'])->toHaveCount(1)
            ->and($rosario['rows'][0]['customer']->name)->toBe('Beto')
            ->and($rosario['total'])->toBe('1200.00');
    });

    it('ignora filtros inválidos y usa los valores por defecto', function () {
        expect($this->report->normalize(['months' => 'abc', 'only_overdue' => 'x', 'city' => '  ']))
            ->toBe(['months' => 12, 'only_overdue' => false, 'city' => null]);
    });
});

describe('cobranza del mes', function () {
    beforeEach(function () {
        $this->report = app(MonthlyCollectionReport::class);
        reportSale($this->ana, $this->admin, '2026-09-01');
        reportSale($this->beto, $this->admin, '2027-01-01');
    });

    it('lista las cuotas del mes y las atrasadas, con los días de atraso', function () {
        $data = $this->report->build($this->report->normalize([]));

        expect($data['groups'])->toHaveCount(1)
            ->and($data['groups'][0]['customer']->name)->toBe('Ana')
            ->and(array_column($data['groups'][0]['lines'], 'days_late'))->toBe([43, 13, 0])
            ->and(array_column($data['groups'][0]['lines'], 'previous'))->toBe([true, true, false])
            ->and($data['groups'][0]['subtotal'])->toBe('1200.00')
            ->and($data['count'])->toBe(3)
            ->and($data['month_amount'])->toBe('400.00')
            ->and($data['previous_amount'])->toBe('800.00')
            ->and($data['total'])->toBe('1200.00');
    });

    it('sin atrasadas muestra solo lo que vence en el mes', function () {
        $data = $this->report->build($this->report->normalize(['include_overdue' => '0']));

        expect($data['count'])->toBe(1)
            ->and($data['total'])->toBe('400.00')
            ->and($data['previous_amount'])->toBe('0.00');
    });

    it('para un mes futuro suma lo ya vencido, pero no lo que vence en el medio', function () {
        $data = $this->report->build($this->report->normalize(['month' => '2027-01']));

        expect(array_map(fn (array $group): string => $group['customer']->name, $data['groups']))->toBe(['Ana', 'Beto'])
            ->and($data['groups'][0]['lines'])->toHaveCount(2)
            ->and($data['month_amount'])->toBe('400.00')
            ->and($data['previous_amount'])->toBe('800.00');
    });

    it('una cuota cobrada sale de la lista y una parcial muestra su saldo', function () {
        app(RegisterPayment::class)->handle(['customer_id' => $this->ana->id, 'amount' => 500], $this->admin);

        $data = $this->report->build($this->report->normalize([]));

        expect(array_column($data['groups'][0]['lines'], 'balance'))->toBe(['300.00', '400.00'])
            ->and($data['total'])->toBe('700.00');
    });

    it('filtra por localidad', function () {
        $data = $this->report->build($this->report->normalize(['month' => '2027-01', 'city' => 'Rosario']));

        expect($data['groups'])->toHaveCount(1)
            ->and($data['groups'][0]['customer']->name)->toBe('Beto');
    });
});

describe('cobros realizados', function () {
    beforeEach(function () {
        $this->report = app(PaymentsReport::class);
        reportSale($this->ana, $this->admin, '2026-09-01', date: '2026-08-15');

        $pay = fn (float $amount, string $date, User $user, PaymentMethod $method = PaymentMethod::Cash) => app(RegisterPayment::class)
            ->handle(['customer_id' => $this->ana->id, 'amount' => $amount, 'date' => $date, 'method' => $method], $user);

        $pay(100, '2026-10-31', $this->admin);
        $pay(400, '2026-11-01', $this->admin);
        $pay(300, '2026-11-30', $this->seller, PaymentMethod::Transfer);
        app(CancelPayment::class)->handle($pay(50, '2026-11-10', $this->admin), 'Error', $this->admin);

        // Anticipo de 200 en efectivo, cobrado por el vendedor al vender.
        reportSale($this->beto, $this->seller, '2027-01-01', date: '2026-11-10', downPayment: 200);
    });

    it('suma lo cobrado en el período, con anticipos y sin anulados', function () {
        $data = $this->report->build($this->report->normalize([]));

        expect($data['payments'])->toHaveCount(3)
            ->and($data['total'])->toBe('900.00')
            ->and($data['by_method'])->toBe(['Efectivo' => '600.00', 'Transferencia' => '300.00'])
            ->and($data['by_collector'])->toBe(['Admin' => '400.00', 'Vendedor' => '500.00']);
    });

    it('coincide con lo cobrado en el dashboard para el mismo rango', function () {
        $data = $this->report->build($this->report->normalize(['from' => '2026-11-01', 'to' => '2026-11-29']));

        expect($data['total'])->toBe('600.00')
            ->and($data['total'])->toBe(app(DashboardMetrics::class)->collectedBetween(
                CarbonImmutable::parse('2026-11-01'),
                CarbonImmutable::parse('2026-11-29'),
            ));
    });

    it('filtra por fechas, medio de pago y quien cobró', function () {
        $october = $this->report->build($this->report->normalize(['from' => '2026-10-01', 'to' => '2026-10-31']));
        $transfer = $this->report->build($this->report->normalize(['method' => 'transfer']));
        $bySeller = $this->report->build($this->report->normalize(['collected_by' => $this->seller->id]));

        expect($october['total'])->toBe('100.00')
            ->and($transfer['total'])->toBe('300.00')
            ->and($bySeller['total'])->toBe('500.00');
    });

    it('corrige un rango invertido y descarta valores inválidos', function () {
        expect($this->report->normalize(['from' => '2026-11-20', 'to' => '2026-11-05', 'method' => 'bitcoin', 'collected_by' => 'x']))
            ->toBe(['from' => '2026-11-05', 'to' => '2026-11-20', 'method' => null, 'collected_by' => null])
            ->and($this->report->normalize(['from' => 'no-es-fecha']))
            ->toMatchArray(['from' => '2026-11-01', 'to' => '2026-11-30']);
    });
});

describe('ventas y ganancia', function () {
    beforeEach(function () {
        $this->report = app(SalesReport::class);
        $this->heladera = Product::factory()->create(['name' => 'Heladera']);
        $this->cocina = Product::factory()->create(['name' => 'Cocina']);

        // 1200 de total, 600 de ganancia.
        reportSale($this->ana, $this->admin, '2026-12-01', date: '2026-11-03', product: $this->heladera);
        // 2 unidades con flete de 100: 2600 de total, 1200 de costo, 200 de flete, 1200 de ganancia.
        reportSale($this->beto, $this->seller, '2026-12-01', date: '2026-11-30', quantity: 2, freight: 100, product: $this->cocina);
        // Fuera del período y anulada: no cuentan.
        reportSale($this->ana, $this->admin, '2026-12-01', date: '2026-10-31', product: $this->heladera);
        app(CancelSale::class)->handle(
            reportSale($this->beto, $this->admin, '2026-12-01', date: '2026-11-05', product: $this->cocina),
            'Error',
            $this->admin,
        );
    });

    it('detalla las ventas del período con su ganancia', function () {
        $data = $this->report->build($this->report->normalize([]));

        expect($data['rows'])->toHaveCount(2)
            ->and($data['rows'][0])->toMatchArray(['total' => '1200.00', 'cost' => '600.00', 'freight' => '0.00', 'profit' => '600.00', 'margin' => '50.0'])
            ->and($data['rows'][1])->toMatchArray(['total' => '2600.00', 'cost' => '1200.00', 'freight' => '200.00', 'profit' => '1200.00', 'margin' => '46.1'])
            ->and($data['totals'])->toBe(['quantity' => 2, 'total' => '3800.00', 'cost' => '1800.00', 'freight' => '200.00', 'profit' => '1800.00', 'margin' => '47.3']);
    });

    it('agrupa por vendedor', function () {
        $data = $this->report->build($this->report->normalize(['group_by' => 'seller']));

        expect(array_column($data['rows'], 'label'))->toBe(['Admin', 'Vendedor'])
            ->and($data['rows'][1])->toMatchArray(['quantity' => 1, 'total' => '2600.00', 'profit' => '1200.00'])
            ->and($data['totals']['profit'])->toBe('1800.00');
    });

    it('agrupa por producto, con las unidades vendidas', function () {
        $data = $this->report->build($this->report->normalize(['group_by' => 'product']));

        expect(array_column($data['rows'], 'label'))->toBe(['Cocina', 'Heladera'])
            ->and($data['rows'][0])->toMatchArray(['quantity' => 2, 'total' => '2600.00', 'cost' => '1200.00', 'freight' => '200.00', 'profit' => '1200.00'])
            ->and($data['totals'])->toMatchArray(['quantity' => 3, 'total' => '3800.00', 'profit' => '1800.00']);
    });

    it('filtra por vendedor', function () {
        $data = $this->report->build($this->report->normalize(['seller_id' => $this->seller->id]));

        expect($data['rows'])->toHaveCount(1)
            ->and($data['totals']['total'])->toBe('2600.00');
    });
});
