<?php
// src/Repositories/ReservationRepository.php

namespace App\Repositories;

use App\Helpers\Config;

/**
 * Modules 3, 6 and 9 — storage for reservations.
 *
 * Statuses a reservation can hold:
 *   pending_payment | pending_verification | confirmed | payment_rejected | cancelled
 * Guest-arrival status (Module 9) is tracked separately in `guest_status`:
 *   reserved | arrived | left
 */
class ReservationRepository
{
    /**
     * Statuses that occupy the slot. A reservation only stops blocking the
     * calendar once payment is rejected or it is cancelled.
     */
    public const BLOCKING_STATUSES = ['pending_payment', 'pending_verification', 'confirmed'];

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PAYMENT_REJECTED = 'payment_rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const GUEST_RESERVED = 'reserved';

    private JsonFileStore $store;

    public function __construct(?JsonFileStore $store = null)
    {
        $this->store = $store ?? new JsonFileStore(Config::get('paths.data') . '/reservations.json');
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->store->read();
    }

    /** @return array<int, array<string, mixed>> */
    public function writeAll(array $reservations): void
    {
        $this->store->replace($reservations);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        foreach ($this->all() as $reservation) {
            if ((int) ($reservation['id'] ?? 0) === $id) {
                return $reservation;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function byStatus(?string $status = null): array
    {
        $all = $this->all();

        if ($status === null) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            static fn (array $reservation): bool => ($reservation['status'] ?? null) === $status
        ));
    }

    /** Next sequential id. Must be called while holding the booking lock. */
    public function nextId(array $reservations): int
    {
        $max = 0;

        foreach ($reservations as $reservation) {
            $max = max($max, (int) ($reservation['id'] ?? 0));
        }

        return $max + 1;
    }
}
