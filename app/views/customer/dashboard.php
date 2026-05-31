<?php
// app/views/customer/dashboard.php
$name   = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$email  = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

/* ── Stats ── */
$stTotal = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND deleted_at IS NULL");
$stTotal->execute([$userId]);
$totalBookings = (int)$stTotal->fetchColumn();

$stUpcoming = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE() AND deleted_at IS NULL");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();

$stCompleted = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status = 'completed' AND deleted_at IS NULL");
$stCompleted->execute([$userId]);
$completedCount = (int)$stCompleted->fetchColumn();

$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status = 'pending' AND deleted_at IS NULL");
$stPending->execute([$userId]);
$pendingCount = (int)$stPending->fetchColumn();

$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();

$stSpent = $db->prepare("SELECT COALESCE(SUM(b.total_amount),0) FROM tbl_bookings b WHERE b.customer_id = ? AND b.status = 'completed' AND b.deleted_at IS NULL");
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
      AND b.deleted_at IS NULL
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
      AND b.deleted_at IS NULL
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
           GROUP_CONCAT(DISTINCT s.location_type ORDER BY s.location_type SEPARATOR ',') AS location_types,
           MIN(s.price) AS min_price
    FROM tbl_provider_profiles pp
    JOIN tbl_users u ON pp.user_id = u.id
    LEFT JOIN tbl_services s ON s.provider_id = pp.id AND s.is_active = 1
    LEFT JOIN tbl_reviews r ON r.provider_id = pp.id
    LEFT JOIN tbl_bookings b ON b.provider_id = pp.id AND b.status = 'completed'
    WHERE pp.is_approved = 1 AND pp.is_active = 1 AND u.is_active = 1 AND pp.city = 'Bacolod City'
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
    WHERE b.customer_id = ? AND b.deleted_at IS NULL
    ORDER BY b.created_at DESC LIMIT 5
");
$stRecent->execute([$userId]);
$recentBookings = $stRecent->fetchAll();

