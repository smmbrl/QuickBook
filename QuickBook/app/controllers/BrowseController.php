<?php
// app/controllers/BrowseController.php
class BrowseController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'login'); exit;
        }
    }

    public function index(): void
    {
        require_once __DIR__ . '/../views/customer/browse.php';
    }

    public function category(string $slug): void
    {
        $_GET['category'] = $slug;
        require_once __DIR__ . '/../views/customer/browse.php';
    }
}