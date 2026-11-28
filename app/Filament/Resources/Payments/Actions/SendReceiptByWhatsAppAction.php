<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Models\Payment;
use App\Services\BusinessSettings;
use App\Services\PaymentReceipt;
use App\Support\Money;
use App\Support\WhatsApp;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Abre WhatsApp con el chat del cliente y un mensaje con el enlace al comprobante. WhatsApp no deja
 * adjuntar el PDF desde un enlace: el cliente lo abre desde el link, sin usuario (ver
 * {@see PaymentReceipt::sharedUrl()}). Si el cliente no tiene un celular válido, WhatsApp pide
 * elegir el contacto.
 */
class SendReceiptByWhatsAppAction
{
    public static function make(): Action
    {
        return Action::make('whatsapp')->label('WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('success')
            ->url(fn (Payment $record): string => static::url($record))
            ->openUrlInNewTab()
            ->tooltip(fn (Payment $record): string => WhatsApp::phone($record->customer->phone) === null
                ? 'WhatsApp (el cliente no tiene un celular válido: elegís el contacto)'
                : 'WhatsApp')
            ->visible(fn (Payment $record): bool => ! $record->isCancelled() && (auth()->user()?->can('view', $record) ?? false));
    }

    public static function url(Payment $payment): string
    {
        return WhatsApp::url($payment->customer->phone, static::message($payment));
    }

    /**
     * Mensaje para el cliente, con el enlace al PDF y la firma del negocio si está cargado.
     */
    public static function message(Payment $payment): string
    {
        $businessName = once(fn (): ?string => app(BusinessSettings::class)->all()['business_name']);

        return collect([
            "Hola {$payment->customer->name}, te enviamos el comprobante {$payment->number()} de tu pago de "
                .Money::format($payment->amount).' del '.$payment->date->format('d/m/Y').':',
            PaymentReceipt::sharedUrl($payment),
            $businessName === null ? '¡Gracias!' : "¡Gracias! {$businessName}",
        ])->implode("\n");
    }
}
