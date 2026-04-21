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
$statsSql = "
    SELECT
        COUNT(*) as total,
        SUM(status = 'pending')   as pending,
        SUM(status = 'confirmed') as confirmed,
        SUM(status = 'completed') as completed,
        SUM(status IN ('cancelled','rejected')) as cancelled
    FROM tbl_bookings WHERE customer_id = ?
";
$statsStmt = $db->prepare($statsSql);
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// ── Loyalty points (for sidebar) ──────────────────────
$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$nextLevel     = 500;
$loyaltyProg   = min(100, round(($loyaltyPoints % $nextLevel) / $nextLevel * 100));
$ptsToNext     = $nextLevel - ($loyaltyPoints % $nextLevel);

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

$bookingSql = "
    SELECT b.*,
           s.name  as service_name, s.price,
           pp.business_name, pp.offers_home_service,
           c.name  as category_name, c.slug as category_slug,
           (SELECT COUNT(*) FROM tbl_reviews r WHERE r.booking_id = b.id) as has_review
    FROM tbl_bookings b
    JOIN tbl_services s          ON b.service_id  = s.id
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    LEFT JOIN tbl_categories c   ON pp.category_id = c.id
    WHERE $whereClause
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
";
$bookingStmt = $db->prepare($bookingSql);
$bookingStmt->execute($params);
$bookings = $bookingStmt->fetchAll();

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

