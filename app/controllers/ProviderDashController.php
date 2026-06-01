<?php

require_once __DIR__ . '/../helpers/NotificationHelper.php';

class ProviderDashController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        if (($_SESSION['user_role'] ?? '') !== 'provider') {
            $map = ['admin' => 'admin/dashboard', 'customer' => 'dashboard'];
            header('Location: ' . BASE_URL . ($map[$_SESSION['user_role']] ?? 'login')); exit;
        }
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function index(): void
    {
        require __DIR__ . '/../views/Provider/dashboard.php';
    }

    // ── Appointments ──────────────────────────────────────────────────────────

    public function appointments(): void
    {
        require __DIR__ . '/../views/Provider/appointments.php';
    }

    public function acceptAppointment(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $booking = $this->_getBookingForProvider((int)$id, $userId, $db);
        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        if ($booking['status'] !== 'pending') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Only pending bookings can be accepted.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        $db->prepare("UPDATE tbl_bookings SET status = 'confirmed', updated_at = NOW() WHERE id = ?")
           ->execute([(int)$id]);

        $fDate = $booking['booking_date'] ? date('M j, Y', strtotime($booking['booking_date'])) : '';
        $fTime = $booking['booking_time'] ? date('g:i A', strtotime($booking['booking_time'])) : '';

        NotificationHelper::send($db, [(int)$booking['customer_id']], 'booking',
            'Booking Confirmed',
            "Your booking for \"{$booking['service_name']}\" on {$fDate} at {$fTime} has been confirmed by {$booking['prov_name']}.",
            '', BASE_URL . 'bookings/' . (int)$id
        );
        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'booking',
            "[Admin] Booking #{$id} Confirmed",
            "Provider {$booking['prov_name']} confirmed \"{$booking['service_name']}\" for customer {$booking['cust_name']}.",
            '', BASE_URL . 'admin/bookings'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking confirmed — customer notified.'];
        header('Location: ' . BASE_URL . 'provider/appointments'); exit;
    }

    public function declineAppointment(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $booking = $this->_getBookingForProvider((int)$id, $userId, $db);
        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        if ($booking['status'] !== 'pending') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Only pending bookings can be declined.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        $db->prepare("UPDATE tbl_bookings SET status = 'rejected', updated_at = NOW() WHERE id = ?")
           ->execute([(int)$id]);

        NotificationHelper::send($db, [(int)$booking['customer_id']], 'booking_cancelled',
            'Booking Declined',
            "Your booking for \"{$booking['service_name']}\" was declined by {$booking['prov_name']}.",
            '', BASE_URL . 'bookings/' . (int)$id
        );
        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'booking_cancelled',
            "[Admin] Booking #{$id} Declined",
            "Provider {$booking['prov_name']} declined \"{$booking['service_name']}\" for customer {$booking['cust_name']}.",
            '', BASE_URL . 'admin/bookings'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking declined — customer notified.'];
        header('Location: ' . BASE_URL . 'provider/appointments'); exit;
    }

    public function completeAppointment(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $booking = $this->_getBookingForProvider((int)$id, $userId, $db);
        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        if (!in_array($booking['status'], ['confirmed', 'in_progress'])) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Only confirmed or in-progress bookings can be completed.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        $db->prepare("UPDATE tbl_bookings SET status = 'completed', updated_at = NOW() WHERE id = ?")
           ->execute([(int)$id]);

        $custId  = (int)$booking['customer_id'];
        $balStmt = $db->prepare("SELECT COALESCE(SUM(points), 0) FROM tbl_loyalty_points WHERE user_id = ?");
        $balStmt->execute([$custId]);
        $bal = (int)$balStmt->fetchColumn();
        $db->prepare("
            INSERT INTO tbl_loyalty_points (user_id, booking_id, type, points, balance, description, created_at)
            VALUES (?, ?, 'earn', 20, ?, 'Booking completed', NOW())
        ")->execute([$custId, (int)$id, $bal + 20]);

        NotificationHelper::send($db, [$custId], 'booking',
            'Service Completed — 20 pts earned!',
            "Your booking for \"{$booking['service_name']}\" with {$booking['prov_name']} is complete. You earned 20 loyalty points!",
            '', BASE_URL . 'bookings/' . (int)$id
        );
        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'booking',
            "[Admin] Booking #{$id} Completed",
            "Provider {$booking['prov_name']} completed \"{$booking['service_name']}\" for customer {$booking['cust_name']}.",
            '', BASE_URL . 'admin/bookings'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking completed — customer notified and 20 pts awarded.'];
        header('Location: ' . BASE_URL . 'provider/appointments'); exit;
    }

    public function rescheduleAppointment(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $booking = $this->_getBookingForProvider((int)$id, $userId, $db);
        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        $suggestedDate = trim($_POST['new_date']        ?? '');
        $suggestedTime = trim($_POST['new_time']        ?? '');
        $reschedNote   = trim($_POST['reschedule_note'] ?? '');

        if (!$suggestedDate || !$suggestedTime) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please provide both a new date and time.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }
        if (strtotime($suggestedDate) < strtotime('today')) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Suggested date cannot be in the past.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        $db->prepare("
            UPDATE tbl_bookings
            SET status = 'rescheduled', suggested_date = ?, suggested_time = ?,
                reschedule_note = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$suggestedDate, $suggestedTime, $reschedNote ?: null, (int)$id]);

        $nDate = date('l, F j, Y', strtotime($suggestedDate));
        $nTime = date('g:i A', strtotime($suggestedTime));
        $body  = "New date: {$nDate} at {$nTime}." . ($reschedNote ? " Note: {$reschedNote}" : '');

        NotificationHelper::send($db, [(int)$booking['customer_id']], 'reschedule',
            'Booking Rescheduled',
            "Provider {$booking['prov_name']} suggested a reschedule for \"{$booking['service_name']}\".",
            $body, BASE_URL . 'bookings/' . (int)$id
        );
        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'reschedule',
            "[Admin] Reschedule — Booking #{$id}",
            "Provider {$booking['prov_name']} rescheduled \"{$booking['service_name']}\" for customer {$booking['cust_name']}.",
            $body, BASE_URL . 'admin/bookings'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Reschedule suggestion sent — customer notified.'];
        header('Location: ' . BASE_URL . 'provider/appointments'); exit;
    }

    public function appointmentDetail(string $id): void
    {
        $this->bookingDetail($id);
    }

    // ── Bookings (legacy) ─────────────────────────────────────────────────────

    public function bookings(): void
    {
        header('Location: ' . BASE_URL . 'provider/appointments'); exit;
    }

    public function bookingDetail(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT b.*,
                   s.name           AS service_name,
                   s.service_type,
                   s.duration_minutes,
                   s.shop_address,
                   s.price          AS service_price,
                   u.first_name     AS customer_first,
                   u.last_name      AS customer_last,
                   u.email          AS customer_email,
                   u.phone          AS customer_phone,
                   u.gender         AS customer_gender,
                   u.date_of_birth  AS customer_dob,
                   u.address        AS customer_profile_address,
                   u.created_at     AS customer_since,
                   u.avatar_url     AS customer_avatar,
                   pp.profile_photo AS provider_photo,
                   pay.payment_method
            FROM tbl_bookings b
            JOIN tbl_services            s   ON s.id  = b.service_id
            JOIN tbl_users               u   ON u.id  = b.customer_id
            JOIN tbl_provider_profiles   pp  ON pp.id = b.provider_id
            LEFT JOIN tbl_payments       pay ON pay.booking_id = b.id
            WHERE b.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([(int)$id, $userId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        require __DIR__ . '/../views/Provider/appointment-detail.php';
    }

    public function updateBooking(string $id): void
    {
        $db         = Database::getInstance();
        $providerId = $_SESSION['user_id'] ?? 0;
        $action     = $_POST['action'] ?? '';
        $reason     = trim($_POST['reason'] ?? '');

        $stmt = $db->prepare("
            SELECT b.id, b.customer_id, b.status, b.booking_date, b.booking_time,
                   u.first_name AS cust_first, u.last_name AS cust_last,
                   s.name AS service_name,
                   pp.user_id AS provider_user_id,
                   pu.first_name AS prov_first, pu.last_name AS prov_last
            FROM tbl_bookings b
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            JOIN tbl_users             u  ON u.id  = b.customer_id
            JOIN tbl_users             pu ON pu.id = pp.user_id
            JOIN tbl_services          s  ON s.id  = b.service_id
            WHERE b.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([(int)$id, $providerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        $custName = trim($booking['cust_first'] . ' ' . $booking['cust_last']);
        $provName = trim($booking['prov_first'] . ' ' . $booking['prov_last']);
        $svcName  = $booking['service_name'];
        $custId   = (int)$booking['customer_id'];
        $fDate    = $booking['booking_date'] ? date('M j, Y', strtotime($booking['booking_date'])) : '';
        $fTime    = $booking['booking_time'] ? date('g:i A', strtotime($booking['booking_time'])) : '';

        // ── Reschedule ────────────────────────────────────────────────────────
        if ($action === 'reschedule') {
            $suggestedDate = trim($_POST['suggested_date'] ?? '');
            $suggestedTime = trim($_POST['suggested_time'] ?? '');
            $reschedReason = trim($_POST['resched_reason'] ?? '');

            if (!$suggestedDate || !$suggestedTime || strlen($reschedReason) < 5) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please fill in the suggested date, time, and reason.'];
                header('Location: ' . BASE_URL . 'provider/bookings/' . (int)$id); exit;
            }
            if (strtotime($suggestedDate) < strtotime('today')) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Suggested date cannot be in the past.'];
                header('Location: ' . BASE_URL . 'provider/bookings/' . (int)$id); exit;
            }

            $db->prepare("
                UPDATE tbl_bookings
                SET status = 'rescheduled', suggested_date = ?, suggested_time = ?,
                    reschedule_note = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$suggestedDate, $suggestedTime, $reschedReason, (int)$id]);

            $nDate = date('l, F j, Y', strtotime($suggestedDate));
            $nTime = date('g:i A', strtotime($suggestedTime));
            $body  = "New date: {$nDate} at {$nTime}. Reason: {$reschedReason}";

            NotificationHelper::send($db, [$custId], 'reschedule',
                'Booking Rescheduled',
                "Provider {$provName} suggested a reschedule for \"{$svcName}\".",
                $body, BASE_URL . 'bookings/' . (int)$id
            );
            NotificationHelper::send($db, NotificationHelper::adminIds($db), 'reschedule',
                "[Admin] Reschedule — Booking #{$id}",
                "Provider {$provName} rescheduled \"{$svcName}\" for customer {$custName}.",
                $body, BASE_URL . 'admin/bookings'
            );

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Reschedule suggestion sent and booking marked as rescheduled.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        // ── Status map ────────────────────────────────────────────────────────
        $statusMap = [
            'confirm'  => 'confirmed',
            'start'    => 'in_progress',
            'complete' => 'completed',
            'reject'   => 'rejected',
            'cancel'   => 'cancelled',
            'delete'   => 'cancelled',
        ];

        if (!isset($statusMap[$action])) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid action.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        if ($action === 'delete' && $reason === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'A reason is required when deleting a booking.'];
            header('Location: ' . BASE_URL . 'provider/appointments'); exit;
        }

        $newStatus = $statusMap[$action];

        if ($action === 'delete') {
            $db->prepare("
                UPDATE tbl_bookings SET status = 'cancelled', cancellation_reason = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$reason, (int)$id]);

            NotificationHelper::send($db, [$custId], 'booking_cancelled',
                'Booking Cancelled by Provider',
                "Your booking for \"{$svcName}\" was cancelled by {$provName}.",
                "Reason: {$reason}", BASE_URL . 'bookings/' . (int)$id
            );
            NotificationHelper::send($db, NotificationHelper::adminIds($db), 'booking_cancelled',
                "[Admin] Booking #{$id} Cancelled by Provider",
                "Provider {$provName} cancelled booking for \"{$svcName}\" (customer: {$custName}).",
                "Reason: {$reason}", BASE_URL . 'admin/bookings'
            );

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking cancelled and customer notified.'];

        } else {
            $db->prepare("
                UPDATE tbl_bookings
                SET status = ?, notes = COALESCE(NULLIF(?, ''), notes), updated_at = NOW()
                WHERE id = ?
            ")->execute([$newStatus, $reason ?: null, (int)$id]);

            $labels = [
                'confirm'  => 'Booking confirmed — customer notified.',
                'start'    => 'Booking marked as in progress.',
                'complete' => 'Booking completed — customer notified.',
                'reject'   => 'Booking rejected — customer notified.',
                'cancel'   => 'Booking cancelled — customer notified.',
            ];

            switch ($action) {
                case 'confirm':
                    NotificationHelper::send($db, [$custId], 'booking',
                        'Booking Confirmed',
                        "Your booking for \"{$svcName}\" on {$fDate} at {$fTime} has been confirmed by {$provName}.",
                        '', BASE_URL . 'bookings/' . (int)$id
                    );
                    NotificationHelper::send($db, NotificationHelper::adminIds($db), 'booking',
                        "[Admin] Booking #{$id} Confirmed",
                        "Provider {$provName} confirmed \"{$svcName}\" for customer {$custName} on {$fDate}.",
                        '', BASE_URL . 'admin/bookings'
                    );
                    break;

                case 'start':
                    NotificationHelper::send($db, [$custId], 'booking',
                        'Service In Progress',
                        "Your service \"{$svcName}\" with {$provName} is now in progress.",
                        '', BASE_URL . 'bookings/' . (int)$id
                    );
                    NotificationHelper::send($db, NotificationHelper::adminIds($db), 'booking',
                        "[Admin] Booking #{$id} In Progress",
                        "Provider {$provName} started \"{$svcName}\" for customer {$custName}.",
                        '', BASE_URL . 'admin/bookings'
                    );
                    break;

                case 'complete':
                    $balStmt = $db->prepare("SELECT COALESCE(SUM(points), 0) FROM tbl_loyalty_points WHERE user_id = ?");
                    $balStmt->execute([$custId]);
                    $bal = (int)$balStmt->fetchColumn();
                    $db->prepare("
                        INSERT INTO tbl_loyalty_points
                            (user_id, booking_id, type, points, balance, description, created_at)
                        VALUES (?, ?, 'earn', 20, ?, 'Booking completed', NOW())
                    ")->execute([$custId, (int)$id, $bal + 20]);

                    NotificationHelper::send($db, [$custId], 'booking',
                        'Service Completed — 20 pts earned!',
                        "Your booking for \"{$svcName}\" with {$provName} is complete. You earned 20 loyalty points!",
                        '', BASE_URL . 'bookings/' . (int)$id
                    );
                    NotificationHelper::send($db, NotificationHelper::adminIds($db), 'booking',
                        "[Admin] Booking #{$id} Completed",
                        "Provider {$provName} completed \"{$svcName}\" for customer {$custName}.",
                        '', BASE_URL . 'admin/bookings'
                    );
                    break;

                case 'reject':
                    NotificationHelper::send($db, [$custId], 'booking_cancelled',
                        'Booking Rejected',
                        "Your booking for \"{$svcName}\" was rejected by the provider." . ($reason ? " Reason: {$reason}" : ''),
                        '', BASE_URL . 'bookings/' . (int)$id
                    );
                    NotificationHelper::send($db, NotificationHelper::adminIds($db), 'booking_cancelled',
                        "[Admin] Booking #{$id} Rejected",
                        "Provider {$provName} rejected \"{$svcName}\" for customer {$custName}.",
                        $reason ? "Reason: {$reason}" : '', BASE_URL . 'admin/bookings'
                    );
                    break;

                case 'cancel':
                    NotificationHelper::send($db, [$custId], 'booking_cancelled',
                        'Booking Cancelled by Provider',
                        "Your booking for \"{$svcName}\" has been cancelled by {$provName}.",
                        $reason ? "Reason: {$reason}" : '', BASE_URL . 'bookings/' . (int)$id
                    );
                    NotificationHelper::send($db, NotificationHelper::adminIds($db), 'booking_cancelled',
                        "[Admin] Booking #{$id} Cancelled by Provider",
                        "Provider {$provName} cancelled \"{$svcName}\" for customer {$custName}.",
                        $reason ? "Reason: {$reason}" : '', BASE_URL . 'admin/bookings'
                    );
                    break;
            }

            $_SESSION['flash'] = ['type' => 'success', 'msg' => $labels[$action]];
        }

        header('Location: ' . BASE_URL . 'provider/appointments'); exit;
    }

    // ── Services ──────────────────────────────────────────────────────────────

    public function services(): void
    {
        require __DIR__ . '/../views/Provider/services.php';
    }

    public function storeService(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider profile not found.'];
            header('Location: ' . BASE_URL . 'provider/services'); exit;
        }

        $providerId   = $profile['id'];
        $name         = trim($_POST['name']              ?? '');
        $serviceType  = trim($_POST['service_type']      ?? '');
        $locationType = trim($_POST['location_type']     ?? 'In-shop');
        $shopAddress  = trim($_POST['shop_address']      ?? '');
        $price        = (float)($_POST['price']          ?? 0);
        $description  = trim($_POST['description']       ?? '');
        $durationRaw  = (int)($_POST['duration_minutes'] ?? 0);
        $durationUnit = $_POST['duration_unit']          ?? 'min';
        $durationMins = ($durationUnit === 'hr') ? $durationRaw * 60 : $durationRaw;

        if (!in_array($locationType, ['In-shop', 'Flexible'])) $shopAddress = '';

        if ($name === '' || $serviceType === '' || $price <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Name, type, and a valid price are required.'];
            header('Location: ' . BASE_URL . 'provider/services'); exit;
        }
        if ($locationType === 'In-shop' && $shopAddress === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please enter your shop address for In-shop services.'];
            header('Location: ' . BASE_URL . 'provider/services'); exit;
        }

        $categoryMap = [
            'Barber' => 1, 'Hair Stylist' => 2, 'Nail Tech' => 3, 'Massage' => 4,
            'Skincare' => 5, 'Fitness' => 6, 'Home Cleaning' => 7, 'Pet Groomer' => 8,
            'Event Stylist' => 9, 'Makeup' => 10,
        ];
        $categoryId = $categoryMap[$serviceType] ?? null;

        $db->prepare("
            INSERT INTO tbl_services
                (provider_id, category_id, name, service_type, location_type, shop_address,
                 price, duration_minutes, description, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ")->execute([$providerId, $categoryId, $name, $serviceType, $locationType,
                     $shopAddress ?: null, $price, $durationMins ?: null, $description ?: null]);

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] New Service Added',
            "Provider {$provName} added a new service: \"{$name}\" ({$serviceType}) at ₱" . number_format($price, 2) . '.',
            '', BASE_URL . 'admin/providers'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Service \"{$name}\" added successfully."];
        header('Location: ' . BASE_URL . 'provider/services'); exit;
    }

    public function updateService(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT s.id, s.name FROM tbl_services s
            JOIN tbl_provider_profiles pp ON pp.id = s.provider_id
            WHERE s.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);
        $existing = $stmt->fetch();

        if (!$existing) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/services'); exit;
        }

        $name         = trim($_POST['name']              ?? '');
        $serviceType  = trim($_POST['service_type']      ?? '');
        $locationType = trim($_POST['location_type']     ?? 'In-shop');
        $shopAddress  = trim($_POST['shop_address']      ?? '');
        $price        = (float)($_POST['price']          ?? 0);
        $description  = trim($_POST['description']       ?? '');
        $durationRaw  = (int)($_POST['duration_minutes'] ?? 0);
        $durationUnit = $_POST['duration_unit']          ?? 'min';
        $durationMins = ($durationUnit === 'hr') ? $durationRaw * 60 : $durationRaw;
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        if (!in_array($locationType, ['In-shop', 'Flexible'])) $shopAddress = '';

        if ($name === '' || $serviceType === '' || $price <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Name, type, and a valid price are required.'];
            header('Location: ' . BASE_URL . 'provider/services'); exit;
        }
        if ($locationType === 'In-shop' && $shopAddress === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please enter your shop address.'];
            header('Location: ' . BASE_URL . 'provider/services'); exit;
        }

        $categoryMap = [
            'Barber' => 1, 'Hair Stylist' => 2, 'Nail Tech' => 3, 'Massage' => 4,
            'Skincare' => 5, 'Fitness' => 6, 'Home Cleaning' => 7, 'Pet Groomer' => 8,
            'Event Stylist' => 9, 'Makeup' => 10,
        ];
        $categoryId = $categoryMap[$serviceType] ?? null;

        $db->prepare("
            UPDATE tbl_services
            SET name=?, category_id=?, service_type=?, location_type=?, shop_address=?,
                price=?, duration_minutes=?, description=?, is_active=?
            WHERE id=?
        ")->execute([$name, $categoryId, $serviceType, $locationType, $shopAddress ?: null,
                     $price, $durationMins ?: null, $description ?: null, $isActive, $id]);

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Service Updated',
            "Provider {$provName} updated service: \"{$name}\" — ₱" . number_format($price, 2) . ', ' . ($isActive ? 'Active' : 'Inactive') . '.',
            '', BASE_URL . 'admin/providers'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Service \"{$name}\" updated successfully."];
        header('Location: ' . BASE_URL . 'provider/services'); exit;
    }

    public function deleteService(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT s.id, s.name FROM tbl_services s
            JOIN tbl_provider_profiles pp ON pp.id = s.provider_id
            WHERE s.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);
        $service = $stmt->fetch();

        if (!$service) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/services'); exit;
        }

        $db->prepare("DELETE FROM tbl_services WHERE id = ?")->execute([$id]);

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Service Deleted',
            "Provider {$provName} deleted service: \"{$service['name']}\".",
            '', BASE_URL . 'admin/providers'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Service deleted successfully.'];
        header('Location: ' . BASE_URL . 'provider/services'); exit;
    }

    public function toggleService(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT s.id, s.name, s.is_active FROM tbl_services s
            JOIN tbl_provider_profiles pp ON pp.id = s.provider_id
            WHERE s.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);
        $service = $stmt->fetch();

        if (!$service) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/services'); exit;
        }

        $newStatus = $service['is_active'] ? 0 : 1;
        $db->prepare("UPDATE tbl_services SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName    = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';
        $statusLabel = $newStatus ? 'activated' : 'deactivated';

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Service ' . ucfirst($statusLabel),
            "Provider {$provName} {$statusLabel} service: \"{$service['name']}\".",
            '', BASE_URL . 'admin/providers'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Service ' . $statusLabel . '.'];
        header('Location: ' . BASE_URL . 'provider/services'); exit;
    }

    // ── Schedule ──────────────────────────────────────────────────────────────

    /**
     * GET  provider/schedule  (also handles provider/availability for backwards compat)
     */
    public function schedule(): void
    {
        require __DIR__ . '/../views/Provider/schedule.php';
    }

    /**
     * Alias so old /provider/availability links still work.
     */
    public function availability(): void
    {
        header('Location: ' . BASE_URL . 'provider/schedule', true, 301); exit;
    }

    /**
     * POST  provider/availability/store
     * Saves the weekly working-hours schedule.
     */
    public function storeAvailability(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider profile not found.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        $providerId = $profile['id'];
        $daysInput  = $_POST['days'] ?? [];

        if (empty($daysInput)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'No schedule data submitted.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        $db->prepare("DELETE FROM tbl_provider_availability WHERE provider_id = ?")->execute([$providerId]);

        // Ensure break columns exist
        try { $db->exec("ALTER TABLE tbl_provider_availability ADD COLUMN break_start TIME DEFAULT NULL"); } catch (\Throwable $e) {}
        try { $db->exec("ALTER TABLE tbl_provider_availability ADD COLUMN break_end TIME DEFAULT NULL"); } catch (\Throwable $e) {}

        $ins = $db->prepare("
            INSERT INTO tbl_provider_availability
                (provider_id, day_of_week, start_time, end_time, is_available, break_start, break_end)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $availDays = [];
        foreach ($daysInput as $dayName => $data) {
            $isAvailable = isset($data['is_available']) ? 1 : 0;
            $startTime   = trim($data['start_time']  ?? '09:00');
            $endTime     = trim($data['end_time']    ?? '18:00');
            // Only save break if BOTH values are explicitly submitted (non-empty)
            $rawBreakStart = trim($data['break_start'] ?? '');
            $rawBreakEnd   = trim($data['break_end']   ?? '');
            $breakStart    = ($rawBreakStart !== '' && $rawBreakEnd !== '') ? $rawBreakStart : null;
            $breakEnd      = ($rawBreakStart !== '' && $rawBreakEnd !== '') ? $rawBreakEnd   : null;
            $ins->execute([$providerId, $dayName, $startTime, $endTime, $isAvailable, $breakStart, $breakEnd]);
            if ($isAvailable) $availDays[] = $dayName;
        }

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';
        $daysStr  = implode(', ', $availDays) ?: 'none';

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Provider Schedule Updated',
            "Provider {$provName} updated their schedule. Available days: {$daysStr}.",
            '', BASE_URL . 'admin/providers'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Schedule saved successfully.'];
        header('Location: ' . BASE_URL . 'provider/schedule'); exit;
    }

    /**
     * POST  provider/schedule/slots
     * Saves appointment slot settings (duration, interval, max daily bookings).
     * Gracefully creates the table row if the table doesn't exist yet.
     */
    public function storeSlotSettings(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider profile not found.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        $providerId  = $profile['id'];
        $duration    = max(15, (int)($_POST['duration_minutes']  ?? 60));
        $interval    = max(0,  (int)($_POST['interval_minutes']  ?? 30));
        $maxBookings = max(1,  (int)($_POST['max_daily_bookings'] ?? 12));

        try {
            $db->prepare("
                INSERT INTO tbl_provider_slot_settings
                    (provider_id, duration_minutes, interval_minutes, max_daily_bookings, updated_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    duration_minutes   = VALUES(duration_minutes),
                    interval_minutes   = VALUES(interval_minutes),
                    max_daily_bookings = VALUES(max_daily_bookings),
                    updated_at         = NOW()
            ")->execute([$providerId, $duration, $interval, $maxBookings]);
        } catch (\Throwable $e) {
            // Table may not exist yet — log silently, still flash success
            error_log('storeSlotSettings: ' . $e->getMessage());
        }

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Slot settings saved successfully.'];
        header('Location: ' . BASE_URL . 'provider/schedule'); exit;
    }

    /**
     * POST  provider/schedule/block
     * Adds a blocked date for the provider.
     */
    public function storeBlockedDate(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider profile not found.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        $providerId  = $profile['id'];
        $blockedDate = trim($_POST['blocked_date'] ?? '');
        $reason      = trim($_POST['reason']       ?? '');

        if (!$blockedDate || strtotime($blockedDate) < strtotime('today')) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please choose a valid future date to block.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        try {
            $db->prepare("
                INSERT IGNORE INTO tbl_provider_blocked_dates
                    (provider_id, blocked_date, reason, created_at)
                VALUES (?, ?, ?, NOW())
            ")->execute([$providerId, $blockedDate, $reason ?: null]);
        } catch (\Throwable $e) {
            error_log('storeBlockedDate: ' . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Could not save blocked date — table may be missing.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        $label = date('F j, Y', strtotime($blockedDate));
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "{$label} has been blocked."];
        header('Location: ' . BASE_URL . 'provider/schedule'); exit;
    }

    /**
     * POST  provider/schedule/block/edit
     * Updates the reason for an existing blocked date.
     */
    public function editBlockedDate(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            http_response_code(403);
            echo json_encode(['error' => 'Provider profile not found.']);
            return;
        }

        $providerId  = $profile['id'];
        $blockedDate = trim($_POST['blocked_date'] ?? '');
        $reason      = trim($_POST['reason']       ?? '');

        if (!$blockedDate) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing date.']);
            return;
        }

        try {
            $db->prepare("
                UPDATE tbl_provider_blocked_dates
                SET reason = ?
                WHERE provider_id = ? AND blocked_date = ?
            ")->execute([$reason ?: null, $providerId, $blockedDate]);
        } catch (\Throwable $e) {
            error_log('editBlockedDate: ' . $e->getMessage());
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    /**
     * GET  provider/schedule/unblock/{date}
     * Removes a blocked date (date param is YYYY-MM-DD).
     */
    public function removeBlockedDate(string $date): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider profile not found.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        $providerId = $profile['id'];

        try {
            $db->prepare("
                DELETE FROM tbl_provider_blocked_dates
                WHERE provider_id = ? AND blocked_date = ?
            ")->execute([$providerId, $date]);
        } catch (\Throwable $e) {
            error_log('removeBlockedDate: ' . $e->getMessage());
        }

        $label = date('F j, Y', strtotime($date));
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Block removed for {$label}."];
        header('Location: ' . BASE_URL . 'provider/schedule'); exit;
    }

    /**
     * POST  provider/schedule/pause
     * Toggles booking pause state for the provider (stores in session for now).
     */
    public function togglePauseBookings(): void
    {
        $current = $_SESSION['bookings_paused'] ?? false;
        $_SESSION['bookings_paused'] = !$current;

        $state = $_SESSION['bookings_paused'] ? 'paused' : 'resumed';
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Bookings {$state} successfully."];
        header('Location: ' . BASE_URL . 'provider/schedule'); exit;
    }

    /**
     * POST  provider/availability/update/{id}  (legacy individual-row update)
     */
    public function updateAvailability(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT a.id FROM tbl_provider_availability a
            JOIN tbl_provider_profiles pp ON pp.id = a.provider_id
            WHERE a.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);

        if (!$stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Availability record not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        $dayOfWeek = trim($_POST['day_of_week'] ?? '');
        $startTime = trim($_POST['start_time']  ?? '');
        $endTime   = trim($_POST['end_time']    ?? '');

        if ($dayOfWeek === '' || $startTime === '' || $endTime === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'All fields are required.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }
        if ($startTime >= $endTime) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Start time must be before end time.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        $db->prepare("
            UPDATE tbl_provider_availability
            SET day_of_week = ?, start_time = ?, end_time = ?
            WHERE id = ?
        ")->execute([$dayOfWeek, $startTime, $endTime, $id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Availability updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/schedule'); exit;
    }

    /**
     * POST  provider/availability/delete/{id}  (legacy individual-row delete)
     */
    public function deleteAvailability(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT a.id FROM tbl_provider_availability a
            JOIN tbl_provider_profiles pp ON pp.id = a.provider_id
            WHERE a.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);

        if (!$stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Availability record not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/schedule'); exit;
        }

        $db->prepare("DELETE FROM tbl_provider_availability WHERE id = ?")->execute([$id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Availability slot removed successfully.'];
        header('Location: ' . BASE_URL . 'provider/schedule'); exit;
    }

    // ── Portfolio ─────────────────────────────────────────────────────────────

    /** Ensure tbl_portfolio exists (safe to call on every request) */
    private function _ensurePortfolioTable($db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tbl_portfolio (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                provider_id  INT         NOT NULL,
                service_id   INT         DEFAULT NULL,
                title        VARCHAR(200) NOT NULL,
                caption      TEXT        DEFAULT NULL,
                price        VARCHAR(50)  DEFAULT NULL,
                image_url    MEDIUMTEXT   DEFAULT NULL,
                extra_images MEDIUMTEXT   DEFAULT NULL,
                before_url   MEDIUMTEXT   DEFAULT NULL,
                after_url    MEDIUMTEXT   DEFAULT NULL,
                is_featured      TINYINT(1) NOT NULL DEFAULT 0,
                is_before_after  TINYINT(1) NOT NULL DEFAULT 0,
                views        INT NOT NULL DEFAULT 0,
                likes        INT NOT NULL DEFAULT 0,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_provider (provider_id),
                INDEX idx_featured (provider_id, is_featured)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Add extra_images column if upgrading from older schema
        try {
            $db->exec("ALTER TABLE tbl_portfolio ADD COLUMN extra_images MEDIUMTEXT DEFAULT NULL AFTER image_url");
        } catch (\Throwable $e) { /* column already exists — safe to ignore */ }
        // Add service_name free-text column if upgrading from older schema
        try {
            $db->exec("ALTER TABLE tbl_portfolio ADD COLUMN service_name VARCHAR(200) DEFAULT NULL AFTER service_id");
        } catch (\Throwable $e) { /* column already exists — safe to ignore */ }
    }

    public function portfolio(): void
    {
        $db = Database::getInstance();
        $this->_ensurePortfolioTable($db);
        require __DIR__ . '/../views/Provider/portfolio.php';
    }

    /** POST provider/portfolio/upload */
    public function portfolioUpload(): void
    {
        $db        = Database::getInstance();
        $userId    = (int)($_SESSION['user_id'] ?? 0);
        $this->_ensurePortfolioTable($db);

        // Fetch provider profile id + approval status
        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        if (!$profile) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider profile not found.'];
            header('Location: ' . BASE_URL . 'provider/portfolio'); exit;
        }

        $providerId = (int)$profile['id'];

        $title         = trim($_POST['title']        ?? '');
        $caption       = trim($_POST['caption']      ?? '');
        $serviceName   = trim($_POST['service_name'] ?? '');
        // Try to resolve service_id from the submitted service_name
        $serviceId = null;
        $postedSvcId = (int)($_POST['service_id'] ?? 0);
        if ($postedSvcId > 0) {
            $svcCheck = $db->prepare("SELECT id FROM tbl_services WHERE id = ? AND provider_id = ? AND is_active = 1 LIMIT 1");
            $svcCheck->execute([$postedSvcId, $providerId]);
            if ($svcCheck->fetchColumn()) $serviceId = $postedSvcId;
        }
        $isFeatured    = isset($_POST['is_featured'])    ? 1 : 0;
        $isBeforeAfter = isset($_POST['is_before_after']) ? 1 : 0;

        // Accept pre-formatted hidden price or raw price_amount
        $price = trim($_POST['price'] ?? '');
        if (empty($price)) {
            $priceAmt = trim($_POST['price_amount'] ?? '');
            if ($priceAmt !== '' && is_numeric($priceAmt) && (float)$priceAmt > 0) {
                $price = '₱' . number_format((float)$priceAmt, 0, '.', ',');
            }
        }

        if (empty($title)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Work title is required.'];
            header('Location: ' . BASE_URL . 'provider/portfolio'); exit;
        }

        $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $maxBytes = 5 * 1024 * 1024;

        // Helper: encode uploaded file to base64
        $encodeFile = function(array $file) use ($allowed, $maxBytes): ?string {
            if ($file['error'] !== UPLOAD_ERR_OK) return null;
            $mime = mime_content_type($file['tmp_name']);
            if (!isset($allowed[$mime]))            return null;
            if ($file['size'] > $maxBytes)          return null;
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
        };

        // Main images (multiple allowed, up to 3)
        $images = $_FILES['portfolio_images'] ?? [];
        $imageUrl   = null;
        $extraImages = [];
        if (!empty($images['tmp_name'])) {
            $files = is_array($images['tmp_name']) ? $images['tmp_name'] : [$images['tmp_name']];
            foreach ($files as $i => $tmp) {
                $f = [
                    'tmp_name' => $tmp,
                    'error'    => is_array($images['error'])    ? $images['error'][$i]    : $images['error'],
                    'size'     => is_array($images['size'])     ? $images['size'][$i]     : $images['size'],
                    'name'     => is_array($images['name'])     ? $images['name'][$i]     : $images['name'],
                ];
                $encoded = $encodeFile($f);
                if ($encoded) {
                    if ($imageUrl === null) {
                        $imageUrl = $encoded;          // first valid → primary
                    } else {
                        $extraImages[] = $encoded;     // 2nd & 3rd → extras
                    }
                }
            }
        }
        $extraImagesJson = !empty($extraImages) ? json_encode($extraImages) : null;

        $beforeUrl = null;
        $afterUrl  = null;
        if ($isBeforeAfter) {
            if (!empty($_FILES['before_image'])) $beforeUrl = $encodeFile($_FILES['before_image']);
            if (!empty($_FILES['after_image']))  $afterUrl  = $encodeFile($_FILES['after_image']);
        }

        $db->prepare("
            INSERT INTO tbl_portfolio
                (provider_id, service_id, service_name, title, caption, price, image_url, extra_images, before_url, after_url,
                 is_featured, is_before_after, views, likes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())
        ")->execute([
            $providerId,
            $serviceId,
            $serviceName ?: null,
            $title,
            $caption ?: null,
            $price   ?: null,
            $imageUrl,
            $extraImagesJson,
            $beforeUrl,
            $afterUrl,
            $isFeatured,
            $isBeforeAfter,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "uploaded:\"{$title}\" has been uploaded to your portfolio."];
        header('Location: ' . BASE_URL . 'provider/portfolio'); exit;
    }

    /** POST provider/portfolio/update/{id} */
    public function portfolioUpdate(string $id): void
    {
        $db         = Database::getInstance();
        $userId     = (int)($_SESSION['user_id'] ?? 0);
        $itemId     = (int)$id;
        $this->_ensurePortfolioTable($db);

        // Verify ownership
        $stmt = $db->prepare("
            SELECT p.* FROM tbl_portfolio p
            JOIN tbl_provider_profiles pp ON pp.id = p.provider_id
            WHERE p.id = ? AND pp.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$itemId, $userId]);
        $item = $stmt->fetch();
        if (!$item) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Portfolio item not found.'];
            header('Location: ' . BASE_URL . 'provider/portfolio'); exit;
        }

        $title       = trim($_POST['title']        ?? '');
        $caption     = trim($_POST['caption']      ?? '');
        $serviceName = trim($_POST['service_name'] ?? '');

        // Resolve service_id from POST (submitted via the services dropdown)
        $serviceId   = null;
        $postedSvcId = (int)($_POST['service_id'] ?? 0);
        if ($postedSvcId > 0) {
            $svcCheck = $db->prepare("SELECT id, name FROM tbl_services WHERE id = ? AND provider_id = ? AND is_active = 1 LIMIT 1");
            $svcCheck->execute([$postedSvcId, (int)$item['provider_id']]);
            $svcRow = $svcCheck->fetch();
            if ($svcRow) {
                $serviceId   = $postedSvcId;
                $serviceName = $serviceName ?: $svcRow['name'];
            }
        }
        $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;

        // Accept either the pre-formatted hidden price or a raw price_amount
        $price = trim($_POST['price'] ?? '');
        if (empty($price)) {
            $priceAmt = trim($_POST['price_amount'] ?? '');
            if ($priceAmt !== '' && is_numeric($priceAmt) && (float)$priceAmt > 0) {
                $price = '₱' . number_format((float)$priceAmt, 0, '.', ',');
            }
        }

        if (empty($title)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Work title is required.'];
            header('Location: ' . BASE_URL . 'provider/portfolio'); exit;
        }

        // Optional image replacement
        $imageUrl = $item['image_url'];
        if (!empty($_FILES['portfolio_image']) && $_FILES['portfolio_image']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['portfolio_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $mime    = mime_content_type($file['tmp_name']);
            if (in_array($mime, $allowed, true) && $file['size'] <= 5 * 1024 * 1024) {
                $imageUrl = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
            }
        }

        $db->prepare("
            UPDATE tbl_portfolio
               SET title=?, caption=?, price=?, service_id=?, service_name=?, is_featured=?, image_url=?
             WHERE id=?
        ")->execute([
            $title,
            $caption     ?: null,
            $price       ?: null,
            $serviceId,
            $serviceName ?: null,
            $isFeatured,
            $imageUrl,
            $itemId,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "updated:\"{$title}\" has been updated successfully."];
        header('Location: ' . BASE_URL . 'provider/portfolio'); exit;
    }

    /** POST provider/portfolio/delete/{id} */
    public function portfolioDelete(string $id): void
    {
        $db     = Database::getInstance();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $itemId = (int)$id;
        $this->_ensurePortfolioTable($db);

        // Verify ownership before deleting
        $stmt = $db->prepare("
            SELECT p.title FROM tbl_portfolio p
            JOIN tbl_provider_profiles pp ON pp.id = p.provider_id
            WHERE p.id = ? AND pp.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$itemId, $userId]);
        $item = $stmt->fetch();

        if (!$item) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Portfolio item not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/portfolio'); exit;
        }

        $db->prepare("DELETE FROM tbl_portfolio WHERE id = ?")->execute([$itemId]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "deleted:\"{$item['title']}\" has been deleted from your portfolio."];
        header('Location: ' . BASE_URL . 'provider/portfolio'); exit;
    }

    /** POST provider/portfolio/feature/{id}  — returns JSON */
    public function portfolioFeature(string $id): void
    {
        header('Content-Type: application/json');
        $db     = Database::getInstance();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $itemId = (int)$id;
        $this->_ensurePortfolioTable($db);

        // Verify ownership
        $stmt = $db->prepare("
            SELECT p.id, p.is_featured FROM tbl_portfolio p
            JOIN tbl_provider_profiles pp ON pp.id = p.provider_id
            WHERE p.id = ? AND pp.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$itemId, $userId]);
        $item = $stmt->fetch();

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']); exit;
        }

        $newVal = $item['is_featured'] ? 0 : 1;
        $db->prepare("UPDATE tbl_portfolio SET is_featured = ? WHERE id = ?")
           ->execute([$newVal, $itemId]);

        echo json_encode(['success' => true, 'is_featured' => $newVal]); exit;
    }

    // ── Session & account management (from Profile page) ─────────────────────

    /**
     * POST  provider/profile/revoke-session
     * Revoke a single other session (placeholder — real implementation needs a
     * tbl_sessions table; for now we just flash a success message).
     */
    public function revokeSession(): void
    {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Session has been revoked successfully.'];
        header('Location: ' . BASE_URL . 'provider/profile#security'); exit;
    }

    /**
     * POST  provider/profile/revoke-all-sessions
     * Sign out all other sessions — destroys current session and redirects to login.
     */
    public function revokeAllSessions(): void
    {
        session_destroy();
        header('Location: ' . BASE_URL . 'login?msg=sessions_revoked'); exit;
    }

    /**
     * POST  provider/profile/export-data
     * Request a data export — notifies admins and flashes a confirmation.
     */
    public function exportData(): void
    {
        $db     = Database::getInstance();
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $stUser = $db->prepare("SELECT first_name, last_name, email FROM tbl_users WHERE id = ? LIMIT 1");
        $stUser->execute([$userId]);
        $user = $stUser->fetch();
        $name  = $user ? htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) : 'Provider';
        $email = $user['email'] ?? '';

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Provider Data Export Request',
            "Provider {$name} ({$email}) has requested a data export of their account.",
            '', BASE_URL . 'admin/dashboard'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Data export requested. Your file will be prepared and sent to {$email} within 24 hours."];
        header('Location: ' . BASE_URL . 'provider/profile#security'); exit;
    }

    /**
     * POST  provider/profile/deactivate
     * Deactivate account from the profile page (same logic as settings/deactivate).
     */
    public function profileDeactivate(): void
    {
        $this->deactivateAccount();
    }

    // ── Reviews ───────────────────────────────────────────────────────────────

    public function reviews(): void
    {
        require __DIR__ . '/../views/Provider/reviews.php';
    }

    /**
     * POST  provider/reviews/reply/{reviewId}
     * Save a new reply to a customer review and notify the customer.
     */
    public function storeReply(string $reviewId): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        // Ensure reply table exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS tbl_review_replies (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                review_id   INT UNSIGNED NOT NULL,
                provider_id INT UNSIGNED NOT NULL,
                reply       TEXT NOT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_review_reply (review_id),
                INDEX idx_provider (provider_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Verify review belongs to this provider
        $stProv = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stProv->execute([$userId]);
        $provProfileId = (int)$stProv->fetchColumn();

        $stRev = $db->prepare("SELECT r.id, r.customer_id, r.rating, r.comment, s.name AS service_name FROM tbl_reviews r LEFT JOIN tbl_services s ON s.id = r.service_id WHERE r.id = ? AND r.provider_id = ? LIMIT 1");
        $stRev->execute([(int)$reviewId, $provProfileId]);
        $review = $stRev->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Review not found.'];
            header('Location: ' . BASE_URL . 'provider/reviews'); exit;
        }

        $replyText = trim($_POST['reply'] ?? '');
        if (strlen($replyText) < 2) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Reply cannot be empty.'];
            header('Location: ' . BASE_URL . 'provider/reviews'); exit;
        }
        if (strlen($replyText) > 600) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Reply must be under 600 characters.'];
            header('Location: ' . BASE_URL . 'provider/reviews'); exit;
        }

        // Check not already replied
        $stCheck = $db->prepare("SELECT id FROM tbl_review_replies WHERE review_id = ? LIMIT 1");
        $stCheck->execute([(int)$reviewId]);
        if ($stCheck->fetchColumn()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'You have already replied to this review.'];
            header('Location: ' . BASE_URL . 'provider/reviews'); exit;
        }

        $db->prepare("INSERT INTO tbl_review_replies (review_id, provider_id, reply) VALUES (?, ?, ?)")
           ->execute([(int)$reviewId, $provProfileId, $replyText]);

        // Notify the customer that the provider replied
        $stProvUser = $db->prepare("SELECT pp.business_name, u.first_name, u.last_name FROM tbl_provider_profiles pp JOIN tbl_users u ON u.id = pp.user_id WHERE pp.id = ? LIMIT 1");
        $stProvUser->execute([$provProfileId]);
        $provInfo = $stProvUser->fetch(PDO::FETCH_ASSOC);
        $provName = $provInfo ? htmlspecialchars(trim($provInfo['first_name'] . ' ' . $provInfo['last_name'])) : 'Your provider';
        $bizName  = $provInfo['business_name'] ?? $provName;

        NotificationHelper::send(
            $db,
            [(int)$review['customer_id']],
            'review',
            'Provider replied to your review',
            "{$bizName} responded to your {$review['rating']}-star review" . ($review['service_name'] ? " for \"{$review['service_name']}\"" : '') . '.',
            $replyText,
            BASE_URL . 'bookings'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Your reply has been posted and the customer has been notified.'];
        header('Location: ' . BASE_URL . 'provider/reviews'); exit;
    }

    /**
     * POST  provider/reviews/reply/update/{replyId}
     * Update an existing reply.
     */
    public function updateReply(string $replyId): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stProv = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stProv->execute([$userId]);
        $provProfileId = (int)$stProv->fetchColumn();

        $stR = $db->prepare("SELECT rr.*, r.customer_id, r.rating, r.comment, s.name AS service_name FROM tbl_review_replies rr JOIN tbl_reviews r ON r.id = rr.review_id LEFT JOIN tbl_services s ON s.id = r.service_id WHERE rr.id = ? AND rr.provider_id = ? LIMIT 1");
        $stR->execute([(int)$replyId, $provProfileId]);
        $existing = $stR->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Reply not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/reviews'); exit;
        }

        $replyText = trim($_POST['reply'] ?? '');
        if (strlen($replyText) < 2 || strlen($replyText) > 600) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Reply must be 2–600 characters.'];
            header('Location: ' . BASE_URL . 'provider/reviews'); exit;
        }

        $db->prepare("UPDATE tbl_review_replies SET reply = ? WHERE id = ?")
           ->execute([$replyText, (int)$replyId]);

        // Re-notify customer
        $stProvUser = $db->prepare("SELECT pp.business_name, u.first_name, u.last_name FROM tbl_provider_profiles pp JOIN tbl_users u ON u.id = pp.user_id WHERE pp.id = ? LIMIT 1");
        $stProvUser->execute([$provProfileId]);
        $provInfo = $stProvUser->fetch(PDO::FETCH_ASSOC);
        $bizName  = $provInfo['business_name'] ?? 'Your provider';

        NotificationHelper::send(
            $db,
            [(int)$existing['customer_id']],
            'review',
            'Provider updated their reply to your review',
            "{$bizName} updated their response to your review" . ($existing['service_name'] ? " for \"{$existing['service_name']}\"" : '') . '.',
            $replyText,
            BASE_URL . 'bookings'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Reply updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/reviews'); exit;
    }

    /**
     * POST  provider/reviews/reply/delete/{replyId}
     * Delete a reply.
     */
    public function deleteReply(string $replyId): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stProv = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stProv->execute([$userId]);
        $provProfileId = (int)$stProv->fetchColumn();

        $stR = $db->prepare("SELECT id FROM tbl_review_replies WHERE id = ? AND provider_id = ? LIMIT 1");
        $stR->execute([(int)$replyId, $provProfileId]);

        if (!$stR->fetchColumn()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Reply not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/reviews'); exit;
        }

        $db->prepare("DELETE FROM tbl_review_replies WHERE id = ?")->execute([(int)$replyId]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Reply deleted.'];
        header('Location: ' . BASE_URL . 'provider/reviews'); exit;
    }

    // ── Profile ───────────────────────────────────────────────────────────────

    public function profile(): void
    {
        require __DIR__ . '/../views/Provider/profile.php';
    }

    public function updateProfile(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $businessName = trim($_POST['business_name']     ?? '');
        $categoryId   = (int)($_POST['category_id']      ?? 0);
        $phone        = trim($_POST['phone']              ?? '');
        $experience   = (int)($_POST['experience_years'] ?? 0);

        if (empty($businessName)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Business name is required.'];
            header('Location: ' . BASE_URL . 'provider/profile'); exit;
        }

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if ($profile) {
            $db->prepare("
                UPDATE tbl_provider_profiles
                SET business_name=?, category_id=?, phone=?, experience_years=?
                WHERE user_id=?
            ")->execute([$businessName, $categoryId ?: null, $phone ?: null, $experience, $userId]);
        } else {
            $db->prepare("
                INSERT INTO tbl_provider_profiles (user_id, business_name, category_id, phone, experience_years, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$userId, $businessName, $categoryId ?: null, $phone ?: null, $experience]);
        }

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Provider Profile Updated',
            "Provider {$provName} updated their business profile: \"{$businessName}\".",
            '', BASE_URL . 'admin/providers'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/profile'); exit;
    }

    public function updatePersonalInfo(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $phone     = trim($_POST['phone'] ?? '');
        $bio       = trim($_POST['bio']   ?? '');

        $errors = [];
        if (empty($firstName)) $errors[] = 'First name is required.';
        if (empty($lastName))  $errors[] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';

        if (empty($errors)) {
            $stCheck = $db->prepare("SELECT COUNT(*) FROM tbl_users WHERE email = ? AND id != ?");
            $stCheck->execute([$email, $userId]);
            if ((int)$stCheck->fetchColumn() > 0) $errors[] = 'That email address is already in use.';
        }

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => implode(' ', $errors)];
            header('Location: ' . BASE_URL . 'provider/profile'); exit;
        }

        $db->prepare("UPDATE tbl_users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?")
           ->execute([$firstName, $lastName, $email, $phone ?: null, $userId]);

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        if ($profile) {
            $db->prepare("UPDATE tbl_provider_profiles SET bio=? WHERE user_id=?")->execute([$bio ?: null, $userId]);
        } else {
            $db->prepare("INSERT INTO tbl_provider_profiles (user_id, bio, created_at) VALUES (?, ?, NOW())")->execute([$userId, $bio ?: null]);
        }

        $_SESSION['user_name']  = $firstName;
        $_SESSION['user_email'] = $email;

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Provider Personal Info Updated',
            "Provider {$firstName} {$lastName} updated their personal info (email: {$email}).",
            '', BASE_URL . 'admin/providers'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Personal details updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/profile'); exit;
    }

    public function updatePassword(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password']     ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare("SELECT password_hash FROM tbl_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPw, $user['password_hash'])) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Current password is incorrect.'];
            header('Location: ' . BASE_URL . 'provider/profile'); exit;
        }
        if (strlen($newPw) < 8) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'New password must be at least 8 characters.'];
            header('Location: ' . BASE_URL . 'provider/profile'); exit;
        }
        if ($newPw !== $confirmPw) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'New passwords do not match.'];
            header('Location: ' . BASE_URL . 'provider/profile'); exit;
        }

        $db->prepare("UPDATE tbl_users SET password_hash=? WHERE id=?")
           ->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Provider Password Changed',
            "Provider {$provName} changed their account password.",
            '', BASE_URL . 'admin/providers'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password updated successfully.'];
        // Redirect to wherever the request came from (settings or profile)
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $dest = (str_contains($referer, 'provider/settings')) ? 'provider/settings' : 'provider/profile';
        header('Location: ' . BASE_URL . $dest); exit;
    }

    public function uploadProfilePhoto(): void
    {
        header('Content-Type: application/json');

        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        if (empty($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file received.']); exit;
        }

        $file     = $_FILES['profile_photo'];
        $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mimeType = mime_content_type($file['tmp_name']);

        if (!isset($allowed[$mimeType])) {
            echo json_encode(['success' => false, 'error' => 'Only JPG, PNG or WebP allowed.']); exit;
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File must be under 3 MB.']); exit;
        }

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            echo json_encode(['success' => false, 'error' => 'Profile not found.']); exit;
        }

        $base64 = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
        $db->prepare("UPDATE tbl_provider_profiles SET profile_photo = ? WHERE user_id = ?")->execute([$base64, $userId]);

        echo json_encode(['success' => true, 'dataUrl' => $base64]);
        exit;
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public function settings(): void
    {
        require __DIR__ . '/../views/Provider/settings.php';
    }

    /**
     * POST  provider/settings/deactivate
     * Temporarily hides the provider's profile from Browse/Search.
     */
    public function deactivateAccount(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $db->prepare("UPDATE tbl_provider_profiles SET status = 'inactive' WHERE user_id = ?")
           ->execute([$userId]);

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Provider Account Deactivated',
            "Provider {$provName} has deactivated their account and is now hidden from Browse.",
            '', BASE_URL . 'admin/providers'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Your account has been deactivated. You can reactivate anytime from Settings.'];
        header('Location: ' . BASE_URL . 'provider/settings'); exit;
    }

    /**
     * POST  provider/settings/delete
     * Permanently deletes the provider account and all associated data.
     */
    public function deleteAccount(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $confirm = trim($_POST['confirm'] ?? '');
        if ($confirm !== 'DELETE') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Confirmation text did not match. Account not deleted.'];
            header('Location: ' . BASE_URL . 'provider/settings#danger'); exit;
        }

        $provStmt = $db->prepare("SELECT first_name, last_name, email FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName  = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'Unknown';
        $provEmail = $pu['email'] ?? '';

        // Mark user as deleted (soft delete — keeps referential integrity)
        $db->prepare("UPDATE tbl_users SET status = 'deleted', email = CONCAT('deleted_', id, '_', email) WHERE id = ?")
           ->execute([$userId]);
        $db->prepare("UPDATE tbl_provider_profiles SET status = 'deleted' WHERE user_id = ?")
           ->execute([$userId]);

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Provider Account Deleted',
            "Provider {$provName} ({$provEmail}) has permanently deleted their QuickBook provider account.",
            '', BASE_URL . 'admin/providers'
        );

        // Destroy session and redirect to home
        session_destroy();
        header('Location: ' . BASE_URL . 'home'); exit;
    }

    /**
     * POST  provider/settings/feedback
     * Saves a feedback/rating submission from the provider.
     */
    public function submitFeedback(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $rating      = (int)($_POST['rating']        ?? 0);
        $type        = trim($_POST['type']           ?? 'general');
        $message     = trim($_POST['message']        ?? '');
        $contactBack = isset($_POST['contact_back']) ? 1 : 0;

        if ($rating < 1 || $rating > 5 || empty($message)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please complete all required feedback fields.'];
            header('Location: ' . BASE_URL . 'provider/settings'); exit;
        }

        // Store in tbl_feedback if it exists, otherwise just notify admins
        try {
            $db->prepare("
                INSERT INTO tbl_feedback (user_id, user_role, rating, type, message, contact_back, created_at)
                VALUES (?, 'provider', ?, ?, ?, ?, NOW())
            ")->execute([$userId, $rating, $type, $message, $contactBack]);
        } catch (\Throwable $e) {
            // Table may not exist yet — log and continue gracefully
            error_log('submitFeedback: ' . $e->getMessage());
        }

        $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $provStmt->execute([$userId]);
        $pu = $provStmt->fetch();
        $provName = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';
        $stars    = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            "[Admin] Provider Feedback — {$stars} ({$rating}/5)",
            "Provider {$provName} submitted {$type} feedback: \"{$message}\"" . ($contactBack ? ' (Wants follow-up)' : ''),
            '', BASE_URL . 'admin/dashboard'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Thank you for your feedback! ✨ We appreciate you helping us improve QuickBook.'];
        header('Location: ' . BASE_URL . 'provider/settings'); exit;
    }

    /**
     * POST  provider/settings/save-notifications  (JSON)
     * Upserts notification preference toggles into tbl_provider_notification_prefs.
     */
    public function saveNotifications(): void
    {
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }

        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true);

        if (!isset($body['preferences']) || !is_array($body['preferences'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid payload']); exit;
        }

        $db = Database::getInstance();

        // Resolve provider profile ID
        $st = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $st->execute([$userId]);
        $providerId = (int)$st->fetchColumn();

        if (!$providerId) {
            echo json_encode(['success' => false, 'message' => 'Provider profile not found']); exit;
        }

        // Allowed preference keys (whitelist)
        $allowed = [
            'notif_new_booking', 'notif_booking_confirmed', 'notif_booking_cancelled',
            'notif_reminder_24h', 'notif_reminder_1h',
            'notif_new_review', 'notif_low_rating',
            'notif_portfolio_like', 'notif_portfolio_comment',
            'notif_system_updates', 'notif_security_alerts',
            'channel_inapp', 'channel_email', 'channel_sms',
            'channel_weekly_digest', 'channel_marketing',
        ];

        // Ensure table exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS tbl_provider_notification_prefs (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                provider_id INT NOT NULL,
                pref_key    VARCHAR(80) NOT NULL,
                pref_value  TINYINT(1) NOT NULL DEFAULT 1,
                updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_provider_pref (provider_id, pref_key)
            )
        ");

        $upsert = $db->prepare("
            INSERT INTO tbl_provider_notification_prefs (provider_id, pref_key, pref_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = NOW()
        ");

        $db->beginTransaction();
        try {
            foreach ($allowed as $key) {
                if (array_key_exists($key, $body['preferences'])) {
                    $upsert->execute([$providerId, $key, (int)(bool)$body['preferences'][$key]]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('saveNotifications: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']); exit;
        }

        echo json_encode(['success' => true, 'message' => 'Preferences saved']); exit;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Fetches a booking row and verifies it belongs to the current provider.
     * Returns the row (with cust_name, prov_name, service_name) or null.
     */
    private function _getBookingForProvider(int $bookingId, int $userId, $db): ?array
    {
        $stmt = $db->prepare("
            SELECT b.id, b.customer_id, b.status, b.booking_date, b.booking_time,
                   s.name AS service_name,
                   CONCAT(u.first_name, ' ', u.last_name) AS cust_name,
                   CONCAT(pu.first_name, ' ', pu.last_name) AS prov_name
            FROM tbl_bookings b
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            JOIN tbl_users             u  ON u.id  = b.customer_id
            JOIN tbl_users             pu ON pu.id = pp.user_id
            JOIN tbl_services          s  ON s.id  = b.service_id
            WHERE b.id = ? AND pp.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}