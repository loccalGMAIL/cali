<?php

namespace App\Filament\Pages;

use App\Services\BusinessSettings as BusinessSettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Datos del negocio (nombre, CUIT, contacto y logo) y si se muestran en el encabezado de los
 * comprobantes de pago. Solo para administradores.
 *
 * @property-read Schema $form
 */
class BusinessSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Datos del negocio';

    protected static ?string $title = 'Datos del negocio';

    protected static ?string $slug = 'business-settings';

    /**
     * Estado del formulario.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(app(BusinessSettingsService::class)->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Negocio')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('business_name')->label('Nombre o razón social')->maxLength(255)
                            ->required(fn (Get $get): bool => (bool) $get('receipt_shows_business_header')),
                        TextInput::make('business_tax_id')->label('CUIT')->maxLength(20),
                        TextInput::make('business_phone')->label('Teléfono')->tel()->maxLength(50),
                        TextInput::make('business_email')->label('Email')->email()->maxLength(255),
                        TextInput::make('business_address')->label('Domicilio')->maxLength(255)->columnSpanFull(),
                        FileUpload::make('business_logo')->label('Logo')
                            ->helperText('PNG o JPG, hasta 1 MB.')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(1024)
                            ->disk(BusinessSettingsService::LOGO_DISK)
                            ->directory(BusinessSettingsService::LOGO_DIRECTORY)
                            ->visibility('public')
                            ->columnSpanFull(),
                    ]),
                Section::make('Comprobantes de pago')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('receipt_shows_business_header')
                            ->label('Mostrar los datos del negocio en el encabezado')
                            ->helperText('Si está apagado, el comprobante solo lleva el título y el número.')
                            ->live(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')->label('Guardar')->submit('save')->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        app(BusinessSettingsService::class)->save($this->form->getState());

        Notification::make()->success()->title('Datos guardados')->send();
    }
}
