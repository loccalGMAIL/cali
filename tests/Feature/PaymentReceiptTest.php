<?php

use App\Actions\CancelPayment;
use App\Actions\RegisterPayment;
use App\Filament\Resources\Payments\Actions\SendReceiptByWhatsAppAction;
use App\Filament\Resources\Payments\Actions\ShareReceiptAction;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\BusinessSettings;
use App\Services\PaymentReceipt;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create(['name' => 'Juan Pérez', 'document_number' => '30123456']);

    // 3 cuotas de $ 400.
    $this->sale = reportSale($this->customer, $this->admin, '2026-12-01', product: Product::factory()->create(['name' => 'Heladera 300L']));

    $this->pay = fn (float $amount, array $data = []): Payment => app(RegisterPayment::class)->handle(
        ['customer_id' => $this->customer->id, 'amount' => $amount, 'date' => '2026-11-21', ...$data],
        $this->admin,
    );
});

it('arma el comprobante con las cuotas cobradas y las que quedan', function () {
    $receipt = app(PaymentReceipt::class)->for(($this->pay)(500));

    expect($receipt)
        ->customer_name->toBe('Juan Pérez')
        ->customer_document->toBe('30123456')
        ->amount->toBe('500.00')
        ->is_down_payment->toBeFalse()
        ->is_cancelled->toBeFalse()
        ->and($receipt['sales'])->toHaveCount(1)
        ->and($receipt['sales'][0])
        ->number->toBe($this->sale->number())
        ->products->toBe('Heladera 300L')
        ->installments_count->toBe(3)
        ->remaining->toBe(2)
        ->lines->toBe([
            ['number' => 1, 'amount' => '400.00', 'partial' => false],
            ['number' => 2, 'amount' => '100.00', 'partial' => true],
        ]);
});

it('las cuotas restantes quedan a la fecha del cobro aunque después se cobre más', function () {
    $first = ($this->pay)(500);
    $second = ($this->pay)(700);

    $receipts = app(PaymentReceipt::class);

    expect($receipts->for($first)['sales'][0]['remaining'])->toBe(2)
        ->and($receipts->for($second)['sales'][0])
        ->remaining->toBe(0)
        ->lines->toBe([
            ['number' => 2, 'amount' => '300.00', 'partial' => false],
            ['number' => 3, 'amount' => '400.00', 'partial' => false],
        ]);
});

it('no cuenta los cobros anulados antes, pero sí los anulados después', function () {
    $cancelledBefore = ($this->pay)(400);
    app(CancelPayment::class)->handle($cancelledBefore, 'Error de carga', $this->admin);

    $this->travel(1)->minutes();
    $payment = ($this->pay)(400);

    expect(app(PaymentReceipt::class)->for($payment)['sales'][0]['remaining'])->toBe(2);

    $this->travel(1)->minutes();
    $next = ($this->pay)(400);
    $this->travel(1)->minutes();
    app(CancelPayment::class)->handle($payment, 'Se cobró dos veces', $this->admin);

    expect(app(PaymentReceipt::class)->for($next)['sales'][0]['remaining'])->toBe(1);
});

it('separa las cuotas por venta cuando el cobro abarca varias', function () {
    $otherSale = reportSale($this->customer, $this->admin, '2027-01-01', product: Product::factory()->create(['name' => 'Lavarropas']));

    $receipt = app(PaymentReceipt::class)->for(($this->pay)(1600));

    expect($receipt['sales'])->toHaveCount(2)
        ->and($receipt['sales'][0])->number->toBe($this->sale->number())->remaining->toBe(0)
        ->and($receipt['sales'][1])
        ->number->toBe($otherSale->number())
        ->products->toBe('Lavarropas')
        ->remaining->toBe(2)
        ->lines->toBe([['number' => 1, 'amount' => '400.00', 'partial' => false]]);
});

it('arma el comprobante del anticipo con todas las cuotas pendientes', function () {
    $sale = reportSale($this->customer, $this->admin, '2027-01-01', downPayment: 300);

    $receipt = app(PaymentReceipt::class)->for($sale->activeDownPayment());

    expect($receipt)
        ->is_down_payment->toBeTrue()
        ->amount->toBe('300.00')
        ->and($receipt['sales'])->toHaveCount(1)
        ->and($receipt['sales'][0])->number->toBe($sale->number())->lines->toBe([])->remaining->toBe(3);
});

it('el comprobante lleva la leyenda de no fiscal y no muestra saldos', function () {
    $payment = ($this->pay)(500);

    $html = view('payments.receipt', [
        'receipt' => app(PaymentReceipt::class)->for($payment),
        'business' => app(BusinessSettings::class)->all(),
        'logo' => null,
    ])->render();

    expect($html)
        ->toContain('Comprobante no válido como factura')
        ->toContain($payment->number())
        ->toContain('Juan Pérez')
        ->toContain('Cuota 2 de 3')
        ->toContain('Cuotas pendientes: 2 de 3')
        ->not->toContain('Saldo')
        ->not->toContain('$ 700,00')
        ->not->toContain('ANULADO');
});

