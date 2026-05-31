<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/NotificationHelper.php';
require_once __DIR__ . '/../../config/database.php';

class AdminController
{
    private PDO $db;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->requireAdmin();
        $this->db = Database::getInstance();
    }

    private function requireAdmin(): void
    {
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            $role = $_SESSION['user_role'] ?? '';
            $map  = ['provider' => 'provider/dashboard', 'customer' => 'dashboard'];
            header('Location: ' . BASE_URL . ($map[$role] ?? 'login'));
            exit;
        }
    }

    public function dashboard(): void
    {
        $totalUsers     = (int) $this->db->query("SELECT COUNT(*) FROM tbl_users")->fetchColumn();
        $totalProviders = (int) $this->db->query("SELECT COUNT(*) FROM tbl_users WHERE role = 'provider'")->fetchColumn();
        $totalCustomers = (int) $this->db->query("SELECT COUNT(*) FROM tbl_users WHERE role = 'customer'")->fetchColumn();
        $totalBookings  = (int) $this->db->query("SELECT COUNT(*) FROM tbl_bookings")->fetchColumn();
        $totalRevenue   = (float) $this->db->query("SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings WHERE status = 'completed'")->fetchColumn();
        $pendingBookings = (int) $this->db->query("SELECT COUNT(*) FROM tbl_bookings WHERE status = 'pending'")->fetchColumn();

        $recentBookings = $this->db->query("
            SELECT b.id, b.booking_date, b.status, b.total_amount, b.created_at,
                   cu.first_name AS cust_first, cu.last_name AS cust_last,
                   pu.first_name AS prov_first, pu.last_name AS prov_last,
                   s.name AS service_name
            FROM tbl_bookings b
            JOIN tbl_users cu ON cu.id = b.customer_id
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            JOIN tbl_users pu ON pu.id = pp.user_id
            JOIN tbl_services s ON s.id = b.service_id
            ORDER BY b.created_at DESC LIMIT 10
        ")->fetchAll();

        $newUsers = $this->db->query("
            SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.created_at,
                   pp.id AS provider_profile_id
            FROM tbl_users u
            LEFT JOIN tbl_provider_profiles pp ON pp.user_id = u.id AND u.role = 'provider'
            ORDER BY u.created_at DESC LIMIT 8
        ")->fetchAll();

        require_once __DIR__ . '/../views/admin/dashboard.php';
    }

    public function bookings(): void
    {
        $bookings = $this->db->query("
            SELECT b.*,
                   cu.first_name AS cust_first, cu.last_name AS cust_last,
                   pu.first_name AS prov_first, pu.last_name AS prov_last,
                   s.name AS service_name,
                   pp.business_name
            FROM tbl_bookings b
            JOIN tbl_users cu ON cu.id = b.customer_id
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            JOIN tbl_users pu ON pu.id = pp.user_id
            JOIN tbl_services s ON s.id = b.service_id
            ORDER BY b.created_at DESC
        ")->fetchAll();

        require_once __DIR__ . '/../views/admin/bookings.php';
    }

    public function updateBooking(string $id): void
    {
        $status  = $_POST['status'] ?? '';
        $allowed = ['pending', 'confirmed', 'completed', 'cancelled', 'in_progress'];

        if (!in_array($status, $allowed)) {
            header('Location: ' . BASE_URL . 'admin/bookings'); exit;
        }

        // Fetch booking + parties for notifications
        $stmt = $this->db->prepare("
            SELECT b.customer_id, b.booking_date, b.booking_time,
                   cu.first_name AS cust_first, cu.last_name AS cust_last,
                   pu.first_name AS prov_first, pu.last_name AS prov_last,
                   pu.id AS provider_user_id,
                   s.name AS service_name
            FROM tbl_bookings b
            JOIN tbl_users cu ON cu.id = b.customer_id
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            JOIN tbl_users pu ON pu.id = pp.user_id
            JOIN tbl_services s ON s.id = b.service_id
            WHERE b.id = ?
        ");
        $stmt->execute([(int)$id]);
        $b = $stmt->fetch();

        $this->db->prepare("UPDATE tbl_bookings SET status = ? WHERE id = ?")->execute([$status, $id]);

        if ($b) {
            $custName = trim($b['cust_first'] . ' ' . $b['cust_last']);
            $provName = trim($b['prov_first'] . ' ' . $b['prov_last']);
            $svcName  = $b['service_name'];
            $custId   = (int)$b['customer_id'];
            $provUId  = (int)$b['provider_user_id'];
            $label    = ucfirst(str_replace('_', ' ', $status));
            $fDate    = $b['booking_date'] ? date('M j, Y', strtotime($b['booking_date'])) : '';

            NotificationHelper::send($this->db, [$custId], 'booking',
                "Booking {$label}",
                "Your booking for \"{$svcName}\" on {$fDate} has been marked as {$label} by the administrator.",
                '',
                BASE_URL . 'bookings/' . (int)$id
            );
            NotificationHelper::send($this->db, [$provUId], 'booking',
                "[Admin] Booking {$label}",
                "Admin updated booking for \"{$svcName}\" (customer: {$custName}) to status: {$label}.",
                '',
                BASE_URL . 'provider/bookings/' . (int)$id
            );
        }

        $this->writeLog('update_booking_status', 'booking', (int)$id,
            "Status changed to {$status} for booking #{$id}");

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking status updated.'];
        header('Location: ' . BASE_URL . 'admin/bookings'); exit;
    }

    public function deleteBooking(string $id): void
    {
        $check = $this->db->prepare("SELECT id FROM tbl_bookings WHERE id = ? AND deleted_at IS NULL");
        $check->execute([(int)$id]);

        if (!$check->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found or already deleted.'];
            header('Location: ' . BASE_URL . 'admin/bookings'); exit;
        }

        $stmt = $this->db->prepare("
            SELECT b.customer_id, pu.id AS provider_user_id,
                   cu.first_name AS cust_first, cu.last_name AS cust_last,
                   s.name AS service_name, b.booking_date
            FROM tbl_bookings b
            JOIN tbl_users cu ON cu.id = b.customer_id
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            JOIN tbl_users pu ON pu.id = pp.user_id
            JOIN tbl_services s ON s.id = b.service_id
            WHERE b.id = ?
        ");
        $stmt->execute([(int)$id]);
        $b = $stmt->fetch();

        $this->db->prepare("
            UPDATE tbl_bookings SET status = 'cancelled', deleted_at = NOW() WHERE id = ?
        ")->execute([(int)$id]);

        if ($b) {
            $svcName = $b['service_name'];
            $fDate   = $b['booking_date'] ? date('M j, Y', strtotime($b['booking_date'])) : '';
            NotificationHelper::send($this->db, [(int)$b['customer_id']], 'booking_cancelled',
                'Booking Cancelled by Admin',
                "Your booking for \"{$svcName}\" on {$fDate} has been cancelled by the administrator.",
                '',
                BASE_URL . 'bookings'
            );
            NotificationHelper::send($this->db, [(int)$b['provider_user_id']], 'booking_cancelled',
                '[Admin] Booking Deleted',
                "Admin removed a booking for \"{$svcName}\" (customer: {$b['cust_first']} {$b['cust_last']}) on {$fDate}.",
                '',
                BASE_URL . 'provider/bookings'
            );
        }

        $this->writeLog('delete_booking', 'booking', (int)$id,
            "Booking #{$id} deleted by admin");

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Booking deleted successfully.'];
        header('Location: ' . BASE_URL . 'admin/bookings'); exit;
    }

    public function providers(): void
    {
        $providers = $this->db->query("
            SELECT u.id, u.first_name, u.last_name, u.email, u.created_at,
                   pp.id AS provider_profile_id,
                   pp.business_name, pp.is_approved,
                   COUNT(s.id) AS service_count
            FROM tbl_users u
            JOIN tbl_provider_profiles pp ON pp.user_id = u.id
            LEFT JOIN tbl_services s ON s.provider_id = pp.id
            WHERE u.role = 'provider'
            GROUP BY u.id, pp.id
            ORDER BY u.created_at DESC
        ")->fetchAll();

        require_once __DIR__ . '/../views/admin/providers.php';
    }

    public function updateProvider(string $id): void
    {
        $action = $_POST['action'] ?? '';

        $stmt = $this->db->prepare("SELECT first_name, last_name, email FROM tbl_users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $pu = $stmt->fetch();
        $provName = $pu ? trim($pu['first_name'] . ' ' . $pu['last_name']) : 'A provider';

        if ($action === 'approve') {
            $this->db->prepare("UPDATE tbl_provider_profiles SET is_approved = 1 WHERE user_id = ?")->execute([$id]);
            NotificationHelper::send($this->db, [(int)$id], 'system',
                'Account Approved!',
                'Congratulations! Your provider account has been approved by the administrator. You can now receive bookings.',
                '',
                BASE_URL . 'provider/dashboard'
            );
            $this->writeLog('approve_provider', 'user', (int)$id,
                "Provider {$provName} (ID #{$id}) approved");
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "{$provName}'s account has been approved."];

        } elseif ($action === 'suspend') {
            $this->db->prepare("UPDATE tbl_provider_profiles SET is_approved = 0 WHERE user_id = ?")->execute([$id]);
            NotificationHelper::send($this->db, [(int)$id], 'system',
                'Account Suspended',
                'Your provider account has been suspended by the administrator. Please contact support for assistance.',
                '',
                BASE_URL . 'provider/profile'
            );
            $this->writeLog('suspend_provider', 'user', (int)$id,
                "Provider {$provName} (ID #{$id}) suspended");
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "{$provName}'s account has been suspended."];
        }

        header('Location: ' . BASE_URL . 'admin/providers'); exit;
    }

    public function users(): void
    {
        $users = $this->db->query("
            SELECT id, first_name, last_name, email, role, is_verified, is_active, created_at
            FROM tbl_users
            ORDER BY created_at DESC
        ")->fetchAll();

        require_once __DIR__ . '/../views/admin/users.php';
    }

    public function reports(): void
    {
        require_once __DIR__ . '/../views/admin/reports.php';
    }

    public function logs(): void
    {
        // Create the log table if it doesn't exist yet
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tbl_admin_logs (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_id    INT UNSIGNED NOT NULL,
                action      VARCHAR(64)  NOT NULL,
                target_type VARCHAR(32)  DEFAULT NULL,
                target_id   INT UNSIGNED DEFAULT NULL,
                details     TEXT         DEFAULT NULL,
                ip_address  VARCHAR(45)  DEFAULT NULL,
                created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action     (action),
                INDEX idx_admin      (admin_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $logs = $this->db->query("
            SELECT l.id, l.action, l.target_type, l.target_id,
                   l.details, l.ip_address, l.created_at,
                   u.first_name, u.last_name
            FROM tbl_admin_logs l
            LEFT JOIN tbl_users u ON u.id = l.admin_id
            ORDER BY l.created_at DESC
            LIMIT 200
        ")->fetchAll();

        require_once __DIR__ . '/../views/admin/logs.php';
    }

    /* ── Private helper: write an audit log entry ─────────────── */
    private function writeLog(string $action, string $targetType, int $targetId, string $details = ''): void
    {
        try {
            // Ensure table exists
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS tbl_admin_logs (
                    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    admin_id    INT UNSIGNED NOT NULL,
                    action      VARCHAR(64)  NOT NULL,
                    target_type VARCHAR(32)  DEFAULT NULL,
                    target_id   INT UNSIGNED DEFAULT NULL,
                    details     TEXT         DEFAULT NULL,
                    ip_address  VARCHAR(45)  DEFAULT NULL,
                    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_action     (action),
                    INDEX idx_admin      (admin_id),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $adminId = (int)($_SESSION['user_id'] ?? 0);
            $ip      = $_SERVER['HTTP_X_FORWARDED_FOR']
                    ?? $_SERVER['REMOTE_ADDR']
                    ?? null;

            $stmt = $this->db->prepare("
                INSERT INTO tbl_admin_logs (admin_id, action, target_type, target_id, details, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$adminId, $action, $targetType, $targetId, $details, $ip]);
        } catch (Exception $e) {
            // Silently fail — logging should never break the main action
        }
    }

    /**
     * Display admin profile page
     */
    public function profile(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $this->db->prepare(
            "SELECT id, first_name, last_name, email, phone, gender, date_of_birth FROM tbl_users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            header('Location: ' . BASE_URL . 'admin/dashboard');
            exit;
        }

        require __DIR__ . '/../views/admin/profile.php';
    }

    /**
     * Handle profile update (modal form or direct POST)
     */
    public function updateProfile(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/profile');
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $phone     = trim($_POST['phone']      ?? '') ?: null;
        $gender    = in_array($_POST['gender'] ?? '', ['male','female','non_binary','prefer_not_to_say']) ? $_POST['gender'] : null;
        $dob       = $_POST['date_of_birth'] ?? null ?: null;

        if (!$firstName || !$lastName || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please fill in all required fields with valid data.'];
            header('Location: ' . BASE_URL . 'admin/profile');
            exit;
        }

        // Check email uniqueness (excluding self)
        $check = $this->db->prepare("SELECT id FROM tbl_users WHERE email = ? AND id != ?");
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'That email address is already in use.'];
            header('Location: ' . BASE_URL . 'admin/profile');
            exit;
        }

        $stmt = $this->db->prepare("
            UPDATE tbl_users
            SET first_name = ?, last_name = ?, email = ?, phone = ?, gender = ?, date_of_birth = ?
            WHERE id = ? AND role = 'admin'
        ");
        $stmt->execute([$firstName, $lastName, $email, $phone, $gender, $dob, $userId]);

        $_SESSION['user_name'] = trim($firstName . ' ' . $lastName);
        $this->writeLog('update_profile', 'user', $userId, 'Admin updated their own profile');

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
        header('Location: ' . BASE_URL . 'admin/profile');
        exit;
    }


    /**
     * Handle password change
     */
    public function changePassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/profile');
            exit;
        }

        $userId    = (int)($_SESSION['user_id'] ?? 0);
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password']     ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        $stmt = $this->db->prepare("SELECT password_hash FROM tbl_users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPw, $user['password_hash'])) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Current password is incorrect.'];
            header('Location: ' . BASE_URL . 'admin/profile'); exit;
        }
        if (strlen($newPw) < 8) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'New password must be at least 8 characters.'];
            header('Location: ' . BASE_URL . 'admin/profile'); exit;
        }
        if ($newPw !== $confirmPw) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'New passwords do not match.'];
            header('Location: ' . BASE_URL . 'admin/profile'); exit;
        }

        $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare("UPDATE tbl_users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);

        $this->writeLog('change_password', 'user', $userId, 'Admin changed their password');
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password changed successfully.'];
        header('Location: ' . BASE_URL . 'admin/profile'); exit;
    }

    /**
     * Handle avatar/profile photo upload
     */
    public function uploadAvatar(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        $jsonError = function(string $msg) use ($isAjax): void {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $msg]);
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => $msg];
                header('Location: ' . BASE_URL . 'admin/profile');
            }
            exit;
        };

        if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $jsonError('No file uploaded or upload error occurred.');
        }

        $file     = $_FILES['avatar'];
        $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mimeType = mime_content_type($file['tmp_name']);

        if (!isset($allowed[$mimeType])) {
            $jsonError('Only JPG, PNG, or WEBP images are allowed.');
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            $jsonError('Image must be under 3 MB.');
        }

        $base64 = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));
        $this->db->prepare("UPDATE tbl_users SET avatar_url = ? WHERE id = ? AND role = 'admin'")->execute([$base64, $userId]);

        $this->writeLog('upload_avatar', 'user', $userId, 'Admin updated profile photo');

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'avatar' => $base64]);
            exit;
        }

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile photo updated successfully.'];
        header('Location: ' . BASE_URL . 'admin/profile'); exit;
    }

    /**
     * Save notification preferences
     */
    public function saveNotificationPrefs(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $this->writeLog('update_notification_prefs', 'user', $userId, 'Admin updated notification preferences');
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Notification preferences saved.'];
        header('Location: ' . BASE_URL . 'admin/profile'); exit;
    }

    /**
     * Submit admin feedback
     */
    public function submitFeedback(): void
    {
        $userId  = (int)($_SESSION['user_id'] ?? 0);
        $type    = trim($_POST['feedback_type'] ?? '');
        $rating  = (int)($_POST['rating']       ?? 0);
        $message = trim($_POST['message']       ?? '');
        $area    = trim($_POST['area']          ?? '');

        if (empty($message)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please provide a feedback message.'];
            header('Location: ' . BASE_URL . 'admin/profile'); exit;
        }

        $details = "Type: {$type} | Area: {$area} | Rating: {$rating}/5 | " . mb_substr($message, 0, 200);
        $this->writeLog('admin_feedback', 'user', $userId, $details);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Thank you for your feedback!'];
        header('Location: ' . BASE_URL . 'admin/profile'); exit;
    }

    /**
     * Revoke all other sessions
     */
    public function revokeSessions(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $this->writeLog('revoke_sessions', 'user', $userId, 'Admin revoked all other sessions');
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'All other sessions have been signed out.'];
        header('Location: ' . BASE_URL . 'admin/profile'); exit;
    }

    /**
     * Deactivate/delete a user account (soft delete via is_active = 0)
     */
    public function deleteUser(string $id): void
    {
        $userId = (int)$id;
        $stmt   = $this->db->prepare("SELECT first_name, last_name, role FROM tbl_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || $user['role'] === 'admin') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'User not found or cannot deactivate an admin.'];
            header('Location: ' . BASE_URL . 'admin/users'); exit;
        }

        $this->db->prepare("UPDATE tbl_users SET is_active = 0 WHERE id = ?")->execute([$userId]);

        $name = trim($user['first_name'] . ' ' . $user['last_name']);
        $this->writeLog('delete_user', 'user', $userId, "User {$name} (ID #{$userId}) deactivated");

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "{$name}'s account has been deactivated."];
        $redirect = $user['role'] === 'provider' ? 'admin/providers' : 'admin/users';
        header('Location: ' . BASE_URL . $redirect); exit;
    }

    /* ──────────────────────────────────────────────────────────────
       FEEDBACK MANAGEMENT
    ────────────────────────────────────────────────────────────── */

    /**
     * Display the feedback management page
     */
    public function feedback(): void
    {
        require_once __DIR__ . '/../views/admin/feedback.php';
    }

    /**
     * Toggle a review's visibility (hide / restore)
     */
    public function toggleReview(string $id): void
    {
        $reviewId = (int)$id;
        $stmt = $this->db->prepare("SELECT is_visible FROM tbl_reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
        $row = $stmt->fetch();

        if (!$row) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Review not found.'];
            header('Location: ' . BASE_URL . 'admin/feedback?tab=reviews'); exit;
        }

        $newVis = $row['is_visible'] ? 0 : 1;
        $this->db->prepare("UPDATE tbl_reviews SET is_visible = ? WHERE id = ?")->execute([$newVis, $reviewId]);

        $action = $newVis ? 'restored' : 'hidden';
        $this->writeLog('toggle_review', 'review', $reviewId, "Review #{$reviewId} {$action}");

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Review ' . $action . ' successfully.'];
        header('Location: ' . BASE_URL . 'admin/feedback?tab=reviews'); exit;
    }

    /**
     * Update app-feedback status
     */
    public function updateFeedback(string $id): void
    {
        $fbId   = (int)$id;
        $status = $_POST['status'] ?? '';
        $allowed = ['open', 'reviewed', 'resolved', 'dismissed'];

        if (!in_array($status, $allowed)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid status.'];
            header('Location: ' . BASE_URL . 'admin/feedback?tab=feedback'); exit;
        }

        $this->db->prepare("UPDATE tbl_app_feedback SET status = ? WHERE id = ?")->execute([$status, $fbId]);
        $this->writeLog('update_feedback_status', 'feedback', $fbId, "Status set to {$status}");

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Feedback status updated.'];
        header('Location: ' . BASE_URL . 'admin/feedback?tab=feedback'); exit;
    }

    /**
     * Save admin note on app feedback
     */
    public function noteFeedback(string $id): void
    {
        $fbId = (int)$id;
        $note = trim($_POST['admin_note'] ?? '');

        $this->db->prepare("UPDATE tbl_app_feedback SET admin_note = ? WHERE id = ?")->execute([$note ?: null, $fbId]);
        $this->writeLog('note_feedback', 'feedback', $fbId, 'Admin note updated');

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Note saved.'];
        header('Location: ' . BASE_URL . 'admin/feedback?tab=feedback'); exit;
    }

    /**
     * Delete an app-feedback item
     */
    public function deleteFeedback(string $id): void
    {
        $fbId = (int)$id;
        $this->db->prepare("DELETE FROM tbl_app_feedback WHERE id = ?")->execute([$fbId]);
        $this->writeLog('delete_feedback', 'feedback', $fbId, "Feedback #{$fbId} deleted");

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Feedback deleted.'];
        header('Location: ' . BASE_URL . 'admin/feedback?tab=feedback'); exit;
    }

    /**
     * Delete a provider review reply
     */
    public function deleteReply(string $id): void
    {
        $replyId = (int)$id;
        $this->db->prepare("DELETE FROM tbl_review_replies WHERE id = ?")->execute([$replyId]);
        $this->writeLog('delete_reply', 'review_reply', $replyId, "Reply #{$replyId} deleted by admin");

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Provider reply removed.'];
        // Redirect back to whichever tab invoked this
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $tab = str_contains($ref, 'tab=replies') ? 'replies' : 'reviews';
        header('Location: ' . BASE_URL . 'admin/feedback?tab=' . $tab); exit;
    }

    /**
     * Edit a provider review reply
     */
    public function editReply(string $id): void
    {
        $replyId = (int)$id;
        $reply   = trim($_POST['reply'] ?? '');

        if (!$reply) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Reply cannot be empty.'];
            header('Location: ' . BASE_URL . 'admin/feedback?tab=replies'); exit;
        }

        $this->db->prepare("UPDATE tbl_review_replies SET reply = ? WHERE id = ?")->execute([$reply, $replyId]);
        $this->writeLog('edit_reply', 'review_reply', $replyId, "Reply #{$replyId} edited by admin");

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Reply updated successfully.'];
        header('Location: ' . BASE_URL . 'admin/feedback?tab=replies'); exit;
    }

    /**
     * Toggle user active/inactive status
     */
    public function toggleUser(string $id): void
    {
        $userId = (int)$id;
        $stmt   = $this->db->prepare("SELECT first_name, last_name, role, is_active FROM tbl_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || $user['role'] === 'admin') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'User not found or cannot modify an admin.'];
            header('Location: ' . BASE_URL . 'admin/users'); exit;
        }

        $newStatus = $user['is_active'] ? 0 : 1;
        $this->db->prepare("UPDATE tbl_users SET is_active = ? WHERE id = ?")->execute([$newStatus, $userId]);

        $name   = trim($user['first_name'] . ' ' . $user['last_name']);
        $action = $newStatus ? 'activated' : 'deactivated';
        $this->writeLog('toggle_user', 'user', $userId, "User {$name} (ID #{$userId}) {$action}");

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "{$name}'s account has been {$action}."];
        $redirect = $user['role'] === 'provider' ? 'admin/providers' : 'admin/users';
        header('Location: ' . BASE_URL . $redirect); exit;
    }

}