<?php

require_once __DIR__ . '/../../../config/database.php';
$db     = Database::getInstance();
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Customer');
$initials = strtoupper(substr($userName, 0, 2));
$stAv = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAv->execute([$userId]);
$avatarUrl = ($av = $stAv->fetchColumn()) ? ($av) : null;


$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 8;
$offset       = ($page - 1) * $perPage;

$validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'rejected', 'rescheduled'];

$statsStmt = $db->prepare("
    SELECT
        SUM(status NOT IN ('cancelled','rejected') AND deleted_at IS NULL) as total,
        SUM(status = 'pending'      AND deleted_at IS NULL) as pending,
        SUM(status = 'confirmed'    AND deleted_at IS NULL) as confirmed,
        SUM(status = 'completed'    AND deleted_at IS NULL) as completed,
        SUM(status IN ('cancelled','rejected')) as cancelled,
        SUM(status = 'rescheduled'  AND deleted_at IS NULL) as rescheduled
    FROM tbl_bookings WHERE customer_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();


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

if ($statusFilter === 'cancelled') {
    $where  = ["b.customer_id = ?", "b.status IN ('cancelled','rejected')"];
} else {
    $where  = ["b.customer_id = ?", "b.deleted_at IS NULL", "b.status NOT IN ('cancelled','rejected')"];
}
$params = [$userId];

if ($statusFilter !== 'all' && $statusFilter !== 'cancelled' && in_array($statusFilter, $validStatuses)) {
    $where[]  = "b.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = "(s.name LIKE ? OR pp.business_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);


$countStmt = $db->prepare("
    SELECT COUNT(*) FROM tbl_bookings b
    JOIN tbl_services s ON b.service_id = s.id
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    WHERE $whereClause
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));


$params[] = $perPage;
$params[] = $offset;

$bookingStmt = $db->prepare("
    SELECT b.*,
           s.name  as service_name, s.price, s.duration_minutes, s.service_type,
           pp.business_name, pp.offers_home_service, pp.avg_rating as provider_rating,
           c.name  as category_name, c.slug as category_slug,
           (SELECT COUNT(*) FROM tbl_reviews r WHERE r.booking_id = b.id) as has_review,
           (SELECT py.status FROM tbl_payments py WHERE py.booking_id = b.id ORDER BY py.created_at DESC LIMIT 1) as payment_status,
           (SELECT py.payment_method FROM tbl_payments py WHERE py.booking_id = b.id ORDER BY py.created_at DESC LIMIT 1) as payment_method,
           (SELECT py.amount FROM tbl_payments py WHERE py.booking_id = b.id ORDER BY py.created_at DESC LIMIT 1) as payment_amount
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


$stUpcoming = $db->prepare("
    SELECT COUNT(*) FROM tbl_bookings
    WHERE customer_id = ?
      AND status IN ('pending','confirmed')
      AND booking_date >= CURDATE()
      AND deleted_at IS NULL
");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();


$svcImageMap = [
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
function pvBookingImage(string $type, array $map): string {
    return $map[$type] ?? 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=80&h=80&fit=crop&q=70';
}

$tabCounts = [
    'all'          => (int)$stats['total'],
    'pending'      => (int)$stats['pending'],
    'confirmed'    => (int)$stats['confirmed'],
    'rescheduled'  => (int)$stats['rescheduled'],
    'completed'    => (int)$stats['completed'],
    'cancelled'    => (int)$stats['cancelled'],
];

$filterCards = [
    'all'          => ['label' => 'All Bookings',  'sub' => 'All time',               'icon' => ''],
    'pending'      => ['label' => 'Pending',        'sub' => 'Awaiting confirmation',   'icon' => ''],
    'confirmed'    => ['label' => 'Confirmed',      'sub' => 'Ready to go',             'icon' => ''],
    'rescheduled'  => ['label' => 'Rescheduled',    'sub' => 'New schedule suggested',  'icon' => ''],
    'completed'    => ['label' => 'Completed',      'sub' => 'Services enjoyed',        'icon' => ''],
    'cancelled'    => ['label' => 'Cancelled',      'sub' => 'Dismissed',               'icon' => ''],
];

/* ── Payment method icons ── */
function pvPaymentIcon(string $method): string {
    return match($method) {
        'gcash'   => '📱',
        'paymaya' => '💳',
        'card'    => '💳',
        'cash'    => '💵',
        'points'  => '⭐',
        default   => '💰',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — My Bookings</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_bookings.css">
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
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>

<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">

    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo" style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard" class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings" class="pv-nav-link is-active">
        Bookings<?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse" class="pv-nav-link">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty" class="pv-nav-link">Loyalty</a>
      <a href="<?= BASE_URL ?>profile" class="pv-nav-link">Profile</a>
    </div>

    <div class="pv-nav-end">

      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
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
      <span aria-hidden="true"></span>
      ＋ Book a New Service
      <span aria-hidden="true">→</span>
    </a>
  </div>
</header>

<main class="pv-page" role="main">

  <div class="pv-filter-cards" role="region" aria-label="Filter bookings by status">
    <?php foreach ($filterCards as $val => $card):
      $url = BASE_URL . 'bookings?' . http_build_query(array_filter([
          'status' => $val === 'all' ? '' : $val,
          'search' => $search,
      ]));
      $isActive = $statusFilter === $val;
    ?>
    <a href="<?= $url ?>"
       class="pv-fc pv-fc--<?= $val ?><?= $isActive ? ' active' : '' ?>"
       role="button"
       aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
       aria-label="Filter by <?= $card['label'] ?>, <?= $tabCounts[$val] ?> bookings">

      <div class="pv-fc-top">
        <?php if ($tabCounts[$val] > 0): ?>
          <span class="pv-fc-badge"><?= $tabCounts[$val] ?></span>
        <?php endif; ?>
      </div>

      <div class="pv-fc-val"><?= $tabCounts[$val] ?></div>
      <div class="pv-fc-label"><?= $card['label'] ?></div>
      <div class="pv-fc-sub"><?= $card['sub'] ?></div>

    </a>
    <?php endforeach; ?>
  </div>

  <div class="pv-card pv-bookings-section">

    <div class="pv-bookings-head">

      <div class="pv-bookings-head-left">
        <h2 class="pv-bookings-title">
          <?= $filterCards[$statusFilter]['label'] ?>
        </h2>
        <p class="pv-bookings-subtitle"><?= $filterCards[$statusFilter]['sub'] ?></p>
      </div>

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

    </div>

    <div class="pv-results-info">
      <span><?= $totalRows ?> booking<?= $totalRows !== 1 ? 's' : '' ?><?= $search ? ' for "<strong>'.htmlspecialchars($search).'</strong>"' : '' ?></span>
      <?php if ($search || $statusFilter !== 'all'): ?>
        <a href="<?= BASE_URL ?>bookings" class="pv-results-clear">Clear filters</a>
      <?php endif; ?>
    </div>

    <?php if (empty($bookings)): ?>
    <div class="pv-empty-state">
      <div class="pv-empty-icon" aria-hidden="true">📭</div>
      <p>No bookings found<?= $search ? ' for "<strong>' . htmlspecialchars($search) . '</strong>"' : '' ?>.</p>
      <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Browse Services →</a>
    </div>

    <?php else: ?>
    <div class="pv-booking-grid" role="list">
      <?php foreach ($bookings as $b):
        $imgSrc        = pvBookingImage($b['service_type'] ?? '', $svcImageMap);
        $status        = $b['status'];
        $isCompleted   = $status === 'completed';
        $isCancelled   = in_array($status, ['cancelled', 'rejected']);
        $isUpcoming    = in_array($status, ['pending', 'confirmed', 'rescheduled']);
        $bookingDate   = !empty($b['booking_date'])  ? date('M j, Y', strtotime($b['booking_date'])) : '—';
        $bookingTime   = !empty($b['booking_time'])  ? date('g:i A', strtotime($b['booking_time'])) : null;
        $endTime       = !empty($b['end_time'])      ? date('g:i A', strtotime($b['end_time'])) : null;
        $duration      = !empty($b['duration_minutes']) ? $b['duration_minutes'].' min' : null;
        $payStatus     = $b['payment_status']  ?? null;
        $payMethod     = $b['payment_method']  ?? null;
        $payAmount     = $b['payment_amount']  ?? $b['total_amount'];
        $isPaid        = $payStatus === 'paid';
        $loyaltyEarned = (int)($b['loyalty_points_earned'] ?? 0);
        $provRating    = $b['provider_rating'] ? number_format((float)$b['provider_rating'], 1) : null;

        /* Build "Book Again" URL — link to browse with service pre-selected */
        $bookAgainUrl  = BASE_URL . 'browse/service/' . (int)$b['service_id'];
      ?>
      <div class="pv-bk-card pv-bk-card--<?= htmlspecialchars($status) ?>" role="listitem">

        <!-- Top accent strip (colour-coded by status) -->
        <div class="pv-bk-strip" aria-hidden="true"></div>

        <!-- Card Header: image + core info + status pill -->
        <div class="pv-bk-header">
          <div class="pv-bk-thumb">
            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($b['service_type'] ?? '') ?>" loading="lazy">
          </div>

          <div class="pv-bk-head-info">
            <div class="pv-bk-service-name"><?= htmlspecialchars($b['service_name']) ?></div>
            <div class="pv-bk-provider">
              <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
              <?= htmlspecialchars($b['business_name']) ?>
              <?php if ($provRating): ?>
                <span class="pv-bk-rating"><i class="fa-solid fa-star"></i> <?= $provRating ?></span>
              <?php endif; ?>
            </div>
            <?php if ($b['category_name']): ?>
              <span class="pv-tag pv-tag--cat"><?= htmlspecialchars($b['category_name']) ?></span>
            <?php endif; ?>
          </div>

          <span class="pv-pill pv-pill--<?= htmlspecialchars($status) ?>">
            <?= ucfirst(str_replace('_', ' ', $status)) ?>
          </span>
        </div>

        <!-- Divider -->
        <div class="pv-bk-divider" aria-hidden="true"></div>

        <!-- Details grid -->
        <div class="pv-bk-details">

          <div class="pv-bk-detail">
            <span class="pv-bk-detail-icon">📅</span>
            <div>
              <div class="pv-bk-detail-label">Date</div>
              <div class="pv-bk-detail-val"><?= $bookingDate ?></div>
            </div>
          </div>

          <?php if ($bookingTime): ?>
          <div class="pv-bk-detail">
            <span class="pv-bk-detail-icon">🕐</span>
            <div>
              <div class="pv-bk-detail-label">Time</div>
              <div class="pv-bk-detail-val"><?= $bookingTime ?><?= $endTime ? ' – ' . $endTime : '' ?></div>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($duration): ?>
          <div class="pv-bk-detail">
            <span class="pv-bk-detail-icon">⏱</span>
            <div>
              <div class="pv-bk-detail-label">Duration</div>
              <div class="pv-bk-detail-val"><?= $duration ?></div>
            </div>
          </div>
          <?php endif; ?>

          <div class="pv-bk-detail">
            <span class="pv-bk-detail-icon">📍</span>
            <div>
              <div class="pv-bk-detail-label">Location</div>
              <div class="pv-bk-detail-val"><?= htmlspecialchars($b['location_type'] ?? 'In-shop') ?></div>
            </div>
          </div>

        </div>

        <!-- Divider -->
        <div class="pv-bk-divider" aria-hidden="true"></div>

        <!-- Payment row -->
        <div class="pv-bk-payment-row">
          <div class="pv-bk-price-block">
            <span class="pv-bk-price-label">Total</span>
            <span class="pv-bk-price">₱<?= number_format((float)($payAmount ?? 0), 2) ?></span>
          </div>

          <div class="pv-bk-pay-info">
            <?php if ($payMethod): ?>
              <span class="pv-bk-pay-method">
                <?= pvPaymentIcon($payMethod) ?> <?= ucfirst($payMethod) ?>
              </span>
            <?php endif; ?>
            <?php if ($payStatus): ?>
              <span class="pv-pay-badge pv-pay-badge--<?= htmlspecialchars($payStatus) ?>">
                <?= $isPaid ? '✓ Paid' : ucfirst($payStatus) ?>
              </span>
            <?php else: ?>
              <span class="pv-pay-badge pv-pay-badge--none">No Payment</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($loyaltyEarned > 0): ?>
        <div class="pv-bk-loyalty">
          <span>⭐ +<?= $loyaltyEarned ?> loyalty points earned</span>
        </div>
        <?php endif; ?>

        <?php if ($status === 'rescheduled' && $b['suggested_date']): ?>
        <div class="pv-bk-reschedule-note">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i>
          Suggested: <?= date('M j, Y', strtotime($b['suggested_date'])) ?>
          <?= $b['suggested_time'] ? ' at ' . date('g:i A', strtotime($b['suggested_time'])) : '' ?>
          <?= $b['reschedule_note'] ? ' — ' . htmlspecialchars($b['reschedule_note']) : '' ?>
        </div>
        <?php endif; ?>

        <?php if ($isCancelled && $b['cancellation_reason']): ?>
        <div class="pv-bk-cancel-note">
          <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
          <?= htmlspecialchars($b['cancellation_reason']) ?>
        </div>
        <?php endif; ?>

        <!-- Action buttons -->
        <div class="pv-bk-actions">
          <!-- Book Again always visible -->
          <a href="<?= $bookAgainUrl ?>" class="pv-btn pv-btn--book-again">
            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
            Book Again
          </a>

          <!-- Review: only if completed and no review yet -->
          <?php if ($isCompleted && !$b['has_review']): ?>
            <a href="<?= BASE_URL ?>bookings/<?= (int)$b['id'] ?>/review"
               class="pv-btn pv-btn--review">
              <i class="fa-solid fa-star" aria-hidden="true"></i>
              Leave a Review
            </a>
          <?php elseif ($isCompleted && $b['has_review']): ?>
            <span class="pv-btn-reviewed">
              <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
              Reviewed
            </span>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
    </div>

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

  </div>

</main>

<script>
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