// Build URL helper preserving existing query params
function bookingUrl($overrides = []) {
    $base = array_filter([
        'status' => $_GET['status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'page'   => $_GET['page']   ?? '',
    ], fn($v) => $v !== '');
    $merged = array_merge($base, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== 'all' && $v !== 1 || isset($overrides[array_search($v, $merged)]));
    $q = http_build_query($merged);
    return BASE_URL . 'bookings' . ($q ? "?$q" : '');
}

// Tab counts
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
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/_bookings.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     SIDEBAR
══════════════════════════════════════ -->
<aside class="sidebar" role="navigation" aria-label="Customer navigation">

  <a href="<?= BASE_URL ?>home" class="sidebar-logo">
    Quick<em>Book</em>
    <span class="sidebar-logo-badge">Customer</span>
  </a>

  <div class="sidebar-user">
    <div class="sidebar-user-av"><?= $initials ?></div>
    <div class="sidebar-user-info">
      <div class="sidebar-user-name"><?= $userName ?></div>
      <div class="sidebar-user-role">Customer</div>
      <div class="sidebar-user-status">Active now</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a href="<?= BASE_URL ?>dashboard" class="nav-link">
      <span class="icon">⊞</span> Dashboard
    </a>
    <a href="<?= BASE_URL ?>bookings" class="nav-link active" aria-current="page">
      <span class="icon">📅</span> My Bookings
      <?php if ((int)$stats['pending'] + (int)$stats['confirmed'] > 0): ?>
        <span class="nav-badge"><?= (int)$stats['pending'] + (int)$stats['confirmed'] ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>browse" class="nav-link">
      <span class="icon">🔍</span> Browse Services
    </a>
    <div class="nav-section" style="margin-top:.6rem">Account</div>
    <a href="<?= BASE_URL ?>loyalty" class="nav-link">
      <span class="icon">⭐</span> Loyalty Points
    </a>
    <a href="<?= BASE_URL ?>spending" class="nav-link">
      <span class="icon">📊</span> Spending History
    </a>
    <a href="<?= BASE_URL ?>profile" class="nav-link">
      <span class="icon">👤</span> Profile
    </a>
    <a href="<?= BASE_URL ?>settings" class="nav-link">
      <span class="icon">⚙️</span> Settings
    </a>
  </nav>

  <!-- Loyalty mini bar -->
  <div class="sidebar-loyalty">
    <div class="sidebar-loyalty-head">
      <span>⭐ Loyalty</span>
      <strong><?= number_format($loyaltyPoints) ?> pts</strong>
    </div>
    <div class="loyalty-bar">
      <div class="loyalty-fill" style="width:<?= $loyaltyProg ?>%"></div>
    </div>
    <div class="loyalty-hint"><?= number_format($ptsToNext) ?> pts to next reward</div>
  </div>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>auth/logout" class="logout-btn">Sign Out</a>
  </div>

</aside>

<!-- ══════════════════════════════════════
     MAIN
══════════════════════════════════════ -->
<div class="main" role="main">

  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-left">
      <div class="topbar-title">My Bookings</div>
      <div class="topbar-sub"><?= date('l, F j, Y') ?> &mdash; <?= $userName ?></div>
    </div>
    <div class="topbar-right">
      <div class="topbar-av" aria-hidden="true"><?= $initials ?></div>
    </div>
  </header>

  <div class="content">

    <!-- Page header -->
    <div class="page-header">
      <div>
        <h1>My <em>Bookings</em></h1>
        <p>Track, manage and review all your appointments.</p>
      </div>
      <a href="<?= BASE_URL ?>browse" class="browse-btn">＋ Book a Service</a>
    </div>

    <!-- Stats row -->
    <div class="stats-row" role="region" aria-label="Booking statistics">
      <div class="mini-stat gold">
        <div class="mini-stat-icon">📋</div>
        <div>
          <div class="mini-stat-value"><?= (int)$stats['total'] ?></div>
          <div class="mini-stat-label">Total Bookings</div>
        </div>
      </div>
      <div class="mini-stat yellow">
        <div class="mini-stat-icon">⏳</div>
        <div>
          <div class="mini-stat-value"><?= (int)$stats['pending'] ?></div>
          <div class="mini-stat-label">Pending</div>
        </div>
      </div>
      <div class="mini-stat green">
        <div class="mini-stat-icon">✅</div>
        <div>
          <div class="mini-stat-value"><?= (int)$stats['confirmed'] ?></div>
          <div class="mini-stat-label">Confirmed</div>
        </div>
      </div>
      <div class="mini-stat blue">
        <div class="mini-stat-icon">🏅</div>
        <div>
          <div class="mini-stat-value"><?= (int)$stats['completed'] ?></div>
          <div class="mini-stat-label">Completed</div>
        </div>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="tab-row" role="tablist">
        <?php
        $tabs = [
            'all'       => 'All',
            'pending'   => 'Pending',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
        foreach ($tabs as $val => $label):
            $url = BASE_URL . 'bookings?' . http_build_query(array_filter([
                'status' => $val === 'all' ? '' : $val,
                'search' => $search,
            ]));
        ?>
        <a href="<?= $url ?>"
           class="tab <?= $statusFilter === $val ? 'active' : '' ?>"
           role="tab" aria-selected="<?= $statusFilter === $val ? 'true' : 'false' ?>">
          <?= $label ?>
          <?php if ($tabCounts[$val] > 0): ?>
            <span class="tab-count"><?= $tabCounts[$val] ?></span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>

      <div class="filter-right">
        <form method="GET" action="<?= BASE_URL ?>bookings">
          <?php if ($statusFilter !== 'all'): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
          <?php endif; ?>
          <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" name="search"
                   placeholder="Search bookings…"
                   value="<?= htmlspecialchars($search) ?>"
                   aria-label="Search bookings">
          </div>
        </form>
      </div>
    </div>

    <!-- Booking cards / empty state -->
    <?php if (empty($bookings)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>No bookings found<?= $search ? ' for "<strong>' . htmlspecialchars($search) . '</strong>"' : '' ?>.</p>
      <a href="<?= BASE_URL ?>browse" class="empty-cta">Browse Services →</a>
    </div>

    <?php else: ?>
    <div class="booking-cards" role="list">
      <?php foreach ($bookings as $b):
        $emoji        = catEmoji($b['category_slug'] ?? '', $catEmojiMap);
        $status       = $b['status'];
        $isCancellable= in_array($status, ['pending', 'confirmed']);
        $isCompleted  = $status === 'completed';
        $bookingTime  = !empty($b['booking_time']) ? date('g:i A', strtotime($b['booking_time'])) : '—';
      ?>
      <div class="booking-card" role="listitem">
        <div class="booking-card-inner">
          <div class="booking-accent <?= htmlspecialchars($status) ?>"></div>
          <div class="booking-card-body">

            <!-- Icon -->
            <div class="booking-emoji" aria-hidden="true"><?= $emoji ?></div>

            <!-- Service info -->
            <div class="booking-main">
              <div class="booking-service-name"><?= htmlspecialchars($b['service_name']) ?></div>
              <div class="booking-business">📍 <?= htmlspecialchars($b['business_name']) ?></div>
              <div class="booking-tags">
                <?php if ($b['category_name']): ?>
                  <span class="tag cat"><?= htmlspecialchars($b['category_name']) ?></span>
                <?php endif; ?>
                <?php if ($b['offers_home_service']): ?>
                  <span class="tag home">🏠 Home Service</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Date / time -->
            <div class="booking-details">
              <div class="booking-detail-row">
                📅 <span class="val"><?= date('M d, Y', strtotime($b['booking_date'])) ?></span>
              </div>
              <div class="booking-detail-row">
                🕐 <span class="val"><?= $bookingTime ?></span>
              </div>
            </div>

            <!-- Price -->
            <div class="booking-price-col">
              <div class="booking-price">₱<?= number_format($b['price'], 2) ?></div>
              <div class="booking-price-label">Service fee</div>
            </div>

            <!-- Status -->
            <div class="booking-status-col">
              <span class="status-badge <?= htmlspecialchars($status) ?>">
                <?= ucfirst(str_replace('_', ' ', $status)) ?>
              </span>
            </div>

            <!-- Actions -->
            <div class="booking-actions">
              <a href="<?= BASE_URL ?>bookings/<?= (int)$b['id'] ?>" class="btn-sm btn-primary">View</a>
              <?php if ($isCancellable): ?>
                <a href="<?= BASE_URL ?>bookings/<?= (int)$b['id'] ?>/cancel"
                   class="btn-sm btn-ghost"
                   onclick="return confirm('Cancel this booking?')">Cancel</a>
              <?php elseif ($isCompleted && !$b['has_review']): ?>
                <a href="<?= BASE_URL ?>bookings/<?= (int)$b['id'] ?>/review"
                   class="btn-sm btn-ghost review">⭐ Review</a>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Booking pages">
      <a href="<?= BASE_URL ?>bookings?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
         class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" aria-label="Previous page">‹</a>

      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="<?= BASE_URL ?>bookings?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
           class="page-btn <?= $i === $page ? 'active' : '' ?>"
           aria-label="Page <?= $i ?>" aria-current="<?= $i === $page ? 'page' : 'false' ?>"><?= $i ?></a>
      <?php endfor; ?>

      <a href="<?= BASE_URL ?>bookings?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
         class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" aria-label="Next page">›</a>
    </nav>
    <?php endif; ?>

    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

</body>
</html>