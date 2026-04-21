<?php
// app/controllers/AuthViewController.php
// ─────────────────────────────────────────────────────────────
//  Renders the auth views (GET requests).
//  POST actions are handled by AuthController.
// ─────────────────────────────────────────────────────────────

class AuthViewController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // If already logged in, redirect away from auth pages
        if (!empty($_SESSION['user_id'])) {
            $role = $_SESSION['user_role'] ?? 'customer';
            $map  = [
                'admin'    => 'admin/dashboard',
                'provider' => 'provider/dashboard',
                'customer' => 'dashboard',
            ];
            header('Location: ' . BASE_URL . ($map[$role] ?? 'home'));
            exit;
        }
    }

    public function showLogin(): void
    {
        // Pass any flash messages to the view
        $error   = $_SESSION['flash_error']   ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require_once __DIR__ . '/../views/login.php';
    }

    public function showRegister(): void
    {
        $error   = $_SESSION['flash_error']   ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require_once __DIR__ . '/../views/register.php';
    }

    public function showForgotPassword(): void
    {
        $error   = $_SESSION['flash_error']   ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);

        require_once __DIR__ . '/../views/forgot-password.php';
    }

    public function showResetForm(): void
    {
        // Validate token exists before rendering the form
        require_once __DIR__ . '/../models/User.php';
        $userModel = new User();

        $token = $_GET['token'] ?? '';
        $email = $token ? $userModel->validatePasswordResetToken($token) : false;

        if (!$email) {
            $_SESSION['flash_error'] = 'This password reset link is invalid or has expired.';
            header('Location: ' . BASE_URL . 'forgot-password');
            exit;
        }

        require_once __DIR__ . '/../views/reset-password.php';
    }
}