<?php
// app/views/customer/provider-profile.php — REDESIGNED: Business-Focused Profile
// QuickBook Design System — Cream & Gold Edition

require_once __DIR__ . '/../../../config/database.php';
$db         = Database::getInstance();
$customerId = (int)($_SESSION['user_id']  ?? 0);
$userName   = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$initials   = strtoupper(substr($userName, 0, 2));

$providerId = (int)($id ?? 0);

// ── Fetch provider ─────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT pp.*, u.first_name, u.last_name, u.email, u.avatar_url,
           c.name as category_name, c.slug as category_slug, c.id as category_id
    FROM tbl_provider_profiles pp
    JOIN tbl_users u ON pp.user_id = u.id
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    WHERE pp.id = ? AND pp.is_approved = 1 AND u.is_active = 1
");
$stmt->execute([$providerId]);
$provider = $stmt->fetch();

if (!$provider) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider not found.'];
    header('Location: ' . BASE_URL . 'browse'); exit;
}

// ── Portfolio works (from tbl_portfolio — uploaded by provider) ────────────────
try {
    $galStmt = $db->prepare("
        SELECT p.id, p.title, p.caption, p.image_url, p.extra_images, p.before_url, p.after_url,
               p.is_featured, p.is_before_after, p.price, p.views, p.likes,
               s.name AS service_name
        FROM tbl_portfolio p
        LEFT JOIN tbl_services s ON s.id = p.service_id
        WHERE p.provider_id = ?
        ORDER BY p.is_featured DESC, p.created_at DESC
    ");
    $galStmt->execute([$providerId]);
    $galleryPhotos = $galStmt->fetchAll();
} catch (\Exception $e) { $galleryPhotos = []; }

// ── Services ───────────────────────────────────────────────────
$svcStmt = $db->prepare("
    SELECT * FROM tbl_services
    WHERE provider_id = ? AND is_active = 1 ORDER BY price ASC
");
$svcStmt->execute([$providerId]);
$services = $svcStmt->fetchAll();

// ── Availability ───────────────────────────────────────────────
$avStmt = $db->prepare("
    SELECT * FROM tbl_provider_availability
    WHERE provider_id = ? AND is_available = 1
    ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')
");
$avStmt->execute([$providerId]);
$availability = $avStmt->fetchAll();

// ── All Reviews (with service name via service_id) ─────────────
$revStmt = $db->prepare("
    SELECT r.*, u.first_name, u.last_name, u.avatar_url,
           s.name AS service_name
    FROM tbl_reviews r
    JOIN tbl_users u ON r.customer_id = u.id
    LEFT JOIN tbl_services s ON r.service_id = s.id
    WHERE r.provider_id = ? AND r.is_visible = 1
    ORDER BY r.created_at DESC
");
$revStmt->execute([$providerId]);
$allReviews = $revStmt->fetchAll();

// Rating breakdown
$ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($allReviews as $rev) {
    $star = max(1, min(5, (int)round($rev['rating'])));
    $ratingBreakdown[$star]++;
}

// ── Completed bookings ─────────────────────────────────────────
$cmpStmt = $db->prepare("
    SELECT COUNT(*) FROM tbl_bookings
    WHERE provider_id = ? AND status = 'completed' AND deleted_at IS NULL
");
$cmpStmt->execute([$providerId]);
$completedCount = (int)$cmpStmt->fetchColumn();

// ── Favorites check & count ────────────────────────────────────
$isFavStmt = $db->prepare("SELECT id FROM tbl_provider_favorites WHERE customer_id = ? AND provider_id = ?");
$isFavStmt->execute([$customerId, $providerId]);
$isFavorited = (bool)$isFavStmt->fetchColumn();

$favCntStmt = $db->prepare("SELECT COUNT(*) FROM tbl_provider_favorites WHERE provider_id = ?");
$favCntStmt->execute([$providerId]);
$favoriteCount = (int)$favCntStmt->fetchColumn();

// ── Nav data ───────────────────────────────────────────────────
$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$customerId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$loyaltyTier   = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};

$stUp = $db->prepare("
    SELECT COUNT(*) FROM tbl_bookings
    WHERE customer_id = ? AND status IN ('pending','confirmed')
      AND booking_date >= CURDATE() AND deleted_at IS NULL
");
$stUp->execute([$customerId]);
$upcomingCount = (int)$stUp->fetchColumn();

$stAv = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAv->execute([$customerId]);
$navAvatar = $stAv->fetchColumn() ?: null;

// ── Flash ──────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Helpers ────────────────────────────────────────────────────
$catEmojiMap = [
    'barbershop'       => '✂️',  'hair-salon'       => '💇',
    'nail-care'        => '💅',  'massage-therapy'  => '💆',
    'skincare-facial'  => '🧴',  'fitness-training' => '🏋️',
    'home-cleaning'    => '🧹',  'pet-grooming'     => '🐾',
    'event-styling'    => '🎨',  'dental'           => '🦷',
    'makeup'           => '💄',
];
$catEmoji = $catEmojiMap[$provider['category_slug'] ?? ''] ?? '🛠️';

$catAccentMap = [
    'barbershop'       => '#2563EB',
    'hair-salon'       => '#7C3AED',
    'nail-care'        => '#DB2777',
    'massage-therapy'  => '#16A34A',
    'skincare-facial'  => '#D97706',
    'fitness-training' => '#EA580C',
    'home-cleaning'    => '#0D9488',
    'pet-grooming'     => '#C9A84C',
    'event-styling'    => '#6366F1',
    'dental'           => '#0EA5E9',
    'makeup'           => '#EC4899',
];
$catAccent = $catAccentMap[$provider['category_slug'] ?? ''] ?? '#C9A84C';

function renderStars(float $r): string {
    $full = (int)floor($r);
    $half = ($r - $full) >= .5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $out = '';
    for ($i = 0; $i < $full; $i++)  $out .= '<i class="fa-solid fa-star" style="color:#f59e0b"></i>';
    if ($half)                       $out .= '<i class="fa-solid fa-star-half-stroke" style="color:#f59e0b"></i>';
    for ($i = 0; $i < $empty; $i++) $out .= '<i class="fa-regular fa-star" style="color:#f59e0b"></i>';
    return $out;
}

function isOpenNow(?string $hoursJson): ?bool {
    if (!$hoursJson) return null;
    $hours = json_decode($hoursJson, true);
    if (!$hours) return null;
    $dayKey = strtolower(date('D'));
    $day    = $hours[$dayKey] ?? null;
    if (!$day || empty($day['open']) || empty($day['close'])) return null;
    $now = strtotime(date('H:i'));
    return $now >= strtotime($day['open']) && $now <= strtotime($day['close']);
}

$todayName  = date('l');
$todayAvail = null;
foreach ($availability as $av) {
    if ($av['day_of_week'] === $todayName) { $todayAvail = $av; break; }
}

$openStatus = isOpenNow($provider['business_hours'] ?? null);
if ($openStatus === null && $todayAvail) {
    $nowTs      = strtotime(date('H:i'));
    $openStatus = $nowTs >= strtotime($todayAvail['start_time'])
               && $nowTs <= strtotime($todayAvail['end_time']);
}

$daysOfWeek = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$todayIdx   = (int)(date('N')) - 1;
$availDays  = array_column($availability, 'day_of_week');
$nextAvail  = null;
for ($i = 1; $i <= 7; $i++) {
    $checkDay = $daysOfWeek[($todayIdx + $i) % 7];
    if (in_array($checkDay, $availDays)) {
        foreach ($availability as $av) {
            if ($av['day_of_week'] === $checkDay) { $nextAvail = $av; break 2; }
        }
    }
}

$locTypes   = $provider['location_types_offered'] ?? 'In-shop';
$offersHome = (int)$provider['offers_home_service'] === 1;
$offersShop = strpos($locTypes, 'In-shop') !== false || strpos($locTypes, 'Flexible') !== false;
$showToggle = $offersHome && $offersShop;
$isVerified = !empty($provider['is_verified']);
$minPrice   = $services ? min(array_column($services, 'price')) : null;

$phone     = $provider['phone'] ?? $provider['contact_number'] ?? '';
$email     = $provider['email'] ?? '';
$website   = $provider['website'] ?? '';
$instagram = $provider['instagram'] ?? $provider['social_instagram'] ?? '';
$facebook  = $provider['facebook'] ?? $provider['social_facebook'] ?? '';

$colorAccents = [
    'blue'   => '#2563EB', 'purple' => '#7C3AED', 'pink'   => '#DB2777',
    'green'  => '#16A34A', 'yellow' => '#D97706', 'orange' => '#EA580C',
    'teal'   => '#0D9488', 'gold'   => '#C9A84C',
];
$serviceTypeColors = [
    'Barber'       => 'blue',   'Hair Stylist' => 'purple',
    'Nail Tech'    => 'pink',   'Massage'      => 'green',
    'Facial'       => 'yellow', 'Trainer'      => 'orange',
    'Cleaner'      => 'teal',   'Pet Groomer'  => 'gold',
];
$locIcons = [
    'In-shop'  => '<svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M2 5.5L6 1.5L10 5.5V10.5H7.5V7.5H4.5V10.5H2Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" fill="none"/></svg>',
    'On-site'  => '<svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M6 1C4.07 1 2.5 2.57 2.5 4.5c0 2.8 3.5 6.5 3.5 6.5s3.5-3.7 3.5-6.5C9.5 2.57 7.93 1 6 1z" stroke="currentColor" stroke-width="1.3"/><circle cx="6" cy="4.5" r="1.2" stroke="currentColor" stroke-width="1.2"/></svg>',
    'Flexible' => '<svg width="11" height="11" viewBox="0 0 12 12" fill="none"><path d="M2 6h2.5M7.5 6H10M6 2v2.5M6 7.5V10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="6" cy="6" r="1.5" stroke="currentColor" stroke-width="1.3"/></svg>',
    'Remote'   => '<svg width="11" height="11" viewBox="0 0 12 12" fill="none"><rect x="1.5" y="3" width="9" height="6" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M4 9.5L6 11L8 9.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
];

$addrParts   = array_filter([$provider['address'] ?? '', $provider['barangay'] ?? '', $provider['city'] ?? '']);
$fullAddress = implode(', ', $addrParts);
$mapQuery    = urlencode($fullAddress ?: ($provider['city'] ?? 'Bacolod City'));
$lat         = $provider['latitude']  ?? null;
$lng         = $provider['longitude'] ?? null;
$hasCoords   = $lat && $lng;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — <?= htmlspecialchars($provider['business_name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_provider_profile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" defer></script>
  <style>
    /* ══════════════════════════════════════
       CATEGORY ACCENT VARIABLE
    ══════════════════════════════════════ */
    :root {
      --cat-accent: <?= $catAccent ?>;
      --cat-accent-soft: <?= $catAccent ?>22;
      --cat-accent-border: <?= $catAccent ?>55;
    }

    /* ══════════════════════════════════════
       BACK BUTTON — NAV TOP-LEFT
    ══════════════════════════════════════ */
    .pp-nav-back-btn {
      display: inline-flex; align-items: center; gap: .45rem;
      padding: .42rem 1.05rem; border-radius: var(--r-md);
      background: var(--gold-lt); border: 1.5px solid var(--gold-border-md);
      color: var(--gold-dim); font-family: var(--font-mono); font-size: .65rem;
      font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
      text-decoration: none; transition: all .2s var(--ease-out); flex-shrink: 0;
      white-space: nowrap;
    }
    .pp-nav-back-btn:hover {
      background: var(--gold-soft-md); border-color: var(--gold-border-lg);
      transform: translateX(-2px);
    }
    [data-theme="dark"] .pp-nav-back-btn {
      background: rgba(201,168,76,.12); border-color: rgba(201,168,76,.28); color: var(--gold);
    }
    .pp-nav-back-btn svg { flex-shrink: 0; }

    /* ══════════════════════════════════════
       HERO — BUSINESS-FIRST
    ══════════════════════════════════════ */
    .pp-hero-category-badge {
      display: inline-flex; align-items: center; gap: .45rem;
      background: var(--cat-accent-soft);
      border: 1.5px solid var(--cat-accent-border);
      color: var(--cat-accent);
      font-family: var(--font-mono); font-size: .63rem; font-weight: 700;
      letter-spacing: .1em; text-transform: uppercase;
      padding: .3rem .85rem; border-radius: 99px;
      box-shadow: 0 2px 8px <?= $catAccent ?>30;
    }
    .pp-hero-category-badge .cat-dot {
      width: 6px; height: 6px; border-radius: 99px;
      background: var(--cat-accent); flex-shrink: 0;
      animation: pp-pulse 1.8s ease infinite;
    }
    .pp-hero-business-name {
      font-family: var(--font-display);
      font-size: clamp(1.8rem, 4vw, 2.9rem);
      font-weight: 700; letter-spacing: -.035em;
      color: var(--text-primary); line-height: 1.0;
      margin: .45rem 0 .25rem;
    }
    .pp-hero-provider-byline {
      display: flex; align-items: center; gap: .45rem;
      font-family: var(--font-mono); font-size: .7rem;
      color: var(--text-dim); letter-spacing: .04em;
      margin-bottom: .65rem;
    }
    .pp-hero-provider-byline .byline-sep {
      width: 3px; height: 3px; border-radius: 99px;
      background: var(--text-faint); flex-shrink: 0;
    }
    .pp-verified-badge {
      display: inline-flex; align-items: center; gap: .32rem;
      font-family: var(--font-mono); font-size: .62rem; font-weight: 700;
      letter-spacing: .06em; text-transform: uppercase;
      background: var(--gold-lt); color: var(--gold-dim);
      border: 1.5px solid var(--gold-border-md);
      padding: .26rem .7rem; border-radius: 99px;
    }
    .pp-open-badge {
      display: inline-flex; align-items: center; gap: .32rem;
      font-family: var(--font-mono); font-size: .62rem; font-weight: 700;
      letter-spacing: .06em; text-transform: uppercase;
      padding: .26rem .7rem; border-radius: 99px;
    }
    .pp-open-badge.open  { background: rgba(22,163,74,.12); color: var(--green); border: 1.5px solid rgba(22,163,74,.30); }
    .pp-open-badge.closed{ background: rgba(220,38,38,.10); color: var(--red);   border: 1.5px solid rgba(220,38,38,.25); }
    .pp-open-dot { width: 6px; height: 6px; border-radius: 99px; flex-shrink: 0; }
    .open  .pp-open-dot { background: var(--green); }
    .closed .pp-open-dot{ background: var(--red); }
    .pv-hero-badge-row {
      display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: .7rem;
    }

    /* Hero business avatar */
    .pv-provider-av {
      width: 96px; height: 96px; border-radius: var(--r-lg); flex-shrink: 0;
      background: linear-gradient(135deg, var(--cat-accent-soft), var(--gold-lt));
      border: 2.5px solid var(--cat-accent-border);
      display: flex; align-items: center; justify-content: center;
      font-family: var(--font-display); font-size: 2.2rem; font-weight: 700; color: var(--cat-accent);
      box-shadow: 0 8px 28px <?= $catAccent ?>33, 0 0 0 4px var(--cat-accent-soft);
      overflow: hidden; position: relative;
    }
    .pv-provider-av img { width:100%; height:100%; object-fit:cover; border-radius:inherit; display:block; }
    .pv-provider-av-ring {
      position: absolute; inset: -5px;
      border-radius: calc(var(--r-lg) + 5px);
      border: 1.5px solid var(--cat-accent-border);
      pointer-events: none; animation: ring-spin 8s linear infinite;
      background: transparent;
    }
    @keyframes ring-spin { to { transform: rotate(360deg); } }

    /* Hero CTA row */
    .pv-hero-cta-row {
      display: flex; align-items: center; gap: .75rem; margin-top: 1rem; flex-wrap: wrap;
    }
    .pp-hero-book-btn {
      display: inline-flex; align-items: center; gap: .5rem;
      padding: .75rem 2rem; border-radius: var(--r-md);
      background: linear-gradient(135deg, var(--gold-dim), var(--gold), #e8c060);
      color: #140e00; font-family: var(--font-display); font-size: .95rem; font-weight: 700;
      letter-spacing: -.01em; text-decoration: none;
      box-shadow: 0 4px 20px rgba(201,168,76,.40), 0 1px 0 rgba(255,255,255,.25) inset;
      transition: transform .2s var(--ease-out), box-shadow .2s, filter .15s;
      white-space: nowrap;
    }
    .pp-hero-book-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(201,168,76,.55);
      filter: brightness(1.06);
    }

    /* Hero action buttons */
    .pp-hero-action-btn {
      display: inline-flex; align-items: center; gap: .45rem;
      padding: .62rem 1.1rem; border-radius: var(--r-md);
      background: rgba(255,255,255,.55); color: var(--text-primary);
      font-family: var(--font-body); font-size: .82rem; font-weight: 500;
      border: 1.5px solid rgba(255,255,255,.70); backdrop-filter: blur(12px);
      text-decoration: none; white-space: nowrap; cursor: pointer;
      transition: background .2s, border-color .2s, transform .15s var(--ease-out);
    }
    [data-theme="dark"] .pp-hero-action-btn {
      background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.15);
      color: var(--text-muted);
    }
    .pp-hero-action-btn:hover {
      background: rgba(255,255,255,.80); border-color: var(--gold-border);
      transform: translateY(-1px);
    }
    .pp-hero-action-btn.fav-active {
      background: rgba(220,38,38,.10); border-color: rgba(220,38,38,.30); color: var(--red);
    }
    .pp-hero-action-btn.fav-active:hover { background: rgba(220,38,38,.18); }

    /* Hero bio */
    .pp-hero-bio {
      font-size: .84rem; color: var(--text-muted); max-width: 600px;
      line-height: 1.65; margin-top: .7rem;
      border-left: 2.5px solid var(--cat-accent-border);
      padding-left: .9rem;
    }

    /* ── QUICK STATS ── */
    .pv-provider-quick-stats { flex-wrap: nowrap; }
    .pv-qs-item { min-width: 90px; }
    .pv-qs-val--accent { color: var(--cat-accent); }

    /* ══════════════════════════════════════
       SECTION HEADER
    ══════════════════════════════════════ */
    .pp-section-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 1.2rem 1.5rem .9rem;
      border-bottom: 1px solid var(--border);
      background: linear-gradient(180deg, var(--gold-lt) 0%, transparent 100%);
    }
    .pp-section-title {
      font-family: var(--font-display); font-size: 1.05rem; font-weight: 700;
      letter-spacing: -.02em; color: var(--text-primary);
      display: flex; align-items: center; gap: .6rem;
    }
    .pp-section-title-icon {
      width: 30px; height: 30px; border-radius: var(--r-sm);
      background: var(--cat-accent-soft); border: 1px solid var(--cat-accent-border);
      display: flex; align-items: center; justify-content: center;
      font-size: .85rem; flex-shrink: 0;
    }
    .pp-section-badge {
      font-family: var(--font-mono); font-size: .6rem; font-weight: 700;
      background: var(--surface-md); border: 1px solid var(--border);
      padding: .12rem .48rem; border-radius: 99px; color: var(--text-dim);
    }

    /* ══════════════════════════════════════
       TABS
    ══════════════════════════════════════ */
    .pp-tabs {
      display: flex; gap: 2px; padding: 1rem 1.3rem .2rem;
      border-bottom: 1.5px solid var(--card-border);
      overflow-x: auto; -webkit-overflow-scrolling: touch;
    }
    .pp-tab {
      display: flex; align-items: center; gap: .42rem; flex-shrink: 0;
      padding: .52rem 1rem; border-radius: var(--r-sm);
      font-family: var(--font-body); font-size: .82rem; font-weight: 500;
      color: var(--text-muted); background: transparent; border: none;
      cursor: pointer; transition: all .2s; position: relative;
    }
    .pp-tab:hover { color: var(--text-primary); background: var(--surface-md); }
    .pp-tab.active { color: var(--cat-accent); background: var(--cat-accent-soft); font-weight: 600; }
    .pp-tab.active::after {
      content: ''; position: absolute; bottom: -1px; left: .75rem; right: .75rem;
      height: 2px; background: var(--cat-accent); border-radius: 99px;
    }
    .pp-tab-count {
      font-family: var(--font-mono); font-size: .6rem;
      background: var(--surface-md); border: 1px solid var(--border);
      padding: .1rem .42rem; border-radius: 99px; color: var(--text-dim);
    }
    .pp-tab.active .pp-tab-count {
      background: var(--cat-accent-soft); border-color: var(--cat-accent-border); color: var(--cat-accent);
    }
    .pp-panel { padding: 1.25rem 1.3rem 1.4rem; }
    .pp-panel--hidden { display: none; }

    /* ══════════════════════════════════════
       MASONRY GALLERY
    ══════════════════════════════════════ */
    .pp-masonry {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      grid-template-rows: repeat(3, 130px);
      gap: 8px;
    }
    .pp-masonry-item {
      border-radius: 10px; overflow: hidden; cursor: pointer; position: relative;
      background: var(--surface); border: 1.5px solid var(--card-border);
      transition: transform .2s var(--ease-out), box-shadow .2s;
    }
    .pp-masonry-item:hover { transform: scale(1.02); box-shadow: var(--shadow-md); }
    .pp-masonry-featured { grid-column: span 2; grid-row: span 2; }
    .pp-masonry-item img {
      width: 100%; height: 100%; object-fit: cover; display: block;
      transition: transform .3s var(--ease-out);
    }
    .pp-masonry-item:hover img { transform: scale(1.06); }
    .pp-masonry-overlay {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: linear-gradient(0deg, rgba(0,0,0,.72) 0%, transparent 100%);
      padding: .65rem .7rem .55rem;
      opacity: 0; transition: opacity .22s;
      display: flex; flex-direction: column; gap: .18rem;
    }
    .pp-masonry-item:hover .pp-masonry-overlay { opacity: 1; }
    .pp-masonry-service-tag {
      font-size: .6rem; font-weight: 600; letter-spacing: .06em;
      color: #E8C96A; font-family: var(--font-mono);
      text-transform: uppercase;
    }
    .pp-masonry-title {
      font-size: .72rem; font-weight: 700; color: #fff;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pp-masonry-caption {
      font-size: .62rem; color: rgba(255,255,255,.78); font-family: var(--font-mono);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pp-masonry-zoom {
      position: absolute; top: .5rem; right: .5rem;
      background: rgba(0,0,0,.4); border-radius: 50%;
      width: 28px; height: 28px; display: flex; align-items: center;
      justify-content: center; opacity: 0; transition: opacity .2s;
    }
    .pp-masonry-item:hover .pp-masonry-zoom { opacity: 1; }
    .pp-masonry-more { background: var(--surface-md); }
    .pp-masonry-more img { opacity: .3; }
    .pp-masonry-more-label {
      position: absolute; inset: 0;
      display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .2rem;
    }
    .pp-masonry-more-label span:first-child {
      font-family: var(--font-display); font-size: 1.5rem; font-weight: 700; color: var(--gold-dim);
    }
    .pp-masonry-more-label span:last-child {
      font-family: var(--font-mono); font-size: .58rem; color: var(--text-dim);
      letter-spacing: .08em; text-transform: uppercase;
    }
    .pp-gallery-empty {
      background: var(--surface); border: 1.5px dashed var(--border-md);
      border-radius: 12px; padding: 2.5rem; text-align: center;
      font-size: .8rem; color: var(--text-faint); font-family: var(--font-mono);
      letter-spacing: .06em;
    }
    .pp-gallery-all-btn {
      display: inline-flex; align-items: center; gap: .4rem;
      margin-top: 1rem; padding: .48rem 1.1rem; border-radius: var(--r-sm);
      background: var(--surface-md); border: 1px solid var(--border);
      color: var(--text-muted); font-size: .78rem; font-weight: 500;
      cursor: pointer; transition: all .2s;
    }
    .pp-gallery-all-btn:hover { background: var(--gold-lt); border-color: var(--gold-border); color: var(--gold-dim); }

    /* ══════════════════════════════════════
       SERVICE CARDS
    ══════════════════════════════════════ */
    .pp-svc-category-header {
      display: flex; align-items: center; gap: .65rem;
      padding: .9rem 1.3rem .6rem;
      font-family: var(--font-mono); font-size: .62rem; letter-spacing: .1em;
      text-transform: uppercase; color: var(--text-dim);
    }
    .pp-svc-category-line { flex: 1; height: 1px; background: var(--border); }
    .pp-svc-grid { display: flex; flex-direction: column; gap: 10px; }
    .pp-svc-item {
      border-radius: var(--r-md); padding: 1.1rem 1.15rem;
      background: var(--surface); border: 1.5px solid var(--card-border);
      transition: border-color .2s, box-shadow .2s, background .2s;
      position: relative; overflow: hidden;
    }
    .pp-svc-item::before {
      content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3.5px;
      background: var(--accent, var(--cat-accent)); border-radius: 2px 0 0 2px;
    }
    .pp-svc-item:hover {
      border-color: var(--accent, var(--cat-accent-border));
      box-shadow: 0 4px 20px rgba(0,0,0,.07);
      background: var(--card-bg);
    }
    .pp-svc-item-top { display: flex; align-items: flex-start; gap: .9rem; margin-bottom: .8rem; }
    .pp-svc-item-icon {
      width: 42px; height: 42px; border-radius: var(--r-sm); flex-shrink: 0;
      background: var(--cat-accent-soft); border: 1px solid var(--cat-accent-border);
      display: flex; align-items: center; justify-content: center; font-size: 1.15rem;
    }
    .pp-svc-item-info { flex: 1; min-width: 0; }
    .pp-svc-item-name {
      font-family: var(--font-display); font-size: .97rem; font-weight: 600;
      color: var(--text-primary); line-height: 1.3; margin-bottom: .22rem;
    }
    .pp-svc-item-desc {
      font-size: .78rem; color: var(--text-muted); line-height: 1.5;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .pp-svc-item-price { flex-shrink: 0; text-align: right; }
    .pp-svc-item-price-val {
      font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;
      color: var(--accent, var(--cat-accent));
    }
    .pp-svc-item-bottom {
      display: flex; align-items: center; justify-content: space-between;
      gap: .65rem; flex-wrap: wrap;
    }
    .pp-svc-item-tags { display: flex; gap: .35rem; flex-wrap: wrap; align-items: center; }
    .pp-svc-tag {
      display: inline-flex; align-items: center; gap: .3rem;
      font-family: var(--font-mono); font-size: .6rem; letter-spacing: .06em;
      text-transform: uppercase; padding: .22rem .55rem; border-radius: 99px; border: 1px solid;
      white-space: nowrap;
    }
    .pp-svc-tag--type { background: var(--surface-md); border-color: var(--border); color: var(--text-muted); }
    .pp-svc-tag--loc  { background: var(--surface-md); border-color: var(--border); color: var(--text-muted); }
    .pp-svc-tag--dur  { background: var(--blue-soft);  border-color: var(--blue-border); color: var(--blue); }
    .pp-svc-book-btn {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .5rem 1.1rem; border-radius: var(--r-sm);
      background: linear-gradient(135deg, var(--gold-dim), var(--gold));
      color: #140e00; font-size: .78rem; font-weight: 700;
      text-decoration: none; white-space: nowrap; flex-shrink: 0;
      transition: filter .18s, transform .15s var(--ease-out), box-shadow .18s;
      box-shadow: 0 2px 8px rgba(201,168,76,.25);
    }
    .pp-svc-book-btn:hover {
      filter: brightness(1.08); transform: translateY(-1px);
      box-shadow: 0 5px 16px rgba(201,168,76,.40);
    }
    .pp-svc-empty {
      padding: 2.5rem 1.5rem; text-align: center;
      display: flex; flex-direction: column; align-items: center; gap: .6rem;
    }

    /* ══════════════════════════════════════
       REVIEWS TAB
    ══════════════════════════════════════ */
    .pp-rating-summary {
      display: flex; align-items: center; gap: 2rem;
      padding: .25rem 0 1.2rem; border-bottom: 1px solid var(--border); margin-bottom: 1.1rem;
      flex-wrap: wrap;
    }
    .pp-rating-big-block { text-align: center; flex-shrink: 0; min-width: 90px; }
    .pp-rating-big-num {
      font-family: var(--font-display); font-size: 3.4rem; font-weight: 700;
      color: var(--gold-dim); line-height: 1; letter-spacing: -.05em;
    }
    .pp-rating-big-stars { margin: .35rem 0 .25rem; font-size: .85rem; gap: 2px; display: flex; align-items: center; justify-content: center; }
    .pp-rating-big-count { font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim); letter-spacing: .04em; }
    .pp-rating-bars { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: .42rem; }
    .pp-rating-bar-row { display: flex; align-items: center; gap: .6rem; }
    .pp-rating-bar-label {
      font-family: var(--font-mono); font-size: .62rem; color: var(--text-dim);
      display: flex; align-items: center; gap: .22rem; width: 36px; flex-shrink: 0;
    }
    .pp-rating-bar-track {
      flex: 1; height: 7px; background: var(--surface-md); border-radius: 99px; overflow: hidden;
    }
    .pp-rating-bar-fill {
      height: 100%; background: linear-gradient(90deg, var(--gold-dim), var(--gold));
      border-radius: 99px; transition: width .6s var(--ease-out);
    }
    .pp-rating-bar-num {
      font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim);
      width: 18px; text-align: right; flex-shrink: 0;
    }
    .pp-review-list { display: flex; flex-direction: column; gap: 0; }
    .pp-review-item { padding: 1.1rem 0; border-bottom: 1px solid var(--border); }
    .pp-review-item:last-child { border-bottom: none; }
    .pp-review-head { display: flex; align-items: center; gap: .7rem; margin-bottom: .5rem; }
    .pp-review-av {
      width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
      background: var(--gold-lt); border: 1.5px solid var(--gold-border);
      color: var(--gold-dim); font-family: var(--font-display); font-weight: 700; font-size: .7rem;
      display: flex; align-items: center; justify-content: center; overflow: hidden;
    }
    .pp-review-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .pp-review-name { font-size: .86rem; font-weight: 600; color: var(--text-primary); }
    .pp-review-date { font-family: var(--font-mono); font-size: .6rem; color: var(--text-faint); margin-top: .05rem; }
    .pp-review-stars { display: flex; align-items: center; gap: 2px; margin-left: auto; }
    .pp-review-text { font-size: .83rem; color: var(--text-muted); line-height: 1.62; margin-bottom: .4rem; font-style: italic; }
    .pp-review-service {
      display: inline-flex; align-items: center; gap: .3rem;
      font-family: var(--font-mono); font-size: .6rem; color: var(--cat-accent);
      background: var(--cat-accent-soft); border: 1px solid var(--cat-accent-border);
      padding: .12rem .55rem; border-radius: 99px;
    }
    .pp-review-reply {
      margin-top: .65rem; padding: .75rem 1rem;
      background: var(--surface); border: 1px solid var(--border);
      border-left: 3px solid var(--cat-accent-border);
      border-radius: 0 var(--r-sm) var(--r-sm) 0;
    }
    .pp-review-reply-header {
      display: flex; align-items: center; gap: .4rem;
      font-family: var(--font-mono); font-size: .62rem; font-weight: 700;
      color: var(--cat-accent); text-transform: uppercase; letter-spacing: .06em;
      margin-bottom: .35rem;
    }
    .pp-review-reply-text { font-size: .8rem; color: var(--text-muted); line-height: 1.55; }
    .pp-reviews-empty {
      padding: 2.5rem 1.5rem; text-align: center;
      display: flex; flex-direction: column; align-items: center; gap: .6rem;
    }

    /* ══════════════════════════════════════
       BOOKING TYPE TOGGLE
    ══════════════════════════════════════ */
    .pp-loc-toggle-wrap {
      background: var(--card-bg); border: 1.5px solid var(--card-border);
      border-radius: var(--r-lg); padding: 1.1rem 1.3rem; margin-bottom: 1.5rem;
      box-shadow: var(--shadow-sm);
    }
    .pp-loc-toggle-label {
      font-size: .72rem; font-family: var(--font-mono); letter-spacing: .1em;
      text-transform: uppercase; color: var(--text-dim); margin-bottom: .7rem; display: block;
    }
    .pp-loc-toggle { display: flex; gap: .5rem; }
    .pp-loc-btn {
      flex: 1; padding: .65rem 1rem; border-radius: var(--r-sm);
      border: 1.5px solid var(--border-md); background: transparent;
      font-family: var(--font-body); font-size: .84rem; font-weight: 500;
      color: var(--text-muted); cursor: pointer; text-align: center;
      transition: all .2s var(--ease-out);
      display: flex; align-items: center; justify-content: center; gap: .45rem;
    }
    .pp-loc-btn:hover { border-color: var(--gold-border-md); color: var(--text-primary); background: var(--surface); }
    .pp-loc-btn.active { background: var(--gold-lt); border-color: var(--gold-border-md); color: var(--gold-dim); font-weight: 600; }
    .pp-loc-btn.active.home-active { background: var(--green-soft); border-color: var(--green-border); color: var(--green); }
    .pp-home-addr-row { margin-top: .75rem; display: none; animation: ppFadeIn .2s ease; }
    .pp-home-addr-row.visible { display: block; }
    .pp-home-addr-input {
      width: 100%; padding: .65rem .9rem;
      background: var(--surface); border: 1.5px solid var(--border-md);
      border-radius: var(--r-sm); font-family: var(--font-body); font-size: .84rem;
      color: var(--text-primary); outline: none; transition: border-color .2s, box-shadow .2s;
    }
    .pp-home-addr-input:focus { border-color: var(--gold-border-md); box-shadow: 0 0 0 3px var(--gold-lt); }
    .pp-home-addr-input::placeholder { color: var(--text-faint); }
    @keyframes ppFadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }

    /* ══════════════════════════════════════
       SIDEBAR — QUICK BOOK CARD
    ══════════════════════════════════════ */
    .pp-quickbook-card {
      background: linear-gradient(160deg, #1C1710 0%, #2A2010 60%, #1C1710 100%);
      border: 1.5px solid var(--gold-border-md);
      border-radius: var(--r-xl); overflow: hidden;
      box-shadow: 0 8px 28px rgba(0,0,0,.20), 0 0 0 1px rgba(201,168,76,.10);
      position: relative;
    }
    .pp-quickbook-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, transparent, var(--gold), transparent);
    }
    [data-theme="dark"] .pp-quickbook-card {
      background: linear-gradient(160deg, #0A0D14 0%, #111520 60%, #0A0D14 100%);
    }
    .pp-qb-body { padding: 1.4rem 1.3rem; }
    .pp-qb-title {
      font-family: var(--font-display); font-size: 1.05rem; font-weight: 700; font-style: italic;
      color: #EDE3CC; margin-bottom: .85rem; letter-spacing: -.01em;
    }
    .pp-qb-facts { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.1rem; }
    .pp-qb-fact {
      display: flex; align-items: center; gap: .6rem;
      font-size: .8rem; color: rgba(237,227,204,.65);
    }
    .pp-qb-fact-ico { width: 22px; text-align: center; flex-shrink: 0; font-size: .82rem; }
    .pp-qb-fact strong { color: #EDE3CC; font-weight: 600; }
    .pp-qb-fact .qb-verified { color: var(--gold); }
    .pp-qb-fact .qb-open    { color: var(--green); }
    .pp-qb-fact .qb-closed  { color: #ef4444; }
    .pp-qb-cta {
      display: block; width: 100%; padding: .85rem 1.25rem; text-align: center;
      background: linear-gradient(135deg, var(--gold-dim), var(--gold), #e8c060);
      color: #140e00; font-family: var(--font-display); font-size: .95rem; font-weight: 700;
      border-radius: var(--r-md); text-decoration: none;
      box-shadow: 0 4px 20px rgba(201,168,76,.35), 0 1px 0 rgba(255,255,255,.25) inset;
      transition: transform .2s var(--ease-out), box-shadow .2s, filter .15s;
      letter-spacing: -.01em;
    }
    .pp-qb-cta:hover {
      transform: translateY(-2px); filter: brightness(1.06);
      box-shadow: 0 8px 28px rgba(201,168,76,.50);
    }

    /* ── Sidebar action buttons ── */
    .pp-sidebar-actions {
      display: flex; gap: .55rem; margin-top: .9rem; flex-wrap: wrap;
    }
    .pp-sb-action-btn {
      flex: 1; min-width: 0; display: inline-flex; align-items: center; justify-content: center;
      gap: .35rem; padding: .55rem .75rem; border-radius: var(--r-sm);
      border: 1.5px solid var(--gold-border); background: rgba(201,168,76,.07);
      color: rgba(237,227,204,.75); font-family: var(--font-mono); font-size: .6rem;
      font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
      cursor: pointer; text-decoration: none; transition: all .2s; white-space: nowrap;
    }
    .pp-sb-action-btn:hover { background: rgba(201,168,76,.16); border-color: var(--gold-border-md); color: #EDE3CC; }
    .pp-sb-action-btn.fav-active { background: rgba(220,38,38,.15); border-color: rgba(220,38,38,.35); color: #ef4444; }

    /* ══════════════════════════════════════
       SIDEBAR — AVAILABILITY CARD
    ══════════════════════════════════════ */
    .pp-avail-today {
      padding: .95rem 1.25rem; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    }
    .pp-avail-today-label {
      font-family: var(--font-mono); font-size: .64rem; text-transform: uppercase;
      letter-spacing: .1em; color: var(--text-dim); margin-bottom: .18rem;
    }
    .pp-avail-today-hours {
      font-family: var(--font-display); font-size: .95rem; font-weight: 700;
      color: var(--text-primary); letter-spacing: -.01em;
    }
    .pp-avail-status-pill {
      display: inline-flex; align-items: center; gap: .3rem;
      font-family: var(--font-mono); font-size: .62rem; font-weight: 700;
      letter-spacing: .05em; text-transform: uppercase;
      padding: .28rem .7rem; border-radius: 99px; flex-shrink: 0;
    }
    .pp-avail-status-pill.open  { background: var(--green-soft); color: var(--green); border: 1px solid var(--green-border); }
    .pp-avail-status-pill.closed{ background: var(--red-soft);   color: var(--red);   border: 1px solid var(--red-border); }
    .pp-avail-status-dot { width: 5px; height: 5px; border-radius: 50%; }
    .open  .pp-avail-status-dot { background: var(--green); }
    .closed .pp-avail-status-dot{ background: var(--red); }
    .pp-next-avail {
      padding: .7rem 1.2rem; background: var(--surface);
      border-bottom: 1px solid var(--border);
      font-family: var(--font-mono); font-size: .64rem; color: var(--text-dim);
    }
    .pp-next-avail strong { color: var(--gold-dim); }

    /* ══════════════════════════════════════
       SIDEBAR — LEAFLET MAP CARD
    ══════════════════════════════════════ */
    .pp-map-card { overflow: hidden; padding: 0 !important; }
    .pp-map-card .pv-card-head { padding: 1.1rem 1.3rem .9rem; border-bottom: 1px solid var(--border); }
    .pp-map-wrap { position: relative; width: 100%; height: 220px; overflow: hidden; }
    #leafletMap { width: 100%; height: 100%; }
    .pp-map-fallback iframe {
      width: 100%; height: 220px; border: none; display: block;
      filter: saturate(.85) contrast(.95);
    }
    [data-theme="dark"] .pp-map-fallback iframe {
      filter: saturate(.7) contrast(.9) brightness(.85) hue-rotate(180deg);
    }
    .pp-map-addr { padding: .9rem 1.2rem 1.05rem; }
    .pp-map-addr-row {
      display: flex; align-items: flex-start; gap: .5rem;
      font-size: .8rem; color: var(--text-muted); margin-bottom: .38rem; line-height: 1.5;
    }
    .pp-map-home-row { color: var(--green); }
    .pp-map-addr-actions { display: flex; gap: .5rem; margin-top: .65rem; flex-wrap: wrap; }
    .pp-map-directions-btn {
      display: inline-flex; align-items: center; gap: .45rem;
      padding: .52rem 1rem; border-radius: var(--r-sm);
      background: var(--gold-lt); border: 1.5px solid var(--gold-border);
      color: var(--gold-dim); font-size: .78rem; font-weight: 600;
      text-decoration: none; transition: all .2s; flex: 1; justify-content: center;
    }
    .pp-map-directions-btn:hover { background: var(--gold-soft-md); border-color: var(--gold-border-md); }

    /* Distance badge */
    .pp-distance-badge {
      display: inline-flex; align-items: center; gap: .35rem;
      font-family: var(--font-mono); font-size: .6rem; font-weight: 700;
      background: var(--cat-accent-soft); border: 1px solid var(--cat-accent-border);
      color: var(--cat-accent); padding: .18rem .55rem; border-radius: 99px;
      letter-spacing: .04em;
    }

    /* ══════════════════════════════════════
       SIDEBAR — CONTACT CARD
    ══════════════════════════════════════ */
    .pp-contact-list { display: flex; flex-direction: column; }
    .pp-contact-item {
      display: flex; align-items: center; gap: .85rem;
      padding: .75rem 1.3rem; border-bottom: 1px solid var(--border);
      font-size: .81rem; color: var(--text-muted); transition: background .15s;
    }
    .pp-contact-item:last-child { border-bottom: none; }
    .pp-contact-item:hover { background: var(--surface); }
    .pp-contact-icon {
      width: 32px; height: 32px; border-radius: var(--r-sm); flex-shrink: 0;
      background: var(--cat-accent-soft); border: 1px solid var(--cat-accent-border);
      display: flex; align-items: center; justify-content: center;
      color: var(--cat-accent); font-size: .82rem;
    }
    .pp-contact-label {
      font-family: var(--font-mono); font-size: .58rem; letter-spacing: .08em;
      text-transform: uppercase; color: var(--text-dim); display: block; margin-bottom: .08rem;
    }
    .pp-contact-val { color: var(--text-primary); font-weight: 500; }
    .pp-contact-val a { color: var(--cat-accent); text-decoration: none; transition: opacity .15s; }
    .pp-contact-val a:hover { opacity: .75; }

    /* ══════════════════════════════════════
       BREADCRUMB & MISC
    ══════════════════════════════════════ */
    .pv-breadcrumb {
      display: flex; align-items: center; gap: .45rem; flex-wrap: wrap;
      font-family: var(--font-mono); font-size: .66rem; color: var(--text-dim);
      margin-bottom: 1rem;
    }
    .pv-breadcrumb a { color: var(--text-dim); transition: color .15s; }
    .pv-breadcrumb a:hover { color: var(--gold-dim); }
    .pv-breadcrumb span:last-child { color: var(--text-muted); }

    /* ══════════════════════════════════════
       LIGHTBOX
    ══════════════════════════════════════ */
    .pp-lightbox {
      display: none; position: fixed; inset: 0; z-index: 9999;
      background: rgba(0,0,0,.9); backdrop-filter: blur(10px);
      align-items: center; justify-content: center; padding: 1.5rem;
    }
    .pp-lightbox.open { display: flex; }
    .pp-lightbox-inner {
      position: relative; max-width: 880px; width: 100%;
      display: flex; flex-direction: column; align-items: center; gap: .75rem;
    }
    .pp-lightbox img {
      max-width: 100%; max-height: 80vh; border-radius: 12px; object-fit: contain;
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
    }
    .pp-lightbox-caption {
      font-family: var(--font-mono); font-size: .72rem;
      color: rgba(255,255,255,.55); text-align: center;
    }
    .pp-lightbox-close {
      position: absolute; top: -2.5rem; right: 0;
      color: rgba(255,255,255,.7); font-size: 1.5rem; cursor: pointer;
      width: 36px; height: 36px; display: flex; align-items: center;
      justify-content: center; border-radius: 50%; border: 1px solid rgba(255,255,255,.2);
      transition: all .15s;
    }
    .pp-lightbox-close:hover { color: #fff; background: rgba(255,255,255,.1); }
    .pp-lightbox-nav { display: flex; gap: .75rem; align-items: center; }
    .pp-lightbox-btn {
      width: 40px; height: 40px; border-radius: 50%;
      border: 1px solid rgba(255,255,255,.25); color: rgba(255,255,255,.75);
      background: rgba(255,255,255,.08); font-size: 1rem; cursor: pointer;
      display: flex; align-items: center; justify-content: center; transition: all .15s;
    }
    .pp-lightbox-btn:hover { background: rgba(255,255,255,.18); color: #fff; }
    .pp-lightbox-counter {
      font-family: var(--font-mono); font-size: .7rem; color: rgba(255,255,255,.45);
      min-width: 60px; text-align: center;
    }

    /* ══════════════════════════════════════
       SHARE TOAST
    ══════════════════════════════════════ */
    .pp-share-toast {
      position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(100px);
      background: var(--text-primary); color: var(--card-bg);
      font-family: var(--font-mono); font-size: .7rem; font-weight: 700; letter-spacing: .06em;
      padding: .65rem 1.4rem; border-radius: 99px;
      box-shadow: 0 8px 28px rgba(0,0,0,.25);
      transition: transform .3s var(--ease-out), opacity .3s; opacity: 0; z-index: 9998;
    }
    .pp-share-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }

    /* ══════════════════════════════════════
       NAV LOGOUT ICON
    ══════════════════════════════════════ */
    .pv-nav-logout-icon {
      width: 34px; height: 34px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      color: var(--text-dim); border: 1px solid transparent; font-size: 1.1rem;
      transition: color .2s, background .2s, border-color .2s, transform .15s; flex-shrink: 0;
    }
    .pv-nav-logout-icon:hover { color: var(--red); background: var(--red-soft); border-color: var(--red-border); transform: translateY(-1px); }

    /* ══════════════════════════════════════
       RESPONSIVE
    ══════════════════════════════════════ */
    @media (max-width: 680px) {
      .pp-masonry { grid-template-rows: repeat(3, 100px); }
      .pp-hero-book-btn { padding: .65rem 1.4rem; font-size: .86rem; }
      .pv-provider-quick-stats { flex-wrap: wrap; }
      .pv-qs-div:nth-child(4) { display: none; }
    }
    @media (max-width: 440px) {
      .pp-masonry { grid-template-columns: repeat(2,1fr); grid-template-rows: repeat(4, 100px); }
      .pp-masonry-featured { grid-column: span 2; grid-row: span 2; }
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

<!-- ════════════ NAVIGATION — Business Profile Variant ════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider profile navigation">
  <div class="pv-nav-inner">

    <!-- Logo -->
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo"
           style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>

    <!-- BACK TO BOOKINGS — prominent, replaces nav links -->
    <div style="flex:1;display:flex;justify-content:center;">
      <a href="<?= BASE_URL ?>bookings" class="pp-nav-back-btn" aria-label="Back to Bookings">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <path d="M9 2L4 7L9 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Back to Bookings
        <?php if ($upcomingCount): ?>
          <span class="pv-sup"><?= $upcomingCount ?></span>
        <?php endif; ?>
      </a>
    </div>

    <!-- Nav end -->
    <div class="pv-nav-end">
      <?php $notifUserId = (int)$customerId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/>  <line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22" y1="4.22"  x2="5.64" y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1"  y1="12" x2="3"  y2="12"/>  <line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22"  y1="19.78" x2="5.64"  y2="18.36"/>
          <line x1="18.36" y1="5.64"  x2="19.78" y2="4.22"/>
        </svg>
      </button>

      <!-- User profile trigger -->
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0" aria-expanded="false" aria-haspopup="true">
        <div class="pv-nav-av" aria-hidden="true">
          <?php if ($navAvatar): ?>
            <img src="<?= $navAvatar ?>" alt="<?= $userName ?>"
                 style="width:34px;height:34px;object-fit:cover;border-radius:99px;display:block;">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div class="pv-nav-user">
          <div class="pv-nav-user-name"><?= $userName ?></div>
          <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
        </div>
        <svg class="pv-profile-chevron" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
          <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <!-- Dropdown -->
        <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
          <div class="pv-pd-header">
            <div class="pv-pd-avatar">
              <?php if ($navAvatar): ?>
                <img src="<?= $navAvatar ?>" alt="">
              <?php else: ?>
                <?= $initials ?>
              <?php endif; ?>
            </div>
            <div class="pv-pd-info">
              <div class="pv-pd-name"><?= $userName ?></div>
              <div class="pv-pd-tier"><?= $loyaltyTier ?> Member</div>
            </div>
          </div>
          <div class="pv-pd-divider"></div>
          <a href="<?= BASE_URL ?>profile" class="pv-pd-item" role="menuitem">
            <div class="pv-pd-item-ico"><i class="fa-solid fa-user" style="font-size:.75rem;"></i></div>
            My Profile
            <svg class="pv-pd-item-arrow" width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3 2.5L6 5L3 7.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
          <a href="<?= BASE_URL ?>bookings" class="pv-pd-item" role="menuitem">
            <div class="pv-pd-item-ico"><i class="fa-solid fa-calendar-check" style="font-size:.75rem;"></i></div>
            My Bookings
            <svg class="pv-pd-item-arrow" width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3 2.5L6 5L3 7.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
          <div class="pv-pd-divider"></div>
          <a href="<?= BASE_URL ?>auth/logout" class="pv-pd-item pv-pd-item--danger" role="menuitem">
            <div class="pv-pd-item-ico"><i class="fa-solid fa-arrow-right-from-bracket" style="font-size:.75rem;"></i></div>
            Sign Out
          </a>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- ════════════ FLASH ════════════ -->
<?php if ($flash): ?>
<div class="pv-flash pv-flash--<?= $flash['type'] ?>" role="alert">
  <span><?= $flash['type'] === 'success' ? '✅' : '⚠️' ?></span>
  <?= htmlspecialchars($flash['msg']) ?>
  <button class="pv-flash-close" onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
</div>
<?php endif; ?>

<!-- ════════════ HERO — BUSINESS IDENTITY ════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>

  <div class="pv-hero-inner">
    <div class="pv-provider-hero-wrap">

      <!-- Business Avatar -->
      <?php
        $provPhoto    = $provider['profile_photo'] ?? '';
        $provInitials = strtoupper(substr($provider['first_name'] ?? 'P', 0, 1) . substr($provider['last_name'] ?? 'R', 0, 1));
      ?>
      <div class="pv-provider-av" aria-hidden="true">
        <?php if ($provPhoto): ?>
          <img src="<?= htmlspecialchars($provPhoto) ?>"
               alt="<?= htmlspecialchars($provider['business_name']) ?>">
        <?php else: ?>
          <?= $catEmoji ?>
        <?php endif; ?>
      </div>

      <!-- Business Info -->
      <div class="pv-provider-info">

        <!-- Category eyebrow -->
        <div style="margin-bottom:.5rem;">
          <span class="pp-hero-category-badge">
            <span class="cat-dot"></span>
            <?= htmlspecialchars($provider['category_name'] ?? 'Service Provider') ?>
          </span>
        </div>

        <!-- BUSINESS NAME — primary identity -->
        <h1 class="pp-hero-business-name">
          <?= htmlspecialchars($provider['business_name']) ?>
        </h1>

        <!-- Provider name — secondary -->
        <div class="pp-hero-provider-byline">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
            <circle cx="6" cy="4" r="2.5" stroke="currentColor" stroke-width="1.3"/>
            <path d="M1.5 11c0-2.485 2.015-4.5 4.5-4.5s4.5 2.015 4.5 4.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
          </svg>
          Managed by <?= htmlspecialchars(trim($provider['first_name'] . ' ' . $provider['last_name'])) ?>
          <?php if ($provider['years_experience'] ?? 0): ?>
            <span class="byline-sep"></span>
            <?= (int)$provider['years_experience'] ?>+ yrs experience
          <?php endif; ?>
        </div>

        <!-- Verified + Open + Favorite count badges -->
        <div class="pv-hero-badge-row">
          <?php if ($isVerified): ?>
          <span class="pp-verified-badge">
            <svg width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true">
              <path d="M2 5l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Verified Business
          </span>
          <?php endif; ?>

          <?php if ($openStatus === true): ?>
          <span class="pp-open-badge open"><span class="pp-open-dot"></span> Open Now</span>
          <?php elseif ($openStatus === false): ?>
          <span class="pp-open-badge closed"><span class="pp-open-dot"></span> Closed Now</span>
          <?php endif; ?>

          <?php if ($favoriteCount > 0): ?>
          <span class="pp-verified-badge" style="background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.22);color:var(--red);">
            <i class="fa-solid fa-heart" style="font-size:.55rem;"></i>
            <?= $favoriteCount ?> <?= $favoriteCount === 1 ? 'favorite' : 'favorites' ?>
          </span>
          <?php endif; ?>
        </div>

        <!-- Meta chips -->
        <div class="pv-provider-meta">
          <?php if ($provider['avg_rating'] > 0): ?>
          <span class="pv-meta-chip pv-meta-chip--gold">
            ⭐ <?= number_format($provider['avg_rating'], 1) ?>
            <span class="pv-meta-chip-sub">(<?= (int)$provider['total_reviews'] ?> review<?= (int)$provider['total_reviews'] !== 1 ? 's' : '' ?>)</span>
          </span>
          <?php endif; ?>

          <?php if ($provider['barangay'] || $provider['city']): ?>
          <span class="pv-meta-chip">
            📍 <?= htmlspecialchars(implode(', ', array_filter([$provider['barangay'] ?? '', $provider['city'] ?? '']))) ?>
          </span>
          <?php endif; ?>

          <?php if ($minPrice): ?>
          <span class="pv-meta-chip pv-meta-chip--gold">
            ₱<?= number_format((float)$minPrice, 0) ?>+ starting price
          </span>
          <?php endif; ?>

          <?php if ($offersHome): ?>
          <span class="pv-meta-chip pv-meta-chip--green">🏠 Home Service</span>
          <?php endif; ?>
        </div>

        <!-- Business Bio -->
        <?php if ($provider['bio']): ?>
        <p class="pp-hero-bio">
          <?= nl2br(htmlspecialchars($provider['bio'])) ?>
        </p>
        <?php endif; ?>

        <!-- Hero CTAs + Actions -->
        <div class="pv-hero-cta-row">
          <?php if (!empty($services)): ?>
          <a href="<?= BASE_URL ?>services/<?= (int)$services[0]['id'] ?>"
             class="pp-hero-book-btn js-cta-link" id="heroCTABtn">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <rect x="2" y="3" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
              <path d="M5 1v3M11 1v3M2 7h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Book Appointment
          </a>
          <?php endif; ?>

          <!-- Favorite button -->
          <button type="button"
                  class="pp-hero-action-btn <?= $isFavorited ? 'fav-active' : '' ?>"
                  id="heroFavBtn"
                  onclick="toggleFavorite()"
                  aria-label="<?= $isFavorited ? 'Remove from favorites' : 'Add to favorites' ?>"
                  title="<?= $isFavorited ? 'Remove from favorites' : 'Add to favorites' ?>">
            <i class="fa-<?= $isFavorited ? 'solid' : 'regular' ?> fa-heart" id="heroFavIcon"></i>
            <span id="heroFavText"><?= $isFavorited ? 'Saved' : 'Save' ?></span>
          </button>

          <!-- Share button -->
          <button type="button"
                  class="pp-hero-action-btn"
                  onclick="shareBusiness()"
                  aria-label="Share this business" title="Share">
            <i class="fa-solid fa-share-nodes"></i>
            Share
          </button>

          <?php if ($fullAddress): ?>
          <!-- Get directions -->
          <a href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>"
             target="_blank" rel="noopener noreferrer"
             class="pp-hero-action-btn"
             aria-label="Get directions">
            <i class="fa-solid fa-map-location-dot"></i>
            Directions
          </a>
          <?php endif; ?>
        </div>

      </div>
    </div><!-- /hero-wrap -->

    <!-- Quick stats bar -->
    <div class="pv-provider-quick-stats">
      <div class="pv-qs-item">
        <span class="pv-qs-val"><?= count($services) ?></span>
        <span class="pv-qs-label">Services</span>
      </div>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val"><?= $completedCount ?></span>
        <span class="pv-qs-label">Completed</span>
      </div>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val gold"><?= number_format((float)$provider['avg_rating'], 1) ?></span>
        <span class="pv-qs-label">Rating</span>
      </div>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val">₱<?= $minPrice ? number_format((float)$minPrice, 0) : '—' ?></span>
        <span class="pv-qs-label">From</span>
      </div>
      <?php if ($favoriteCount > 0): ?>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val" style="color:var(--red);"><?= $favoriteCount ?></span>
        <span class="pv-qs-label">Saves</span>
      </div>
      <?php endif; ?>
      <?php if (!empty($galleryPhotos)): ?>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val"><?= count($galleryPhotos) ?></span>
        <span class="pv-qs-label">Portfolio</span>
      </div>
      <?php endif; ?>
    </div>

  </div>
</header>

<!-- ════════════ MAIN ════════════ -->
<main class="pv-page" role="main">
  <div class="pv-layout">

    <!-- ══ LEFT COLUMN ══ -->
    <div class="pv-main">

      <!-- Breadcrumb -->
      <nav class="pv-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>bookings">Bookings</a>
        <span aria-hidden="true">›</span>
        <a href="<?= BASE_URL ?>browse">Browse</a>
        <span aria-hidden="true">›</span>
        <?php if ($provider['category_name']): ?>
          <a href="<?= BASE_URL ?>browse?category=<?= (int)$provider['category_id'] ?>">
            <?= htmlspecialchars($provider['category_name']) ?>
          </a>
          <span aria-hidden="true">›</span>
        <?php endif; ?>
        <span><?= htmlspecialchars($provider['business_name']) ?></span>
      </nav>

      <!-- ══ MAIN TAB CARD: Portfolio | Services | Reviews ══ -->
      <div class="pv-card">

        <!-- Tab bar -->
        <div class="pp-tabs" role="tablist">
          <button class="pp-tab active" onclick="switchTab('gallery',this)" id="tabGallery"
                  role="tab" aria-selected="true" aria-controls="panelGallery">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <rect x=".7" y=".7" width="5.3" height="5.3" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
              <rect x="8"  y=".7" width="5.3" height="5.3" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
              <rect x=".7" y="8"  width="5.3" height="5.3" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
              <rect x="8"  y="8"  width="5.3" height="5.3" rx="1.2" stroke="currentColor" stroke-width="1.3"/>
            </svg>
            Portfolio
            <span class="pp-tab-count"><?= count($galleryPhotos) ?></span>
          </button>

          <button class="pp-tab" onclick="switchTab('services',this)" id="tabServices"
                  role="tab" aria-selected="false" aria-controls="panelServices">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M1 3h12M1 7h12M1 11h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            <?= htmlspecialchars($provider['category_name'] ?? 'Services') ?>
            <span class="pp-tab-count"><?= count($services) ?></span>
          </button>

          <button class="pp-tab" onclick="switchTab('reviews',this)" id="tabReviews"
                  role="tab" aria-selected="false" aria-controls="panelReviews">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M7 1l1.8 3.6L13 5.3l-3 2.9.7 4.1L7 10.2l-3.7 2.1.7-4.1-3-2.9 4.2-.7z"
                    stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
            </svg>
            Reviews
            <span class="pp-tab-count"><?= count($allReviews) ?></span>
          </button>
        </div>

        <!-- ── PORTFOLIO PANEL ── -->
        <div class="pp-panel" id="panelGallery" role="tabpanel" aria-labelledby="tabGallery">
          <?php if (empty($galleryPhotos)): ?>
          <div class="pp-gallery-empty">
            <div style="font-size:2.4rem;margin-bottom:.75rem;"><?= $catEmoji ?></div>
            No portfolio photos yet for this business.
          </div>
          <?php else:
            $displayPhotos = array_slice($galleryPhotos, 0, 9);
            $extraCount    = max(0, count($galleryPhotos) - 9);
          ?>
          <div class="pp-masonry" id="galleryGrid">
            <?php foreach ($displayPhotos as $idx => $photo):
              $isLastSlot = ($idx === count($displayPhotos) - 1) && $extraCount > 0;
            ?>
            <?php if ($isLastSlot): ?>
              <div class="pp-masonry-item pp-masonry-more"
                   onclick="openLightbox(<?= $idx ?>)" role="button" tabindex="0"
                   aria-label="View all photos">
                <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="" loading="lazy">
                <div class="pp-masonry-more-label">
                  <span>+<?= $extraCount + 1 ?></span>
                  <span>more works</span>
                </div>
              </div>
            <?php else: ?>
              <div class="pp-masonry-item <?= ($idx === 0 || !empty($photo['is_featured'])) ? 'pp-masonry-featured' : '' ?>"
                   onclick="openLightbox(<?= $idx ?>)" role="button" tabindex="0"
                   aria-label="View portfolio photo <?= $idx + 1 ?>">
                <img src="<?= htmlspecialchars($photo['image_url'] ?? '') ?>"
                     alt="<?= htmlspecialchars($photo['title'] ?? $photo['caption'] ?? 'Portfolio work') ?>"
                     loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>">
                <div class="pp-masonry-overlay">
                  <?php if (!empty($photo['service_name'])): ?>
                    <div class="pp-masonry-service-tag">
                      <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($photo['service_name']) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($photo['title'])): ?>
                    <div class="pp-masonry-title"><?= htmlspecialchars($photo['title']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($photo['caption'])): ?>
                    <div class="pp-masonry-caption"><?= htmlspecialchars($photo['caption']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="pp-masonry-zoom" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <circle cx="7" cy="7" r="4.5" stroke="white" stroke-width="1.5"/>
                    <path d="M10.5 10.5L14 14" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                  </svg>
                </div>
              </div>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php if (count($galleryPhotos) > 9): ?>
          <div style="text-align:center;padding:.75rem 0 .25rem;">
            <button class="pp-gallery-all-btn" onclick="openLightbox(0)">
              <i class="fa-solid fa-images" style="font-size:.8rem;"></i>
              View all <?= count($galleryPhotos) ?> portfolio works
            </button>
          </div>
          <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- ── SERVICES PANEL ── -->
        <div class="pp-panel pp-panel--hidden" id="panelServices" role="tabpanel" aria-labelledby="tabServices">
          <?php if (empty($services)): ?>
          <div class="pp-svc-empty">
            <div style="font-size:2.2rem;"><?= $catEmoji ?></div>
            <p style="font-size:.82rem;color:var(--text-muted);">No services listed yet.</p>
          </div>
          <?php else: ?>

          <div class="pp-svc-category-header">
            <span><?= $catEmoji ?> <?= htmlspecialchars($provider['category_name'] ?? 'Services') ?></span>
            <span class="pp-svc-category-line"></span>
            <span><?= count($services) ?> service<?= count($services) !== 1 ? 's' : '' ?></span>
          </div>

          <div class="pp-svc-grid" role="list">
            <?php foreach ($services as $s):
              $color     = $serviceTypeColors[$s['service_type'] ?? ''] ?? 'gold';
              $accentHex = $colorAccents[$color] ?? $catAccent;
              $locType   = $s['location_type'] ?? 'In-shop';
              $locIcon   = $locIcons[$locType]  ?? $locIcons['In-shop'];
              $mins      = (int)($s['duration_minutes'] ?? 0);
              $dur       = '';
              if ($mins) {
                $dur = $mins >= 60
                  ? (($mins % 60 === 0) ? ($mins/60).'h' : floor($mins/60).'h '.($mins%60).'m')
                  : $mins.'m';
              }
            ?>
            <div class="pp-svc-item" role="listitem" style="--accent:<?= $accentHex ?>;">
              <div class="pp-svc-item-top">
                <div class="pp-svc-item-icon" aria-hidden="true"><?= $catEmoji ?></div>
                <div class="pp-svc-item-info">
                  <div class="pp-svc-item-name"><?= htmlspecialchars($s['name']) ?></div>
                  <?php if ($s['description']): ?>
                    <div class="pp-svc-item-desc"><?= htmlspecialchars($s['description']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="pp-svc-item-price">
                  <span class="pp-svc-item-price-val">₱<?= number_format((float)$s['price'], 0) ?></span>
                </div>
              </div>
              <div class="pp-svc-item-bottom">
                <div class="pp-svc-item-tags">
                  <?php if (!empty($s['service_type'])): ?>
                  <span class="pp-svc-tag pp-svc-tag--type"><?= htmlspecialchars($s['service_type']) ?></span>
                  <?php endif; ?>
                  <span class="pp-svc-tag pp-svc-tag--loc"><?= $locIcon ?> <?= htmlspecialchars($locType) ?></span>
                  <?php if ($dur): ?>
                  <span class="pp-svc-tag pp-svc-tag--dur">
                    <svg width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                      <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.3"/>
                      <path d="M6 3.5V6L7.5 7.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?= $dur ?>
                  </span>
                  <?php endif; ?>
                </div>
                <a href="<?= BASE_URL ?>services/<?= (int)$s['id'] ?>"
                   class="pp-svc-book-btn js-book-link"
                   data-service-id="<?= (int)$s['id'] ?>"
                   aria-label="Book <?= htmlspecialchars($s['name']) ?>">
                  Book Appointment
                  <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M2.5 6h7M7 3.5L9.5 6 7 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- ── REVIEWS PANEL ── -->
        <div class="pp-panel pp-panel--hidden" id="panelReviews" role="tabpanel" aria-labelledby="tabReviews">
          <?php if (empty($allReviews)): ?>
          <div class="pp-reviews-empty">
            <div style="font-size:2rem;opacity:.4;">⭐</div>
            <p style="font-size:.82rem;color:var(--text-muted);">No reviews yet — be the first to book!</p>
          </div>
          <?php else:
            $avgRating = (float)$provider['avg_rating'];
            $totalRevs = count($allReviews);
          ?>
          <div class="pp-rating-summary">
            <div class="pp-rating-big-block">
              <div class="pp-rating-big-num"><?= number_format($avgRating, 1) ?></div>
              <div class="pp-rating-big-stars"><?= renderStars($avgRating) ?></div>
              <div class="pp-rating-big-count"><?= $totalRevs ?> review<?= $totalRevs !== 1 ? 's' : '' ?></div>
            </div>
            <div class="pp-rating-bars">
              <?php foreach ([5,4,3,2,1] as $star):
                $cnt = $ratingBreakdown[$star] ?? 0;
                $pct = $totalRevs > 0 ? round(($cnt / $totalRevs) * 100) : 0;
              ?>
              <div class="pp-rating-bar-row">
                <span class="pp-rating-bar-label"><?= $star ?> <i class="fa-solid fa-star" style="color:#f59e0b;font-size:.5rem;"></i></span>
                <div class="pp-rating-bar-track">
                  <div class="pp-rating-bar-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="pp-rating-bar-num"><?= $cnt ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="pp-review-list">
            <?php foreach ($allReviews as $rev):
              $revInitials = strtoupper(substr($rev['first_name'],0,1).substr($rev['last_name'],0,1));
              $hasReply    = !empty($rev['reply']) || !empty($rev['provider_reply']);
              $replyText   = $rev['reply'] ?? $rev['provider_reply'] ?? '';
            ?>
            <div class="pp-review-item">
              <div class="pp-review-head">
                <div class="pp-review-av">
                  <?php if ($rev['avatar_url']): ?>
                    <img src="<?= htmlspecialchars($rev['avatar_url']) ?>" alt="">
                  <?php else: ?>
                    <?= $revInitials ?>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="pp-review-name"><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></div>
                  <div class="pp-review-date"><?= date('M j, Y', strtotime($rev['created_at'])) ?></div>
                </div>
                <div class="pp-review-stars" aria-label="<?= $rev['rating'] ?> out of 5 stars">
                  <?= renderStars((float)$rev['rating']) ?>
                  <span style="font-family:var(--font-mono);font-size:.68rem;color:var(--text-dim);margin-left:.3rem;"><?= number_format((float)$rev['rating'], 1) ?></span>
                </div>
              </div>
              <?php if (!empty($rev['comment'])): ?>
                <p class="pp-review-text">"<?= htmlspecialchars($rev['comment']) ?>"</p>
              <?php endif; ?>
              <?php if (!empty($rev['service_name'])): ?>
                <span class="pp-review-service">
                  <?= $catEmoji ?> <?= htmlspecialchars($rev['service_name']) ?>
                </span>
              <?php endif; ?>
              <?php if ($hasReply && $replyText): ?>
              <div class="pp-review-reply">
                <div class="pp-review-reply-header">
                  <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M1 3h10v6a1 1 0 01-1 1H2a1 1 0 01-1-1V3z" stroke="currentColor" stroke-width="1.2"/>
                    <path d="M1 3l5 4 5-4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                  </svg>
                  <?= htmlspecialchars($provider['business_name']) ?> replied
                </div>
                <p class="pp-review-reply-text"><?= htmlspecialchars($replyText) ?></p>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- /pv-card (tabs) -->

      <!-- ══ BOOKING TYPE TOGGLE ══ -->
      <?php if ($showToggle): ?>
      <div class="pp-loc-toggle-wrap" id="locToggleWrap">
        <span class="pp-loc-toggle-label">How would you like your service?</span>
        <div class="pp-loc-toggle">
          <button type="button" class="pp-loc-btn active" id="btnInShop" onclick="setLocType('shop')">
            🏪 Visit the Shop
          </button>
          <button type="button" class="pp-loc-btn" id="btnHomeService" onclick="setLocType('home')">
            🏠 Home Service
          </button>
        </div>
        <div class="pp-home-addr-row" id="homeAddrRow">
          <input type="text" id="homeAddress" class="pp-home-addr-input"
                 placeholder="Enter your full address for home service…"
                 aria-label="Your address for home service">
        </div>
      </div>
      <?php elseif ($offersHome && !$offersShop): ?>
      <div class="pp-loc-toggle-wrap">
        <span class="pp-loc-toggle-label">Service type</span>
        <div class="pp-loc-toggle">
          <button type="button" class="pp-loc-btn active home-active">🏠 Home Service Only</button>
        </div>
        <div class="pp-home-addr-row visible">
          <input type="text" id="homeAddress" class="pp-home-addr-input"
                 placeholder="Enter your full address for home service…"
                 aria-label="Your address for home service">
        </div>
      </div>
      <?php elseif ($offersShop && !$offersHome): ?>
      <div class="pp-loc-toggle-wrap">
        <span class="pp-loc-toggle-label">Service type</span>
        <div class="pp-loc-toggle">
          <button type="button" class="pp-loc-btn active">🏪 In-Shop Only</button>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /pv-main -->

    <!-- ══ RIGHT SIDEBAR ══ -->
    <aside class="pv-sidebar" aria-label="Provider booking details">

      <!-- 1. QUICK BOOK CARD -->
      <?php if (!empty($services)): ?>
      <div class="pp-quickbook-card">
        <div class="pp-qb-body">
          <div class="pp-qb-title">Book at <?= htmlspecialchars($provider['business_name']) ?></div>
          <div class="pp-qb-facts">
            <?php if ($isVerified): ?>
            <div class="pp-qb-fact">
              <span class="pp-qb-fact-ico">✔</span>
              <span class="qb-verified"><strong>Verified</strong> Business</span>
            </div>
            <?php endif; ?>
            <?php if ($provider['avg_rating'] > 0): ?>
            <div class="pp-qb-fact">
              <span class="pp-qb-fact-ico">⭐</span>
              <span><strong><?= number_format($provider['avg_rating'],1) ?></strong> Rating
                (<?= (int)$provider['total_reviews'] ?> reviews)</span>
            </div>
            <?php endif; ?>
            <?php if ($openStatus === true): ?>
            <div class="pp-qb-fact">
              <span class="pp-qb-fact-ico">🟢</span>
              <span class="qb-open"><strong>Open Now</strong>
                <?php if ($todayAvail): ?>
                  · <?= date('g:i A', strtotime($todayAvail['start_time'])) ?>–<?= date('g:i A', strtotime($todayAvail['end_time'])) ?>
                <?php endif; ?>
              </span>
            </div>
            <?php elseif ($openStatus === false && $nextAvail): ?>
            <div class="pp-qb-fact">
              <span class="pp-qb-fact-ico">🔴</span>
              <span class="qb-closed"><strong>Closed Now</strong> · Opens <?= $nextAvail['day_of_week'] ?></span>
            </div>
            <?php endif; ?>
            <?php if ($minPrice): ?>
            <div class="pp-qb-fact">
              <span class="pp-qb-fact-ico">₱</span>
              <span>From <strong>₱<?= number_format((float)$minPrice,0) ?></strong> · <?= count($services) ?> service<?= count($services) !== 1 ? 's' : '' ?></span>
            </div>
            <?php endif; ?>
            <?php if ($completedCount > 0): ?>
            <div class="pp-qb-fact">
              <span class="pp-qb-fact-ico">✅</span>
              <span><strong><?= $completedCount ?></strong> completed bookings</span>
            </div>
            <?php endif; ?>
          </div>
          <a href="<?= BASE_URL ?>services/<?= (int)$services[0]['id'] ?>"
             class="pp-qb-cta js-cta-link" id="ctaBookBtn">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" style="display:inline-block;margin-right:.4rem;vertical-align:middle;" aria-hidden="true">
              <rect x="2" y="3" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
              <path d="M5 1v3M11 1v3M2 7h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            Book Appointment
          </a>
          <!-- Sidebar action row -->
          <div class="pp-sidebar-actions">
            <button type="button"
                    class="pp-sb-action-btn <?= $isFavorited ? 'fav-active' : '' ?>"
                    id="sbFavBtn"
                    onclick="toggleFavorite()"
                    title="<?= $isFavorited ? 'Remove from favorites' : 'Add to favorites' ?>">
              <i class="fa-<?= $isFavorited ? 'solid' : 'regular' ?> fa-heart" id="sbFavIcon"></i>
              <span id="sbFavText"><?= $isFavorited ? 'Saved' : 'Save' ?></span>
            </button>
            <button type="button" class="pp-sb-action-btn" onclick="shareBusiness()" title="Share">
              <i class="fa-solid fa-share-nodes"></i> Share
            </button>
            <?php if ($fullAddress): ?>
            <a href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>"
               target="_blank" rel="noopener noreferrer"
               class="pp-sb-action-btn" title="Get Directions">
              <i class="fa-solid fa-diamond-turn-right"></i> Directions
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- 2. AVAILABILITY CARD -->
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>
            <span style="display:flex;align-items:center;gap:.55rem;">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <rect x="1" y="2" width="12" height="11" rx="2" stroke="currentColor" stroke-width="1.3"/>
                <path d="M4 1v2M10 1v2M1 6h12" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
              </svg>
              Business Hours &amp; Availability
            </span>
          </h2>
        </div>

        <?php if ($todayAvail): ?>
        <div class="pp-avail-today">
          <div>
            <div class="pp-avail-today-label">Today · <?= $todayName ?></div>
            <div class="pp-avail-today-hours">
              <?= date('g:i A', strtotime($todayAvail['start_time'])) ?> – <?= date('g:i A', strtotime($todayAvail['end_time'])) ?>
            </div>
          </div>
          <?php if ($openStatus !== null): ?>
          <span class="pp-avail-status-pill <?= $openStatus ? 'open' : 'closed' ?>">
            <span class="pp-avail-status-dot"></span>
            <?= $openStatus ? 'Open' : 'Closed' ?>
          </span>
          <?php endif; ?>
        </div>
        <?php elseif ($nextAvail): ?>
        <div class="pp-next-avail">
          Closed today · Next: <strong><?= $nextAvail['day_of_week'] ?>, <?= date('g:i A', strtotime($nextAvail['start_time'])) ?></strong>
        </div>
        <?php endif; ?>

        <?php if (empty($availability)): ?>
        <div style="padding:1.5rem;">
          <p style="font-size:.82rem;color:var(--text-dim);">No schedule set yet.</p>
        </div>
        <?php else: ?>
        <div class="pv-avail-list">
          <?php foreach ($availability as $av): ?>
          <div class="pv-avail-item <?= $av['day_of_week'] === $todayName ? 'pv-avail-item--today' : '' ?>">
            <span class="pv-avail-day">
              <?php if ($av['day_of_week'] === $todayName): ?>
                <span style="color:var(--cat-accent);font-weight:700;"><?= $av['day_of_week'] ?></span>
              <?php else: ?>
                <?= htmlspecialchars($av['day_of_week']) ?>
              <?php endif; ?>
            </span>
            <span class="pv-avail-time">
              <?= date('g:i A', strtotime($av['start_time'])) ?> – <?= date('g:i A', strtotime($av['end_time'])) ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- 3. LOCATION CARD with Leaflet Map -->
      <div class="pv-card pp-map-card">
        <div class="pv-card-head">
          <h2>
            <span style="display:flex;align-items:center;gap:.55rem;">
              <svg width="13" height="14" viewBox="0 0 13 14" fill="none" aria-hidden="true">
                <path d="M6.5 1C4.57 1 3 2.57 3 4.5C3 7.3 6.5 13 6.5 13S10 7.3 10 4.5C10 2.57 8.43 1 6.5 1Z" stroke="currentColor" stroke-width="1.3"/>
                <circle cx="6.5" cy="4.5" r="1.5" stroke="currentColor" stroke-width="1.2"/>
              </svg>
              Shop Address
            </span>
          </h2>
        </div>
        <div class="pp-map-wrap">
          <?php if ($hasCoords): ?>
            <div id="leafletMap"></div>
          <?php else: ?>
            <div class="pp-map-fallback">
              <iframe
                title="Shop location"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                src="https://maps.google.com/maps?q=<?= $mapQuery ?>&output=embed&z=15"
                allowfullscreen></iframe>
            </div>
          <?php endif; ?>
        </div>
        <div class="pp-map-addr">
          <?php if ($fullAddress): ?>
          <div class="pp-map-addr-row">
            <span>📍</span>
            <span><?= htmlspecialchars($fullAddress) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($offersHome): ?>
          <div class="pp-map-addr-row pp-map-home-row">
            <span>🏠</span>
            <span>Home service available in your area</span>
          </div>
          <?php endif; ?>
          <?php if ($fullAddress): ?>
          <div class="pp-map-addr-actions">
            <a href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>"
               target="_blank" rel="noopener noreferrer" class="pp-map-directions-btn">
              <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M7 1L13 7L7 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M1 7h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              Get Directions
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 4. CONTACT INFORMATION CARD -->
      <?php if ($phone || $email || $website || $instagram || $facebook): ?>
      <div class="pv-card" id="sidebarContact">
        <div class="pv-card-head">
          <h2>
            <span style="display:flex;align-items:center;gap:.55rem;">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M2.5 2h9a1 1 0 011 1v8a1 1 0 01-1 1h-9a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.3"/>
                <path d="M1.5 3.5l5 4.5 5-4.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
              </svg>
              Contact
            </span>
          </h2>
        </div>
        <div class="pp-contact-list">
          <?php if ($phone): ?>
          <div class="pp-contact-item">
            <div class="pp-contact-icon"><i class="fa-solid fa-phone"></i></div>
            <div>
              <span class="pp-contact-label">Phone</span>
              <div class="pp-contact-val"><a href="tel:<?= htmlspecialchars($phone) ?>"><?= htmlspecialchars($phone) ?></a></div>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($email): ?>
          <div class="pp-contact-item">
            <div class="pp-contact-icon"><i class="fa-solid fa-envelope"></i></div>
            <div>
              <span class="pp-contact-label">Email</span>
              <div class="pp-contact-val"><a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a></div>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($website): ?>
          <div class="pp-contact-item">
            <div class="pp-contact-icon"><i class="fa-solid fa-globe"></i></div>
            <div>
              <span class="pp-contact-label">Website</span>
              <div class="pp-contact-val"><a href="<?= htmlspecialchars($website) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(preg_replace('/^https?:\/\//', '', $website)) ?></a></div>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($instagram): ?>
          <div class="pp-contact-item">
            <div class="pp-contact-icon"><i class="fa-brands fa-instagram"></i></div>
            <div>
              <span class="pp-contact-label">Instagram</span>
              <div class="pp-contact-val"><a href="https://instagram.com/<?= htmlspecialchars(ltrim($instagram, '@')) ?>" target="_blank" rel="noopener">@<?= htmlspecialchars(ltrim($instagram, '@')) ?></a></div>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($facebook): ?>
          <div class="pp-contact-item">
            <div class="pp-contact-icon"><i class="fa-brands fa-facebook-f"></i></div>
            <div>
              <span class="pp-contact-label">Facebook</span>
              <div class="pp-contact-val"><a href="<?= strpos($facebook, 'http') === 0 ? htmlspecialchars($facebook) : 'https://facebook.com/'.htmlspecialchars($facebook) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($facebook) ?></a></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </aside><!-- /pv-sidebar -->

  </div><!-- /pv-layout -->
</main>

<!-- Share toast -->
<div class="pp-share-toast" id="shareToast">Link copied to clipboard!</div>

<!-- ════════════ LIGHTBOX ════════════ -->
<?php if (!empty($galleryPhotos)): ?>
<div class="pp-lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Photo viewer">
  <div class="pp-lightbox-inner">
    <button class="pp-lightbox-close" onclick="closeLightbox()" aria-label="Close">✕</button>
    <img src="" id="lightboxImg" alt="">
    <div class="pp-lightbox-caption" id="lightboxCaption"></div>
    <div class="pp-lightbox-nav">
      <button class="pp-lightbox-btn" onclick="prevPhoto()" aria-label="Previous">‹</button>
      <span class="pp-lightbox-counter" id="lightboxCounter"></span>
      <button class="pp-lightbox-btn" onclick="nextPhoto()" aria-label="Next">›</button>
    </div>
  </div>
</div>
<script>
const galleryPhotos = <?= json_encode(array_map(function($p) {
    $extras = !empty($p['extra_images']) ? json_decode($p['extra_images'], true) : [];
    if (!is_array($extras)) $extras = [];
    $allImgs = array_filter(array_merge([$p['image_url'] ?? ''], $extras));
    return [
        'url'    => $p['image_url'] ?? '',
        'images' => array_values($allImgs),
        'title'  => $p['title'] ?? '',
        'caption'=> $p['caption'] ?? '',
        'service'=> $p['service_name'] ?? '',
    ];
}, $galleryPhotos)) ?>;
let currentIdx = 0;
let currentImgIdx = 0;
function openLightbox(idx) {
  currentIdx = idx % galleryPhotos.length;
  currentImgIdx = 0;
  updateLightbox();
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox()   { document.getElementById('lightbox').classList.remove('open'); document.body.style.overflow = ''; }
function updateLightbox() {
  const p = galleryPhotos[currentIdx];
  const imgs = (p.images && p.images.length) ? p.images : [p.url];
  currentImgIdx = Math.max(0, Math.min(currentImgIdx, imgs.length - 1));
  document.getElementById('lightboxImg').src = imgs[currentImgIdx];
  document.getElementById('lightboxImg').alt = p.title || p.caption || 'Portfolio photo ' + (currentIdx + 1);
  var captionParts = [];
  if (p.service) captionParts.push('🏷 ' + p.service);
  if (p.title)   captionParts.push(p.title);
  if (p.caption) captionParts.push(p.caption);
  document.getElementById('lightboxCaption').textContent = captionParts.join(' · ') || '';
  // Counter shows work position + image dot indicators if multiple
  var counter = (currentIdx + 1) + ' / ' + galleryPhotos.length;
  if (imgs.length > 1) counter += '  (' + (currentImgIdx + 1) + ' of ' + imgs.length + ' photos)';
  document.getElementById('lightboxCounter').textContent = counter;
}
function nextPhoto() {
  const p = galleryPhotos[currentIdx];
  const imgs = (p.images && p.images.length) ? p.images : [p.url];
  if (currentImgIdx < imgs.length - 1) { currentImgIdx++; updateLightbox(); }
  else { currentIdx = (currentIdx + 1) % galleryPhotos.length; currentImgIdx = 0; updateLightbox(); }
}
function prevPhoto() {
  if (currentImgIdx > 0) { currentImgIdx--; updateLightbox(); }
  else { currentIdx = (currentIdx - 1 + galleryPhotos.length) % galleryPhotos.length; const p2 = galleryPhotos[currentIdx]; const imgs2 = (p2.images && p2.images.length) ? p2.images : [p2.url]; currentImgIdx = imgs2.length - 1; updateLightbox(); }
}
document.getElementById('lightbox').addEventListener('click', e => { if (e.target === document.getElementById('lightbox')) closeLightbox(); });
document.addEventListener('keydown', e => {
  if (!document.getElementById('lightbox').classList.contains('open')) return;
  if (e.key === 'Escape') closeLightbox();
  if (e.key === 'ArrowRight') nextPhoto();
  if (e.key === 'ArrowLeft')  prevPhoto();
});
</script>
<?php endif; ?>

<!-- ════════════ LEAFLET MAP INIT ════════════ -->
<?php if ($hasCoords): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  var map = L.map('leafletMap', { zoomControl: true, scrollWheelZoom: false }).setView([<?= (float)$lat ?>, <?= (float)$lng ?>], 15);
  var tileUrl = isDark
    ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
    : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
  L.tileLayer(tileUrl, {
    attribution: '© OpenStreetMap contributors © CARTO',
    subdomains: 'abcd', maxZoom: 19
  }).addTo(map);

  var markerHtml = '<div style="width:36px;height:36px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:<?= $catAccent ?>;border:3px solid white;box-shadow:0 3px 12px rgba(0,0,0,.35);"></div>';
  var customIcon = L.divIcon({ html: markerHtml, className: '', iconSize: [36, 36], iconAnchor: [18, 36], popupAnchor: [0, -36] });

  L.marker([<?= (float)$lat ?>, <?= (float)$lng ?>], { icon: customIcon })
    .addTo(map)
    .bindPopup('<strong><?= htmlspecialchars(addslashes($provider['business_name'])) ?></strong><br><small><?= htmlspecialchars(addslashes($fullAddress)) ?></small>')
    .openPopup();

  // Try to show customer distance using Geolocation API
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
      var lat1 = pos.coords.latitude, lon1 = pos.coords.longitude;
      var lat2 = <?= (float)$lat ?>, lon2 = <?= (float)$lng ?>;
      var R = 6371;
      var dLat = (lat2-lat1)*Math.PI/180;
      var dLon = (lon2-lon1)*Math.PI/180;
      var a = Math.sin(dLat/2)*Math.sin(dLat/2)+Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)*Math.sin(dLon/2);
      var c = 2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
      var d = (R*c).toFixed(1);
      var badge = document.createElement('span');
      badge.className = 'pp-distance-badge';
      badge.innerHTML = '<i class="fa-solid fa-location-crosshairs" style="font-size:.55rem;"></i> ' + d + ' km from you';
      var addrDiv = document.querySelector('.pp-map-addr-row');
      if (addrDiv) addrDiv.parentNode.insertBefore(badge, addrDiv);
    }, null, {timeout:5000});
  }
});
</script>
<?php endif; ?>

<!-- ════════════ LOCATION TOGGLE JS ════════════ -->
<?php if ($showToggle || ($offersHome && !$offersShop)): ?>
<script>
let currentLocType = '<?= $offersHome && !$offersShop ? 'home' : 'shop' ?>';
function setLocType(type) {
  currentLocType = type;
  const btnShop  = document.getElementById('btnInShop');
  const btnHome  = document.getElementById('btnHomeService');
  const addrRow  = document.getElementById('homeAddrRow');
  const addrInput = document.getElementById('homeAddress');
  if (type === 'home') {
    btnShop?.classList.remove('active');
    btnHome?.classList.add('active','home-active');
    addrRow?.classList.add('visible');
    addrInput?.focus();
  } else {
    btnHome?.classList.remove('active','home-active');
    btnShop?.classList.add('active');
    addrRow?.classList.remove('visible');
  }
  updateBookLinks();
}
function updateBookLinks() {
  const addr = document.getElementById('homeAddress')?.value || '';
  document.querySelectorAll('.js-book-link, .js-cta-link').forEach(link => {
    const base = link.getAttribute('href').split('?')[0];
    const params = new URLSearchParams({ loc: currentLocType });
    if (currentLocType === 'home' && addr) params.set('addr', addr);
    link.setAttribute('href', base + '?' + params.toString());
  });
}
document.getElementById('homeAddress')?.addEventListener('input', updateBookLinks);
</script>
<?php endif; ?>

<!-- ════════════ FAVORITE TOGGLE JS ════════════ -->
<script>
let isFavorited = <?= $isFavorited ? 'true' : 'false' ?>;
let favCount    = <?= $favoriteCount ?>;

function toggleFavorite() {
  fetch('<?= BASE_URL ?>api/favorites/toggle', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ provider_id: <?= $providerId ?> })
  })
  .then(r => r.ok ? r.json() : Promise.reject())
  .then(data => {
    isFavorited = data.favorited ?? !isFavorited;
    favCount += isFavorited ? 1 : -1;
    updateFavUI();
  })
  .catch(() => {
    // Optimistic fallback
    isFavorited = !isFavorited;
    favCount += isFavorited ? 1 : -1;
    updateFavUI();
  });
}

function updateFavUI() {
  const iconClass = isFavorited ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
  const text      = isFavorited ? 'Saved' : 'Save';

  ['heroFavIcon','sbFavIcon'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.className = iconClass;
  });
  ['heroFavText','sbFavText'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  });
  ['heroFavBtn','sbFavBtn'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.classList.toggle('fav-active', isFavorited);
      el.setAttribute('aria-label', isFavorited ? 'Remove from favorites' : 'Add to favorites');
    }
  });
}
</script>

