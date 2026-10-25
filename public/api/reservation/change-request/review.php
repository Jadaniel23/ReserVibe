<?php
declare(strict_types=1);
use App\Helpers\Response;
use App\Services\CsrfProtection;
use App\Services\ReservationChangeRequestService;
use App\Services\GoogleCalendarService;
require_once __DIR__ . '/../../bootstrap.php';
Response::requireMethod('POST');
Response::requireRole(['staff', 'admin']);
$input = Response::input();
if (!CsrfProtection::validate(CsrfProtection::fromRequest($input))) Response::error('Invalid or missing CSRF token.', 419);
$id = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$decision = $input['decision'] ?? null;
$note = $input['note'] ?? '';
if (!$id || !is_string($decision) || !is_string($note)) Response::error('Provide a request, decision, and optional note.', 422);
try {
    $request = (new ReservationChangeRequestService())->review((int) $id, $decision, trim($note), (string) $_SESSION['user_id']);
    $calendarSync = $decision === 'approve'
        ? (new GoogleCalendarService())->syncAfterReservationChange()
        : ['status' => 'not_needed'];
    Response::success(['request' => $request, 'calendar_sync' => $calendarSync]);
} catch (InvalidArgumentException $error) {
    Response::error($error->getMessage(), 409);
} catch (RuntimeException $error) {
    Response::error($error->getMessage(), 500);
}
