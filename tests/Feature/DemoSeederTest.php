<?php

use App\Enums\InstallmentStatus;
use App\Enums\SaleStatus;
use App\Models\AccountMovement;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AccountService;
use App\Services\DashboardMetrics;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
});

it('carga clientes, productos, compras, ventas y cobros', function () {
    expect(Customer::count())->toBe(28)
        ->and(Product::count())->toBe(17)
        ->and(Purchase::count())->toBeGreaterThanOrEqual(12)
        ->and(Sale::count())->toBeGreaterThanOrEqual(30)
        ->and(Payment::count())->toBeGreaterThan(30)
        ->and(User::where('role', 'seller')->count())->toBe(2);
});

it('el saldo de la cuenta corriente de cada cliente coincide con su deuda en cuotas', function () {
    $accounts = app(AccountService::class);

    Customer::all()->each(function (Customer $customer) use ($accounts) {
        expect($accounts->balance($customer))->toBe($customer->debt(), "Cliente #{$customer->id}");
    });
});

it('el stock de cada producto coincide con la suma de sus movimientos y nunca es negativo', function () {
    Product::all()->each(function (Product $product) {
        expect($product->stock)->toBeGreaterThanOrEqual(0)
            ->and((int) StockMovement::where('product_id', $product->id)->sum('quantity'))->toBe($product->stock, $product->name);
    });
});

it('cada venta suma anticipo más financiado y sus cuotas suman lo financiado', function () {
    Sale::notCancelled()->with('installments')->get()->each(function (Sale $sale) {
        expect(bcadd($sale->down_payment, $sale->financed_amount, 2))->toBe($sale->total, "Venta #{$sale->id}");

        $sum = $sale->installments->reduce(fn (string $carry, Installment $i): string => bcadd($carry, $i->amount, 2), '0.00');
        expect($sum)->toBe($sale->financed_amount, "Cuotas de la venta #{$sale->id}");
    });
});

it('deja datos para mostrar el dashboard: vencidas, por cobrar, ganancia y stock bajo', function () {
    $metrics = app(DashboardMetrics::class);

    expect($metrics->overdue()['count'])->toBeGreaterThan(0)
        ->and(bccomp($metrics->receivable()['amount'], '0', 2))->toBe(1)
        ->and(bccomp($metrics->futureProfit(), '0', 2))->toBe(1)
        ->and(bccomp($metrics->collectedBetween(today()->startOfMonth(), today()->endOfMonth()), '0', 2))->toBe(1)
        ->and($metrics->lowStockCount())->toBeGreaterThan(0)
        ->and(Installment::where('status', InstallmentStatus::Partial)->count())->toBeGreaterThan(0)
        ->and(Installment::where('status', InstallmentStatus::Paid)->count())->toBeGreaterThan(0);
});

it('hay clientes al día, con atraso y morosos', function () {
    $overdueCustomers = Customer::all()->filter(fn (Customer $customer) => $customer->overdueCount() > 0)->count();
    $clean = Customer::all()->filter(fn (Customer $customer) => $customer->overdueCount() === 0)->count();

    expect($overdueCustomers)->toBeGreaterThan(3)->and($clean)->toBeGreaterThan(3);
});

it('incluye una venta, un cobro y una compra anulados para ver cómo se muestran', function () {
    expect(Sale::where('status', SaleStatus::Cancelled)->count())->toBeGreaterThanOrEqual(1)
        ->and(Payment::whereNotNull('cancelled_at')->count())->toBeGreaterThanOrEqual(1)
        ->and(Purchase::whereNotNull('cancelled_at')->count())->toBe(1);
});

it('algunas ventas tienen anticipo y otras no', function () {
    expect(Sale::where('down_payment', '>', 0)->count())->toBeGreaterThan(5)
        ->and(Sale::where('down_payment', 0)->count())->toBeGreaterThan(5)
        ->and(Payment::whereNotNull('sale_id')->count())->toBe(Sale::where('down_payment', '>', 0)->count());
});

it('el último costo sube entre compras y el precio automático lo acompaña', function () {
    $product = Product::where('sku', 'HE-360')->firstOrFail();

    expect((float) $product->last_cost)->toBeGreaterThan(780000)
        ->and((float) $product->sale_price)->toBeGreaterThan((float) $product->last_cost);
});

it('no deja registros en el registro de actividad', function () {
    expect(ActivityLog::count())->toBe(0);
});

it('cada movimiento de cuenta corriente deja un saldo consistente con el anterior', function () {
    Customer::all()->each(function (Customer $customer) {
        $previous = '0.00';

        AccountMovement::where('customer_id', $customer->id)->orderBy('id')->get()->each(function (AccountMovement $movement) use (&$previous) {
            expect($movement->balance)->toBe(bcsub(bcadd($previous, $movement->debit, 2), $movement->credit, 2));
            $previous = $movement->balance;
        });
    });
});

it('no duplica datos si se ejecuta de nuevo', function () {
    $sales = Sale::count();
    $customers = Customer::count();

    $this->seed(DemoSeeder::class);

    expect(Sale::count())->toBe($sales)->and(Customer::count())->toBe($customers);
});
