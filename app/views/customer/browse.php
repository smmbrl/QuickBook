<?php


require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();


$userName      = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$userEmail     = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId        = (int)($_SESSION['user_id'] ?? 0);
$initials      = strtoupper(substr($userName, 0, 2));
$stAv = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAv->execute([$userId]);
$avatarUrl = ($av = $stAv->fetchColumn()) ? ($av) : null;


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


// ── Build WHERE for providers ──────────────────────────────────────────────────
$where  = ["pp.is_approved = 1", "u.is_active = 1"];
$params = [];

if ($selectedCat) {
    $where[]  = "pp.category_id = ?";
    $params[] = $selectedCat;
}
if ($search !== '') {
    $where[]  = "(pp.business_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($locationFilter !== '') {
    if ($locationFilter === 'on-site') {
        $where[]  = "pp.offers_home_service = 1";
    } elseif ($locationFilter === 'in-shop') {
        $where[]  = "FIND_IN_SET('In-shop', pp.location_types_offered)";
    } elseif ($locationFilter === 'flexible') {
        $where[]  = "FIND_IN_SET('Flexible', pp.location_types_offered)";
    } elseif ($locationFilter === 'remote') {
        $where[]  = "FIND_IN_SET('Remote', pp.location_types_offered)";
    }
}

$orderMap = [
    'rating'   => 'pp.avg_rating DESC',
    'reviews'  => 'pp.total_reviews DESC',
    'price_lo' => 'min_price ASC',
    'price_hi' => 'min_price DESC',
    'name'     => 'pp.business_name ASC',
];
$order = $orderMap[$sortBy] ?? $orderMap['rating'];

$sql = "
    SELECT pp.*,
           pp.id                 AS profile_id,
           pp.business_name,
           COALESCE(pp.avg_rating, 0)     AS avg_rating,
           COALESCE(pp.total_reviews, 0)  AS total_reviews,
           pp.city,
           pp.barangay,
           pp.offers_home_service,
           pp.location_types_offered,
           pp.profile_photo,
           c.name                AS category_name,
           c.slug                AS category_slug,
           u.first_name, u.last_name, u.avatar_url,
           (SELECT MIN(s.price) FROM tbl_services s
            WHERE s.provider_id = pp.id AND s.is_active = 1) AS min_price,
           (SELECT COUNT(*) FROM tbl_services s
            WHERE s.provider_id = pp.id AND s.is_active = 1) AS service_count
    FROM tbl_provider_profiles pp
    JOIN tbl_users u              ON pp.user_id = u.id
    LEFT JOIN tbl_categories c    ON pp.category_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $order
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$providers = $stmt->fetchAll();


$totalProviders   = count($providers);
$homeServiceCount = count(array_filter($providers, fn($p) => (int)$p['offers_home_service'] === 1));
$ratedProviders   = array_filter($providers, fn($p) => (float)$p['avg_rating'] > 0);
$avgRating        = count($ratedProviders) > 0
    ? round(array_sum(array_column(array_values($ratedProviders), 'avg_rating')) / count($ratedProviders), 1)
    : 0;
