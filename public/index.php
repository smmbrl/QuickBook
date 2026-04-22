<?php
// public/index.php  — QuickBook Front Controller
// ─────────────────────────────────────────────────────────────
//  All HTTP requests are funnelled here by .htaccess.
//  Routes map URI segments to Controller::method pairs.
// ─────────────────────────────────────────────────────────────

session_start();

// ── Base URL (auto-detected) ──────────────────────────────────
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('BASE_URL', rtrim($scriptDir, '/') . '/');   // e.g. /quickbook/public/

// ── Autoload helpers ──────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';


// ── Parse URI ─────────────────────────────────────────────────
// ── Parse URI ─────────────────────────────────────────────────
$basePath    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$uri         = $_GET['url'] ?? '';                 // set by .htaccess RewriteRule
$uri         = trim($uri, '/');                    // e.g. "auth/login"
$method      = $_SERVER['REQUEST_METHOD'];         // GET | POST


// ── Route Table ───────────────────────────────────────────────
//  Format:
//    'HTTP_METHOD:uri/pattern' => ['ControllerClass', 'method']
//
//  Wildcards:
//    {any}  → matches one path segment (stored in $params[0] …)

$routes = [

    // ── Public pages ─────────────────────────────────────────
    'GET:'          => ['HomeController',  'index'],
    'GET:home'      => ['HomeController',  'index'],

    // ── Auth views (GET shows the page) ──────────────────────
    'GET:login'                => ['AuthViewController', 'showLogin'],
    'GET:auth/login'           => ['AuthViewController', 'showLogin'],
    'GET:register'             => ['AuthViewController', 'showRegister'],
    'GET:auth/register'        => ['AuthViewController', 'showRegister'],
    'GET:forgot-password'      => ['AuthViewController', 'showForgotPassword'],
    'GET:auth/forgot-password' => ['AuthViewController', 'showForgotPassword'],
    'GET:reset-password'       => ['AuthViewController', 'showResetForm'],

    // ── Auth actions (POST processes the form) ────────────────
    'POST:auth/login'           => ['AuthController', 'login'],
    'POST:auth/register'        => ['AuthController', 'register'],
    'POST:auth/logout'          => ['AuthController', 'logout'],
    'GET:auth/logout'           => ['AuthController', 'logout'],
    'GET:auth/verify'           => ['AuthController', 'verifyEmail'],
    'POST:auth/forgot-password' => ['AuthController', 'forgotPassword'],
    'GET:auth/reset-password'   => ['AuthController', 'showResetForm'],
    'POST:auth/reset-password'  => ['AuthController', 'resetPassword'],

    // ── Customer dashboard ────────────────────────────────────
    'GET:dashboard'                  => ['CustomerController', 'dashboard'],
    'GET:bookings'                   => ['CustomerController', 'bookings'],
    'GET:bookings/{any}'             => ['CustomerController', 'bookingDetail'],
    'POST:bookings/{any}/cancel'     => ['CustomerController', 'cancelBooking'],
    'GET:bookings/{any}/cancel'      => ['CustomerController', 'cancelBooking'],
    'GET:loyalty'                    => ['CustomerController', 'loyalty'],
    'GET:profile'                    => ['CustomerController', 'profile'],
    'POST:profile'                   => ['CustomerController', 'updateProfile'],

    // ── Browse & booking flow ─────────────────────────────────
    'GET:browse'                     => ['BrowseController',    'index'],
    'GET:browse/{any}'               => ['BrowseController',    'category'],
    'GET:providers/{any}'            => ['ProviderController',  'show'],
    'POST:book'                      => ['BookingController',   'store'],

    // ── Provider dashboard ────────────────────────────────────
    'GET:provider/dashboard'                  => ['ProviderDashController', 'index'],
    'GET:provider/bookings'                   => ['ProviderDashController', 'bookings'],
    'POST:provider/bookings/{any}'            => ['ProviderDashController', 'updateBooking'],
    'GET:provider/services'                   => ['ProviderDashController', 'services'],
    'POST:provider/services/store'            => ['ProviderDashController', 'storeService'],
    'POST:provider/service/update/{any}'      => ['ProviderDashController', 'updateService'],
    'POST:provider/service/delete/{any}'      => ['ProviderDashController', 'deleteService'],
    'POST:provider/service/toggle/{any}'      => ['ProviderDashController', 'toggleService'],
    'GET:provider/availability'               => ['ProviderDashController', 'availability'],
    'POST:provider/availability/store'        => ['ProviderDashController', 'storeAvailability'],
    'POST:provider/availability/update/{any}' => ['ProviderDashController', 'updateAvailability'],
    'POST:provider/availability/delete/{any}' => ['ProviderDashController', 'deleteAvailability'],
    'GET:provider/profile'                    => ['ProviderDashController', 'profile'],
    'POST:provider/profile'                   => ['ProviderDashController', 'updateProfile'],

    // ── Admin ─────────────────────────────────────────────────
    'GET:admin/dashboard'       => ['AdminController', 'dashboard'],
    'GET:admin/bookings'               => ['AdminController', 'bookings'],
    'POST:admin/bookings/{any}'        => ['AdminController', 'updateBooking'],
    'POST:admin/bookings/{any}/delete' => ['AdminController', 'deleteBooking'],
    'GET:admin/providers'       => ['AdminController', 'providers'],
    'POST:admin/providers/{any}'=> ['AdminController', 'updateProvider'],
    'GET:admin/users'           => ['AdminController', 'users'],
    'GET:admin/reports'         => ['AdminController', 'reports'],
];

