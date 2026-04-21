<?php
class CustomerController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'login'); exit;
        }
    }

    public function dashboard(): void
    {
        require_once __DIR__ . '/../views/customer/dashboard.php';
    }

    public function bookings(): void
    {
        require_once __DIR__ . '/../views/customer/bookings.php';
    }

    public function bookingDetail(string $id): void
    {
        require_once __DIR__ . '/../views/customer/booking-detail.php';
    }

    public function cancelBooking(string $id): void
    {
        header('Location: ' . BASE_URL . 'bookings'); exit;
    }

    public function loyalty(): void
    {
        require_once __DIR__ . '/../views/customer/loyalty.php';
    }

    public function profile(): void
    {
        require_once __DIR__ . '/../views/customer/profile.php';
    }

    public function updateProfile(): void
    {
        header('Location: ' . BASE_URL . 'profile'); exit;
    }
}