$totalCategories  = count($cats);


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
    'dental' => '
        <path d="M10 6 C7 6 5 8.5 5 11 C5 14 7 15 8 19 C9 22 9.5 25 11.5 25 C13 25 13.5 22 15 22 C16.5 22 17 25 18.5 25 C20.5 25 21 22 22 19 C23 15 25 14 25 11 C25 8.5 23 6 20 6 C18 6 17 7.5 15 7.5 C13 7.5 12 6 10 6 Z" fill="#1A1000" stroke="#C9A84C" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M10.5 6.5 C10.5 10 12 12 15 12 C18 12 19.5 10 19.5 6.5" stroke="#C9A84C" stroke-width="1.3" stroke-linecap="round" fill="none" opacity=".6"/>
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
    'on-site'  => ' Home Service',
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
  <title>QuickBook — Browse Providers</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_browse.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .pv-cat-scroll { --cat-count: <?= $catCount ?>; }

    /* ── Provider card gallery strip ── */
    .pv-svc-gallery {
      display: flex; gap: 4px; padding: 0 1.3rem .75rem;
    }
    .pv-svc-gallery-img {
      width: 56px; height: 56px; border-radius: 8px;
      object-fit: cover; flex-shrink: 0;
      border: 1px solid var(--gold-border);
    }
    .pv-svc-gallery-more {
      width: 56px; height: 56px; border-radius: 8px;
      background: var(--surface-md); border: 1px solid var(--gold-border);
      display: flex; align-items: center; justify-content: center;
      font-family: var(--font-mono); font-size: .65rem;
      color: var(--gold-dim); flex-shrink: 0;
    }
    /* ── Provider name pill ── */
    .pv-svc-fullname {
      font-size: .72rem; color: var(--text-dim); margin-top: .15rem;
    }
    /* ── Location chip ── */
    .pv-svc-location {
      font-family: var(--font-mono); font-size: .62rem;
      color: var(--text-dim); display: flex; align-items: center; gap: .3rem;
    }
    /* ── Service count chip ── */
    .pv-svc-count {
      font-family: var(--font-mono); font-size: .62rem;
      color: var(--text-muted); background: var(--surface-md);
      padding: .18rem .55rem; border-radius: 99px;
      border: 1px solid var(--border);
    }
    /* ── Provider avatar override ── */
    .pv-svc-av img {
      width: 44px; height: 44px; object-fit: cover;
      border-radius: var(--r-md);
    }
  </style>
  <script>
    (function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();
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
      <a href="<?= BASE_URL ?>bookings" class="pv-nav-link">
        Bookings<?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse" class="pv-nav-link is-active">Browse Services</a>
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
        Browse Providers
      </p>
      <h1 class="pv-hero-name">Find the perfect <em>provider</em> for you</h1>
      <p class="pv-hero-sub">Browse local shops by category, view their work, then book in seconds.</p>
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
               placeholder="Search providers or categories…"
               value="<?= htmlspecialchars($search) ?>"
               aria-label="Search providers or categories"
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
          <span class="pv-hs-val"><?= $totalProviders ?></span>
          <span class="pv-hs-label">Providers Available</span>
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
          <span class="pv-hs-val"><?= $homeServiceCount ?></span>
          <span class="pv-hs-label">Offer Home Service</span>
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
      <span class="pv-cat-title">Categories</span>
    </div>

    <div class="pv-cat-scroll" role="list">

      <a href="<?= BASE_URL ?>browse<?= $search ? '?search='.urlencode($search) : '' ?>"
         class="pv-cat-item <?= !$selectedCat ? 'active' : '' ?>"
         role="listitem" aria-label="All categories">
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
      <span class="pv-result-num"><?= count($providers) ?></span>
      provider<?= count($providers) !== 1 ? 's' : '' ?> found
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

      <!-- Service Type Filter -->
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
              ''         => ['label' => 'All types',     'desc' => 'No filter applied',         'icon' => '◈'],
              'on-site'  => ['label' => 'Home Service',  'desc' => 'Provider comes to you',      'icon' => ''],
              'remote'   => ['label' => 'Remote',        'desc' => 'Online / virtual session',   'icon' => ''],
              'in-shop'  => ['label' => 'In-shop',       'desc' => "Visit provider's location",  'icon' => ''],
              'flexible' => ['label' => 'Flexible',      'desc' => 'Multiple options available', 'icon' => ''],
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
          <select name="sort" class="pv-sort-select" onchange="this.form.submit()" aria-label="Sort providers">
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


  <?php if (empty($providers)): ?>
  <div class="pv-empty-state">
    <div class="pv-empty-icon" aria-hidden="true">🔍</div>
    <p>No providers found. Try adjusting your filters or search term.</p>
    <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Clear All Filters →</a>
  </div>

  <?php else: ?>
  <div class="pv-service-grid" role="list">
    <?php
    // Pre-fetch gallery thumbnails for all providers (max 3 per provider)
    $providerIds = array_column($providers, 'profile_id');
    $galleryMap  = [];
    if (!empty($providerIds)) {
        $placeholders = implode(',', array_fill(0, count($providerIds), '?'));
        $gStmt = $db->prepare("
            SELECT provider_id, image_url
            FROM tbl_provider_gallery
            WHERE provider_id IN ($placeholders)
            ORDER BY sort_order ASC, id ASC
        ");
        $gStmt->execute($providerIds);
        foreach ($gStmt->fetchAll() as $gRow) {
            $galleryMap[$gRow['provider_id']][] = $gRow['image_url'];
        }
    }

    foreach ($providers as $p):
      $slug      = $p['category_slug'] ?? '';
      $rating    = (float)$p['avg_rating'];
      $reviews   = (int)$p['total_reviews'];
      $minPrice  = $p['min_price'];
      $svcCount  = (int)$p['service_count'];
      $locTypes  = $p['location_types_offered'] ?? '';
      $hasHome   = (int)$p['offers_home_service'] === 1;

      // Location type badges
      $badges = [];
      if ($hasHome)                              $badges[] = ['label' => 'Home Service', 'class' => 'badge-onsite'];
      if (strpos($locTypes, 'In-shop') !== false) $badges[] = ['label' => 'In-shop',    'class' => 'badge-inshop'];
      if (strpos($locTypes, 'Flexible') !== false) $badges[] = ['label' => 'Flexible',  'class' => 'badge-flexible'];
      if (strpos($locTypes, 'Remote') !== false)   $badges[] = ['label' => 'Remote',    'class' => 'badge-remote'];

      // Provider photo: prefer profile_photo, fallback avatar_url, fallback category icon
      $providerPhoto = $p['profile_photo'] ?? $p['avatar_url'] ?? null;

      // Gallery images
      $galleryImgs = $galleryMap[$p['profile_id']] ?? [];
      $showImgs    = array_slice($galleryImgs, 0, 3);
      $extraCount  = count($galleryImgs) - 3;

      $fullName = htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name']));
    ?>
    <a href="<?= BASE_URL ?>providers/<?= (int)$p['profile_id'] ?>"
       class="pv-service-card"
       role="listitem"
       aria-label="<?= htmlspecialchars($p['business_name']) ?> — <?= htmlspecialchars($p['category_name'] ?? 'Provider') ?>">

      <div class="pv-svc-accent" aria-hidden="true"></div>

      <!-- Card Header: avatar + category + badges -->
      <div class="pv-svc-head">
        <div class="pv-svc-av" aria-hidden="true">
          <?php if ($providerPhoto): ?>
            <img src="<?= htmlspecialchars($providerPhoto) ?>" alt="<?= htmlspecialchars($p['business_name']) ?>">
          <?php else: ?>
            <?= catSvg($slug, $catIconMap, $allIcon) ?>
          <?php endif; ?>
        </div>
        <div class="pv-svc-head-right">
          <div class="pv-svc-category"><?= htmlspecialchars($p['category_name'] ?? 'Service') ?></div>
          <div style="display:flex;flex-wrap:wrap;gap:.25rem;justify-content:flex-end;">
            <?php foreach (array_slice($badges, 0, 2) as $badge): ?>
              <span class="pv-svc-stype-badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Gallery strip (only if photos exist) -->
      <?php if (!empty($showImgs)): ?>
      <div class="pv-svc-gallery">
        <?php foreach ($showImgs as $imgUrl): ?>
          <img src="<?= htmlspecialchars($imgUrl) ?>" class="pv-svc-gallery-img" alt="Work photo" loading="lazy">
        <?php endforeach; ?>
        <?php if ($extraCount > 0): ?>
          <div class="pv-svc-gallery-more">+<?= $extraCount ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Card Body: shop name, owner name, location, services -->
      <div class="pv-svc-body">
        <div class="pv-svc-name"><?= htmlspecialchars($p['business_name']) ?></div>
        <div class="pv-svc-fullname"><?= $fullName ?></div>
        <?php if ($p['barangay'] || $p['city']): ?>
          <div class="pv-svc-location" style="margin-top:.35rem;">
            📍 <?= htmlspecialchars(implode(', ', array_filter([$p['barangay'], $p['city']]))) ?>
          </div>
        <?php endif; ?>
        <?php if ($svcCount > 0): ?>
          <div style="margin-top:.4rem;">
            <span class="pv-svc-count"><?= $svcCount ?> service<?= $svcCount !== 1 ? 's' : '' ?></span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Rating row -->
      <div class="pv-svc-meta">
        <div class="pv-svc-rating">
          <span class="pv-svc-stars" aria-label="Rating <?= number_format($rating,1) ?> out of 5">
            <?= renderStars($rating) ?>
          </span>
          <span class="pv-svc-rating-val"><?= $reviews > 0 ? number_format($rating,1) : '—' ?></span>
          <span class="pv-svc-reviews">(<?= $reviews ?>)</span>
        </div>
      </div>

      <!-- Footer: starting price + CTA -->
      <div class="pv-svc-footer">
        <div class="pv-svc-price">
          <?php if ($minPrice !== null): ?>
            <span style="font-size:.72rem;color:var(--text-muted);margin-right:.2rem;">From</span>
            <span class="pv-svc-price-val">₱<?= number_format((float)$minPrice, 0) ?></span>
          <?php else: ?>
            <span style="font-size:.72rem;color:var(--text-dim);">No services yet</span>
          <?php endif; ?>
        </div>
        <span class="pv-svc-cta">View Shop</span>
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