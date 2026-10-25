<?php
declare(strict_types=1);
use App\Helpers\Response;
use App\Services\CsrfProtection;
use App\Services\ReservationChangeRequestService;
require_once __DIR__ . '/../../bootstrap.php';
Response::requireMethod('POST');
$input = Response::input();
if (!CsrfProtection::validate(CsrfProtection::fromRequest($input))) Response::error('Invalid or missing CSRF token.', 419);
$reference = $input['reference'] ?? null;
$email = $input['email'] ?? null;
if (!is_string($reference) || !preg_match('/^RSV-\d{4,}$/i', $reference) || !is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('Enter the reservation reference and booking email.', 422);
}
try {
    Response::success(['requests' => (new ReservationChangeRequestService())->guestStatus($reference, $email)]);
} catch (InvalidArgumentException $error) {
    Response::error($error->getMessage(), 404);
}
