<?php
// app/views/admin/dashboard.php
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
$today     = date('F j, Y');
$dayName   = date('l, F j, Y');

function admStatusPill(string $s): string {
    $cls = in_array($s, ['pending','confirmed','completed','cancelled','in_progress']) ? $s : '';
    return "<span class='adm-pill adm-pill--{$cls}'>" . htmlspecialchars($s) . "</span>";
}
function admRolePill(string $r): string {
    $map = ['admin'=>'adm-role--admin','provider'=>'adm-role--provider','customer'=>'adm-role--customer'];
    $cls = $map[$r] ?? '';
    return "<span class='adm-role {$cls}'>" . htmlspecialchars(ucfirst($r)) . "</span>";
}

$hour  = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — QuickBook Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_dashboard.css">
</head>
<body>
<div class="grain"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('dashboard'); ?>

<!-- ══════════════ HERO ══════════════ -->
<div class="admin-hero">
  <div class="admin-hero-overlay"></div>

  <div class="admin-hero-inner">
    <div>
      <div class="admin-hero-eyebrow">
        <span class="admin-dot-pulse"></span><?= $greet ?>
      </div>
      <h1 class="admin-hero-name"><?= $adminName ?> 👋</h1>
      <div class="admin-hero-date"><?= $dayName ?></div>
      <div class="admin-hero-meta">
        <span class="admin-status-badge">
          <span class="admin-status-dot"></span>Administrator
        </span>
      </div>
    </div>
    <?php if ($pendingBookings > 0): ?>
    <a href="<?= BASE_URL ?>admin/bookings" class="admin-pending-chip">
      <span class="admin-pending-dot"></span>
      <?= $pendingBookings ?> pending booking<?= $pendingBookings !== 1 ? 's' : '' ?>
    </a>
    <?php endif ?>
  </div>

  <!-- Stat strip -->
  <div class="admin-hero-stats">
    <div class="admin-hs-item">
      <div class="admin-hs-val accent"><?= number_format($totalUsers) ?></div>
      <div class="admin-hs-label">Total Users</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val"><?= number_format($totalBookings) ?></div>
      <div class="admin-hs-label">All Bookings</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val" style="color:#E8C96A"><?= $pendingBookings ?></div>
      <div class="admin-hs-label">Pending</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val green">₱<?= number_format($totalRevenue, 0) ?></div>
      <div class="admin-hs-label">Revenue</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val"><?= $totalProviders ?></div>
      <div class="admin-hs-label">Active Providers</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val"><?= $totalCustomers ?></div>
      <div class="admin-hs-label">Customers</div>
    </div>
  </div>
</div><!-- /admin-hero -->

