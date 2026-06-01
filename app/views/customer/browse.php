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

// ── Custom category display order ─────────────────────────────────────────────
$catOrder = [
    'barbershop', 'barber',
    'hair-salon', 'hair',
    'skincare-facial', 'skincare',
    'nail-care', 'nail',
    'fitness-training', 'fitness',
    'massage-therapy', 'massage',
    'dental', 'dental-services',
    'pet-grooming', 'pet',
    'home-cleaning', 'cleaning',
    'makeup', 'makeup-artist',
];
usort($cats, function($a, $b) use ($catOrder) {
    $posA = array_search($a['slug'], $catOrder);
    $posB = array_search($b['slug'], $catOrder);
    $posA = $posA === false ? 999 : $posA;
    $posB = $posB === false ? 999 : $posB;
    return $posA <=> $posB;
});

$selectedCat    = isset($_GET['category'])     ? (int)$_GET['category']          : 0;
$search         = trim($_GET['search']         ?? '');
$locationFilter = trim($_GET['service_type']   ?? '');
$sortBy         = $_GET['sort']                ?? 'rating';
$quickFilter    = trim($_GET['quick']          ?? '');
$viewMode       = trim($_GET['view']           ?? 'grid'); // 'grid' or 'map'

// ── Build WHERE ────────────────────────────────────────────────────────────────
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
if ($quickFilter === 'home') {
    $where[] = "pp.offers_home_service = 1";
} elseif ($quickFilter === 'top') {
    $where[] = "COALESCE(pp.avg_rating, 0) >= 4";
} elseif ($quickFilter === 'verified') {
    $where[] = "pp.is_verified = 1";
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
           pp.city, pp.barangay,
           pp.latitude, pp.longitude,
           pp.business_address,
           pp.offers_home_service,
           pp.location_types_offered,
           pp.profile_photo,
           pp.cover_photo,
           pp.is_verified,
           pp.business_hours,
           c.name  AS category_name,
           c.slug  AS category_slug,
           c.id    AS category_id_ref,
           u.first_name, u.last_name, u.avatar_url,
           (SELECT MIN(s.price) FROM tbl_services s
            WHERE s.provider_id = pp.id AND s.is_active = 1) AS min_price,
           (SELECT COUNT(*) FROM tbl_services s
            WHERE s.provider_id = pp.id AND s.is_active = 1) AS service_count,
           (SELECT GROUP_CONCAT(DISTINCT s.location_type ORDER BY s.location_type SEPARATOR ',')
            FROM tbl_services s
            WHERE s.provider_id = pp.id AND s.is_active = 1) AS svc_location_types,
           (SELECT id FROM tbl_services s
            WHERE s.provider_id = pp.id AND s.is_active = 1 ORDER BY s.id ASC LIMIT 1) AS first_service_id
    FROM tbl_provider_profiles pp
    JOIN tbl_users u           ON pp.user_id = u.id
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $order
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$providers = $stmt->fetchAll();

// Total unfiltered provider count (for the "All" category pill)
$totalProviders = (int)$db->query("
    SELECT COUNT(*) FROM tbl_provider_profiles pp
    JOIN tbl_users u ON pp.user_id = u.id
    WHERE pp.is_approved = 1 AND u.is_active = 1
")->fetchColumn();
$homeServiceCount = count(array_filter($providers, fn($p) => (int)$p['offers_home_service'] === 1));
$totalCategories  = count($cats);

// Provider counts per category (for category pills)
$catCountsRows = $db->query("
    SELECT pp.category_id, COUNT(*) AS cnt
    FROM tbl_provider_profiles pp
    JOIN tbl_users u ON pp.user_id = u.id
    WHERE pp.is_approved = 1 AND u.is_active = 1
    GROUP BY pp.category_id
")->fetchAll(PDO::FETCH_KEY_PAIR);

$catCount = count($cats) + 1; // for CSS var

// ── FA icon map for Browse Categories pills (matches dashboard icons) ─────────
$catFaIconMap = [
    // Only these 5 categories use FA icons; others keep custom SVG
    'hair-salon'      => 'fa-scissors',
    'hair'            => 'fa-scissors',
    'nail-care'       => 'fa-hand-sparkles',
    'nail'            => 'fa-hand-sparkles',
    'pet-grooming'    => 'fa-paw',
    'pet'             => 'fa-paw',
    'makeup'          => 'fa-wand-sparkles',
    'makeup-artist'   => 'fa-wand-sparkles',
    'massage-therapy' => 'fa-spa',
    'massage'         => 'fa-spa',
];

// ── Icon map ─────────────────────────────────────────────────────────────────
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

// ── Slug aliases (short DB slugs map to same icon as long-form) ──────────────
$catIconMap['barber']                 = $catIconMap['barbershop'];
$catIconMap['hair']                   = $catIconMap['hair-salon'];
$catIconMap['nail']                   = $catIconMap['nail-care'];
$catIconMap['massage']                = $catIconMap['massage-therapy'];
$catIconMap['skincare']               = $catIconMap['skincare-facial'];
$catIconMap['facial']                 = $catIconMap['skincare-facial'];
$catIconMap['fitness']                = $catIconMap['fitness-training'];
$catIconMap['cleaning']               = $catIconMap['home-cleaning'];
$catIconMap['home-cleaning-services'] = $catIconMap['home-cleaning'];
$catIconMap['pet']                    = $catIconMap['pet-grooming'];
$catIconMap['dental-services']        = $catIconMap['dental'];
$catIconMap['makeup-artist']          = $catIconMap['makeup'];

// ── Map icon SVG strings for JS (inline SVG encoded for divIcon) ─────────────
$mapIconSvgMap = [];
foreach ($catIconMap as $slug => $paths) {
    $svgStr = '<svg viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">' . $paths . '</svg>';
    $mapIconSvgMap[$slug] = $svgStr;
}
$mapIconSvgJson = json_encode($mapIconSvgMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// ── Build provider map JSON ────────────────────────────────────────────────────
$mapProviders = [];
foreach ($providers as $p) {
    /* Service mode: use actual service location_types (same source as dashboard)
       svc_location_types = GROUP_CONCAT from tbl_services.location_type
       enum values: 'On-site','Remote','In-shop','Flexible'
       Fallback to location_types_offered + offers_home_service flag. */
    $svcLoc   = strtolower($p['svc_location_types'] ?? '');
    $svcParts = array_filter(array_map('trim', explode(',', $svcLoc)));
    // Also check the profile SET column as fallback
    $locRaw   = strtolower($p['location_types_offered'] ?? '');
    $locParts = array_filter(array_map('trim', explode(',', $locRaw)));
    // Merge both sources for detection
    $allLoc   = array_unique(array_merge($svcParts, $locParts));
    $hasShop  = in_array('in-shop', $allLoc) || in_array('business_location', $allLoc);
    $hasHome  = in_array('on-site', $allLoc) || in_array('remote', $allLoc)
                || in_array('home_service', $allLoc)
                || (!empty($p['offers_home_service']) && (int)$p['offers_home_service'] === 1);
    $hasFlex  = in_array('flexible', $allLoc);
    if ($hasFlex || ($hasShop && $hasHome)) $serviceMode = 'flexible';
    elseif ($hasHome)                       $serviceMode = 'home_service';
    else                                    $serviceMode = 'in_shop';

    $mapProviders[] = [
        'id'          => (int)$p['profile_id'],
        'name'        => $p['business_name'],
        'provider'    => htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name'])),
        'category'    => $p['category_name'] ?? '',
        'categorySlug'=> $p['category_slug'] ?? '',
        'rating'      => round((float)$p['avg_rating'], 1),
        'reviews'     => (int)$p['total_reviews'],
        'lat'         => !empty($p['latitude'])  ? (float)$p['latitude']  : null,
        'lng'         => !empty($p['longitude']) ? (float)$p['longitude'] : null,
        'barangay'    => $p['barangay'] ?? '',
        'city'        => $p['city'] ?? '',
        'address'     => $p['business_address'] ?? '',
        'minPrice'    => $p['min_price'] !== null ? '₱' . number_format((float)$p['min_price'], 0) : '',
        'urlView'     => BASE_URL . 'providers/' . (int)$p['profile_id'],
        'urlBook'     => !empty($p['first_service_id']) ? BASE_URL . 'services/' . (int)$p['first_service_id'] : BASE_URL . 'providers/' . (int)$p['profile_id'],
        'photo'       => $p['profile_photo'] ?? $p['avatar_url'] ?? '',
        'isVerified'  => !empty($p['is_verified']),
        'serviceMode' => $serviceMode,
    ];
}
$mapProvidersJson = json_encode($mapProviders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// ── Helper: render category SVG or FA icon for card/map placeholders ──────────
/**
 * Renders the correct icon element for a given category slug.
 * Checks FA icon map first; falls back to custom SVG map; then falls back to $allIcon.
 *
 * @param string $slug         Category slug
 * @param array  $faMap        $catFaIconMap
 * @param array  $svgMap       $catIconMap
 * @param string $allIcon      Fallback SVG paths string
 * @param string $size         'lg' (card placeholder) | 'sm' (map sidebar) | 'pill' (category strip)
 * @return string              HTML string
 */
function renderCatIcon(string $slug, array $faMap, array $svgMap, string $allIcon, string $size = 'lg'): string {
    $slug = strtolower(trim($slug));

    $faSizes  = ['lg' => '36px', 'sm' => '20px', 'pill' => '18px'];
    $svgSizes = ['lg' => '44px', 'sm' => '26px', 'pill' => '26px'];
    $faSize   = $faSizes[$size]  ?? '36px';
    $svgSize  = $svgSizes[$size] ?? '44px';

    if ($slug !== '' && isset($faMap[$slug])) {
        return '<i class="fa-solid ' . htmlspecialchars($faMap[$slug]) . '" '
             . 'style="font-size:' . $faSize . ';color:#C9A84C;opacity:0.32;" aria-hidden="true"></i>';
    }

    $paths = ($slug !== '' && isset($svgMap[$slug])) ? $svgMap[$slug] : $allIcon;
    return '<svg viewBox="0 0 30 30" fill="none" aria-hidden="true" '
         . 'style="width:' . $svgSize . ';height:' . $svgSize . ';opacity:.28;">'
         . $paths . '</svg>';
}

// Legacy wrapper used by category strip & map info-card SVG (SVG-only, no FA needed there)
function catSvg(string $slug, array $map, string $allIcon): string {
    $slug  = strtolower(trim($slug));
    $paths = ($slug !== '' && isset($map[$slug])) ? $map[$slug] : $allIcon;
    return '<svg viewBox="0 0 30 30" fill="none" aria-hidden="true">' . $paths . '</svg>';
}

function renderStars(float $rating): string {
    $filled = floor($rating);
    $half   = ($rating - $filled) >= .5 ? 1 : 0;
    $empty  = 5 - $filled - $half;
    return str_repeat('★', $filled) . ($half ? '½' : '') . str_repeat('☆', $empty);
}

function isOpenNow(?string $hoursJson): ?bool {
    if (!$hoursJson) return null;
    $hours = json_decode($hoursJson, true);
    if (!$hours) return null;
    $dayKey = strtolower(date('D'));
    $day    = $hours[$dayKey] ?? null;
    if (!$day || empty($day['open']) || empty($day['close'])) return null;
    $now   = strtotime(date('H:i'));
    $open  = strtotime($day['open']);
    $close = strtotime($day['close']);
    return $now >= $open && $now <= $close;
}

$serviceTypeLabels = [
    'in-shop'  => 'In-shop',
    'on-site'  => 'Home Service',
    'flexible' => 'Flexible',
];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
  <style>
    .pv-cat-scroll { --cat-count: <?= $catCount ?>; }
    .pv-svc-av-float img { width:50px;height:50px;object-fit:cover;border-radius:calc(var(--r-md) - 2px); }
    .pv-nav-av img { width:34px;height:34px;object-fit:cover;border-radius:99px;display:block; }

    /* ── FA icon inside card/map placeholders ── */
    .pv-svc-profile-placeholder i.fa-solid,
    .pv-map-shop-photo-placeholder i.fa-solid {
      color: #C9A84C;
      opacity: 0.32;
      display: block;
    }
    .pv-svc-profile-placeholder i.fa-solid { font-size: 36px; }
    .pv-map-shop-photo-placeholder i.fa-solid { font-size: 20px; }
  </style>
  <script>
    (function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();
  </script>
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- ════════════ NAVIGATION ════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">

    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo"
           style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard"  class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>browse"     class="pv-nav-link is-active">Browse</a>
      <a href="<?= BASE_URL ?>bookings"   class="pv-nav-link">
        Bookings<?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>loyalty"    class="pv-nav-link">Loyalty</a>
    </div>

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/>  <line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22" y1="4.22"  x2="5.64" y2="5.64"/>   <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1"  y1="12" x2="3"  y2="12"/>  <line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>  <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
      </button>

      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0"
           aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av">
          <?php if ($avatarUrl): ?>
            <img src="<?= $avatarUrl ?>" alt="<?= $userName ?>">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div class="pv-nav-user">
          <div class="pv-nav-user-name"><?= $userName ?></div>
          <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
        </div>
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>

      <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="pv-pd-header">
          <div class="pv-pd-avatar">
            <?php if ($avatarUrl): ?>
              <img src="<?= $avatarUrl ?>" alt="<?= $userName ?>">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $userName ?></div>
            <div class="pv-pd-email"><?= $userEmail ?></div>
            <span class="pv-pd-tier"><?= $loyaltyTier ?> Member</span>
          </div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>profile" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-user"></i></span>
          <span>My Profile</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>auth/logout" class="pv-pd-item pv-pd-item--danger" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
          <span>Sign Out</span>
        </a>
      </div>
  </div>
</nav>

<!-- ════════════ HERO ════════════ -->
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
        <span class="pv-search-icon" aria-hidden="true">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
        </span>
        <input type="text" name="search"
               placeholder="Search providers, services or categories…"
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
</header>

<!-- view toggle hrefs computed here for use in toolbar -->
<?php
  $gridHref = BASE_URL . 'browse?' . http_build_query(array_filter([
    'category' => $selectedCat ?: '', 'search' => $search, 'sort' => $sortBy,
    'service_type' => $locationFilter, 'view' => 'grid',
  ]));
  $mapHref = BASE_URL . 'browse?' . http_build_query(array_filter([
    'category' => $selectedCat ?: '', 'search' => $search, 'sort' => $sortBy,
    'service_type' => $locationFilter, 'view' => 'map',
  ]));
?>

<!-- ════════════ PAGE BODY ════════════ -->
<main class="pv-page" role="main">

<?php if ($flash): ?>
<div class="bd-flash bd-flash--<?= htmlspecialchars($flash['type']) ?>" role="alert" style="margin:1.2rem auto;max-width:1200px;">
  <?= $flash['type'] === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?>
  <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

  <!-- CATEGORIES -->
  <section class="pv-cat-section" role="region" aria-label="Filter by category">
    <div class="pv-cat-header">
      <span class="pv-cat-title">Categories</span>
    </div>
    <div class="pv-cat-scroll" role="list">

      <a href="<?= BASE_URL ?>browse<?= $search ? '?search='.urlencode($search) : '' ?><?= $viewMode === 'map' ? ($search ? '&' : '?').'view=map' : '' ?>"
         class="pv-cat-item <?= !$selectedCat ? 'active' : '' ?>"
         role="listitem" aria-label="All categories">
        <div class="pv-cat-circle">
          <svg viewBox="0 0 30 30" fill="none" aria-hidden="true"><?= $allIcon ?></svg>
        </div>
        <span class="pv-cat-name">All</span>
        <span class="pv-cat-count"><?= $totalProviders ?> providers</span>
      </a>

      <?php foreach ($cats as $cat):
        $isActive  = $selectedCat == $cat['id'];
        $provCount = $catCountsRows[$cat['id']] ?? 0;
        $href = BASE_URL . 'browse?category=' . $cat['id']
              . ($search         ? '&search='.urlencode($search)               : '')
              . ($locationFilter ? '&service_type='.urlencode($locationFilter) : '')
              . ($sortBy !== 'rating' ? '&sort='.urlencode($sortBy)            : '')
              . ($viewMode === 'map' ? '&view=map' : '');
        $faIcon = $catFaIconMap[$cat['slug']] ?? null;
      ?>
      <a href="<?= $href ?>"
         class="pv-cat-item <?= $isActive ? 'active' : '' ?>"
         role="listitem"
         aria-label="<?= htmlspecialchars($cat['name']) ?>">
        <div class="pv-cat-circle">
          <?php if ($faIcon): ?>
            <i class="fa-solid <?= htmlspecialchars($faIcon) ?>" aria-hidden="true"></i>
          <?php else: ?>
            <?= catSvg($cat['slug'], $catIconMap, $allIcon) ?>
          <?php endif; ?>
        </div>
        <span class="pv-cat-name"><?= htmlspecialchars($cat['name']) ?></span>
        <span class="pv-cat-count"><?= $provCount ?> provider<?= $provCount !== 1 ? 's' : '' ?></span>
      </a>
      <?php endforeach; ?>

    </div>
  </section>

  <!-- TOOLBAR -->
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

      <!-- Service Type -->
      <div class="pv-stype-wrap" id="stypeWrap">
        <button class="pv-stype-btn <?= $locationFilter ? 'is-on' : '' ?>"
                id="stypeBtn" onclick="toggleStype(event)"
                aria-haspopup="listbox" aria-expanded="false">
          <span id="stypeLabel"><?= $locationFilter ? htmlspecialchars($serviceTypeLabels[$locationFilter] ?? $locationFilter) : 'Service Type' ?></span>
          <svg class="pv-stype-chev" viewBox="0 0 10 6" fill="none" stroke="currentColor"
               stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
               width="10" height="6" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
        </button>
        <div class="pv-stype-menu" id="stypeMenu" role="listbox">
          <?php
            $stypeOptions = [
              'in-shop'  => ['label'=>'In-shop',      'desc'=>"Visit provider's location", 'icon'=>'fa-store'],
              'on-site'  => ['label'=>'Home Service', 'desc'=>'Provider comes to you',     'icon'=>'fa-house'],
              'flexible' => ['label'=>'Flexible',     'desc'=>'Multiple options',          'icon'=>'fa-sliders'],
            ];
            foreach ($stypeOptions as $val => $opt):
              $isChosen = $locationFilter === $val;
              $href = BASE_URL . 'browse?' . http_build_query(array_filter([
                'category'     => $selectedCat ?: '',
                'search'       => $search,
                'sort'         => $sortBy,
                'service_type' => $val,
                'view'         => $viewMode !== 'grid' ? $viewMode : '',
              ]));
          ?>
          <a href="<?= $href ?>"
             class="pv-stype-opt <?= $isChosen ? 'chosen' : '' ?>"
             role="option" aria-selected="<?= $isChosen ? 'true' : 'false' ?>">
            <span class="pv-stype-opt-icon"><i class="fa-solid <?= $opt['icon'] ?>" aria-hidden="true"></i></span>
            <span class="pv-stype-opt-text">
              <span class="pv-stype-opt-label"><?= $opt['label'] ?></span>
              <span class="pv-stype-opt-desc"><?= $opt['desc'] ?></span>
            </span>
            <?php if ($isChosen): ?>
              <svg class="pv-stype-check" viewBox="0 0 12 12" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true"><path d="M2 6l3 3 5-5"/></svg>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Sort -->
      <?php
        $sortOptions = [
          'rating'   => ['label' => 'Top Rated',        'icon' => 'fa-star'],
          'reviews'  => ['label' => 'Most Reviews',     'icon' => 'fa-comment'],
          'price_lo' => ['label' => 'Price: Low → High','icon' => 'fa-arrow-up-wide-short'],
          'price_hi' => ['label' => 'Price: High → Low','icon' => 'fa-arrow-down-wide-short'],
          'name'     => ['label' => 'A – Z',            'icon' => 'fa-arrow-down-a-z'],
        ];
        $sortLabel = $sortOptions[$sortBy]['label'] ?? 'Top Rated';
      ?>
      <div class="pv-sort-wrap" id="sortWrap">
        <button class="pv-stype-btn <?= $sortBy !== 'rating' ? 'is-on' : '' ?>"
                id="sortBtn" onclick="toggleSort(event)"
                aria-haspopup="listbox" aria-expanded="false">
          <span id="sortLabel"><?= htmlspecialchars($sortLabel) ?></span>
          <svg class="pv-stype-chev" viewBox="0 0 10 6" fill="none" stroke="currentColor"
               stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
               width="10" height="6" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
        </button>
        <div class="pv-stype-menu" id="sortMenu" role="listbox">
          <?php foreach ($sortOptions as $val => $opt):
            $isChosen = $sortBy === $val;
            $href = BASE_URL . 'browse?' . http_build_query(array_filter([
              'category'     => $selectedCat ?: '',
              'search'       => $search,
              'sort'         => $val,
              'service_type' => $locationFilter,
              'view'         => $viewMode !== 'grid' ? $viewMode : '',
            ]));
          ?>
          <a href="<?= $href ?>"
             class="pv-stype-opt <?= $isChosen ? 'chosen' : '' ?>"
             role="option" aria-selected="<?= $isChosen ? 'true' : 'false' ?>">
            <span class="pv-stype-opt-icon"><i class="fa-solid <?= $opt['icon'] ?>" aria-hidden="true"></i></span>
            <span class="pv-stype-opt-text">
              <span class="pv-stype-opt-label"><?= $opt['label'] ?></span>
            </span>
            <?php if ($isChosen): ?>
              <svg class="pv-stype-check" viewBox="0 0 12 12" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true"><path d="M2 6l3 3 5-5"/></svg>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($search || $selectedCat || $locationFilter): ?>
        <a href="<?= BASE_URL ?>browse<?= $viewMode === 'map' ? '?view=map' : '' ?>" class="pv-clear-btn">✕ Clear</a>
      <?php endif; ?>

      <!-- View toggle: Grid / Map -->
      <div class="pv-view-toggle" role="group" aria-label="View mode">
        <a href="<?= $gridHref ?>" class="pv-view-btn <?= $viewMode !== 'map' ? 'is-active' : '' ?>" title="Grid view">
          <svg width="16" height="16" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="1" width="5" height="5" rx="1"/><rect x="8" y="1" width="5" height="5" rx="1"/>
            <rect x="1" y="8" width="5" height="5" rx="1"/><rect x="8" y="8" width="5" height="5" rx="1"/>
          </svg>
        </a>
        <a href="<?= $mapHref ?>" class="pv-view-btn <?= $viewMode === 'map' ? 'is-active' : '' ?>" title="Map view">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/>
            <line x1="9" y1="3" x2="9" y2="18"/>
            <line x1="15" y1="6" x2="15" y2="21"/>
          </svg>
        </a>
      </div>

    </div>
  </div>

  <!-- ════ MAP VIEW ════ -->
  <?php if ($viewMode === 'map'): ?>

  <?php if (empty($providers)): ?>
  <div class="pv-empty-state">
    <div class="pv-empty-icon" aria-hidden="true">🔍</div>
    <p>No providers found. Try adjusting your filters or search term.</p>
    <a href="<?= BASE_URL ?>browse?view=map" class="pv-empty-cta">Clear All Filters →</a>
  </div>
  <?php else: ?>

  <!-- Split layout: Left = shop list, Right = map -->
  <div class="pv-map-layout" id="mapLayout">

    <!-- Sidebar toggle button -->
    <button class="pv-map-sidebar-toggle" id="sidebarToggle" aria-label="Toggle shop list" title="Toggle shop list">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="5" x2="15" y2="5"/><line x1="3" y1="9" x2="15" y2="9"/><line x1="3" y1="13" x2="15" y2="13"/>
      </svg>
    </button>

    <!-- Left Panel: Shop list -->
    <aside class="pv-map-panel" id="mapPanel" aria-label="Nearby shops">
      <div class="pv-map-panel-header">
        <div class="pv-map-panel-title">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M7 1C4.79 1 3 2.79 3 5c0 3.25 4 8 4 8s4-4.75 4-8c0-2.21-1.79-4-4-4z"/>
            <circle cx="7" cy="5" r="1.2"/>
          </svg>
          Nearby Shops
        </div>
        <span class="pv-map-panel-count" id="panelCount"><?= count($providers) ?> found</span>
      </div>

      <!-- Use My Location -->
      <button class="pv-use-location-btn" id="useLocationBtn" type="button">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="7" cy="7" r="3"/><line x1="7" y1="1" x2="7" y2="3"/><line x1="7" y1="11" x2="7" y2="13"/>
          <line x1="1" y1="7" x2="3" y2="7"/><line x1="11" y1="7" x2="13" y2="7"/>
        </svg>
        <span id="useLocationLabel">Use My Location</span>
      </button>

      <!-- Shop list -->
      <div class="pv-map-shop-list" id="mapShopList">
        <?php
          // Pre-fetch gallery for map list shops
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
        ?>
        <?php foreach ($providers as $idx => $p):
          $slug       = $p['category_slug'] ?? '';
          $rating     = (float)$p['avg_rating'];
          $reviews    = (int)$p['total_reviews'];
          $fullName   = htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name']));
          $providerAv = $p['profile_photo'] ?? $p['avatar_url'] ?? null;
          $locLine    = implode(', ', array_filter([$p['barangay'] ?? '', $p['city'] ?? '']));
          $openStatus = isOpenNow($p['business_hours'] ?? null);
          $hasHome    = (int)$p['offers_home_service'] === 1;
          $isVerified = !empty($p['is_verified']);
          $minPrice   = $p['min_price'];
        ?>
        <div class="pv-map-shop-item" data-provider-id="<?= (int)$p['profile_id'] ?>"
             data-lat="<?= $p['latitude'] ?? '' ?>" data-lng="<?= $p['longitude'] ?? '' ?>">
          <div class="pv-map-shop-photo">
            <?php if ($providerAv): ?>
              <img src="<?= htmlspecialchars($providerAv) ?>" alt="<?= htmlspecialchars($p['business_name']) ?>" loading="lazy">
            <?php else: ?>
              <div class="pv-map-shop-photo-placeholder">
                <?= renderCatIcon($slug, $catFaIconMap, $catIconMap, $allIcon, 'sm') ?>
              </div>
            <?php endif; ?>
            <?php if ($openStatus === true): ?>
              <span class="pv-map-shop-status open"></span>
            <?php elseif ($openStatus === false): ?>
              <span class="pv-map-shop-status closed"></span>
            <?php endif; ?>
          </div>
          <div class="pv-map-shop-info">
            <div class="pv-map-shop-name"><?= htmlspecialchars($p['business_name']) ?>
              <?php if ($isVerified): ?><span class="pv-map-shop-verified" title="Verified">✔</span><?php endif; ?>
            </div>
            <div class="pv-map-shop-provider"><?= $fullName ?></div>
            <div class="pv-map-shop-cat"><?= htmlspecialchars($p['category_name'] ?? '') ?></div>
            <div class="pv-map-shop-meta">
              <?php if ($rating > 0): ?>
                <span class="pv-map-shop-stars">★ <?= number_format($rating, 1) ?></span>
                <span class="pv-map-shop-reviews">(<?= $reviews ?>)</span>
              <?php else: ?>
                <span class="pv-map-shop-reviews">New</span>
              <?php endif; ?>
              <span class="pv-map-shop-dist" id="dist-<?= (int)$p['profile_id'] ?>"></span>
            </div>
            <?php if ($locLine): ?>
            <div class="pv-map-shop-loc">📍 <?= htmlspecialchars($locLine) ?></div>
            <?php endif; ?>
          </div>
          <div class="pv-map-shop-actions">
            <a href="<?= BASE_URL ?>providers/<?= (int)$p['profile_id'] ?>" class="pv-map-shop-view-btn">View Shop</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </aside>

    <!-- Right Panel: Map -->
    <div class="pv-map-container" id="mapContainer">
      <div id="browseMap" class="pv-browse-map" aria-label="Map of providers"></div>

      <!-- Floating info card (shown on marker click) -->
      <div class="pv-map-info-card" id="mapInfoCard" role="dialog" aria-label="Provider details" aria-hidden="true">
        <button class="pv-map-info-close" id="mapInfoClose" aria-label="Close">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="2" y1="2" x2="12" y2="12"/><line x1="12" y1="2" x2="2" y2="12"/></svg>
        </button>

        <div class="pv-map-info-photo-wrap">
          <div class="pv-map-info-photo" id="cardPhoto"></div>
          <div class="pv-map-info-badges" id="cardBadges"></div>
        </div>

        <div class="pv-map-info-body">
          <div class="pv-map-info-cat" id="cardCat"></div>
          <div class="pv-map-info-name" id="cardName"></div>
          <div class="pv-map-info-provider" id="cardProvider"></div>

          <div class="pv-map-info-row">
            <span class="pv-map-info-rating" id="cardRating"></span>
            <span class="pv-map-info-dist" id="cardDist"></span>
          </div>

          <div class="pv-map-info-address" id="cardAddress"></div>

          <div class="pv-map-info-actions">
            <a href="#" class="pv-map-info-btn pv-map-info-btn--outline" id="cardViewShop">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 11 L11 1M5 1h6v6"/></svg>
              View Shop
            </a>
            <a href="#" class="pv-map-info-btn pv-map-info-btn--primary" id="cardBookNow">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="2" width="10" height="9" rx="1.5"/><path d="M8 1v2M4 1v2M1 5h10"/></svg>
              Book Now
            </a>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /pv-map-layout -->

  <?php endif; ?>

  <!-- ════ GRID VIEW ════ -->
  <?php else: ?>

  <!-- PROVIDER GRID -->
  <?php if (empty($providers)): ?>
  <div class="pv-empty-state">
    <div class="pv-empty-icon" aria-hidden="true">🔍</div>
    <p>No providers found. Try adjusting your filters or search term.</p>
    <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Clear All Filters →</a>
  </div>

  <?php else: ?>
  <?php
    // Pre-fetch gallery thumbnails (max 4 per provider)
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
  ?>
  <div class="pv-service-grid" role="list">
    <?php foreach ($providers as $p):
      $slug      = $p['category_slug'] ?? '';
      $rating    = (float)$p['avg_rating'];
      $reviews   = (int)$p['total_reviews'];
      $minPrice  = $p['min_price'];
      $svcCount  = (int)$p['service_count'];
      $locTypes  = $p['location_types_offered'] ?? '';
      $hasHome   = (int)$p['offers_home_service'] === 1;
      $isVerified = !empty($p['is_verified']);

      $coverImg   = $p['cover_photo'] ?? ($galleryMap[$p['profile_id']][0] ?? null);
      $providerAv = $p['profile_photo'] ?? $p['avatar_url'] ?? null;
      $allGallery  = $galleryMap[$p['profile_id']] ?? [];
      $galleryImgs = $coverImg && $allGallery && $allGallery[0] === $coverImg
                    ? array_slice($allGallery, 1, 3)
                    : array_slice($allGallery, 0, 3);
      $extraCount = max(0, count($allGallery) - (($coverImg && $allGallery && $allGallery[0] === $coverImg) ? 4 : 3));

      $badges = [];
      if ($hasHome)                               $badges[] = ['label'=>'Home Service','class'=>'badge-onsite'];
      if (strpos($locTypes,'In-shop')  !== false) $badges[] = ['label'=>'In-shop',    'class'=>'badge-inshop'];
      if (strpos($locTypes,'Flexible') !== false) $badges[] = ['label'=>'Flexible',   'class'=>'badge-flexible'];
      if (strpos($locTypes,'Remote')   !== false) $badges[] = ['label'=>'Remote',     'class'=>'badge-remote'];

      $fullName   = htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name']));
      $openStatus = isOpenNow($p['business_hours'] ?? null);
    ?>
    <a href="<?= BASE_URL ?>providers/<?= (int)$p['profile_id'] ?>"
       class="pv-service-card"
       role="listitem"
       aria-label="<?= htmlspecialchars($p['business_name']) ?>">

      <!-- TOP AREA: profile pic (right) + category icon overlay (top-left) + heart (top-right) -->
      <div class="pv-svc-top">

        <!-- Profile picture fills the top area -->
        <div class="pv-svc-profile-bg">
          <?php if ($providerAv): ?>
            <img src="<?= htmlspecialchars($providerAv) ?>" alt="<?= htmlspecialchars($p['business_name']) ?>" class="pv-svc-profile-img" loading="lazy">
          <?php else: ?>
            <!-- ── FIX: use renderCatIcon() so FA-mapped categories show correctly ── -->
            <div class="pv-svc-profile-placeholder">
              <?= renderCatIcon($slug, $catFaIconMap, $catIconMap, $allIcon, 'lg') ?>
            </div>
          <?php endif; ?>
          <!-- gradient overlay -->
          <div class="pv-svc-top-fade"></div>
        </div>

        <!-- Category icon — top left, in front -->
        <div class="pv-svc-cat-icon" aria-hidden="true">
          <?php if (isset($catFaIconMap[$slug])): ?>
            <i class="fa-solid <?= htmlspecialchars($catFaIconMap[$slug]) ?>" style="font-size:16px;color:#C9A84C;" aria-hidden="true"></i>
          <?php else: ?>
            <?= catSvg($slug, $catIconMap, $allIcon) ?>
          <?php endif; ?>
        </div>

        <!-- Heart/Favorite — top right -->
        <button class="pv-svc-heart" aria-label="Save to favourites" onclick="event.preventDefault();this.classList.toggle('is-liked');">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        </button>

        <!-- Open/Closed + Verified badges -->
        <div class="pv-svc-cover-badges">
          <?php if ($openStatus === true): ?>
            <span class="pv-svc-status open"><span class="pv-svc-status-dot"></span>Open Now</span>
          <?php elseif ($openStatus === false): ?>
            <span class="pv-svc-status closed"><span class="pv-svc-status-dot"></span>Closed</span>
          <?php endif; ?>
          <?php if ($isVerified): ?>
            <span class="pv-svc-verified">✔ Verified</span>
          <?php endif; ?>
        </div>

      </div>

      <!-- BODY -->
      <div class="pv-svc-body">

        <!-- Name row: name + service type badge -->
        <div class="pv-svc-name-row">
          <div class="pv-svc-name"><?= htmlspecialchars($p['business_name']) ?></div>
          <?php if (!empty($badges)): ?>
            <span class="pv-svc-stype-badge <?= $badges[0]['class'] ?>"><?= $badges[0]['label'] ?></span>
          <?php endif; ?>
        </div>

        <!-- Category name below business name -->
        <div class="pv-svc-category-name"><?= htmlspecialchars($p['category_name'] ?? '') ?></div>

        <!-- Location with SVG pin -->
        <?php if ($p['barangay'] || $p['city']): ?>
          <div class="pv-svc-location-row">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;color:var(--gold-dim);">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
            </svg>
            <?= htmlspecialchars(implode(', ', array_filter([$p['barangay'], $p['city']]))) ?>
          </div>
        <?php endif; ?>

      </div>

      <!-- RATING + CTA FOOTER -->
      <div class="pv-svc-footer">
        <div class="pv-svc-rating">
          <span class="pv-svc-stars" aria-label="Rating <?= number_format($rating,1) ?> out of 5">
            <?= renderStars($rating) ?>
          </span>
          <span class="pv-svc-rating-val"><?= $reviews>0 ? number_format($rating,1) : '—' ?></span>
          <span class="pv-svc-reviews">(<?= $reviews ?>)</span>
        </div>
        <span class="pv-svc-cta">View Provider →</span>
      </div>

    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php endif; // end grid vs map view ?>

</main>

<!-- ════════════ SCRIPTS ════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<script>
/* ── Service type dropdown ── */
function toggleStype(e) {
  e.stopPropagation();
  const menu = document.getElementById('stypeMenu');
  const btn  = document.getElementById('stypeBtn');
  const open = menu.classList.toggle('is-open');
  btn.setAttribute('aria-expanded', open);
  // close sort if open
  document.getElementById('sortMenu')?.classList.remove('is-open');
  document.getElementById('sortBtn')?.setAttribute('aria-expanded','false');
}
function toggleSort(e) {
  e.stopPropagation();
  const menu = document.getElementById('sortMenu');
  const btn  = document.getElementById('sortBtn');
  const open = menu.classList.toggle('is-open');
  btn.setAttribute('aria-expanded', open);
  // close stype if open
  document.getElementById('stypeMenu')?.classList.remove('is-open');
  document.getElementById('stypeBtn')?.setAttribute('aria-expanded','false');
}
document.addEventListener('click', () => {
  document.getElementById('stypeMenu')?.classList.remove('is-open');
  document.getElementById('stypeBtn')?.setAttribute('aria-expanded','false');
  document.getElementById('sortMenu')?.classList.remove('is-open');
  document.getElementById('sortBtn')?.setAttribute('aria-expanded','false');
});
</script>

<?php if ($viewMode === 'map' && !empty($providers)): ?>
<script>
/* ══════════════════════════════════════
   BROWSE MAP — Full interactive map
══════════════════════════════════════ */
(function () {
  var BACOLOD    = [10.6840, 122.9560];
  var providers  = <?= $mapProvidersJson ?>;
  var iconSvgMap = <?= $mapIconSvgJson ?>;
  var userLatLng = null;
  var markers    = {};
  var activeMarkerId = null;

  // ── Init Leaflet map ──────────────────────────────────────────────────────
  var map = L.map('browseMap', {
    center: BACOLOD, zoom: 13,
    zoomControl: true,
    scrollWheelZoom: true,
    attributionControl: true
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18
  }).addTo(map);

  // Map tile theme is handled by applyTheme() in the theme toggle script

  // ── Service mode meta — identical to dashboard ───────────────────────────
  var MODE_META = {
    in_shop:      { color: '#C9A84C', stroke: '#A8892E', label: 'In-Shop' },
    home_service: { color: '#3B82F6', stroke: '#2563EB', label: 'Home Service' },
    flexible:     { color: '#F97316', stroke: '#EA6000', label: 'Flexible' }
  };

  // ── Teardrop SVG pin — identical to dashboard ────────────────────────────
  function makeCatIcon(slug, isActive, serviceMode) {
    var mode   = serviceMode || 'in_shop';
    var meta   = MODE_META[mode] || MODE_META['in_shop'];
    var fill   = meta.color;

    /* Pin sizes: normal = 22x30, active = 28x38 */
    var W      = isActive ? 28 : 22;
    var H      = isActive ? 38 : 30;
    var headR  = isActive ? 11 : 9;
    var cx     = W / 2;
    var headCy = headR + 1;

    /* Tail bezier */
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

  // ── Legend control (service mode key) — identical to dashboard ──────────
  var LegendCtrl = L.Control.extend({
    onAdd: function () {
      var el = L.DomUtil.create('div', 'qb-map-legend');
      el.innerHTML =
        '<div class="qb-map-legend-title">Service Type</div>' +
        Object.keys(MODE_META).map(function (k) {
          var m = MODE_META[k];
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

  // ── Haversine distance (km) ───────────────────────────────────────────────
  function haversine(lat1, lon1, lat2, lon2) {
    var R = 6371;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a = Math.sin(dLat/2)*Math.sin(dLat/2) +
            Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*
            Math.sin(dLon/2)*Math.sin(dLon/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  function fmtDist(km) {
    return km < 1 ? Math.round(km * 1000) + 'm away' : km.toFixed(1) + 'km away';
  }

  // ── Show floating info card ───────────────────────────────────────────────
  function showInfoCard(p) {
    activeMarkerId = p.id;

    // Update marker icons
    Object.keys(markers).forEach(function(id) {
      markers[id].setIcon(makeCatIcon(providers.find(x => x.id == id)?.categorySlug || '', id == p.id, providers.find(x => x.id == id)?.serviceMode));
    });

    var card      = document.getElementById('mapInfoCard');
    var locLine   = [p.barangay, p.city].filter(Boolean).join(', ') || 'Bacolod City';
    var addrLine  = p.address || locLine;
    var distEl    = document.getElementById('dist-' + p.id);
    var distText  = distEl ? distEl.textContent : '';

    // Photo
    var photoEl = document.getElementById('cardPhoto');
    if (p.photo) {
      photoEl.innerHTML = '<img src="' + p.photo + '" alt="' + p.name + '" style="width:100%;height:100%;object-fit:cover;">';
    } else {
      photoEl.innerHTML = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--gold-lt);">' +
        (iconSvgMap[p.categorySlug] || '') + '</div>';
    }

    // Badges
    var badgesEl = document.getElementById('cardBadges');
    var badgesHtml = '';
    if (p.isVerified) badgesHtml += '<span class="pv-map-card-badge pv-map-card-badge--verified">✔ Verified</span>';
    badgesEl.innerHTML = badgesHtml;

    document.getElementById('cardCat').textContent    = p.category || '';
    document.getElementById('cardName').textContent   = p.name;
    document.getElementById('cardProvider').textContent = p.provider ? '👤 ' + p.provider : '';
    document.getElementById('cardAddress').textContent = '📍 ' + addrLine;

    var ratingEl = document.getElementById('cardRating');
    ratingEl.innerHTML = p.rating > 0
      ? '<span style="color:#C9A84C;">★</span> ' + p.rating.toFixed(1) +
        ' <span style="opacity:.6;font-size:.7em;">(' + p.reviews + ' reviews)</span>'
      : '<span style="opacity:.5;">No reviews yet</span>';

    var distCardEl = document.getElementById('cardDist');
    distCardEl.textContent = distText || '';

    document.getElementById('cardViewShop').href = p.urlView;
    document.getElementById('cardBookNow').href  = p.urlBook;

    card.setAttribute('aria-hidden', 'false');
    card.classList.add('is-visible');

    // Highlight sidebar item
    document.querySelectorAll('.pv-map-shop-item').forEach(function(el) {
      el.classList.toggle('is-active', parseInt(el.dataset.providerId) === p.id);
    });
    var activeEl = document.querySelector('.pv-map-shop-item.is-active');
    if (activeEl) activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function hideInfoCard() {
    var card = document.getElementById('mapInfoCard');
    card.classList.remove('is-visible');
    card.setAttribute('aria-hidden', 'true');
    // Reset marker icons
    if (activeMarkerId !== null) {
      var p = providers.find(x => x.id == activeMarkerId);
      if (p && markers[activeMarkerId]) {
        markers[activeMarkerId].setIcon(makeCatIcon(p.categorySlug, false, p.serviceMode));
      }
    }
    activeMarkerId = null;
    document.querySelectorAll('.pv-map-shop-item').forEach(function(el) {
      el.classList.remove('is-active');
    });
  }

  // ── Close button ─────────────────────────────────────────────────────────
  document.getElementById('mapInfoClose').addEventListener('click', hideInfoCard);
  document.getElementById('browseMap').addEventListener('click', function(e) {
    if (e.target === this) hideInfoCard();
  });

  // ── Add markers ──────────────────────────────────────────────────────────
  var bounds = [];

  providers.forEach(function(p, idx) {
    var lat = p.lat, lng = p.lng;
    if (!lat || !lng) {
      var angle  = (idx / Math.max(providers.length, 1)) * 2 * Math.PI;
      var radius = 0.012 + (idx % 3) * 0.006;
      lat = BACOLOD[0] + radius * Math.cos(angle);
      lng = BACOLOD[1] + radius * Math.sin(angle);
    }
    bounds.push([lat, lng]);
    var marker = L.marker([lat, lng], { icon: makeCatIcon(p.categorySlug, false, p.serviceMode) }).addTo(map);
    markers[p.id] = marker;
    marker.on('click', function(e) {
      L.DomEvent.stopPropagation(e);
      showInfoCard(p);
      map.panTo([lat, lng], { animate: true, duration: 0.4 });
    });
  });

  if (bounds.length === 1) {
    map.setView(bounds[0], 15);
  } else if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [60, 60], maxZoom: 14 });
  }

  // ── Sidebar shop item click → pan + show card ─────────────────────────────
  document.querySelectorAll('.pv-map-shop-item').forEach(function(el) {
    el.addEventListener('click', function() {
      var id  = parseInt(el.dataset.providerId);
      var p   = providers.find(x => x.id === id);
      if (!p) return;
      var lat = p.lat, lng = p.lng;
      if (!lat || !lng) {
        var idx = providers.indexOf(p);
        var angle  = (idx / Math.max(providers.length, 1)) * 2 * Math.PI;
        var radius = 0.012 + (idx % 3) * 0.006;
        lat = BACOLOD[0] + radius * Math.cos(angle);
        lng = BACOLOD[1] + radius * Math.sin(angle);
      }
      map.panTo([lat, lng], { animate: true, duration: 0.5 });
      setTimeout(function() { showInfoCard(p); }, 300);
    });
  });

  // ── Update distances given user location ────────────────────────────────
  function updateDistances(userLat, userLng) {
    var sorted = providers.map(function(p, idx) {
      var lat = p.lat, lng = p.lng;
      if (!lat || !lng) {
        var angle  = (idx / Math.max(providers.length, 1)) * 2 * Math.PI;
        var radius = 0.012 + (idx % 3) * 0.006;
        lat = BACOLOD[0] + radius * Math.cos(angle);
        lng = BACOLOD[1] + radius * Math.sin(angle);
      }
      return { p: p, dist: haversine(userLat, userLng, lat, lng), lat: lat, lng: lng };
    });
    sorted.sort(function(a, b) { return a.dist - b.dist; });

    // Update inline distance elements
    sorted.forEach(function(item) {
      var el = document.getElementById('dist-' + item.p.id);
      if (el) el.textContent = '· ' + fmtDist(item.dist);
    });

    // Update card dist if open
    if (activeMarkerId !== null) {
      var active = sorted.find(x => x.p.id === activeMarkerId);
      if (active) {
        document.getElementById('cardDist').textContent = fmtDist(active.dist);
      }
    }

    // Reorder sidebar list by distance
    var list = document.getElementById('mapShopList');
    sorted.forEach(function(item) {
      var el = list.querySelector('[data-provider-id="' + item.p.id + '"]');
      if (el) list.appendChild(el);
    });

    document.getElementById('panelCount').textContent = sorted.length + ' found · sorted by distance';
  }

  // ── Use My Location ────────────────────────────────────────────────────
  var userMarker = null;
  document.getElementById('useLocationBtn').addEventListener('click', function() {
    var btn   = this;
    var label = document.getElementById('useLocationLabel');
    if (!navigator.geolocation) {
      label.textContent = 'Geolocation not supported';
      return;
    }
    btn.classList.add('is-loading');
    label.textContent = 'Locating…';
    navigator.geolocation.getCurrentPosition(
      function(pos) {
        userLatLng = [pos.coords.latitude, pos.coords.longitude];
        btn.classList.remove('is-loading');
        btn.classList.add('is-located');
        label.textContent = 'Location Found';

        // Add user marker
        if (userMarker) map.removeLayer(userMarker);
        userMarker = L.marker(userLatLng, {
          icon: L.divIcon({
            className: '',
            html: '<div style="width:18px;height:18px;background:#3B82F6;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(59,130,246,.5);"></div>',
            iconSize:   [18, 18],
            iconAnchor: [9, 9]
          })
        }).addTo(map);

        // Show coordinates immediately, then replace with real address via reverse geocode
        userMarker.bindTooltip('Locating address…', { permanent: false, direction: 'top', className: 'qb-user-tooltip' });

        fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' +
              pos.coords.latitude + '&lon=' + pos.coords.longitude +
              '&zoom=18&addressdetails=1',
          { headers: { 'Accept-Language': 'en' } }
        )
        .then(function(r) { return r.json(); })
        .then(function(data) {
          var a   = data.address || {};
          // Build a short but precise address line
          var parts = [];
          if (a.house_number && a.road)       parts.push(a.house_number + ' ' + a.road);
          else if (a.road)                    parts.push(a.road);
          else if (a.pedestrian)              parts.push(a.pedestrian);
          else if (a.footway)                 parts.push(a.footway);
          if (a.suburb || a.village || a.neighbourhood)
            parts.push(a.suburb || a.village || a.neighbourhood);
          if (a.city || a.town || a.municipality)
            parts.push(a.city || a.town || a.municipality);
          var address = parts.length ? parts.join(', ') : (data.display_name || 'Your location');
          if (userMarker) {
            userMarker.unbindTooltip();
            userMarker.bindTooltip(address, { permanent: false, direction: 'top', className: 'qb-user-tooltip' });
          }
        })
        .catch(function() {
          if (userMarker) {
            userMarker.unbindTooltip();
            userMarker.bindTooltip('Your location', { permanent: false, direction: 'top', className: 'qb-user-tooltip' });
          }
        });

        map.setView(userLatLng, 14, { animate: true, duration: 1 });
        updateDistances(userLatLng[0], userLatLng[1]);
      },
      function(err) {
        btn.classList.remove('is-loading');
        label.textContent = err.code === 1 ? 'Permission denied' : 'Could not locate';
        setTimeout(function() { label.textContent = 'Use My Location'; btn.classList.remove('is-located'); }, 3000);
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  });

  // ── Sidebar toggle ────────────────────────────────────────────────────
  document.getElementById('sidebarToggle').addEventListener('click', function() {
    var layout = document.getElementById('mapLayout');
    var panel  = document.getElementById('mapPanel');
    var isHidden = layout.classList.toggle('panel-hidden');
    panel.setAttribute('aria-hidden', isHidden ? 'true' : 'false');
    this.setAttribute('aria-label', isHidden ? 'Show shop list' : 'Hide shop list');
    setTimeout(function() { map.invalidateSize(); }, 320);
  });

})();
</script>
<?php endif; ?>

<script>
/* ── Theme toggle ── */
(function () {
  var btn  = document.getElementById('themeToggle');
  var moon = document.querySelector('.icon-moon');
  var sun  = document.querySelector('.icon-sun');
  function applyMapTheme(theme) {
    var tilePane = document.querySelector('.leaflet-tile-pane');
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
      document.documentElement.setAttribute('data-theme','dark');
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
/* ── Profile dropdown ── */
(function () {
  const trigger  = document.getElementById('profileTrigger');
  const dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;
  function open()   { trigger.classList.add('is-open'); dropdown.classList.add('is-open'); trigger.setAttribute('aria-expanded','true'); }
  function close()  { trigger.classList.remove('is-open'); dropdown.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); }
  function toggle() { dropdown.classList.contains('is-open') ? close() : open(); }
  trigger.addEventListener('click', e => { e.stopPropagation(); toggle(); });
  trigger.addEventListener('keydown', e => {
    if (e.key==='Enter'||e.key===' ') { e.preventDefault(); toggle(); }
    if (e.key==='Escape') close();
  });
  document.addEventListener('click', e => {
    if (!dropdown.contains(e.target) && !trigger.contains(e.target)) close();
  });
  document.addEventListener('keydown', e => { if (e.key==='Escape') close(); });
})();
</script>
</body>
</html>