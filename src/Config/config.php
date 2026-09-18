<?php
// src/Config/config.php

if (!function_exists('getEnv')) {
    function getEnv($key, $default = null) {
        $envPath = __DIR__ . '/../../.env';
        if (!file_exists($envPath)) {
            return $default;
        }
        $env = parse_ini_file($envPath);

        // If key doesn't exist or value is empty/null, return the default
        if (!isset($env[$key]) || $env[$key] === '') {
            return $default;
        }

        return $env[$key];
    }
}

return [
    'db' => [
        'host' => getEnv('DB_HOST', 'localhost'),
        'name' => getEnv('DB_NAME', 'reservibe'),
        'user' => getEnv('DB_USER', 'root'),
        'pass' => getEnv('DB_PASS', ''),
    ],
    'app_name' => 'ReserVibe Prototype',
];
