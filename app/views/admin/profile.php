<?php
// app/views/admin/profile.php
// Admin Profile Page — QuickBook Admin Design System
// Cream & Gold · Playfair Display + DM Sans + DM Mono

if (session_status() === PHP_SESSION_NONE) session_start();

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
$hour      = (int)date('G');
$greet     = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

/* ─── Admin user record ─── */
$stAdmin = $db->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.gender,
           u.date_of_birth, u.avatar_url, u.is_active, u.is_verified,
           u.totp_enabled, u.created_at, u.updated_at
    FROM tbl_users u
    WHERE u.id = ? AND u.role = 'admin'
");
$stAdmin->execute([$adminId]);
$admin = $stAdmin->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    // Fallback for demo / missing session
    $admin = [
        'id'           => $adminId,
        'first_name'   => 'Admin',
        'last_name'    => 'User',
        'email'        => 'admin@quickbook.com',
        'phone'        => null,
        'gender'       => null,
        'date_of_birth'=> null,
        'avatar_url'   => null,
        'is_active'    => 1,
        'is_verified'  => 1,
        'totp_enabled' => 0,
        'created_at'   => date('Y-m-d H:i:s'),
        'updated_at'   => date('Y-m-d H:i:s'),
    ];
}

$fullName = trim(htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']));
$initials = strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1));
$memberSince = $admin['created_at'] ? date('F Y', strtotime($admin['created_at'])) : '—';

/* ─── Platform-wide stats (admin overview) ─── */
$totalUsers     = (int)$db->query("SELECT COUNT(*) FROM tbl_users")->fetchColumn();
$totalBookings  = (int)$db->query("SELECT COUNT(*) FROM tbl_bookings")->fetchColumn();
$totalProviders = (int)$db->query("SELECT COUNT(*) FROM tbl_users WHERE role='provider'")->fetchColumn();
$totalRevenue   = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings WHERE status='completed'")->fetchColumn();

