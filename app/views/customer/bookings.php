<?php
// app/views/customer/bookings.php

require_once __DIR__ . '/../../../config/database.php';
$db     = Database::getInstance();
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Customer');
$initials = strtoupper(substr($userName, 0, 2));

// ── Filters ──────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 8;
$offset       = ($page - 1) * $perPage;

$validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'];

// ── Stats ─────────────────────────────────────────────
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(status = 'pending')   as pending,
        SUM(status = 'confirmed') as confirmed,
        SUM(status = 'completed') as completed,
        SUM(status IN ('cancelled','rejected')) as cancelled
    FROM tbl_bookings WHERE customer_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// ── Loyalty points ──────────────────────────────────
$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$loyaltyTier   = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};
$nextLevel   = 500;
$loyaltyProg = min(100, round(($loyaltyPoints % $nextLevel) / $nextLevel * 100));
$ptsToNext   = $nextLevel - ($loyaltyPoints % $nextLevel);

// ── Build query ───────────────────────────────────────
$where  = ["b.customer_id = ?"];
$params = [$userId];

if ($statusFilter !== 'all' && in_array($statusFilter, $validStatuses)) {
    $where[]  = "b.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = "(s.name LIKE ? OR pp.business_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Total count for pagination
$countStmt = $db->prepare("
    SELECT COUNT(*) FROM tbl_bookings b
    JOIN tbl_services s ON b.service_id = s.id
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    WHERE $whereClause
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch page
$params[] = $perPage;
$params[] = $offset;

$bookingStmt = $db->prepare("
    SELECT b.*,
           s.name  as service_name, s.price, s.duration_minutes,
           pp.business_name, pp.offers_home_service,
           c.name  as category_name, c.slug as category_slug,
           (SELECT COUNT(*) FROM tbl_reviews r WHERE r.booking_id = b.id) as has_review
    FROM tbl_bookings b
    JOIN tbl_services s           ON b.service_id  = s.id
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    LEFT JOIN tbl_categories c    ON pp.category_id = c.id
    WHERE $whereClause
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
");
$bookingStmt->execute($params);
$bookings = $bookingStmt->fetchAll();

// ── Upcoming count for nav badge ─────────────────────
$stUpcoming = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE()");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();

// ── Helpers ───────────────────────────────────────────
$catEmojiMap = [
    'barbershop'       => '✂️',
    'hair-salon'       => '💇',
    'nail-care'        => '💅',
    'massage-therapy'  => '💆',
    'skincare-facial'  => '🧴',
    'fitness-training' => '🏋️',
    'home-cleaning'    => '🧹',
    'pet-grooming'     => '🐾',
    'event-styling'    => '🎨',
    'dental'           => '🦷',
    'tutoring'         => '📚',
];
function catEmoji($slug, $map) { return $map[$slug] ?? '🛠️'; }

$tabCounts = [
    'all'       => (int)$stats['total'],
    'pending'   => (int)$stats['pending'],
    'confirmed' => (int)$stats['confirmed'],
    'completed' => (int)$stats['completed'],
    'cancelled' => (int)$stats['cancelled'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — My Bookings</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_bookings.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAV — matches customer dashboard nav exactly
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">

    <a href="<?= BASE_URL ?>home" class="pv-logo">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard"  class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings"   class="pv-nav-link is-active">
        Bookings
        <?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse"     class="pv-nav-link">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty"    class="pv-nav-link">Loyalty</a>
      <a href="<?= BASE_URL ?>profile"    class="pv-nav-link">Profile</a>
    </div>

    <div class="pv-nav-end">
      <div class="pv-points-badge">⭐ <?= number_format($loyaltyPoints) ?> pts</div>
      <button class="pv-notif-btn" aria-label="Notifications">
        🔔
        <span class="pv-notif-dot" aria-hidden="true"></span>
      </button>
      <div class="pv-nav-av" aria-hidden="true"><?= $initials ?></div>
      <div class="pv-nav-user">
        <div class="pv-nav-user-name"><?= $userName ?></div>
        <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>

  </div>
</nav>

<!-- ══════════════════════════════════════
     HERO — matches dashboard hero structure
══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>

  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        My Bookings
      </p>
      <h1 class="pv-hero-name">Track &amp; Manage <em>Appointments</em></h1>
      <p class="pv-hero-date"><?= date('l, F j, Y') ?></p>
      <div class="pv-hero-meta">
        <span class="pv-status-badge">
          <span class="pv-status-dot" aria-hidden="true"></span>
          Active Member
        </span>
        <span class="pv-tier-badge">⭐ <?= $loyaltyTier ?></span>
      </div>
    </div>

    <a href="<?= BASE_URL ?>browse" class="pv-points-chip">
      <span class="pv-points-chip-dot" aria-hidden="true"></span>
      ＋ Book a New Service
      <span aria-hidden="true">→</span>
    </a>
  </div>


</header>

<!-- ══════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════ -->
<main class="pv-page" role="main">

  <!-- KPI Cards — same pattern as dashboard -->
  <div class="pv-kpi-row" role="region" aria-label="Booking overview">

    <div class="pv-kpi pv-kpi--gold">
      <div class="pv-kpi-val"><?= (int)$stats['total'] ?></div>
      <div class="pv-kpi-label">Total Bookings</div>
      <div class="pv-kpi-sub">All time</div>
    </div>

    <div class="pv-kpi pv-kpi--yellow">
      <div class="pv-kpi-val"><?= (int)$stats['pending'] ?></div>
      <div class="pv-kpi-label">Pending</div>
      <div class="pv-kpi-sub">Awaiting confirmation</div>
    </div>

    <div class="pv-kpi pv-kpi--green">
      <div class="pv-kpi-val"><?= (int)$stats['confirmed'] ?></div>
      <div class="pv-kpi-label">Confirmed</div>
      <div class="pv-kpi-sub">Ready to go</div>
    </div>

    <div class="pv-kpi pv-kpi--blue">
      <div class="pv-kpi-val"><?= (int)$stats['completed'] ?></div>
      <div class="pv-kpi-label">Completed</div>
      <div class="pv-kpi-sub">Services enjoyed</div>
    </div>

  </div>

  <!-- ── Filter & Booking List Section ── -->
  <div class="pv-card pv-bookings-section">

    <!-- Card header with tabs + search -->
    <div class="pv-bookings-head">

      <!-- Tab row -->
      <div class="pv-tab-row" role="tablist" aria-label="Filter bookings by status">
        <?php
        $tabs = [
            'all'       => ['label' => 'All', 'icon' => '📋'],
            'pending'   => ['label' => 'Pending', 'icon' => '⏳'],
            'confirmed' => ['label' => 'Confirmed', 'icon' => '✅'],
            'completed' => ['label' => 'Completed', 'icon' => '🏅'],
            'cancelled' => ['label' => 'Cancelled', 'icon' => '✖'],
        ];
        foreach ($tabs as $val => $tab):
            $url = BASE_URL . 'bookings?' . http_build_query(array_filter([
                'status' => $val === 'all' ? '' : $val,
                'search' => $search,
            ]));
        ?>
        <a href="<?= $url ?>"
           class="pv-tab <?= $statusFilter === $val ? 'active' : '' ?>"
           role="tab"
           aria-selected="<?= $statusFilter === $val ? 'true' : 'false' ?>">
          <span aria-hidden="true"><?= $tab['icon'] ?></span>
          <?= $tab['label'] ?>
          <?php if ($tabCounts[$val] > 0): ?>
            <span class="pv-tab-count <?= $statusFilter === $val ? 'active' : '' ?>"><?= $tabCounts[$val] ?></span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Search -->
      <form method="GET" action="<?= BASE_URL ?>bookings" class="pv-search-form" role="search">
        <?php if ($statusFilter !== 'all'): ?>
          <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <?php endif; ?>
        <div class="pv-search-wrap">
          <span class="pv-search-icon" aria-hidden="true">🔍</span>
          <input type="text" name="search"
                 placeholder="Search service or provider…"
                 value="<?= htmlspecialchars($search) ?>"
                 aria-label="Search bookings"
                 class="pv-search-input">
          <?php if ($search): ?>
            <a href="<?= BASE_URL ?>bookings<?= $statusFilter !== 'all' ? '?status='.$statusFilter : '' ?>"
               class="pv-search-clear" aria-label="Clear search">✕</a>
          <?php endif; ?>
        </div>
      </form>

    </div><!-- /pv-bookings-head -->

    <!-- Results info -->
    <div class="pv-results-info">
      <span><?= $totalRows ?> booking<?= $totalRows !== 1 ? 's' : '' ?><?= $search ? ' for "<strong>'.htmlspecialchars($search).'</strong>"' : '' ?></span>
      <?php if ($search || $statusFilter !== 'all'): ?>
        <a href="<?= BASE_URL ?>bookings" class="pv-results-clear">Clear filters</a>
      <?php endif; ?>
    </div>

    <!-- ── Booking Cards ── -->
    <?php if (empty($bookings)): ?>
    <div class="pv-empty-state">
      <div class="pv-empty-icon" aria-hidden="true">📭</div>
      <p>No bookings found<?= $search ? ' for "<strong>' . htmlspecialchars($search) . '</strong>"' : '' ?>.</p>
      <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Browse Services →</a>
    </div>

    <?php else: ?>
    <div class="pv-booking-list" role="list">
      <?php foreach ($bookings as $b):
        $emoji        = catEmoji($b['category_slug'] ?? '', $catEmojiMap);
        $status       = $b['status'];
        $isCancellable= in_array($status, ['pending', 'confirmed']);
        $isCompleted  = $status === 'completed';
        $bookingTime  = !empty($b['booking_time']) ? date('g:i A', strtotime($b['booking_time'])) : null;
        $duration     = !empty($b['duration_minutes']) ? $b['duration_minutes'].' min' : null;
      ?>
      <div class="pv-booking-item" role="listitem">

        <!-- Colored left accent bar -->
        <div class="pv-booking-accent pv-booking-accent--<?= htmlspecialchars($status) ?>" aria-hidden="true"></div>

        <!-- Emoji avatar -->
        <div class="pv-booking-av" aria-hidden="true"><?= $emoji ?></div>

        <!-- Service + provider info -->
        <div class="pv-booking-info">
          <div class="pv-booking-service"><?= htmlspecialchars($b['service_name']) ?></div>
          <div class="pv-booking-provider">📍 <?= htmlspecialchars($b['business_name']) ?></div>
          <div class="pv-booking-tags">
            <?php if ($b['category_name']): ?>
              <span class="pv-tag pv-tag--cat"><?= htmlspecialchars($b['category_name']) ?></span>
            <?php endif; ?>
            <?php if ($b['offers_home_service']): ?>
              <span class="pv-tag pv-tag--home">🏠 Home Service</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Date & time -->
        <div class="pv-booking-datetime">
          <div class="pv-booking-datetime-day">
            <span class="pv-booking-date-num"><?= date('d', strtotime($b['booking_date'])) ?></span>
            <span class="pv-booking-date-mon"><?= date('M Y', strtotime($b['booking_date'])) ?></span>
          </div>
          <?php if ($bookingTime): ?>
            <div class="pv-booking-time">🕐 <?= $bookingTime ?></div>
          <?php endif; ?>
          <?php if ($duration): ?>
            <div class="pv-booking-dur">⏱ <?= $duration ?></div>
          <?php endif; ?>
        </div>

        <!-- Price -->
        <div class="pv-booking-price-col">
          <div class="pv-booking-price">₱<?= number_format($b['price'], 2) ?></div>
          <div class="pv-booking-price-label">Service fee</div>
        </div>

        <!-- Status badge -->
        <div class="pv-booking-status-col">
          <span class="pv-pill pv-pill--<?= htmlspecialchars($status) ?>">
            <?= ucfirst(str_replace('_', ' ', $status)) ?>
          </span>
        </div>

        <!-- Actions -->
        <div class="pv-booking-actions">
          <a href="<?= BASE_URL ?>bookings/<?= (int)$b['id'] ?>" class="pv-btn pv-btn--sm pv-btn--primary">
            View
          </a>
          <?php if ($isCancellable): ?>
            <a href="<?= BASE_URL ?>bookings/<?= (int)$b['id'] ?>/cancel"
               class="pv-btn pv-btn--sm pv-btn--ghost"
               onclick="return confirm('Are you sure you want to cancel this booking?')">
              Cancel
            </a>
          <?php elseif ($isCompleted && !$b['has_review']): ?>
            <a href="<?= BASE_URL ?>bookings/<?= (int)$b['id'] ?>/review"
               class="pv-btn pv-btn--sm pv-btn--review">
              ⭐ Review
            </a>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="pv-pagination" aria-label="Booking pages">
      <?php
      $prevPage = max(1, $page - 1);
      $nextPage = min($totalPages, $page + 1);
      $paginateBase = array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : '', 'search' => $search]);
      ?>
      <a href="<?= BASE_URL ?>bookings?<?= http_build_query(array_merge($paginateBase, ['page' => $prevPage])) ?>"
         class="pv-page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
         aria-label="Previous page"
         <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>>‹</a>

      <?php
      $range = range(max(1, $page - 2), min($totalPages, $page + 2));
      if (!in_array(1, $range)) { echo '<span class="pv-page-ellipsis">…</span>'; }
      foreach ($range as $i): ?>
        <a href="<?= BASE_URL ?>bookings?<?= http_build_query(array_merge($paginateBase, ['page' => $i])) ?>"
           class="pv-page-btn <?= $i === $page ? 'active' : '' ?>"
           aria-label="Page <?= $i ?>"
           aria-current="<?= $i === $page ? 'page' : 'false' ?>"><?= $i ?></a>
      <?php endforeach;
      if (!in_array($totalPages, $range)) { echo '<span class="pv-page-ellipsis">…</span>'; }
      ?>

      <a href="<?= BASE_URL ?>bookings?<?= http_build_query(array_merge($paginateBase, ['page' => $nextPage])) ?>"
         class="pv-page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
         aria-label="Next page"
         <?= $page >= $totalPages ? 'aria-disabled="true"' : '' ?>>›</a>
    </nav>
    <?php endif; ?>

    <?php endif; ?>

  </div><!-- /pv-bookings-section -->

</main>

</body>
</html>