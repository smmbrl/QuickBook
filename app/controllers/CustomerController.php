<?php
// app/controllers/CustomerController.php

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
        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("
            SELECT b.*,
                   s.name            AS service_name,
                   s.price,
                   s.duration_minutes,
                   s.description     AS service_description,
                   pp.business_name,
                   pp.offers_home_service,
                   pp.id             AS profile_id,
                   c.name            AS category_name,
                   c.slug            AS category_slug,
                   (SELECT COUNT(*) FROM tbl_reviews r WHERE r.booking_id = b.id) AS has_review
            FROM tbl_bookings b
            JOIN tbl_services          s  ON b.service_id  = s.id
            JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
            LEFT JOIN tbl_categories   c  ON pp.category_id = c.id
            WHERE b.id = ? AND b.customer_id = ? AND b.deleted_at IS NULL
        ");
        $stmt->execute([(int)$id, $customerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'bookings'); exit;
        }

        require_once __DIR__ . '/../views/customer/booking-detail.php';
    }

    public function cancelBooking(string $id): void
    {
        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

        // Verify booking belongs to this customer, is cancellable, and not deleted
        $stmt = $db->prepare("
            SELECT id, status FROM tbl_bookings
            WHERE id = ? AND customer_id = ?
              AND status IN ('pending','confirmed')
              AND deleted_at IS NULL
        ");
        $stmt->execute([$id, $customerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found or cannot be cancelled.'];
            header('Location: ' . BASE_URL . 'bookings'); exit;
        }

        // Soft-delete the booking after cancelling
        $upd = $db->prepare("
            UPDATE tbl_bookings
            SET status = 'cancelled', deleted_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking cancelled successfully.'];
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