it('muestra los datos del negocio en el encabezado solo si está activado', function () {
    $settings = app(BusinessSettings::class);
    $settings->save(['business_name' => 'Cali Hogar', 'business_tax_id' => '20-12345678-9', 'receipt_shows_business_header' => false]);

    $payment = ($this->pay)(400);

    $render = fn (): string => view('payments.receipt', [
        'receipt' => app(PaymentReceipt::class)->for($payment),
        'business' => $settings->all(),
        'logo' => null,
    ])->render();

    expect($render())->not->toContain('Cali Hogar')->not->toContain('20-12345678-9');

    $settings->save(['business_name' => 'Cali Hogar', 'business_tax_id' => '20-12345678-9', 'receipt_shows_business_header' => true]);

    expect($render())->toContain('Cali Hogar')->toContain('CUIT 20-12345678-9');
});

it('marca como anulado el comprobante de un cobro anulado', function () {
    $payment = ($this->pay)(400);
    app(CancelPayment::class)->handle($payment, 'Error de carga', $this->admin);

    $html = view('payments.receipt', [
        'receipt' => app(PaymentReceipt::class)->for($payment->fresh()),
        'business' => app(BusinessSettings::class)->all(),
        'logo' => null,
    ])->render();

    expect($html)->toContain('ANULADO')->toContain('Cuota 1 de 3');
});

it('descarga el comprobante en PDF', function () {
    $payment = ($this->pay)(500);

    $response = $this->actingAs($this->admin)
        ->get(route('filament.admin.payments.receipt', $payment))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=comprobante-'.$payment->number().'.pdf');

    expect($response->getContent())->toStartWith('%PDF')
        ->and(strlen($response->getContent()))->toBeLessThan(100_000);
});

it('un vendedor no puede descargar comprobantes', function () {
    $payment = ($this->pay)(500);

    $this->actingAs(User::factory()->seller()->create())
        ->get(route('filament.admin.payments.receipt', $payment))
        ->assertForbidden();
});

it('ofrece el comprobante en el detalle del cobro', function () {
    $payment = ($this->pay)(500);
    $this->actingAs($this->admin);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertActionVisible('receipt')
        ->assertActionHasUrl('receipt', route('filament.admin.payments.receipt', $payment));
});

it('al registrar un cobro ofrece descargar el comprobante, mandarlo por WhatsApp o compartirlo', function () {
    $this->freezeTime();
    $this->actingAs($this->admin);

    Livewire::test(CreatePayment::class)
        ->fillForm(['customer_id' => $this->customer->id, 'amount' => 500, 'method' => 'cash', 'date' => '2026-11-21'])
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = Payment::query()->firstOrFail();

    Notification::assertNotified(
        Notification::make()->success()
            ->title(__('filament-panels::resources/pages/create-record.notifications.created.title'))
            ->body("Cobro {$payment->number()} registrado.")
            ->actions([
                Action::make('receipt')->label('Descargar comprobante')
                    ->url(route('filament.admin.payments.receipt', $payment))
                    ->openUrlInNewTab(),
                Action::make('whatsapp')->label('Enviar por WhatsApp')
                    ->url(SendReceiptByWhatsAppAction::url($payment))
                    ->openUrlInNewTab(),
                Action::make('share')->label('Compartir PDF')
                    ->alpineClickHandler(ShareReceiptAction::clickHandler($payment)),
            ])
            ->persistent(),
    );
});

it('el cliente abre el comprobante desde el enlace firmado, sin usuario', function () {
    $payment = ($this->pay)(500);

    $response = $this->get(PaymentReceipt::sharedUrl($payment))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=comprobante-'.$payment->number().'.pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

it('el enlace al comprobante no sirve sin firma, con otro cobro ni vencido', function () {
    $payment = ($this->pay)(500);
    $other = ($this->pay)(400);
    $url = PaymentReceipt::sharedUrl($payment);

    $this->get(route('payments.receipt.shared', $payment))->assertForbidden();
    $this->get(str_replace("/comprobante/{$payment->id}?", "/comprobante/{$other->id}?", $url))->assertForbidden();

    $this->travel(config('cali.shared_receipt_days') + 1)->days();

    $this->get($url)->assertForbidden();
});

it('arma el mensaje de WhatsApp al celular del cliente con el enlace al comprobante', function () {
    $this->freezeTime();
    $this->customer->update(['phone' => '0351 15-555-1234']);
    app(BusinessSettings::class)->save(['business_name' => 'Cali Hogar']);
    $payment = ($this->pay)(500);

    $url = SendReceiptByWhatsAppAction::url($payment->fresh());

    expect($url)->toStartWith('https://wa.me/5493515551234?text=')
        ->and(rawurldecode(str($url)->after('?text=')->toString()))->toBe(
            "Hola Juan Pérez, te enviamos el comprobante {$payment->number()} de tu pago de $ 500,00 del 21/11/2026:\n"
            .PaymentReceipt::sharedUrl($payment)."\n"
            .'¡Gracias! Cali Hogar',
        );
});

it('ofrece mandar y compartir el comprobante desde el detalle y la tabla de cobros', function () {
    $payment = ($this->pay)(500);
    $this->actingAs($this->admin);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertActionVisible('whatsapp')
        ->assertActionVisible('share');

    Livewire::test(ListPayments::class)
        ->assertActionVisible(TestAction::make('whatsapp')->table($payment))
        ->assertActionVisible(TestAction::make('share')->table($payment));
});

it('no ofrece mandar el comprobante de un cobro anulado', function () {
    $payment = ($this->pay)(500);
    app(CancelPayment::class)->handle($payment, 'Error de carga', $this->admin);
    $this->actingAs($this->admin);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertActionVisible('receipt')
        ->assertActionHidden('whatsapp')
        ->assertActionHidden('share');
});