/* ── Analytics: bookings by status ── */
$stAnalytics = $db->prepare("
  SELECT status, COUNT(*) AS cnt
  FROM tbl_bookings
  WHERE customer_id = ? AND deleted_at IS NULL
  GROUP BY status
");
$stAnalytics->execute([$userId]);
$analyticsByStatus = [];
foreach ($stAnalytics->fetchAll() as $row) {
  $analyticsByStatus[$row['status']] = (int)$row['cnt'];
}
$chartCompleted  = $analyticsByStatus['completed']  ?? 0;
$chartPending    = $analyticsByStatus['pending']     ?? 0;
$chartConfirmed  = $analyticsByStatus['confirmed']   ?? 0;
$chartCancelled  = $analyticsByStatus['cancelled']   ?? 0;

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

/* ── Build provider map data for JS ── */

// Fetch category slugs for each featured provider
$dashCatSlugs = [];
if (!empty($featuredProviders)) {
    $dashProvIds      = array_column($featuredProviders, 'id');
    $dashPlaceholders = implode(',', array_fill(0, count($dashProvIds), '?'));
    $dashCatStmt = $db->prepare("
        SELECT pp.id AS provider_id, c.slug AS cat_slug
        FROM tbl_provider_profiles pp
        LEFT JOIN tbl_categories c ON pp.category_id = c.id
        WHERE pp.id IN ($dashPlaceholders)
    ");
    $dashCatStmt->execute($dashProvIds);
    foreach ($dashCatStmt->fetchAll() as $row) {
        $dashCatSlugs[(int)$row['provider_id']] = $row['cat_slug'] ?? '';
    }
}

$mapProviders = [];
foreach ($featuredProviders as $p) {
    /* Service mode: in_shop=Gold, home_service=Blue, both=Orange
       tbl_services.location_type enum: 'On-site','Remote','In-shop','Flexible' */
    $locTypes = array_filter(array_map('trim', explode(',', strtolower($p['location_types'] ?? ''))));
    $hasShop  = in_array('in-shop', $locTypes);
    $hasHome  = in_array('on-site', $locTypes) || in_array('remote', $locTypes);
    $hasFlex  = in_array('flexible', $locTypes);
    if ($hasFlex || ($hasShop && $hasHome)) { $serviceMode = 'flexible'; }
    elseif ($hasHome)                        { $serviceMode = 'home_service'; }
    else                                     { $serviceMode = 'in_shop'; }

    /* Address line */
    $barangay = trim($p['barangay'] ?? '');
    $city     = trim($p['city']     ?? 'Bacolod City');
    $address  = $barangay ? $barangay . ', ' . $city : $city;

    $mapProviders[] = [
        'id'           => (int)$p['id'],
        'name'         => $p['business_name'],
        'photo'        => !empty($p['profile_photo']) ? $p['profile_photo'] : '',
        'barangay'     => $barangay,
        'address'      => $address,
        'lat'          => !empty($p['latitude'])  ? (float)$p['latitude']  : null,
        'lng'          => !empty($p['longitude']) ? (float)$p['longitude'] : null,
        'rating'       => round((float)$p['avg_rating'], 1),
        'category'     => !empty($p['service_types']) ? ucwords(strtolower(trim(explode(',', $p['service_types'])[0]))) : '',
        'categorySlug' => $dashCatSlugs[(int)$p['id']] ?? '',
        'serviceMode'  => $serviceMode,
        'urlView'      => BASE_URL . 'provider/' . (int)$p['id'],
        'urlBook'      => BASE_URL . 'book/'     . (int)$p['id'],
        'minPrice'     => !empty($p['min_price']) ? '₱' . number_format((float)$p['min_price'], 0) : '',
    ];
}
$mapProvidersJson = json_encode($mapProviders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

// ── Category SVG icon paths for map markers (same icons as Browse Categories) ──
$dashCatIconPaths = [
    'barbershop' => 'M10 5 L10 19 M20 5 L20 19 M7.5 12 L22.5 12',
    'hair-salon' => 'M9 26 C9 18 13 15 15 8 M21 26 C21 18 17 15 15 8 M11 10 C11 7.5 12.7 6 15 6 C17.3 6 19 7.5 19 10 M10 19 L20 19',
    'nail-care' => 'M8 17 L8 12 C8 8.7 11.1 6 15 6 C18.9 6 22 8.7 22 12 L22 17',
    'massage-therapy' => 'circle|M6 24 C6 18.5 9.5 15 15 15 C20.5 15 24 18.5 24 24',
    'skincare-facial' => 'circle-face|M11.5 17 C12.5 18.5 13.8 19.2 15 19.2 C16.2 19.2 17.5 18.5 18.5 17',
    'fitness-training' => 'dumbbell',
    'home-cleaning' => 'M5 26 L5 13 L15 5 L25 13 L25 26',
    'pet-grooming' => 'pet',
    'dental' => 'dental',
    'makeup' => 'M15 22 L15 12 M10 12 C10 8.7 12 7 15 7 C18 7 20 8.7 20 12 L10 12 Z',
];

// Build full inline SVG strings for each category (used in JS divIcon html)
$dashIconSvgMap = [
    'barbershop' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 5 L10 19" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M20 5 L20 19" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M7.5 12 L22.5 12" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="22.5" r="3" fill="none" stroke="#C9A84C" stroke-width="1.5"/><circle cx="20" cy="22.5" r="3" fill="none" stroke="#C9A84C" stroke-width="1.5"/></svg>',
    'hair-salon' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 26 C9 18 13 15 15 8" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M21 26 C21 18 17 15 15 8" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M11 10 C11 7.5 12.7 6 15 6 C17.3 6 19 7.5 19 10" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round" fill="none"/><path d="M10 19 L20 19" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/></svg>',
    'nail-care' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 17 L8 12 C8 8.7 11.1 6 15 6 C18.9 6 22 8.7 22 12 L22 17" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round" fill="none"/><rect x="6" y="17" width="18" height="7" rx="3" fill="none" stroke="#C9A84C" stroke-width="1.5"/><circle cx="12" cy="20.5" r="1.2" fill="#C9A84C"/><circle cx="18" cy="20.5" r="1.2" fill="#C9A84C"/></svg>',
    'massage-therapy' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="15" cy="8" r="3.5" stroke="#C9A84C" stroke-width="1.5"/><path d="M6 24 C6 18.5 9.5 15 15 15 C20.5 15 24 18.5 24 24" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round" fill="none"/><path d="M8 16.5 C5.5 17.5 4 20 4 22.5" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" fill="none"/><path d="M22 16.5 C24.5 17.5 26 20 26 22.5" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" fill="none"/></svg>',
    'skincare-facial' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="15" cy="15" r="9" stroke="#C9A84C" stroke-width="1.5"/><path d="M11.5 17 C12.5 18.5 13.8 19.2 15 19.2 C16.2 19.2 17.5 18.5 18.5 17" stroke="#C9A84C" stroke-width="1.4" stroke-linecap="round" fill="none"/><circle cx="11.5" cy="12.5" r="1" fill="#C9A84C"/><circle cx="18.5" cy="12.5" r="1" fill="#C9A84C"/></svg>',
    'fitness-training' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="11" y="8" width="8" height="14" rx="1.5" stroke="#C9A84C" stroke-width="1.5"/><rect x="7" y="10.5" width="4" height="9" rx="1.5" stroke="#C9A84C" stroke-width="1.4"/><rect x="19" y="10.5" width="4" height="9" rx="1.5" stroke="#C9A84C" stroke-width="1.4"/><path d="M3 15 L7 15" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round"/><path d="M23 15 L27 15" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round"/></svg>',
    'home-cleaning' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 26 L5 13 L15 5 L25 13 L25 26" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/><rect x="11" y="18" width="8" height="8" rx="1.2" stroke="#C9A84C" stroke-width="1.4"/><path d="M10 13 L15 9.5 L20 13" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>',
    'pet-grooming' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="12" cy="17" rx="6" ry="7.5" stroke="#C9A84C" stroke-width="1.5"/><path d="M18 14 C20 14 25 12 25 8.5 C25 5.5 21.5 6 20.5 9" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round" fill="none"/><path d="M18 16 C20.5 17 24.5 16 24.5 13" stroke="#C9A84C" stroke-width="1.4" stroke-linecap="round" fill="none"/><circle cx="10" cy="14.5" r="1" fill="#C9A84C"/><circle cx="13.5" cy="13" r="1" fill="#C9A84C"/></svg>',
    'dental' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 6 C7 6 5 8.5 5 11 C5 14 7 15 8 19 C9 22 9.5 25 11.5 25 C13 25 13.5 22 15 22 C16.5 22 17 25 18.5 25 C20.5 25 21 22 22 19 C23 15 25 14 25 11 C25 8.5 23 6 20 6 C18 6 17 7.5 15 7.5 C13 7.5 12 6 10 6 Z" stroke="#C9A84C" stroke-width="1.5" stroke-linejoin="round"/><path d="M10.5 6.5 C10.5 10 12 12 15 12 C18 12 19.5 10 19.5 6.5" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" fill="none"/></svg>',
    'makeup' => '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 22 L15 12" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/><path d="M10 12 C10 8.7 12 7 15 7 C18 7 20 8.7 20 12 L10 12 Z" stroke="#C9A84C" stroke-width="1.5" stroke-linejoin="round"/><rect x="8" y="19" width="14" height="6" rx="3" stroke="#C9A84C" stroke-width="1.5"/><path d="M11 22 L19 22" stroke="#C9A84C" stroke-width="1" stroke-linecap="round"/></svg>',
];
$dashIconSvgJson = json_encode($dashIconSvgMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

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
        <span id="pv-greeting"><?= $greeting ?></span>
      </p>
      <h1 class="pv-hero-name" style="margin-bottom:.6rem;"><?= $firstNameOnly ?></h1>
      <div class="pv-hero-location" style="margin-top:.6rem;">
        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
        <span>Bacolod City, Negros Occidental</span>
      </div>
      <div class="pv-hero-meta">
        <span class="pv-status-badge">
          <span class="pv-status-dot" aria-hidden="true"></span>
          Active Member
        </span>

      </div>
    </div>
    <script>
      (function() {
        var h = new Date().getHours();
        var g = h < 12 ? 'Good morning' : (h < 18 ? 'Good afternoon' : 'Good evening');
        var el = document.getElementById('pv-greeting');
        if (el) el.textContent = g;
      })();
    </script>

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
          <?php
            // Trust badge logic
            $isVerified   = !empty($p['is_verified']);
            $isTopRated   = $avgRating >= 4.5;
            $reviewCount  = (int)$p['review_count'];
            $bookingCount = (int)$p['booking_count'];
            // "Available Today" — check if provider has any active services (booking_count proxy)
            $availableToday = $bookingCount > 0 || $reviewCount > 0;
          ?>
          <div class="pv-provider-card" data-provider-id="<?= (int)$p['id'] ?>">

            <!-- Photo / Cover -->
            <div class="pv-pc-photo">
              <?php if (!empty($p['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($p['profile_photo']) ?>" alt="<?= htmlspecialchars($p['business_name']) ?>">
              <?php else: ?>
                <div class="pv-pc-placeholder"><?= $catEmoji ?></div>
              <?php endif; ?>
              <!-- Gradient overlay always present -->
              <div class="pv-pc-overlay" aria-hidden="true"></div>

              <!-- Top-left badges -->
              <div class="pv-pc-badges">
                <?php if ($isTopRated): ?>
                  <span class="pv-pc-badge pv-pc-badge--top">⭐ Top Rated</span>
                <?php endif; ?>
                <?php if ($isVerified): ?>
                  <span class="pv-pc-badge pv-pc-badge--verified"><i class="fa-solid fa-shield-check"></i> Verified</span>
                <?php endif; ?>
              </div>

              <!-- Available Today — bottom-right of photo -->
              <?php if ($availableToday): ?>
              <div class="pv-pc-avail">
                <span class="pv-pc-avail-dot" aria-hidden="true"></span>
                Available Today
              </div>
              <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="pv-pc-body">
              <!-- Category -->
              <?php if ($firstCategory): ?>
              <div class="pv-pc-category"><?= htmlspecialchars($firstCategory) ?></div>
              <?php endif; ?>

              <!-- Name -->
              <div class="pv-pc-name"><?= htmlspecialchars($p['business_name']) ?></div>

              <!-- Rating row -->
              <div class="pv-pc-rating-row" aria-label="Rated <?= $avgRating ?> out of 5">
                <span class="pv-pc-stars"><?= starRating($avgRating) ?></span>
                <span class="pv-pc-rating-val"><?= $avgRating > 0 ? number_format($avgRating, 1) : 'New' ?></span>
                <?php if ($reviewCount > 0): ?>
                  <span class="pv-pc-rating-count"><?= $reviewCount ?> review<?= $reviewCount > 1 ? 's' : '' ?></span>
                <?php endif; ?>
              </div>

              <!-- Location -->
              <div class="pv-pc-loc">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <?= !empty($p['barangay']) ? htmlspecialchars($p['barangay']) . ', Bacolod City' : 'Bacolod City' ?>
              </div>
            </div>

            <!-- Actions -->
            <div class="pv-pc-actions">
              <a href="<?= BASE_URL ?>provider/<?= (int)$p['id'] ?>" class="pv-pc-btn pv-pc-btn--ghost">
                <i class="fa-regular fa-user" aria-hidden="true"></i> View Profile
              </a>
              <a href="<?= BASE_URL ?>book/<?= (int)$p['id'] ?>" class="pv-pc-btn pv-pc-btn--primary">
                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Book Appointment
              </a>
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
                  <th>Business</th>
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
                  <td><div class="pv-rb-name"><?= htmlspecialchars($b['business_name']) ?></div></td>
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

      <!-- Booking Analytics -->
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>Booking Analytics</h2>
        </div>
        <div class="pv-analytics-body">
          <div class="pv-analytics-chart-wrap">
            <canvas id="bookingDonut" width="160" height="160"></canvas>
            <div class="pv-analytics-center">
              <div class="pv-analytics-total"><?= $totalBookings ?></div>
              <div class="pv-analytics-sublabel">Total Bookings</div>
            </div>
          </div>
          <div class="pv-analytics-legend">
            <div class="pv-analytics-leg-item">
              <span class="pv-analytics-dot" style="background:#4CAF50;"></span>
              <span class="pv-analytics-leg-label">Completed</span>
              <span class="pv-analytics-leg-val"><?= $chartCompleted ?></span>
            </div>
            <div class="pv-analytics-leg-item">
              <span class="pv-analytics-dot" style="background:#C9A84C;"></span>
              <span class="pv-analytics-leg-label">Confirmed</span>
              <span class="pv-analytics-leg-val"><?= $chartConfirmed ?></span>
            </div>
            <div class="pv-analytics-leg-item">
              <span class="pv-analytics-dot" style="background:#3B82F6;"></span>
              <span class="pv-analytics-leg-label">Pending</span>
              <span class="pv-analytics-leg-val"><?= $chartPending ?></span>
            </div>
            <div class="pv-analytics-leg-item">
              <span class="pv-analytics-dot" style="background:#EF4444;"></span>
              <span class="pv-analytics-leg-label">Cancelled</span>
              <span class="pv-analytics-leg-val"><?= $chartCancelled ?></span>
            </div>
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
   <a href="<?= BASE_URL ?>bookings/<?= (int)$pendingReview['id'] ?>/review">Leave a Review</a>
  </div>
  <?php endif; ?>

</main>

<!-- ══ SCRIPTS ══ -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function() {
  var ctx = document.getElementById('bookingDonut');
  if (!ctx) return;
  var completed  = <?= (int)$chartCompleted ?>;
  var confirmed  = <?= (int)$chartConfirmed ?>;
  var pending    = <?= (int)$chartPending ?>;
  var cancelled  = <?= (int)$chartCancelled ?>;
  var total = completed + confirmed + pending + cancelled;
  if (total === 0) { completed = 1; } // show empty state ring
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      datasets: [{
        data: [completed, confirmed, pending, cancelled],
        backgroundColor: ['#4CAF50','#C9A84C','#3B82F6','#EF4444'],
        borderWidth: 3,
        borderColor: 'transparent',
        hoverOffset: 6
      }]
    },
    options: {
      cutout: '75%',
      plugins: { legend: { display: false }, tooltip: { enabled: total > 0 } },
      animation: { animateRotate: true, duration: 900 }
    }
  });
})();
</script>

<!-- Leaflet JS — load before map init script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<script>
/* ══════════════════════════════════════
   DASHBOARD MAP — Matches Browse page style exactly
   Diamond markers (white bg + gold border) with category SVG icons
   Service-mode colour badge in popup only
   OSM tiles + dark-mode filter identical to Browse
══════════════════════════════════════ */
(function () {
  var mapEl = document.getElementById('providerMap');
  if (!mapEl) return;

  var BACOLOD    = [10.6840, 122.9560];
  var providers  = <?= $mapProvidersJson ?>;
  var iconSvgMap = <?= $dashIconSvgJson ?>;

  if (!providers.length) return;

  /* Service mode meta — drives both marker colour and popup badge */
  var MODE_META = {
    in_shop:      { color: '#C9A84C', stroke: '#A8892E', label: 'In-Shop' },
    home_service: { color: '#3B82F6', stroke: '#2563EB', label: 'Home Service' },
    flexible:     { color: '#F97316', stroke: '#EA6000', label: 'Flexible' }
  };

  /* ── Init map ── */
  var map = L.map('providerMap', {
    center:           BACOLOD,
    zoom:             13,
    zoomControl:      true,
    scrollWheelZoom:  false,
    dragging:         true,
    doubleClickZoom:  false,
    touchZoom:        false,
    keyboard:         false,
    attributionControl: true
  });

  /* ── OSM tiles — identical to Browse page ── */
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18
  }).addTo(map);

  /* ── Dark-mode filter — handled by applyTheme() in theme toggle script ── */

  /* ── Compact SVG pin marker — service-mode colour-coded ── */
  function makeCatIcon(slug, isActive, serviceMode) {
    var mode   = serviceMode || 'in_shop';
    var meta   = MODE_META[mode] || MODE_META['in_shop'];
    var fill   = meta.color;
    var stroke = meta.stroke;

    /* Pin sizes: normal = 22x30, active = 28x38 */
    var W      = isActive ? 28 : 22;
    var H      = isActive ? 38 : 30;
    var headR  = isActive ? 11 : 9;   /* outer radius of pin head circle */
    var cx     = W / 2;
    var headCy = headR + 1;

    /* Tail: two bezier curves meeting at the tip */
    var tailPath =
      'M ' + (cx - headR * 0.52) + ' ' + (headCy + headR * 0.72) +
      ' Q ' + cx + ' ' + (H - 1) + ' ' +
      (cx + headR * 0.52) + ' ' + (headCy + headR * 0.72) + ' Z';

    var shadow = isActive
      ? 'drop-shadow(0 3px 7px rgba(0,0,0,.42))'
      : 'drop-shadow(0 2px 4px rgba(0,0,0,.30))';

    var html =
      '<div class="qb-map-marker' + (isActive ? ' is-active' : '') + '" style="' +
        'width:' + W + 'px;height:' + H + 'px;' +
        'filter:' + shadow + ';' +
        'transition:all .18s cubic-bezier(.22,1,.36,1);' +
        'cursor:pointer;' +
      '">' +
        '<svg width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" ' +
            'fill="none" xmlns="http://www.w3.org/2000/svg">' +
          '<path d="' + tailPath + '" fill="' + fill + '"/>' +
          '<circle cx="' + cx + '" cy="' + headCy + '" r="' + headR + '" ' +
              'fill="' + fill + '"/>' +
          '<circle cx="' + cx + '" cy="' + headCy + '" r="' + Math.round(headR * 0.36) + '" ' +
              'fill="rgba(255,255,255,0.55)"/>' +
        '</svg>' +
      '</div>';

    return L.divIcon({
      className:   '',
      html:        html,
      iconSize:    [W, H],
      iconAnchor:  [W / 2, H],
      popupAnchor: [0, -(H + 4)],
      shadowUrl:   '',
      shadowSize:  [0, 0]
    });
  }

  /* ── Legend control (service mode key) ── */
  var LegendCtrl = L.Control.extend({
    onAdd: function () {
      var el = L.DomUtil.create('div', 'qb-map-legend');
      el.innerHTML =
        '<div class="qb-map-legend-title">Service Type</div>' +
        Object.keys(MODE_META).map(function (k) {
          var m = MODE_META[k];
          /* Mini pin SVG: 10x14 teardrop */
          var pinSvg =
            '<svg width="10" height="14" viewBox="0 0 10 14" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">' +
              '<path d="M 2.4 8.2 Q 5 13 7.6 8.2 Z" fill="' + m.color + '"/>' +
              '<circle cx="5" cy="4.5" r="3.8" fill="' + m.color + '"/>' +
              '<circle cx="5" cy="4.5" r="1.4" fill="rgba(255,255,255,0.55)"/>' +
            '</svg>';
          return '<div class="qb-map-legend-row">' +
            pinSvg +
            '<span class="qb-map-legend-label">' + m.label + '</span>' +
            '</div>';
        }).join('');
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new LegendCtrl({ position: 'bottomleft' }));

  /* ── "Open Full Map" pill button ── */
  var OpenMapCtrl = L.Control.extend({
    onAdd: function () {
      var el = L.DomUtil.create('a', 'qb-open-map-btn');
      el.href = '<?= BASE_URL ?>browse?view=map';
      el.innerHTML =
        '<svg width="13" height="13" viewBox="0 0 13 13" fill="none" ' +
            'stroke="#fff8e8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
          '<path d="M6.5 1C4.57 1 3 2.57 3 4.5c0 2.63 3.5 7.5 3.5 7.5S10 7.13 10 4.5C10 2.57 8.43 1 6.5 1z"/>' +
          '<circle cx="6.5" cy="4.5" r="1.2"/>' +
        '</svg>' +
        'Open Full Map';
      L.DomEvent.disableClickPropagation(el);
      return el;
    }
  });
  map.addControl(new OpenMapCtrl({ position: 'bottomright' }));

  var allMarkers = {};

  /* ── Place markers ── */
  var bounds = [];

  providers.forEach(function (p, idx) {
    var lat = p.lat, lng = p.lng;

    if (!lat || !lng) {
      var angle  = (idx / Math.max(providers.length, 1)) * 2 * Math.PI;
      var radius = 0.012 + (idx % 3) * 0.006;
      lat = BACOLOD[0] + radius * Math.cos(angle);
      lng = BACOLOD[1] + radius * Math.sin(angle);
    }

    bounds.push([lat, lng]);

    var marker = L.marker([lat, lng], {
      icon:  makeCatIcon(p.categorySlug, false, p.serviceMode),
      title: p.name
    }).addTo(map);

    /* ── Popup on click ── */
    var mode    = p.serviceMode || 'in_shop';
    var meta    = MODE_META[mode] || MODE_META['in_shop'];
    /* Photo fallback: initials avatar */
    var initials = p.name.split(' ').slice(0,2).map(function(w){return w[0]||'';}).join('').toUpperCase();
    var photoHtml = p.photo
      ? '<img src="' + p.photo + '" alt="' + p.name + '" class="pv-popup-photo">'
      : '<div class="pv-popup-photo pv-popup-photo--initials">' + initials + '</div>';

    var popupHtml =
      '<div class="pv-map-popup">' +
        /* Left: large flush photo */
        photoHtml +
        /* Right: all text stacked */
        '<div class="pv-popup-body">' +
          '<div class="pv-popup-name">' + p.name + '</div>' +
          (p.category ? '<span class="pv-popup-cat-pill">' + p.category + '</span>' : '') +
          '<div class="pv-popup-service-row">' +
            '<span class="pv-popup-service-dot" style="background:' + meta.color + '"></span>' +
            '<span class="pv-popup-service-label" style="color:' + meta.color + '">' + meta.label + '</span>' +
          '</div>' +
          '<div class="pv-popup-address">' +
            '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>' +
            '<span>' + p.address + '</span>' +
          '</div>' +
        '</div>' +
      '</div>';

    marker.bindPopup(popupHtml, { maxWidth: 270, className: 'qb-dash-popup' });

    /* Active state on click + highlight card */
    marker.on('click', function () {
      /* Reset all markers to inactive */
      Object.values(allMarkers).forEach(function (m) {
        var pid = Object.keys(allMarkers).find(function (k) { return allMarkers[k] === m; });
        var prov = providers.find(function (x) { return x.id == pid; });
        if (prov) m.setIcon(makeCatIcon(prov.categorySlug, false, prov.serviceMode));
      });
      /* Set this marker active */
      marker.setIcon(makeCatIcon(p.categorySlug, true, p.serviceMode));

      /* Highlight provider card */
      document.querySelectorAll('.pv-provider-card').forEach(function (c) {
        c.classList.remove('is-highlighted');
      });
      var card = document.querySelector('[data-provider-id="' + p.id + '"]');
      if (card) {
        card.classList.add('is-highlighted');
        setTimeout(function () {
          card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 180);
      }
    });

    allMarkers[p.id] = marker;
  });

  /* ── Fit to markers ── */
  if (bounds.length === 1) {
    map.setView(bounds[0], 15);
  } else if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [52, 52], maxZoom: 14 });
  }
})();
</script>



<style>
/* ================================================================
   DASHBOARD MAP — Styles
================================================================ */

/* Ensure map container is fully visible — no overlay */
#providerMap {
  position: relative !important;
  z-index: 1 !important;
  cursor: grab !important;
  background: #f5f0e8 !important;   /* matches Browse light */
}
[data-theme="dark"] #providerMap { background: #0D1117 !important; }
#providerMap .leaflet-marker-icon { cursor: pointer !important; }

