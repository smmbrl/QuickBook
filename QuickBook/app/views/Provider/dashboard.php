<?php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$providerId   = $_SESSION['user_id'] ?? 0;
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');

$profile = $db->prepare("SELECT * FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
$profile->execute([$providerId]);
$profile   = $profile->fetch();
$profileId = $profile['id'] ?? 0;

/* ── Core counts ── */
$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ?");
$stmt->execute([$profileId]);
$totalBookings = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$profileId]);
$todayBookings = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings WHERE provider_id = ? AND status = 'completed'");
$stmt->execute([$profileId]);
$totalRevenue = (float)$stmt->fetchColumn();

/* Month-over-month revenue for delta badge */
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings
    WHERE provider_id = ? AND status = 'completed'
      AND MONTH(booking_date) = MONTH(CURDATE())
      AND YEAR(booking_date)  = YEAR(CURDATE())
");
$stmt->execute([$profileId]);
$thisMonthRevenue = (float)$stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings
    WHERE provider_id = ? AND status = 'completed'
      AND MONTH(booking_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND YEAR(booking_date)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
");
$stmt->execute([$profileId]);
$lastMonthRevenue = (float)$stmt->fetchColumn();

$revDelta   = $lastMonthRevenue > 0 ? round(($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100) : null;
$revDeltaPos = $revDelta !== null && $revDelta >= 0;

/* ── Status counts ── */
$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$profileId]);
$pendingBookings = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'confirmed'");
$stmt->execute([$profileId]);
$confirmedBookings = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'completed'");
$stmt->execute([$profileId]);
$completedBookings = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'cancelled'");
$stmt->execute([$profileId]);
$cancelledBookings = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_services WHERE provider_id = ? AND is_active = 1");
$stmt->execute([$profileId]);
$totalServices = (int)$stmt->fetchColumn();

/* ── Recent bookings ── */
$stmt = $db->prepare("
    SELECT b.id, b.booking_date, b.status, b.total_amount, b.created_at,
           u.first_name, u.last_name,
           s.name AS service_name
    FROM tbl_bookings b
    JOIN tbl_users u ON u.id = b.customer_id
    JOIN tbl_services s ON s.id = b.service_id
    WHERE b.provider_id = ?
    ORDER BY b.created_at DESC LIMIT 8
");
$stmt->execute([$profileId]);
$recentBookings = $stmt->fetchAll();

/* ── Status breakdown ── */
$stmt = $db->prepare("SELECT status, COUNT(*) AS cnt FROM tbl_bookings WHERE provider_id = ? GROUP BY status");
$stmt->execute([$profileId]);
$statusCounts = [];
foreach ($stmt->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int)$row['cnt'];
}

