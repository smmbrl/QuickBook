<?php

require_once __DIR__ . '/../helpers/NotificationHelper.php';

class BookingController
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
        // GET:book/{provider_id} — redirect to the provider's public profile so the
        // customer can pick a service and then submit the booking form.
        $db         = Database::getInstance();
        $providerId = (int)$id;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE id = ? AND is_approved = 1 LIMIT 1");
        $stmt->execute([$providerId]);

        if ($stmt->fetchColumn()) {
            header('Location: ' . BASE_URL . 'providers/' . $providerId); exit;
        }

        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider not found or not available.'];
        header('Location: ' . BASE_URL . 'browse'); exit;
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'browse'); exit;
        }

        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

        $serviceId       = (int)($_POST['service_id']      ?? 0);
        $providerId      = (int)($_POST['provider_id']     ?? 0);
        $bookingDate     = trim($_POST['booking_date']     ?? '');
        $bookingTime     = trim($_POST['booking_time']     ?? '');
        $notes           = trim($_POST['notes']            ?? '');
        $locationType    = trim($_POST['location_type']    ?? 'In-shop');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $paymentMethod   = trim($_POST['payment_method']   ?? 'cash');
        $allowedPayments = ['gcash', 'paymaya', 'card', 'cash'];
        if (!in_array($paymentMethod, $allowedPayments)) $paymentMethod = 'cash';

        // Fallback redirect URL — back to the service booking page or browse
        $fallback = $serviceId
            ? BASE_URL . 'services/' . $serviceId
            : BASE_URL . 'browse';

        $errors = [];
        if (!$serviceId)   $errors[] = 'Please select a service.';
        if (!$providerId)  $errors[] = 'Provider not found.';
        if (!$bookingDate) $errors[] = 'Please pick a booking date.';
        if (!$bookingTime) $errors[] = 'Please pick a booking time.';
        if ($locationType === 'On-site' && $customerAddress === '') {
            $errors[] = 'Please enter your address so the provider knows where to come.';
        }
        if ($bookingDate && strtotime($bookingDate) < strtotime('today')) {
            $errors[] = 'Booking date cannot be in the past.';
        }

        if ($errors) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => implode(' ', $errors)];
            header('Location: ' . $fallback); exit;
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
            header('Location: ' . $fallback); exit;
        }

        $dayOfWeek = date('l', strtotime($bookingDate));
        $avCheck   = $db->prepare("
            SELECT * FROM tbl_provider_availability
            WHERE provider_id = ? AND day_of_week = ? AND is_available = 1
        ");
        $avCheck->execute([$providerId, $dayOfWeek]);
        $avRow = $avCheck->fetch();

        if (!$avRow) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => ucfirst($dayOfWeek) . ' is not an available day for this provider.'];
            header('Location: ' . $fallback); exit;
        }

        if ($bookingTime) {
            $reqTime   = strtotime($bookingDate . ' ' . $bookingTime);
            $startTime = strtotime($bookingDate . ' ' . $avRow['start_time']);
            $endTime   = strtotime($bookingDate . ' ' . $avRow['end_time']);
            if ($reqTime < $startTime || $reqTime > $endTime) {
                $fmt = fn($t) => date('g:i A', strtotime($bookingDate . ' ' . $t));
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please choose a time between ' . $fmt($avRow['start_time']) . ' and ' . $fmt($avRow['end_time']) . '.'];
                header('Location: ' . $fallback); exit;
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
            header('Location: ' . $fallback); exit;
        }

        $startTime   = $bookingTime ?: $avRow['start_time'];
        $endTime     = date('H:i:s', strtotime($startTime) + ($service['duration_minutes'] * 60));
        $serviceFee  = 50.00;
        $totalAmount = (float)($_POST['total_amount'] ?? $service['price']);
        $expectedMin = (float)$service['price'];
        $expectedMax = (float)$service['price'] + $serviceFee;
        if ($totalAmount < $expectedMin || $totalAmount > $expectedMax) {
            $totalAmount = ($locationType === 'On-site') ? $expectedMax : $expectedMin;
        }

        $insert = $db->prepare("
            INSERT INTO tbl_bookings
                (customer_id, provider_id, service_id, booking_date, booking_time,
                 start_time, end_time, location_type, customer_address, notes, total_amount, status, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $insert->execute([
            $customerId, $providerId, $serviceId, $bookingDate,
            $bookingTime ?: null, $startTime, $endTime,
            $locationType, $customerAddress ?: null, $notes ?: null, $totalAmount,
        ]);
        $bookingId = (int)$db->lastInsertId();

        // Payment record
        $db->prepare("
            INSERT INTO tbl_payments (booking_id, amount, payment_method, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ")->execute([$bookingId, $totalAmount, $paymentMethod]);

        // Loyalty points
        $balStmt = $db->prepare("SELECT COALESCE(SUM(points), 0) FROM tbl_loyalty_points WHERE user_id = ?");
        $balStmt->execute([$customerId]);
        $currentBalance = (int)$balStmt->fetchColumn();
        $db->prepare("
            INSERT INTO tbl_loyalty_points
                (user_id, booking_id, type, points, balance, description, created_at)
            VALUES (?, ?, 'earn', 10, ?, 'Booking placed', NOW())
        ")->execute([$customerId, $bookingId, $currentBalance + 10]);

        // Resolve provider user_id
        $provUserStmt = $db->prepare("SELECT user_id FROM tbl_provider_profiles WHERE id = ? LIMIT 1");
        $provUserStmt->execute([$providerId]);
        $providerUserId = (int)($provUserStmt->fetchColumn() ?: 0);

        // Names & formatted date/time
        $custStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $custStmt->execute([$customerId]);
        $custUser  = $custStmt->fetch();
        $custName  = $custUser ? trim($custUser['first_name'] . ' ' . $custUser['last_name']) : 'A customer';

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$providerUserId]);
        $provUser  = $provStmt->fetch();
        $provName  = $provUser ? trim($provUser['first_name'] . ' ' . $provUser['last_name']) : 'The provider';

        $fDate = date('l, F j, Y', strtotime($bookingDate));
        $fTime = $bookingTime ? date('g:i A', strtotime($bookingTime)) : 'TBD';
        $body  = "Scheduled for {$fDate} at {$fTime}. Amount: ₱" . number_format($totalAmount, 2) . '.';

        // Notify customer
        NotificationHelper::send($db, [$customerId], 'booking',
            'Booking Submitted',
            "Your booking for \"{$service['name']}\" has been submitted and is awaiting confirmation.",
            $body,
            BASE_URL . 'bookings/' . $bookingId
        );

        // Notify provider
        if ($providerUserId) {
            NotificationHelper::send($db, [$providerUserId], 'booking',
                'New Booking Request',
                "{$custName} booked \"{$service['name']}\" — please confirm or reschedule.",
                $body,
                BASE_URL . 'provider/bookings/' . $bookingId
            );
        }

        // Notify admins
        NotificationHelper::send(
            $db,
            NotificationHelper::adminIds($db),
            'booking',
            '[Admin] New Booking — #' . $bookingId,
            "Customer {$custName} booked \"{$service['name']}\" with provider {$provName}.",
            $body . " Payment: {$paymentMethod}.",
            BASE_URL . 'admin/bookings'
        );

        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  => 'Booking submitted! The provider will confirm your appointment shortly.',
        ];
        header('Location: ' . BASE_URL . 'bookings/' . $bookingId); exit;
    }
}
