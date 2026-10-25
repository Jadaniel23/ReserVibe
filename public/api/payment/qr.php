<?php
declare(strict_types=1);
use App\Helpers\Response;
use App\Services\PaymentQrService;
require_once __DIR__ . '/../bootstrap.php';
Response::requireMethod('GET');
$method = $_GET['method'] ?? null;
if (!is_string($method)) Response::error('Choose a configured payment method.', 422);
try { $record = (new PaymentQrService())->imageRecord($method); }
catch (InvalidArgumentException $error) { Response::error($error->getMessage(), 422); }
if ($record === null) Response::error('No QR code has been configured for this payment method.', 404);
header_remove('Content-Type');
header('Content-Type: ' . $record['mime']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Content-Length: ' . filesize($record['path']));
readfile($record['path']);
