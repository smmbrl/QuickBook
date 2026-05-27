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
           COALESCE(AVG(r.rating), 0) AS avg_rating,
           COUNT(DISTINCT r.id) AS review_count,
           COUNT(DISTINCT b.id) AS booking_count,
           GROUP_CONCAT(DISTINCT s.service_type ORDER BY s.service_type SEPARATOR ', ') AS service_types,
           MIN(s.price) AS min_price
    FROM tbl_provider_profiles pp
    LEFT JOIN tbl_services s ON s.provider_id = pp.id AND s.is_active = 1
    LEFT JOIN tbl_reviews r ON r.provider_id = pp.id
    LEFT JOIN tbl_bookings b ON b.provider_id = pp.id AND b.status = 'completed'
    WHERE pp.is_approved = 1 AND pp.is_active = 1 AND pp.city = 'Bacolod City'
    GROUP BY pp.id
    ORDER BY avg_rating DESC, booking_count DESC
    LIMIT 6
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
    ['label' => 'Nail Tech',  'icon' => 'fa-hand-sparkles', 'slug' => 'nail'],
    ['label' => 'Hair Salon', 'icon' => 'fa-scissors',      'slug' => 'hair'],
    ['label' => 'Massage',    'icon' => 'fa-spa',           'slug' => 'massage'],
    ['label' => 'Dental',     'icon' => 'fa-tooth',         'slug' => 'dental'],
    ['label' => 'Makeup',     'icon' => 'fa-wand-sparkles', 'slug' => 'makeup'],
    ['label' => 'Cleaning',   'icon' => 'fa-broom',         'slug' => 'cleaning'],
    ['label' => 'Pet Care',   'icon' => 'fa-paw',           'slug' => 'pet'],
    ['label' => 'Repair',     'icon' => 'fa-wrench',        'slug' => 'repair'],
];

