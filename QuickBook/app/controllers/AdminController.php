<?php
require_once __DIR__ . '/../models/User.php';

class AdminController
{
    private PDO $db;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->requireAdmin();
        $this->db = Database::getInstance();
    }

    // ── Security guard ────────────────────────────────────────
    private function requireAdmin(): void
    {
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            // Redirect non-admins away silently — no error message exposed
            $role = $_SESSION['user_role'] ?? '';
            $map  = ['provider' => 'provider/dashboard', 'customer' => 'dashboard'];
            header('Location: ' . BASE_URL . ($map[$role] ?? 'login'));
            exit;
        }
    }

    // ── admin/dashboard ───────────────────────────────────────
    public function dashboard(): void
    {
        // Platform-wide stats
        $totalUsers     = (int) $this->db->query("SELECT COUNT(*) FROM tbl_users")->fetchColumn();
        $totalProviders = (int) $this->db->query("SELECT COUNT(*) FROM tbl_users WHERE role = 'provider'")->fetchColumn();
        $totalCustomers = (int) $this->db->query("SELECT COUNT(*) FROM tbl_users WHERE role = 'customer'")->fetchColumn();
        $totalBookings  = (int) $this->db->query("SELECT COUNT(*) FROM tbl_bookings")->fetchColumn();
        $totalRevenue   = (float) $this->db->query("SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings WHERE status = 'completed'")->fetchColumn();
        $pendingBookings = (int) $this->db->query("SELECT COUNT(*) FROM tbl_bookings WHERE status = 'pending'")->fetchColumn();

        // Recent bookings (all providers)
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

        // Newest registered users
        $newUsers = $this->db->query("
            SELECT id, first_name, last_name, email, role, created_at
            FROM tbl_users
            ORDER BY created_at DESC LIMIT 8
        ")->fetchAll();

        require_once __DIR__ . '/../views/admin/dashboard.php';
    }

    // ── admin/bookings ────────────────────────────────────────
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
        $status = $_POST['status'] ?? '';
        $allowed = ['pending', 'confirmed', 'completed', 'cancelled', 'in_progress'];
        if (in_array($status, $allowed)) {
            $stmt = $this->db->prepare("UPDATE tbl_bookings SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }
        header('Location: ' . BASE_URL . 'admin/bookings'); exit;
    }

    // ── admin/providers ───────────────────────────────────────
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
        if ($action === 'approve') {
            $stmt = $this->db->prepare("UPDATE tbl_provider_profiles SET is_approved = 1 WHERE user_id = ?");
            $stmt->execute([$id]);
        } elseif ($action === 'suspend') {
            $stmt = $this->db->prepare("UPDATE tbl_provider_profiles SET is_approved = 0 WHERE user_id = ?");
            $stmt->execute([$id]);
        }
        header('Location: ' . BASE_URL . 'admin/providers'); exit;
    }

    // ── admin/users ───────────────────────────────────────────
    public function users(): void
    {
        $users = $this->db->query("
            SELECT id, first_name, last_name, email, role, is_verified, created_at
            FROM tbl_users
            ORDER BY created_at DESC
        ")->fetchAll();

        require_once __DIR__ . '/../views/admin/users.php';
    }

    // ── admin/reports ─────────────────────────────────────────
    public function reports(): void
    {
        require_once __DIR__ . '/../views/admin/reports.php';
    }
}