<?php
// app/views/customer/dashboard.php
$name   = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$email  = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

/* ── Stats ── */
$stTotal = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ?");
$stTotal->execute([$userId]);
$totalBookings = (int)$stTotal->fetchColumn();

$stUpcoming = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE()");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();

$stCompleted = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status = 'completed'");
$stCompleted->execute([$userId]);
$completedCount = (int)$stCompleted->fetchColumn();

$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status = 'pending'");
$stPending->execute([$userId]);
$pendingCount = (int)$stPending->fetchColumn();

$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();

$stSpent = $db->prepare("SELECT COALESCE(SUM(s.price),0) FROM tbl_bookings b JOIN tbl_services s ON b.service_id = s.id WHERE b.customer_id = ? AND b.status = 'completed'");
$stSpent->execute([$userId]);
$totalSpent = (float)$stSpent->fetchColumn();

$stMonthSpent = $db->prepare("SELECT COALESCE(SUM(s.price),0) FROM tbl_bookings b JOIN tbl_services s ON b.service_id = s.id WHERE b.customer_id = ? AND b.status = 'completed' AND MONTH(b.booking_date) = MONTH(CURDATE()) AND YEAR(b.booking_date) = YEAR(CURDATE())");
$stMonthSpent->execute([$userId]);
$monthSpent = (float)$stMonthSpent->fetchColumn();

/* ── Loyalty ── */
$loyaltyTier = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};
$nextLevel = 500;
$progress  = min(100, round(($loyaltyPoints % $nextLevel) / $nextLevel * 100));
$ptsToNext = $nextLevel - ($loyaltyPoints % $nextLevel);

