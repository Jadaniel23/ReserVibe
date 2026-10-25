<?php
declare(strict_types=1);
use App\Helpers\Response;
use App\Services\CsrfProtection;
use App\Services\PaymentQrService;
require_once __DIR__ . '/../../bootstrap.php';
Response::requireMethod('POST');
Response::requireRole(['staff', 'admin']);
$input = Response::input();
if (!CsrfProtection::validate(CsrfProtection::fromRequest($input))) Response::error('Invalid or missing CSRF token.', 419);
$method = $input['method'] ?? null;
if (!is_string($method)) Response::error('Choose a payment method.', 422);
$service = new PaymentQrService();
try {
    if (($input['action'] ?? '') === 'remove') {
        $service->remove($method);
        Response::success(['message' => 'Payment QR removed.']);
    }
    if (!isset($_FILES['qr_image']) || !is_array($_FILES['qr_image'])) Response::error('Choose a QR image to upload.', 422);
    Response::success($service->upload($method, $_FILES['qr_image']), 201);
} catch (InvalidArgumentException $error) {
    Response::error($error->getMessage(), 422);
}
