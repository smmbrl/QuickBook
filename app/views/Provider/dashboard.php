<?php
// app/views/provider/dashboard.php
$name        = htmlspecialchars($_SESSION['user_name']  ?? 'Provider');
$email       = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId      = (int)($_SESSION['user_id'] ?? 0);
$providerId  = (int)($_SESSION['provider_id'] ?? 0);

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

/* ── If session doesn't hold provider_id, look it up ── */
if (!$providerId && $userId) {
    $stPid = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
    $stPid->execute([$userId]);
    $providerId = (int)$stPid->fetchColumn();
}

/* ── Business info (JOIN with categories to get category_name) ── */
$stBiz = $db->prepare("
    SELECT pp.*, c.name AS category_name
    FROM tbl_provider_profiles pp
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    WHERE pp.id = ? LIMIT 1
");
$stBiz->execute([$providerId]);
$biz = $stBiz->fetch() ?: [];
$bizName      = htmlspecialchars($biz['business_name'] ?? $name); 
$firstName = htmlspecialchars(explode(' ', $name)[0]);
$bizCategory  = htmlspecialchars($biz['category_name'] ?? 'Service Provider');
$profilePhoto = $biz['profile_photo'] ?? null;

/* ── Rating from stored columns (updated by DB trigger on review insert) ── */
$avgRating   = round((float)($biz['avg_rating']    ?? 0), 1);
$reviewCount = (int)($biz['total_reviews'] ?? 0);

/* ── KPI Stats ── */
$stTotalApt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ?");
$stTotalApt->execute([$providerId]);
$totalBookings = (int)$stTotalApt->fetchColumn();

$stMonthApt = $db->prepare("
    SELECT COUNT(*) FROM tbl_bookings
    WHERE provider_id = ?
      AND MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at)  = YEAR(CURDATE())
");
$stMonthApt->execute([$providerId]);
$monthBookings = (int)$stMonthApt->fetchColumn();

$stEarnings = $db->prepare("
    SELECT COALESCE(SUM(b.total_amount), 0)
    FROM tbl_bookings b
    WHERE b.provider_id = ? AND b.status = 'completed'
");
$stEarnings->execute([$providerId]);
$totalEarnings = (float)$stEarnings->fetchColumn();

$stMonthEarn = $db->prepare("
    SELECT COALESCE(SUM(b.total_amount), 0)
    FROM tbl_bookings b
    WHERE b.provider_id = ? AND b.status = 'completed'
      AND MONTH(b.booking_date) = MONTH(CURDATE())
      AND YEAR(b.booking_date)  = YEAR(CURDATE())
");
$stMonthEarn->execute([$providerId]);
$monthEarnings = (float)$stMonthEarn->fetchColumn();

/* ── Unique customers served (replaces profile views — no tbl_provider_views in schema) ── */
$stCustomers = $db->prepare("
    SELECT COUNT(DISTINCT customer_id)
    FROM tbl_bookings
    WHERE provider_id = ? AND status = 'completed'
");
$stCustomers->execute([$providerId]);
$uniqueCustomers = (int)$stCustomers->fetchColumn();

$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stPending->execute([$providerId]);
$pendingCount = (int)$stPending->fetchColumn();

$stCompleted = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'completed'");
$stCompleted->execute([$providerId]);
$completedCount = (int)$stCompleted->fetchColumn();

/* ── Upcoming appointments ── */
$stUpcoming = $db->prepare("
    SELECT b.*,
           CONCAT(u.first_name,' ',u.last_name) AS customer_name,
           u.avatar_url AS customer_avatar,
           s.name AS service_name, s.price, s.duration_minutes
    FROM tbl_bookings b
    JOIN tbl_users u    ON b.customer_id=u.id
    JOIN tbl_services s ON b.service_id=s.id
    WHERE b.provider_id=?
      AND b.status IN ('pending','confirmed')
      AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC, b.booking_time ASC
    LIMIT 8
");
$stUpcoming->execute([$providerId]);
$upcomingApts = $stUpcoming->fetchAll();

/* ── Monthly earnings chart ── */
$stMonthly = $db->prepare("
    SELECT DATE_FORMAT(b.booking_date, '%b')    AS month,
           DATE_FORMAT(b.booking_date, '%Y-%m') AS month_key,
           COALESCE(SUM(b.total_amount), 0)     AS total,
           COUNT(*)                              AS cnt
    FROM tbl_bookings b
    WHERE b.provider_id = ? AND b.status = 'completed'
      AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month
    ORDER BY month_key ASC
");
$stMonthly->execute([$providerId]);
$monthlyData   = $stMonthly->fetchAll();
$chartLabels   = array_column($monthlyData, 'month');
$chartEarnings = array_map(fn($r) => (float)$r['total'], $monthlyData);

/* ── Portfolio — uses tbl_provider_gallery (not tbl_portfolio) ── */
$stPortfolio = $db->prepare("
    SELECT * FROM tbl_provider_gallery
    WHERE provider_id = ?
    ORDER BY sort_order ASC, created_at DESC
    LIMIT 6
");
$stPortfolio->execute([$providerId]);
$portfolioItems = $stPortfolio->fetchAll();

$stPortTotal = $db->prepare("SELECT COUNT(*) FROM tbl_provider_gallery WHERE provider_id = ?");
$stPortTotal->execute([$providerId]);
$totalPortfolio = (int)$stPortTotal->fetchColumn();

/* ── Rating breakdown from tbl_reviews ── */
$stRatingBreakdown = $db->prepare("
    SELECT rating, COUNT(*) AS cnt
    FROM tbl_reviews
    WHERE provider_id = ? AND is_visible = 1
    GROUP BY rating
    ORDER BY rating DESC
");
$stRatingBreakdown->execute([$providerId]);
$ratingBreakdown = [];
foreach ($stRatingBreakdown->fetchAll() as $row) {
    $ratingBreakdown[(int)$row['rating']] = (int)$row['cnt'];
}

/* ── Today's booked slots ── */
$stSchedule = $db->prepare("
    SELECT b.booking_time, s.name AS service_name,
           CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
           b.status
    FROM tbl_bookings b
    JOIN tbl_services s ON b.service_id  = s.id
    JOIN tbl_users u    ON b.customer_id = u.id
    WHERE b.provider_id = ?
      AND b.booking_date = CURDATE()
      AND b.status IN ('confirmed', 'pending', 'in_progress')
    ORDER BY b.booking_time ASC
    LIMIT 5
");
$stSchedule->execute([$providerId]);
$todaySlots = $stSchedule->fetchAll();

/* ── Helpers ── */
$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$initials = strtoupper(substr($bizName, 0, 2));

function fmtMoney(float $v): string {
    return $v >= 1000 ? '₱'.number_format($v/1000,1).'k' : '₱'.number_format($v,0);
}
function starFill(float $avg, int $pos): string {
    $diff = $avg - $pos + 1;
    if ($diff >= 1)   return 'full';
    if ($diff >= 0.5) return 'half';
    return 'empty';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Provider Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>
    (function(){
      var t = localStorage.getItem('qb-theme') || 'light';
      if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
    })();
  </script>
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAVBAR
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">

    <!-- Logo -->
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo"
           style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Provider</span>
    </a>

    <!-- Centre nav links -->
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"     class="pv-nav-link is-active">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/appointments"  class="pv-nav-link">
        Appointments
        <?php if ($pendingCount): ?><sup class="pv-sup"><?= $pendingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"   class="pv-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/portfolio"  class="pv-nav-link">Portfolio</a>
      <a href="<?= BASE_URL ?>provider/schedule"   class="pv-nav-link">Schedule</a>
    </div>

    <!-- Right-side controls -->
    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <!-- Theme toggle -->
      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22"  y1="4.22"  x2="5.64"  y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1"  y1="12" x2="3"  y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22"  y1="19.78" x2="5.64"  y2="18.36"/><line x1="18.36" y1="5.64"  x2="19.78" y2="4.22"/>
        </svg>
      </button>

      <!-- Profile dropdown trigger -->
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0"
           aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av">
          <?php if ($profilePhoto): ?>
            <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div class="pv-nav-user">
  <div class="pv-nav-user-name"><?= $firstName ?></div>
</div>
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

      <!-- Profile dropdown panel -->
      <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="pv-pd-header">
          <div class="pv-pd-avatar">
            <?php if ($profilePhoto): ?>
              <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $bizName ?></div>
            <div class="pv-pd-email"><?= $email ?></div>
            <span class="pv-pd-role">Provider</span>
          </div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>provider/profile" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-store"></i></span>
          <span>Business Profile</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="<?= BASE_URL ?>provider/settings" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-gear"></i></span>
          <span>Settings</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>auth/logout" class="pv-pd-item pv-pd-item--danger" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
          <span>Sign Out</span>
        </a>
      </div>

    </div>
  </div>
</nav>

<!-- ══════════════════════════════════════
     HERO BANNER
══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        <?= $greeting ?>
      </p>
      <h1 class="pv-hero-name"><?= $bizName ?></h1>
      <p class="pv-hero-sub"><?= $bizCategory ?> · <?= date('l, F j, Y') ?></p>
      <div class="pv-hero-meta">
        <span class="pv-status-badge">
          <span class="pv-status-dot" aria-hidden="true"></span>
          Active Business
        </span>
        <span class="pv-tier-badge">⭐ <?= number_format($avgRating, 1) ?> Rating</span>
      </div>
    </div>
    <div class="pv-hero-right">
      <?php if ($pendingCount > 0): ?>
      <a href="<?= BASE_URL ?>provider/appointments?status=pending" class="pv-hero-chip">
        <span class="pv-hero-chip-dot" aria-hidden="true"></span>
        <?= $pendingCount ?> pending request<?= $pendingCount > 1 ? 's' : '' ?>
        <span aria-hidden="true">→</span>
      </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>provider/appointments?date=today" class="pv-hero-chip">
        <i class="fa-regular fa-calendar-check" style="font-size:.75rem"></i>
        <?= count($todaySlots) ?> today
      </a>
    </div>
  </div>

  <!-- Stats strip -->
  <div class="pv-hero-stats" role="region" aria-label="Business quick stats">
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
      <span class="pv-hs-val green"><?= fmtMoney($totalEarnings) ?></span>
      <span class="pv-hs-label">Total Earnings</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val accent"><?= fmtMoney($monthEarnings) ?></span>
      <span class="pv-hs-label">This Month</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val yellow"><?= number_format($avgRating, 1) ?></span>
      <span class="pv-hs-label">Avg Rating</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val blue"><?= number_format($uniqueCustomers) ?></span>
      <span class="pv-hs-label">Customers Served</span>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════ -->
<main class="pv-page" role="main">

  <!-- KPI Cards — Row 1 -->
  <div class="pv-kpi-row">
    <div class="pv-kpi pv-kpi--gold">
      <div class="pv-kpi-ico"><i class="fa-solid fa-calendar-check"></i></div>
      <div class="pv-kpi-val"><?= $totalBookings ?></div>
      <div class="pv-kpi-label">Total Bookings</div>
      <div class="pv-kpi-sub"><?= $monthBookings ?> this month</div>
    </div>
    <div class="pv-kpi pv-kpi--green">
      <div class="pv-kpi-ico"><i class="fa-solid fa-peso-sign"></i></div>
      <div class="pv-kpi-val"><?= fmtMoney($totalEarnings) ?></div>
      <div class="pv-kpi-label">Total Earnings</div>
      <div class="pv-kpi-sub"><?= fmtMoney($monthEarnings) ?> this month</div>
    </div>
    <div class="pv-kpi pv-kpi--yellow">
      <div class="pv-kpi-ico"><i class="fa-solid fa-star"></i></div>
      <div class="pv-kpi-val"><?= number_format($avgRating, 1) ?></div>
      <div class="pv-kpi-label">Customer Rating</div>
      <div class="pv-kpi-sub"><?= number_format($reviewCount) ?> review<?= $reviewCount !== 1 ? 's' : '' ?></div>
    </div>
    <div class="pv-kpi pv-kpi--blue">
      <div class="pv-kpi-ico"><i class="fa-solid fa-users"></i></div>
      <div class="pv-kpi-val"><?= number_format($uniqueCustomers) ?></div>
      <div class="pv-kpi-label">Customers Served</div>
      <div class="pv-kpi-sub"><?= $completedCount ?> completed</div>
    </div>
  </div>

  <div class="pv-layout">

    <!-- ── MAIN column ── -->
    <div class="pv-main">

      <!-- Row 2 — Earnings Chart -->
      <div class="pv-card pv-card--trend">
        <div class="pv-trend-head">
          <div class="pv-trend-meta">
            <span class="pv-trend-eyebrow">LAST 6 MONTHS</span>
            <h2 class="pv-trend-title">Earnings Overview</h2>
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
          <canvas id="earningsChart"></canvas>
        </div>
      </div>

      <!-- Row 3 — Upcoming Appointments -->
      <div class="pv-card pv-card--table">
        <div class="pv-card-head">
          <h2>Upcoming Appointments</h2>
          <a href="<?= BASE_URL ?>provider/appointments" class="pv-link">View all →</a>
        </div>
        <?php if (empty($upcomingApts)): ?>
        <div class="pv-empty-state">
          <div class="pv-empty-icon" aria-hidden="true">📭</div>
          <p>No upcoming appointments yet.</p>
          <a href="<?= BASE_URL ?>provider/services" class="pv-empty-cta">Manage Services →</a>
        </div>
        <?php else: ?>
        <div class="pv-apt-table-wrap">
          <table class="pv-apt-table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Service</th>
                <th>Date</th>
                <th>Time</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($upcomingApts as $a): ?>
              <tr>
                <td>
                  <div class="pv-apt-customer">
                    <div class="pv-apt-av">
                      <?php if (!empty($a['customer_avatar'])): ?>
                        <img src="<?= htmlspecialchars($a['customer_avatar']) ?>" alt="<?= htmlspecialchars($a['customer_name']) ?>">
                      <?php else: ?>
                        <?= strtoupper(substr($a['customer_name'], 0, 2)) ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <div class="pv-apt-name"><?= htmlspecialchars($a['customer_name']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="pv-apt-name" style="font-size:.82rem"><?= htmlspecialchars($a['service_name']) ?></div>
                  <?php if (!empty($a['duration_minutes'])): ?>
                    <div class="pv-apt-service"><?= (int)$a['duration_minutes'] ?> min</div>
                  <?php endif; ?>
                </td>
                <td class="pv-apt-date"><?= date('M d, Y', strtotime($a['booking_date'])) ?></td>
                <td class="pv-apt-time">
                  <?= !empty($a['booking_time']) ? date('g:i A', strtotime($a['booking_time'])) : '—' ?>
                </td>
                <td class="pv-apt-price">₱<?= number_format((float)$a['total_amount'], 2) ?></td>
                <td>
                  <span class="pv-badge pv-badge--<?= $a['status'] ?>">
                    <?= ucfirst($a['status']) ?>
                  </span>
                </td>
                <td>
                  <div class="pv-apt-actions">
                    <?php if ($a['status'] === 'pending'): ?>
                      <button class="pv-btn-accept"
                              onclick="location.href='<?= BASE_URL ?>provider/appointments/accept/<?= (int)$a['id'] ?>'">
                        Accept
                      </button>
                      <button class="pv-btn-decline"
                              onclick="location.href='<?= BASE_URL ?>provider/appointments/decline/<?= (int)$a['id'] ?>'">
                        Decline
                      </button>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>provider/appointments/<?= (int)$a['id'] ?>" class="pv-btn-view">
                      Details
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /pv-main -->

    <!-- ── SIDEBAR column ── -->
    <aside class="pv-sidebar" aria-label="Provider sidebar">

      <!-- Quick Actions -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Quick Actions</h2></div>
        <div class="pv-actions">
          <a href="<?= BASE_URL ?>provider/services/create" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
            <div class="pv-action-txt">
              <strong>Add Service</strong>
              <span>Create a new offering</span>
            </div>
          </a>
          <a href="<?= BASE_URL ?>provider/portfolio/upload" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-images"></i></span>
            <div class="pv-action-txt"><strong>Upload Portfolio</strong><span>Showcase your work</span></div>
          </a>
          <a href="<?= BASE_URL ?>provider/schedule" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-clock"></i></span>
            <div class="pv-action-txt"><strong>Update Schedule</strong><span>Set availability</span></div>
          </a>
          <a href="<?= BASE_URL ?>provider/profile" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-store"></i></span>
            <div class="pv-action-txt"><strong>View Profile</strong><span>See public listing</span></div>
          </a>
        </div>
      </div>

      <!-- Portfolio Insights -->
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>Portfolio</h2>
          <a href="<?= BASE_URL ?>provider/portfolio" class="pv-link">Manage →</a>
        </div>
        <div class="pv-portfolio-body">
          <div class="pv-portfolio-grid">
            <?php for ($i = 0; $i < 6; $i++):
              $item = $portfolioItems[$i] ?? null; ?>
            <div class="pv-port-thumb">
              <?php if ($item && !empty($item['image_url'])): ?>
                <img src="<?= htmlspecialchars($item['image_url']) ?>"
                     alt="<?= htmlspecialchars($item['caption'] ?? 'Portfolio') ?>">
                <div class="pv-port-overlay">
                  <i class="fa-solid fa-expand pv-port-overlay-ico"></i>
                </div>
              <?php else: ?>
                <div class="pv-port-thumb-empty" aria-hidden="true">📷</div>
              <?php endif; ?>
            </div>
            <?php endfor; ?>
          </div>
          <div class="pv-port-stats">
            <div class="pv-port-stat">
              <div class="pv-port-stat-val"><?= $totalPortfolio ?></div>
              <div class="pv-port-stat-label">Uploads</div>
            </div>
            <div class="pv-port-stat">
              <div class="pv-port-stat-val"><?= $reviewCount ?></div>
              <div class="pv-port-stat-label">Reviews</div>
            </div>
            <div class="pv-port-stat">
              <div class="pv-port-stat-val"><?= $uniqueCustomers ?></div>
              <div class="pv-port-stat-label">Clients</div>
            </div>
          </div>
          <button class="pv-port-upload-btn"
                  onclick="location.href='<?= BASE_URL ?>provider/portfolio/upload'">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            Upload New Work
          </button>
        </div>
      </div>

      <!-- Schedule Status -->
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>Today's Schedule</h2>
          <a href="<?= BASE_URL ?>provider/schedule" class="pv-link">Manage →</a>
        </div>
        <div class="pv-schedule-body">
          <div class="pv-schedule-status <?= empty($todaySlots) ? 'is-closed' : '' ?>">
            <span class="pv-sched-label">
              <?= empty($todaySlots) ? 'No Bookings Today' : 'Open Today' ?>
            </span>
            <button class="pv-sched-toggle">Edit</button>
          </div>
          <?php if (!empty($todaySlots)): ?>
          <p class="pv-schedule-next">
            <?= count($todaySlots) ?> appointment<?= count($todaySlots) > 1 ? 's' : '' ?> scheduled
          </p>
          <div class="pv-schedule-slots">
            <?php foreach ($todaySlots as $slot): ?>
            <div class="pv-slot-item">
              <span class="pv-slot-time">
                <?= !empty($slot['booking_time']) ? date('g:i A', strtotime($slot['booking_time'])) : 'TBD' ?>
              </span>
              <span class="pv-slot-avail"><?= htmlspecialchars($slot['customer_name']) ?></span>
              <span class="pv-slot-dot <?= $slot['status'] === 'confirmed' ? '' : 'is-booked' ?>"></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p class="pv-schedule-next" style="text-align:center;padding:.5rem 0;opacity:.55">
            No appointments for today.
          </p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Customer Ratings -->
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>Customer Ratings</h2>
          <a href="<?= BASE_URL ?>provider/reviews" class="pv-link">All reviews →</a>
        </div>
        <div class="pv-rating-body">
          <div class="pv-rating-score">
            <div class="pv-rating-big"><?= number_format($avgRating, 1) ?></div>
            <div>
              <div class="pv-rating-stars">
                <?php for ($s = 1; $s <= 5; $s++):
                  $fill = starFill($avgRating, $s);
                ?>
                  <span class="pv-star <?= $fill === 'empty' ? 'half' : '' ?>" aria-hidden="true">
                    <?= $fill === 'full' ? '★' : ($fill === 'half' ? '½' : '☆') ?>
                  </span>
                <?php endfor; ?>
              </div>
              <span class="pv-rating-count"><?= $reviewCount ?> review<?= $reviewCount !== 1 ? 's' : '' ?></span>
            </div>
          </div>
          <div class="pv-rating-bars">
            <?php for ($r = 5; $r >= 1; $r--):
              $cnt = $ratingBreakdown[$r] ?? 0;
              $pct = $reviewCount > 0 ? round($cnt / $reviewCount * 100) : 0;
            ?>
            <div class="pv-rating-row">
              <span class="pv-rating-row-label"><?= $r ?></span>
              <div class="pv-rating-bar-bg">
                <div class="pv-rating-bar-fill" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="pv-rating-row-pct"><?= $pct ?>%</span>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

    </aside>

  </div><!-- /pv-layout -->

</main>

<!-- ══════════════════════════════════════
     SCRIPTS
══════════════════════════════════════ -->
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
      document.documentElement.setAttribute('data-theme','dark');
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
      if (chart) {
        setTimeout(function() {
          var g = buildGradients();
          var c = getColors();
          chart.data.datasets[0].backgroundColor = g.gradGold;
          chart.data.datasets[0].borderColor      = c.borderColor;
          chart.data.datasets[1].backgroundColor  = g.gradWarm;
          chart.options.scales.x.grid.color       = c.gridColor;
          chart.options.scales.x.ticks.color      = c.xTickColor;
          chart.options.scales.y.grid.color       = c.gridColor;
          chart.options.scales.y.ticks.color      = c.yTickColor;
          chart.update();
        }, 50);
      }
    });
  }
})();
</script>

<script>
/* ── EARNINGS CHART ── */
(function () {
  var labels   = <?= json_encode(array_values($chartLabels)) ?>;
  var earnings = <?= json_encode(array_values($chartEarnings)) ?>;

  var ctx = document.getElementById('earningsChart');
  if (!ctx) return;
  var chart2d = ctx.getContext('2d');
  var hoveredIdx = null;

  var isDark = function() { return document.documentElement.getAttribute('data-theme') === 'dark'; };

  window.buildGradients = function() {
    var dark = isDark();
    var gradGold = chart2d.createLinearGradient(0,0,0,220);
    if (dark) {
      gradGold.addColorStop(0,   'rgba(201,168,76,0.55)');
      gradGold.addColorStop(0.55,'rgba(201,168,76,0.22)');
      gradGold.addColorStop(1,   'rgba(201,168,76,0.00)');
    } else {
      gradGold.addColorStop(0,   'rgba(201,168,76,0.38)');
      gradGold.addColorStop(0.55,'rgba(201,168,76,0.16)');
      gradGold.addColorStop(1,   'rgba(201,168,76,0.00)');
    }
    var gradWarm = chart2d.createLinearGradient(0,0,0,220);
    if (dark) {
      gradWarm.addColorStop(0,   'rgba(139,110,32,0.30)');
      gradWarm.addColorStop(0.6, 'rgba(139,110,32,0.10)');
      gradWarm.addColorStop(1,   'rgba(139,110,32,0.00)');
    } else {
      gradWarm.addColorStop(0,   'rgba(232,201,106,0.20)');
      gradWarm.addColorStop(0.6, 'rgba(232,201,106,0.08)');
      gradWarm.addColorStop(1,   'rgba(232,201,106,0.00)');
    }
    return { gradGold: gradGold, gradWarm: gradWarm };
  };

  window.getColors = function() {
    var dark = isDark();
    return {
      borderColor: dark ? '#C9A84C' : '#8B6E20',
      xTickColor:  dark ? 'rgba(237,227,204,0.35)' : 'rgba(28,23,16,0.40)',
      yTickColor:  dark ? 'rgba(237,227,204,0.30)' : 'rgba(28,23,16,0.38)',
      gridColor:   dark ? 'rgba(201,168,76,0.08)'  : 'rgba(201,168,76,0.10)',
    };
  };

  var hoverPlugin = {
    id: 'hoverDotTooltip',
    afterDraw: function(c) {
      if (hoveredIdx === null) return;
      var ctx2 = c.ctx, scales = c.scales, chartArea = c.chartArea;
      var i = hoveredIdx, x = scales.x.getPixelForValue(i), y = scales.y.getPixelForValue(earnings[i]);
      var label = labels[i] || '', raw = earnings[i] || 0;

      ctx2.save();
      ctx2.beginPath(); ctx2.setLineDash([4,4]);
      ctx2.strokeStyle = 'rgba(201,168,76,0.50)'; ctx2.lineWidth = 1.5;
      ctx2.moveTo(x, chartArea.top); ctx2.lineTo(x, chartArea.bottom); ctx2.stroke();
      ctx2.setLineDash([]);

      ctx2.beginPath(); ctx2.arc(x,y,7,0,Math.PI*2);
      ctx2.fillStyle = '#1C1710'; ctx2.fill();
      ctx2.beginPath(); ctx2.arc(x,y,4,0,Math.PI*2);
      ctx2.fillStyle = '#E8C96A'; ctx2.fill();

      var sym = '₱ ', num = Number(raw).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
      ctx2.font = "500 10px 'DM Mono', monospace";
      var labelW = ctx2.measureText(label).width;
      ctx2.font = "700 11px 'DM Mono', monospace";
      var symW = ctx2.measureText(sym).width, numW = ctx2.measureText(num).width;
      var amountW = symW + numW, padX = 10, padY = 6;
      var pw = Math.max(labelW, amountW) + padX*2, ph = 40, rx = 8;
      var px = Math.max(chartArea.left, Math.min(x - pw/2, chartArea.right - pw));
      var py = Math.max(chartArea.top+4, y - ph - 20);
      var dark = isDark();

      ctx2.beginPath(); ctx2.roundRect(px,py,pw,ph,rx);
      ctx2.fillStyle = dark ? '#1E2535' : '#FFFFFF';
      ctx2.shadowColor = 'rgba(0,0,0,0.18)'; ctx2.shadowBlur = 16; ctx2.shadowOffsetY = 5;
      ctx2.fill(); ctx2.shadowBlur = 0; ctx2.shadowOffsetY = 0;
      ctx2.beginPath(); ctx2.roundRect(px,py,pw,ph,rx);
      ctx2.strokeStyle = 'rgba(201,168,76,0.45)'; ctx2.lineWidth = 1; ctx2.stroke();

      ctx2.font = "500 10px 'DM Mono', monospace";
      ctx2.fillStyle = dark ? 'rgba(237,227,204,0.45)' : 'rgba(28,23,16,0.45)';
      ctx2.textAlign = 'center'; ctx2.textBaseline = 'middle';
      ctx2.fillText(label.toUpperCase(), px+pw/2, py+11);
      ctx2.beginPath(); ctx2.moveTo(px+8,py+20); ctx2.lineTo(px+pw-8,py+20);
      ctx2.strokeStyle = 'rgba(201,168,76,0.20)'; ctx2.lineWidth = 1; ctx2.stroke();
      var startX = px+pw/2-amountW/2;
      ctx2.textAlign = 'left'; ctx2.textBaseline = 'middle';
      ctx2.font = "700 11px 'DM Mono', monospace";
      ctx2.fillStyle = '#A88A38'; ctx2.fillText(sym, startX, py+31);
      ctx2.fillStyle = dark ? '#EDE3CC' : '#1C1710'; ctx2.fillText(num, startX+symW, py+31);
      ctx2.restore();
    }
  };

  var g = buildGradients(), c = getColors();
  window.chart = new Chart(ctx, {
    type: 'line',
    plugins: [hoverPlugin],
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Earnings (₱)', data: earnings,
          borderColor: c.borderColor, backgroundColor: g.gradGold,
          borderWidth: 2.5, tension: 0.48, fill: true, pointRadius: 0, pointHoverRadius: 0, order: 1,
        },
        {
          label: '_bg',
          data: earnings.map(function(v,i){ var shift=Math.sin((i/(Math.max(earnings.length-1,1)))*Math.PI*1.5)*Math.max.apply(null,earnings.concat([1]))*0.18; return Math.max(0,v-shift); }),
          borderColor: 'transparent', backgroundColor: g.gradWarm,
          borderWidth: 0, tension: 0.48, fill: true, pointRadius: 0, pointHoverRadius: 0, order: 2,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false }, tooltip: { enabled: false } },
      scales: {
        x: { grid: { color: c.gridColor, lineWidth: 1, drawBorder: false }, ticks: { color: c.xTickColor, font: { family:"'DM Mono',monospace", size: 10, weight: '500' }, maxRotation: 0 }, border: { display: false } },
        y: { min: 0, ticks: { color: c.yTickColor, font: { family:"'DM Mono',monospace", size: 10 }, callback: function(v){ return v; }, maxTicksLimit: 8 }, grid: { color: c.gridColor, lineWidth: 1, drawBorder: false }, border: { display: false } }
      }
    }
  });

  ctx.addEventListener('mousemove', function(e) {
    var rect = ctx.getBoundingClientRect(), mouseX = e.clientX - rect.left;
    var xScale = window.chart.scales.x, closest = null, minDist = Infinity;
    labels.forEach(function(_,i) { var px = xScale.getPixelForValue(i), dist = Math.abs(mouseX-px); if (dist<minDist){ minDist=dist; closest=i; } });
    if (hoveredIdx !== closest) { hoveredIdx = closest; window.chart.draw(); }
  });
  ctx.addEventListener('mouseleave', function() { hoveredIdx = null; window.chart.draw(); });

  document.querySelectorAll('.pv-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      tab.closest('.pv-tabs').querySelectorAll('.pv-tab').forEach(function(t){ t.classList.remove('active'); });
      tab.classList.add('active');
    });
  });
})();
</script>

<script>
/* ── PROFILE DROPDOWN ── */
(function () {
  var trigger  = document.getElementById('profileTrigger');
  var dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;

  function open()   { trigger.classList.add('is-open'); dropdown.classList.add('is-open'); trigger.setAttribute('aria-expanded','true'); }
  function close()  { trigger.classList.remove('is-open'); dropdown.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); }
  function toggle() { dropdown.classList.contains('is-open') ? close() : open(); }

  trigger.addEventListener('click', function(e){ e.stopPropagation(); toggle(); });
  trigger.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); toggle(); } if(e.key==='Escape') close(); });
  document.addEventListener('click', function(e){ if(!dropdown.contains(e.target)&&!trigger.contains(e.target)) close(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
})();
</script>

</body>
</html>