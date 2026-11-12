<?php

use App\Filament\Resources\Sales\Actions\CancelSaleAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

it('los botones con ícono muestran solo el ícono y el texto como tooltip', function () {
    $view = ViewAction::make()->label('Ver');
    $cancel = CancelSaleAction::make();

    expect($view->isLabelHidden())->toBeTrue()
        ->and($view->getTooltip())->toBe('Ver')
        ->and($cancel->isLabelHidden())->toBeTrue()
        ->and($cancel->getTooltip())->toBe('Anular');
});

it('los botones sin ícono conservan su texto', function () {
    $save = Action::make('save')->label('Guardar');

    expect($save->isLabelHidden())->toBeFalse()
        ->and($save->getTooltip())->toBeNull();

    $withIcon = Action::make('print')->label('Imprimir')->icon(Heroicon::OutlinedPrinter);

    expect($withIcon->isLabelHidden())->toBeTrue()->and($withIcon->getTooltip())->toBe('Imprimir');
});
