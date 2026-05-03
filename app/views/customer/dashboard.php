<?php

$name   = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$email  = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

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

$loyaltyTier = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};
$nextLevel = 500;
$progress  = min(100, round(($loyaltyPoints % $nextLevel) / $nextLevel * 100));
$ptsToNext = $nextLevel - ($loyaltyPoints % $nextLevel);

$stRecent = $db->prepare("
    SELECT b.*, pp.business_name, pp.profile_photo,
           s.name AS service_name, s.price,
           s.service_type, s.duration_minutes, s.location_type
    FROM tbl_bookings b
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    JOIN tbl_services s           ON b.service_id  = s.id
    WHERE b.customer_id = ?
    ORDER BY b.created_at DESC LIMIT 5
");
$stRecent->execute([$userId]);
$recentBookings = $stRecent->fetchAll();

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

$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$initials = strtoupper(substr($name, 0, 2));
$stAv = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAv->execute([$userId]);
$avatarUrl = ($av = $stAv->fetchColumn()) ? BASE_URL . 'assets/uploads/profiles/' . htmlspecialchars($av) : null;

$imageMap = [
    'Barber'        => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=80&h=80&fit=crop&q=70',
    'Hair Stylist'  => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=80&h=80&fit=crop&q=70',
    'Nail Tech'     => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=80&h=80&fit=crop&q=70',
    'Massage'       => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=80&h=80&fit=crop&q=70',
    'Skincare'      => 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=80&h=80&fit=crop&q=70',
    'Fitness'       => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=80&h=80&fit=crop&q=70',
    'Home Cleaning' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=80&h=80&fit=crop&q=70',
    'Pet Groomer'   => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=80&h=80&fit=crop&q=70',
    'Event Stylist' => 'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=80&h=80&fit=crop&q=70',
    'Makeup'        => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=80&h=80&fit=crop&q=70',
];
function pvServiceImage(string $type, array $map): string {
    return $map[$type] ?? 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=80&h=80&fit=crop&q=70';
}

$chartLabels   = array_column($monthlyData, 'month');
$chartSpend    = array_map(fn($r) => (float)$r['total'], $monthlyData);
$chartBookings = array_map(fn($r) => (int)$r['cnt'],     $monthlyData);

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
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">

    <a href="<?= BASE_URL ?>home" class="pv-logo">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard"    class="pv-nav-link is-active">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings"     class="pv-nav-link">
        Bookings
        <?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse"       class="pv-nav-link">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty"      class="pv-nav-link">Loyalty</a>
      <a href="<?= BASE_URL ?>profile"      class="pv-nav-link">Profile</a>
    </div>

    <div class="pv-nav-end">
      <button class="pv-notif-btn" aria-label="Notifications">
        <i class="fa-solid fa-bell"></i>
        <span class="pv-notif-dot" aria-hidden="true"></span>
      </button>
      <div class="pv-nav-av" aria-hidden="true">
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
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
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

  <div class="pv-kpi-row" role="region" aria-label="Performance overview">

    <div class="pv-kpi pv-kpi--gold">
      <div class="pv-kpi-val"><?= $totalBookings ?></div>
      <div class="pv-kpi-label">Total Bookings</div>
      <div class="pv-kpi-sub">All time</div>
    </div>

    <div class="pv-kpi pv-kpi--green">
      <div class="pv-kpi-val"><?= $upcomingCount ?></div>
      <div class="pv-kpi-label">Upcoming</div>
      <div class="pv-kpi-sub">Pending &amp; confirmed</div>
    </div>

    <div class="pv-kpi pv-kpi--blue">
      <div class="pv-kpi-val"><?= number_format($loyaltyPoints) ?></div>
      <div class="pv-kpi-label">Loyalty Points</div>
      <div class="pv-kpi-sub"><?= $loyaltyTier ?> tier</div>
    </div>

    <div class="pv-kpi pv-kpi--indigo">
      <div class="pv-kpi-val"><?= $spentDisplay ?></div>
      <div class="pv-kpi-label">Total Spent</div>
      <div class="pv-kpi-sub"><?= $completedCount ?> completed</div>
    </div>

  </div>

  <div class="pv-layout">


    <div class="pv-main">

      <div class="pv-card">
        <div class="pv-card-head">
          <div>
            <h2>Spending Overview</h2>
            <span class="pv-card-sub">Last 6 months · completed bookings</span>
          </div>
          <div class="pv-tabs">
            <span class="pv-tab active">6M</span>
            <span class="pv-tab">1Y</span>
            <span class="pv-tab">All</span>
          </div>
        </div>
        <div class="pv-chart-wrap">
          <div class="pv-chart-canvas">
            <canvas id="spendChart"></canvas>
          </div>
        </div>
      </div>

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
                <th>Type</th>
                <th>Price</th>
                <th>Duration</th>
                <th>Location</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentBookings as $b):
                $dm      = (int)($b['duration_minutes'] ?? 0);
                $dLabel  = $dm ? (($dm >= 60 && $dm % 60 === 0) ? ($dm/60).' hr' : $dm.' min') : '—';
                $imgSrc  = pvServiceImage($b['service_type'] ?? '', $imageMap);
              ?>
              <tr>
                <td>
                  <div class="pv-rb-name">
                    <div class="pv-rb-icon">
                      <img src="<?= $imgSrc ?>" alt="">
                    </div>
                    <?= htmlspecialchars($b['service_name']) ?>
                  </div>
                </td>
                <td><?= htmlspecialchars($b['service_type'] ?? '—') ?></td>
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

    </div>

    <aside class="pv-sidebar" aria-label="Sidebar">

  
      <div class="pv-card">
        <div class="pv-card-head"><h2>Quick Actions</h2></div>
        <div class="pv-actions">
          <a href="<?= BASE_URL ?>bookings?status=pending" class="pv-action is-primary">
            <span class="pv-action-ico" aria-hidden="true">📅</span>
            <div class="pv-action-txt">
              <strong>Pending Bookings</strong>
              <span>Review &amp; manage</span>
            </div>
          </a>
          <a href="<?= BASE_URL ?>browse" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true">🔍</span>
            <div class="pv-action-txt"><strong>Browse Services</strong><span>Find providers near you</span></div>
          </a>
          <a href="<?= BASE_URL ?>loyalty" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true">⭐</span>
            <div class="pv-action-txt"><strong>Loyalty Points</strong><span>Redeem <?= number_format($loyaltyPoints) ?> pts</span></div>
          </a>
          <a href="<?= BASE_URL ?>profile" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true">👤</span>
            <div class="pv-action-txt"><strong>My Profile</strong><span>Update account details</span></div>
          </a>
        </div>
      </div>

      <div class="pv-card">
        <div class="pv-card-head"><h2>Today's Snapshot</h2></div>
        <div class="pv-snap">
          <div class="pv-snap-item">
            <div class="pv-snap-ico" aria-hidden="true">📥</div>
            <div>
              <div class="pv-snap-val"><?= $upcomingCount ?></div>
              <div class="pv-snap-label">Upcoming booking<?= $upcomingCount !== 1 ? 's' : '' ?></div>
            </div>
          </div>
          <div class="pv-snap-item">
            <div class="pv-snap-ico" aria-hidden="true">✅</div>
            <div>
              <div class="pv-snap-val"><?= $completedCount ?></div>
              <div class="pv-snap-label">Completed</div>
            </div>
          </div>
          <div class="pv-snap-item">
            <div class="pv-snap-ico" aria-hidden="true">⭐</div>
            <div>
              <div class="pv-snap-val"><?= number_format($loyaltyPoints) ?></div>
              <div class="pv-snap-label">Loyalty points</div>
            </div>
          </div>
        </div>
      </div>

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

  </div>

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
(function () {
  const labels = <?= json_encode(array_values($chartLabels)) ?>;
  const spend  = <?= json_encode(array_values($chartSpend)) ?>;
  const booked = <?= json_encode(array_values($chartBookings)) ?>;

  const ctx = document.getElementById('spendChart');
  if (!ctx) return;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Spending (₱)',
          data: spend,
          backgroundColor: 'rgba(201,168,76,.22)',
          borderColor: '#C9A84C',
          borderWidth: 1.5,
          borderRadius: 5,
          borderSkipped: false,
          yAxisID: 'y',
        },
        {
          label: 'Bookings',
          data: booked,
          type: 'line',
          yAxisID: 'y1',
          borderColor: '#38BDF8',
          backgroundColor: 'rgba(56,189,248,.07)',
          borderWidth: 2,
          pointBackgroundColor: '#38BDF8',
          pointRadius: 3.5,
          pointHoverRadius: 5.5,
          tension: .42,
          fill: true,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(10,11,13,.96)',
          borderColor: 'rgba(201,168,76,.25)', borderWidth: 1,
          titleColor: '#E8C96A', bodyColor: 'rgba(255,255,255,.65)', padding: 9,
          callbacks: {
            label: c => c.datasetIndex === 0
              ? ' ₱' + c.parsed.y.toLocaleString()
              : ' ' + c.parsed.y + ' booking' + (c.parsed.y !== 1 ? 's' : '')
          }
        }
      },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,.035)' }, ticks: { color: 'rgba(255,255,255,.35)', font: { family:"'DM Mono',monospace", size:10 } } },
        y: { position:'left', grid: { color:'rgba(255,255,255,.04)' }, ticks: { color:'rgba(255,255,255,.35)', font:{family:"'DM Mono',monospace",size:10}, callback: v => '₱'+v.toLocaleString() } },
        y1: { position:'right', grid:{ drawOnChartArea:false }, ticks:{ color:'rgba(56,189,248,.45)', font:{family:"'DM Mono',monospace",size:10}, stepSize:1, precision:0 } }
      }
    }
  });

  document.querySelectorAll('.pv-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      tab.closest('.pv-tabs').querySelectorAll('.pv-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
    });
  });
})();
</script>
</body>
</html>