<?php

session_start();

//  Base URL (auto-detected)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('BASE_URL', rtrim($scriptDir, '/') . '/');   // e.g. /QuickBook/public/

//  Autoload helpers
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/NotificationHelper.php';
require_once __DIR__ . '/../app/controllers/TwoFactorController.php';


//  Parse URI
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$uri      = $_GET['url'] ?? '';
$uri      = trim($uri, '/');
$method   = $_SERVER['REQUEST_METHOD'];



$routes = [

    //  Public pages
    'GET:'          => ['HomeController',  'index'],
    'GET:home'      => ['HomeController',  'index'],

    //  Auth views (GET shows the page)
    'GET:login'                => ['AuthViewController', 'showLogin'],
    'GET:auth/login'           => ['AuthViewController', 'showLogin'],
    'GET:register'             => ['AuthViewController', 'showRegister'],
    'GET:auth/register'        => ['AuthViewController', 'showRegister'],
    'GET:forgot-password'      => ['AuthViewController', 'showForgotPassword'],
    'GET:auth/forgot-password' => ['AuthViewController', 'showForgotPassword'],
    'GET:reset-password'       => ['AuthViewController', 'showResetForm'],

    //  Auth actions (POST processes the form)
    'POST:auth/login'           => ['AuthController', 'login'],
    'POST:auth/register'        => ['AuthController', 'register'],
    'POST:auth/logout'          => ['AuthController', 'logout'],
    'GET:auth/logout'           => ['AuthController', 'logout'],
    'GET:auth/verify'           => ['AuthController', 'verifyEmail'],
    'GET:auth/forgot-password'  => ['AuthController', 'forgotPassword'],
    'POST:auth/forgot-password' => ['AuthController', 'forgotPassword'],
    'GET:auth/reset-password'   => ['AuthController', 'showResetForm'],
    'POST:auth/reset-password'  => ['AuthController', 'resetPassword'],

    //  Customer dashboard
    'GET:dashboard'                         => ['CustomerController', 'dashboard'],
    'GET:bookings'                          => ['CustomerController', 'bookings'],
    'GET:bookings/{any}'                    => ['CustomerController', 'bookingDetail'],
    'POST:bookings/{any}/cancel'            => ['CustomerController', 'cancelBooking'],
    'GET:bookings/{any}/cancel'             => ['CustomerController', 'cancelBooking'],
    'POST:bookings/{any}/accept-reschedule' => ['CustomerController', 'acceptReschedule'],
    'GET:bookings/{any}/review'             => ['CustomerController', 'review'],
    'POST:bookings/{any}/review'            => ['CustomerController', 'review'],
    'GET:loyalty'                           => ['CustomerController', 'loyalty'],
    'POST:loyalty/redeem'                   => ['CustomerController', 'redeemLoyalty'],
    'GET:profile'                           => ['CustomerController', 'profile'],
    'POST:profile'                          => ['CustomerController', 'updateProfile'],

    // Browse & booking flow
    'GET:browse'         => ['BrowseController',   'index'],
    'GET:browse/{any}'   => ['BrowseController',   'category'],
    'GET:services/{any}' => ['CustomerController', 'serviceDetail'],
    'GET:providers/{any}'=> ['ProviderController', 'show'],
    'POST:book'          => ['BookingController',  'store'],

    // ── Provider dashboard ──────────────────────────────────────────
    'GET:provider/dashboard'                  => ['ProviderDashController', 'index'],

    // Appointments (new dedicated routes — must come BEFORE the old bookings routes)
    'GET:provider/appointments'                    => ['ProviderDashController', 'appointments'],
    'GET:provider/appointments/accept/{any}'       => ['ProviderDashController', 'acceptAppointment'],
    'GET:provider/appointments/decline/{any}'      => ['ProviderDashController', 'declineAppointment'],
    'GET:provider/appointments/complete/{any}'     => ['ProviderDashController', 'completeAppointment'],
    'POST:provider/appointments/reschedule/{any}'  => ['ProviderDashController', 'rescheduleAppointment'],
    'GET:provider/appointments/{any}'              => ['ProviderDashController', 'appointmentDetail'],
    'POST:provider/appointments/{any}'             => ['ProviderDashController', 'updateBooking'],

    // Bookings (legacy alias — kept so old links don't break)
    'GET:provider/bookings'                   => ['ProviderDashController', 'bookings'],
    'GET:provider/bookings/{any}'             => ['ProviderDashController', 'bookingDetail'],
    'POST:provider/bookings/{any}'            => ['ProviderDashController', 'updateBooking'],

    // Services
    'GET:provider/services'                   => ['ProviderDashController', 'services'],
    'POST:provider/services/store'            => ['ProviderDashController', 'storeService'],
    'POST:provider/service/update/{any}'      => ['ProviderDashController', 'updateService'],
    'POST:provider/service/delete/{any}'      => ['ProviderDashController', 'deleteService'],
    'POST:provider/service/toggle/{any}'      => ['ProviderDashController', 'toggleService'],

    // Schedule (Provider working hours, slots, blocked dates)
    'GET:provider/schedule'                   => ['ProviderDashController', 'schedule'],
    'GET:provider/availability'               => ['ProviderDashController', 'availability'],   // Legacy redirect to /schedule
    'POST:provider/availability/store'        => ['ProviderDashController', 'storeAvailability'],
    'POST:provider/schedule/store'            => ['ProviderDashController', 'storeAvailability'],
    'POST:provider/schedule/slots'            => ['ProviderDashController', 'storeSlotSettings'],
    'POST:provider/schedule/block'            => ['ProviderDashController', 'storeBlockedDate'],
    'POST:provider/schedule/block/edit'       => ['ProviderDashController', 'editBlockedDate'],
    'GET:provider/schedule/unblock/{any}'     => ['ProviderDashController', 'removeBlockedDate'],
    'POST:provider/schedule/pause'            => ['ProviderDashController', 'togglePauseBookings'],
    'POST:provider/availability/update/{any}' => ['ProviderDashController', 'updateAvailability'],   // Legacy
    'POST:provider/availability/delete/{any}' => ['ProviderDashController', 'deleteAvailability'],   // Legacy

    // Profile
    'GET:provider/profile'                    => ['ProviderDashController', 'profile'],
    'POST:provider/profile'                   => ['ProviderDashController', 'updateProfile'],
    'POST:provider/profile/update-business'   => ['ProviderDashController', 'updateProfile'],
    'POST:provider/profile/update-personal'   => ['ProviderDashController', 'updatePersonalInfo'],
    'POST:provider/profile/update-password'   => ['ProviderDashController', 'updatePassword'],
    'POST:provider/profile/upload-photo'      => ['ProviderDashController', 'uploadProfilePhoto'],

    // Portfolio
    // Reviews
    'GET:provider/reviews'                          => ['ProviderDashController', 'reviews'],
    'POST:provider/reviews/reply/update/{any}'      => ['ProviderDashController', 'updateReply'],
    'POST:provider/reviews/reply/delete/{any}'      => ['ProviderDashController', 'deleteReply'],
    'POST:provider/reviews/reply/{any}'             => ['ProviderDashController', 'storeReply'],

    'GET:provider/portfolio'                  => ['ProviderDashController', 'portfolio'],
    'POST:provider/portfolio/upload'          => ['ProviderDashController', 'portfolioUpload'],
    'POST:provider/portfolio/update/{any}'    => ['ProviderDashController', 'portfolioUpdate'],
    'POST:provider/portfolio/delete/{any}'    => ['ProviderDashController', 'portfolioDelete'],
    'POST:provider/portfolio/feature/{any}'   => ['ProviderDashController', 'portfolioFeature'],

    // Settings
    'GET:provider/settings'                   => ['ProviderDashController', 'settings'],
    'POST:provider/settings/update-password'  => ['ProviderDashController', 'updatePassword'],
    'POST:provider/settings/deactivate'       => ['ProviderDashController', 'deactivateAccount'],
    'POST:provider/settings/delete'           => ['ProviderDashController', 'deleteAccount'],
    'POST:provider/settings/feedback'         => ['ProviderDashController', 'submitFeedback'],

    // Notifications (all roles)
    'POST:notifications/mark-read'     => ['NotificationController', 'markRead'],
    'POST:notifications/mark-all-read' => ['NotificationController', 'markAllRead'],

    // Admin
    'GET:admin/dashboard'              => ['AdminController', 'dashboard'],
    'GET:admin/bookings'               => ['AdminController', 'bookings'],
    'POST:admin/bookings/{any}'        => ['AdminController', 'updateBooking'],
    'POST:admin/bookings/{any}/delete' => ['AdminController', 'deleteBooking'],
    'GET:admin/providers'              => ['AdminController', 'providers'],
    'POST:admin/providers/{any}'       => ['AdminController', 'updateProvider'],
    'GET:admin/users'                  => ['AdminController', 'users'],
    'GET:admin/reports'                => ['AdminController', 'reports'],
    'GET:admin/logs'                   => ['AdminController', 'logs'],
    'GET:admin/profile'                => ['AdminController', 'profile'],
    'POST:admin/update-profile'        => ['AdminController', 'updateProfile'],

    // 2FA routes
    'GET:auth/2fa/setup'    => ['TwoFactorController', 'setup'],
    'POST:auth/2fa/enable'  => ['TwoFactorController', 'enable'],
    'GET:auth/2fa/verify'   => ['TwoFactorController', 'showVerify'],
    'POST:auth/2fa/verify'  => ['TwoFactorController', 'verify'],
    'POST:auth/2fa/disable' => ['TwoFactorController', 'disable'],
];

// ── Dispatcher ────────────────────────────────────────────────────
$matched = false;
$params  = [];

foreach ($routes as $pattern => $handler) {
    [$routeMethod, $routeUri] = explode(':', $pattern, 2);

    if ($routeMethod !== $method) {
        continue;
    }

    $regex = '#^' . preg_replace('#\{any\}#', '([^/]+)', $routeUri) . '$#';

    if (preg_match($regex, $uri, $matches)) {
        array_shift($matches);
        $params  = $matches;
        $matched = true;

        [$controllerName, $action] = $handler;

        $file = __DIR__ . '/../app/controllers/' . $controllerName . '.php';

        if (!file_exists($file)) {
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

if (!$matched) {
    renderError(404, $uri);
}

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