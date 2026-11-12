<?php

namespace App\Filament\Concerns;

use App\Models\TableColumnPreference;

/**
 * Guarda en la base las columnas visibles de la tabla para cada usuario, en lugar de solo en la
 * sesión. Se usa en componentes con `InteractsWithTable`: si la clase incluye el trait de Filament
 * directamente hay que resolver el conflicto con `insteadof` para estos dos métodos.
 */
trait PersistsTableColumnsPerUser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadTableColumnsFromSession(): array
    {
        if (! $this->getTable()->persistsColumnsInSession()) {
            return $this->getDefaultTableColumnState();
        }

        $userId = auth()->id();

        if ($userId !== null) {
            $columns = TableColumnPreference::query()
                ->where('user_id', $userId)
                ->where('table', static::class)
                ->value('columns');

            if (is_array($columns) && $columns !== []) {
                session()->put($this->getTableColumnsSessionKey(), $columns);

                return $columns;
            }
        }

        return session()->get($this->getTableColumnsSessionKey(), $this->getDefaultTableColumnState());
    }

    protected function persistTableColumns(): void
    {
        if (! $this->getTable()->persistsColumnsInSession()) {
            return;
        }

        $userId = auth()->id();

        if ($userId !== null) {
            $stored = session()->get($this->getTableColumnsSessionKey(), $this->getDefaultTableColumnState());

            if ($stored !== $this->tableColumns) {
                TableColumnPreference::query()->updateOrCreate(
                    ['user_id' => $userId, 'table' => static::class],
                    ['columns' => $this->tableColumns],
                );
            }
        }

        session()->put($this->getTableColumnsSessionKey(), $this->tableColumns);
    }
}