<!-- ════════════ SHARE JS ════════════ -->
<script>
function shareBusiness() {
  const url  = window.location.href;
  const name = '<?= htmlspecialchars(addslashes($provider['business_name'])) ?>';
  const text = 'Check out ' + name + ' on QuickBook!';

  if (navigator.share) {
    navigator.share({ title: name, text: text, url: url }).catch(() => {});
  } else if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => showToast('Link copied to clipboard!'));
  } else {
    showToast('Link: ' + url);
  }
}

function showToast(msg) {
  const t = document.getElementById('shareToast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}
</script>

<!-- ════════════ TAB SWITCHING ════════════ -->
<script>
function switchTab(name, btn) {
  document.querySelectorAll('.pp-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
  document.querySelectorAll('.pp-panel').forEach(p => p.classList.add('pp-panel--hidden'));
  btn.classList.add('active');
  btn.setAttribute('aria-selected','true');
  const panelId = 'panel' + name.charAt(0).toUpperCase() + name.slice(1);
  document.getElementById(panelId)?.classList.remove('pp-panel--hidden');
}
</script>

<!-- ════════════ PROFILE DROPDOWN ════════════ -->
<script>
(function() {
  const trigger = document.getElementById('profileTrigger');
  const dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;

  trigger.addEventListener('click', function(e) {
    e.stopPropagation();
    const isOpen = dropdown.classList.toggle('is-open');
    trigger.classList.toggle('is-open', isOpen);
    trigger.setAttribute('aria-expanded', isOpen);
  });

  document.addEventListener('click', function() {
    dropdown.classList.remove('is-open');
    trigger.classList.remove('is-open');
    trigger.setAttribute('aria-expanded', 'false');
  });

  dropdown.addEventListener('click', e => e.stopPropagation());
})();
</script>

<!-- ════════════ THEME TOGGLE ════════════ -->
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
      document.documentElement.setAttribute('data-theme','dark');
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

</body>
</html>