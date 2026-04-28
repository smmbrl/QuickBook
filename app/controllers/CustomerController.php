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
                   (SELECT COUNT(*) FROM tbl_reviews r WHERE r.booking_id = b.id) AS has_review
            FROM tbl_bookings b
            JOIN tbl_services          s  ON b.service_id  = s.id
            JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
            LEFT JOIN tbl_categories   c  ON pp.category_id = c.id
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

    public function cancelBooking(string $id): void
    {
        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

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

        $upd = $db->prepare("
            UPDATE tbl_bookings
            SET status = 'cancelled'
            WHERE id = ?
        ");
        $upd->execute([$id]);

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
                   pp.address,
                   pp.avg_rating,
                   pp.total_reviews,
                   pp.offers_home_service,
                   pp.is_approved,
                   c.name              AS category_name,
                   c.slug              AS category_slug,
                   u.first_name        AS provider_first,
                   u.last_name         AS provider_last
            FROM tbl_services s
            JOIN tbl_provider_profiles pp ON s.provider_id = pp.id
            JOIN tbl_users             u  ON pp.user_id    = u.id
            LEFT JOIN tbl_categories   c  ON pp.category_id = c.id
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

        require_once __DIR__ . '/../views/customer/service-detail.php';
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
        $db     = Database::getInstance();
        $userId = (int)$_SESSION['user_id'];
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $newFirst = trim($_POST['first_name'] ?? '');
            $newLast  = trim($_POST['last_name']  ?? '');
            $newPhone = trim($_POST['phone']      ?? '');
            $newEmail = strtolower(trim($_POST['email'] ?? ''));

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

            $db->prepare("UPDATE tbl_users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?")
               ->execute([$newFirst, $newLast, $newEmail, $newPhone ?: null, $userId]);

            $_SESSION['user_name']  = $newFirst;
            $_SESSION['user_email'] = $newEmail;

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
            header('Location: ' . BASE_URL . 'profile'); exit;
        }

        if ($action === 'change_password') {
            $currentPw = $_POST['current_password'] ?? '';
            $newPw     = $_POST['new_password']     ?? '';
            $confirmPw = $_POST['confirm_password'] ?? '';

            $errors = [];

            $stUser = $db->prepare("SELECT password_hash FROM tbl_users WHERE id = ? LIMIT 1");
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
}