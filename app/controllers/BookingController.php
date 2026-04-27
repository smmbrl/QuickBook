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
        
        $serviceId    = (int)($_POST['service_id']    ?? 0);
        $providerId   = (int)($_POST['provider_id']   ?? 0);
        $bookingDate  = trim($_POST['booking_date']   ?? '');
        $bookingTime  = trim($_POST['booking_time']   ?? '');
        $notes        = trim($_POST['notes']          ?? '');
        $locationType = trim($_POST['location_type']  ?? 'In-shop');

        $errors = [];

        if (!$serviceId)   $errors[] = 'Please select a service.';
        if (!$providerId)  $errors[] = 'Provider not found.';
        if (!$bookingDate) $errors[] = 'Please pick a booking date.';
        if (!$bookingTime) $errors[] = 'Please pick a booking time.';

        if ($bookingDate && strtotime($bookingDate) < strtotime('today')) {
            $errors[] = 'Booking date cannot be in the past.';
        }

        if ($errors) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => implode(' ', $errors)];
            header('Location: ' . BASE_URL . 'providers/' . $providerId); exit;
        }

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

        $dayOfWeek = date('l', strtotime($bookingDate)); 
        $avCheck = $db->prepare("
            SELECT * FROM tbl_provider_availability
            WHERE provider_id = ? AND day_of_week = ? AND is_available = 1
        ");
        $avCheck->execute([$providerId, $dayOfWeek]);
        $avRow = $avCheck->fetch();

        if (!$avRow) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => ucfirst($dayOfWeek) . ' is not an available day for this provider. Please choose a different date.'];
            header('Location: ' . BASE_URL . 'providers/' . $providerId); exit;
        }

        if ($bookingTime) {
            $reqTime   = strtotime($bookingDate . ' ' . $bookingTime);
            $startTime = strtotime($bookingDate . ' ' . $avRow['start_time']);
            $endTime   = strtotime($bookingDate . ' ' . $avRow['end_time']);
            if ($reqTime < $startTime || $reqTime > $endTime) {
                $fmt = fn($t) => date('g:i A', strtotime($bookingDate . ' ' . $t));
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please choose a time between ' . $fmt($avRow['start_time']) . ' and ' . $fmt($avRow['end_time']) . ' on ' . $dayOfWeek . '.'];
                header('Location: ' . BASE_URL . 'providers/' . $providerId); exit;
            }
        }

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

        $startTime = $bookingTime ?: $avRow['start_time'];
        $endTime   = date('H:i:s', strtotime($startTime) + ($service['duration_minutes'] * 60));

        $insert = $db->prepare("
            INSERT INTO tbl_bookings
                (customer_id, provider_id, service_id, booking_date, booking_time,
                 start_time, end_time, location_type, notes, status, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $insert->execute([
            $customerId,
            $providerId,
            $serviceId,
            $bookingDate,
            $bookingTime ?: null,
            $startTime,
            $endTime,
            $locationType,
            $notes ?: null,
        ]);

        $bookingId = (int)$db->lastInsertId();

        $balStmt = $db->prepare("
            SELECT COALESCE(SUM(points), 0) FROM tbl_loyalty_points WHERE user_id = ?
        ");
        $balStmt->execute([$customerId]);
        $currentBalance = (int) $balStmt->fetchColumn();

        $pts = $db->prepare("
            INSERT INTO tbl_loyalty_points
                (user_id, booking_id, type, points, balance, description, created_at)
            VALUES (?, ?, 'earn', 10, ?, 'Booking placed', NOW())
        ");
        $pts->execute([$customerId, $bookingId, $currentBalance + 10]);

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