<?php
declare(strict_types=1);
use App\Helpers\Response;
use App\Services\ReservationChangeRequestService;
require_once __DIR__ . '/../../bootstrap.php';
Response::requireMethod('GET');
Response::requireRole(['staff', 'admin']);
$status = isset($_GET['status']) && $_GET['status'] !== '' ? (string) $_GET['status'] : null;
if ($status !== null && !in_array($status, ['pending', 'approved', 'rejected'], true)) Response::error('Unknown request status.', 422);
$requests = (new ReservationChangeRequestService())->all($status);
Response::success(['requests' => $requests, 'count' => count($requests)]);
