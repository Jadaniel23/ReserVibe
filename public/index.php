<?php
// public/index.php

require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Helpers/Response.php';

// Basic Autoloader for Prototype
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Helpers\Response;

// Use $_GET['action'] for simplicity in prototype (e.g. ?action=rooms)
$action = $_GET['action'] ?? 'home';

switch ($action) {
    case 'home':
        Response::render('guest/home');
        break;
    case 'rooms':
        $controller = new App\Controllers\CatalogController();
        $controller->index();
        break;
    case 'book':
        $controller = new App\Controllers\ReservationController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->create();
        } else {
            $controller->showForm();
        }
        break;
    default:
        // Render the real 404 page rather than bare text, so a mistyped URL
        // still gives the visitor the site layout and a way back. The view
        // exists at views/guest/404.php and is styled like the rest of the site.
        http_response_code(404);
        Response::render('guest/404');
        break;
}
