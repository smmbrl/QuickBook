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
$stUpcoming = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE()");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();

// ── Categories ────────────────────────────────────────
$cats = $db->query("SELECT * FROM tbl_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// ── Filters ───────────────────────────────────────────
$selectedCat = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search      = trim($_GET['search'] ?? '');
$homeService = isset($_GET['home']) ? 1 : 0;
$sortBy      = $_GET['sort'] ?? 'rating';

// ── Build providers query ─────────────────────────────
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
if ($homeService) {
    $where[] = "pp.offers_home_service = 1";
}

$orderMap = [
    'rating'   => 'pp.avg_rating DESC',
    'reviews'  => 'pp.total_reviews DESC',
    'price'    => 'min_price ASC',
    'name'     => 'pp.business_name ASC',
];
$order = $orderMap[$sortBy] ?? $orderMap['rating'];

$sql = "
    SELECT pp.*, u.first_name, u.last_name, u.avatar_url,
           c.name as category_name, c.slug as category_slug,
           (SELECT COUNT(*) FROM tbl_services s WHERE s.provider_id = pp.id AND s.is_active = 1) as service_count,
           (SELECT MIN(s.price) FROM tbl_services s WHERE s.provider_id = pp.id AND s.is_active = 1) as min_price
    FROM tbl_provider_profiles pp
    JOIN tbl_users u ON pp.user_id = u.id
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY $order
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$providers = $stmt->fetchAll();

// ── Stats for hero strip ──────────────────────────────
$totalProviders  = count($providers);
$homeProviders   = count(array_filter($providers, fn($p) => $p['offers_home_service']));
$avgRating       = $totalProviders > 0
    ? round(array_sum(array_column($providers, 'avg_rating')) / $totalProviders, 1)
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

<!-- ══════════════════════════════════════
     NAV — identical to dashboard & bookings
══════════════════════════════════════ -->
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

<!-- ══════════════════════════════════════
     HERO — matches dashboard / bookings hero
══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>

  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Browse Services
      </p>
      <h1 class="pv-hero-name">Find trusted <em>local experts</em></h1>
      <p class="pv-hero-sub">Browse verified service providers — from barbers to massage therapists.</p>
    </div>

    <!-- Hero search bar -->
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
               placeholder="Search providers or services…"
               value="<?= htmlspecialchars($search) ?>"
               aria-label="Search providers or services"
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
          <span class="pv-hs-val"><?= $totalProviders ?></span>
          <span class="pv-hs-label">Providers Found</span>
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
          <span class="pv-hs-val"><?= $homeProviders ?></span>
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

<!-- ══════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════ -->
<main class="pv-page" role="main">

  <!-- ── Category pills ── -->
  <div class="pv-cat-section" role="region" aria-label="Filter by category">
    <div class="pv-cat-row">
      <a href="<?= BASE_URL ?>browse<?= $search ? '?search='.urlencode($search) : '' ?>"
         class="pv-cat-pill <?= !$selectedCat ? 'active' : '' ?>">
        All Categories
      </a>
      <?php foreach ($cats as $cat): ?>
      <a href="<?= BASE_URL ?>browse?category=<?= $cat['id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>"
         class="pv-cat-pill <?= $selectedCat == $cat['id'] ? 'active' : '' ?>">
        <?= catEmoji($cat['slug'], $catEmojiMap) ?> <?= htmlspecialchars($cat['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Toolbar ── -->
  <div class="pv-toolbar">
    <div class="pv-result-count">
      <span class="pv-result-num"><?= count($providers) ?></span>
      provider<?= count($providers) !== 1 ? 's' : '' ?> found
      <?php if ($search): ?>
        for "<strong><?= htmlspecialchars($search) ?></strong>"
      <?php endif; ?>
      <?php if ($selectedCat): ?>
        <?php $activeCat = array_filter($cats, fn($c) => $c['id'] == $selectedCat); ?>
        <?php if ($activeCat): ?>
          in <strong><?= htmlspecialchars(reset($activeCat)['name']) ?></strong>
        <?php endif; ?>
      <?php endif; ?>
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
        <select name="sort" class="pv-sort-select" onchange="this.form.submit()" aria-label="Sort providers">
          <option value="rating"  <?= $sortBy === 'rating'  ? 'selected' : '' ?>>⭐ Top Rated</option>
          <option value="reviews" <?= $sortBy === 'reviews' ? 'selected' : '' ?>>💬 Most Reviews</option>
          <option value="price"   <?= $sortBy === 'price'   ? 'selected' : '' ?>>💰 Lowest Price</option>
          <option value="name"    <?= $sortBy === 'name'    ? 'selected' : '' ?>>🔤 A – Z</option>
        </select>
      </form>

      <?php if ($search || $selectedCat || $homeService): ?>
        <a href="<?= BASE_URL ?>browse" class="pv-clear-btn">Clear filters</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Provider grid ── -->
  <?php if (empty($providers)): ?>
  <div class="pv-empty-state">
    <div class="pv-empty-icon" aria-hidden="true">🔍</div>
    <p>No providers found. Try adjusting your filters or search term.</p>
    <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Clear All Filters →</a>
  </div>

  <?php else: ?>
  <div class="pv-provider-grid" role="list">
    <?php foreach ($providers as $p):
      $slug    = $p['category_slug'] ?? '';
      $emoji   = catEmoji($slug, $catEmojiMap);
      $rating  = (float)$p['avg_rating'];
      $reviews = (int)$p['total_reviews'];
    ?>
    <a href="<?= BASE_URL ?>providers/<?= (int)$p['id'] ?>"
       class="pv-provider-card"
       role="listitem"
       aria-label="<?= htmlspecialchars($p['business_name']) ?>">

      <!-- Card cover -->
      <div class="pv-card-cover">
        <div class="pv-card-cover-emoji" aria-hidden="true"><?= $emoji ?></div>

        <!-- Glow orb behind emoji -->
        <div class="pv-card-cover-glow" aria-hidden="true"></div>

        <!-- Badges -->
        <?php if ($rating > 0): ?>
        <div class="pv-card-badge pv-card-badge--rating">
          ⭐ <?= number_format($rating, 1) ?>
        </div>
        <?php endif; ?>

        <?php if ($p['offers_home_service']): ?>
        <div class="pv-card-badge pv-card-badge--home">
          🏠 Home Service
        </div>
        <?php endif; ?>
      </div>

      <!-- Card body -->
      <div class="pv-card-body">
        <div class="pv-card-category"><?= htmlspecialchars($p['category_name'] ?? 'Services') ?></div>
        <div class="pv-card-name"><?= htmlspecialchars($p['business_name']) ?></div>
        <div class="pv-card-location">
          📍 <?= htmlspecialchars(($p['barangay'] ? $p['barangay'].', ' : '') . $p['city']) ?>
        </div>

        <div class="pv-card-meta">
          <div class="pv-card-rating">
            <span class="pv-card-stars" aria-label="Rating: <?= number_format($rating, 1) ?> out of 5">
              <?= renderStars($rating) ?>
            </span>
            <span class="pv-card-rating-val"><?= $reviews > 0 ? number_format($rating, 1) : '—' ?></span>
            <span class="pv-card-reviews">(<?= $reviews ?>)</span>
          </div>
          <div class="pv-card-price">
            from <strong>₱<?= $p['min_price'] ? number_format((float)$p['min_price'], 0) : '—' ?></strong>
          </div>
        </div>
      </div>

      <!-- Card footer -->
      <div class="pv-card-footer">
        <span class="pv-card-services">
          🛠 <?= (int)$p['service_count'] ?> service<?= (int)$p['service_count'] !== 1 ? 's' : '' ?>
        </span>
        <span class="pv-card-cta">View →</span>
      </div>

    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</main>

</body>
</html>