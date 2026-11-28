<?php

namespace App\Support;

/**
 * Enlaces `wa.me` para abrir un chat de WhatsApp con un mensaje ya escrito. WhatsApp no permite
 * adjuntar archivos por este medio: solo texto.
 */
class WhatsApp
{
    /**
     * Pasa un teléfono cargado a mano al formato internacional que espera WhatsApp, sin `+` ni
     * espacios. Los celulares argentinos quedan como `549` + característica + número, sin el 0 ni
     * el 15: `0351 15-555-1234` → `5493515551234`. Un número con otro código de país (empieza con
     * `+` o `00`) se deja como está. Si el número no se puede interpretar, por ejemplo porque le
     * falta la característica, devuelve `null`.
     */
    public static function phone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        $digits = (string) preg_replace('/\D+/', '', $phone);
        $isInternational = str_starts_with($phone, '+') || str_starts_with($digits, '00');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($isInternational && ! str_starts_with($digits, '54')) {
            return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
        }

        // Ninguna característica argentina empieza con 5: un 54 adelante es el código de país.
        if (str_starts_with($digits, '54')) {
            $digits = substr($digits, 2);
        }

        // Ni con 9 (el prefijo de celular en formato internacional) ni con 0 (el de larga distancia).
        $digits = ltrim($digits, '90');

        $national = strlen($digits) === 12 ? self::withoutMobilePrefix($digits) : $digits;

        return $national !== null && preg_match('/^(11\d{8}|[23]\d{9})$/', $national) === 1 ? '549'.$national : null;
    }

    /**
     * Enlace que abre el chat con ese teléfono y el mensaje escrito. Sin un teléfono válido, WhatsApp
     * pide elegir el contacto.
     */
    public static function url(?string $phone, string $message): string
    {
        return 'https://wa.me/'.(self::phone($phone) ?? '').'?text='.rawurlencode($message);
    }

    /**
     * Quita el 15 que va entre la característica (de 2 a 4 dígitos) y el número: `351 15 5551234`.
     */
    private static function withoutMobilePrefix(string $digits): ?string
    {
        foreach ([2, 3, 4] as $areaCodeLength) {
            $areaCode = substr($digits, 0, $areaCodeLength);
            $isAreaCode = $areaCodeLength === 2 ? $areaCode === '11' : in_array($areaCode[0], ['2', '3'], true);

            if ($isAreaCode && substr($digits, $areaCodeLength, 2) === '15') {
                return $areaCode.substr($digits, $areaCodeLength + 2);
            }
        }

        return null;
    }
}