/* ── 6-month revenue trend ── */
$stmt = $db->prepare("
    SELECT DATE_FORMAT(booking_date,'%b') AS month,
           DATE_FORMAT(booking_date,'%Y-%m') AS ym,
           COALESCE(SUM(total_amount),0) AS revenue
    FROM tbl_bookings
    WHERE provider_id = ?
      AND status = 'completed'
      AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(booking_date,'%Y-%m')
    ORDER BY MIN(booking_date) ASC
");
$stmt->execute([$profileId]);
$revTrend = $stmt->fetchAll();
$currentYM = date('Y-m');

/* ── Average rating ── */
$stmt = $db->prepare("
    SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS total_reviews
    FROM tbl_reviews WHERE provider_id = ?
");
$stmt->execute([$profileId]);
$reviewData = $stmt->fetch();
$avgRating  = round((float)($reviewData['avg_rating'] ?? 0), 1);
$totalReviews = (int)($reviewData['total_reviews'] ?? 0);

/* Rating distribution (1-5) */
$ratingDist = [];
for ($i = 5; $i >= 1; $i--) {
    $s = $db->prepare("SELECT COUNT(*) FROM tbl_reviews WHERE provider_id = ? AND rating = ?");
    $s->execute([$profileId, $i]);
    $ratingDist[$i] = (int)$s->fetchColumn();
}

/* ── Greeting ── */
$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$initials = strtoupper(substr($providerName, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Provider Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_dashboard.css">
</head>
<body>

<!-- GRAIN -->
<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAV
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">

    <a href="<?= BASE_URL ?>provider/dashboard" class="pv-logo">
      Quick<span>Book</span><span class="pv-logo-badge">Provider</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="pv-nav-link is-active">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/bookings"     class="pv-nav-link">
        Bookings
        <?php if ($pendingBookings): ?>
          <sup class="pv-sup"><?= $pendingBookings ?></sup>
        <?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"     class="pv-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/availability" class="pv-nav-link">Availability</a>
      <a href="<?= BASE_URL ?>provider/profile"      class="pv-nav-link">Profile</a>
    </div>

    <div class="pv-nav-end">
      <!-- Notification bell -->
      <button class="pv-notif-btn" aria-label="Notifications">
        🔔
        <?php if ($pendingBookings): ?>
          <span class="pv-notif-dot" aria-hidden="true"></span>
        <?php endif; ?>
      </button>

      <div class="pv-nav-user">
        <div class="pv-nav-av" aria-hidden="true"><?= $initials ?></div>
        <span><?= $providerName ?></span>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>

  </div>
</nav>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>

  <div class="pv-hero-inner">
    <div class="pv-hero-text">
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        <?= $greeting ?>
      </p>
      <h1 class="pv-hero-name"><?= $providerName ?></h1>
      <p class="pv-hero-date"><?= date('l, F j, Y') ?></p>
      <div class="pv-hero-meta">
        <span class="pv-status-badge">
          <span class="pv-status-dot" aria-hidden="true"></span>
          Active &amp; Accepting
        </span>
        <?php if ($avgRating > 0): ?>
        <span class="pv-status-badge" style="background:var(--gold-soft);border-color:var(--gold-border);color:var(--gold-bright);">
          ★ <?= $avgRating ?> rating
        </span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($pendingBookings > 0): ?>
    <a href="<?= BASE_URL ?>provider/bookings?status=pending" class="pv-pending-chip">
      <span class="pv-pending-dot" aria-hidden="true"></span>
      <?= $pendingBookings ?> booking<?= $pendingBookings > 1 ? 's' : '' ?> need your confirmation
      <span aria-hidden="true">→</span>
    </a>
    <?php endif; ?>
  </div>

  <!-- STAT STRIP -->
  <div class="pv-hero-stats" role="region" aria-label="Key metrics">
    <div class="pv-hs-item">
      <span class="pv-hs-val">₱<?= number_format($totalRevenue, 0) ?></span>
      <span class="pv-hs-label">Total Revenue</span>
      <?php if ($revDelta !== null): ?>
      <span class="pv-hs-change <?= $revDeltaPos ? '' : 'neg' ?>">
        <?= $revDeltaPos ? '↑' : '↓' ?> <?= abs($revDelta) ?>% vs last month
      </span>
      <?php endif; ?>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>

    <div class="pv-hs-item">
      <span class="pv-hs-val"><?= $totalBookings ?></span>
      <span class="pv-hs-label">All Bookings</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>

    <div class="pv-hs-item">
      <span class="pv-hs-val accent"><?= $pendingBookings ?></span>
      <span class="pv-hs-label">Pending</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>

    <div class="pv-hs-item">
      <span class="pv-hs-val green"><?= $completedBookings ?></span>
      <span class="pv-hs-label">Completed</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>

    <div class="pv-hs-item">
      <span class="pv-hs-val"><?= $totalServices ?></span>
      <span class="pv-hs-label">Active Services</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>

    <div class="pv-hs-item">
      <span class="pv-hs-val">+<?= $todayBookings ?></span>
      <span class="pv-hs-label">Today</span>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════ -->
<main class="pv-page" role="main">
  <div class="pv-layout">

    <!-- ── LEFT COLUMN ── -->
    <div class="pv-main">

      <!-- KPI Cards Row -->
      <div class="pv-kpi-row" role="region" aria-label="Performance overview">

        <!-- Revenue this month -->
        <div class="pv-kpi pv-kpi--gold">
          <div class="pv-kpi-icon">💰</div>
          <?php if ($revDelta !== null): ?>
          <span class="pv-kpi-delta <?= $revDeltaPos ? '' : 'neg' ?>">
            <?= $revDeltaPos ? '+' : '' ?><?= $revDelta ?>%
          </span>
          <?php endif; ?>
          <div class="pv-kpi-val">₱<?= number_format($thisMonthRevenue, 0) ?></div>
          <div class="pv-kpi-label">This Month's Revenue</div>
        </div>

        <!-- Completed -->
        <div class="pv-kpi pv-kpi--green">
          <div class="pv-kpi-icon">✅</div>
          <div class="pv-kpi-val"><?= $completedBookings ?></div>
          <div class="pv-kpi-label">Completed Bookings</div>
        </div>

        <!-- Pending -->
        <div class="pv-kpi pv-kpi--indigo">
          <div class="pv-kpi-icon">⏳</div>
          <div class="pv-kpi-val"><?= $pendingBookings ?></div>
          <div class="pv-kpi-label">Awaiting Confirmation</div>
        </div>

        <!-- Cancellation rate -->
        <?php $cancelRate = $totalBookings > 0 ? round($cancelledBookings / $totalBookings * 100) : 0; ?>
        <div class="pv-kpi pv-kpi--blue">
          <div class="pv-kpi-icon">📊</div>
          <div class="pv-kpi-val"><?= $cancelRate ?>%</div>
          <div class="pv-kpi-label">Cancellation Rate</div>
        </div>

      </div>

      <!-- Charts Row -->
      <div class="pv-row2">

        <!-- Revenue Trend -->
        <div class="pv-card">
          <div class="pv-card-head">
            <div>
              <h2>Revenue Trend</h2>
              <span class="pv-card-sub">Last 6 months · completed bookings</span>
            </div>
          </div>
          <div class="pv-chart-area">
            <div class="pv-chart-legend">
              <span class="pv-chart-legend-item">
                <span class="pv-chart-legend-swatch" style="background:var(--gold)"></span>
                Revenue
              </span>
              <span class="pv-chart-legend-item">
                <span class="pv-chart-legend-swatch" style="background:var(--faint)"></span>
                Current month
              </span>
            </div>
            <div class="pv-chart" role="img" aria-label="Revenue bar chart">
              <?php if (empty($revTrend)): ?>
                <p class="pv-chart-empty">No revenue data yet — complete your first booking to see trends.</p>
              <?php else:
                $maxRev = max(1, max(array_column($revTrend, 'revenue')));
                foreach ($revTrend as $r):
                  $h        = round(($r['revenue'] / $maxRev) * 130);
                  $isCurrent = $r['ym'] === $currentYM;
              ?>
              <div class="pv-bar-col <?= $isCurrent ? 'is-current' : '' ?>">
                <span class="pv-bar-val">₱<?= number_format($r['revenue'] / 1000, 1) ?>k</span>
                <div class="pv-bar" style="height:<?= max($h, 6) ?>px"
                     role="presentation"
                     title="<?= $r['month'] ?>: ₱<?= number_format($r['revenue'], 0) ?>"></div>
                <span class="pv-bar-mo"><?= $r['month'] ?></span>
              </div>
              <?php endforeach; endif; ?>
            </div>
          </div>
        </div>

        <!-- Booking Breakdown -->
        <div class="pv-card">
          <div class="pv-card-head">
            <h2>Booking Status</h2>
            <a href="<?= BASE_URL ?>provider/bookings" class="pv-link">View all →</a>
          </div>
          <div class="pv-breakdown">
            <?php
            $bsMeta = [
              'pending'     => ['Pending',     '#C9A84C'],
              'confirmed'   => ['Confirmed',   '#22C55E'],
              'in_progress' => ['In Progress', '#818CF8'],
              'completed'   => ['Completed',   '#38BDF8'],
              'cancelled'   => ['Cancelled',   '#F43F5E'],
            ];
            $total = max(1, $totalBookings);
            foreach ($bsMeta as $key => [$label, $color]):
              $count = $statusCounts[$key] ?? 0;
              $pct   = round($count / $total * 100);
            ?>
            <div class="pv-bd-row">
              <div class="pv-bd-left">
                <span class="pv-bd-dot" style="background:<?= $color ?>" aria-hidden="true"></span>
                <span class="pv-bd-lbl"><?= $label ?></span>
              </div>
              <div class="pv-bd-track" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="pv-bd-fill" style="width:<?= max($pct, 1) ?>%;background:<?= $color ?>"></div>
              </div>
              <span class="pv-bd-n"><?= $count ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /pv-row2 -->

      <!-- Recent Bookings Table -->
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>Recent Bookings</h2>
          <a href="<?= BASE_URL ?>provider/bookings" class="pv-link">View all →</a>
        </div>

        <!-- Filter tabs -->
        <div class="pv-table-tabs" role="tablist" aria-label="Filter bookings">
          <button class="pv-tab is-active" role="tab" aria-selected="true">All</button>
          <button class="pv-tab" role="tab" aria-selected="false">Pending</button>
          <button class="pv-tab" role="tab" aria-selected="false">Confirmed</button>
          <button class="pv-tab" role="tab" aria-selected="false">Completed</button>
          <button class="pv-tab" role="tab" aria-selected="false">Cancelled</button>
        </div>

        <div class="pv-table-wrap">
          <table class="pv-table" aria-label="Recent booking records">
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Customer</th>
                <th scope="col">Service</th>
                <th scope="col">Date</th>
                <th scope="col">Amount</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentBookings)): ?>
              <tr>
                <td colspan="6" class="pv-empty">
                  No bookings yet — share your profile to get your first client!
                </td>
              </tr>
              <?php else: foreach ($recentBookings as $b): ?>
              <tr>
                <td class="pv-table-id">#<?= $b['id'] ?></td>
                <td>
                  <div class="pv-cust">
                    <div class="pv-cust-av" aria-hidden="true">
                      <?= strtoupper(substr($b['first_name'], 0, 1) . substr($b['last_name'], 0, 1)) ?>
                    </div>
                    <span class="pv-cust-name">
                      <?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?>
                    </span>
                  </div>
                </td>
                <td class="pv-trunc" title="<?= htmlspecialchars($b['service_name']) ?>">
                  <?= htmlspecialchars($b['service_name']) ?>
                </td>
                <td class="mono muted"><?= date('M d, Y', strtotime($b['booking_date'])) ?></td>
                <td class="pv-amount">₱<?= number_format($b['total_amount'], 2) ?></td>
                <td>
                  <span class="pv-pill pv-pill--<?= $b['status'] ?>">
                    <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /pv-main -->

    <!-- ── SIDEBAR ── -->
    <aside class="pv-sidebar" aria-label="Sidebar">

      <!-- Quick Actions -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Quick Actions</h2></div>
        <div class="pv-actions">

          <a href="<?= BASE_URL ?>provider/bookings?status=pending" class="pv-action is-primary">
            <span class="pv-action-ico" aria-hidden="true">⏳</span>
            <div class="pv-action-txt">
              <strong>Pending Bookings</strong>
              <span>Review &amp; confirm</span>
            </div>
            <?php if ($pendingBookings): ?>
            <span class="pv-action-badge" aria-label="<?= $pendingBookings ?> pending">
              <?= $pendingBookings ?>
            </span>
            <?php endif; ?>
          </a>

          <a href="<?= BASE_URL ?>provider/services" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true">🛎️</span>
            <div class="pv-action-txt"><strong>My Services</strong><span>Manage listings</span></div>
          </a>

          <a href="<?= BASE_URL ?>provider/availability" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true">📅</span>
            <div class="pv-action-txt"><strong>Availability</strong><span>Set your schedule</span></div>
          </a>

          <a href="<?= BASE_URL ?>provider/profile" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true">👤</span>
            <div class="pv-action-txt"><strong>My Profile</strong><span>Edit information</span></div>
          </a>

        </div>
      </div>

      <!-- Today's Snapshot -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Today's Snapshot</h2></div>
        <div class="pv-snap">
          <div class="pv-snap-item">
            <div class="pv-snap-ico-wrap" aria-hidden="true">📥</div>
            <div>
              <strong><?= $todayBookings ?></strong>
              <span>New booking<?= $todayBookings !== 1 ? 's' : '' ?></span>
            </div>
            <?php if ($todayBookings > 0): ?>
            <span class="pv-snap-trend">+<?= $todayBookings ?></span>
            <?php endif; ?>
          </div>
          <div class="pv-snap-item">
            <div class="pv-snap-ico-wrap" aria-hidden="true">✅</div>
            <div>
              <strong><?= $confirmedBookings ?></strong>
              <span>Confirmed</span>
            </div>
          </div>
          <div class="pv-snap-item">
            <div class="pv-snap-ico-wrap" aria-hidden="true">🏅</div>
            <div>
              <strong><?= $completedBookings ?></strong>
              <span>Completed</span>
            </div>
          </div>
          <div class="pv-snap-item">
            <div class="pv-snap-ico-wrap" aria-hidden="true">❌</div>
            <div>
              <strong><?= $cancelledBookings ?></strong>
              <span>Cancelled</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Rating Widget -->
      <?php if ($totalReviews > 0): ?>
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>Rating</h2>
          <span class="pv-card-sub"><?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></span>
        </div>
        <div class="pv-rating-body">
          <div class="pv-rating-score">
            <span class="pv-rating-big"><?= $avgRating ?></span>
            <span class="pv-rating-max">/ 5.0</span>
          </div>
          <div class="pv-stars" aria-label="<?= $avgRating ?> out of 5 stars">
            <?php
            $full  = floor($avgRating);
            $half  = ($avgRating - $full) >= 0.5;
            for ($i = 1; $i <= 5; $i++):
              if ($i <= $full)          echo '★';
              elseif ($i === $full + 1 && $half) echo '⭐';
              else                      echo '<span style="opacity:.25">★</span>';
            endfor;
            ?>
          </div>
          <div class="pv-rating-bars">
            <?php for ($star = 5; $star >= 1; $star--):
              $cnt = $ratingDist[$star] ?? 0;
              $pct = $totalReviews > 0 ? round($cnt / $totalReviews * 100) : 0;
            ?>
            <div class="pv-rbar-row">
              <span class="pv-rbar-lbl"><?= $star ?></span>
              <div class="pv-rbar-track">
                <div class="pv-rbar-fill" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="pv-rbar-n"><?= $cnt ?></span>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </aside>

  </div>
</main>

<script>
/* ── Tab filter (client-side UX, no page reload) ── */
(function () {
  var tabs  = document.querySelectorAll('.pv-tab');
  var rows  = document.querySelectorAll('.pv-table tbody tr[data-status]');

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      tabs.forEach(function (t) {
        t.classList.remove('is-active');
        t.setAttribute('aria-selected', 'false');
      });
      tab.classList.add('is-active');
      tab.setAttribute('aria-selected', 'true');

      var filter = tab.textContent.trim().toLowerCase().replace(' ', '_');
      rows.forEach(function (row) {
        if (filter === 'all' || row.dataset.status === filter) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });
})();
</script>

</body>
</html>