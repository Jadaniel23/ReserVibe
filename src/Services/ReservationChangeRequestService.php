<?php
// src/Services/ReservationChangeRequestService.php

namespace App\Services;

use App\Helpers\Config;
use App\Helpers\Validator;
use App\Repositories\JsonFileStore;
use App\Repositories\ReservationRepository;
use InvalidArgumentException;

/** Guest cancellation/reschedule requests, approved or declined by staff. */
class ReservationChangeRequestService
{
    private JsonFileStore $store;
    private ReservationRepository $reservations;

    public function __construct()
    {
        $this->store = new JsonFileStore((string) Config::get('paths.data') . '/change_requests.json');
        $this->reservations = new ReservationRepository();
    }

    public function submit(string $action, string $reference, string $email, string $reason, ?string $checkIn = null, ?string $checkOut = null): array
    {
        if (!in_array($action, ['cancel', 'reschedule'], true)) throw new InvalidArgumentException('Choose a cancellation or reschedule request.');
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) throw new InvalidArgumentException('Enter a reason (up to 500 characters).');
        if ($action === 'reschedule' && (Validator::date($checkIn) === null || Validator::date($checkOut) === null || $checkOut <= $checkIn)) {
            throw new InvalidArgumentException('Choose valid new check-in and check-out dates.');
        }

        return JsonFileStore::withLock($this->lockPath(), function () use ($action, $reference, $email, $reason, $checkIn, $checkOut): array {
            $reservation = $this->findReservation($reference);
            if ($reservation === null || strcasecmp((string) ($reservation['guest_email'] ?? ''), trim($email)) !== 0) {
                throw new InvalidArgumentException('Reservation reference or email did not match.');
            }
            if (!in_array($reservation['status'] ?? '', ReservationRepository::BLOCKING_STATUSES, true)
                || ($reservation['guest_status'] ?? 'reserved') !== 'reserved') {
                throw new InvalidArgumentException('Only an active stay that has not started can be changed.');
            }
            $items = $this->store->read();
            foreach ($items as $item) {
                if ((int) ($item['reservation_id'] ?? 0) === (int) $reservation['id'] && ($item['status'] ?? '') === 'pending') {
                    throw new InvalidArgumentException('A change request is already waiting for staff review.');
                }
            }
            $id = 1;
            foreach ($items as $item) $id = max($id, (int) ($item['id'] ?? 0) + 1);
            $request = [
                'id' => $id,
                'reservation_id' => (int) $reservation['id'],
                'reservation_reference' => (string) $reservation['reference'],
                'action' => $action,
                'status' => 'pending',
                'reason' => $reason,
                'requested_check_in' => $action === 'reschedule' ? $checkIn : null,
                'requested_check_out' => $action === 'reschedule' ? $checkOut : null,
                'created_at' => date('c'),
            ];
            $items[] = $request;
            $this->store->replace($items);
            return $request;
        });
    }

    public function review(int $id, string $decision, string $note, string $actor): array
    {
        if (!in_array($decision, ['approve', 'reject'], true)) throw new InvalidArgumentException('Choose approve or reject.');
        return JsonFileStore::withLock($this->lockPath(), function () use ($id, $decision, $note, $actor): array {
            $items = $this->store->read();
            $index = null;
            foreach ($items as $i => $item) if ((int) ($item['id'] ?? 0) === $id) { $index = $i; break; }
            if ($index === null || ($items[$index]['status'] ?? '') !== 'pending') throw new InvalidArgumentException('That request is no longer awaiting review.');
            if (mb_strlen($note) > 500) throw new InvalidArgumentException('Review note must be 500 characters or fewer.');
            $request = $items[$index];

            if ($decision === 'approve') {
                $operations = new ReservationOperationsService();
                if (($request['action'] ?? '') === 'cancel') {
                    $operations->cancel((string) $request['reservation_reference'], null, $actor, true);
                } else {
                    $operations->reschedule(
                        (string) $request['reservation_reference'], null,
                        (string) ($request['requested_check_in'] ?? ''),
                        (string) ($request['requested_check_out'] ?? ''), $actor, true
                    );
                }
            }

            $items[$index]['status'] = $decision === 'approve' ? 'approved' : 'rejected';
            $items[$index]['review_note'] = trim($note);
            $items[$index]['reviewed_by'] = $actor;
            $items[$index]['reviewed_at'] = date('c');
            $this->store->replace($items);
            return $items[$index];
        });
    }

    public function all(?string $status = null): array
    {
        $items = $this->store->read();
        if ($status !== null) $items = array_values(array_filter($items, static fn (array $item): bool => ($item['status'] ?? '') === $status));
        $reservations = [];
        foreach ($this->reservations->all() as $reservation) $reservations[(int) ($reservation['id'] ?? 0)] = $reservation;
        foreach ($items as &$item) {
            $reservation = $reservations[(int) ($item['reservation_id'] ?? 0)] ?? [];
            $item['guest_name'] = (string) ($reservation['guest_name'] ?? '');
            $item['accommodation_name'] = (string) ($reservation['accommodation_name'] ?? '');
            $item['current_check_in'] = (string) ($reservation['check_in'] ?? '');
            $item['current_check_out'] = (string) ($reservation['check_out'] ?? '');
        }
        unset($item);
        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return $items;
    }

    public function guestStatus(string $reference, string $email): array
    {
        $reservation = $this->findReservation($reference);
        if ($reservation === null || strcasecmp((string) ($reservation['guest_email'] ?? ''), trim($email)) !== 0) {
            throw new InvalidArgumentException('Reservation reference or email did not match.');
        }
        $items = array_values(array_filter($this->store->read(), static fn (array $item): bool => (int) ($item['reservation_id'] ?? 0) === (int) $reservation['id']));
        usort($items, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return array_map(static function (array $item) use ($reservation): array {
            return [
                'action' => $item['action'] ?? '', 'status' => $item['status'] ?? '',
                'reason' => $item['reason'] ?? '', 'requested_check_in' => $item['requested_check_in'] ?? null,
                'requested_check_out' => $item['requested_check_out'] ?? null,
                'review_note' => $item['review_note'] ?? '', 'created_at' => $item['created_at'] ?? '',
                'reviewed_at' => $item['reviewed_at'] ?? null,
                'current_check_in' => $reservation['check_in'] ?? null,
                'current_check_out' => $reservation['check_out'] ?? null,
                'reservation_status' => $reservation['status'] ?? null,
                'balance_due' => $reservation['balance_due'] ?? null,
                'cancellation_policy_result' => $reservation['cancellation_policy_result'] ?? null,
            ];
        }, $items);
    }

    private function findReservation(string $reference): ?array
    {
        foreach ($this->reservations->all() as $reservation) {
            if (strcasecmp((string) ($reservation['reference'] ?? ''), trim($reference)) === 0) return $reservation;
        }
        return null;
    }

    private function lockPath(): string
    {
        return (string) Config::get('paths.data') . '/.change-requests.lock';
    }
}
