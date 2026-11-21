<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Datos del negocio y la opción de mostrarlos en el encabezado de los comprobantes. Se guardan
 * en la tabla `settings`, una fila por campo.
 *
 * @phpstan-type BusinessData array{
 *     business_name: string|null,
 *     business_tax_id: string|null,
 *     business_address: string|null,
 *     business_phone: string|null,
 *     business_email: string|null,
 *     business_logo: string|null,
 *     receipt_shows_business_header: bool
 * }
 */
class BusinessSettings
{
    /** Disco donde se guarda el logo. */
    public const LOGO_DISK = 'public';

    /** Carpeta del disco donde se guarda el logo. */
    public const LOGO_DIRECTORY = 'business';

    /**
     * @var list<string>
     */
    private const TEXT_KEYS = [
        'business_name',
        'business_tax_id',
        'business_address',
        'business_phone',
        'business_email',
        'business_logo',
    ];

    private const HEADER_KEY = 'receipt_shows_business_header';

    /**
     * @return BusinessData
     */
    public function all(): array
    {
        $values = Setting::query()
            ->whereIn('key', [...self::TEXT_KEYS, self::HEADER_KEY])
            ->pluck('value', 'key');

        $data = [];

        foreach (self::TEXT_KEYS as $key) {
            $data[$key] = filled($values->get($key)) ? (string) $values->get($key) : null;
        }

        $data[self::HEADER_KEY] = (bool) $values->get(self::HEADER_KEY, false);

        /** @var BusinessData $data */
        return $data;
    }

    /**
     * Guarda los datos del negocio. Si cambia el logo, borra el archivo anterior.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $previousLogo = $this->all()['business_logo'];

        foreach (self::TEXT_KEYS as $key) {
            $value = $data[$key] ?? null;

            Setting::query()->updateOrCreate(['key' => $key], ['value' => filled($value) ? trim((string) $value) : null]);
        }

        Setting::query()->updateOrCreate(['key' => self::HEADER_KEY], ['value' => ($data[self::HEADER_KEY] ?? false) ? '1' : '0']);

        if ($previousLogo !== null && $previousLogo !== ($data['business_logo'] ?? null)) {
            Storage::disk(self::LOGO_DISK)->delete($previousLogo);
        }
    }

    /**
     * El logo como data URI, para incrustarlo en el PDF sin depender de una URL pública.
     */
    public function logoDataUri(): ?string
    {
        $path = $this->all()['business_logo'];
        $disk = Storage::disk(self::LOGO_DISK);

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode((string) $disk->get($path));
    }
}
