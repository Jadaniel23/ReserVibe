<?php
declare(strict_types=1);
use App\Helpers\Response;
use App\Services\PaymentQrService;
require_once __DIR__ . '/../../bootstrap.php';
Response::requireMethod('GET');
Response::requireRole(['staff', 'admin']);
Response::success(['codes' => (new PaymentQrService())->list()]);
