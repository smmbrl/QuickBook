<?php
// app/views/customer/browse.php

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

// ── Session ───────────────────────────────────────────
$userName      = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$userEmail     = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId        = (int)($_SESSION['user_id'] ?? 0);
$initials      = strtoupper(substr($userName, 0, 2));

// ── Loyalty (for nav badge) ───────────────────────────
$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$loyaltyTier   = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};

// ── Upcoming bookings count (for nav badge) ───────────
$stUpcoming = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE() AND deleted_at IS NULL");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();

// ── Categories ────────────────────────────────────────
$cats = $db->query("SELECT * FROM tbl_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// ── Filters ───────────────────────────────────────────
$selectedCat = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search      = trim($_GET['search'] ?? '');
$homeService = isset($_GET['home']) ? 1 : 0;
$sortBy      = $_GET['sort'] ?? 'rating';

// ── Build SERVICES query ──────────────────────────────
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
if ($homeService) {
    $where[] = "pp.offers_home_service = 1";
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
           pp.id             AS profile_id,
           pp.business_name,
           pp.offers_home_service,
           pp.avg_rating,
           pp.total_reviews,
           pp.city,
           pp.barangay,
           c.name            AS category_name,
           c.slug            AS category_slug,
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

// ── Hero stats ────────────────────────────────────────
$totalServices   = count($services);
$homeServices    = count(array_filter($services, fn($s) => $s['offers_home_service']));
$avgRating       = $totalServices > 0
    ? round(array_sum(array_column($services, 'avg_rating')) / $totalServices, 1)
    : 0;
$totalCategories = count($cats);

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

function renderStars(float $rating): string {
    $filled = floor($rating);
    $half   = ($rating - $filled) >= .5 ? 1 : 0;
    $empty  = 5 - $filled - $half;
    return str_repeat('★', $filled) . ($half ? '½' : '') . str_repeat('☆', $empty);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Browse Services</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_browse.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>

<!-- ══ NAV ══ -->
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

<!-- ══ HERO ══ -->
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
      <?php if ($homeService): ?>
        <input type="hidden" name="home" value="1">
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

  <!-- Stat strip -->
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
          <span class="pv-hs-val"><?= $homeServices ?></span>
          <span class="pv-hs-label">Home Service</span>
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

<!-- ══ MAIN ══ -->
<main class="pv-page" role="main">

  <!-- Category pills -->
  <div class="pv-cat-section" role="region" aria-label="Filter by category">
    <div class="pv-cat-row">
      <a href="<?= BASE_URL ?>browse<?= $search ? '?search='.urlencode($search) : '' ?>"
         class="pv-cat-pill <?= !$selectedCat ? 'active' : '' ?>">
        All Services
      </a>
      <?php foreach ($cats as $cat): ?>
      <a href="<?= BASE_URL ?>browse?category=<?= $cat['id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>"
         class="pv-cat-pill <?= $selectedCat == $cat['id'] ? 'active' : '' ?>">
        <?= catEmoji($cat['slug'], $catEmojiMap) ?> <?= htmlspecialchars($cat['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Toolbar -->
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
      <?php
        $homeUrl = BASE_URL . 'browse?' . http_build_query(array_filter([
          'category' => $selectedCat ?: '',
          'search'   => $search,
          'sort'     => $sortBy,
          'home'     => $homeService ? 0 : 1,
        ]));
      ?>
      <a href="<?= $homeUrl ?>" class="pv-filter-toggle <?= $homeService ? 'is-on' : '' ?>">
        🏠 Home Service<?= $homeService ? ' ✓' : '' ?>
      </a>

      <form method="GET" action="<?= BASE_URL ?>browse">
        <?php if ($selectedCat): ?><input type="hidden" name="category" value="<?= $selectedCat ?>"><?php endif; ?>
        <?php if ($search):       ?><input type="hidden" name="search"   value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
        <?php if ($homeService):  ?><input type="hidden" name="home"     value="1"><?php endif; ?>
        <select name="sort" class="pv-sort-select" onchange="this.form.submit()" aria-label="Sort services">
          <option value="rating"   <?= $sortBy === 'rating'   ? 'selected' : '' ?>>⭐ Top Rated</option>
          <option value="reviews"  <?= $sortBy === 'reviews'  ? 'selected' : '' ?>>💬 Most Reviews</option>
          <option value="price_lo" <?= $sortBy === 'price_lo' ? 'selected' : '' ?>>💰 Price: Low to High</option>
          <option value="price_hi" <?= $sortBy === 'price_hi' ? 'selected' : '' ?>>💸 Price: High to Low</option>
          <option value="name"     <?= $sortBy === 'name'     ? 'selected' : '' ?>>🔤 A – Z</option>
        </select>
      </form>

      <?php if ($search || $selectedCat || $homeService): ?>
        <a href="<?= BASE_URL ?>browse" class="pv-clear-btn">Clear filters</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Service grid -->
  <?php if (empty($services)): ?>
  <div class="pv-empty-state">
    <div class="pv-empty-icon" aria-hidden="true">🔍</div>
    <p>No services found. Try adjusting your filters or search term.</p>
    <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Clear All Filters →</a>
  </div>

  <?php else: ?>
  <div class="pv-service-grid" role="list">
    <?php foreach ($services as $s):
      $slug    = $s['category_slug'] ?? '';
      $emoji   = catEmoji($slug, $catEmojiMap);
      $rating  = (float)$s['avg_rating'];
      $reviews = (int)$s['total_reviews'];
      $duration = !empty($s['duration_minutes']) ? $s['duration_minutes'] . ' min' : null;
    ?>
    <a href="<?= BASE_URL ?>providers/<?= (int)$s['profile_id'] ?>"
       class="pv-service-card"
       role="listitem"
       aria-label="<?= htmlspecialchars($s['name']) ?> by <?= htmlspecialchars($s['business_name']) ?>">

      <!-- Card top accent -->
      <div class="pv-svc-accent" aria-hidden="true"></div>

      <!-- Header: emoji + category -->
      <div class="pv-svc-head">
        <div class="pv-svc-av" aria-hidden="true"><?= $emoji ?></div>
        <div class="pv-svc-head-right">
          <div class="pv-svc-category"><?= htmlspecialchars($s['category_name'] ?? 'Service') ?></div>
          <?php if ($s['offers_home_service']): ?>
            <span class="pv-svc-home-badge">🏠 Home</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Service name & provider -->
      <div class="pv-svc-body">
        <div class="pv-svc-name"><?= htmlspecialchars($s['name']) ?></div>
        <div class="pv-svc-provider">📍 <?= htmlspecialchars($s['business_name']) ?></div>
        <?php if (!empty($s['description'])): ?>
          <div class="pv-svc-desc"><?= htmlspecialchars(mb_strimwidth($s['description'], 0, 80, '…')) ?></div>
        <?php endif; ?>
      </div>

      <!-- Meta row: duration + rating -->
      <div class="pv-svc-meta">
        <?php if ($duration): ?>
          <span class="pv-svc-dur">⏱ <?= $duration ?></span>
        <?php endif; ?>
        <div class="pv-svc-rating">
          <span class="pv-svc-stars" aria-label="Rating <?= number_format($rating,1) ?> out of 5">
            <?= renderStars($rating) ?>
          </span>
          <span class="pv-svc-rating-val"><?= $reviews > 0 ? number_format($rating,1) : '—' ?></span>
          <span class="pv-svc-reviews">(<?= $reviews ?>)</span>
        </div>
      </div>

      <!-- Footer: price + CTA -->
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

</body>
</html>