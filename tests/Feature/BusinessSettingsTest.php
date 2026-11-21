<?php

use App\Filament\Pages\BusinessSettings as BusinessSettingsPage;
use App\Models\User;
use App\Services\BusinessSettings;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
});

it('guarda los datos del negocio desde el panel', function () {
    $this->actingAs($this->admin);

    Livewire::test(BusinessSettingsPage::class)
        ->fillForm([
            'business_name' => 'Cali Hogar',
            'business_tax_id' => '20-12345678-9',
            'business_address' => 'Av. Siempre Viva 742',
            'business_phone' => '11 5555-5555',
            'business_email' => 'ventas@example.com',
            'receipt_shows_business_header' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Datos guardados');

    expect(app(BusinessSettings::class)->all())->toBe([
        'business_name' => 'Cali Hogar',
        'business_tax_id' => '20-12345678-9',
        'business_address' => 'Av. Siempre Viva 742',
        'business_phone' => '11 5555-5555',
        'business_email' => 'ventas@example.com',
        'business_logo' => null,
        'receipt_shows_business_header' => true,
    ]);
});

it('carga los datos guardados al abrir la página', function () {
    app(BusinessSettings::class)->save(['business_name' => 'Cali Hogar', 'receipt_shows_business_header' => true]);

    $this->actingAs($this->admin);

    Livewire::test(BusinessSettingsPage::class)
        ->assertSchemaStateSet(['business_name' => 'Cali Hogar', 'receipt_shows_business_header' => true]);
});

it('pide el nombre si se muestran los datos en el comprobante', function () {
    $this->actingAs($this->admin);

    Livewire::test(BusinessSettingsPage::class)
        ->fillForm(['business_name' => null, 'receipt_shows_business_header' => true])
        ->call('save')
        ->assertHasFormErrors(['business_name' => 'required']);
});

it('sin datos cargados el encabezado está apagado', function () {
    expect(app(BusinessSettings::class)->all())
        ->business_name->toBeNull()
        ->receipt_shows_business_header->toBeFalse();
});

it('guarda el logo y lo da como data URI para el PDF', function () {
    Storage::fake(BusinessSettings::LOGO_DISK);
    $this->actingAs($this->admin);

    Livewire::test(BusinessSettingsPage::class)
        ->fillForm(['business_name' => 'Cali Hogar', 'business_logo' => UploadedFile::fake()->image('logo.png', 120, 60)])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(BusinessSettings::class);
    $logo = $settings->all()['business_logo'];

    expect($logo)->toStartWith(BusinessSettings::LOGO_DIRECTORY.'/')
        ->and($settings->logoDataUri())->toStartWith('data:image/png;base64,');

    Storage::disk(BusinessSettings::LOGO_DISK)->assertExists($logo);
});

it('borra el logo anterior al reemplazarlo', function () {
    Storage::fake(BusinessSettings::LOGO_DISK);
    Storage::disk(BusinessSettings::LOGO_DISK)->put('business/old.png', 'png');

    $settings = app(BusinessSettings::class);
    $settings->save(['business_logo' => 'business/old.png']);
    $settings->save(['business_logo' => null]);

    Storage::disk(BusinessSettings::LOGO_DISK)->assertMissing('business/old.png');
    expect($settings->logoDataUri())->toBeNull();
});

it('un vendedor no puede entrar a la configuración del negocio', function () {
    $this->actingAs(User::factory()->seller()->create());

    expect(BusinessSettingsPage::canAccess())->toBeFalse();

    $this->get(BusinessSettingsPage::getUrl())->assertForbidden();
});
