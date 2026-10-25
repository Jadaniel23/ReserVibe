<?php
// public/index.php

// Guest pages share the PHP session used by API requests. In particular,
// the reservation confirmation is stored by the reservation API and read here.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    case 'manage':
        Response::render('guest/manage');
        break;
    case 'terms':
        Response::render('guest/terms');
        break;
    case 'calendar':
        Response::render('guest/calendar');
        break;
    case 'confirmation':
        Response::render('guest/confirmation');
        break;
    case 'staff-login':
        if (in_array(App\Services\AuthService::role(), ['staff', 'admin'], true)) {
            header('Location: ?action=dashboard', true, 302);
            exit;
        }
        Response::render('staff/login', ['csrfToken' => App\Services\CsrfProtection::token()]);
        break;
    case 'dashboard':
        $role = App\Services\AuthService::role();
        if (!in_array($role, ['staff', 'admin'], true)) {
            header('Location: ?action=staff-login', true, 302);
            exit;
        }
        Response::render('staff/dashboard', ['role' => $role]);
        break;
    case 'google-connect':
        if (App\Services\AuthService::role() !== 'admin') {
            header('Location: ?action=staff-login', true, 302);
            exit;
        }
        try {
            (new App\Services\GoogleCalendarService())->beginAuthorization();
        } catch (RuntimeException $error) {
            http_response_code(503);
            echo htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
        }
        break;
    case 'google-callback':
        if (App\Services\AuthService::role() !== 'admin') {
            header('Location: ?action=staff-login', true, 302);
            exit;
        }
        try {
            (new App\Services\GoogleCalendarService())->finishAuthorization(
                is_string($_GET['code'] ?? null) ? $_GET['code'] : '',
                is_string($_GET['state'] ?? null) ? $_GET['state'] : ''
            );
            header('Location: ?action=dashboard', true, 302);
            exit;
        } catch (RuntimeException $error) {
            http_response_code(400);
            echo htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
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