/* ── Recent bookings ── */
$stRecent = $db->prepare("
    SELECT b.*, pp.business_name, pp.profile_photo,
           s.name AS service_name, s.price,
           s.service_type, s.duration_minutes, s.location_type,
           CONCAT(u.first_name, ' ', u.last_name) AS provider_name
    FROM tbl_bookings b
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    JOIN tbl_services s           ON b.service_id  = s.id
    JOIN tbl_users u              ON pp.user_id = u.id
    WHERE b.customer_id = ?
    ORDER BY b.created_at DESC LIMIT 5
");
$stRecent->execute([$userId]);
$recentBookings = $stRecent->fetchAll();

/* ── Upcoming ── */
$stUpcomingList = $db->prepare("
    SELECT b.*, pp.business_name, s.name AS service_name, s.price
    FROM tbl_bookings b
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    JOIN tbl_services s           ON b.service_id  = s.id
    WHERE b.customer_id = ? AND b.status IN ('pending','confirmed') AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC LIMIT 3
");
$stUpcomingList->execute([$userId]);
$upcomingBookings = $stUpcomingList->fetchAll();

/* ── Monthly chart ── */
$stMonthly = $db->prepare("
    SELECT DATE_FORMAT(b.booking_date,'%b') AS month,
           DATE_FORMAT(b.booking_date,'%Y-%m') AS month_key,
           COALESCE(SUM(s.price),0) AS total, COUNT(*) AS cnt
    FROM tbl_bookings b JOIN tbl_services s ON b.service_id = s.id
    WHERE b.customer_id = ? AND b.status = 'completed'
      AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month ORDER BY month_key ASC
");
$stMonthly->execute([$userId]);
$monthlyData = $stMonthly->fetchAll();

$chartLabels = array_column($monthlyData, 'month');
$chartSpend  = array_map(fn($r) => (float)$r['total'], $monthlyData);
$chartDates  = array_column($monthlyData, 'month');

/* ── Pending review ── */
$stLastCompleted = $db->prepare("
    SELECT b.*, pp.business_name, s.name AS service_name
    FROM tbl_bookings b
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    JOIN tbl_services s           ON b.service_id  = s.id
    LEFT JOIN tbl_reviews r ON r.booking_id = b.id
    WHERE b.customer_id = ? AND b.status = 'completed' AND r.id IS NULL
    ORDER BY b.booking_date DESC LIMIT 1
");
$stLastCompleted->execute([$userId]);
$pendingReview = $stLastCompleted->fetch();

/* ── Helpers ── */
$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$initials = strtoupper(substr($name, 0, 2));
$stAv = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAv->execute([$userId]);
$avatarUrl = ($av = $stAv->fetchColumn()) ? ($av) : null;

function serviceIcon(string $n): string {
    $n = strtolower($n);
    if (str_contains($n,'massage')||str_contains($n,'spa'))   return '💆';
    if (str_contains($n,'hair')||str_contains($n,'salon'))    return '✂️';
    if (str_contains($n,'dental')||str_contains($n,'teeth'))  return '🦷';
    if (str_contains($n,'gym')||str_contains($n,'train'))     return '🏋️';
    if (str_contains($n,'pet')||str_contains($n,'groom'))     return '🐾';
    if (str_contains($n,'clean')||str_contains($n,'laundry')) return '🧹';
    if (str_contains($n,'repair')||str_contains($n,'plumb'))  return '🔧';
    return '📋';
}

function fmtMoney(float $v): string {
    return $v >= 1000 ? '₱'.number_format($v/1000,1).'k' : '₱'.number_format($v,0);
}
$spentDisplay = fmtMoney($totalSpent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Apply saved theme BEFORE render to prevent flash -->
  <script>
    (function(){
      var t = localStorage.getItem('qb-theme') || 'light';
      if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
    })();
  </script>
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo" style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard"  class="pv-nav-link is-active">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings"   class="pv-nav-link">
        Bookings
        <?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse"     class="pv-nav-link">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty"    class="pv-nav-link">Loyalty</a>
      <a href="<?= BASE_URL ?>profile"    class="pv-nav-link">Profile</a>
    </div>
    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <!-- THEME TOGGLE -->
      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <!-- Moon icon (shown in light mode) -->
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <!-- Sun icon (shown in dark mode) -->
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/>
          <line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22"  y1="4.22"  x2="5.64"  y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1"  y1="12" x2="3"  y2="12"/>
          <line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22"  y1="19.78" x2="5.64"  y2="18.36"/>
          <line x1="18.36" y1="5.64"  x2="19.78" y2="4.22"/>
        </svg>
      </button>

      <!-- Profile dropdown trigger -->
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av">
          <?php if ($avatarUrl): ?>
            <img src="<?= $avatarUrl ?>" alt="<?= $name ?>" style="width:34px;height:34px;object-fit:cover;border-radius:99px;display:block;">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div class="pv-nav-user">
          <div class="pv-nav-user-name"><?= $name ?></div>
          <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
        </div>
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

      <!-- Profile dropdown panel -->
      <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="pv-pd-header">
          <div class="pv-pd-avatar">
            <?php if ($avatarUrl): ?>
              <img src="<?= $avatarUrl ?>" alt="<?= $name ?>">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $name ?></div>
            <div class="pv-pd-email"><?= $email ?></div>
            <span class="pv-pd-tier"><?= $loyaltyTier ?> Member</span>
          </div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>profile" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-user"></i></span>
          <span>My Profile</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>auth/logout" class="pv-pd-item pv-pd-item--danger" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
          <span>Sign Out</span>
        </a>
      </div>
  </div>
</nav>

<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        <?= $greeting ?>
      </p>
      <h1 class="pv-hero-name"><?= $name ?></h1>
      <p class="pv-hero-date"><?= date('l, F j, Y') ?></p>
      <div class="pv-hero-meta">
        <span class="pv-status-badge">
          <span class="pv-status-dot" aria-hidden="true"></span>
          Active Member
        </span>
        <span class="pv-tier-badge">⭐ <?= $loyaltyTier ?></span>
      </div>
    </div>
    <?php if ($upcomingCount > 0): ?>
    <a href="<?= BASE_URL ?>bookings?status=pending" class="pv-points-chip">
      <span class="pv-points-chip-dot" aria-hidden="true"></span>
      <?= $upcomingCount ?> upcoming booking<?= $upcomingCount > 1 ? 's' : '' ?>
      <span aria-hidden="true">→</span>
    </a>
    <?php endif; ?>
  </div>
  <div class="pv-hero-stats" role="region" aria-label="Quick stats">
    <div class="pv-hs-item">
      <span class="pv-hs-val"><?= $totalBookings ?></span>
      <span class="pv-hs-label">Total Bookings</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val accent"><?= $pendingCount ?></span>
      <span class="pv-hs-label">Pending</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val green"><?= $completedCount ?></span>
      <span class="pv-hs-label">Completed</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val accent"><?= $spentDisplay ?></span>
      <span class="pv-hs-label">Total Spent</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val blue"><?= number_format($loyaltyPoints) ?></span>
      <span class="pv-hs-label">Loyalty Points</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val yellow"><?= $upcomingCount ?></span>
      <span class="pv-hs-label">Upcoming</span>
    </div>
  </div>
</header>

<main class="pv-page" role="main">

  <div class="pv-layout">

    <div class="pv-main">

      <!-- Spending Chart -->
      <div class="pv-card pv-card--trend">
        <div class="pv-trend-head">
          <div class="pv-trend-meta">
            <span class="pv-trend-eyebrow">LAST 6 MONTHS</span>
            <h2 class="pv-trend-title">Spending Overview</h2>
          </div>
          <div class="pv-trend-right">
            <div class="pv-tabs">
              <span class="pv-tab active">6M</span>
              <span class="pv-tab">1Y</span>
              <span class="pv-tab">All</span>
            </div>
          </div>
        </div>
        <div class="pv-trend-canvas-wrap">
          <canvas id="spendChart"></canvas>
        </div>
      </div>

      <!-- Recent Bookings -->
      <div class="pv-card pv-card--table">
        <div class="pv-card-head">
          <h2>Recent Bookings</h2>
          <a href="<?= BASE_URL ?>bookings" class="pv-link">View all →</a>
        </div>
        <?php if (empty($recentBookings)): ?>
        <div class="pv-empty-state">
          <div class="pv-empty-icon" aria-hidden="true">📭</div>
          <p>No bookings yet — find a service to get started.</p>
          <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Browse Services →</a>
        </div>
        <?php else: ?>
        <div class="pv-rb-table-wrap">
          <table class="pv-rb-table">
            <thead>
              <tr>
                <th>Service</th>
                <th>Provider</th>
                <th>Price</th>
                <th>Duration</th>
                <th>Location</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentBookings as $b):
                $dm     = (int)($b['duration_minutes'] ?? 0);
                $dLabel = $dm ? (($dm >= 60 && $dm % 60 === 0) ? ($dm/60).' hr' : $dm.' min') : '—';
              ?>
              <tr>
                <td>
                  <div class="pv-rb-name">
                    <?= htmlspecialchars($b['service_name']) ?>
                  </div>
                </td>
                <td><?= htmlspecialchars($b['provider_name'] ?? '—') ?></td>
                <td class="pv-rb-price">₱<?= number_format((float)$b['price'], 2) ?></td>
                <td><?= $dLabel ?></td>
                <td><?= htmlspecialchars($b['location_type'] ?? '—') ?></td>
                <td>
                  <span class="pv-rb-badge pv-rb-badge--<?= $b['status'] ?>">
                    <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
                  </span>
                </td>
                <td class="pv-rb-date"><?= date('M d, Y', strtotime($b['booking_date'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /pv-main -->

    <aside class="pv-sidebar" aria-label="Sidebar">

      <!-- Quick Actions -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Quick Actions</h2></div>
        <div class="pv-actions">
          <a href="<?= BASE_URL ?>bookings?status=pending" class="pv-action is-primary">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-clock"></i></span>
            <div class="pv-action-txt">
              <strong>Pending Bookings</strong>
              <span>Review &amp; manage</span>
            </div>
          </a>
          <a href="<?= BASE_URL ?>browse" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
            <div class="pv-action-txt"><strong>Browse Services</strong><span>Find providers near you</span></div>
          </a>
          <a href="<?= BASE_URL ?>loyalty" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-star"></i></span>
            <div class="pv-action-txt"><strong>Loyalty Points</strong><span>Redeem <?= number_format($loyaltyPoints) ?> pts</span></div>
          </a>
          <a href="<?= BASE_URL ?>profile" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
            <div class="pv-action-txt"><strong>My Profile</strong><span>Update account details</span></div>
          </a>
        </div>
      </div>



      <!-- Upcoming Bookings -->
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>Upcoming</h2>
          <a href="<?= BASE_URL ?>bookings" class="pv-link">View all →</a>
        </div>
        <div class="pv-upcoming-list">
          <?php if (empty($upcomingBookings)): ?>
          <div class="pv-empty-state" style="padding:1.8rem 1.2rem">
            <div class="pv-empty-icon" aria-hidden="true">📆</div>
            <p>No upcoming bookings.</p>
            <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Book Now →</a>
          </div>
          <?php else: foreach ($upcomingBookings as $u): ?>
          <div class="pv-upcoming-item">
            <div class="pv-upcoming-date">
              <div class="pv-upcoming-day"><?= date('d', strtotime($u['booking_date'])) ?></div>
              <div class="pv-upcoming-mon"><?= date('M', strtotime($u['booking_date'])) ?></div>
            </div>
            <div class="pv-upcoming-info">
              <div class="pv-upcoming-service"><?= htmlspecialchars($u['service_name']) ?></div>
              <div class="pv-upcoming-time">
                <?php if (!empty($u['booking_time'])): ?>
                  <?= date('g:i A', strtotime($u['booking_time'])) ?> ·
                <?php endif; ?>
                <?= htmlspecialchars($u['business_name']) ?>
              </div>
            </div>
            <div class="pv-upcoming-price">₱<?= number_format($u['price'],0) ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Loyalty -->
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>Loyalty Status</h2>
          <a href="<?= BASE_URL ?>loyalty" class="pv-link">Details →</a>
        </div>
        <div class="pv-loyalty-body">
          <div class="pv-loyalty-score-row">
            <div>
              <div class="pv-loyalty-big"><?= number_format($loyaltyPoints) ?></div>
              <div class="pv-loyalty-label">Total Points</div>
            </div>
            <span class="pv-loyalty-tier"><?= $loyaltyTier ?></span>
          </div>
          <div class="pv-loyalty-progress-label">
            <span>Progress to next reward</span>
            <span><?= $progress ?>%</span>
          </div>
          <div class="pv-loyalty-bar">
            <div class="pv-loyalty-fill" style="width:<?= $progress ?>%"></div>
          </div>
          <div class="pv-loyalty-hint">
            <?= number_format($ptsToNext) ?> pts to next reward · <?= $completedCount ?> completed booking<?= $completedCount !== 1 ? 's' : '' ?>
          </div>
        </div>
      </div>

    </aside>

  </div><!-- /pv-layout -->

  <?php if ($pendingReview): ?>
  <div class="pv-review-banner">
    <div class="pv-review-icon" aria-hidden="true">⭐</div>
    <div class="pv-review-text">
      <strong>How was your <?= htmlspecialchars(strtolower($pendingReview['service_name'])) ?> at <?= htmlspecialchars($pendingReview['business_name']) ?>?</strong>
      <p>Share your experience and earn 50 bonus loyalty points.</p>
    </div>
    <a href="<?= BASE_URL ?>review/create/<?= (int)$pendingReview['id'] ?>" class="pv-review-btn">Leave a Review</a>
  </div>
  <?php endif; ?>

</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
/* ── THEME TOGGLE ── */
(function () {
  var btn  = document.getElementById('themeToggle');
  var moon = document.querySelector('.icon-moon');
  var sun  = document.querySelector('.icon-sun');

  function applyTheme(theme) {
    if (theme === 'light') {
      document.documentElement.removeAttribute('data-theme');
      if (moon) moon.style.display = 'none';
      if (sun)  sun.style.display  = 'block';
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      if (moon) moon.style.display = 'block';
      if (sun)  sun.style.display  = 'none';
    }
  }

  var saved = localStorage.getItem('qb-theme') || 'light';
  applyTheme(saved);

  if (btn) {
    btn.addEventListener('click', function () {
      var current = document.documentElement.getAttribute('data-theme');
      var next = current === 'dark' ? 'light' : 'dark';
      localStorage.setItem('qb-theme', next);
      applyTheme(next);
    });
  }
})();
</script>
<script>
(function () {
  const labels = <?= json_encode(array_values($chartLabels)) ?>;
  const spend  = <?= json_encode(array_values($chartSpend)) ?>;

  const ctx = document.getElementById('spendChart');
  if (!ctx) return;

  const chart2d = ctx.getContext('2d');

  const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

  function buildGradients() {
    const dark = isDark();

    const gradGold = chart2d.createLinearGradient(0, 0, 0, 220);
    if (dark) {
      gradGold.addColorStop(0,   'rgba(201,168,76,0.55)');
      gradGold.addColorStop(0.55,'rgba(201,168,76,0.22)');
      gradGold.addColorStop(1,   'rgba(201,168,76,0.00)');
    } else {
      gradGold.addColorStop(0,   'rgba(201,168,76,0.38)');
      gradGold.addColorStop(0.55,'rgba(201,168,76,0.16)');
      gradGold.addColorStop(1,   'rgba(201,168,76,0.00)');
    }

    const gradWarm = chart2d.createLinearGradient(0, 0, 0, 220);
    if (dark) {
      gradWarm.addColorStop(0,   'rgba(139,110,32,0.30)');
      gradWarm.addColorStop(0.6, 'rgba(139,110,32,0.10)');
      gradWarm.addColorStop(1,   'rgba(139,110,32,0.00)');
    } else {
      gradWarm.addColorStop(0,   'rgba(232,201,106,0.20)');
      gradWarm.addColorStop(0.6, 'rgba(232,201,106,0.08)');
      gradWarm.addColorStop(1,   'rgba(232,201,106,0.00)');
    }

    return { gradGold, gradWarm };
  }

  function getChartColors() {
    const dark = isDark();
    return {
      borderColor:  dark ? '#C9A84C' : '#8B6E20',
      xTickColor:   dark ? 'rgba(237,227,204,0.35)' : 'rgba(28,23,16,0.40)',
      yTickColor:   dark ? 'rgba(237,227,204,0.30)' : 'rgba(28,23,16,0.38)',
      gridColor:    dark ? 'rgba(201,168,76,0.08)'  : 'rgba(201,168,76,0.10)',
    };
  }

  /* Track hovered index */
  let hoveredIdx = null;

  const hoverPlugin = {
    id: 'hoverDotTooltip',
    afterDraw(chart) {
      if (hoveredIdx === null) return;

      const { ctx: c, chartArea, scales } = chart;
      const idx   = hoveredIdx;
      const x     = scales.x.getPixelForValue(idx);
      const y     = scales.y.getPixelForValue(spend[idx]);
      const label = labels[idx] ?? '';
      const raw   = spend[idx] ?? 0;

      c.save();

      /* ── Dashed vertical line ── */
      c.beginPath();
      c.setLineDash([4, 4]);
      c.strokeStyle = 'rgba(201,168,76,0.50)';
      c.lineWidth   = 1.5;
      c.moveTo(x, chartArea.top);
      c.lineTo(x, chartArea.bottom);
      c.stroke();
      c.setLineDash([]);

      /* ── Dot ── */
      c.beginPath();
      c.arc(x, y, 7, 0, Math.PI * 2);
      c.fillStyle = '#1C1710';
      c.fill();
      c.beginPath();
      c.arc(x, y, 4, 0, Math.PI * 2);
      c.fillStyle = '#E8C96A';
      c.fill();

      /* ── Tooltip card ── */
      const sym  = '₱ ';
      const num  = Number(raw).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      c.font = "500 10px 'DM Mono', monospace";
      const labelW = c.measureText(label).width;
      c.font = "700 11px 'DM Mono', monospace";
      const symW = c.measureText(sym).width;
      const numW = c.measureText(num).width;
      const amountW = symW + numW;

      const padX = 10, padY = 6;
      const pw = Math.max(labelW, amountW) + padX * 2;
      const ph = 40;
      const rx = 8;

      let px = x - pw / 2;
      px = Math.max(chartArea.left, Math.min(px, chartArea.right - pw));
      const py = Math.max(chartArea.top + 4, y - ph - 20);

      /* Card background */
      const dark = isDark();
      c.beginPath();
      c.roundRect(px, py, pw, ph, rx);
      c.fillStyle     = dark ? '#1E2535' : '#FFFFFF';
      c.shadowColor   = 'rgba(0,0,0,0.18)';
      c.shadowBlur    = 16;
      c.shadowOffsetY = 5;
      c.fill();
      c.shadowBlur = 0; c.shadowOffsetY = 0;

      /* Card border */
      c.beginPath();
      c.roundRect(px, py, pw, ph, rx);
      c.strokeStyle = 'rgba(201,168,76,0.45)';
      c.lineWidth   = 1;
      c.stroke();

      /* Month label */
      c.font         = "500 10px 'DM Mono', monospace";
      c.fillStyle    = dark ? 'rgba(237,227,204,0.45)' : 'rgba(28,23,16,0.45)';
      c.textAlign    = 'center';
      c.textBaseline = 'middle';
      c.fillText(label.toUpperCase(), px + pw / 2, py + 11);

      /* Divider */
      c.beginPath();
      c.moveTo(px + 8, py + 20);
      c.lineTo(px + pw - 8, py + 20);
      c.strokeStyle = 'rgba(201,168,76,0.20)';
      c.lineWidth   = 1;
      c.stroke();

      /* Amount */
      const startX = px + pw / 2 - amountW / 2;
      const midY   = py + 31;
      c.textAlign    = 'left';
      c.textBaseline = 'middle';
      c.font         = "700 11px 'DM Mono', monospace";
      c.fillStyle    = '#A88A38';
      c.fillText(sym, startX, midY);
      c.fillStyle    = dark ? '#EDE3CC' : '#1C1710';
      c.fillText(num, startX + symW, midY);

      c.restore();
    }
  };

  let { gradGold, gradWarm } = buildGradients();
  let colors = getChartColors();

  const chart = new Chart(ctx, {
    type: 'line',
    plugins: [hoverPlugin],
    data: {
      labels,
      datasets: [
        {
          label: 'Spending (₱)',
          data: spend,
          borderColor: colors.borderColor,
          backgroundColor: gradGold,
          borderWidth: 2.5,
          tension: 0.48,
          fill: true,
          pointRadius: 0,
          pointHoverRadius: 0,
          order: 1,
        },
        {
          label: '_bg',
          data: spend.map((v, i) => {
            const shift = Math.sin((i / (Math.max(spend.length - 1, 1))) * Math.PI * 1.5) * Math.max(...spend, 1) * 0.18;
            return Math.max(0, v - shift);
          }),
          borderColor: 'transparent',
          backgroundColor: gradWarm,
          borderWidth: 0,
          tension: 0.48,
          fill: true,
          pointRadius: 0,
          pointHoverRadius: 0,
          order: 2,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: { enabled: false }
      },
      scales: {
        x: {
          grid: { color: colors.gridColor, lineWidth: 1, drawBorder: false },
          ticks: { color: colors.xTickColor, font: { family:"'DM Mono',monospace", size: 10, weight: '500' }, maxRotation: 0 },
          border: { display: false }
        },
        y: {
          min: 0,
          ticks: {
            color: colors.yTickColor,
            font: { family:"'DM Mono',monospace", size: 10 },
            callback: v => v,
            stepSize: 100,
            maxTicksLimit: 8,
          },
          grid: { color: colors.gridColor, lineWidth: 1, drawBorder: false },
          border: { display: false }
        }
      }
    }
  });

  /* ── Redraw chart with correct colors when theme toggles ── */
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      setTimeout(() => {
        const g = buildGradients();
        const c = getChartColors();
        chart.data.datasets[0].backgroundColor = g.gradGold;
        chart.data.datasets[0].borderColor      = c.borderColor;
        chart.data.datasets[1].backgroundColor  = g.gradWarm;
        chart.options.scales.x.grid.color       = c.gridColor;
        chart.options.scales.x.ticks.color      = c.xTickColor;
        chart.options.scales.y.grid.color       = c.gridColor;
        chart.options.scales.y.ticks.color      = c.yTickColor;
        chart.update();
      }, 50);
    });
  }

  /* ── Mouse tracking ── */
  ctx.addEventListener('mousemove', function (e) {
    const rect   = ctx.getBoundingClientRect();
    const mouseX = e.clientX - rect.left;
    const xScale = chart.scales.x;
    let   closest = null, minDist = Infinity;

    labels.forEach((_, i) => {
      const px   = xScale.getPixelForValue(i);
      const dist = Math.abs(mouseX - px);
      if (dist < minDist) { minDist = dist; closest = i; }
    });

    if (hoveredIdx !== closest) {
      hoveredIdx = closest;
      chart.draw();
    }
  });

  ctx.addEventListener('mouseleave', function () {
    hoveredIdx = null;
    chart.draw();
  });

  document.querySelectorAll('.pv-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      tab.closest('.pv-tabs').querySelectorAll('.pv-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
    });
  });
})();
</script>
<script>
/* ── Profile Dropdown ── */
(function () {
  const trigger  = document.getElementById('profileTrigger');
  const dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;

  function open() {
    trigger.classList.add('is-open');
    dropdown.classList.add('is-open');
    trigger.setAttribute('aria-expanded', 'true');
  }
  function close() {
    trigger.classList.remove('is-open');
    dropdown.classList.remove('is-open');
    trigger.setAttribute('aria-expanded', 'false');
  }
  function toggle() {
    dropdown.classList.contains('is-open') ? close() : open();
  }

  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    toggle();
  });
  trigger.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    if (e.key === 'Escape') close();
  });
  document.addEventListener('click', function (e) {
    if (!dropdown.contains(e.target) && !trigger.contains(e.target)) close();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
})();

</script>
</body>
</html>