/* Leaflet container must not be covered */
.pv-map-wrap { isolation: isolate; }
.leaflet-container { background: #f5f0e8 !important; }
[data-theme="dark"] .leaflet-container { background: #0D1117 !important; }

/* ── SVG pin markers — compact, no rotation ── */
.qb-map-marker { transition: all .18s cubic-bezier(.22,1,.36,1); }
.qb-map-marker:hover { transform: scale(1.12) translateY(-2px) !important; }
.qb-map-marker.is-active { transform: scale(1.18) translateY(-3px) !important; }
#providerMap .leaflet-marker-shadow { display: none !important; }

/* ── "Open Full Map" pill button ── */
.qb-open-map-btn {
  display: inline-flex !important;
  align-items: center !important;
  gap: .4rem !important;
  font-family: 'DM Mono', monospace !important;
  font-size: .62rem !important;
  font-weight: 600 !important;
  letter-spacing: .06em !important;
  text-transform: uppercase !important;
  background: linear-gradient(135deg, #A88A38, #C9A84C) !important;
  color: #fff8e8 !important;
  padding: .42rem .85rem !important;
  border-radius: 99px !important;
  text-decoration: none !important;
  box-shadow: 0 2px 10px rgba(201,168,76,.45) !important;
  border: none !important;
  white-space: nowrap !important;
  transition: filter .15s !important;
  margin-bottom: .5rem !important;
  margin-right: .5rem !important;
}
.qb-open-map-btn:hover { filter: brightness(1.1) !important; }

/* ── Pin marker legend ── */
.qb-map-legend {
  background: rgba(255,255,255,.94) !important;
  border: 1.5px solid rgba(201,168,76,.35) !important;
  border-radius: 10px !important;
  padding: .55rem .75rem !important;
  box-shadow: 0 3px 14px rgba(0,0,0,.12) !important;
  font-family: 'DM Mono', monospace !important;
  margin-bottom: .5rem !important;
  margin-left: .5rem !important;
  pointer-events: none !important;
}
[data-theme="dark"] .qb-map-legend {
  background: rgba(18,24,38,.94) !important;
  border-color: rgba(201,168,76,.25) !important;
}
.qb-map-legend-title {
  font-size: .52rem !important;
  font-weight: 700 !important;
  letter-spacing: .1em !important;
  text-transform: uppercase !important;
  color: #888 !important;
  margin-bottom: .38rem !important;
}
[data-theme="dark"] .qb-map-legend-title { color: rgba(237,227,204,.45) !important; }
.qb-map-legend-row {
  display: flex !important;
  align-items: center !important;
  gap: .4rem !important;
  margin-bottom: .22rem !important;
}
.qb-map-legend-row:last-child { margin-bottom: 0 !important; }
.qb-map-legend-dot { display: none !important; } /* replaced by inline SVG pins */
.qb-map-legend-label {
  font-size: .6rem !important;
  color: #444 !important;
  line-height: 1.2 !important;
}
[data-theme="dark"] .qb-map-legend-label { color: rgba(237,227,204,.75) !important; }

/* ── Popup styling ── */
.leaflet-popup.qb-dash-popup .leaflet-popup-content-wrapper {
  border-radius: 12px !important;
  border: 1.5px solid rgba(201,168,76,.32) !important;
  box-shadow: 0 8px 28px rgba(0,0,0,.18) !important;
  padding: 0 !important;
  overflow: hidden !important;
  background: #fff !important;
}
[data-theme="dark"] .leaflet-popup.qb-dash-popup .leaflet-popup-content-wrapper {
  background: rgba(18,24,38,.97) !important;
  border-color: rgba(201,168,76,.28) !important;
}
.leaflet-popup.qb-dash-popup .leaflet-popup-content { margin: 0 !important; }
.leaflet-popup.qb-dash-popup .leaflet-popup-tip-container { display: none !important; }
</style>

<script>
/* ── Theme Toggle ── */
(function () {
  var btn  = document.getElementById('themeToggle');
  var moon = document.querySelector('.icon-moon');
  var sun  = document.querySelector('.icon-sun');

  function applyMapTheme(theme) {
    var tilePane = document.querySelector('#providerMap .leaflet-tile-pane');
    if (!tilePane) return;
    tilePane.style.filter = theme === 'dark'
      ? 'brightness(0.7) invert(1) contrast(3) hue-rotate(200deg) saturate(0.3) brightness(0.7)'
      : 'none';
  }

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
    applyMapTheme(theme);
    // retry after map tiles render
    setTimeout(function() { applyMapTheme(theme); }, 500);
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