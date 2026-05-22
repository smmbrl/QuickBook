<?php
// app/views/admin/dashboard.php
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
$today     = date('F j, Y');
$dayName   = date('l, F j, Y');

function admStatusPill(string $s): string {
    $cls   = in_array($s, ['pending','confirmed','completed','cancelled','in_progress','rescheduled']) ? $s : 'default';
    $label = ucfirst(str_replace('_', ' ', $s));
    return "<span class='adm-pill adm-pill--{$cls}'>{$label}</span>";
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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Dashboard dark-mode overrides ── */
[data-theme="dark"] body {
  background: #0D1117;
  color: #EDE3CC;
}
[data-theme="dark"] body::before {
  background:
    radial-gradient(ellipse 70% 55% at 0% 0%,   rgba(201,168,76,.09) 0%, transparent 60%),
    radial-gradient(ellipse 55% 45% at 100% 10%, rgba(201,140,80,.07) 0%, transparent 55%),
    radial-gradient(ellipse 50% 60% at 95% 90%,  rgba(100,80,180,.07) 0%, transparent 58%),
    radial-gradient(ellipse 65% 50% at 5% 88%,   rgba(50,120,100,.06) 0%, transparent 60%),
    radial-gradient(ellipse 60% 40% at 50% 50%,  rgba(13,17,23,.80)   0%, transparent 70%);
}
[data-theme="dark"] .admin-hero-overlay {
  background:
    linear-gradient(180deg, rgba(13,17,23,.88) 0%, rgba(13,17,23,.68) 40%, rgba(13,17,23,.97) 100%),
    linear-gradient(110deg, rgba(13,17,23,.85) 0%, rgba(13,17,23,.50) 55%, rgba(13,17,23,.20) 100%);
}
[data-theme="dark"] .admin-hero-name,
[data-theme="dark"] .adm-booking-service,
[data-theme="dark"] .adm-user-name,
[data-theme="dark"] .adm-snap-val,
[data-theme="dark"] .admin-hs-val,
[data-theme="dark"] .admin-kpi-val,
[data-theme="dark"] .adm-action-title,
[data-theme="dark"] .admin-card-head h2 { color: #EDE3CC; }
[data-theme="dark"] .admin-hero-date,
[data-theme="dark"] .adm-booking-meta,
[data-theme="dark"] .adm-user-email,
[data-theme="dark"] .adm-snap-lbl,
[data-theme="dark"] .admin-hs-label,
[data-theme="dark"] .admin-kpi-label,
[data-theme="dark"] .adm-action-sub { color: rgba(237,227,204,.40); }
[data-theme="dark"] .admin-hero-stats {
  background: rgba(255,255,255,.04);
  border-color: rgba(255,255,255,.10);
  box-shadow: 0 8px 32px rgba(0,0,0,.40);
}
[data-theme="dark"] .admin-hs-div { background: rgba(255,255,255,.10); }
[data-theme="dark"] .admin-card {
  background: rgba(18,24,38,.80);
  border-color: rgba(201,168,76,.14);
  box-shadow: 0 4px 28px rgba(0,0,0,.30);
}
[data-theme="dark"] .admin-card:hover { border-color: rgba(201,168,76,.35); }
[data-theme="dark"] .admin-card-head {
  background: linear-gradient(135deg, rgba(28,22,10,.92) 0%, rgba(20,16,8,.96) 100%);
  border-bottom-color: rgba(201,168,76,.22);
}
[data-theme="dark"] .adm-booking-row,
[data-theme="dark"] .adm-user-row,
[data-theme="dark"] .adm-snap-item { border-bottom-color: rgba(255,255,255,.06); }
[data-theme="dark"] .adm-booking-row:hover,
[data-theme="dark"] .adm-user-row:hover,
[data-theme="dark"] .adm-snap-item:hover { background: rgba(255,255,255,.04); }
[data-theme="dark"] .adm-booking-av,
[data-theme="dark"] .adm-snap-ico,
[data-theme="dark"] .adm-action-ico {
  background: linear-gradient(135deg, rgba(38,30,14,.90), rgba(50,40,18,.90));
  border-color: rgba(201,168,76,.25);
}
[data-theme="dark"] .adm-action { border-color: rgba(201,168,76,.25); }
[data-theme="dark"] .adm-action:hover { background: rgba(201,168,76,.07); }
[data-theme="dark"] .adm-footer { border-top-color: rgba(255,255,255,.06); }
/* ── Status Pills ── */
.adm-pill {
  display: inline-flex; align-items: center; gap: .35rem;
  font-family: 'DM Mono', monospace;
  font-size: .62rem; font-weight: 600;
  letter-spacing: .06em; text-transform: uppercase;
  padding: .28rem .82rem; border-radius: 99px;
  border: 1px solid transparent; white-space: nowrap;
}
.adm-pill::before {
  content: ''; width: 5px; height: 5px;
  border-radius: 99px; background: currentColor; flex-shrink: 0;
}
/* pending — yellow */
.adm-pill--pending     { background: rgba(217,119,6,.10);  color: #D97706; border-color: rgba(217,119,6,.28); }
/* confirmed — green */
.adm-pill--confirmed   { background: rgba(22,163,74,.10);  color: #16A34A; border-color: rgba(22,163,74,.28); }
/* completed — blue */
.adm-pill--completed   { background: rgba(37,99,235,.10);  color: #2563EB; border-color: rgba(37,99,235,.28); }
/* cancelled — red */
.adm-pill--cancelled   { background: rgba(220,38,38,.10);  color: #DC2626; border-color: rgba(220,38,38,.28); }
/* in_progress — orange */
.adm-pill--in_progress { background: rgba(234,88,12,.10);  color: #EA580C; border-color: rgba(234,88,12,.28); }
/* rescheduled — purple */
.adm-pill--rescheduled { background: rgba(124,58,237,.10); color: #7C3AED; border-color: rgba(124,58,237,.28); }
/* Snapshot inline row */
.adm-snap-inline {
  display: flex;
  align-items: baseline;
  gap: .5rem;
  white-space: nowrap;
}
.adm-snap-inline .adm-snap-val {
  font-family: var(--font-display);
  font-size: 1.1rem; font-weight: 700;
  color: var(--text-primary);
  line-height: 1; flex-shrink: 0;
}
.adm-snap-inline .adm-snap-val.revenue { color: var(--gold-dim, #A88A38); }
.adm-snap-inline .adm-snap-lbl {
  font-size: .78rem; color: var(--text-muted);
}
</style>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
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
      <div class="admin-hs-val yellow"><?= $pendingBookings ?></div>
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
            <div class="adm-empty-icon"><i class="fa-solid fa-clipboard-list"></i></div>
            <p>No bookings yet.</p>
          </div>
        <?php else: ?>
          <?php foreach (array_slice($recentBookings, 0, 5) as $b): ?>
            <div class="adm-booking-row">
              <div class="adm-booking-av"><i class="fa-solid fa-calendar-check"></i></div>
              <div class="adm-booking-info">
                <div class="adm-booking-service"><?= htmlspecialchars($b['service_name']) ?></div>
                <div class="adm-booking-meta">
                  <?= htmlspecialchars($b['prov_first'].' '.$b['prov_last']) ?>
                </div>
              </div>
              <div class="adm-booking-right">
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

    if ($u['role'] === 'provider' && !empty($u['provider_profile_id'])) {
        $profileUrl = BASE_URL . 'providers/' . $u['provider_profile_id'];
    } elseif ($u['role'] === 'customer') {
        $profileUrl = BASE_URL . 'admin/users';
    } else {
        $profileUrl = null;
    }
  ?>
    <?php if ($profileUrl): ?>
    <a href="<?= $profileUrl ?>" class="adm-user-row" style="text-decoration:none;cursor:pointer;" title="View profile">
    <?php else: ?>
    <div class="adm-user-row">
    <?php endif ?>

      <div class="adm-av <?= $avcls ?>"><?= $init ?></div>
      <div style="flex:1;min-width:0">
        <div class="adm-user-name"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></div>
        <div class="adm-user-email"><?= htmlspecialchars($u['email']) ?></div>
      </div>
      <?= admRolePill($u['role']) ?>

    <?php if ($profileUrl): ?></a><?php else: ?></div><?php endif ?>
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
            <div class="adm-action-ico"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="adm-action-txt">
              <span class="adm-action-title">All Bookings</span>
              <span class="adm-action-sub">Review &amp; manage</span>
            </div>
          </a>
          <a href="<?= BASE_URL ?>admin/providers" class="adm-action">
            <div class="adm-action-ico"><i class="fa-solid fa-store"></i></div>
            <div class="adm-action-txt">
              <span class="adm-action-title">Manage Providers</span>
              <span class="adm-action-sub">Approve or suspend listings</span>
            </div>
            <span class="adm-action-chevron">›</span>
          </a>
          <a href="<?= BASE_URL ?>admin/users" class="adm-action">
            <div class="adm-action-ico"><i class="fa-solid fa-users"></i></div>
            <div class="adm-action-txt">
              <span class="adm-action-title">Manage Users</span>
              <span class="adm-action-sub">Browse all accounts</span>
            </div>
            <span class="adm-action-chevron">›</span>
          </a>
          <a href="<?= BASE_URL ?>admin/reports" class="adm-action">
            <div class="adm-action-ico"><i class="fa-solid fa-chart-bar"></i></div>
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
          <div class="adm-snap-inline">
            <span class="adm-snap-val"><?= number_format($totalUsers) ?></span>
            <span class="adm-snap-lbl">Registered users</span>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-inline">
            <span class="adm-snap-val"><?= number_format($totalProviders) ?></span>
            <span class="adm-snap-lbl">Active providers</span>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-inline">
            <span class="adm-snap-val"><?= number_format($totalCustomers) ?></span>
            <span class="adm-snap-lbl">Customers</span>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-inline">
            <span class="adm-snap-val"><?= number_format($totalBookings) ?></span>
            <span class="adm-snap-lbl">Total bookings</span>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-inline">
            <span class="adm-snap-val revenue">₱<?= number_format($totalRevenue, 0) ?></span>
            <span class="adm-snap-lbl">Total revenue</span>
          </div>
        </div>
      </div>

    </div><!-- /admin-sidebar -->
  </div><!-- /admin-layout -->

  <div class="adm-footer">QuickBook Admin · Dashboard · <?= $today ?></div>
</div><!-- /admin-pv-page -->

</body>
</html>