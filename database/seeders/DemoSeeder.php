<?php

namespace Database\Seeders;

use App\Actions\CancelPayment;
use App\Actions\CancelPurchase;
use App\Actions\CancelSale;
use App\Actions\CreatePurchase;
use App\Actions\CreateSale;
use App\Actions\RegisterPayment;
use App\Enums\PaymentMethod;
use App\Enums\PriceMode;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Datos de demostración con aspecto real: proveedores, productos, compras, clientes, ventas en
 * cuotas (algunas con anticipo), cobros y clientes atrasados, para mostrar el dashboard.
 *
 * Se arma con las mismas acciones del sistema (CreatePurchase, CreateSale, RegisterPayment…),
 * así el stock, las cuotas y la cuenta corriente quedan coherentes como si se hubieran cargado
 * a mano. Es determinístico: siempre genera los mismos datos.
 *
 *     php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder
 *
 * En producción `db:seed` pide confirmación (o `--force`); el seeder no hace nada si ya hay ventas.
 */
class DemoSeeder extends Seeder
{
    private const SALES = 48;

    private const CUSTOMERS = 28;

    /**
     * Catálogo: [nombre, marca, código, costo, margen %, stock mínimo, proveedor, unidades en la 1ª compra]
     *
     * @var list<array{string, string, string, int, int, int, int, int}>
     */
    private const CATALOG = [
        ['Heladera con freezer 360 L', 'Drean', 'HE-360', 780000, 40, 2, 0, 8],
        ['Heladera No Frost 420 L inverter', 'Samsung', 'HE-420', 1150000, 38, 2, 0, 6],
        ['Lavarropas automático 8 kg', 'Drean', 'LV-080', 620000, 42, 2, 0, 8],
        ['Lavarropas carga frontal 9 kg inverter', 'LG', 'LV-090', 980000, 40, 2, 0, 6],
        ['Cocina a gas 5 hornallas', 'Longvie', 'CO-005', 540000, 45, 2, 1, 7],
        ['Horno eléctrico 60 L', 'Atma', 'HO-060', 190000, 50, 3, 1, 10],
        ['Microondas 25 L digital', 'BGH', 'MI-025', 150000, 50, 3, 1, 12],
        ['Aire acondicionado split 3000 frigorías', 'Surrey', 'AA-300', 690000, 38, 2, 1, 7],
        ['Aire acondicionado inverter 4500 frigorías', 'Carrier', 'AA-450', 1100000, 36, 2, 1, 5],
        ['Smart TV 43" Full HD', 'Philips', 'TV-043', 460000, 40, 3, 2, 10],
        ['Smart TV 55" 4K', 'Samsung', 'TV-055', 820000, 38, 2, 2, 7],
        ['Ventilador de pie 20"', 'Liliana', 'VE-020', 85000, 55, 12, 3, 6],
        ['Cafetera express', 'Oster', 'CA-001', 140000, 50, 3, 3, 12],
        ['Licuadora 600 W', 'Philips', 'LI-600', 60000, 55, 4, 3, 14],
        ['Pava eléctrica 2 L', 'Atma', 'PA-002', 38000, 60, 12, 3, 6],
        ['Aspiradora 1600 W', 'Electrolux', 'AS-160', 120000, 50, 3, 3, 10],
        ['Freidora de aire 4 L', 'Philco', 'FR-004', 95000, 55, 3, 3, 12],
    ];

    private const SUPPLIERS = [
        ['Distribuidora Línea Blanca S.A.', '30-70123456-7'],
        ['Hogar y Confort S.R.L.', '30-70234567-1'],
        ['Mayorista Electro Sur', '30-70345678-5'],
        ['Pequeños Electro S.A.', '30-70456789-3'],
    ];

    private const CITIES = ['Quilmes', 'Lomas de Zamora', 'Banfield', 'Lanús', 'Avellaneda', 'Berazategui', 'Florencio Varela'];

    public function run(): void
    {
        if (Sale::query()->exists()) {
            $this->command?->warn('Ya hay ventas cargadas: los datos de demostración se pensaron para una base vacía. No se hizo nada.');

            return;
        }

        fake()->seed(20261108);

        // Los datos de demostración no son acciones de un usuario: no van al registro de actividad.
        ActivityLogger::withoutLogging(function (): void {
            $this->call(DatabaseSeeder::class);

            $admin = User::query()->where('role', UserRole::Admin->value)->firstOrFail();
            $sellers = $this->sellers();
            $suppliers = $this->suppliers();
            $products = $this->products();

            $this->purchases($admin, $suppliers, $products);
            $customers = $this->customers();
            $this->sales($admin, $sellers->push($admin), $customers);
            $this->collections($admin, $customers);
            $this->corrections($admin);
        });

        $this->command?->info('Datos de demostración cargados: '.Customer::count().' clientes, '.Sale::count().' ventas, '.Payment::count().' cobros.');
    }