/* ── Build provider map data for JS ── */
$mapProviders = [];
foreach ($featuredProviders as $p) {
    $mapProviders[] = [
        'id'       => (int)$p['id'],
        'name'     => $p['business_name'],
        'barangay' => $p['barangay'] ?? '',
        'lat'      => !empty($p['latitude'])  ? (float)$p['latitude']  : null,
        'lng'      => !empty($p['longitude']) ? (float)$p['longitude'] : null,
        'rating'   => round((float)$p['avg_rating'], 1),
        'category' => !empty($p['service_types']) ? ucwords(strtolower(trim(explode(',', $p['service_types'])[0]))) : '',
        'urlView'  => BASE_URL . 'provider/' . (int)$p['id'],
        'urlBook'  => BASE_URL . 'book/'     . (int)$p['id'],
        'minPrice' => !empty($p['min_price']) ? '₱' . number_format((float)$p['min_price'], 0) : '',
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

      <!-- ── Providers Near You ── -->
      <section class="pv-section" aria-label="Providers near you">
        <div class="pv-section-head">
          <div>
            <h2 class="pv-section-title">Providers Near You</h2>
            <p class="pv-section-sub">
              <i class="fa-solid fa-location-dot" style="color:var(--gold-dim);font-size:.7rem" aria-hidden="true"></i>
              Bacolod City
            </p>
          </div>
          <a href="<?= BASE_URL ?>browse" class="pv-link">View all →</a>
        </div>

        <?php if (empty($featuredProviders)): ?>
        <div class="pv-empty-state">
          <div class="pv-empty-icon" aria-hidden="true">🏪</div>
          <p>No providers available yet in your area.</p>
          <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Browse Anyway →</a>
        </div>
        <?php else: ?>

        <!-- Leaflet Map -->
        <div class="pv-map-wrap">
          <div id="providerMap" class="pv-map" aria-label="Map of providers near you"></div>
        </div>

        <!-- Provider Cards -->
        <div class="pv-provider-grid">
          <?php foreach ($featuredProviders as $p):
            $avgRating   = round((float)$p['avg_rating'], 1);
            $categoryRaw = strtolower($p['service_types'] ?? '');
            $catEmoji    = '🏪';
            if      (str_contains($categoryRaw, 'nail'))                                           $catEmoji = '💅';
            elseif  (str_contains($categoryRaw, 'hair'))                                           $catEmoji = '✂️';
            elseif  (str_contains($categoryRaw, 'massage') || str_contains($categoryRaw, 'spa'))   $catEmoji = '🧖';
            elseif  (str_contains($categoryRaw, 'dental'))                                         $catEmoji = '🦷';
            elseif  (str_contains($categoryRaw, 'clean'))                                          $catEmoji = '🧹';
            elseif  (str_contains($categoryRaw, 'pet'))                                            $catEmoji = '🐾';
            elseif  (str_contains($categoryRaw, 'repair'))                                         $catEmoji = '🔧';
            $firstCategory = !empty($p['service_types'])
                ? ucwords(strtolower(trim(explode(',', $p['service_types'])[0])))
                : '';
          ?>
          <div class="pv-provider-card" data-provider-id="<?= (int)$p['id'] ?>">
            <div class="pv-provider-photo">
              <?php if (!empty($p['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($p['profile_photo']) ?>" alt="<?= htmlspecialchars($p['business_name']) ?>">
              <?php else: ?>
                <div class="pv-provider-photo-placeholder"><?= $catEmoji ?></div>
              <?php endif; ?>
              <?php if ($avgRating >= 4.5): ?>
                <div class="pv-provider-top-badge">⭐ Top Rated</div>
              <?php endif; ?>
            </div>
            <div class="pv-provider-body">
              <div class="pv-provider-name"><?= htmlspecialchars($p['business_name']) ?></div>
              <?php if ($firstCategory): ?>
              <div class="pv-provider-category"><?= htmlspecialchars($firstCategory) ?></div>
              <?php endif; ?>
              <div class="pv-provider-meta-row">
                <span class="pv-provider-stars" aria-label="Rated <?= $avgRating ?> out of 5">
                  <?= starRating($avgRating) ?>
                  <span class="pv-stars-val"><?= $avgRating > 0 ? number_format($avgRating, 1) : 'New' ?></span>
                  <?php if ((int)$p['review_count'] > 0): ?>
                  <span class="pv-stars-count">(<?= (int)$p['review_count'] ?>)</span>
                  <?php endif; ?>
                </span>
              </div>
              <div class="pv-provider-loc">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <?= !empty($p['barangay']) ? htmlspecialchars($p['barangay']) . ', Bacolod City' : 'Bacolod City' ?>
              </div>
              <?php if (!empty($p['min_price'])): ?>
              <div class="pv-provider-from">From <strong><?= fmtMoney((float)$p['min_price']) ?></strong></div>
              <?php endif; ?>
            </div>
            <div class="pv-provider-actions">
              <a href="<?= BASE_URL ?>provider/<?= (int)$p['id'] ?>" class="pv-provider-btn pv-provider-btn--outline">View Profile</a>
              <a href="<?= BASE_URL ?>book/<?= (int)$p['id'] ?>"    class="pv-provider-btn pv-provider-btn--primary">Book Now</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

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
/* ── Leaflet Map Init ── */
(function () {
  var mapEl = document.getElementById('providerMap');
  if (!mapEl) return;

  var BACOLOD = [10.6840, 122.9560];

  var map = L.map('providerMap', {
    center: BACOLOD,
    zoom: 13,
    zoomControl: true,
    scrollWheelZoom: false,
    attributionControl: true
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>',
    maxZoom: 18
  }).addTo(map);

  var providers = <?= $mapProvidersJson ?>;

  if (!providers.length) return;

  var bounds = [];

  providers.forEach(function (p, idx) {
    var lat = p.lat, lng = p.lng;

    /* Fallback scatter around Bacolod if no coords saved yet */
    if (!lat || !lng) {
      var angle  = (idx / providers.length) * 2 * Math.PI;
      var radius = 0.012 + (idx % 3) * 0.006;
      lat = BACOLOD[0] + radius * Math.cos(angle);
      lng = BACOLOD[1] + radius * Math.sin(angle);
    }

    bounds.push([lat, lng]);

    /* Custom gold pin icon */
    var icon = L.divIcon({
      className: '',
      html: '<div class="pv-map-pin"><i class="fa-solid fa-location-dot"></i></div>',
      iconSize:   [28, 34],
      iconAnchor: [14, 34],
      popupAnchor:[0, -36]
    });

    var locLine   = p.barangay ? p.barangay + ', Bacolod City' : 'Bacolod City';
    var ratingStr = p.rating > 0 ? '&#9733; ' + p.rating : 'New';
    var priceStr  = p.minPrice ? '<div class="pv-map-popup-price">From ' + p.minPrice + '</div>' : '';
    var catStr    = p.category ? '<div class="pv-map-popup-cat">' + p.category + '</div>' : '';
    var dirUrl    = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(locLine);

    var popupHtml =
      '<div class="pv-map-popup">' +
        '<div class="pv-map-popup-name">' + p.name + '</div>' +
        catStr +
        '<div class="pv-map-popup-meta">' + ratingStr + ' &nbsp;&middot;&nbsp; &#128205; ' + locLine + '</div>' +
        priceStr +
        '<div class="pv-map-popup-actions">' +
          '<a href="' + p.urlView + '" class="pv-map-popup-btn pv-map-popup-btn--outline">View Profile</a>' +
          '<a href="' + p.urlBook + '" class="pv-map-popup-btn pv-map-popup-btn--primary">Book Now</a>' +
        '</div>' +
        '<a href="' + dirUrl + '" target="_blank" rel="noopener" class="pv-map-popup-directions">&#128506; Get Directions</a>' +
      '</div>';

    var marker = L.marker([lat, lng], { icon: icon }).addTo(map);
    marker.bindPopup(popupHtml, { maxWidth: 240, closeButton: false });

    /* Highlight matching provider card on marker click */
    marker.on('click', function () {
      document.querySelectorAll('.pv-provider-card').forEach(function (c) {
        c.classList.remove('is-highlighted');
      });
      var card = document.querySelector('[data-provider-id="' + p.id + '"]');
      if (card) {
        card.classList.add('is-highlighted');
        setTimeout(function () { card.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 200);
      }
    });
  });

  /* Fit map to show all markers */
  if (bounds.length === 1) {
    map.setView(bounds[0], 15);
  } else if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
  }
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