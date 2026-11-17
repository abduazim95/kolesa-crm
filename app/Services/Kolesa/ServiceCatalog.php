<?php

namespace App\Services\Kolesa;

use App\Models\Advert;

class ServiceCatalog
{
    /**
     * Human-readable labels for all known service/package names.
     */
    public const LABELS = [
        're' => 'Продление',
        'up' => 'Поднять',
        'hot' => 'Горячие',
        'vip' => 'VIP',
        'turbo-sale' => 'Турбо',
        'fast-sale' => 'Fast',
        'archive-re' => 'Восстановить из архива',
    ];

    /**
     * Services/packages exposed in UI for each storage_id.
     * Only names listed here are selectable when adding to cart.
     */
    public const ALLOWED_BY_STORAGE = [
        Advert::STATUS_LIVE => ['re', 'up', 'hot', 'vip', 'turbo-sale', 'fast-sale'],
        Advert::STATUS_ARCHIVE => ['archive-re'],
    ];

    /**
     * Return the list of services/packages available for this advert,
     * each with a snapshot price pulled from its raw payload.
     *
     * @return array<int, array{name: string, label: string, price: int, type: string}>
     */
    public function forAdvert(Advert $advert): array
    {
        $allowed = self::ALLOWED_BY_STORAGE[$advert->status] ?? [];
        $out = [];

        foreach ($allowed as $name) {
            $price = $this->priceFor($advert, $name);

            if ($price === null) {
                continue;
            }

            $out[] = [
                'name' => $name,
                'label' => self::LABELS[$name] ?? $name,
                'price' => $price,
                'type' => $this->typeOf($name),
            ];
        }

        return $out;
    }

    /**
     * Snapshot price of a specific service/package for this advert.
     * Returns null if the service is not applicable or price missing.
     */
    public function priceFor(Advert $advert, string $serviceName): ?int
    {
        $allowed = self::ALLOWED_BY_STORAGE[$advert->status] ?? [];

        if (! in_array($serviceName, $allowed, true)) {
            return null;
        }

        $raw = $advert->raw ?? [];

        // Packages live in `packages[]`.
        // Prefer the discounted price (`discount.value`) when present —
        // that's the amount that will actually be charged.
        if ($this->typeOf($serviceName) === 'package') {
            foreach ((array) data_get($raw, 'packages', []) as $pkg) {
                if (($pkg['name'] ?? null) !== $serviceName) {
                    continue;
                }

                $discounted = data_get($pkg, 'discount.value');

                if (is_numeric($discounted)) {
                    return (int) $discounted;
                }

                if (isset($pkg['price']) && is_numeric($pkg['price'])) {
                    return (int) $pkg['price'];
                }
            }

            return null;
        }

        // Plain services live in `payment.data.<name>`.
        $price = data_get($raw, 'payment.data.'.$serviceName);

        return is_numeric($price) ? (int) $price : null;
    }

    public function label(string $serviceName): string
    {
        return self::LABELS[$serviceName] ?? $serviceName;
    }

    public function isAllowedFor(string $storageId, string $serviceName): bool
    {
        return in_array(
            $serviceName,
            self::ALLOWED_BY_STORAGE[$storageId] ?? [],
            true,
        );
    }

    /**
     * Which storage_id does this service target?
     * Returns null for unknown service names.
     */
    public function storageForService(string $serviceName): ?string
    {
        foreach (self::ALLOWED_BY_STORAGE as $storage => $services) {
            if (in_array($serviceName, $services, true)) {
                return $storage;
            }
        }

        return null;
    }

    /**
     * Flat list of (name, label, storage_id) for every known service/package.
     *
     * @return array<int, array{name: string, label: string, storage_id: string}>
     */
    public function allServices(): array
    {
        $out = [];
        foreach (self::ALLOWED_BY_STORAGE as $storage => $services) {
            foreach ($services as $name) {
                $out[] = [
                    'name' => $name,
                    'label' => self::LABELS[$name] ?? $name,
                    'storage_id' => $storage,
                ];
            }
        }

        return $out;
    }

    private function typeOf(string $serviceName): string
    {
        return in_array($serviceName, ['turbo-sale', 'fast-sale'], true)
            ? 'package'
            : 'service';
    }
}
