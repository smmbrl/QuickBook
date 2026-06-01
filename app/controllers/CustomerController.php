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
                   p.payment_method,
                   p.status          AS payment_status,
                   (SELECT COUNT(*) FROM tbl_reviews r WHERE r.booking_id = b.id) AS has_review
            FROM tbl_bookings b
            JOIN tbl_services          s  ON b.service_id  = s.id
            JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
            LEFT JOIN tbl_categories   c  ON pp.category_id = c.id
            LEFT JOIN tbl_payments     p  ON p.booking_id  = b.id
            WHERE b.id = ? AND b.customer_id = ? AND (b.deleted_at IS NULL OR b.status IN ('cancelled','rejected'))
        ");
        $stmt->execute([(int)$id, $customerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'bookings'); exit;
        }

        require_once __DIR__ . '/../views/customer/booking-detail.php';
    }

    public function review(string $id): void
    {
        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("
            SELECT b.id, b.customer_id, b.provider_id, b.status,
                   s.name           AS service_name,
                   pp.business_name,
                   pp.id            AS profile_id,
                   (SELECT COUNT(*) FROM tbl_reviews r WHERE r.booking_id = b.id) AS has_review
            FROM tbl_bookings b
            JOIN tbl_services          s  ON s.id  = b.service_id
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            WHERE b.id = ? AND b.customer_id = ? AND b.status = 'completed'
        ");
        $stmt->execute([(int)$id, $customerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found or not eligible for review.'];
            header('Location: ' . BASE_URL . 'bookings'); exit;
        }

        if ((int)$booking['has_review'] > 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'You have already submitted a review for this booking.'];
            header('Location: ' . BASE_URL . 'bookings/' . (int)$id); exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rating  = (int)($_POST['rating']  ?? 0);
            $comment = trim($_POST['comment']  ?? '');

            if ($rating < 1 || $rating > 5) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please select a rating between 1 and 5 stars.'];
                header('Location: ' . BASE_URL . 'bookings/' . (int)$id . '/review'); exit;
            }

            // Fetch service_id from the booking
            $svcIdStmt = $db->prepare("SELECT service_id FROM tbl_bookings WHERE id = ? LIMIT 1");
            $svcIdStmt->execute([(int)$id]);
            $serviceId = (int)($svcIdStmt->fetchColumn() ?: 0);

            $ins = $db->prepare("
                INSERT INTO tbl_reviews
                    (booking_id, customer_id, provider_id, service_id, rating, comment, is_visible, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $ins->execute([
                (int)$id,
                $customerId,
                (int)$booking['provider_id'],
                $serviceId ?: null,
                $rating,
                $comment ?: null,
            ]);

            // Update provider's cached avg_rating and total_reviews
            $db->prepare("
                UPDATE tbl_provider_profiles
                SET avg_rating    = (SELECT ROUND(AVG(rating),2) FROM tbl_reviews WHERE provider_id = ? AND is_visible = 1),
                    total_reviews = (SELECT COUNT(*)             FROM tbl_reviews WHERE provider_id = ? AND is_visible = 1),
                    updated_at    = NOW()
                WHERE id = ?
            ")->execute([
                (int)$booking['provider_id'],
                (int)$booking['provider_id'],
                (int)$booking['provider_id'],
            ]);

            // Update service's cached avg_rating and total_reviews
            if ($serviceId) {
                $db->prepare("
                    UPDATE tbl_services
                    SET avg_rating    = (SELECT ROUND(AVG(rating),2) FROM tbl_reviews WHERE service_id = ? AND is_visible = 1),
                        total_reviews = (SELECT COUNT(*)             FROM tbl_reviews WHERE service_id = ? AND is_visible = 1)
                    WHERE id = ?
                ")->execute([$serviceId, $serviceId, $serviceId]);
            }

            // Get customer name
            $custStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
            $custStmt->execute([$customerId]);
            $custUser = $custStmt->fetch();
            $custName = $custUser
                ? trim($custUser['first_name'] . ' ' . $custUser['last_name'])
                : 'A customer';

            // Get provider's user_id
            $provStmt = $db->prepare("SELECT user_id FROM tbl_provider_profiles WHERE id = ? LIMIT 1");
            $provStmt->execute([(int)$booking['provider_id']]);
            $providerUserId = (int)($provStmt->fetchColumn() ?: 0);

            $starLabel = match($rating) {
                1 => '⭐ Poor',
                2 => '⭐⭐ Fair',
                3 => '⭐⭐⭐ Good',
                4 => '⭐⭐⭐⭐ Very Good',
                5 => '⭐⭐⭐⭐⭐ Excellent',
                default => "{$rating} stars",
            };
            $snippet = $comment
                ? '"' . mb_substr($comment, 0, 120) . (mb_strlen($comment) > 120 ? '…' : '') . '"'
                : 'No written comment.';

            // ── Notify Provider ────────────────────────────────────────────────
            if ($providerUserId) {
                $notifProv = $db->prepare("
                    INSERT INTO tbl_notifications
                        (user_id, type, title, message, body, link_url, is_read, created_at)
                    VALUES (?, 'review', 'New Review Received', ?, ?, ?, 0, NOW())
                ");
                $notifProv->execute([
                    $providerUserId,
                    "{$custName} left you a {$starLabel} review for \"{$booking['service_name']}\".",
                    $snippet,
                    BASE_URL . 'provider/bookings/' . (int)$id,
                ]);
            }

            // ── Notify Admin ───────────────────────────────────────────────────
            $adminStmt = $db->prepare("SELECT id FROM tbl_users WHERE role = 'admin' LIMIT 5");
            $adminStmt->execute();
            $adminIds = $adminStmt->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($adminIds as $adminId) {
                $notifAdmin = $db->prepare("
                    INSERT INTO tbl_notifications
                        (user_id, type, title, message, body, link_url, is_read, created_at)
                    VALUES (?, 'review', 'Review Submitted', ?, ?, ?, 0, NOW())
                ");
                $notifAdmin->execute([
                    (int)$adminId,
                    "{$custName} submitted a {$starLabel} review for \"{$booking['service_name']}\" (Booking #{$id}).",
                    $snippet,
                    BASE_URL . 'admin/bookings',
                ]);
            }

            // ── Notify Customer (confirmation) ─────────────────────────────────
            $notifCust = $db->prepare("
                INSERT INTO tbl_notifications
                    (user_id, type, title, message, body, link_url, is_read, created_at)
                VALUES (?, 'review', 'Review Submitted', ?, ?, ?, 0, NOW())
            ");
            $notifCust->execute([
                $customerId,
                "Your {$starLabel} review for \"{$booking['service_name']}\" has been submitted successfully.",
                'Thank you for your feedback! It helps other customers make better decisions.',
                BASE_URL . 'bookings/' . (int)$id,
            ]);

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Thank you! Your review has been submitted.'];
            header('Location: ' . BASE_URL . 'bookings/' . (int)$id . '/review'); exit;
        }

        require_once __DIR__ . '/../views/customer/review.php';
    }

    public function addReview(string $id): void
    {
        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("
            SELECT b.id, b.customer_id, b.provider_id, b.status,
                   s.name           AS service_name,
                   pp.business_name,
                   pp.id            AS profile_id,
                   (SELECT COUNT(*) FROM tbl_reviews r WHERE r.booking_id = b.id) AS has_review
            FROM tbl_bookings b
            JOIN tbl_services          s  ON s.id  = b.service_id
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            WHERE b.id = ? AND b.customer_id = ? AND b.status = 'completed'
        ");
        $stmt->execute([(int)$id, $customerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found or not eligible for review.'];
            header('Location: ' . BASE_URL . 'bookings'); exit;
        }

        if ((int)$booking['has_review'] > 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'You have already submitted a review for this booking.'];
            header('Location: ' . BASE_URL . 'bookings/' . (int)$id . '/review'); exit;
        }

        // POST is handled by the existing review() method — redirect there
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Location: ' . BASE_URL . 'bookings/' . (int)$id . '/review'); exit;
        }

        require_once __DIR__ . '/../views/customer/add_review.php';
    }

    public function acceptReschedule(string $id): void
    {
        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("
            SELECT b.id, b.customer_id, b.provider_id, b.status,
                   b.suggested_date, b.suggested_time, b.reschedule_note,
                   s.name AS service_name,
                   pp.user_id AS provider_user_id
            FROM tbl_bookings b
            JOIN tbl_services          s  ON s.id  = b.service_id
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            WHERE b.id = ? AND b.customer_id = ? AND b.status = 'rescheduled'
        ");
        $stmt->execute([(int)$id, $customerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found or cannot be accepted.'];
            header('Location: ' . BASE_URL . 'bookings'); exit;
        }

        if (empty($booking['suggested_date']) || empty($booking['suggested_time'])) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'No suggested schedule found for this booking.'];
            header('Location: ' . BASE_URL . 'bookings/' . (int)$id); exit;
        }

        $upd = $db->prepare("
            UPDATE tbl_bookings
            SET booking_date    = ?,
                booking_time    = ?,
                start_time      = ?,
                status          = 'pending',
                suggested_date  = NULL,
                suggested_time  = NULL,
                reschedule_note = NULL,
                updated_at      = NOW()
            WHERE id = ?
        ");
        $upd->execute([
            $booking['suggested_date'],
            $booking['suggested_time'],
            $booking['suggested_time'],
            (int)$id,
        ]);

        $custStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $custStmt->execute([$customerId]);
        $custUser = $custStmt->fetch();
        $custName = $custUser
            ? htmlspecialchars($custUser['first_name'] . ' ' . $custUser['last_name'])
            : 'The customer';

        $formattedDate = date('l, F j, Y', strtotime($booking['suggested_date']));
        $formattedTime = date('g:i A',     strtotime($booking['suggested_time']));

        // ── Notify Provider that customer accepted the reschedule ──────────────
        $notif = $db->prepare("
            INSERT INTO tbl_notifications
                (user_id, type, title, message, body, link_url, is_read, created_at)
            VALUES (?, 'reschedule_accepted', 'Reschedule Accepted', ?, ?, ?, 0, NOW())
        ");
        $notif->execute([
            $booking['provider_user_id'],
            "{$custName} accepted the reschedule for \"{$booking['service_name']}\".",
            "New schedule confirmed: {$formattedDate} at {$formattedTime}.",
            BASE_URL . 'provider/bookings/' . (int)$id,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'You accepted the reschedule. Booking is now pending confirmation.'];
        header('Location: ' . BASE_URL . 'bookings/' . (int)$id); exit;
    }

    public function cancelBooking(string $id): void
    {
        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("
            SELECT b.id, b.status, b.provider_id,
                   s.name AS service_name,
                   b.booking_date, b.booking_time,
                   pp.user_id AS provider_user_id
            FROM tbl_bookings b
            JOIN tbl_services s ON s.id = b.service_id
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            WHERE b.id = ? AND b.customer_id = ?
              AND b.status IN ('pending','confirmed','rescheduled')
              AND b.deleted_at IS NULL
        ");
        $stmt->execute([$id, $customerId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found or cannot be cancelled.'];
            header('Location: ' . BASE_URL . 'bookings'); exit;
        }

        $upd = $db->prepare("UPDATE tbl_bookings SET status = 'cancelled' WHERE id = ?");
        $upd->execute([$id]);

        // ── Notify Provider of customer cancellation ───────────────────────────
        $custStmt = $db->prepare("SELECT first_name, last_name FROM tbl_users WHERE id = ? LIMIT 1");
        $custStmt->execute([$customerId]);
        $custUser = $custStmt->fetch();
        $custName = $custUser ? trim($custUser['first_name'] . ' ' . $custUser['last_name']) : 'A customer';

        if (!empty($booking['provider_user_id'])) {
            $formattedDate = date('l, F j, Y', strtotime($booking['booking_date']));
            $provNotif = $db->prepare("
                INSERT INTO tbl_notifications (user_id, type, title, message, body, link_url, is_read, created_at)
                VALUES (?, 'booking_cancelled', 'Booking Cancelled by Customer', ?, ?, ?, 0, NOW())
            ");
            $provNotif->execute([
                (int)$booking['provider_user_id'],
                "{$custName} cancelled their booking for \"{$booking['service_name']}\".",
                "The booking scheduled for {$formattedDate} has been cancelled.",
                BASE_URL . 'provider/bookings/' . (int)$id,
            ]);
        }

        // ── Notify Customer (confirmation) ─────────────────────────────────────
        $custNotif = $db->prepare("
            INSERT INTO tbl_notifications (user_id, type, title, message, body, link_url, is_read, created_at)
            VALUES (?, 'booking_cancelled', 'Booking Cancelled', 'Your booking has been cancelled successfully.', ?, ?, 0, NOW())
        ");
        $custNotif->execute([
            $customerId,
            "Booking #{$id} for \"{$booking['service_name']}\" has been cancelled.",
            BASE_URL . 'bookings/' . (int)$id,
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking cancelled successfully.'];
        header('Location: ' . BASE_URL . 'bookings'); exit;
    }

    public function serviceDetail(string $id): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT s.*,
                   pp.id               AS profile_id,
                   pp.business_name,
                   pp.bio,
                   pp.business_address,
                   pp.offers_home_service,
                   pp.is_approved,
                   pp.profile_photo,
                   s.avg_rating,
                   s.total_reviews,
                   c.name              AS category_name,
                   c.slug              AS category_slug,
                   u.first_name        AS provider_first,
                   u.last_name         AS provider_last
            FROM tbl_services s
            JOIN tbl_provider_profiles pp ON s.provider_id = pp.id
            JOIN tbl_users             u  ON pp.user_id    = u.id
            LEFT JOIN tbl_categories   c  ON s.category_id = c.id
            WHERE s.id = ? AND s.is_active = 1 AND pp.is_approved = 1
        ");
        $stmt->execute([(int)$id]);
        $service = $stmt->fetch();

        if (!$service) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or unavailable.'];
            header('Location: ' . BASE_URL . 'browse'); exit;
        }

        $avStmt = $db->prepare("
            SELECT day_of_week, start_time, end_time
            FROM tbl_provider_availability
            WHERE provider_id = ? AND is_available = 1
            ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')
        ");
        $avStmt->execute([$service['profile_id']]);
        $availability = $avStmt->fetchAll();

        require_once __DIR__ . '/../views/customer/appointment_booking.php';
    }

    public function loyalty(): void
    {
        require_once __DIR__ . '/../views/customer/loyalty.php';
    }

    public function redeemLoyalty(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        $db       = Database::getInstance();
        $userId   = (int)($_SESSION['user_id'] ?? 0);
        $rewardId = (int)($_POST['reward_id'] ?? 0);

        $rewards = [
            1 => ['title' => '₱50 Booking Credit',     'cost' => 200],
            2 => ['title' => '₱150 Booking Credit',    'cost' => 500],
            3 => ['title' => 'Free Service Upgrade',    'cost' => 750],
            4 => ['title' => '20% Off Next Booking',   'cost' => 1000],
            5 => ['title' => 'Priority Scheduling',    'cost' => 400],
            6 => ['title' => 'Free Home Visit Add-on', 'cost' => 600],
        ];

        if (!$userId || !isset($rewards[$rewardId])) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid request.'];
            header('Location: ' . BASE_URL . 'loyalty'); exit;
        }

        $reward = $rewards[$rewardId];
        $cost   = $reward['cost'];

        // Get current balance
        $stBal = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
        $stBal->execute([$userId]);
        $balance = (int)$stBal->fetchColumn();

        if ($balance < $cost) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Not enough points to redeem this reward.'];
            header('Location: ' . BASE_URL . 'loyalty'); exit;
        }

        $newBalance = $balance - $cost;

        // Insert redemption record (negative points)
        $ins = $db->prepare("
            INSERT INTO tbl_loyalty_points (user_id, booking_id, type, points, balance, description, created_at)
            VALUES (?, NULL, 'redeem', ?, ?, ?, NOW())
        ");
        $ins->execute([
            $userId,
            -$cost,
            $newBalance,
            'Redeemed: ' . $reward['title'],
        ]);

        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  => '🎉 Success! "' . $reward['title'] . '" has been redeemed for ' . number_format($cost) . ' pts. Our team will apply it to your next booking.',
        ];
        header('Location: ' . BASE_URL . 'loyalty'); exit;
    }

    public function profile(): void
    {
        require_once __DIR__ . '/../views/customer/profile.php';
    }

    public function updateProfile(): void
    {
        $db     = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];
        $action = $_POST['action'] ?? '';

        // Upload avatar
        if ($action === 'upload_avatar') {
            if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'No file uploaded or upload error occurred.'];
                header('Location: ' . BASE_URL . 'profile'); exit;
            }

            $file     = $_FILES['avatar'];
            $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mimeType = mime_content_type($file['tmp_name']);

            if (!isset($allowed[$mimeType])) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Only JPG, PNG, or WEBP images are allowed.'];
                header('Location: ' . BASE_URL . 'profile'); exit;
            }
            if ($file['size'] > 3 * 1024 * 1024) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Image must be under 3 MB.'];
                header('Location: ' . BASE_URL . 'profile'); exit;
            }

            $base64 = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
            $db->prepare("UPDATE tbl_users SET avatar_url = ? WHERE id = ?")->execute([$base64, $userId]);

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile picture updated successfully.'];
            header('Location: ' . BASE_URL . 'profile'); exit;
        }

        // Update profile info
        if ($action === 'update_profile') {
            $newFirst  = trim($_POST['first_name']    ?? '');
            $newLast   = trim($_POST['last_name']     ?? '');
            $newPhone  = trim($_POST['phone']         ?? '');
            $newEmail  = strtolower(trim($_POST['email'] ?? ''));
            $newGender = in_array($_POST['gender'] ?? '', ['male','female','non_binary','prefer_not_to_say'])
                         ? $_POST['gender'] : null;
            $newDob    = trim($_POST['date_of_birth'] ?? '') ?: null;

            $errors = [];
            if (empty($newFirst)) $errors[] = 'First name is required.';
            if (empty($newLast))  $errors[] = 'Last name is required.';
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';

            if (empty($errors)) {
                $stCheck = $db->prepare("SELECT COUNT(*) FROM tbl_users WHERE email = ? AND id != ?");
                $stCheck->execute([$newEmail, $userId]);
                if ((int)$stCheck->fetchColumn() > 0) {
                    $errors[] = 'That email address is already in use.';
                }
            }

            if (!empty($errors)) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => implode(' ', $errors)];
                header('Location: ' . BASE_URL . 'profile'); exit;
            }

            $db->prepare("UPDATE tbl_users SET first_name=?, last_name=?, email=?, phone=?, gender=?, date_of_birth=? WHERE id=?")
               ->execute([$newFirst, $newLast, $newEmail, $newPhone ?: null, $newGender, $newDob, $userId]);

            $_SESSION['user_name']  = $newFirst;
            $_SESSION['user_email'] = $newEmail;

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
            header('Location: ' . BASE_URL . 'profile'); exit;
        }

        // Change password
        if ($action === 'change_password') {
            $currentPw = $_POST['current_password'] ?? '';
            $newPw     = $_POST['new_password']     ?? '';
            $confirmPw = $_POST['confirm_password'] ?? '';

            $errors  = [];
            $stUser  = $db->prepare("SELECT password_hash FROM tbl_users WHERE id = ? LIMIT 1");
            $stUser->execute([$userId]);
            $user = $stUser->fetch();

            if (!$user || !password_verify($currentPw, $user['password_hash'])) $errors[] = 'Current password is incorrect.';
            if (strlen($newPw) < 8)    $errors[] = 'New password must be at least 8 characters.';
            if ($newPw !== $confirmPw) $errors[] = 'New passwords do not match.';

            if (!empty($errors)) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => implode(' ', $errors)];
                header('Location: ' . BASE_URL . 'profile'); exit;
            }

            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("UPDATE tbl_users SET password_hash=? WHERE id=?")->execute([$hash, $userId]);

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password changed successfully.'];
            header('Location: ' . BASE_URL . 'profile'); exit;
        }

        header('Location: ' . BASE_URL . 'profile'); exit;
    }

    public function deactivateAccount(): void
    {
        $db     = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        // Soft-deactivate: set is_active = 0 so user cannot log in
        $db->prepare("UPDATE tbl_users SET is_active = 0, updated_at = NOW() WHERE id = ?")
           ->execute([$userId]);

        // Notify admins
        $adminStmt = $db->prepare("SELECT id FROM tbl_users WHERE role = 'admin' LIMIT 5");
        $adminStmt->execute();
        $adminIds = $adminStmt->fetchAll(\PDO::FETCH_COLUMN);

        $custName = $_SESSION['user_name'] ?? 'A customer';
        foreach ($adminIds as $adminId) {
            $db->prepare("
                INSERT INTO tbl_notifications
                    (user_id, type, title, message, body, link_url, is_read, created_at)
                VALUES (?, 'system', 'Account Deactivated', ?, ?, ?, 0, NOW())
            ")->execute([
                (int)$adminId,
                "Customer \"{$custName}\" has deactivated their account.",
                'The account has been soft-deactivated and the user has been logged out.',
                BASE_URL . 'admin/users',
            ]);
        }

        // Destroy session and redirect to login
        session_destroy();
        header('Location: ' . BASE_URL . 'login'); exit;
    }

    public function deleteAccount(): void
    {
        $db     = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];

        // Anonymise personal data — keep booking records for provider/admin history
        $db->prepare("
            UPDATE tbl_users
            SET first_name     = 'Deleted',
                last_name      = 'User',
                email          = CONCAT('deleted_', id, '@quickbook.invalid'),
                phone          = NULL,
                avatar_url     = NULL,
                password_hash  = '',
                is_active      = 0,
                deleted_at     = NOW(),
                updated_at     = NOW()
            WHERE id = ?
        ")->execute([$userId]);

        // Remove loyalty points
        $db->prepare("DELETE FROM tbl_loyalty_points WHERE user_id = ?")->execute([$userId]);

        // Remove notifications
        $db->prepare("DELETE FROM tbl_notifications WHERE user_id = ?")->execute([$userId]);

        // Remove favourites
        $db->prepare("DELETE FROM tbl_provider_favorites WHERE customer_id = ?")->execute([$userId]);

        // Soft-cancel any active bookings
        $db->prepare("
            UPDATE tbl_bookings
            SET status = 'cancelled', deleted_at = NOW(), updated_at = NOW()
            WHERE customer_id = ? AND status IN ('pending','confirmed','rescheduled')
        ")->execute([$userId]);

        // Notify admins
        $adminStmt = $db->prepare("SELECT id FROM tbl_users WHERE role = 'admin' LIMIT 5");
        $adminStmt->execute();
        $adminIds = $adminStmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($adminIds as $adminId) {
            $db->prepare("
                INSERT INTO tbl_notifications
                    (user_id, type, title, message, body, link_url, is_read, created_at)
                VALUES (?, 'system', 'Account Deleted', ?, ?, ?, 0, NOW())
            ")->execute([
                (int)$adminId,
                "A customer account (ID: {$userId}) has been permanently deleted.",
                'Personal data has been anonymised and active bookings cancelled.',
                BASE_URL . 'admin/users',
            ]);
        }

        session_destroy();
        header('Location: ' . BASE_URL . 'login'); exit;
    }
}