    /**
     * @return Collection<int, User>
     */
    private function sellers(): Collection
    {
        return collect([['Martina Ríos', 'martina@cali.localhost'], ['Lucas Herrera', 'lucas@cali.localhost']])
            ->map(fn (array $data): User => User::query()->updateOrCreate(
                ['email' => $data[1]],
                ['name' => $data[0], 'password' => 'password', 'role' => UserRole::Seller, 'active' => true],
            ));
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function suppliers(): Collection
    {
        return collect(self::SUPPLIERS)->map(fn (array $data): Supplier => Supplier::factory()->create([
            'name' => $data[0],
            'cuit' => $data[1],
        ]));
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return collect(self::CATALOG)->map(fn (array $row): Product => Product::create([
            'sku' => $row[2],
            'name' => $row[0],
            'brand' => $row[1],
            'last_cost' => $row[3],
            'margin_percent' => $row[4],
            'min_stock' => $row[5],
            'price_mode' => PriceMode::Auto,
            'stock' => 0,
        ]));
    }

    /**
     * Tres tandas de compras a cada proveedor (hace 4, 2 y casi 1 mes). El costo sube de una
     * tanda a la siguiente, como pasa con la inflación, y con él el precio automático.
     *
     * @param  Collection<int, Supplier>  $suppliers
     * @param  Collection<int, Product>  $products
     */
    private function purchases(User $admin, Collection $suppliers, Collection $products): void
    {
        $rounds = [[120, 1.00, 1.0], [70, 1.06, 0.5], [25, 1.11, 0.35]];
        $invoice = 1000;

        foreach ($rounds as [$daysAgo, $inflation, $quantityFactor]) {
            foreach ($suppliers as $supplierIndex => $supplier) {
                $items = [];

                foreach (self::CATALOG as $index => $row) {
                    if ($row[6] !== $supplierIndex) {
                        continue;
                    }

                    $items[] = [
                        'product_id' => $products[$index]->id,
                        'quantity' => max(2, (int) round($row[7] * $quantityFactor)),
                        'unit_cost' => round($row[3] * $inflation, -2),
                    ];
                }

                app(CreatePurchase::class)->handle([
                    'supplier_id' => $supplier->id,
                    'date' => today()->subDays($daysAgo + $supplierIndex * 3)->toDateString(),
                    'invoice_number' => sprintf('0001-%08d', $invoice++),
                ], $items, $admin);
            }
        }
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customers(): Collection
    {
        return collect(range(1, self::CUSTOMERS))->map(fn (int $i): Customer => Customer::factory()->create([
            'name' => fake()->firstName().' '.fake()->lastName(),
            'city' => self::CITIES[($i - 1) % count(self::CITIES)],
            'active' => $i !== self::CUSTOMERS, // uno inactivo, para ver el filtro
            'notes' => $i % 9 === 0 ? 'Prefiere que lo llamen por la tarde.' : null,
        ]));
    }

    /**
     * Ventas repartidas en los últimos 100 días: planes variados y una de cada tres con anticipo.
     *
     * @param  Collection<int, User>  $sellers
     * @param  Collection<int, Customer>  $customers
     */
    private function sales(User $admin, Collection $sellers, Collection $customers): void
    {
        $plans = FinancingPlan::query()->active()->get();
        $planWeights = [1 => 10, 3 => 25, 6 => 40, 8 => 25];
        $buyers = $customers->filter(fn (Customer $customer): bool => $customer->active)->values();

        for ($i = 0; $i < self::SALES; $i++) {
            $stocked = Product::query()->where('stock', '>', 0)->get();

            if ($stocked->isEmpty()) {
                return;
            }

            $product = $this->pick($stocked);
            $date = CarbonImmutable::today()->subDays(fake()->numberBetween(2, 100));
            $subtotal = (float) $product->sale_price;

            app(CreateSale::class)->handle([
                'customer_id' => $this->pick($buyers)->id,
                'seller_id' => $this->pick($sellers)->id,
                'financing_plan_id' => $this->pickPlan($plans, $planWeights)->id,
                'date' => $date->toDateString(),
                'first_due_date' => $date->addMonthNoOverflow()->toDateString(),
                'down_payment' => $i % 3 === 0 ? round($subtotal * fake()->randomElement([0.1, 0.2, 0.3]), -3) : 0,
                'down_payment_method' => fake()->randomElement([PaymentMethod::Cash, PaymentMethod::Transfer])->value,
            ], [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->sale_price]], $admin);
        }
    }

    /**
     * Elige un elemento con el generador con semilla (Collection::random() no lo usa y rompería
     * que los datos sean siempre los mismos).
     *
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @return T
     */
    private function pick(Collection $items): mixed
    {
        return fake()->randomElement($items->values()->all());
    }

    /**
     * @param  Collection<int, FinancingPlan>  $plans
     * @param  array<int, int>  $weights  cantidad de cuotas => peso
     */
    private function pickPlan(Collection $plans, array $weights): FinancingPlan
    {
        $roll = fake()->numberBetween(1, array_sum($weights));

        foreach ($weights as $installments => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $plans->firstWhere('installments_count', $installments) ?? $plans->first();
            }
        }

        return $plans->first();
    }

