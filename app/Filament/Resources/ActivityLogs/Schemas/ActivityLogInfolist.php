<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use App\Models\ActivityLog;
use App\Support\ActivityDetails;
use App\Support\ActivityLabels;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Actividad')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('created_at')->label('Fecha y hora')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('user.name')->label('Usuario')->placeholder('Sistema o desconocido'),
                        TextEntry::make('event')->label('Evento')->badge(),
                        TextEntry::make('description')->label('Descripción')->columnSpanFull(),
                        TextEntry::make('subject')->label('Registro afectado')
                            ->state(fn (ActivityLog $record): ?string => $record->subject_type === null
                                ? null
                                : ActivityLabels::subject($record->subject_type).' #'.$record->subject_id)
                            ->placeholder('—'),
                        TextEntry::make('ip_address')->label('Dirección IP')->placeholder('—'),
                        TextEntry::make('user_agent')->label('Navegador')->placeholder('—'),
                    ]),
                Section::make('Detalle')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('details')->hiddenLabel()
                            ->state(fn (ActivityLog $record) => ActivityDetails::render($record))
                            ->html(),
                    ]),
            ]);
    }
}
