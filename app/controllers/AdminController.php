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
            SELECT id, first_name, last_name, email, role, created_at
            FROM tbl_users
            ORDER BY created_at DESC LIMIT 8
        ")->fetchAll();

        require_once __DIR__ . '/../views/admin/dashboard.php';
    }

    public function bookings(): void
    {
        $bookings = $this->db->query("
            SELECT b.*,
                   cu.first_name AS cust_first, cu.last_name AS cust_last,
                   pu.first_name AS prov_first, pu.last_name AS prov_last,
                   s.name AS service_name
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
            SELECT id, first_name, last_name, email, role, is_verified, created_at
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
            "SELECT id, first_name, last_name, email, bio, profile_picture FROM tbl_users WHERE id = ?"
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
     * Handle profile updates
     */
    public function updateProfile(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'admin/profile');
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $success = false;
        $error = null;

        try {
            // Parse full name
            $fullName = trim($_POST['full_name'] ?? '');
            $names = explode(' ', $fullName, 2);
            $firstName = trim($names[0] ?? '');
            $lastName = trim($names[1] ?? '');

            if (!$firstName) {
                throw new Exception('First name is required');
            }

            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Valid email is required');
            }

            // Check if email is already used by another user
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM tbl_users WHERE email = ? AND id != ?"
            );
            $stmt->execute([$email, $userId]);
            $existingEmail = (int) $stmt->fetchColumn();

            if ($existingEmail > 0) {
                throw new Exception('Email is already in use');
            }

            $bio = trim($_POST['bio'] ?? '');
            if (strlen($bio) > 500) {
                throw new Exception('Bio must be 500 characters or less');
            }

            // Handle password change
            $passwordUpdate = '';
            $passwordParams = [];
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($newPassword || $confirmPassword || $currentPassword) {
                // If any password field is filled, validate all
                if (!$currentPassword) {
                    throw new Exception('Current password is required to set a new password');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new Exception('New passwords do not match');
                }

                if (strlen($newPassword) < 8) {
                    throw new Exception('Password must be at least 8 characters');
                }

                // Validate password strength
                if (!preg_match('/[A-Z]/', $newPassword) || 
                    !preg_match('/[0-9]/', $newPassword) || 
                    !preg_match('/[!@#$%^&*]/', $newPassword)) {
                    throw new Exception('Password must contain uppercase, number, and special character');
                }

                // Verify current password
                $stmt = $this->db->prepare(
                    "SELECT password FROM tbl_users WHERE id = ?"
                );
                $stmt->execute([$userId]);
                $currentUser = $stmt->fetch();

                if (!password_verify($currentPassword, $currentUser['password'])) {
                    throw new Exception('Current password is incorrect');
                }

                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $passwordUpdate = ', password = ?';
                $passwordParams = [$hashedPassword];
            }

            // Handle profile picture
            $profilePictureData = $_POST['profile_picture'] ?? '';
            $profilePictureUpdate = '';
            $pictureParams = [];

            if ($profilePictureData && strpos($profilePictureData, 'data:image') === 0) {
                // Decode base64 image
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $profilePictureData));
                $fileName = 'admin_' . $userId . '_' . time() . '.png';
                $uploadPath = __DIR__ . '/../../public/assets/img/profiles/' . $fileName;

                // Create directory if it doesn't exist
                if (!is_dir(dirname($uploadPath))) {
                    mkdir(dirname($uploadPath), 0755, true);
                }

                // Save the image
                if (file_put_contents($uploadPath, $imageData)) {
                    $profilePictureUrl = 'assets/img/profiles/' . $fileName;
                    $profilePictureUpdate = ', profile_picture = ?';
                    $pictureParams = [$profilePictureUrl];
                }
            }

            // Build update query
            $updateParts = [$firstName, $lastName, $email, $bio];
            $updateQuery = "UPDATE tbl_users SET first_name = ?, last_name = ?, email = ?, bio = ?";

            if ($passwordUpdate) {
                $updateQuery .= $passwordUpdate;
                $updateParts = array_merge($updateParts, $passwordParams);
            }

            if ($profilePictureUpdate) {
                $updateQuery .= $profilePictureUpdate;
                $updateParts = array_merge($updateParts, $pictureParams);
            }

            $updateQuery .= " WHERE id = ?";
            $updateParts[] = $userId;

            // Execute update
            $stmt = $this->db->prepare($updateQuery);
            $stmt->execute($updateParts);

            // Update session
            $_SESSION['user_name'] = $firstName . ' ' . $lastName;

            $success = true;
            $this->logAction('update_profile', 'admin', $userId, 'Admin updated their profile');

            // Redirect with success message
            header('Location: ' . BASE_URL . 'admin/profile?success=1');
            exit;

        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        // Load user data for display
        $stmt = $this->db->prepare(
            "SELECT id, first_name, last_name, email, bio, profile_picture FROM tbl_users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        require __DIR__ . '/../views/admin/profile.php';
    }
}