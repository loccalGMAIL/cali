<?php

namespace App\Filament\Pages\Reports;

use App\Models\Customer;
use App\Services\Reports\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Base de las páginas de informes: filtros arriba, la tabla del informe debajo y un botón para
 * abrir la versión imprimible con los mismos filtros. Solo para administradores.
 *
 * @property-read Schema $form
 */
abstract class ReportPage extends Page
{
    protected string $view = 'filament.pages.report';

    protected static string|UnitEnum|null $navigationGroup = 'Informes';

    /**
     * Estado del formulario de filtros.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @return class-string<Report>
     */
    abstract protected static function reportClass(): string;

    /**
     * @return array<Component>
     */
    abstract protected function filterComponents(): array;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return static::report()->title();
    }

    public function getTitle(): string
    {
        return static::report()->title();
    }

    public function mount(): void
    {
        $this->form->fill(static::report()->normalize([]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->filterComponents())
            ->columns(['sm' => 2, 'lg' => 4])
            ->statePath('data');
    }

    /**
     * Filtros vigentes, ya corregidos: es lo que usan la tabla y la versión imprimible.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return static::report()->normalize($this->data ?? []);
    }

    public function printUrl(): string
    {
        $query = array_map(
            fn (mixed $value): mixed => is_bool($value) ? (int) $value : $value,
            array_filter($this->filters(), fn (mixed $value): bool => $value !== null),
        );

        return route('filament.admin.reports.print', ['report' => static::reportClass()::slug(), ...$query]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label('Imprimir')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => $this->printUrl())
                ->openUrlInNewTab(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $filters = $this->filters();

        return [
            'partial' => 'reports.partials.'.static::reportClass()::slug(),
            'filters' => $filters,
            'report' => static::report()->build($filters),
        ];
    }

    protected static function report(): Report
    {
        return app(static::reportClass());
    }

    /**
     * Filtro por localidad, con las localidades cargadas en los clientes.
     */
    protected function cityFilter(): Select
    {
        return Select::make('city')->label('Localidad')
            ->options(fn (): array => Customer::query()
                ->whereNotNull('city')->where('city', '!=', '')
                ->orderBy('city')->distinct()->pluck('city', 'city')->all())
            ->placeholder('Todas')
            ->live();
    }
}