    /**
     * Cobros de las cuotas ya vencidas según cómo paga cada cliente: la mayoría al día, algunos
     * con uno o dos meses de atraso y unos pocos morosos. Se registran en orden cronológico.
     *
     * @param  Collection<int, Customer>  $customers
     */
    private function collections(User $admin, Collection $customers): void
    {
        $planned = [];

        foreach ($customers as $index => $customer) {
            $behavior = $index % 10 < 6 ? 'good' : ($index % 10 < 9 ? 'regular' : 'late');

            $due = Installment::query()
                ->where('customer_id', $customer->id)
                ->whereDate('due_date', '<=', today())
                ->orderBy('due_date')->orderBy('id')
                ->get();

            $toPay = match ($behavior) {
                'good' => $due,
                // Paga todo menos la última cuota vencida; si solo vencía una, la paga parcialmente.
                'regular' => $due->count() <= 1 ? $due : $due->slice(0, $due->count() - 1),
                default => $due->slice(0, min(1, $due->count())),
            };

            $previousDate = null;

            foreach ($toPay->values() as $position => $installment) {
                $date = CarbonImmutable::parse($installment->due_date)->addDays(fake()->numberBetween(0, 4));
                $date = $date->greaterThan(today()) ? CarbonImmutable::today() : $date;
                // La fecha no puede retroceder para un mismo cliente: así el orden FIFO coincide.
                $date = $previousDate !== null && $date->lessThan($previousDate) ? $previousDate : $date;
                $previousDate = $date;

                $isLastOfRegular = $behavior === 'regular' && $position === $toPay->count() - 1;

                $planned[] = [
                    'date' => $date,
                    'customer_id' => $customer->id,
                    // El último pago de quien paga con atraso es parcial: la cuota queda con saldo.
                    'amount' => $isLastOfRegular
                        ? round((float) $installment->balance() / 2, 2)
                        : $installment->balance(),
                    'method' => fake()->randomElement([PaymentMethod::Cash, PaymentMethod::Transfer])->value,
                ];
            }
        }

        usort($planned, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        foreach ($planned as $payment) {
            app(RegisterPayment::class)->handle([
                'customer_id' => $payment['customer_id'],
                'amount' => $payment['amount'],
                'date' => $payment['date']->toDateString(),
                'method' => $payment['method'],
            ], $admin);
        }
    }

    /**
     * Un par de errores corregidos, para que se vea cómo queda lo anulado en cada pantalla:
     * una venta reciente que se cayó, un cobro mal cargado y una factura de compra equivocada.
     */
    private function corrections(User $admin): void
    {
        $untouched = Sale::query()
            ->whereDoesntHave('installments', fn ($query) => $query->where('paid_amount', '>', 0))
            ->where('installments_count', '>', 1)
            ->orderByDesc('date')
            ->first();

        if ($untouched !== null) {
            app(CancelSale::class)->handle($untouched, 'El cliente desistió de la compra', $admin);
        }

        $payment = Payment::query()->notCancelled()->whereNull('sale_id')->latest('id')->first();

        if ($payment !== null) {
            $amount = $payment->amount;
            app(CancelPayment::class)->handle($payment, 'Monto mal cargado', $admin);
            app(RegisterPayment::class)->handle([
                'customer_id' => $payment->customer_id,
                'amount' => $amount,
                'date' => today()->toDateString(),
                'method' => PaymentMethod::Cash->value,
            ], $admin);
        }

        $wrong = app(CreatePurchase::class)->handle([
            'supplier_id' => Supplier::query()->firstOrFail()->id,
            'date' => today()->subDays(3)->toDateString(),
            'invoice_number' => '0001-00009999',
        ], [['product_id' => Product::query()->firstOrFail()->id, 'quantity' => 1, 'unit_cost' => 780000]], $admin);

        app(CancelPurchase::class)->handle($wrong, 'Factura cargada al proveedor equivocado', $admin);
    }
}
