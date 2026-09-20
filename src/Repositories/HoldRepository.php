<?php
// src/Repositories/HoldRepository.php

namespace App\Repositories;

use App\Helpers\Config;

/**
 * Module 4 — storage for temporary slot holds.
 *
 * This class is deliberately dumb: it reads and writes the collection and
 * knows nothing about whether a hold should be granted. The conflict check has
 * to happen inside the same critical section as the write, so it lives in
 * HoldManager, which takes the booking lock and then uses these methods.
 */
class HoldRepository
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CONFIRMED = 'confirmed';

    private JsonFileStore $store;

    public function __construct(?JsonFileStore $store = null)
    {
        $this->store = $store ?? new JsonFileStore(Config::get('paths.data') . '/holds.json');
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->store->read();
    }

    /** @return array<int, array<string, mixed>> */
    public function writeAll(array $holds): void
    {
        $this->store->replace($holds);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        foreach ($this->all() as $hold) {
            if ((int) ($hold['id'] ?? 0) === $id) {
                return $hold;
            }
        }

        return null;
    }

    /** Next sequential id. Must be called while holding the booking lock. */
    public function nextId(array $holds): int
    {
        $max = 0;

        foreach ($holds as $hold) {
            $max = max($max, (int) ($hold['id'] ?? 0));
        }

        return $max + 1;
    }
}
