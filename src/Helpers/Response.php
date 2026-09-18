<?php
// src/Helpers/Response.php

namespace App\Helpers;

class Response {
    public static function json($data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    public static function render($view, $data = []) {
        extract($data);
        // In a real app, we'd use a layout. For the prototype, we'll just include the file.
        $viewPath = __DIR__ . "/../../views/{$view}.php";
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            die("View not found: {$view}");
        }
    }
}
