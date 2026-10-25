<?php
// src/Services/PaymentQrService.php

namespace App\Services;

use App\Helpers\Config;
use App\Repositories\JsonFileStore;
use InvalidArgumentException;
use RuntimeException;

/** Stores owner-supplied payment QR images outside the public web root. */
class PaymentQrService
{
    private function store(): JsonFileStore
    {
        return new JsonFileStore((string) Config::get('paths.data') . '/payment_qrs.json');
    }

    public function list(): array
    {
        $records = $this->store()->read();
        $byMethod = [];
        foreach ($records as $record) if (is_array($record) && is_string($record['method'] ?? null)) $byMethod[$record['method']] = $record;
        $items = [];
        foreach (Config::get('payment_methods', []) as $method) {
            if (empty($method['requires_proof'])) continue;
            $code = (string) $method['code'];
            $items[] = [
                'method' => $code,
                'label' => (string) $method['label'],
                'configured' => isset($byMethod[$code]) && is_file($this->imagePath((string) ($byMethod[$code]['filename'] ?? ''))),
                'url' => '/api/payment/qr.php?method=' . rawurlencode($code),
            ];
        }
        return $items;
    }

    public function imageRecord(string $method): ?array
    {
        $this->assertMethod($method);
        foreach ($this->store()->read() as $record) {
            if (($record['method'] ?? '') === $method && is_string($record['filename'] ?? null)) {
                $path = $this->imagePath($record['filename']);
                $mime = $record['mime'] ?? null;
                if (is_file($path) && is_string($mime) && in_array($mime, Config::get('uploads.allowed_mime', []), true)) {
                    return ['path' => $path, 'mime' => $mime];
                }
            }
        }
        return null;
    }

    public function upload(string $method, array $file): array
    {
        $this->assertMethod($method);
        $tmp = $file['tmp_name'] ?? '';
        $size = (int) ($file['size'] ?? 0);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp)
            || $size < 1 || $size > (int) Config::get('payment_qr.max_bytes', 5242880)) {
            throw new InvalidArgumentException('Choose a QR image up to 5 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if ($extension === null || !in_array($mime, Config::get('uploads.allowed_mime', []), true)) {
            throw new InvalidArgumentException('QR image must be JPEG, PNG, or WebP.');
        }
        $dir = (string) Config::get('payment_qr.dir');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Could not prepare QR image storage.');
        $filename = bin2hex(random_bytes(24)) . '.' . $extension;
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $path)) throw new RuntimeException('Could not save the QR image.');
        @chmod($path, 0600);

        $store = $this->store();
        $old = $store->atomicUpdate(function (array $rows) use ($method, $filename, $mime): array {
            $old = null;
            $found = false;
            foreach ($rows as $i => $row) {
                if (($row['method'] ?? '') !== $method) continue;
                $old = $row['filename'] ?? null;
                $rows[$i] = ['method' => $method, 'filename' => $filename, 'mime' => $mime, 'updated_at' => date('c')];
                $found = true;
                break;
            }
            if (!$found) $rows[] = ['method' => $method, 'filename' => $filename, 'mime' => $mime, 'updated_at' => date('c')];
            return [$rows, $old];
        });
        if (is_string($old) && $old !== $filename) @unlink($this->imagePath($old));
        return ['method' => $method, 'configured' => true, 'url' => '/api/payment/qr.php?method=' . rawurlencode($method)];
    }

    public function remove(string $method): void
    {
        $this->assertMethod($method);
        $old = $this->store()->atomicUpdate(function (array $rows) use ($method): array {
            $filename = null;
            $rows = array_values(array_filter($rows, static function (array $row) use ($method, &$filename): bool {
                if (($row['method'] ?? '') !== $method) return true;
                $filename = $row['filename'] ?? null;
                return false;
            }));
            return [$rows, $filename];
        });
        if (is_string($old)) @unlink($this->imagePath($old));
    }

    private function assertMethod(string $code): void
    {
        foreach (Config::get('payment_methods', []) as $method) {
            if (($method['code'] ?? '') === $code && !empty($method['requires_proof'])) return;
        }
        throw new InvalidArgumentException('QR uploads are only available for payment methods that require proof.');
    }

    private function imagePath(string $filename): string
    {
        if (!preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $filename)) return (string) Config::get('payment_qr.dir') . '/invalid';
        return (string) Config::get('payment_qr.dir') . '/' . $filename;
    }
}
