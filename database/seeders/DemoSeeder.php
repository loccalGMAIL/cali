<?php

namespace Database\Seeders;

use App\Actions\CancelPayment;
use App\Actions\CancelPurchase;
use App\Actions\CancelSale;
use App\Actions\CreatePurchase;
use App\Actions\CreatePurchaseOrder;
use App\Actions\CreateSale;
use App\Actions\IssuePurchaseOrder;
use App\Actions\RegisterPayment;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Datos de demostración con aspecto real: proveedores, productos, clientes, ventas en cuotas
 * (algunas con anticipo), órdenes de compra semanales con sus facturas, cobros y clientes
 * atrasados, para mostrar el dashboard.
 *
 * Se arma con las mismas acciones del sistema (CreateSale, CreatePurchaseOrder, CreatePurchase,
 * RegisterPayment…), así las cuotas, los pendientes de compra y la cuenta corriente quedan
 * coherentes como si se hubieran cargado a mano. Es determinístico: siempre genera los mismos datos.
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
     * Catálogo: [nombre, marca, código, costo hace 100 días, margen % habitual, proveedor habitual]
     *
     * @var list<array{string, string, string, int, int, int}>
     */
    private const CATALOG = [
        ['Heladera con freezer 360 L', 'Drean', 'HE-360', 780000, 40, 0],
        ['Heladera No Frost 420 L inverter', 'Samsung', 'HE-420', 1150000, 38, 0],
        ['Lavarropas automático 8 kg', 'Drean', 'LV-080', 620000, 42, 0],
        ['Lavarropas carga frontal 9 kg inverter', 'LG', 'LV-090', 980000, 40, 0],
        ['Cocina a gas 5 hornallas', 'Longvie', 'CO-005', 540000, 45, 1],
        ['Horno eléctrico 60 L', 'Atma', 'HO-060', 190000, 50, 1],
        ['Microondas 25 L digital', 'BGH', 'MI-025', 150000, 50, 1],
        ['Aire acondicionado split 3000 frigorías', 'Surrey', 'AA-300', 690000, 38, 1],
        ['Aire acondicionado inverter 4500 frigorías', 'Carrier', 'AA-450', 1100000, 36, 1],
        ['Smart TV 43" Full HD', 'Philips', 'TV-043', 460000, 40, 2],
        ['Smart TV 55" 4K', 'Samsung', 'TV-055', 820000, 38, 2],
        ['Ventilador de pie 20"', 'Liliana', 'VE-020', 85000, 55, 3],
        ['Cafetera express', 'Oster', 'CA-001', 140000, 50, 3],
        ['Licuadora 600 W', 'Philips', 'LI-600', 60000, 55, 3],
        ['Pava eléctrica 2 L', 'Atma', 'PA-002', 38000, 60, 3],
        ['Aspiradora 1600 W', 'Electrolux', 'AS-160', 120000, 50, 3],
        ['Freidora de aire 4 L', 'Philco', 'FR-004', 95000, 55, 3],
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
            $products = $this->products($suppliers);
            $customers = $this->customers();

            $this->sales($admin, $sellers->push($admin), $customers, $products);
            $this->purchases($admin, $suppliers);
            $this->collections($admin, $customers);
            $this->corrections($admin);
        });

        $this->command?->info('Datos de demostración cargados: '.Customer::count().' clientes, '.Sale::count().' ventas, '.PurchaseOrder::count().' órdenes de compra, '.Payment::count().' cobros.');
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
     * El último costo arranca en el de hace 100 días; las facturas lo van actualizando.
     *
     * @param  Collection<int, Supplier>  $suppliers
     * @return Collection<int, Product>
     */
    private function products(Collection $suppliers): Collection
    {
        return collect(self::CATALOG)->map(fn (array $row): Product => Product::create([
            'sku' => $row[2],
            'name' => $row[0],
            'brand' => $row[1],
            'supplier_id' => $suppliers[$row[5]]->id,
            'last_cost' => $row[3],
        ]));
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
     * Ventas repartidas en los últimos 100 días, en orden cronológico: planes variados y una de
     * cada tres con anticipo. El costo es el del día de la venta y el margen, el habitual del producto.
     *
     * @param  Collection<int, User>  $sellers
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $products
     */
    private function sales(User $admin, Collection $sellers, Collection $customers, Collection $products): void
    {
        $plans = FinancingPlan::query()->active()->get();
        $planWeights = [1 => 10, 3 => 25, 6 => 40, 8 => 25];
        $buyers = $customers->filter(fn (Customer $customer): bool => $customer->active)->values();

        // Las últimas ventas son de esta semana: quedan pendientes de compra.
        $daysAgo = collect(range(1, self::SALES))
            ->map(fn (int $n): int => $n > self::SALES - 6 ? fake()->numberBetween(1, 6) : fake()->numberBetween(8, 100))
            ->sortDesc()
            ->values();

        foreach ($daysAgo as $i => $days) {
            $index = fake()->numberBetween(0, count(self::CATALOG) - 1);
            $row = self::CATALOG[$index];
            $date = CarbonImmutable::today()->subDays($days);
            $cost = self::costAt($row[3], $days);
            $margin = round($cost * $row[4] / 100, -3);
            $quantity = $row[3] < 150000 && fake()->boolean(30) ? 2 : 1;
            $total = ($cost + $margin) * $quantity;

            app(CreateSale::class)->handle([
                'customer_id' => $this->pick($buyers)->id,
                'seller_id' => $this->pick($sellers)->id,
                'financing_plan_id' => $this->pickPlan($plans, $planWeights)->id,
                'date' => $date->toDateString(),
                'first_due_date' => $date->addMonthNoOverflow()->toDateString(),
                'down_payment' => $i % 3 === 0 ? round($total * fake()->randomElement([0.1, 0.2, 0.3]), -3) : 0,
                'down_payment_method' => fake()->randomElement([PaymentMethod::Cash, PaymentMethod::Transfer])->value,
            ], [[
                'product_id' => $products[$index]->id,
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'unit_margin' => $margin,
            ]], $admin);
        }
    }

    /**
     * Costo de un producto hace tantos días: sube un poco cada día, como pasa con la inflación.
     */
    private static function costAt(int $baseCost, int $daysAgo): float
    {
        return round($baseCost * (1 + (100 - $daysAgo) * 0.0011), -2);
    }

    /**
     * Cada lunes se arma una orden por proveedor con lo vendido hasta ese día, se emite y la
     * factura llega dos días después. En una factura, a partir de la tercera semana, falta una
     * unidad (vuelve a pendiente). Lo vendido en la última semana sigue pendiente, una orden
     * queda emitida esperando la factura y otra en borrador.
     *
     * @param  Collection<int, Supplier>  $suppliers
     */
    private function purchases(User $admin, Collection $suppliers): void
    {
        $invoice = 1000;
        $week = 0;
        $shortagePending = true;
        $monday = CarbonImmutable::today()->subDays(98)->startOfWeek();
        $lastMonday = CarbonImmutable::today()->subDays(7);

        while ($monday->lessThanOrEqualTo($lastMonday)) {
            $week++;
            $isLastWeek = $monday->addWeek()->greaterThan($lastMonday);

            foreach ($suppliers as $supplier) {
                $items = $this->pendingUntil($supplier, $monday);

                if ($items === []) {
                    continue;
                }

                $order = app(CreatePurchaseOrder::class)->handle(
                    ['supplier_id' => $supplier->id, 'date' => $monday->toDateString()],
                    $items,
                    $admin,
                );

                app(IssuePurchaseOrder::class)->handle($order, $admin);

                if ($isLastWeek && $supplier->is($suppliers->first())) {
                    continue; // emitida, esperando la factura del proveedor
                }

                $shortage = $shortagePending && $week >= 3 && $order->items()->sum('quantity') > $order->items()->distinct('product_id')->count('product_id');
                $shortagePending = $shortagePending && ! $shortage;

                $this->receive($order, $admin, $monday->addDays(2), sprintf('0001-%08d', $invoice++), $shortage);
            }

            $monday = $monday->addWeek();
        }

        // Una orden en borrador con lo pendiente del proveedor que más tiene; el resto queda pendiente.
        $items = $suppliers
            ->map(fn (Supplier $supplier): array => ['supplier' => $supplier, 'items' => $this->pendingUntil($supplier, CarbonImmutable::today())])
            ->sortByDesc(fn (array $pending): int => count($pending['items']))
            ->first();

        if ($items !== null && $items['items'] !== []) {
            app(CreatePurchaseOrder::class)->handle(['supplier_id' => $items['supplier']->id, 'date' => today()->toDateString()], $items['items'], $admin);
        }
    }

    /**
     * Lo pendiente de compra de los productos del proveedor, vendido hasta la fecha indicada.
     *
     * @return list<array{sale_item_id: int, quantity: int}>
     */
    private function pendingUntil(Supplier $supplier, CarbonImmutable $date): array
    {
        return SaleItem::query()
            ->pendingPurchase()
            ->whereHas('product', fn ($query) => $query->where('supplier_id', $supplier->id))
            ->whereHas('sale', fn ($query) => $query->notCancelled()->whereDate('date', '<=', $date->toDateString()))
            ->orderBy('id')
            ->get()
            ->map(fn (SaleItem $item): array => ['sale_item_id' => $item->id, 'quantity' => $item->pending_quantity])
            ->all();
    }

    /**
     * Factura de la orden al costo del día: llega todo, salvo con faltante, donde falta una
     * unidad del primer producto que se pidió de a más de una.
     */
    private function receive(PurchaseOrder $order, User $admin, CarbonImmutable $date, string $invoiceNumber, bool $shortage = false): void
    {
        $daysAgo = (int) $date->diffInDays(CarbonImmutable::today());
        $shortageApplied = false;

        $items = $order->linesByProduct()->map(function (array $line) use ($daysAgo, $shortage, &$shortageApplied): array {
            $quantity = $line['quantity'];

            if ($shortage && ! $shortageApplied && $quantity > 1) {
                $quantity--;
                $shortageApplied = true;
            }

            $baseCost = collect(self::CATALOG)->firstWhere(2, $line['product']->sku)[3];

            return [
                'product_id' => $line['product']->id,
                'quantity' => $quantity,
                'unit_cost' => self::costAt($baseCost, $daysAgo),
            ];
        })->all();

        app(CreatePurchase::class)->handle([
            'purchase_order_id' => $order->id,
            'date' => $date->toDateString(),
            'invoice_number' => $invoiceNumber,
        ], $items, $admin);
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
     * una venta reciente que se cayó, un cobro mal cargado y una factura de compra con el número equivocado.
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

        // La última factura se había cargado con el número equivocado: se anula y se vuelve a cargar.
        $purchase = Purchase::query()->notCancelled()->latest('id')->first();

        if ($purchase !== null) {
            $items = $purchase->items->map(fn (PurchaseItem $item): array => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
            ])->all();

            app(CancelPurchase::class)->handle($purchase, 'Número de factura mal cargado', $admin);

            app(CreatePurchase::class)->handle([
                'purchase_order_id' => $purchase->purchase_order_id,
                'date' => $purchase->date->toDateString(),
                'invoice_number' => $purchase->invoice_number.'-B',
            ], $items, $admin);
        }
    }
}