/* ─── Recent activity logs for this admin ─── */
$stLogs = $db->prepare("
    SELECT action, target_type, target_id, details, ip_address, created_at
    FROM tbl_admin_logs
    WHERE admin_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stLogs->execute([$adminId]);
$activityLogs = $stLogs->fetchAll(PDO::FETCH_ASSOC);

/* ─── Summary counts for recent activity ─── */
$stSummary = $db->prepare("
    SELECT
        SUM(action = 'admin_login')         AS total_logins,
        SUM(action = 'approve_provider')    AS total_approvals,
        SUM(action = 'suspend_provider')    AS total_suspensions,
        SUM(action LIKE 'update_%')         AS total_updates,
        MAX(created_at)                     AS last_login
    FROM tbl_admin_logs
    WHERE admin_id = ?
");
$stSummary->execute([$adminId]);
$summary = $stSummary->fetch(PDO::FETCH_ASSOC);
$lastLogin = $summary['last_login'] ? date('M j, Y · g:i A', strtotime($summary['last_login'])) : 'Never';

/* ─── Flash message ─── */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ─── Action icons map ─── */
function actionIcon(string $action): array {
    $map = [
        'admin_login'       => ['fa-right-to-bracket', 'blue'],
        'approve_provider'  => ['fa-circle-check',     'green'],
        'suspend_provider'  => ['fa-ban',               'red'],
        'update_booking_status' => ['fa-pen-to-square', 'yellow'],
        'delete_booking'    => ['fa-trash',             'red'],
        'delete_user'       => ['fa-user-xmark',        'red'],
    ];
    foreach ($map as $key => $val) {
        if (str_contains($action, $key) || $action === $key)
            return $val;
    }
    return ['fa-bolt', 'gold'];
}

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)       return 'just now';
    if ($diff < 3600)     return floor($diff/60)   . 'm ago';
    if ($diff < 86400)    return floor($diff/3600)  . 'h ago';
    if ($diff < 2592000)  return floor($diff/86400) . 'd ago';
    return date('M j', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Profile — QuickBook</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_dashboard.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_profile.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="grain"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('profile'); ?>

<!-- ══════════════ HERO ══════════════ -->
<div class="admin-hero aprof-hero">
  <div class="admin-hero-overlay"></div>
  <div class="admin-hero-inner">
    <div>
      <div class="admin-hero-eyebrow">
        <span class="admin-dot-pulse"></span><?= $greet ?>
      </div>
      <h1 class="admin-hero-name"><?= $fullName ?></h1>
      <div class="admin-hero-date"><?= date('l, F j, Y') ?></div>
      <div class="admin-hero-meta">
        <span class="admin-status-badge">
          <span class="admin-status-dot"></span>Super Administrator
        </span>
        <span class="aprof-since-badge">
          <i class="fa-solid fa-calendar-check"></i>Member since <?= $memberSince ?>
        </span>
      </div>
    </div>

  </div>

  <!-- Stat strip -->
  <div class="admin-hero-stats">
    <div class="admin-hs-item">
      <div class="admin-hs-val accent"><?= (int)($summary['total_logins'] ?? 0) ?></div>
      <div class="admin-hs-label">Total Logins</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val green"><?= (int)($summary['total_approvals'] ?? 0) ?></div>
      <div class="admin-hs-label">Approvals</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val yellow"><?= (int)($summary['total_suspensions'] ?? 0) ?></div>
      <div class="admin-hs-label">Suspensions</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val blue"><?= (int)($summary['total_updates'] ?? 0) ?></div>
      <div class="admin-hs-label">Updates</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val" style="font-size:.85rem"><?= $lastLogin ?></div>
      <div class="admin-hs-label">Last Login</div>
    </div>
  </div>
</div><!-- /admin-hero -->

<!-- ══════════════ FLASH ══════════════ -->
<?php if ($flash): ?>
<div class="aprof-flash aprof-flash--<?= htmlspecialchars($flash['type']) ?>">
  <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
  <?= htmlspecialchars($flash['msg']) ?>
  <button class="aprof-flash-close" onclick="this.parentElement.remove()">
    <i class="fa-solid fa-xmark"></i>
  </button>
</div>
<?php endif; ?>

<!-- ══════════════ PAGE ══════════════ -->
<div class="admin-pv-page">

  <!-- Tab nav -->
  <div class="aprof-tab-nav">
    <button class="aprof-tab is-active" data-tab="profile">
      <i class="fa-solid fa-user-circle"></i> Profile
    </button>
    <button class="aprof-tab" data-tab="security">
      <i class="fa-solid fa-shield-halved"></i> Security
    </button>
    <button class="aprof-tab" data-tab="notifications">
      <i class="fa-solid fa-bell"></i> Notifications
    </button>
    <button class="aprof-tab" data-tab="logs">
      <i class="fa-solid fa-scroll"></i> Activity Logs
    </button>
  </div>

  <!-- ════════ TAB: PROFILE ════════ -->
  <div class="aprof-tab-panel is-active" id="tab-profile">
    <div class="admin-layout">

      <!-- LEFT: Profile Info Card -->
      <div class="admin-main">

        <!-- Avatar + info hero card -->
        <div class="admin-card aprof-id-card">
          <div class="aprof-id-card-bg"></div>
          <div class="aprof-id-card-body">
            <!-- Avatar -->
            <div class="aprof-avatar-wrap">
              <?php if (!empty($admin['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($admin['avatar_url']) ?>" alt="<?= $fullName ?>" class="aprof-avatar-img" id="adminAvatarImg">
              <?php else: ?>
                <div class="aprof-avatar-initials" id="adminAvatarInitials"><?= $initials ?></div>
              <?php endif; ?>
              <div class="aprof-avatar-ring"></div>
              <span class="aprof-avatar-status <?= $admin['is_active'] ? 'online' : 'offline' ?>"></span>
              <!-- Camera overlay -->
              <label class="aprof-av-upload-btn" for="avatarFileInput" title="Change profile photo">
                <i class="fa-solid fa-camera"></i>
              </label>
              <form method="POST" action="<?= BASE_URL ?>admin/profile/upload-avatar" enctype="multipart/form-data" id="avatarUploadForm" style="display:none">
                <input type="file" id="avatarFileInput" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none">
              </form>
            </div>

            <!-- Name + role badges -->
            <div class="aprof-id-info">
              <h2 class="aprof-id-name"><?= $fullName ?></h2>
              <div class="aprof-id-badges">
                <span class="aprof-badge aprof-badge--<?= $admin['is_active'] ? 'active' : 'inactive' ?>">
                  <?= $admin['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </div>
              <div class="aprof-id-meta">
                <span><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($admin['email']) ?></span>
                <?php if (!empty($admin['phone'])): ?>
                <span><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($admin['phone']) ?></span>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>

        <!-- Profile Details -->
        <div class="admin-card">
          <div class="admin-card-head">
            <h2>Account Information</h2>
            <button class="aprof-btn aprof-btn--xs" onclick="openEditModal()">
              <i class="fa-solid fa-pen"></i> Edit
            </button>
          </div>
          <div class="aprof-detail-grid">
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">First Name</div>
              <div class="aprof-detail-value"><?= htmlspecialchars($admin['first_name']) ?></div>
            </div>
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">Last Name</div>
              <div class="aprof-detail-value"><?= htmlspecialchars($admin['last_name']) ?></div>
            </div>
            <div class="aprof-detail-item aprof-detail-item--wide">
              <div class="aprof-detail-label">Email Address</div>
              <div class="aprof-detail-value">
                <?= htmlspecialchars($admin['email']) ?>
                <?php if ($admin['is_verified']): ?>
                  <span class="aprof-verified-chip"><i class="fa-solid fa-check"></i> Verified</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">Phone</div>
              <div class="aprof-detail-value"><?= !empty($admin['phone']) ? htmlspecialchars($admin['phone']) : '<span class="aprof-empty">—</span>' ?></div>
            </div>
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">Gender</div>
              <div class="aprof-detail-value"><?= !empty($admin['gender']) ? ucfirst(str_replace('_', ' ', $admin['gender'])) : '<span class="aprof-empty">—</span>' ?></div>
            </div>
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">Date of Birth</div>
              <div class="aprof-detail-value"><?= !empty($admin['date_of_birth']) ? date('F j, Y', strtotime($admin['date_of_birth'])) : '<span class="aprof-empty">—</span>' ?></div>
            </div>
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">Account Role</div>
              <div class="aprof-detail-value">
                <span class="aprof-badge aprof-badge--admin"><i class="fa-solid fa-crown"></i> Super Administrator</span>
              </div>
            </div>
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">Account Status</div>
              <div class="aprof-detail-value">
                <span class="aprof-badge aprof-badge--<?= $admin['is_active'] ? 'active' : 'inactive' ?>">
                  <?= $admin['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </div>
            </div>
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">Member Since</div>
              <div class="aprof-detail-value"><?= $memberSince ?></div>
            </div>
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">Last Updated</div>
              <div class="aprof-detail-value"><?= $admin['updated_at'] ? date('M j, Y', strtotime($admin['updated_at'])) : '—' ?></div>
            </div>
            <div class="aprof-detail-item">
              <div class="aprof-detail-label">Two-Factor Auth</div>
              <div class="aprof-detail-value">
                <?php if ($admin['totp_enabled']): ?>
                  <span class="aprof-badge aprof-badge--active"><i class="fa-solid fa-shield-halved"></i> Enabled</span>
                <?php else: ?>
                  <span class="aprof-badge aprof-badge--warn"><i class="fa-solid fa-shield-xmark"></i> Disabled</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Platform Overview (admin-only visibility) -->
        <div class="admin-card">
          <div class="admin-card-head">
            <h2>Platform Overview</h2>
            <span class="aprof-eyebrow-chip">Admin View</span>
          </div>
          <div class="aprof-platform-grid">
            <?php
            $totalCustomers = (int)$db->query("SELECT COUNT(*) FROM tbl_users WHERE role='customer'")->fetchColumn();
            $pendingBookings = (int)$db->query("SELECT COUNT(*) FROM tbl_bookings WHERE status='pending'")->fetchColumn();
            $completedBookings = (int)$db->query("SELECT COUNT(*) FROM tbl_bookings WHERE status='completed'")->fetchColumn();
            $avgRating = (float)$db->query("SELECT ROUND(AVG(rating),1) FROM tbl_reviews")->fetchColumn();
            $totalReviews = (int)$db->query("SELECT COUNT(*) FROM tbl_reviews")->fetchColumn();
            $totalCategories = (int)$db->query("SELECT COUNT(*) FROM tbl_categories WHERE is_active=1")->fetchColumn();
            $totalLoyaltyPts = (int)$db->query("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE type='earn'")->fetchColumn();
            $pendingProviders = (int)$db->query("SELECT COUNT(*) FROM tbl_provider_profiles WHERE is_approved=0")->fetchColumn();
            ?>
            <div class="aprof-stat-tile">
              <div class="aprof-stat-icon" style="background:rgba(37,99,235,.10);color:#2563EB">
                <i class="fa-solid fa-users"></i>
              </div>
              <div class="aprof-stat-body">
                <div class="aprof-stat-val"><?= number_format($totalCustomers) ?></div>
                <div class="aprof-stat-lbl">Customers</div>
              </div>
            </div>
            <div class="aprof-stat-tile">
              <div class="aprof-stat-icon" style="background:rgba(201,168,76,.12);color:var(--gold-dim)">
                <i class="fa-solid fa-store"></i>
              </div>
              <div class="aprof-stat-body">
                <div class="aprof-stat-val"><?= number_format($totalProviders) ?></div>
                <div class="aprof-stat-lbl">Providers</div>
              </div>
            </div>
            <div class="aprof-stat-tile">
              <div class="aprof-stat-icon" style="background:rgba(217,119,6,.09);color:#D97706">
                <i class="fa-solid fa-clock"></i>
              </div>
              <div class="aprof-stat-body">
                <div class="aprof-stat-val"><?= number_format($pendingBookings) ?></div>
                <div class="aprof-stat-lbl">Pending Bookings</div>
              </div>
            </div>
            <div class="aprof-stat-tile">
              <div class="aprof-stat-icon" style="background:rgba(22,163,74,.09);color:#16A34A">
                <i class="fa-solid fa-calendar-check"></i>
              </div>
              <div class="aprof-stat-body">
                <div class="aprof-stat-val"><?= number_format($completedBookings) ?></div>
                <div class="aprof-stat-lbl">Completed</div>
              </div>
            </div>
            <div class="aprof-stat-tile">
              <div class="aprof-stat-icon" style="background:rgba(249,115,22,.09);color:#EA580C">
                <i class="fa-solid fa-star"></i>
              </div>
              <div class="aprof-stat-body">
                <div class="aprof-stat-val"><?= $avgRating ?: '—' ?></div>
                <div class="aprof-stat-lbl">Avg. Rating (<?= number_format($totalReviews) ?> reviews)</div>
              </div>
            </div>
            <div class="aprof-stat-tile">
              <div class="aprof-stat-icon" style="background:rgba(124,58,237,.09);color:#7C3AED">
                <i class="fa-solid fa-gem"></i>
              </div>
              <div class="aprof-stat-body">
                <div class="aprof-stat-val"><?= number_format($totalLoyaltyPts) ?></div>
                <div class="aprof-stat-lbl">Loyalty Points (all users)</div>
              </div>
            </div>
            <div class="aprof-stat-tile">
              <div class="aprof-stat-icon" style="background:rgba(20,184,166,.09);color:#0D9488">
                <i class="fa-solid fa-tags"></i>
              </div>
              <div class="aprof-stat-body">
                <div class="aprof-stat-val"><?= $totalCategories ?></div>
                <div class="aprof-stat-lbl">Active Categories</div>
              </div>
            </div>
            <div class="aprof-stat-tile <?= $pendingProviders > 0 ? 'aprof-stat-tile--warn' : '' ?>">
              <div class="aprof-stat-icon" style="background:rgba(220,38,38,.09);color:#DC2626">
                <i class="fa-solid fa-hourglass-half"></i>
              </div>
              <div class="aprof-stat-body">
                <div class="aprof-stat-val"><?= $pendingProviders ?></div>
                <div class="aprof-stat-lbl">Pending Provider Approvals</div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /admin-main -->

      <!-- SIDEBAR -->
      <div class="admin-sidebar">

        <!-- Quick Access -->
        <div class="admin-card">
          <div class="admin-card-head"><h2>Quick Access</h2></div>
          <div class="aprof-quick-links">
            <a href="<?= BASE_URL ?>admin/dashboard" class="aprof-quick-link">
              <span class="aprof-ql-icon" style="background:rgba(201,168,76,.12);color:var(--gold-dim)">
                <i class="fa-solid fa-gauge-high"></i>
              </span>
              <span class="aprof-ql-label">Dashboard</span>
              <i class="fa-solid fa-chevron-right aprof-ql-arrow"></i>
            </a>
            <a href="<?= BASE_URL ?>admin/bookings" class="aprof-quick-link">
              <span class="aprof-ql-icon" style="background:rgba(37,99,235,.09);color:#2563EB">
                <i class="fa-solid fa-calendar-check"></i>
              </span>
              <span class="aprof-ql-label">Bookings</span>
              <?php if ($pendingBookings > 0): ?>
              <span class="aprof-ql-badge"><?= $pendingBookings ?></span>
              <?php endif; ?>
              <i class="fa-solid fa-chevron-right aprof-ql-arrow"></i>
            </a>
            <a href="<?= BASE_URL ?>admin/providers" class="aprof-quick-link">
              <span class="aprof-ql-icon" style="background:rgba(16,185,129,.09);color:#059669">
                <i class="fa-solid fa-store"></i>
              </span>
              <span class="aprof-ql-label">Providers</span>
              <?php if ($pendingProviders > 0): ?>
              <span class="aprof-ql-badge aprof-ql-badge--red"><?= $pendingProviders ?></span>
              <?php endif; ?>
              <i class="fa-solid fa-chevron-right aprof-ql-arrow"></i>
            </a>
            <a href="<?= BASE_URL ?>admin/users" class="aprof-quick-link">
              <span class="aprof-ql-icon" style="background:rgba(124,58,237,.09);color:#7C3AED">
                <i class="fa-solid fa-users"></i>
              </span>
              <span class="aprof-ql-label">Users</span>
              <i class="fa-solid fa-chevron-right aprof-ql-arrow"></i>
            </a>
            <a href="<?= BASE_URL ?>admin/reports" class="aprof-quick-link">
              <span class="aprof-ql-icon" style="background:rgba(217,119,6,.09);color:#D97706">
                <i class="fa-solid fa-chart-bar"></i>
              </span>
              <span class="aprof-ql-label">Reports</span>
              <i class="fa-solid fa-chevron-right aprof-ql-arrow"></i>
            </a>
            <a href="<?= BASE_URL ?>admin/logs" class="aprof-quick-link">
              <span class="aprof-ql-icon" style="background:rgba(220,38,38,.09);color:#DC2626">
                <i class="fa-solid fa-scroll"></i>
              </span>
              <span class="aprof-ql-label">System Logs</span>
              <i class="fa-solid fa-chevron-right aprof-ql-arrow"></i>
            </a>
          </div>
        </div>

        <!-- Recent Activity (sidebar mini) -->
        <div class="admin-card">
          <div class="admin-card-head">
            <h2>Recent Activity</h2>
            <button class="admin-card-link" onclick="switchTab('logs')">View all</button>
          </div>
          <div class="aprof-mini-log">
            <?php if (empty($activityLogs)): ?>
            <div class="aprof-empty-state">
              <i class="fa-solid fa-scroll"></i>
              <span>No activity yet</span>
            </div>
            <?php else: foreach (array_slice($activityLogs, 0, 7) as $log):
              [$ico, $col] = actionIcon($log['action']);
            ?>
            <div class="aprof-mini-log-item">
              <span class="aprof-log-ico aprof-log-ico--<?= $col ?>">
                <i class="fa-solid <?= $ico ?>"></i>
              </span>
              <div class="aprof-log-body">
                <div class="aprof-log-action"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))) ?></div>
                <?php if ($log['details']): ?>
                <div class="aprof-log-detail"><?= htmlspecialchars($log['details']) ?></div>
                <?php endif; ?>
              </div>
              <div class="aprof-log-time"><?= timeAgo($log['created_at']) ?></div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

      </div><!-- /admin-sidebar -->
    </div><!-- /admin-layout -->
  </div><!-- /tab-profile -->

  <!-- ════════ TAB: SECURITY ════════ -->
  <div class="aprof-tab-panel" id="tab-security">
    <div class="admin-layout">
      <div class="admin-main">

        <!-- Change Password -->
        <div class="admin-card">
          <div class="admin-card-head">
            <h2>Change Password</h2>
            <span class="aprof-eyebrow-chip"><i class="fa-solid fa-lock"></i> Secure</span>
          </div>
          <div class="aprof-section-body">
            <p class="aprof-section-desc">Choose a strong password to protect your administrator account. Use at least 8 characters including uppercase, lowercase, numbers, and symbols.</p>
            <form method="POST" action="<?= BASE_URL ?>admin/profile/change-password" class="aprof-form" id="pwForm">
              <div class="aprof-form-row">
                <div class="aprof-form-group">
                  <label class="aprof-label">Current Password</label>
                  <div class="aprof-input-wrap">
                    <i class="fa-solid fa-lock aprof-input-icon"></i>
                    <input type="password" name="current_password" class="aprof-input" placeholder="Enter current password" required>
                    <button type="button" class="aprof-eye-btn" onclick="togglePw(this)">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div class="aprof-form-row aprof-form-row--2col">
                <div class="aprof-form-group">
                  <label class="aprof-label">New Password</label>
                  <div class="aprof-input-wrap">
                    <i class="fa-solid fa-key aprof-input-icon"></i>
                    <input type="password" name="new_password" id="newPw" class="aprof-input" placeholder="New password" required oninput="checkPwStrength(this.value)">
                    <button type="button" class="aprof-eye-btn" onclick="togglePw(this)">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                  </div>
                  <div class="aprof-pw-strength" id="pwStrength">
                    <div class="aprof-pw-bar"><div class="aprof-pw-fill" id="pwFill"></div></div>
                    <span id="pwLabel">—</span>
                  </div>
                </div>
                <div class="aprof-form-group">
                  <label class="aprof-label">Confirm New Password</label>
                  <div class="aprof-input-wrap">
                    <i class="fa-solid fa-key aprof-input-icon"></i>
                    <input type="password" name="confirm_password" class="aprof-input" placeholder="Confirm new password" required>
                    <button type="button" class="aprof-eye-btn" onclick="togglePw(this)">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div class="aprof-pw-rules">
                <div class="aprof-pw-rule" id="rule-len"><i class="fa-solid fa-circle-dot"></i> At least 8 characters</div>
                <div class="aprof-pw-rule" id="rule-upper"><i class="fa-solid fa-circle-dot"></i> Uppercase letter</div>
                <div class="aprof-pw-rule" id="rule-num"><i class="fa-solid fa-circle-dot"></i> Number</div>
                <div class="aprof-pw-rule" id="rule-sym"><i class="fa-solid fa-circle-dot"></i> Symbol (!@#$…)</div>
              </div>
              <div class="aprof-form-actions">
                <button type="submit" class="aprof-btn aprof-btn--primary">
                  <i class="fa-solid fa-floppy-disk"></i> Update Password
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Two-Factor Authentication -->
        <div class="admin-card">
          <div class="admin-card-head">
            <h2>Two-Factor Authentication</h2>
            <span class="aprof-badge <?= $admin['totp_enabled'] ? 'aprof-badge--active' : 'aprof-badge--warn' ?>">
              <?= $admin['totp_enabled'] ? '<i class="fa-solid fa-shield-halved"></i> Enabled' : '<i class="fa-solid fa-shield-xmark"></i> Disabled' ?>
            </span>
          </div>
          <div class="aprof-section-body">
            <div class="aprof-2fa-status <?= $admin['totp_enabled'] ? 'aprof-2fa-status--on' : 'aprof-2fa-status--off' ?>">
              <div class="aprof-2fa-icon">
                <i class="fa-solid <?= $admin['totp_enabled'] ? 'fa-shield-halved' : 'fa-shield-xmark' ?>"></i>
              </div>
              <div class="aprof-2fa-body">
                <div class="aprof-2fa-title">
                  <?= $admin['totp_enabled'] ? 'Two-Factor Authentication is Active' : 'Two-Factor Authentication is Disabled' ?>
                </div>
                <div class="aprof-2fa-desc">
                  <?= $admin['totp_enabled']
                    ? 'Your account is protected with TOTP-based 2FA. Each login requires your authenticator app code.'
                    : 'Enable 2FA to add an extra layer of protection. You\'ll need an authenticator app like Google Authenticator.' ?>
                </div>
              </div>
            </div>
            <?php if ($admin['totp_enabled']): ?>
            <div class="aprof-form-actions">
              <a href="<?= BASE_URL ?>auth/2fa/disable" class="aprof-btn aprof-btn--danger">
                <i class="fa-solid fa-shield-xmark"></i> Disable 2FA
              </a>
            </div>
            <?php else: ?>
            <div class="aprof-form-actions">
              <a href="<?= BASE_URL ?>auth/2fa/setup" class="aprof-btn aprof-btn--primary">
                <i class="fa-solid fa-shield-halved"></i> Enable 2FA
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /admin-main -->

      <!-- SIDEBAR -->
      <div class="admin-sidebar">

        <!-- Active Sessions -->
        <div class="admin-card">
          <div class="admin-card-head"><h2>Login Sessions</h2></div>
          <div class="aprof-section-body">
            <div class="aprof-session-item aprof-session-item--current">
              <div class="aprof-session-icon">
                <i class="fa-solid fa-desktop"></i>
              </div>
              <div class="aprof-session-info">
                <div class="aprof-session-device">This Device</div>
                <div class="aprof-session-meta">Current session · Active now</div>
                <div class="aprof-session-ip">
                  <i class="fa-solid fa-location-dot"></i>
                  <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '::1') ?>
                </div>
              </div>
              <span class="aprof-badge aprof-badge--active">Current</span>
            </div>
            <div class="aprof-form-actions" style="margin-top:1rem">
              <button class="aprof-btn aprof-btn--danger aprof-btn--sm" onclick="if(confirm('Sign out all other sessions?')) window.location = '<?= BASE_URL ?>admin/profile/revoke-sessions'">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Revoke All Others
              </button>
            </div>
          </div>
        </div>

        <!-- Security Tips -->
        <div class="admin-card aprof-tips-card">
          <div class="admin-card-head"><h2>Security Checklist</h2></div>
          <div class="aprof-tips-list">
            <div class="aprof-tip <?= $admin['totp_enabled'] ? 'aprof-tip--done' : 'aprof-tip--warn' ?>">
              <i class="fa-solid <?= $admin['totp_enabled'] ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
              <span>Two-Factor Authentication</span>
            </div>
            <div class="aprof-tip <?= $admin['is_verified'] ? 'aprof-tip--done' : 'aprof-tip--warn' ?>">
              <i class="fa-solid <?= $admin['is_verified'] ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
              <span>Email Verified</span>
            </div>
            <div class="aprof-tip aprof-tip--done">
              <i class="fa-solid fa-circle-check"></i>
              <span>Strong Password Policy</span>
            </div>
            <div class="aprof-tip aprof-tip--done">
              <i class="fa-solid fa-circle-check"></i>
              <span>Admin Role Protected</span>
            </div>
          </div>
        </div>

      </div><!-- /sidebar -->
    </div>
  </div><!-- /tab-security -->

  <!-- ════════ TAB: NOTIFICATIONS ════════ -->
  <div class="aprof-tab-panel" id="tab-notifications">
    <div class="admin-layout">
      <div class="admin-main">
        <div class="admin-card">
          <div class="admin-card-head">
            <h2>Notification Preferences</h2>
            <span class="aprof-eyebrow-chip">Admin Account</span>
          </div>
          <div class="aprof-section-body">
            <p class="aprof-section-desc">Choose which events trigger notifications for your administrator account. These preferences apply to in-app and email alerts.</p>
            <form method="POST" action="<?= BASE_URL ?>admin/profile/notification-prefs">
              <div class="aprof-notif-groups">

                <div class="aprof-notif-group">
                  <div class="aprof-notif-group-label">
                    <i class="fa-solid fa-calendar-check"></i> Booking Events
                  </div>
                  <div class="aprof-notif-rows">
                    <?php
                    $notifPrefs = [
                      'new_booking'         => ['New booking created by a customer',         true],
                      'booking_completed'   => ['Booking marked as completed',               true],
                      'booking_cancelled'   => ['Booking cancelled by customer or provider', true],
                      'booking_disputed'    => ['Booking dispute raised',                    true],
                    ];
                    foreach ($notifPrefs as $key => [$label, $default]):
                    ?>
                    <div class="aprof-notif-row">
                      <div class="aprof-notif-row-info">
                        <div class="aprof-notif-row-label"><?= $label ?></div>
                      </div>
                      <label class="aprof-toggle">
                        <input type="checkbox" name="notif[<?= $key ?>]" value="1" <?= $default ? 'checked' : '' ?>>
                        <span class="aprof-toggle-track">
                          <span class="aprof-toggle-thumb"></span>
                        </span>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="aprof-notif-group">
                  <div class="aprof-notif-group-label">
                    <i class="fa-solid fa-store"></i> Provider Events
                  </div>
                  <div class="aprof-notif-rows">
                    <?php
                    $providerPrefs = [
                      'new_provider_registration' => ['New provider registered & pending approval', true],
                      'provider_approved'         => ['Provider approved by admin',                 false],
                      'provider_suspended'        => ['Provider account suspended',                 true],
                    ];
                    foreach ($providerPrefs as $key => [$label, $default]):
                    ?>
                    <div class="aprof-notif-row">
                      <div class="aprof-notif-row-info">
                        <div class="aprof-notif-row-label"><?= $label ?></div>
                      </div>
                      <label class="aprof-toggle">
                        <input type="checkbox" name="notif[<?= $key ?>]" value="1" <?= $default ? 'checked' : '' ?>>
                        <span class="aprof-toggle-track">
                          <span class="aprof-toggle-thumb"></span>
                        </span>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="aprof-notif-group">
                  <div class="aprof-notif-group-label">
                    <i class="fa-solid fa-shield-halved"></i> System & Security
                  </div>
                  <div class="aprof-notif-rows">
                    <?php
                    $systemPrefs = [
                      'admin_login'         => ['Admin login from a new device',   true],
                      'suspicious_activity' => ['Suspicious activity detected',     true],
                      'review_flagged'      => ['Review flagged for moderation',    true],
                    ];
                    foreach ($systemPrefs as $key => [$label, $default]):
                    ?>
                    <div class="aprof-notif-row">
                      <div class="aprof-notif-row-info">
                        <div class="aprof-notif-row-label"><?= $label ?></div>
                      </div>
                      <label class="aprof-toggle">
                        <input type="checkbox" name="notif[<?= $key ?>]" value="1" <?= $default ? 'checked' : '' ?>>
                        <span class="aprof-toggle-track">
                          <span class="aprof-toggle-thumb"></span>
                        </span>
                      </label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>

              </div><!-- /notif-groups -->
              <div class="aprof-form-actions">
                <button type="submit" class="aprof-btn aprof-btn--primary">
                  <i class="fa-solid fa-floppy-disk"></i> Save Preferences
                </button>
                <button type="reset" class="aprof-btn aprof-btn--ghost">Reset</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="admin-sidebar">
        <div class="admin-card">
          <div class="admin-card-head"><h2>Delivery Channels</h2></div>
          <div class="aprof-section-body">
            <div class="aprof-notif-rows">
              <div class="aprof-notif-row">
                <div class="aprof-notif-row-info">
                  <div class="aprof-notif-row-label"><i class="fa-solid fa-bell"></i> In-App Alerts</div>
                </div>
                <label class="aprof-toggle"><input type="checkbox" checked><span class="aprof-toggle-track"><span class="aprof-toggle-thumb"></span></span></label>
              </div>
              <div class="aprof-notif-row">
                <div class="aprof-notif-row-info">
                  <div class="aprof-notif-row-label"><i class="fa-solid fa-envelope"></i> Email Digest</div>
                </div>
                <label class="aprof-toggle"><input type="checkbox" checked><span class="aprof-toggle-track"><span class="aprof-toggle-thumb"></span></span></label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div><!-- /tab-notifications -->

  <!-- ════════ TAB: ACTIVITY LOGS ════════ -->
  <div class="aprof-tab-panel" id="tab-logs">
    <div class="admin-card">
      <div class="admin-card-head">
        <h2>Activity Logs</h2>
        <div class="aprof-log-filters">
          <input type="text" id="logSearch" class="aprof-search-input" placeholder="Search logs…" oninput="filterLogs(this.value)">
          <span class="aprof-eyebrow-chip"><?= count($activityLogs) ?> entries</span>
        </div>
      </div>
      <?php if (empty($activityLogs)): ?>
      <div class="aprof-empty-state aprof-empty-state--lg">
        <i class="fa-solid fa-scroll"></i>
        <span>No activity logs found</span>
      </div>
      <?php else: ?>
      <div class="aprof-log-table-wrap">
        <table class="aprof-log-table" id="logTable">
          <thead>
            <tr>
              <th>Action</th>
              <th>Details</th>
              <th>Target</th>
              <th>IP Address</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($activityLogs as $log):
              [$ico, $col] = actionIcon($log['action']);
            ?>
            <tr>
              <td>
                <div class="aprof-action-cell">
                  <span class="aprof-log-ico aprof-log-ico--<?= $col ?>">
                    <i class="fa-solid <?= $ico ?>"></i>
                  </span>
                  <span><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))) ?></span>
                </div>
              </td>
              <td class="aprof-detail-cell"><?= $log['details'] ? htmlspecialchars($log['details']) : '<span class="aprof-empty">—</span>' ?></td>
              <td>
                <?php if ($log['target_type'] && $log['target_id']): ?>
                  <span class="aprof-target-chip">
                    <?= htmlspecialchars(ucfirst($log['target_type'])) ?> #<?= (int)$log['target_id'] ?>
                  </span>
                <?php else: ?>
                  <span class="aprof-empty">—</span>
                <?php endif; ?>
              </td>
              <td class="aprof-ip-cell">
                <span class="aprof-mono"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></span>
              </td>
              <td class="aprof-time-cell">
                <div><?= date('M j, Y', strtotime($log['created_at'])) ?></div>
                <div class="aprof-time-sub"><?= date('g:i A', strtotime($log['created_at'])) ?></div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div><!-- /tab-logs -->

  <!-- ════════ TAB: FEEDBACK ════════ -->


</div><!-- /admin-pv-page -->

<!-- ══════════════ EDIT PROFILE MODAL ══════════════ -->
<div class="aprof-modal-overlay" id="editModal">
  <div class="aprof-modal">
    <div class="aprof-modal-head">
      <h3 class="aprof-modal-title">Edit Profile</h3>
      <button class="aprof-modal-close" onclick="closeEditModal()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>admin/profile/update" enctype="multipart/form-data" class="aprof-form aprof-modal-body">
      <div class="aprof-form-row aprof-form-row--2col">
        <div class="aprof-form-group">
          <label class="aprof-label">First Name</label>
          <div class="aprof-input-wrap">
            <i class="fa-solid fa-user aprof-input-icon"></i>
            <input type="text" name="first_name" class="aprof-input"
              value="<?= htmlspecialchars($admin['first_name']) ?>" required>
          </div>
        </div>
        <div class="aprof-form-group">
          <label class="aprof-label">Last Name</label>
          <div class="aprof-input-wrap">
            <i class="fa-solid fa-user aprof-input-icon"></i>
            <input type="text" name="last_name" class="aprof-input"
              value="<?= htmlspecialchars($admin['last_name']) ?>" required>
          </div>
        </div>
      </div>
      <div class="aprof-form-group">
        <label class="aprof-label">Email Address</label>
        <div class="aprof-input-wrap">
          <i class="fa-solid fa-envelope aprof-input-icon"></i>
          <input type="email" name="email" class="aprof-input"
            value="<?= htmlspecialchars($admin['email']) ?>" required>
        </div>
      </div>
      <div class="aprof-form-row aprof-form-row--2col">
        <div class="aprof-form-group">
          <label class="aprof-label">Phone</label>
          <div class="aprof-input-wrap">
            <i class="fa-solid fa-phone aprof-input-icon"></i>
            <input type="tel" name="phone" class="aprof-input"
              value="<?= htmlspecialchars($admin['phone'] ?? '') ?>">
          </div>
        </div>
        <div class="aprof-form-group">
          <label class="aprof-label">Gender</label>
          <select name="gender" class="aprof-select">
            <option value="">— Prefer not to say —</option>
            <option value="male"   <?= ($admin['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= ($admin['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
            <option value="non_binary" <?= ($admin['gender'] ?? '') === 'non_binary' ? 'selected' : '' ?>>Non-Binary</option>
            <option value="prefer_not_to_say" <?= ($admin['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : '' ?>>Prefer not to say</option>
          </select>
        </div>
      </div>
      <div class="aprof-form-group">
        <label class="aprof-label">Date of Birth</label>
        <div class="aprof-input-wrap">
          <i class="fa-solid fa-calendar aprof-input-icon"></i>
          <input type="date" name="date_of_birth" class="aprof-input"
            value="<?= htmlspecialchars($admin['date_of_birth'] ?? '') ?>">
        </div>
      </div>
      <div class="aprof-form-actions">
        <button type="submit" class="aprof-btn aprof-btn--primary">
          <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>
        <button type="button" class="aprof-btn aprof-btn--ghost" onclick="closeEditModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
/* ── Avatar photo upload ── */
(function(){
  const fileInput  = document.getElementById('avatarFileInput');
  const uploadForm = document.getElementById('avatarUploadForm');
  const uploadBtn  = document.querySelector('.aprof-av-upload-btn');
  const avatarWrap = document.querySelector('.aprof-avatar-wrap');

  if (!fileInput || !uploadForm) return;

  fileInput.addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
      showAvatarToast('Only JPG, PNG or WebP images are allowed.', 'error'); return;
    }
    if (file.size > 3 * 1024 * 1024) {
      showAvatarToast('Image must be under 3 MB.', 'error'); return;
    }

    // Preview immediately
    const reader = new FileReader();
    reader.onload = function(e) {
      const initials = document.getElementById('adminAvatarInitials');
      if (initials) initials.remove();
      let img = document.getElementById('adminAvatarImg');
      if (!img) {
        img = document.createElement('img');
        img.id = 'adminAvatarImg';
        img.className = 'aprof-avatar-img';
        avatarWrap.prepend(img);
      }
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);

    // Upload via fetch
    uploadBtn.classList.add('aprof-av-upload-btn--loading');
    const fd = new FormData(uploadForm);
    fd.append('avatar', file);
    fetch(uploadForm.action, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
      .then(r => r.json())
      .then(data => {
        uploadBtn.classList.remove('aprof-av-upload-btn--loading');
        if (data.success) {
          showAvatarToast('Profile photo updated!', 'success');
          // Update topbar avatar immediately (no reload needed)
          if (data.avatar) {
            const topbarWrap = document.getElementById('topbarAvatarWrap');
            if (topbarWrap) {
              topbarWrap.innerHTML = '<img src="' + data.avatar + '" alt="avatar" id="topbarAvatarImg" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">';
            }
          }
        } else {
          showAvatarToast(data.error || 'Upload failed.', 'error');
        }
      })
      .catch(() => {
        uploadBtn.classList.remove('aprof-av-upload-btn--loading');
        showAvatarToast('Upload failed. Please try again.', 'error');
      });
  });

  function showAvatarToast(msg, type) {
    const ex = document.getElementById('aprof-avatar-toast');
    if (ex) ex.remove();
    const t = document.createElement('div');
    t.id = 'aprof-avatar-toast';
    t.className = 'aprof-avatar-toast aprof-avatar-toast--' + type;
    t.innerHTML = (type === 'success' ? '<i class="fa-solid fa-circle-check"></i> ' : '<i class="fa-solid fa-triangle-exclamation"></i> ') + msg;
    document.body.appendChild(t);
    setTimeout(() => t.classList.add('aprof-avatar-toast--show'), 10);
    setTimeout(() => { t.classList.remove('aprof-avatar-toast--show'); setTimeout(() => t.remove(), 400); }, 3500);
  }
})();

/* ── Tab switching ── */
function switchTab(name) {
  document.querySelectorAll('.aprof-tab').forEach(t => t.classList.remove('is-active'));
  document.querySelectorAll('.aprof-tab-panel').forEach(p => p.classList.remove('is-active'));
  const btn = document.querySelector(`.aprof-tab[data-tab="${name}"]`);
  const panel = document.getElementById(`tab-${name}`);
  if (btn)   btn.classList.add('is-active');
  if (panel) panel.classList.add('is-active');
}
document.querySelectorAll('.aprof-tab').forEach(btn => {
  btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});

/* ── Edit modal ── */
function openEditModal()  { document.getElementById('editModal').classList.add('is-open');  document.body.style.overflow='hidden'; }
function closeEditModal() { document.getElementById('editModal').classList.remove('is-open'); document.body.style.overflow=''; }
document.getElementById('editModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeEditModal(); });

/* ── Password visibility toggle ── */
function togglePw(btn) {
  const input = btn.parentElement.querySelector('input');
  const icon  = btn.querySelector('i');
  if (input.type === 'password') { input.type = 'text';  icon.classList.replace('fa-eye','fa-eye-slash'); }
  else                            { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
}

/* ── Password strength checker ── */
function checkPwStrength(val) {
  const rules = {
    len:   val.length >= 8,
    upper: /[A-Z]/.test(val),
    num:   /[0-9]/.test(val),
    sym:   /[^A-Za-z0-9]/.test(val),
  };
  const score = Object.values(rules).filter(Boolean).length;
  const labels  = ['', 'Weak', 'Fair', 'Good', 'Strong'];
  const colours = ['', '#DC2626', '#D97706', '#16A34A', '#2563EB'];

  const fill  = document.getElementById('pwFill');
  const label = document.getElementById('pwLabel');
  fill.style.width   = (score / 4 * 100) + '%';
  fill.style.background = colours[score] || '#ccc';
  label.textContent  = labels[score] || '—';
  label.style.color  = colours[score] || 'inherit';

  Object.entries(rules).forEach(([key, ok]) => {
    const el = document.getElementById(`rule-${key}`);
    if (el) { el.classList.toggle('is-met', ok); }
  });
}

/* ── Activity log search ── */
function filterLogs(q) {
  const rows = document.querySelectorAll('#logTable tbody tr');
  const lq = q.toLowerCase();
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(lq) ? '' : 'none';
  });
}

/* ── Dark mode theme persistence (same as other admin pages) ── */
(function() {
  const saved = localStorage.getItem('qb-admin-theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
})();
</script>
</body>
</html>