<?php
class ProviderDashController
{

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // Must be logged in
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        if (($_SESSION['user_role'] ?? '') !== 'provider') {
            $map = ['admin' => 'admin/dashboard', 'customer' => 'dashboard'];
            header('Location: ' . BASE_URL . ($map[$_SESSION['user_role']] ?? 'login')); exit;
        }
    }

    public function index(): void
    {
        require __DIR__ . '/../views/Provider/dashboard.php';
    }


    public function bookings(): void
    {
        require __DIR__ . '/../views/Provider/bookings.php';
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
            header('Location: ' . BASE_URL . 'provider/bookings');
            exit;
        }

        require __DIR__ . '/../views/Provider/customer-detail.php';
    }

    public function updateBooking(string $id): void
    {
        $db         = Database::getInstance();
        $providerId = $_SESSION['user_id'] ?? 0;
        $action     = $_POST['action'] ?? '';
        $reason     = trim($_POST['reason'] ?? '');

        // Fetch booking + customer info in one query
        $stmt = $db->prepare("
            SELECT b.id, b.customer_id, b.status,
                   u.first_name, u.last_name,
                   s.name AS service_name,
                   pp.user_id AS provider_user_id
            FROM tbl_bookings b
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            JOIN tbl_users             u  ON u.id  = b.customer_id
            JOIN tbl_services          s  ON s.id  = b.service_id
            WHERE b.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([(int)$id, $providerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'provider/bookings');
            exit;
        }

        // Handle reschedule suggestion separately (does not change booking status)
        if ($action === 'reschedule') {
            $suggestedDate   = trim($_POST['suggested_date']  ?? '');
            $suggestedTime   = trim($_POST['suggested_time']  ?? '');
            $reschedReason   = trim($_POST['resched_reason']  ?? '');

            if (!$suggestedDate || !$suggestedTime || strlen($reschedReason) < 5) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please fill in the suggested date, time, and reason.'];
                header('Location: ' . BASE_URL . 'provider/bookings/' . (int)$id);
                exit;
            }

            if (strtotime($suggestedDate) < strtotime('today')) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Suggested date cannot be in the past.'];
                header('Location: ' . BASE_URL . 'provider/bookings/' . (int)$id);
                exit;
            }

            // Fetch provider name for the notification
            $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
            $provStmt->execute([$providerId]);
            $provUser = $provStmt->fetch();
            $provName = $provUser
                ? htmlspecialchars($provUser['first_name'] . ' ' . $provUser['last_name'])
                : 'Your provider';

            $formattedDate = date('l, F j, Y', strtotime($suggestedDate));
            $formattedTime = date('g:i A', strtotime($suggestedTime));

            $notifMsg  = "Your provider {$provName} is suggesting a reschedule for \"{$booking['service_name']}\".";
            $notifBody = "Suggested new schedule: {$formattedDate} at {$formattedTime}.\nReason: {$reschedReason}";

            // Update booking status to 'rescheduled' and save suggestion details
            $upd = $db->prepare("
                UPDATE tbl_bookings
                SET status = 'rescheduled',
                    suggested_date  = ?,
                    suggested_time  = ?,
                    reschedule_note = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([$suggestedDate, $suggestedTime, $reschedReason, (int)$id]);

            $notif = $db->prepare("
                INSERT INTO tbl_notifications
                    (user_id, type, title, message, body, is_read, created_at)
                VALUES (?, 'reschedule', 'Booking Rescheduled', ?, ?, 0, NOW())
            ");
            $notif->execute([$booking['customer_id'], $notifMsg, $notifBody]);

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Reschedule suggestion sent and booking marked as rescheduled.'];
            header('Location: ' . BASE_URL . 'provider/bookings');
            exit;
        }

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
            header('Location: ' . BASE_URL . 'provider/bookings');
            exit;
        }

        // Require reason for delete action
        if ($action === 'delete' && $reason === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'A reason is required when deleting a booking.'];
            header('Location: ' . BASE_URL . 'provider/bookings');
            exit;
        }

        $newStatus = $statusMap[$action];

        if ($action === 'delete') {
            // Store reason in cancellation_reason; mark as cancelled
            $upd = $db->prepare("
                UPDATE tbl_bookings
                SET status = 'cancelled',
                    cancellation_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([$reason, (int)$id]);

            // Notify the customer with the provider's reason
            $provStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
            $provStmt->execute([$providerId]);
            $provUser = $provStmt->fetch();
            $provName = $provUser
                ? htmlspecialchars($provUser['first_name'] . ' ' . $provUser['last_name'])
                : 'Your provider';

            $notifTitle = 'Booking Cancelled by Provider';
            $notifMsg   = "Your booking for \"{$booking['service_name']}\" has been cancelled by {$provName}.";
            $notifBody  = "Reason: {$reason}";

            $notif = $db->prepare("
                INSERT INTO tbl_notifications
                    (user_id, type, title, message, body, is_read, created_at)
                VALUES (?, 'booking_cancelled', ?, ?, ?, 0, NOW())
            ");
            $notif->execute([
                $booking['customer_id'],
                $notifTitle,
                $notifMsg,
                $notifBody,
            ]);

            $_SESSION['flash'] = [
                'type' => 'success',
                'msg'  => 'Booking has been cancelled and the customer has been notified.',
            ];
        } else {
            $upd = $db->prepare("
                UPDATE tbl_bookings
                SET status = ?,
                    notes = COALESCE(NULLIF(?, ''), notes),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([$newStatus, $reason ?: null, (int)$id]);

            $labels = [
                'confirm'  => 'Booking confirmed — customer has been notified.',
                'start'    => 'Booking marked as in progress.',
                'complete' => 'Booking marked as completed.',
                'reject'   => 'Booking rejected.',
                'cancel'   => 'Booking cancelled.',
            ];

            // Notify customer on confirm
            if ($action === 'confirm') {
                $notif = $db->prepare("
                    INSERT INTO tbl_notifications
                        (user_id, type, title, message, is_read, created_at)
                    VALUES (?, 'booking', 'Booking Confirmed', ?, 0, NOW())
                ");
                $notif->execute([
                    $booking['customer_id'],
                    "Your booking for \"{$booking['service_name']}\" has been confirmed by your provider.",
                ]);
            }

            $_SESSION['flash'] = ['type' => 'success', 'msg' => $labels[$action]];
        }

        header('Location: ' . BASE_URL . 'provider/bookings');
        exit;
    }

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
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        $providerId   = $profile['id'];
        $name         = trim($_POST['name']               ?? '');
        $serviceType  = trim($_POST['service_type']       ?? '');
        $locationType = trim($_POST['location_type']      ?? 'In-shop');
        $shopAddress  = trim($_POST['shop_address']       ?? '');
        $price        = (float)($_POST['price']           ?? 0);
        $description  = trim($_POST['description']        ?? '');
        $durationRaw  = (int)($_POST['duration_minutes']  ?? 0);
        $durationUnit = $_POST['duration_unit']           ?? 'min';
        $durationMins = ($durationUnit === 'hr') ? $durationRaw * 60 : $durationRaw;

        // shop_address only applies to In-shop and Flexible
        if (!in_array($locationType, ['In-shop', 'Flexible'])) {
            $shopAddress = '';
        }

        if ($name === '' || $serviceType === '' || $price <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Name, type, and a valid price are required.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }
        if ($locationType === 'In-shop' && $shopAddress === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please enter your shop address for In-shop services.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        // Map service_type enum value to the matching category_id in tbl_categories
        $serviceTypeCategoryMap = [
            'Barber'        => 1,
            'Hair Stylist'  => 2,
            'Nail Tech'     => 3,
            'Massage'       => 4,
            'Skincare'      => 5,
            'Fitness'       => 6,
            'Home Cleaning' => 7,
            'Pet Groomer'   => 8,
            'Event Stylist' => 9,
            'Makeup'        => 10,
        ];
        $categoryId = $serviceTypeCategoryMap[$serviceType] ?? null;

        $ins = $db->prepare("
            INSERT INTO tbl_services
                (provider_id, category_id, name, service_type, location_type, shop_address, price, duration_minutes, description, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $ins->execute([$providerId, $categoryId, $name, $serviceType, $locationType, $shopAddress ?: null, $price, $durationMins ?: null, $description ?: null]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Service \"{$name}\" added successfully."];
        header('Location: ' . BASE_URL . 'provider/services');
        exit;
    }

    public function updateService(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT s.id FROM tbl_services s
            JOIN tbl_provider_profiles pp ON pp.id = s.provider_id
            WHERE s.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);

        if (!$stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        $name         = trim($_POST['name']               ?? '');
        $serviceType  = trim($_POST['service_type']       ?? '');
        $locationType = trim($_POST['location_type']      ?? 'In-shop');
        $shopAddress  = trim($_POST['shop_address']       ?? '');
        $price        = (float)($_POST['price']           ?? 0);
        $description  = trim($_POST['description']        ?? '');
        $durationRaw  = (int)($_POST['duration_minutes']  ?? 0);
        $durationUnit = $_POST['duration_unit']           ?? 'min';
        $durationMins = ($durationUnit === 'hr') ? $durationRaw * 60 : $durationRaw;
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        // shop_address only applies to In-shop and Flexible
        if (!in_array($locationType, ['In-shop', 'Flexible'])) {
            $shopAddress = '';
        }

        if ($name === '' || $serviceType === '' || $price <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Name, type, and a valid price are required.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }
        if ($locationType === 'In-shop' && $shopAddress === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please enter your shop address for In-shop services.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        // Map service_type enum value to the matching category_id in tbl_categories
        $serviceTypeCategoryMap = [
            'Barber'        => 1,
            'Hair Stylist'  => 2,
            'Nail Tech'     => 3,
            'Massage'       => 4,
            'Skincare'      => 5,
            'Fitness'       => 6,
            'Home Cleaning' => 7,
            'Pet Groomer'   => 8,
            'Event Stylist' => 9,
            'Makeup'        => 10,
        ];
        $categoryId = $serviceTypeCategoryMap[$serviceType] ?? null;

        $upd = $db->prepare("
            UPDATE tbl_services
            SET name = ?, category_id = ?, service_type = ?, location_type = ?, shop_address = ?, price = ?,
                duration_minutes = ?, description = ?, is_active = ?
            WHERE id = ?
        ");
        $upd->execute([$name, $categoryId, $serviceType, $locationType, $shopAddress ?: null, $price, $durationMins ?: null, $description ?: null, $isActive, $id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Service \"{$name}\" updated successfully."];
        header('Location: ' . BASE_URL . 'provider/services');
        exit;
    }

   public function deleteService(string $id): void
{
    $db     = Database::getInstance();
    $userId = $_SESSION['user_id'] ?? 0;

    $stmt = $db->prepare("
        SELECT s.id FROM tbl_services s
        JOIN tbl_provider_profiles pp ON pp.id = s.provider_id
        WHERE s.id = ? AND pp.user_id = ?
    ");
    $stmt->execute([$id, $userId]);

    if (!$stmt->fetch()) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or access denied.'];
        header('Location: ' . BASE_URL . 'provider/services');
        exit;
    }

    $del = $db->prepare("DELETE FROM tbl_services WHERE id = ?");
    $del->execute([$id]);

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Service deleted successfully.'];
    header('Location: ' . BASE_URL . 'provider/services');
    exit;
}

    public function toggleService(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT s.id, s.is_active FROM tbl_services s
            JOIN tbl_provider_profiles pp ON pp.id = s.provider_id
            WHERE s.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);
        $service = $stmt->fetch();

        if (!$service) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        $newStatus = $service['is_active'] ? 0 : 1;
        $upd = $db->prepare("UPDATE tbl_services SET is_active = ? WHERE id = ?");
        $upd->execute([$newStatus, $id]);

        $label = $newStatus ? 'Service activated.' : 'Service deactivated.';
        $_SESSION['flash'] = ['type' => 'success', 'msg' => $label];
        header('Location: ' . BASE_URL . 'provider/services');
        exit;
    }


    public function availability(): void
    {
        require __DIR__ . '/../views/Provider/availability.php';
    }

    public function storeAvailability(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider profile not found.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        $providerId = $profile['id'];
        $daysInput  = $_POST['days'] ?? [];

        if (empty($daysInput)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'No schedule data submitted.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        $del = $db->prepare("DELETE FROM tbl_provider_availability WHERE provider_id = ?");
        $del->execute([$providerId]);

        $ins = $db->prepare("
            INSERT INTO tbl_provider_availability (provider_id, day_of_week, start_time, end_time, is_available)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($daysInput as $dayName => $data) {
            $isAvailable = isset($data['is_available']) ? 1 : 0;
            $startTime   = trim($data['start_time'] ?? '08:00');
            $endTime     = trim($data['end_time']   ?? '17:00');
            $ins->execute([$providerId, $dayName, $startTime, $endTime, $isAvailable]);
        }

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Availability saved successfully.'];
        header('Location: ' . BASE_URL . 'provider/availability');
        exit;
    }

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
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        $dayOfWeek = trim($_POST['day_of_week'] ?? '');
        $startTime = trim($_POST['start_time']  ?? '');
        $endTime   = trim($_POST['end_time']    ?? '');

        if ($dayOfWeek === '' || $startTime === '' || $endTime === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'All fields are required.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        if ($startTime >= $endTime) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Start time must be before end time.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        $upd = $db->prepare("
            UPDATE tbl_provider_availability
            SET day_of_week = ?, start_time = ?, end_time = ?
            WHERE id = ?
        ");
        $upd->execute([$dayOfWeek, $startTime, $endTime, $id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Availability updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/availability');
        exit;
    }

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
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        $del = $db->prepare("DELETE FROM tbl_provider_availability WHERE id = ?");
        $del->execute([$id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Availability slot removed successfully.'];
        header('Location: ' . BASE_URL . 'provider/availability');
        exit;
    }

    public function profile(): void
    {
        require __DIR__ . '/../views/Provider/profile.php';
    }

    public function updateProfile(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $businessName = trim($_POST['business_name'] ?? '');
        $categoryId   = (int)($_POST['category_id']  ?? 0);
        $phone        = trim($_POST['phone']          ?? '');
        $experience   = (int)($_POST['experience_years'] ?? 0);

        if (empty($businessName)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Business name is required.'];
            header('Location: ' . BASE_URL . 'provider/profile');
            exit;
        }

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if ($profile) {
            $upd = $db->prepare("
                UPDATE tbl_provider_profiles
                SET business_name = ?, category_id = ?, phone = ?, experience_years = ?
                WHERE user_id = ?
            ");
            $upd->execute([$businessName, $categoryId ?: null, $phone ?: null, $experience, $userId]);
        } else {
            $ins = $db->prepare("
                INSERT INTO tbl_provider_profiles (user_id, business_name, category_id, phone, experience_years, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([$userId, $businessName, $categoryId ?: null, $phone ?: null, $experience]);
        }

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/profile');
        exit;
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
            if ((int)$stCheck->fetchColumn() > 0) {
                $errors[] = 'That email address is already in use.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => implode(' ', $errors)];
            header('Location: ' . BASE_URL . 'provider/profile'); exit;
        }

        // Update user table
        $db->prepare("UPDATE tbl_users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?")
           ->execute([$firstName, $lastName, $email, $phone ?: null, $userId]);

        // Update bio in provider_profiles
        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        if ($profile) {
            $db->prepare("UPDATE tbl_provider_profiles SET bio=? WHERE user_id=?")
               ->execute([$bio ?: null, $userId]);
        } else {
            $db->prepare("INSERT INTO tbl_provider_profiles (user_id, bio, created_at) VALUES (?, ?, NOW())")
               ->execute([$userId, $bio ?: null]);
        }

        $_SESSION['user_name']  = $firstName;
        $_SESSION['user_email'] = $email;
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Personal details updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/profile'); exit;
    }

    public function updatePassword(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $currentPw  = $_POST['current_password']  ?? '';
        $newPw      = $_POST['new_password']       ?? '';
        $confirmPw  = $_POST['confirm_password']   ?? '';

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

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/profile'); exit;
    }

    public function uploadProfilePhoto(): void
    {
        header('Content-Type: application/json');

        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        if (empty($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file received.']);
            exit;
        }

        $file     = $_FILES['profile_photo'];
        $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mimeType = mime_content_type($file['tmp_name']);

        if (!isset($allowed[$mimeType])) {
            echo json_encode(['success' => false, 'error' => 'Only JPG, PNG or WebP allowed.']);
            exit;
        }

        if ($file['size'] > 3 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File must be under 3 MB.']);
            exit;
        }

        // Ensure upload directory exists
        $uploadDir = __DIR__ . '/../../public/assets/uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Fetch existing photo to delete old file
        $stmt = $db->prepare("SELECT id, profile_photo FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            echo json_encode(['success' => false, 'error' => 'Profile not found.']);
            exit;
        }

        // Delete old photo file if it exists
        if (!empty($profile['profile_photo'])) {
            $oldPath = $uploadDir . $profile['profile_photo'];
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Save new photo
        $ext      = $allowed[$mimeType];
        $filename = 'provider_' . $userId . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
            exit;
        }

        // Update DB
        $upd = $db->prepare("UPDATE tbl_provider_profiles SET profile_photo = ? WHERE user_id = ?");
        $upd->execute([$filename, $userId]);

        echo json_encode(['success' => true, 'filename' => $filename]);
        exit;
    }
}