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

/* ── Loyalty ── */
$loyaltyTier = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};
$nextLevel = 500;
$progress  = min(100, round(($loyaltyPoints % $nextLevel) / $nextLevel * 100));
$ptsToNext = $nextLevel - ($loyaltyPoints % $nextLevel);

/* ── Next upcoming booking (featured hero card) ── */
$stNextBooking = $db->prepare("
    SELECT b.*, pp.business_name, s.name AS service_name, s.price, s.duration_minutes
    FROM tbl_bookings b
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    JOIN tbl_services s ON b.service_id = s.id
    WHERE b.customer_id = ? AND b.status IN ('pending','confirmed') AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC, b.booking_time ASC LIMIT 1
");
$stNextBooking->execute([$userId]);
$nextBooking = $stNextBooking->fetch();

/* ── Upcoming list (sidebar) ── */
$stUpcomingList = $db->prepare("
    SELECT b.*, pp.business_name, s.name AS service_name, s.price
    FROM tbl_bookings b
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    JOIN tbl_services s ON b.service_id = s.id
    WHERE b.customer_id = ? AND b.status IN ('pending','confirmed') AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC LIMIT 3
");
$stUpcomingList->execute([$userId]);
$upcomingBookings = $stUpcomingList->fetchAll();

/* ── Featured Providers ── */
$stProviders = $db->prepare("
    SELECT pp.id, pp.business_name, pp.profile_photo, pp.barangay, pp.city,
           pp.latitude, pp.longitude,
           pp.business_address,
           c.name  AS category_name,
           c.slug  AS category_slug,
           u.first_name, u.last_name,
           COALESCE(AVG(r.rating), 0) AS avg_rating,
           COUNT(DISTINCT r.id) AS review_count,
           COUNT(DISTINCT b.id) AS booking_count,
           GROUP_CONCAT(DISTINCT s.service_type ORDER BY s.service_type SEPARATOR ', ') AS service_types,
           MIN(s.price) AS min_price
    FROM tbl_provider_profiles pp
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    LEFT JOIN tbl_users u ON pp.user_id = u.id
    LEFT JOIN tbl_services s ON s.provider_id = pp.id AND s.is_active = 1
    LEFT JOIN tbl_reviews r ON r.provider_id = pp.id
    LEFT JOIN tbl_bookings b ON b.provider_id = pp.id AND b.status = 'completed'
    WHERE pp.is_approved = 1 AND pp.is_active = 1 AND pp.city = 'Bacolod City'
    GROUP BY pp.id
    ORDER BY avg_rating DESC, booking_count DESC
    LIMIT 20
");
$stProviders->execute();
$featuredProviders = $stProviders->fetchAll();

/* ── Recent bookings ── */
$stRecent = $db->prepare("
    SELECT b.*, pp.business_name, pp.profile_photo,
           s.name AS service_name, s.price, s.duration_minutes, s.location_type,
           CONCAT(u.first_name, ' ', u.last_name) AS provider_name
    FROM tbl_bookings b
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    JOIN tbl_services s ON b.service_id = s.id
    JOIN tbl_users u ON pp.user_id = u.id
    WHERE b.customer_id = ?
    ORDER BY b.created_at DESC LIMIT 5
");
$stRecent->execute([$userId]);
$recentBookings = $stRecent->fetchAll();

/* ── Pending review ── */
$stLastCompleted = $db->prepare("
    SELECT b.*, pp.business_name, s.name AS service_name
    FROM tbl_bookings b
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    JOIN tbl_services s ON b.service_id = s.id
    LEFT JOIN tbl_reviews r ON r.booking_id = b.id
    WHERE b.customer_id = ? AND b.status = 'completed' AND r.id IS NULL
    ORDER BY b.booking_date DESC LIMIT 1
");
$stLastCompleted->execute([$userId]);
$pendingReview = $stLastCompleted->fetch();

/* ── Avatar ── */
$stAv = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAv->execute([$userId]);
$avatarUrl = ($av = $stAv->fetchColumn()) ? $av : null;

/* ── Helpers ── */
$hour          = (int)date('H');
$greeting      = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$initials      = strtoupper(substr($name, 0, 2));
$firstNameOnly = explode(' ', $name)[0];

/* ── Service categories ── */
$categories = [
    ['label' => 'Barbers',          'icon' => 'fa-scissors',   'slug' => 'barber'],
    ['label' => 'Hair Salon',       'icon' => 'fa-scissors',        'slug' => 'hair'],
    ['label' => 'Nail Care',        'icon' => 'fa-hand-sparkles',   'slug' => 'nail'],
    ['label' => 'Massage Therapy',  'icon' => 'fa-spa',             'slug' => 'massage'],
    ['label' => 'Skincare Facial',  'icon' => 'fa-face-smile-beam', 'slug' => 'skincare'],
    ['label' => 'Fitness Training', 'icon' => 'fa-dumbbell',        'slug' => 'fitness'],
    ['label' => 'Cleaning Services','icon' => 'fa-broom',           'slug' => 'cleaning'],
    ['label' => 'Pet Grooming',     'icon' => 'fa-paw',             'slug' => 'pet'],
    ['label' => 'Dental Services',  'icon' => 'fa-tooth',           'slug' => 'dental'],
    ['label' => 'Makeup Artist',    'icon' => 'fa-wand-sparkles',   'slug' => 'makeup'],
];

/* ── Build provider map data for JS ── */
$mapProviders = [];
foreach ($featuredProviders as $p) {
    $catSlug = $p['category_slug'] ?? '';
    $catName = $p['category_name'] ?? '';
    // Fallback category detection from service_types
    if (!$catName && !empty($p['service_types'])) {
        $catName = ucwords(strtolower(trim(explode(',', $p['service_types'])[0])));
    }
    $fullAddress = implode(', ', array_filter([
        $p['address']  ?? '',
        $p['barangay'] ?? '',
        $p['city']     ?? 'Bacolod City',
        'Negros Occidental'
    ]));
    $mapProviders[] = [
        'id'           => (int)$p['id'],
        'name'         => $p['business_name'],
        'providerName' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
        'barangay'     => $p['barangay'] ?? '',
        'address'      => $fullAddress,
        'lat'          => !empty($p['latitude'])  ? (float)$p['latitude']  : null,
        'lng'          => !empty($p['longitude']) ? (float)$p['longitude'] : null,
        'rating'       => round((float)$p['avg_rating'], 1),
        'reviewCount'  => (int)$p['review_count'],
        'category'     => $catName,
        'categorySlug' => $catSlug,
        'urlView'      => BASE_URL . 'providers/' . (int)$p['id'],
        'urlBook'      => BASE_URL . 'book/'      . (int)$p['id'],
        'minPrice'     => !empty($p['min_price']) ? '₱' . number_format((float)$p['min_price'], 0) : '',
        'photo'        => $p['profile_photo'] ?? '',
    ];
}
$mapProvidersJson = json_encode($mapProviders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

function fmtMoney(float $v): string {
    return $v >= 1000 ? '₱'.number_format($v/1000,1).'k' : '₱'.number_format($v,0);
}

function starRating(float $r): string {
    $full  = (int)floor($r);
    $half  = ($r - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $out   = '';
    for ($i = 0; $i < $full;  $i++) $out .= '<i class="fa-solid fa-star"></i>';
    if ($half)                       $out .= '<i class="fa-solid fa-star-half-stroke"></i>';
    for ($i = 0; $i < $empty; $i++) $out .= '<i class="fa-regular fa-star"></i>';
    return $out;
}

$spentDisplay = fmtMoney($totalSpent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Dashboard</title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

  <!-- App CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_dashboard.css">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Leaflet CSS — must be in <head> before map renders -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">

  <!-- Apply saved theme before render to prevent flash -->
  <script>
    (function(){
      var t = localStorage.getItem('qb-theme') || 'light';
      if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
    })();
  </script>
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<!-- ══ NAV ══ -->
<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo" style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard" class="pv-nav-link is-active">Dashboard</a>
      <a href="<?= BASE_URL ?>browse"    class="pv-nav-link">Browse</a>
      <a href="<?= BASE_URL ?>bookings"  class="pv-nav-link">
        Bookings
        <?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>loyalty" class="pv-nav-link">Loyalty</a>
    </div>

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <!-- Theme toggle -->
      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
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

    </div><!-- /pv-nav-end -->
  </div><!-- /pv-nav-inner -->
</nav>

<!-- ══ HERO ══ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">

    <!-- Left: greeting -->
    <div class="pv-hero-left">
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        <?= $greeting ?>
      </p>
      <h1 class="pv-hero-name"><?= $firstNameOnly ?></h1>
      <div class="pv-hero-location">
        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
        <span>Bacolod City, Negros Occidental</span>
      </div>
      <div class="pv-hero-meta">
        <span class="pv-status-badge">
          <span class="pv-status-dot" aria-hidden="true"></span>
          Active Member
        </span>
        <span class="pv-tier-badge">⭐ <?= $loyaltyTier ?></span>
        <span class="pv-date-badge"><?= date('M j, Y') ?></span>
      </div>
    </div>

    <!-- Right: next appointment card -->
    <?php if ($nextBooking): ?>
    <a href="<?= BASE_URL ?>bookings/<?= (int)$nextBooking['id'] ?>" class="pv-appt-card" aria-label="View next appointment">
      <div class="pv-appt-card-tag">
        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
        Next Appointment
      </div>
      <div class="pv-appt-card-service"><?= htmlspecialchars($nextBooking['service_name']) ?></div>
      <div class="pv-appt-card-biz"><?= htmlspecialchars($nextBooking['business_name']) ?></div>
      <div class="pv-appt-card-when">
        <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= date('l, F j', strtotime($nextBooking['booking_date'])) ?></span>
        <?php if (!empty($nextBooking['booking_time'])): ?>
        <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= date('g:i A', strtotime($nextBooking['booking_time'])) ?></span>
        <?php endif; ?>
      </div>
      <div class="pv-appt-card-footer">
        <span class="pv-appt-price">₱<?= number_format((float)$nextBooking['price'], 0) ?></span>
        <span class="pv-appt-cta">View Details →</span>
      </div>
    </a>
    <?php else: ?>
    <a href="<?= BASE_URL ?>browse" class="pv-appt-card pv-appt-card--empty" aria-label="Browse services">
      <div class="pv-appt-card-tag">
        <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
        No Upcoming Booking
      </div>
      <div class="pv-appt-empty-body">Ready to book your next service?</div>
      <div class="pv-appt-cta-big">Browse Services →</div>
    </a>
    <?php endif; ?>

  </div><!-- /pv-hero-inner -->

  <!-- Stats strip -->
  <div class="pv-hero-stats" role="region" aria-label="Quick stats">
    <div class="pv-hs-item">
      <span class="pv-hs-val"><?= $totalBookings ?></span>
      <span class="pv-hs-label">Total Bookings</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val yellow"><?= $upcomingCount ?></span>
      <span class="pv-hs-label">Upcoming</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val green"><?= $completedCount ?></span>
      <span class="pv-hs-label">Completed</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val blue"><?= number_format($loyaltyPoints) ?></span>
      <span class="pv-hs-label">Loyalty Points</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val accent"><?= $spentDisplay ?></span>
      <span class="pv-hs-label">Total Spent</span>
    </div>
  </div>
</header>

<!-- ══ MAIN PAGE ══ -->
<main class="pv-page" role="main">

  <!-- ── Browse Categories ── -->
  <section class="pv-section" aria-label="Browse by category">
    <div class="pv-section-head">
      <h2 class="pv-section-title">Browse Categories</h2>
      <a href="<?= BASE_URL ?>browse" class="pv-link">See all →</a>
    </div>
    <div class="pv-categories-row">
      <?php foreach ($categories as $cat): ?>
      <a href="<?= BASE_URL ?>browse?category=<?= $cat['slug'] ?>" class="pv-cat-pill">
        <span class="pv-cat-icon" aria-hidden="true"><i class="fa-solid <?= $cat['icon'] ?>"></i></span>
        <span class="pv-cat-label"><?= $cat['label'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── Main layout: providers + sidebar ── -->
  <div class="pv-layout">

    <div class="pv-main">

      <!-- ── Browse Map ── -->
      <section class="pv-section pv-map-section" aria-label="Browse map" id="browseMapSection">
        <div class="pv-section-head">
          <div>
            <h2 class="pv-section-title">Browse Map</h2>
            <p class="pv-section-sub">
              <i class="fa-solid fa-location-dot" style="color:var(--gold-dim);font-size:.7rem" aria-hidden="true"></i>
              Bacolod City, Negros Occidental
            </p>
          </div>
          <div style="display:flex;gap:.6rem;align-items:center;">
            <button id="btnUseLocation" class="pv-map-locate-btn" title="Use my location">
              <i class="fa-solid fa-crosshairs"></i>
              <span>Use My Location</span>
            </button>
            <a href="<?= BASE_URL ?>browse?view=map" class="pv-link">Full Map →</a>
          </div>
        </div>

        <?php if (empty($featuredProviders)): ?>
        <div class="pv-empty-state">
          <div class="pv-empty-icon" aria-hidden="true">🏪</div>
          <p>No providers available yet in your area.</p>
          <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Browse Anyway →</a>
        </div>
        <?php else: ?>

        <!-- Split layout: sidebar + map -->
        <div class="pv-map-split" id="pvMapSplit">

          <!-- Sidebar toggle button (visible on map edge) -->
          <button class="pv-map-sidebar-toggle" id="pvSidebarToggle" title="Toggle business list" aria-label="Toggle business list">
            <i class="fa-solid fa-chevron-left" id="pvSidebarToggleIcon"></i>
          </button>

          <!-- Left panel: business list -->
          <aside class="pv-map-sidebar" id="pvMapSidebar" aria-label="Nearby businesses">
            <div class="pv-map-sidebar-head">
              <span class="pv-map-sidebar-title">
                <i class="fa-solid fa-store"></i>
                Nearby Shops
              </span>
              <span class="pv-map-sidebar-count" id="pvShopCount"><?= count($featuredProviders) ?> shops</span>
            </div>
            <div class="pv-map-sidebar-list" id="pvShopList">
              <?php foreach ($featuredProviders as $idx => $p):
                $catSlug = $p['category_slug'] ?? '';
                $catName = $p['category_name'] ?? ucwords(strtolower(trim(explode(',', $p['service_types'] ?? '')[0])));
                $avgRating = round((float)$p['avg_rating'], 1);
                $pName = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                $loc = implode(', ', array_filter([$p['barangay'] ?? '', 'Bacolod City']));
              ?>
              <div class="pv-shop-item <?= $idx === 0 ? 'is-active' : '' ?>"
                   data-provider-id="<?= (int)$p['id'] ?>"
                   data-lat="<?= !empty($p['latitude']) ? (float)$p['latitude'] : '' ?>"
                   data-lng="<?= !empty($p['longitude']) ? (float)$p['longitude'] : '' ?>"
                   tabindex="0" role="button"
                   aria-label="<?= htmlspecialchars($p['business_name']) ?>">
                <div class="pv-shop-item-icon pv-cat-icon-<?= htmlspecialchars($catSlug) ?>">
                  <?php
                    $iconMap = [
                      'barbershop' => '<i class="fa-solid fa-scissors"></i>',
                      'hair-salon' => '<i class="fa-solid fa-scissors"></i>',
                      'nail-care'  => '<i class="fa-solid fa-hand-sparkles"></i>',
                      'massage-therapy' => '<i class="fa-solid fa-spa"></i>',
                      'skincare-facial' => '<i class="fa-solid fa-face-smile-beam"></i>',
                      'fitness-training'=> '<i class="fa-solid fa-dumbbell"></i>',
                      'home-cleaning'   => '<i class="fa-solid fa-broom"></i>',
                      'pet-grooming'    => '<i class="fa-solid fa-paw"></i>',
                      'dental'          => '<i class="fa-solid fa-tooth"></i>',
                      'makeup'          => '<i class="fa-solid fa-wand-sparkles"></i>',
                    ];
                    echo $iconMap[$catSlug] ?? '<i class="fa-solid fa-store"></i>';
                  ?>
                </div>
                <div class="pv-shop-item-body">
                  <div class="pv-shop-item-name"><?= htmlspecialchars($p['business_name']) ?></div>
                  <div class="pv-shop-item-cat"><?= htmlspecialchars($catName) ?></div>
                  <div class="pv-shop-item-meta">
                    <?php if ($avgRating > 0): ?>
                    <span class="pv-shop-item-rating">
                      <i class="fa-solid fa-star"></i>
                      <?= number_format($avgRating, 1) ?>
                    </span>
                    <?php endif; ?>
                    <span class="pv-shop-item-loc">
                      <i class="fa-solid fa-location-dot"></i>
                      <?= htmlspecialchars($loc) ?>
                    </span>
                  </div>
                  <div class="pv-shop-item-distance" data-provider-id="<?= (int)$p['id'] ?>">
                    <!-- distance populated by JS -->
                  </div>
                </div>
                <a href="<?= BASE_URL ?>providers/<?= (int)$p['id'] ?>" class="pv-shop-item-arrow" tabindex="-1" aria-hidden="true">
                  <i class="fa-solid fa-chevron-right"></i>
                </a>
              </div>
              <?php endforeach; ?>
            </div>
          </aside>

          <!-- Right panel: Leaflet map -->
          <div class="pv-map-panel">
            <div id="providerMap" class="pv-map" aria-label="Interactive map of providers near you"></div>
          </div>

        </div><!-- /pv-map-split -->
        <?php endif; ?>
      </section>

      <!-- ── Recent Bookings ── -->
      <?php if (!empty($recentBookings)): ?>
      <section class="pv-section" aria-label="Recent bookings">
        <div class="pv-section-head">
          <h2 class="pv-section-title">Recent Bookings</h2>
          <a href="<?= BASE_URL ?>bookings" class="pv-link">View all →</a>
        </div>
        <div class="pv-card pv-card--table">
          <div class="pv-rb-table-wrap">
            <table class="pv-rb-table">
              <thead>
                <tr>
                  <th>Service</th>
                  <th>Provider</th>
                  <th>Price</th>
                  <th>Duration</th>
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
                  <td><div class="pv-rb-name"><?= htmlspecialchars($b['service_name']) ?></div></td>
                  <td><?= htmlspecialchars($b['provider_name'] ?? '—') ?></td>
                  <td class="pv-rb-price">₱<?= number_format((float)$b['price'], 2) ?></td>
                  <td><?= $dLabel ?></td>
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
        </div>
      </section>
      <?php endif; ?>

    </div><!-- /pv-main -->

    <!-- ── SIDEBAR ── -->
    <aside class="pv-sidebar" aria-label="Sidebar">

      <!-- Quick Actions -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Quick Actions</h2></div>
        <div class="pv-actions">
          <a href="<?= BASE_URL ?>browse" class="pv-action is-primary">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
            <div class="pv-action-txt">
              <strong>Browse Services</strong>
              <span>Find providers near you</span>
            </div>
          </a>
          <?php if ($upcomingCount > 0): ?>
          <a href="<?= BASE_URL ?>bookings?status=pending" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-clock"></i></span>
            <div class="pv-action-txt">
              <strong>Upcoming Bookings</strong>
              <span><?= $upcomingCount ?> appointment<?= $upcomingCount > 1 ? 's' : '' ?> scheduled</span>
            </div>
          </a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>loyalty" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-star"></i></span>
            <div class="pv-action-txt">
              <strong>Loyalty Rewards</strong>
              <span>Redeem <?= number_format($loyaltyPoints) ?> pts</span>
            </div>
          </a>
          <a href="<?= BASE_URL ?>profile" class="pv-action">
            <span class="pv-action-ico" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
            <div class="pv-action-txt">
              <strong>My Profile</strong>
              <span>Update account details</span>
            </div>
          </a>
        </div>
      </div>

      <!-- Upcoming Appointments -->
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
            <div class="pv-upcoming-price">₱<?= number_format($u['price'], 0) ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Loyalty Status -->
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

  <!-- ── Review Nudge Banner ── -->
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

<!-- ══ SCRIPTS ══ -->

<!-- Leaflet JS — load before map init script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<script>
/* ══════════════════════════════════════════════
   Browse Map — Split Layout with Category Icons
   QuickBook Dashboard (preview-only mode)
══════════════════════════════════════════════ */
(function () {
  var mapEl = document.getElementById('providerMap');
  if (!mapEl) return;

  var BACOLOD = [10.6840, 122.9560];
  var userLatLng = null;
  var markers = [];
  var activeMarkerId = null;

  /* ── Init map ── */
  var map = L.map('providerMap', {
    center: BACOLOD,
    zoom: 13,
    zoomControl: false,
    scrollWheelZoom: true,
    attributionControl: true
  });

  /* Custom zoom position */
  L.control.zoom({ position: 'bottomright' }).addTo(map);

  /* OpenStreetMap tiles */
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>',
    maxZoom: 19
  }).addTo(map);

  /* ── Category icon SVG paths (same as browse.php $catIconMap) ── */
  var catSvgPaths = {
    'barbershop': '<path d="M10 5 L10 19" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M20 5 L20 19" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M7.5 12 L22.5 12" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="22.5" r="3" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/><circle cx="20" cy="22.5" r="3" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/>',
    'hair-salon': '<path d="M9 26 C9 18 13 15 15 8" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M21 26 C21 18 17 15 15 8" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M11 10 C11 7.5 12.7 6 15 6 C17.3 6 19 7.5 19 10" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round" fill="none"/><path d="M10 19 L20 19" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/>',
    'nail-care':  '<path d="M8 17 L8 12 C8 8.7 11.1 6 15 6 C18.9 6 22 8.7 22 12 L22 17" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round" fill="none"/><rect x="6" y="17" width="18" height="7" rx="3" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/><circle cx="12" cy="20.5" r="1.2" fill="#C9A84C" opacity=".6"/><circle cx="18" cy="20.5" r="1.2" fill="#C9A84C" opacity=".6"/>',
    'massage-therapy': '<circle cx="15" cy="8" r="3.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/><path d="M6 24 C6 18.5 9.5 15 15 15 C20.5 15 24 18.5 24 24" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round" fill="none"/><path d="M8 16.5 C5.5 17.5 4 20 4 22.5" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" opacity=".5" fill="none"/><path d="M22 16.5 C24.5 17.5 26 20 26 22.5" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" opacity=".5" fill="none"/>',
    'skincare-facial': '<circle cx="15" cy="15" r="9" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/><path d="M11.5 17 C12.5 18.5 13.8 19.2 15 19.2 C16.2 19.2 17.5 18.5 18.5 17" stroke="#C9A84C" stroke-width="1.4" stroke-linecap="round" fill="none"/><circle cx="11.5" cy="12.5" r="1" fill="#C9A84C"/><circle cx="18.5" cy="12.5" r="1" fill="#C9A84C"/>',
    'fitness-training': '<rect x="11" y="8" width="8" height="14" rx="1.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/><rect x="7" y="10.5" width="4" height="9" rx="1.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.4"/><rect x="19" y="10.5" width="4" height="9" rx="1.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.4"/><path d="M3 15 L7 15" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round"/><path d="M23 15 L27 15" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round"/>',
    'home-cleaning': '<path d="M5 26 L5 13 L15 5 L25 13 L25 26" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/><rect x="11" y="18" width="8" height="8" rx="1.2" fill="#1A1000" stroke="#C9A84C" stroke-width="1.4"/><path d="M10 13 L15 9.5 L20 13" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" opacity=".5" fill="none"/>',
    'pet-grooming': '<ellipse cx="12" cy="17" rx="6" ry="7.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/><path d="M18 14 C20 14 25 12 25 8.5 C25 5.5 21.5 6 20.5 9" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round" fill="none"/><path d="M18 16 C20.5 17 24.5 16 24.5 13" stroke="#C9A84C" stroke-width="1.4" stroke-linecap="round" fill="none"/><circle cx="10" cy="14.5" r="1" fill="#C9A84C" opacity=".6"/><circle cx="13.5" cy="13" r="1" fill="#C9A84C" opacity=".6"/>',
    'dental': '<path d="M10 6 C7 6 5 8.5 5 11 C5 14 7 15 8 19 C9 22 9.5 25 11.5 25 C13 25 13.5 22 15 22 C16.5 22 17 25 18.5 25 C20.5 25 21 22 22 19 C23 15 25 14 25 11 C25 8.5 23 6 20 6 C18 6 17 7.5 15 7.5 C13 7.5 12 6 10 6 Z" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5" stroke-linejoin="round"/><path d="M10.5 6.5 C10.5 10 12 12 15 12 C18 12 19.5 10 19.5 6.5" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" fill="none" opacity=".6"/>',
    'makeup': '<path d="M15 22 L15 12" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M10 12 C10 8.7 12 7 15 7 C18 7 20 8.7 20 12 L10 12 Z" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5" stroke-linejoin="round"/><rect x="8" y="19" width="14" height="6" rx="3" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/><path d="M11 22 L19 22" stroke="#C9A84C" stroke-width="1" stroke-linecap="round" opacity=".4"/>',
    '_default': '<rect x="4" y="4" width="9" height="9" rx="2" fill="#C9A84C" opacity=".9"/><rect x="17" y="4" width="9" height="9" rx="2" fill="#C9A84C" opacity=".5"/><rect x="4" y="17" width="9" height="9" rx="2" fill="#C9A84C" opacity=".5"/><rect x="17" y="17" width="9" height="9" rx="2" fill="#C9A84C" opacity=".9"/>'
  };

  /* ── Category color accents ── */
  var catColors = {
    'barbershop':      '#E8A650',
    'hair-salon':      '#D4B483',
    'nail-care':       '#E88080',
    'massage-therapy': '#7CB9B0',
    'skincare-facial': '#C8A0C8',
    'fitness-training':'#80A8D8',
    'home-cleaning':   '#88C888',
    'pet-grooming':    '#E8B870',
    'dental':          '#80C8D8',
    'makeup':          '#D880A8',
    '_default':        '#C9A84C'
  };

  function buildCatIcon(slug, isActive) {
    var paths = catSvgPaths[slug] || catSvgPaths['_default'];
    var color = catColors[slug]   || catColors['_default'];
    var scale = isActive ? 'scale(1.25)' : 'scale(1)';
    var shadow = isActive
      ? 'drop-shadow(0 4px 12px rgba(201,168,76,.7))'
      : 'drop-shadow(0 2px 6px rgba(0,0,0,.25))';
    var html =
      '<div class="qb-map-cat-marker' + (isActive ? ' is-active' : '') + '" style="' +
        'transform:' + scale + ';filter:' + shadow + ';' +
      '">' +
        '<div class="qb-map-cat-badge" style="--cat-color:' + color + '">' +
          '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">' + paths + '</svg>' +
        '</div>' +
        '<div class="qb-map-cat-tip" style="border-top-color:' + color + '"></div>' +
      '</div>';
    return L.divIcon({
      className: '',
      html: html,
      iconSize:   [44, 54],
      iconAnchor: [22, 54],
      popupAnchor:[0, -56]
    });
  }

  /* ── User location marker ── */
  var userMarker = null;
  function buildUserIcon() {
    return L.divIcon({
      className: '',
      html: '<div class="qb-map-user-dot"><div class="qb-map-user-pulse"></div></div>',
      iconSize:   [20, 20],
      iconAnchor: [10, 10]
    });
  }

  /* ── Haversine distance (km) ── */
  function haversine(lat1, lng1, lat2, lng2) {
    var R = 6371;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLng = (lng2 - lng1) * Math.PI / 180;
    var a = Math.sin(dLat/2)*Math.sin(dLat/2) +
            Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*
            Math.sin(dLng/2)*Math.sin(dLng/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  function fmtDist(km) {
    return km < 1 ? Math.round(km * 1000) + ' m away' : km.toFixed(1) + ' km away';
  }

  /* ── Info card close ── */
  function closeInfoCard() {
    var card = document.getElementById('qbMapInfoCard');
    if (card) {
      card.classList.remove('is-visible');
      setTimeout(function () { if (card) card.style.display = 'none'; }, 200);
    }
    // Deactivate all markers
    markers.forEach(function (m) {
      m.setIcon(buildCatIcon(m._qbSlug, false));
    });
    activeMarkerId = null;
    document.querySelectorAll('.pv-shop-item').forEach(function (el) {
      el.classList.remove('is-active');
    });
  }

  /* ── Show info card (preview only — no full popup) ── */
  function showInfoCard(p) {
    var card = document.getElementById('qbMapInfoCard');
    if (!card) return;

    var distStr = '';
    if (userLatLng && p.lat && p.lng) {
      var km = haversine(userLatLng[0], userLatLng[1], p.lat, p.lng);
      distStr = fmtDist(km);
    }

    var starsHtml = '';
    var full = Math.floor(p.rating);
    var half = (p.rating - full) >= 0.5 ? 1 : 0;
    var empty = 5 - full - half;
    for (var i=0;i<full; i++) starsHtml += '<i class="fa-solid fa-star"></i>';
    if (half)                  starsHtml += '<i class="fa-solid fa-star-half-stroke"></i>';
    for (var i=0;i<empty;i++)  starsHtml += '<i class="fa-regular fa-star"></i>';

    var catColor = catColors[p.categorySlug] || catColors['_default'];
    var paths    = catSvgPaths[p.categorySlug] || catSvgPaths['_default'];

    card.innerHTML =
      '<div class="qb-info-card-inner">' +
        '<button class="qb-info-card-close" id="qbInfoCardClose" aria-label="Close">' +
          '<i class="fa-solid fa-xmark"></i>' +
        '</button>' +
        '<div class="qb-info-card-icon" style="--cat-color:' + catColor + '">' +
          '<svg viewBox="0 0 30 30" fill="none">' + paths + '</svg>' +
        '</div>' +
        '<div class="qb-info-card-body">' +
          '<div class="qb-info-card-cat">' + escHtml(p.category) + '</div>' +
          '<div class="qb-info-card-name">' + escHtml(p.name) + '</div>' +
          '<div class="qb-info-card-provider"><i class="fa-solid fa-user-tie"></i> ' + escHtml(p.providerName) + '</div>' +
          '<div class="qb-info-card-rating">' +
            '<span class="qb-info-stars">' + starsHtml + '</span>' +
            '<span class="qb-info-rating-val">' + (p.rating > 0 ? p.rating.toFixed(1) : 'New') + '</span>' +
            (p.reviewCount > 0 ? '<span class="qb-info-reviews">(' + p.reviewCount + ' reviews)</span>' : '') +
          '</div>' +
          (distStr ? '<div class="qb-info-card-distance"><i class="fa-solid fa-route"></i> ' + distStr + '</div>' : '') +
          '<div class="qb-info-card-address"><i class="fa-solid fa-location-dot"></i> ' + escHtml(p.address || 'Bacolod City, Negros Occidental') + '</div>' +
        '</div>' +
        '<div class="qb-info-card-actions">' +
          '<a href="' + p.urlView + '" class="qb-info-btn qb-info-btn--outline"><i class="fa-solid fa-store"></i> View Shop</a>' +
          '<a href="' + p.urlBook + '" class="qb-info-btn qb-info-btn--primary"><i class="fa-solid fa-calendar-check"></i> Book Now</a>' +
        '</div>' +
      '</div>';

    card.style.display = 'block';
    setTimeout(function () { card.classList.add('is-visible'); }, 10);

    document.getElementById('qbInfoCardClose').addEventListener('click', closeInfoCard);
  }

  function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ── Sort shop list by distance ── */
  function sortShopsByDistance(lat, lng) {
    var list = document.getElementById('pvShopList');
    if (!list) return;
    var items = Array.from(list.querySelectorAll('.pv-shop-item'));
    items.forEach(function (item) {
      var pLat = parseFloat(item.dataset.lat);
      var pLng = parseFloat(item.dataset.lng);
      if (pLat && pLng) {
        item._dist = haversine(lat, lng, pLat, pLng);
      } else {
        item._dist = 9999;
      }
      var distEl = item.querySelector('.pv-shop-item-distance');
      if (distEl) {
        distEl.innerHTML = item._dist < 9999
          ? '<i class="fa-solid fa-route"></i> ' + fmtDist(item._dist)
          : '';
      }
    });
    items.sort(function (a, b) { return a._dist - b._dist; });
    items.forEach(function (item) { list.appendChild(item); });
    var countEl = document.getElementById('pvShopCount');
    if (countEl) countEl.textContent = items.length + ' shops';
  }

  /* ── Providers data ── */
  var providers = <?= $mapProvidersJson ?>;
  if (!providers.length) return;

  var bounds = [];

  providers.forEach(function (p, idx) {
    var lat = p.lat, lng = p.lng;
    if (!lat || !lng) {
      var angle  = (idx / providers.length) * 2 * Math.PI;
      var radius = 0.012 + (idx % 3) * 0.006;
      lat = BACOLOD[0] + radius * Math.cos(angle);
      lng = BACOLOD[1] + radius * Math.sin(angle);
      p.lat = lat; p.lng = lng;
    }
    bounds.push([lat, lng]);

    var marker = L.marker([lat, lng], { icon: buildCatIcon(p.categorySlug, false) }).addTo(map);
    marker._qbSlug = p.categorySlug;
    marker._qbId   = p.id;
    markers.push(marker);

    /* Dashboard: preview-only — show info card, no navigation */
    marker.on('click', function () {
      // Deactivate previous
      markers.forEach(function (m) { m.setIcon(buildCatIcon(m._qbSlug, false)); });
      marker.setIcon(buildCatIcon(p.categorySlug, true));
      activeMarkerId = p.id;
      showInfoCard(p);

      // Highlight sidebar item
      document.querySelectorAll('.pv-shop-item').forEach(function (el) {
        el.classList.remove('is-active');
        if (parseInt(el.dataset.providerId) === p.id) {
          el.classList.add('is-active');
          el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    });
  });

  /* Fit bounds */
  if (bounds.length === 1) {
    map.setView(bounds[0], 15);
  } else if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
  }

  /* ── Sidebar item click — fly to marker, show card ── */
  document.querySelectorAll('.pv-shop-item').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (e.target.closest('a')) return; // let the arrow link through
      var id = parseInt(el.dataset.providerId);
      var p = providers.find(function (pr) { return pr.id === id; });
      if (!p) return;
      map.flyTo([p.lat, p.lng], 16, { animate: true, duration: 0.8 });
      markers.forEach(function (m) { m.setIcon(buildCatIcon(m._qbSlug, false)); });
      var m = markers.find(function (mk) { return mk._qbId === id; });
      if (m) m.setIcon(buildCatIcon(p.categorySlug, true));
      activeMarkerId = id;
      showInfoCard(p);
      document.querySelectorAll('.pv-shop-item').forEach(function (s) { s.classList.remove('is-active'); });
      el.classList.add('is-active');
    });
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); el.click(); }
    });
  });

  /* ── Close card on map click ── */
  map.on('click', closeInfoCard);

  /* ── Use My Location ── */
  var locBtn = document.getElementById('btnUseLocation');
  if (locBtn) {
    locBtn.addEventListener('click', function () {
      locBtn.disabled = true;
      locBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Locating…</span>';
      if (!navigator.geolocation) {
        locBtn.innerHTML = '<i class="fa-solid fa-crosshairs"></i> <span>Not supported</span>';
        locBtn.disabled = false;
        return;
      }
      navigator.geolocation.getCurrentPosition(function (pos) {
        userLatLng = [pos.coords.latitude, pos.coords.longitude];
        if (userMarker) userMarker.remove();
        userMarker = L.marker(userLatLng, { icon: buildUserIcon(), zIndexOffset: 1000 }).addTo(map);
        userMarker.bindTooltip('You are here', { permanent: false, direction: 'top', offset: [0, -14] });
        map.flyTo(userLatLng, 15, { animate: true, duration: 1.2 });
        sortShopsByDistance(userLatLng[0], userLatLng[1]);
        locBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span>Located</span>';
        locBtn.classList.add('is-located');
        locBtn.disabled = false;
      }, function () {
        locBtn.innerHTML = '<i class="fa-solid fa-crosshairs"></i> <span>Use My Location</span>';
        locBtn.disabled = false;
      }, { enableHighAccuracy: true, timeout: 10000 });
    });
  }

  /* ── Sidebar toggle ── */
  var sidebar      = document.getElementById('pvMapSidebar');
  var toggleBtn    = document.getElementById('pvSidebarToggle');
  var toggleIcon   = document.getElementById('pvSidebarToggleIcon');
  var sidebarOpen  = true;

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function () {
      sidebarOpen = !sidebarOpen;
      sidebar.classList.toggle('is-hidden', !sidebarOpen);
      toggleIcon.className = sidebarOpen ? 'fa-solid fa-chevron-left' : 'fa-solid fa-chevron-right';
      setTimeout(function () { map.invalidateSize(); }, 320);
    });
  }

  /* Create floating info card container */
  var infoCard = document.createElement('div');
  infoCard.id = 'qbMapInfoCard';
  infoCard.className = 'qb-map-info-card';
  infoCard.style.display = 'none';
  var mapPanel = document.querySelector('.pv-map-panel');
  if (mapPanel) mapPanel.appendChild(infoCard);

})();
</script>

<script>
/* ── Theme Toggle ── */
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

  applyTheme(localStorage.getItem('qb-theme') || 'light');

  if (btn) {
    btn.addEventListener('click', function () {
      var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem('qb-theme', next);
      applyTheme(next);
    });
  }
})();
</script>

<script>
/* ── Profile Dropdown ── */
(function () {
  var trigger  = document.getElementById('profileTrigger');
  var dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;

  function open()   { trigger.classList.add('is-open'); dropdown.classList.add('is-open'); trigger.setAttribute('aria-expanded', 'true'); }
  function close()  { trigger.classList.remove('is-open'); dropdown.classList.remove('is-open'); trigger.setAttribute('aria-expanded', 'false'); }
  function toggle() { dropdown.classList.contains('is-open') ? close() : open(); }

  trigger.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
  trigger.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    if (e.key === 'Escape') close();
  });
  document.addEventListener('click',   function (e) { if (!dropdown.contains(e.target) && !trigger.contains(e.target)) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>

</body>
</html>
