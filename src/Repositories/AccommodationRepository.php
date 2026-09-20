<?php
// src/Repositories/AccommodationRepository.php

namespace App\Repositories;

use App\Helpers\Config;

/**
 * Module 1 — Accommodation & Package Catalog.
 *
 * The catalogue of record. Rates live in data/accommodations.json and nowhere
 * else: the booking form renders its options from here, so there is no second
 * price list to drift out of sync.
 */
class AccommodationRepository
{
    private JsonFileStore $store;

    public function __construct(?JsonFileStore $store = null)
    {
        $this->store = $store ?? new JsonFileStore(Config::get('paths.data') . '/accommodations.json');
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->store->read();
    }

    /** @return array<int, array<string, mixed>> */
    public function active(?string $type = null): array
    {
        return array_values(array_filter(
            $this->all(),
            static function (array $room) use ($type): bool {
                if (($room['active'] ?? true) !== true) {
                    return false;
                }

                return $type === null || ($room['type'] ?? null) === $type;
            }
        ));
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        foreach ($this->all() as $room) {
            if ((int) ($room['id'] ?? 0) === $id) {
                return $room;
            }
        }

        return null;
    }

    /** Valid values for the ?type= filter. */
    public function types(): array
    {
        $types = array_map(
            static fn (array $room): string => (string) ($room['type'] ?? ''),
            $this->all()
        );

        return array_values(array_unique(array_filter($types)));
    }
}