// ── Dispatcher ────────────────────────────────────────────────
$matched = false;
$params  = [];

foreach ($routes as $pattern => $handler) {
    [$routeMethod, $routeUri] = explode(':', $pattern, 2);

    if ($routeMethod !== $method) {
        continue;
    }

    // Build regex — replace {any} with a capture group
    $regex = '#^' . preg_replace('#\{any\}#', '([^/]+)', $routeUri) . '$#';

    if (preg_match($regex, $uri, $matches)) {
        array_shift($matches);      // remove full match
        $params  = $matches;
        $matched = true;

        [$controllerName, $action] = $handler;

        // Lazy-load controller file
        $file = __DIR__ . '/../app/controllers/' . $controllerName . '.php';

        if (!file_exists($file)) {
            // Controller not built yet — show friendly placeholder
            renderPlaceholder($controllerName, $action, $uri);
            exit;
        }

        require_once $file;

        if (!class_exists($controllerName)) {
            renderError(500, "Controller class '{$controllerName}' not found.");
            exit;
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $action)) {
            renderError(500, "Method '{$action}' not found in '{$controllerName}'.");
            exit;
        }

        call_user_func_array([$controller, $action], $params);
        exit;
    }
}

// ── 404 ───────────────────────────────────────────────────────
if (!$matched) {
    renderError(404, $uri);
}

// ─────────────────────────────────────────────────────────────
// Helper: render error / 404 page
// ─────────────────────────────────────────────────────────────
function renderError(int $code, string $uri = ''): void
{
    http_response_code($code);
    $viewFile = __DIR__ . '/../app/views/errors/' . $code . '.php';
    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<!DOCTYPE html><html><head>
              <title>{$code}</title>
              <style>
                body{background:#0d1117;color:#c9a84c;font-family:sans-serif;
                     display:flex;flex-direction:column;align-items:center;
                     justify-content:center;height:100vh;margin:0}
                h1{font-size:6rem;margin:0}p{opacity:.7}
                a{color:#c9a84c;border:1px solid #c9a84c;padding:.5rem 1.5rem;
                  border-radius:999px;text-decoration:none;margin-top:1rem;display:inline-block}
              </style></head><body>
              <h1>{$code}</h1>
              <p>Page not found &mdash; <code>/{$uri}</code></p>
              <a href='" . BASE_URL . "home'>← Back to Home</a>
              </body></html>";
    }
    exit;
}

// ─────────────────────────────────────────────────────────────
// Helper: placeholder for controllers not yet implemented
// ─────────────────────────────────────────────────────────────
function renderPlaceholder(string $ctrl, string $action, string $uri): void
{
    echo "<!DOCTYPE html><html><head>
          <title>Under Construction</title>
          <style>
            body{background:#0d1117;color:#c9a84c;font-family:sans-serif;
                 display:flex;flex-direction:column;align-items:center;
                 justify-content:center;height:100vh;margin:0;text-align:center}
            code{background:#1a2233;padding:.2rem .5rem;border-radius:4px;font-size:.9rem}
            a{color:#c9a84c;border:1px solid #c9a84c;padding:.5rem 1.5rem;
              border-radius:999px;text-decoration:none;margin-top:1.5rem;display:inline-block}
          </style></head><body>
          <h2>🚧 Coming Soon</h2>
          <p><code>{$ctrl}::{$action}()</code> for <code>/{$uri}</code></p>
          <a href='" . BASE_URL . "home'>← Back to Home</a>
          </body></html>";
}