<!-- ══════════════ PAGE ══════════════ -->
<div class="admin-pv-page">

  <!-- KPI Cards -->
  <div class="admin-kpi-row">

    <div class="admin-kpi admin-kpi--gold">
      <div class="admin-kpi-icon">👥</div>
      <span class="admin-kpi-delta">All roles</span>
      <div class="admin-kpi-val"><?= number_format($totalUsers) ?></div>
      <div class="admin-kpi-label">Total Users</div>
      <div class="admin-kpi-sub"><?= $totalCustomers ?> customers · <?= $totalProviders ?> providers</div>
    </div>

    <div class="admin-kpi admin-kpi--green">
      <div class="admin-kpi-icon">💰</div>
      <span class="admin-kpi-delta">Completed</span>
      <div class="admin-kpi-val">₱<?= number_format($totalRevenue, 0) ?></div>
      <div class="admin-kpi-label">Total Revenue</div>
      <div class="admin-kpi-sub">From all completed bookings</div>
    </div>

    <div class="admin-kpi admin-kpi--blue">
      <div class="admin-kpi-icon">📋</div>
      <span class="admin-kpi-delta neutral"><?= $pendingBookings ?> pending</span>
      <div class="admin-kpi-val"><?= number_format($totalBookings) ?></div>
      <div class="admin-kpi-label">Total Bookings</div>
      <div class="admin-kpi-sub"><?= $pendingBookings ?> awaiting confirmation</div>
    </div>

    <div class="admin-kpi admin-kpi--purple">
      <div class="admin-kpi-icon">🔑</div>
      <span class="admin-kpi-delta">Active</span>
      <div class="admin-kpi-val"><?= $totalProviders ?></div>
      <div class="admin-kpi-label">Active Providers</div>
      <div class="admin-kpi-sub">Listed on the platform</div>
    </div>

  </div><!-- /kpi-row -->

  <!-- Main layout -->
  <div class="admin-layout">

    <!-- Left: main content -->
    <div class="admin-main">

      <!-- Recent Bookings -->
      <div class="admin-card">
        <div class="admin-card-head">
          <h2>Recent Bookings</h2>
          <a href="<?= BASE_URL ?>admin/bookings" class="admin-card-link">View all →</a>
        </div>
        <?php if (empty($recentBookings)): ?>
          <div class="adm-empty">
            <div class="adm-empty-icon">📋</div>
            <p>No bookings yet.</p>
          </div>
        <?php else: ?>
          <?php foreach ($recentBookings as $b): ?>
            <div class="adm-booking-row">
              <div class="adm-booking-av">🛎️</div>
              <div class="adm-booking-info">
                <div class="adm-booking-service"><?= htmlspecialchars($b['service_name']) ?></div>
                <div class="adm-booking-meta">
                  <?= htmlspecialchars($b['cust_first'].' '.$b['cust_last']) ?> ·
                  <?= htmlspecialchars($b['prov_first'].' '.$b['prov_last']) ?>
                </div>
              </div>
              <div class="adm-booking-right">
                <div class="adm-booking-amount">₱<?= number_format($b['total_amount'], 2) ?></div>
                <?= admStatusPill($b['status']) ?>
              </div>
            </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>

      <!-- Newest Users -->
      <div class="admin-card">
        <div class="admin-card-head">
          <h2>Newest Users</h2>
          <a href="<?= BASE_URL ?>admin/users" class="admin-card-link">View all →</a>
        </div>
        <?php if (empty($newUsers)): ?>
          <div class="adm-empty">
            <div class="adm-empty-icon">👥</div>
            <p>No users yet.</p>
          </div>
        <?php else: ?>
          <?php foreach ($newUsers as $u):
            $init  = strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1));
            $avcls = $u['role'] === 'admin' ? 'adm-av-red' : ($u['role'] === 'provider' ? 'adm-av-gold' : 'adm-av-green');
          ?>
            <div class="adm-user-row">
              <div class="adm-av <?= $avcls ?>"><?= $init ?></div>
              <div style="flex:1;min-width:0">
                <div class="adm-user-name"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></div>
                <div class="adm-user-email"><?= htmlspecialchars($u['email']) ?></div>
              </div>
              <?= admRolePill($u['role']) ?>
            </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>

    </div><!-- /admin-main -->

    <!-- Right: sidebar -->
    <div class="admin-sidebar">

      <!-- Quick Actions -->
      <div class="admin-card">
        <div class="admin-card-head"><h2>Quick Actions</h2></div>
        <div class="adm-actions">
          <a href="<?= BASE_URL ?>admin/bookings" class="adm-action is-primary">
            <div class="adm-action-ico">📋</div>
            <div class="adm-action-txt">
              <span class="adm-action-title">Pending Bookings</span>
              <span class="adm-action-sub">Review &amp; confirm</span>
            </div>
          </a>
          <a href="<?= BASE_URL ?>admin/providers" class="adm-action">
            <div class="adm-action-ico">🏪</div>
            <div class="adm-action-txt">
              <span class="adm-action-title">Manage Providers</span>
              <span class="adm-action-sub">Approve or suspend listings</span>
            </div>
            <span class="adm-action-chevron">›</span>
          </a>
          <a href="<?= BASE_URL ?>admin/users" class="adm-action">
            <div class="adm-action-ico">👥</div>
            <div class="adm-action-txt">
              <span class="adm-action-title">Manage Users</span>
              <span class="adm-action-sub">Browse all accounts</span>
            </div>
            <span class="adm-action-chevron">›</span>
          </a>
          <a href="<?= BASE_URL ?>admin/reports" class="adm-action">
            <div class="adm-action-ico">📊</div>
            <div class="adm-action-txt">
              <span class="adm-action-title">Reports &amp; Analytics</span>
              <span class="adm-action-sub">Revenue and performance</span>
            </div>
            <span class="adm-action-chevron">›</span>
          </a>
        </div>
      </div>

      <!-- Platform Snapshot -->
      <div class="admin-card">
        <div class="admin-card-head"><h2>Platform Snapshot</h2></div>
        <div class="adm-snap-item">
          <div class="adm-snap-ico">👥</div>
          <div>
            <div class="adm-snap-val"><?= number_format($totalUsers) ?></div>
            <div class="adm-snap-lbl">Registered users</div>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-ico">🏪</div>
          <div>
            <div class="adm-snap-val"><?= number_format($totalProviders) ?></div>
            <div class="adm-snap-lbl">Active providers</div>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-ico">🧑‍💼</div>
          <div>
            <div class="adm-snap-val"><?= number_format($totalCustomers) ?></div>
            <div class="adm-snap-lbl">Customers</div>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-ico">📋</div>
          <div>
            <div class="adm-snap-val"><?= number_format($totalBookings) ?></div>
            <div class="adm-snap-lbl">Total bookings</div>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-ico">💰</div>
          <div>
            <div class="adm-snap-val" style="color:#4ADE80">₱<?= number_format($totalRevenue, 0) ?></div>
            <div class="adm-snap-lbl">Total revenue</div>
          </div>
        </div>
      </div>

    </div><!-- /admin-sidebar -->
  </div><!-- /admin-layout -->

  <div class="adm-footer">QuickBook Admin · Dashboard · <?= $today ?></div>
</div><!-- /admin-pv-page -->

</body>
</html>