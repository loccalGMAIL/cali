<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Concerns\PersistsTableColumnsPerUser;
use App\Models\Customer;
use App\Services\Reports\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Base de las páginas de informes: filtros arriba, la tabla del informe debajo (una tabla de
 * Filament alimentada con las filas del informe) y un botón para abrir la versión imprimible con
 * los mismos filtros. Solo para administradores.
 *
 * @property-read Schema $form
 */
abstract class ReportPage extends Page implements HasTable
{
    use InteractsWithTable, PersistsTableColumnsPerUser {
        PersistsTableColumnsPerUser::loadTableColumnsFromSession insteadof InteractsWithTable;
        PersistsTableColumnsPerUser::persistTableColumns insteadof InteractsWithTable;
    }

    protected string $view = 'filament.pages.report';

    protected static string|UnitEnum|null $navigationGroup = 'Informes';

    /**
     * Estado del formulario de filtros.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Resultado del informe para los filtros vigentes, calculado una sola vez por pedido.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $cachedReport = null;

    /**
     * @return class-string<Report>
     */
    abstract protected static function reportClass(): string;

    /**
     * @return array<Component>
     */
    abstract protected function filterComponents(): array;

    /**
     * Columnas de la tabla. Las que dependen de un filtro se declaran todas y se muestran con
     * `visible()`, así el nombre de cada columna no cambia y su elección queda guardada.
     *
     * @return array<Column>
     */
    abstract protected function reportColumns(): array;

    /**
     * Filas de la tabla a partir del resultado del informe, indexadas por una clave única. Los
     * montos van como números para poder ordenar.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, array<string, mixed>>
     */
    abstract protected function tableRows(array $report): array;

    /**
     * Columnas (de las filas) donde busca el buscador de la tabla.
     *
     * @return list<string>
     */
    abstract protected function searchKeys(): array;

    abstract protected function emptyMessage(): string;

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

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $sortColumn, ?string $sortDirection, ?string $search, int $page, int|string $recordsPerPage): LengthAwarePaginator => $this->paginateRows(
                $this->tableRows($this->reportData()),
                $sortColumn,
                $sortDirection,
                $search,
                $page,
                $recordsPerPage,
            ))
            ->columns($this->reportColumns())
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading($this->emptyMessage())
            ->emptyStateIcon(Heroicon::OutlinedDocumentMagnifyingGlass);
    }

    /**
     * Al cambiar un filtro se vuelve a la primera página.
     */
    public function updatedData(): void
    {
        $this->cachedReport = null;
        $this->resetPage();
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
        return [
            'summary' => 'reports.summaries.'.static::reportClass()::slug(),
            'filters' => $this->filters(),
            'report' => $this->reportData(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function reportData(): array
    {
        return $this->cachedReport ??= static::report()->build($this->filters());
    }

    protected static function report(): Report
    {
        return app(static::reportClass());
    }

    /**
     * Busca, ordena y pagina las filas: Filament no lo hace con datos propios.
     *
     * @param  array<string, array<string, mixed>>  $rows
     */
    protected function paginateRows(array $rows, ?string $sortColumn, ?string $sortDirection, ?string $search, int $page, int|string $recordsPerPage): LengthAwarePaginator
    {
        $records = collect($rows);

        if (filled($search)) {
            $needle = $this->searchable($search);

            $records = $records->filter(fn (array $row): bool => collect($this->searchKeys())
                ->contains(fn (string $key): bool => str_contains($this->searchable($row[$key] ?? ''), $needle)));
        }

        if (filled($sortColumn)) {
            $records = $records->sortBy(
                fn (array $row): mixed => is_string($row[$sortColumn] ?? null) ? $this->searchable($row[$sortColumn]) : ($row[$sortColumn] ?? null),
                SORT_REGULAR,
                $sortDirection === 'desc',
            );
        }

        $total = $records->count();
        $perPage = is_numeric($recordsPerPage) ? max(1, (int) $recordsPerPage) : max(1, $total);

        return new LengthAwarePaginator($records->forPage($page, $perPage), total: $total, perPage: $perPage, currentPage: $page);
    }

    /**
     * Texto en minúsculas y sin acentos, para buscar y ordenar.
     */
    private function searchable(mixed $value): string
    {
        return Str::lower(Str::ascii((string) $value));
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
