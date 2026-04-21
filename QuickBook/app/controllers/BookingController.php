<?php
// app/controllers/BookingController.php

class BookingController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'login'); exit;
        }
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'browse'); exit;
        }

        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

        // ── Collect & sanitise inputs ─────────────────────────
        $serviceId    = (int)($_POST['service_id']    ?? 0);
        $providerId   = (int)($_POST['provider_id']   ?? 0);
        $bookingDate  = trim($_POST['booking_date']   ?? '');
        $bookingTime  = trim($_POST['booking_time']   ?? '');
        $notes        = trim($_POST['notes']          ?? '');
        $locationType = trim($_POST['location_type']  ?? 'In-shop');

        // ── Basic validation ──────────────────────────────────
        $errors = [];

        if (!$serviceId)   $errors[] = 'Please select a service.';
        if (!$providerId)  $errors[] = 'Provider not found.';
        if (!$bookingDate) $errors[] = 'Please pick a booking date.';
        if (!$bookingTime) $errors[] = 'Please pick a booking time.';

        // Date must not be in the past
        if ($bookingDate && strtotime($bookingDate) < strtotime('today')) {
            $errors[] = 'Booking date cannot be in the past.';
        }

        if ($errors) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => implode(' ', $errors)];
            header('Location: ' . BASE_URL . 'providers/' . $providerId); exit;
        }

        // ── Verify service belongs to provider & is active ────
        $svc = $db->prepare("
            SELECT s.*, pp.id as profile_id
            FROM tbl_services s
            JOIN tbl_provider_profiles pp ON s.provider_id = pp.id
            WHERE s.id = ? AND pp.id = ? AND s.is_active = 1
        ");
        $svc->execute([$serviceId, $providerId]);
        $service = $svc->fetch();

        if (!$service) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or unavailable.'];
            header('Location: ' . BASE_URL . 'providers/' . $providerId); exit;
        }

        // ── Prevent duplicate pending/confirmed booking ───────
        $dup = $db->prepare("
            SELECT id FROM tbl_bookings
            WHERE customer_id = ? AND service_id = ? AND booking_date = ?
              AND status IN ('pending','confirmed')
        ");
        $dup->execute([$customerId, $serviceId, $bookingDate]);
        if ($dup->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'You already have a pending or confirmed booking for this service on that date.'];
            header('Location: ' . BASE_URL . 'providers/' . $providerId); exit;
        }

        // ── Insert booking ────────────────────────────────────
        $insert = $db->prepare("
            INSERT INTO tbl_bookings
                (customer_id, provider_id, service_id, booking_date, booking_time,
                 location_type, notes, status, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $insert->execute([
            $customerId,
            $providerId,
            $serviceId,
            $bookingDate,
            $bookingTime ?: null,
            $locationType,
            $notes ?: null,
        ]);

        $bookingId = (int)$db->lastInsertId();

        // ── Loyalty: award 10 pts per booking ────────────────
        $pts = $db->prepare("
            INSERT INTO tbl_loyalty_points (user_id, points, description, created_at)
            VALUES (?, 10, 'Booking placed', NOW())
        ");
        $pts->execute([$customerId]);

        // ── Notification for customer ─────────────────────────
        $notif = $db->prepare("
            INSERT INTO tbl_notifications (user_id, title, message, type, is_read, created_at)
            VALUES (?, 'Booking Submitted', 'Your booking has been submitted and is awaiting confirmation.', 'booking', 0, NOW())
        ");
        $notif->execute([$customerId]);

        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  => 'Booking submitted! The provider will confirm your appointment shortly.',
        ];
        header('Location: ' . BASE_URL . 'bookings'); exit;
    }
}