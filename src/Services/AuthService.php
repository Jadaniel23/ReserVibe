<?php
// src/Services/AuthService.php

namespace App\Services;

use App\Helpers\Config;
use App\Repositories\JsonFileStore;

/** Session authentication and a small JSON-backed login throttle for the local demo. */
class AuthService
{
    private const MAX_FAILURES = 5;
    private const WINDOW_SECONDS = 600;
    private const LOCK_SECONDS = 300;

    public static function retryAfter(string $username): int
    {
        $key = self::attemptKey($username);
        foreach (self::attemptStore()->read() as $row) {
            if (is_array($row) && ($row['key'] ?? '') === $key) return max(0, (int) ($row['locked_until'] ?? 0) - time());
        }
        return 0;
    }

    public static function login(string $username, string $password): ?string
    {
        if (self::retryAfter($username) > 0) return null;
        foreach (Config::get('demo_accounts', []) as $role => $account) {
            $expectedUser = (string) ($account['username'] ?? '');
            $passwordHash = (string) ($account['password_hash'] ?? '');
            if ($expectedUser !== '' && $passwordHash !== ''
                && hash_equals($expectedUser, $username) && password_verify($password, $passwordHash)) {
                if (session_status() !== PHP_SESSION_ACTIVE) session_start();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $expectedUser;
                $_SESSION['user_role'] = (string) $role;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                self::clearFailures($username);
                return (string) $role;
            }
        }
        self::recordFailure($username);
        return null;
    }

    private static function attemptKey(string $username): string
    {
        return hash('sha256', strtolower(trim($username)) . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    }

    private static function attemptStore(): JsonFileStore
    {
        return new JsonFileStore((string) Config::get('paths.data') . '/login_attempts.json');
    }

    private static function recordFailure(string $username): void
    {
        $key = self::attemptKey($username);
        self::attemptStore()->atomicUpdate(static function (array $records) use ($key): array {
            $now = time();
            $records = array_values(array_filter($records, static fn ($row) => is_array($row)
                && max((int) ($row['locked_until'] ?? 0), (int) ($row['window_started_at'] ?? 0)) > $now - 86400));
            $index = null;
            foreach ($records as $i => $row) if (($row['key'] ?? '') === $key) { $index = $i; break; }
            $row = $index === null ? [] : $records[$index];
            if ((int) ($row['window_started_at'] ?? 0) < $now - self::WINDOW_SECONDS) {
                $row = ['key' => $key, 'failures' => 0, 'window_started_at' => $now, 'locked_until' => 0];
            }
            $row['key'] = $key;
            $row['failures'] = (int) ($row['failures'] ?? 0) + 1;
            if ($row['failures'] >= self::MAX_FAILURES) {
                $row['locked_until'] = $now + self::LOCK_SECONDS;
                $row['failures'] = 0;
                $row['window_started_at'] = $now;
            }
            if ($index === null) $records[] = $row; else $records[$index] = $row;
            return [$records, null];
        });
    }

    private static function clearFailures(string $username): void
    {
        $key = self::attemptKey($username);
        self::attemptStore()->atomicUpdate(static function (array $records) use ($key): array {
            return [array_values(array_filter($records, static fn ($row) => !is_array($row) || ($row['key'] ?? '') !== $key)), null];
        });
    }

    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'],
                'secure' => $params['secure'], 'httponly' => $params['httponly'], 'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function role(): string
    {
        return session_status() === PHP_SESSION_ACTIVE ? (string) ($_SESSION['user_role'] ?? 'guest') : 'guest';
    }
}
