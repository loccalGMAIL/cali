<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityLabels;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Fecha y hora')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Usuario')->placeholder('Sistema')->searchable(),
                TextColumn::make('event')->label('Evento')->badge(),
                TextColumn::make('description')->label('Descripción')->searchable()->wrap(),
                TextColumn::make('subject_type')->label('Registro')
                    ->state(fn (ActivityLog $record): ?string => $record->subject_type === null
                        ? null
                        : ActivityLabels::subject($record->subject_type).' #'.$record->subject_id)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('ip_address')->label('IP')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            // Lo más nuevo primero; el id desempata los registros del mismo segundo.
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('user_id')->label('Usuario')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('event')->label('Evento')->options(ActivityEvent::class)->multiple(),
                Filter::make('period')->label('Período')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Desde '.date('d/m/Y', strtotime($data['from']));
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Hasta '.date('d/m/Y', strtotime($data['until']));
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
            ])
            ->recordUrl(null)
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
