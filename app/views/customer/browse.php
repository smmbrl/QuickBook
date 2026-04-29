<?php


require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();


$userName      = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$userEmail     = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId        = (int)($_SESSION['user_id'] ?? 0);
$initials      = strtoupper(substr($userName, 0, 2));


$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$loyaltyTier   = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};


$stUpcoming = $db->prepare("
    SELECT COUNT(*) FROM tbl_bookings
    WHERE customer_id = ?
      AND status IN ('pending','confirmed')
      AND booking_date >= CURDATE()
      AND deleted_at IS NULL
");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();


$cats = $db->query("SELECT * FROM tbl_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

$selectedCat    = isset($_GET['category'])     ? (int)$_GET['category']          : 0;
$search         = trim($_GET['search']         ?? '');
$locationFilter = trim($_GET['service_type']   ?? '');
$sortBy         = $_GET['sort']                ?? 'rating';


$where  = ["s.is_active = 1", "pp.is_approved = 1", "u.is_active = 1"];
$params = [];

if ($selectedCat) {
    $where[]  = "pp.category_id = ?";
    $params[] = $selectedCat;
}
if ($search !== '') {
    $where[]  = "(s.name LIKE ? OR pp.business_name LIKE ? OR c.name LIKE ? OR s.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($locationFilter !== '') {
    $locationMap = [
        'on-site'  => 'On-site',
        'remote'   => 'Remote',
        'in-shop'  => 'In-shop',
        'flexible' => 'Flexible',
    ];
    $where[]  = "s.location_type = ?";
    $params[] = $locationMap[$locationFilter] ?? ucfirst($locationFilter);
}

$orderMap = [
    'rating'   => 'pp.avg_rating DESC',
    'reviews'  => 'pp.total_reviews DESC',
    'price_lo' => 's.price ASC',
    'price_hi' => 's.price DESC',
    'name'     => 's.name ASC',
];
$order = $orderMap[$sortBy] ?? $orderMap['rating'];

$sql = "
    SELECT s.*,
           s.location_type       AS service_type,
           pp.id                 AS profile_id,
           pp.business_name,
           pp.avg_rating,
           pp.total_reviews,
           pp.city,
           pp.barangay,
           c.name                AS category_name,
           c.slug                AS category_slug,
           u.first_name, u.last_name
    FROM tbl_services s
    JOIN tbl_provider_profiles pp ON s.provider_id = pp.id
    JOIN tbl_users u              ON pp.user_id = u.id
    LEFT JOIN tbl_categories c    ON pp.category_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $order
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();


$totalServices   = count($services);
$onSiteServices  = count(array_filter($services, fn($s) => strtolower($s['service_type']) === 'on-site'));
$avgRating       = $totalServices > 0
    ? round(array_sum(array_column($services, 'avg_rating')) / $totalServices, 1)
    : 0;
$totalCategories = count($cats);


$catCount = count($cats) + 1;


$catIconMap = [
    'barbershop' => '
        <path d="M10 5 L10 19" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M20 5 L20 19" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M7.5 12 L22.5 12" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="10" cy="22.5" r="3" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/>
        <circle cx="20" cy="22.5" r="3" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/>
    ',
    'hair-salon' => '
        <path d="M9 26 C9 18 13 15 15 8" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M21 26 C21 18 17 15 15 8" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M11 10 C11 7.5 12.7 6 15 6 C17.3 6 19 7.5 19 10" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round" fill="none"/>
        <path d="M10 19 L20 19" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round"/>
    ',
    'nail-care' => '
        <path d="M8 17 L8 12 C8 8.7 11.1 6 15 6 C18.9 6 22 8.7 22 12 L22 17" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round" fill="none"/>
        <rect x="6" y="17" width="18" height="7" rx="3" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/>
        <circle cx="12" cy="20.5" r="1.2" fill="#C9A84C" opacity=".6"/>
        <circle cx="18" cy="20.5" r="1.2" fill="#C9A84C" opacity=".6"/>
    ',
    'massage-therapy' => '
        <circle cx="15" cy="8" r="3.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/>
        <path d="M6 24 C6 18.5 9.5 15 15 15 C20.5 15 24 18.5 24 24" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round" fill="none"/>
        <path d="M8 16.5 C5.5 17.5 4 20 4 22.5" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" opacity=".5" fill="none"/>
        <path d="M22 16.5 C24.5 17.5 26 20 26 22.5" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" opacity=".5" fill="none"/>
    ',
    'skincare-facial' => '
        <circle cx="15" cy="15" r="9" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/>
        <path d="M11.5 17 C12.5 18.5 13.8 19.2 15 19.2 C16.2 19.2 17.5 18.5 18.5 17" stroke="#C9A84C" stroke-width="1.4" stroke-linecap="round" fill="none"/>
        <circle cx="11.5" cy="12.5" r="1" fill="#C9A84C"/>
        <circle cx="18.5" cy="12.5" r="1" fill="#C9A84C"/>
    ',
    'fitness-training' => '
        <rect x="11" y="8" width="8" height="14" rx="1.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/>
        <rect x="7" y="10.5" width="4" height="9" rx="1.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.4"/>
        <rect x="19" y="10.5" width="4" height="9" rx="1.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.4"/>
        <path d="M3 15 L7 15" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round"/>
        <path d="M23 15 L27 15" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round"/>
    ',
    'home-cleaning' => '
        <path d="M5 26 L5 13 L15 5 L25 13 L25 26" stroke="#C9A84C" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <rect x="11" y="18" width="8" height="8" rx="1.2" fill="#1A1000" stroke="#C9A84C" stroke-width="1.4"/>
        <path d="M10 13 L15 9.5 L20 13" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" opacity=".5" fill="none"/>
    ',
    'pet-grooming' => '
        <ellipse cx="12" cy="17" rx="6" ry="7.5" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/>
        <path d="M18 14 C20 14 25 12 25 8.5 C25 5.5 21.5 6 20.5 9" stroke="#C9A84C" stroke-width="1.5" stroke-linecap="round" fill="none"/>
        <path d="M18 16 C20.5 17 24.5 16 24.5 13" stroke="#C9A84C" stroke-width="1.4" stroke-linecap="round" fill="none"/>
        <circle cx="10" cy="14.5" r="1" fill="#C9A84C" opacity=".6"/>
        <circle cx="13.5" cy="13" r="1" fill="#C9A84C" opacity=".6"/>
    ',
    'event-styling' => '
        <polygon points="15,4 17.5,11 25,11 19,15.5 21.5,22.5 15,18 8.5,22.5 11,15.5 5,11 12.5,11" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5" stroke-linejoin="round"/>
        <circle cx="15" cy="14" r="2.5" fill="#C9A84C" opacity=".3"/>
    ',
    'makeup' => '
        <path d="M15 22 L15 12" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M10 12 C10 8.7 12 7 15 7 C18 7 20 8.7 20 12 L10 12 Z" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5" stroke-linejoin="round"/>
        <rect x="8" y="19" width="14" height="6" rx="3" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5"/>
        <path d="M11 22 L19 22" stroke="#C9A84C" stroke-width="1" stroke-linecap="round" opacity=".4"/>
    ',
];

$allIcon = '
    <rect x="4" y="4" width="9" height="9" rx="2" fill="#C9A84C" opacity=".9"/>
    <rect x="17" y="4" width="9" height="9" rx="2" fill="#C9A84C" opacity=".5"/>
    <rect x="4" y="17" width="9" height="9" rx="2" fill="#C9A84C" opacity=".5"/>
    <rect x="17" y="17" width="9" height="9" rx="2" fill="#C9A84C" opacity=".9"/>
';

function catSvg(string $slug, array $map, string $allIcon): string {
    $paths = $map[$slug] ?? $allIcon;
    return '<svg viewBox="0 0 30 30" fill="none" aria-hidden="true">' . $paths . '</svg>';
}

function renderStars(float $rating): string {
    $filled = floor($rating);
    $half   = ($rating - $filled) >= .5 ? 1 : 0;
    $empty  = 5 - $filled - $half;
    return str_repeat('★', $filled) . ($half ? '½' : '') . str_repeat('☆', $empty);
}

$serviceTypeLabels = [
    'on-site'  => ' On-site',
    'remote'   => ' Remote',
    'in-shop'  => ' In-shop',
    'flexible' => ' Flexible',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Browse Services</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_browse.css">
  <style>

    .pv-cat-scroll { --cat-count: <?= $catCount ?>; }
  </style>
</head>
<body>

<div class="grain" aria-hidden="true"></div>
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>


<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard"  class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings"   class="pv-nav-link">
        Bookings
        <?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse"     class="pv-nav-link is-active">Browse Services</a>
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

<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>

  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Browse Services
      </p>
      <h1 class="pv-hero-name">Find the perfect <em>service</em> for you</h1>
      <p class="pv-hero-sub">Compare services, prices, and providers — then book in seconds.</p>
    </div>

    <form method="GET" action="<?= BASE_URL ?>browse" class="pv-hero-search" role="search">
      <?php if ($selectedCat): ?>
        <input type="hidden" name="category" value="<?= $selectedCat ?>">
      <?php endif; ?>
      <?php if ($locationFilter): ?>
        <input type="hidden" name="service_type" value="<?= htmlspecialchars($locationFilter) ?>">
      <?php endif; ?>
      <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
      <div class="pv-search-wrap">
        <span class="pv-search-icon" aria-hidden="true">🔍</span>
        <input type="text" name="search"
               placeholder="Search services or providers…"
               value="<?= htmlspecialchars($search) ?>"
               aria-label="Search services or providers"
               class="pv-search-input">
        <?php if ($search): ?>
          <a href="<?= BASE_URL ?>browse<?= $selectedCat ? '?category='.$selectedCat : '' ?>"
             class="pv-search-clear" aria-label="Clear search">✕</a>
        <?php endif; ?>
        <button type="submit" class="pv-search-btn">Search</button>
      </div>
    </form>
  </div>


  <div class="pv-hero-stats" role="region" aria-label="Directory statistics">
    <div class="pv-hero-stats-inner">
      <div class="pv-hs-item hs-gold">
        <div class="pv-hs-text">
          <span class="pv-hs-val"><?= $totalServices ?></span>
          <span class="pv-hs-label">Services Available</span>
        </div>
      </div>
      <div class="pv-hs-item hs-white">
        <div class="pv-hs-text">
          <span class="pv-hs-val"><?= $totalCategories ?></span>
          <span class="pv-hs-label">Categories</span>
        </div>
      </div>
      <div class="pv-hs-item hs-green">
        <div class="pv-hs-text">
          <span class="pv-hs-val"><?= $onSiteServices ?></span>
          <span class="pv-hs-label">On-site Available</span>
        </div>
      </div>
      <div class="pv-hs-item hs-yellow">
        <div class="pv-hs-text">
          <span class="pv-hs-val"><?= $avgRating > 0 ? number_format($avgRating, 1) : '—' ?></span>
          <span class="pv-hs-label">Avg Rating</span>
        </div>
      </div>
    </div>
  </div>
</header>


<main class="pv-page" role="main">


  <section class="pv-cat-section" role="region" aria-label="Filter by category">


    <div class="pv-cat-header">
      <span class="pv-cat-title">Services</span>
    </div>


    <div class="pv-cat-scroll" role="list">


      <a href="<?= BASE_URL ?>browse<?= $search ? '?search='.urlencode($search) : '' ?>"
         class="pv-cat-item <?= !$selectedCat ? 'active' : '' ?>"
         role="listitem" aria-label="All services">
        <div class="pv-cat-circle">
          <svg viewBox="0 0 30 30" fill="none" aria-hidden="true"><?= $allIcon ?></svg>
        </div>
        <span class="pv-cat-name">All</span>
      </a>

      <?php foreach ($cats as $cat):
        $isActive = $selectedCat == $cat['id'];
        $href = BASE_URL . 'browse?category=' . $cat['id']
              . ($search          ? '&search='.urlencode($search)              : '')
              . ($locationFilter  ? '&service_type='.urlencode($locationFilter) : '')
              . ($sortBy !== 'rating' ? '&sort='.urlencode($sortBy)            : '');
      ?>
      <a href="<?= $href ?>"
         class="pv-cat-item <?= $isActive ? 'active' : '' ?>"
         role="listitem"
         aria-label="<?= htmlspecialchars($cat['name']) ?>">
        <div class="pv-cat-circle">
          <?= catSvg($cat['slug'], $catIconMap, $allIcon) ?>
        </div>
        <span class="pv-cat-name"><?= htmlspecialchars($cat['name']) ?></span>
      </a>
      <?php endforeach; ?>

    </div>
  </section>


  <div class="pv-toolbar">
    <div class="pv-result-count">
      <span class="pv-result-num"><?= count($services) ?></span>
      service<?= count($services) !== 1 ? 's' : '' ?> found
      <?php if ($search): ?>
        for "<strong><?= htmlspecialchars($search) ?></strong>"
      <?php endif; ?>
      <?php if ($selectedCat):
        $activeCat = array_filter($cats, fn($c) => $c['id'] == $selectedCat);
        if ($activeCat): ?>
          in <strong><?= htmlspecialchars(reset($activeCat)['name']) ?></strong>
        <?php endif;
      endif; ?>
    </div>

    <div class="pv-toolbar-right">


      <div class="pv-stype-wrap" id="stypeWrap">
        <button class="pv-stype-btn <?= $locationFilter ? 'is-on' : '' ?>"
                id="stypeBtn"
                onclick="toggleStype(event)"
                aria-haspopup="listbox"
                aria-expanded="false">
          <span class="pv-stype-dot"></span>
          <span id="stypeLabel"><?= $locationFilter ? htmlspecialchars($serviceTypeLabels[$locationFilter] ?? $locationFilter) : 'Service Type' ?></span>
          <svg class="pv-stype-chev" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="10" height="6" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
        </button>
        <div class="pv-stype-menu" id="stypeMenu" role="listbox">
          <?php
            $stypeOptions = [
              ''         => ['label' => 'All types',  'desc' => 'No filter applied',         'icon' => '◈'],
              'on-site'  => ['label' => 'On-site',    'desc' => 'Provider comes to you',      'icon' => ''],
              'remote'   => ['label' => 'Remote',     'desc' => 'Online / virtual session',   'icon' => ''],
              'in-shop'  => ['label' => 'In-shop',    'desc' => "Visit provider's location",  'icon' => ''],
              'flexible' => ['label' => 'Flexible',   'desc' => 'Multiple options available', 'icon' => ''],
            ];
            foreach ($stypeOptions as $val => $opt):
              $isChosen = $locationFilter === $val;
              $href = BASE_URL . 'browse?' . http_build_query(array_filter([
                'category'     => $selectedCat ?: '',
                'search'       => $search,
                'sort'         => $sortBy,
                'service_type' => $val,
              ]));
          ?>
          <a href="<?= $href ?>"
             class="pv-stype-opt <?= $isChosen ? 'chosen' : '' ?>"
             role="option"
             aria-selected="<?= $isChosen ? 'true' : 'false' ?>">
            <span class="pv-stype-opt-icon"><?= $opt['icon'] ?></span>
            <span class="pv-stype-opt-text">
              <span class="pv-stype-opt-label"><?= $opt['label'] ?></span>
              <span class="pv-stype-opt-desc"><?= $opt['desc'] ?></span>
            </span>
            <?php if ($isChosen): ?>
              <svg class="pv-stype-check" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 6l3 3 5-5"/></svg>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>


      <form method="GET" action="<?= BASE_URL ?>browse">
        <?php if ($selectedCat):    ?><input type="hidden" name="category"     value="<?= $selectedCat ?>"><?php endif; ?>
        <?php if ($search):         ?><input type="hidden" name="search"       value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
        <?php if ($locationFilter): ?><input type="hidden" name="service_type" value="<?= htmlspecialchars($locationFilter) ?>"><?php endif; ?>
        <div class="pv-sort-wrap">
          <svg class="pv-sort-ico" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M1 3h12M3 7h8M5 11h4"/></svg>
          <select name="sort" class="pv-sort-select" onchange="this.form.submit()" aria-label="Sort services">
            <option value="rating"   <?= $sortBy === 'rating'   ? 'selected' : '' ?>>Top Rated</option>
            <option value="reviews"  <?= $sortBy === 'reviews'  ? 'selected' : '' ?>>Most Reviews</option>
            <option value="price_lo" <?= $sortBy === 'price_lo' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_hi" <?= $sortBy === 'price_hi' ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="name"     <?= $sortBy === 'name'     ? 'selected' : '' ?>>A – Z</option>
          </select>
          <svg class="pv-sort-chev" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="10" height="6" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
        </div>
      </form>

      <?php if ($search || $selectedCat || $locationFilter): ?>
        <a href="<?= BASE_URL ?>browse" class="pv-clear-btn">✕ Clear</a>
      <?php endif; ?>
    </div>
  </div>


  <?php if (empty($services)): ?>
  <div class="pv-empty-state">
    <div class="pv-empty-icon" aria-hidden="true">🔍</div>
    <p>No services found. Try adjusting your filters or search term.</p>
    <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Clear All Filters →</a>
  </div>

  <?php else: ?>
  <div class="pv-service-grid" role="list">
    <?php foreach ($services as $s):
      $slug      = $s['category_slug'] ?? '';
      $rating    = (float)$s['avg_rating'];
      $reviews   = (int)$s['total_reviews'];
      $duration  = !empty($s['duration_minutes']) ? $s['duration_minutes'] . ' min' : null;
      $stype     = $s['service_type'] ?? '';
      $stypeLower = strtolower(str_replace(' ', '-', $stype));
      $stypeBadgeMap = [
        'on-site'  => ['label' => 'On-site',  'class' => 'badge-onsite'],
        'remote'   => ['label' => 'Remote',   'class' => 'badge-remote'],
        'in-shop'  => ['label' => 'In-shop',  'class' => 'badge-inshop'],
        'flexible' => ['label' => 'Flexible', 'class' => 'badge-flexible'],
      ];
    ?>
    <a href="<?= BASE_URL ?>providers/<?= (int)$s['profile_id'] ?>"
       class="pv-service-card"
       role="listitem"
       aria-label="<?= htmlspecialchars($s['name']) ?> by <?= htmlspecialchars($s['business_name']) ?>">

      <div class="pv-svc-accent" aria-hidden="true"></div>


      <div class="pv-svc-head">
        <div class="pv-svc-av" aria-hidden="true">
          <?= catSvg($slug, $catIconMap, $allIcon) ?>
        </div>
        <div class="pv-svc-head-right">
          <div class="pv-svc-category"><?= htmlspecialchars($s['category_name'] ?? 'Service') ?></div>
          <?php if ($stype && isset($stypeBadgeMap[$stypeLower])): ?>
            <span class="pv-svc-stype-badge <?= $stypeBadgeMap[$stypeLower]['class'] ?>">
              <?= $stypeBadgeMap[$stypeLower]['label'] ?>
            </span>
          <?php endif; ?>
        </div>
      </div>


      <div class="pv-svc-body">
        <div class="pv-svc-name"><?= htmlspecialchars($s['name']) ?></div>
        <div class="pv-svc-provider"><?= htmlspecialchars($s['business_name']) ?></div>
        <?php if (!empty($s['description'])): ?>
          <div class="pv-svc-desc"><?= htmlspecialchars(mb_strimwidth($s['description'], 0, 80, '…')) ?></div>
        <?php endif; ?>
      </div>


      <div class="pv-svc-meta">
        <?php if ($duration): ?>
          <span class="pv-svc-dur">⏱ <?= $duration ?></span>
        <?php else: ?>
          <span></span>
        <?php endif; ?>
        <div class="pv-svc-rating">
          <span class="pv-svc-stars" aria-label="Rating <?= number_format($rating,1) ?> out of 5">
            <?= renderStars($rating) ?>
          </span>
          <span class="pv-svc-rating-val"><?= $reviews > 0 ? number_format($rating,1) : '—' ?></span>
          <span class="pv-svc-reviews">(<?= $reviews ?>)</span>
        </div>
      </div>


      <div class="pv-svc-footer">
        <div class="pv-svc-price">
          <span class="pv-svc-price-val">₱<?= number_format((float)$s['price'], 0) ?></span>
          <?php if ($duration): ?>
            <span class="pv-svc-price-per">/ <?= $duration ?></span>
          <?php endif; ?>
        </div>
        <span class="pv-svc-cta">Book Now →</span>
      </div>

    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</main>

<script>
function toggleStype(e) {
  e.stopPropagation();
  const menu = document.getElementById('stypeMenu');
  const btn  = document.getElementById('stypeBtn');
  const open = menu.classList.toggle('is-open');
  btn.setAttribute('aria-expanded', open);
}
document.addEventListener('click', () => {
  document.getElementById('stypeMenu')?.classList.remove('is-open');
  document.getElementById('stypeBtn')?.setAttribute('aria-expanded', 'false');
});
</script>

</body>
</html>