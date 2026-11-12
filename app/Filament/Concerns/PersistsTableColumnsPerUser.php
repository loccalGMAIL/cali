<?php

namespace App\Filament\Concerns;

use App\Models\TableColumnPreference;

/**
 * Guarda en la base las columnas visibles de la tabla para cada usuario, en lugar de solo en la
 * sesión. Se usa en componentes con `InteractsWithTable`.
 */
trait PersistsTableColumnsPerUser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadTableColumnsFromSession(): array
    {
        $userId = auth()->id();

        if ($userId !== null && $this->getTable()->persistsColumnsInSession()) {
            $columns = TableColumnPreference::query()
                ->where('user_id', $userId)
                ->where('table', static::class)
                ->value('columns');

            if (is_array($columns) && $columns !== []) {
                session()->put($this->getTableColumnsSessionKey(), $columns);

                return $columns;
            }
        }

        return parent::loadTableColumnsFromSession();
    }

    protected function persistTableColumns(): void
    {
        $userId = auth()->id();

        if ($userId !== null && $this->getTable()->persistsColumnsInSession()) {
            $stored = session()->get($this->getTableColumnsSessionKey(), $this->getDefaultTableColumnState());

            if ($stored !== $this->tableColumns) {
                TableColumnPreference::query()->updateOrCreate(
                    ['user_id' => $userId, 'table' => static::class],
                    ['columns' => $this->tableColumns],
                );
            }
        }

        parent::persistTableColumns();
    }
}
