<?php
// app/views/customer/browse.php
// Fetch categories and providers from DB

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

// Get all active categories
$cats = $db->query("SELECT * FROM tbl_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Filters
$selectedCat  = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search       = trim($_GET['search'] ?? '');
$homeService  = isset($_GET['home']) ? 1 : 0;
$sortBy       = $_GET['sort'] ?? 'rating';

// Build providers query
$where  = ["pp.is_approved = 1", "u.is_active = 1"];
$params = [];

if ($selectedCat) {
    $where[]  = "pp.category_id = ?";
    $params[] = $selectedCat;
}
if ($search !== '') {
    $where[]  = "(pp.business_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
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

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Customer');

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
];
function catEmoji($slug, $map) {
    return $map[$slug] ?? '🛠️';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Browse Services</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/browse.css">
</head>
<body>

<!-- ══════════════════ SIDEBAR ══════════════════ -->
<aside class="sidebar">
  <a href="<?= BASE_URL ?>home" class="sidebar-logo">Quick<span>Book</span></a>
  <div class="sidebar-user">
    <div class="sidebar-user-name"><?= $userName ?></div>
    <div class="sidebar-user-role">Customer</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <a href="<?= BASE_URL ?>dashboard"  class="nav-link"><span class="icon">⊞</span> Dashboard</a>
    <a href="<?= BASE_URL ?>bookings"   class="nav-link"><span class="icon">📅</span> My Bookings</a>
    <a href="<?= BASE_URL ?>browse"     class="nav-link active"><span class="icon">🔍</span> Browse Services</a>
    <div class="nav-section" style="margin-top:.75rem">Account</div>
    <a href="<?= BASE_URL ?>loyalty"    class="nav-link"><span class="icon">⭐</span> Loyalty Points</a>
    <a href="<?= BASE_URL ?>profile"    class="nav-link"><span class="icon">👤</span> Profile</a>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>auth/logout" class="logout-btn"><span>⏻</span> Sign Out</a>
  </div>
</aside>

<!-- ══════════════════ MAIN ══════════════════════ -->
<div class="main">

  <header class="topbar">
    <div class="topbar-title">Browse Services</div>
    <div class="topbar-right">
      <div class="avatar"><?= strtoupper(substr($userName, 0, 2)) ?></div>
    </div>
  </header>

  <div class="content">

    <!-- Hero Search -->
    <div class="hero">
      <h1>Find trusted <em>local experts</em> near you</h1>
      <p>Browse verified service providers in Bacolod City — from barbers to massage therapists.</p>
      <form method="GET" action="<?= BASE_URL ?>browse">
        <?php if ($selectedCat): ?><input type="hidden" name="category" value="<?= $selectedCat ?>"><?php endif; ?>
        <?php if ($homeService): ?><input type="hidden" name="home" value="1"><?php endif; ?>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
        <div class="search-bar">
          <div class="search-input-wrap">
            <span>🔍</span>
            <input type="text" name="search"
                   placeholder="Search providers or services…"
                   value="<?= htmlspecialchars($search) ?>">
          </div>
          <button type="submit" class="search-btn">Search</button>
        </div>
      </form>
    </div>

    <!-- Category Pills -->
    <div class="cat-row">
      <a href="<?= BASE_URL ?>browse<?= $search ? '?search='.urlencode($search) : '' ?>"
         class="cat-pill <?= !$selectedCat ? 'active' : '' ?>">All</a>
      <?php foreach ($cats as $cat): ?>
      <a href="<?= BASE_URL ?>browse?category=<?= $cat['id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>"
         class="cat-pill <?= $selectedCat == $cat['id'] ? 'active' : '' ?>">
        <?= catEmoji($cat['slug'], $catEmojiMap) ?> <?= htmlspecialchars($cat['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="result-count">
        <strong><?= count($providers) ?></strong> provider<?= count($providers) !== 1 ? 's' : '' ?> found
        <?= $search ? ' for "<strong>'.htmlspecialchars($search).'</strong>"' : '' ?>
      </div>
      <div class="toolbar-right">
        <?php
          $homeUrl = BASE_URL . 'browse?' . http_build_query([
            'category' => $selectedCat, 'search' => $search,
            'sort' => $sortBy, 'home' => $homeService ? 0 : 1
          ]);
        ?>
        <a href="<?= $homeUrl ?>" class="filter-toggle <?= $homeService ? 'on' : '' ?>">
          🏠 Home Service<?= $homeService ? ' ✓' : '' ?>
        </a>
        <form method="GET" action="<?= BASE_URL ?>browse" style="display:inline">
          <?php if ($selectedCat): ?><input type="hidden" name="category" value="<?= $selectedCat ?>"><?php endif; ?>
          <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
          <?php if ($homeService): ?><input type="hidden" name="home" value="1"><?php endif; ?>
          <select name="sort" class="sort-select" onchange="this.form.submit()">
            <option value="rating"  <?= $sortBy==='rating'  ? 'selected' : '' ?>>⭐ Top Rated</option>
            <option value="reviews" <?= $sortBy==='reviews' ? 'selected' : '' ?>>💬 Most Reviews</option>
            <option value="name"    <?= $sortBy==='name'    ? 'selected' : '' ?>>🔤 A – Z</option>
          </select>
        </form>
      </div>
    </div>

    <!-- Provider Grid -->
    <div class="provider-grid">
      <?php if (empty($providers)): ?>
        <div class="empty">
          <div class="icon">🔍</div>
          <p>No providers found. Try adjusting your filters.</p>
        </div>
      <?php else: ?>
        <?php foreach ($providers as $p):
          $slug   = $p['category_slug'] ?? 'services';
          $emoji  = catEmoji($slug, $catEmojiMap);
          $stars  = round($p['avg_rating'] * 2) / 2;
          $filled = floor($stars);
          $half   = ($stars - $filled) >= .5 ? 1 : 0;
        ?>
        <a href="<?= BASE_URL ?>providers/<?= $p['id'] ?>" class="provider-card">
          <div class="card-cover">
            <?= $emoji ?>
            <?php if ($p['avg_rating'] > 0): ?>
            <div class="card-cover-badge">⭐ <?= number_format($p['avg_rating'], 1) ?></div>
            <?php endif; ?>
            <?php if ($p['offers_home_service']): ?>
            <div class="home-badge">🏠 Home Service</div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="card-category"><?= htmlspecialchars($p['category_name'] ?? 'Services') ?></div>
            <div class="card-name"><?= htmlspecialchars($p['business_name']) ?></div>
            <div class="card-location">
              📍 <?= htmlspecialchars($p['barangay'] ? $p['barangay'].', ' : '') ?><?= htmlspecialchars($p['city']) ?>
            </div>
            <div class="card-meta">
              <div class="card-rating">
                <span class="star"><?= str_repeat('★', $filled) . ($half ? '½' : '') . str_repeat('☆', 5 - $filled - $half) ?></span>
                <span><?= number_format($p['avg_rating'], 1) ?></span>
                <span class="reviews">(<?= $p['total_reviews'] ?>)</span>
              </div>
              <div class="card-price">
                from <strong>₱<?= $p['min_price'] ? number_format($p['min_price'], 0) : '—' ?></strong>
              </div>
            </div>
          </div>
          <div class="card-footer">
            <span class="services-count">🛠 <?= $p['service_count'] ?> service<?= $p['service_count'] != 1 ? 's' : '' ?></span>
            <span class="book-btn">View →</span>
          </div>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

</body>
</html>