<?php
// app/controllers/ProviderController.php

class ProviderController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'login'); exit;
        }
    }

    public function show(string $id): void
    {
        require_once __DIR__ . '/../views/customer/provider-profile.php';
    }
}