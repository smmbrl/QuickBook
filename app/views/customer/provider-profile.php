<?php
// app/views/customer/provider-profile.php

require_once __DIR__ . '/../../../config/database.php';
$db         = Database::getInstance();
$customerId = (int)($_SESSION['user_id']  ?? 0);
$userName   = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$initials   = strtoupper(substr($userName, 0, 2));

// ── Provider ID from URL ───────────────────────────────────────
$providerId = (int)($id ?? 0);

// ── Fetch provider ─────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT pp.*, u.first_name, u.last_name, u.email, u.avatar_url,
           c.name as category_name, c.slug as category_slug,
           c.id as category_id
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

// ── Gallery photos ─────────────────────────────────────────────
$galStmt = $db->prepare("
    SELECT * FROM tbl_provider_gallery
    WHERE provider_id = ?
    ORDER BY sort_order ASC, id ASC
");
$galStmt->execute([$providerId]);
$galleryPhotos = $galStmt->fetchAll();

// ── Services ───────────────────────────────────────────────────
$svcStmt = $db->prepare("
    SELECT * FROM tbl_services
    WHERE provider_id = ? AND is_active = 1
    ORDER BY price ASC
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

// ── Recent reviews (max 3) ─────────────────────────────────────
$revStmt = $db->prepare("
    SELECT r.*, u.first_name, u.last_name, u.avatar_url
    FROM tbl_reviews r
    JOIN tbl_users u ON r.customer_id = u.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
    LIMIT 3
");
$revStmt->execute([$providerId]);
$reviews = $revStmt->fetchAll();

// ── Nav data ───────────────────────────────────────────────────
$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$customerId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$loyaltyTier   = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};

$stUp = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE()");
$stUp->execute([$customerId]);
$upcomingCount = (int)$stUp->fetchColumn();

// ── Nav avatar ─────────────────────────────────────────────────
$stAv = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAv->execute([$customerId]);
$navAvatar = $stAv->fetchColumn() ?: null;

// ── Flash ──────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Helpers ────────────────────────────────────────────────────
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
    'makeup'           => '💄',
];
$catEmoji = $catEmojiMap[$provider['category_slug'] ?? ''] ?? '🛠️';

function renderStars(float $r): string {
    $f = floor($r); $h = ($r - $f) >= .5 ? 1 : 0; $e = 5 - $f - $h;
    return str_repeat('★', $f) . ($h ? '½' : '') . str_repeat('☆', $e);
}

// Determine which location options this provider offers
$locTypes   = $provider['location_types_offered'] ?? 'In-shop';
$offersHome = (int)$provider['offers_home_service'] === 1;
$offersShop = strpos($locTypes, 'In-shop') !== false || strpos($locTypes, 'Flexible') !== false;
$showToggle = $offersHome && $offersShop;    // show toggle only if both available

// Min price
$minPrice = $services ? min(array_column($services, 'price')) : null;

$serviceTypeColors = [
    'Barber'       => 'blue',   'Hair Stylist' => 'purple',
    'Nail Tech'    => 'pink',   'Massage'      => 'green',
    'Facial'       => 'yellow', 'Trainer'      => 'orange',
    'Cleaner'      => 'teal',   'Pet Groomer'  => 'gold',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — <?= htmlspecialchars($provider['business_name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_provider.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* ── Gallery grid ── */
    .pp-gallery-section { margin-bottom: 1.75rem; }
    .pp-gallery-title {
      font-family: var(--font-display); font-size: 1rem; font-weight: 600;
      font-style: italic; color: var(--text-primary); margin-bottom: 1rem;
      display: flex; align-items: center; gap: .55rem;
    }
    .pp-gallery-title span { font-style: normal; font-size: .65rem; font-family: var(--font-mono);
      color: var(--text-dim); letter-spacing: .1em; text-transform: uppercase; }
    .pp-gallery-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8px;
    }
    .pp-gallery-item {
      aspect-ratio: 1/1; border-radius: 12px; overflow: hidden;
      cursor: pointer; position: relative;
      border: 1.5px solid var(--card-border);
      background: var(--surface);
      transition: transform .2s var(--ease-out), box-shadow .2s;
    }
    .pp-gallery-item:hover { transform: scale(1.025); box-shadow: var(--shadow-md); }
    .pp-gallery-item img {
      width: 100%; height: 100%; object-fit: cover; display: block;
      transition: transform .3s var(--ease-out);
    }
    .pp-gallery-item:hover img { transform: scale(1.06); }
    .pp-gallery-item--more {
      display: flex; align-items: center; justify-content: center; flex-direction: column;
      gap: .25rem; background: var(--surface-md);
    }
    .pp-gallery-item--more span:first-child {
      font-family: var(--font-display); font-size: 1.4rem; font-weight: 700; color: var(--gold-dim);
    }
    .pp-gallery-item--more span:last-child {
      font-family: var(--font-mono); font-size: .58rem; color: var(--text-dim);
      letter-spacing: .08em; text-transform: uppercase;
    }
    .pp-gallery-caption {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: linear-gradient(0deg, rgba(0,0,0,.55) 0%, transparent 100%);
      padding: .6rem .7rem .45rem;
      font-size: .68rem; color: rgba(255,255,255,.85);
      opacity: 0; transition: opacity .2s; font-family: var(--font-mono);
    }
    .pp-gallery-item:hover .pp-gallery-caption { opacity: 1; }

    /* ── Lightbox ── */
    .pp-lightbox {
      display: none; position: fixed; inset: 0; z-index: 9999;
      background: rgba(0,0,0,.88); backdrop-filter: blur(8px);
      align-items: center; justify-content: center; padding: 1.5rem;
    }
    .pp-lightbox.open { display: flex; }
    .pp-lightbox-inner {
      position: relative; max-width: 860px; width: 100%;
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
      width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
      border-radius: 50%; border: 1px solid rgba(255,255,255,.2);
      transition: all .15s;
    }
    .pp-lightbox-close:hover { color: #fff; background: rgba(255,255,255,.1); }
    .pp-lightbox-nav {
      display: flex; gap: .75rem; align-items: center;
    }
    .pp-lightbox-btn {
      width: 40px; height: 40px; border-radius: 50%;
      border: 1px solid rgba(255,255,255,.25); color: rgba(255,255,255,.75);
      background: rgba(255,255,255,.08); font-size: 1rem; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: all .15s;
    }
    .pp-lightbox-btn:hover { background: rgba(255,255,255,.18); color: #fff; }
    .pp-lightbox-counter {
      font-family: var(--font-mono); font-size: .7rem; color: rgba(255,255,255,.45);
      min-width: 60px; text-align: center;
    }

    /* ── Booking type toggle ── */
    .pp-loc-toggle-wrap {
      background: var(--card-bg); border: 1.5px solid var(--card-border);
      border-radius: var(--r-lg); padding: 1.1rem 1.3rem; margin-bottom: 1.5rem;
      box-shadow: var(--shadow-sm);
    }
    .pp-loc-toggle-label {
      font-size: .72rem; font-family: var(--font-mono); letter-spacing: .1em;
      text-transform: uppercase; color: var(--text-dim); margin-bottom: .7rem;
      display: block;
    }
    .pp-loc-toggle {
      display: flex; gap: .5rem;
    }
    .pp-loc-btn {
      flex: 1; padding: .65rem 1rem; border-radius: var(--r-sm);
      border: 1.5px solid var(--border-md); background: transparent;
      font-family: var(--font-body); font-size: .84rem; font-weight: 500;
      color: var(--text-muted); cursor: pointer; text-align: center;
      transition: all .2s var(--ease-out);
      display: flex; align-items: center; justify-content: center; gap: .45rem;
    }
    .pp-loc-btn:hover { border-color: var(--gold-border-md); color: var(--text-primary); background: var(--surface); }
    .pp-loc-btn.active {
      background: var(--gold-lt); border-color: var(--gold-border-md);
      color: var(--gold-dim); font-weight: 600;
    }
    .pp-loc-btn.active.home-active {
      background: var(--green-soft); border-color: var(--green-border); color: var(--green);
    }
    .pp-home-addr-row {
      margin-top: .75rem; display: none;
      animation: fadeIn .2s ease;
    }
    .pp-home-addr-row.visible { display: block; }
    .pp-home-addr-input {
      width: 100%; padding: .65rem .9rem;
      background: var(--surface); border: 1.5px solid var(--border-md);
      border-radius: var(--r-sm); font-family: var(--font-body); font-size: .84rem;
      color: var(--text-primary); outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .pp-home-addr-input:focus {
      border-color: var(--gold-border-md);
      box-shadow: 0 0 0 3px var(--gold-lt);
    }
    .pp-home-addr-input::placeholder { color: var(--text-faint); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }

    /* ── Service list items ── */
    .pv-service-item { position: relative; }
    .pv-book-btn {
      display: inline-flex; align-items: center; gap: .35rem;
      padding: .5rem 1.1rem; border-radius: var(--r-sm);
      background: linear-gradient(135deg, var(--gold-dim), var(--gold));
      color: #fff8e8; font-family: var(--font-display); font-size: .78rem; font-weight: 700;
      transition: filter .2s, transform .15s var(--ease-out), box-shadow .2s;
      box-shadow: 0 2px 8px rgba(201,168,76,.22); white-space: nowrap;
      text-decoration: none;
    }
    .pv-book-btn:hover { filter: brightness(1.08); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(201,168,76,.35); }

    /* ── Reviews section ── */
    .pp-reviews-section { margin-top: .25rem; }
    .pp-review-item {
      padding: 1rem 0; border-bottom: 1px solid var(--border);
    }
    .pp-review-item:last-child { border-bottom: none; }
    .pp-review-head { display: flex; align-items: center; gap: .65rem; margin-bottom: .4rem; }
    .pp-review-av {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--gold-lt); border: 1px solid var(--gold-border);
      display: flex; align-items: center; justify-content: center;
      font-size: .65rem; font-weight: 700; color: var(--gold-dim);
      flex-shrink: 0; overflow: hidden;
    }
    .pp-review-av img { width: 100%; height: 100%; object-fit: cover; }
    .pp-review-name { font-size: .82rem; font-weight: 600; color: var(--text-primary); }
    .pp-review-stars { font-size: .7rem; color: var(--gold); margin-left: auto; }
    .pp-review-date { font-family: var(--font-mono); font-size: .6rem; color: var(--text-faint); }
    .pp-review-text { font-size: .82rem; color: var(--text-muted); line-height: 1.55; }

    /* ── Breadcrumb ── */
    .pv-breadcrumb {
      display: flex; align-items: center; gap: .45rem; flex-wrap: wrap;
      font-size: .75rem; color: var(--text-dim); margin-bottom: 1.25rem;
    }
    .pv-breadcrumb a { color: var(--text-muted); transition: color .15s; }
    .pv-breadcrumb a:hover { color: var(--gold-dim); }
    .pv-breadcrumb span:not([aria-hidden]) { color: var(--text-primary); font-weight: 500; }

    /* ── Empty gallery placeholder ── */
    .pp-gallery-empty {
      background: var(--surface); border: 1.5px dashed var(--border-md);
      border-radius: 12px; padding: 2rem; text-align: center;
      font-size: .8rem; color: var(--text-faint); font-family: var(--font-mono);
      letter-spacing: .06em;
    }

    /* ── Nav logout icon ── */
    .pv-nav-logout-icon {
      width: 34px; height: 34px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      color: var(--text-dim); border: 1px solid transparent; font-size: 1.1rem;
      transition: color .2s, background .2s, border-color .2s, transform .15s;
      flex-shrink: 0;
    }
    .pv-nav-logout-icon:hover { color: var(--red); background: var(--red-soft); border-color: var(--red-border); transform: translateY(-1px); }

    /* ══════════════ TABS ══════════════ */
    .pp-tabs {
      display: flex; gap: 2px; padding: 1.1rem 1.3rem .25rem;
      border-bottom: 1.5px solid var(--card-border);
    }
    .pp-tab {
      display: flex; align-items: center; gap: .45rem;
      padding: .52rem 1rem; border-radius: var(--r-sm);
      font-family: var(--font-body); font-size: .82rem; font-weight: 500;
      color: var(--text-muted); background: transparent; border: none;
      cursor: pointer; transition: all .2s; position: relative;
    }
    .pp-tab:hover { color: var(--text-primary); background: var(--surface-md); }
    .pp-tab.active { color: var(--gold-dim); background: var(--gold-lt); font-weight: 600; }
    .pp-tab.active::after {
      content: ''; position: absolute; bottom: -1px; left: .75rem; right: .75rem;
      height: 2px; background: var(--gold); border-radius: 99px;
    }
    .pp-tab-count {
      font-family: var(--font-mono); font-size: .6rem;
      background: var(--surface-md); border: 1px solid var(--border);
      padding: .1rem .42rem; border-radius: 99px; color: var(--text-dim);
    }
    .pp-tab.active .pp-tab-count { background: var(--gold-soft-md); border-color: var(--gold-border); color: var(--gold-dim); }

    /* ── Panels ── */
    .pp-panel { padding: 1.25rem 1.3rem 1.4rem; }
    .pp-panel--hidden { display: none; }

    /* ══════════════ MASONRY GALLERY ══════════════ */
    .pp-masonry {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      grid-template-rows: repeat(3, 120px);
      gap: 8px;
    }
    .pp-masonry-item {
      border-radius: 10px; overflow: hidden; cursor: pointer;
      position: relative; background: var(--surface);
      border: 1.5px solid var(--card-border);
      transition: transform .2s var(--ease-out), box-shadow .2s;
    }
    .pp-masonry-item:hover { transform: scale(1.02); box-shadow: var(--shadow-md); }
    /* Featured photo: spans 2 rows and 2 cols */
    .pp-masonry-featured {
      grid-column: span 2; grid-row: span 2;
    }
    .pp-masonry-item img {
      width: 100%; height: 100%; object-fit: cover; display: block;
      transition: transform .3s var(--ease-out);
    }
    .pp-masonry-item:hover img { transform: scale(1.05); }
    .pp-masonry-caption {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: linear-gradient(0deg, rgba(0,0,0,.6) 0%, transparent 100%);
      padding: .6rem .65rem .45rem; font-size: .66rem;
      color: rgba(255,255,255,.9); font-family: var(--font-mono);
      opacity: 0; transition: opacity .2s;
    }
    .pp-masonry-item:hover .pp-masonry-caption { opacity: 1; }
    .pp-masonry-zoom {
      position: absolute; top: .5rem; right: .5rem;
      background: rgba(0,0,0,.4); border-radius: 50%;
      width: 28px; height: 28px; display: flex; align-items: center;
      justify-content: center; opacity: 0; transition: opacity .2s;
    }
    .pp-masonry-item:hover .pp-masonry-zoom { opacity: 1; }
    .pp-masonry-more {
      background: var(--surface-md);
      display: flex; align-items: center; justify-content: center;
    }
    .pp-masonry-more img { opacity: .35; }
    .pp-masonry-more-label {
      position: absolute; inset: 0;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: .2rem;
    }
    .pp-masonry-more-label span:first-child {
      font-family: var(--font-display); font-size: 1.5rem; font-weight: 700;
      color: var(--gold-dim);
    }
    .pp-masonry-more-label span:last-child {
      font-family: var(--font-mono); font-size: .58rem;
      color: var(--text-dim); letter-spacing: .08em; text-transform: uppercase;
    }

    /* ══════════════ SERVICE GRID ══════════════ */
    .pp-svc-grid { display: flex; flex-direction: column; gap: 10px; }
    .pp-svc-item {
      border-radius: var(--r-md); padding: 1rem 1.1rem;
      background: var(--surface);
      border: 1.5px solid var(--card-border);
      transition: border-color .2s, box-shadow .2s, background .2s;
      position: relative; overflow: hidden;
    }
    .pp-svc-item::before {
      content: ''; position: absolute; left: 0; top: 0; bottom: 0;
      width: 3.5px; background: var(--accent, var(--gold));
      border-radius: 2px 0 0 2px;
    }
    .pp-svc-item:hover {
      border-color: var(--accent, var(--gold-border));
      box-shadow: 0 4px 20px color-mix(in srgb, var(--accent, #C9A84C) 18%, transparent);
      background: color-mix(in srgb, var(--accent, #C9A84C) 4%, var(--card-bg));
    }
    .pp-svc-item-top {
      display: flex; align-items: flex-start; gap: .85rem; margin-bottom: .75rem;
    }
    .pp-svc-item-icon {
      width: 40px; height: 40px; border-radius: var(--r-sm); flex-shrink: 0;
      background: color-mix(in srgb, var(--accent, #C9A84C) 12%, var(--surface-md));
      border: 1px solid color-mix(in srgb, var(--accent, #C9A84C) 25%, transparent);
      display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .pp-svc-item-info { flex: 1; min-width: 0; }
    .pp-svc-item-name {
      font-family: var(--font-display); font-size: .95rem; font-weight: 600;
      color: var(--text-primary); line-height: 1.3; margin-bottom: .2rem;
    }
    .pp-svc-item-desc {
      font-size: .78rem; color: var(--text-muted); line-height: 1.5;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .pp-svc-item-price { flex-shrink: 0; text-align: right; }
    .pp-svc-item-price-val {
      font-family: var(--font-display); font-size: 1.1rem; font-weight: 700;
      color: var(--accent, var(--gold-dim));
    }
    .pp-svc-item-bottom {
      display: flex; align-items: center; justify-content: space-between;
      gap: .65rem; flex-wrap: wrap;
    }
    .pp-svc-item-tags { display: flex; gap: .35rem; flex-wrap: wrap; align-items: center; }
    .pp-svc-tag {
      display: inline-flex; align-items: center; gap: .3rem;
      font-family: var(--font-mono); font-size: .6rem; letter-spacing: .06em;
      text-transform: uppercase; padding: .22rem .55rem; border-radius: 99px;
      border: 1px solid; white-space: nowrap;
    }
    .pp-svc-tag--type {
      background: color-mix(in srgb, var(--tag-color, #C9A84C) 10%, transparent);
      border-color: color-mix(in srgb, var(--tag-color, #C9A84C) 30%, transparent);
      color: var(--tag-color, var(--gold-dim));
    }
    .pp-svc-tag--loc {
      background: var(--surface-md); border-color: var(--border);
      color: var(--text-muted);
    }
    .pp-svc-tag--dur {
      background: var(--blue-soft); border-color: var(--blue-border);
      color: var(--blue);
    }
    .pp-svc-book-btn {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .48rem 1.05rem; border-radius: var(--r-sm);
      background: var(--accent, var(--gold));
      color: #fff; font-size: .78rem; font-weight: 600;
      text-decoration: none; white-space: nowrap; flex-shrink: 0;
      transition: filter .18s, transform .15s var(--ease-out), box-shadow .18s;
      box-shadow: 0 2px 8px color-mix(in srgb, var(--accent, #C9A84C) 30%, transparent);
    }
    .pp-svc-book-btn:hover {
      filter: brightness(1.1); transform: translateY(-1px);
      box-shadow: 0 4px 14px color-mix(in srgb, var(--accent, #C9A84C) 40%, transparent);
    }

    /* ══════════════ MAP CARD ══════════════ */
    .pp-map-card { overflow: hidden; padding: 0 !important; }
    .pp-map-card .pv-card-head { padding: 1.1rem 1.3rem .9rem; border-bottom: 1px solid var(--border); }
    .pp-map-wrap {
      position: relative; width: 100%; height: 200px; overflow: hidden;
    }
    .pp-map-wrap iframe {
      width: 100%; height: 100%; border: none; display: block;
      filter: saturate(.85) contrast(.95);
    }
    [data-theme="dark"] .pp-map-wrap iframe {
      filter: saturate(.7) contrast(.9) brightness(.85) hue-rotate(180deg);
    }
    .pp-map-pin-overlay {
      position: absolute; top: 50%; left: 50%; transform: translate(-50%, -100%);
      pointer-events: none; filter: drop-shadow(0 2px 6px rgba(0,0,0,.3));
    }
    .pp-map-addr { padding: .9rem 1.15rem 1.1rem; }
    .pp-map-addr-row {
      display: flex; align-items: flex-start; gap: .5rem;
      font-size: .8rem; color: var(--text-muted); margin-bottom: .4rem;
    }
    .pp-map-home { color: var(--green); }
    .pp-map-addr-icon { flex-shrink: 0; margin-top: .05rem; }
    .pp-map-addr-text { line-height: 1.5; }
    .pp-map-directions-btn {
      display: inline-flex; align-items: center; gap: .45rem;
      margin-top: .55rem; padding: .5rem 1rem; border-radius: var(--r-sm);
      background: var(--gold-lt); border: 1.5px solid var(--gold-border);
      color: var(--gold-dim); font-size: .78rem; font-weight: 600;
      text-decoration: none; transition: all .2s;
    }
    .pp-map-directions-btn:hover { background: var(--gold-soft-md); border-color: var(--gold-border-md); }
  </style>
  <script>
    (function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();
  </script>
</head>
<body>

<div class="grain" aria-hidden="true"></div>
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>

<!-- NAV -->
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

      <?php $notifUserId = (int)$customerId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

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

      <div class="pv-nav-av" aria-hidden="true">
        <?php if ($navAvatar): ?>
          <img src="<?= $navAvatar ?>" alt="<?= $userName ?>" style="width:34px;height:34px;object-fit:cover;border-radius:99px;display:block;">
        <?php else: ?>
          <?= $initials ?>
        <?php endif; ?>
      </div>
      <div class="pv-nav-user">
        <div class="pv-nav-user-name"><?= $userName ?></div>
        <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
      </div>

      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout-icon" title="Sign out" aria-label="Sign out">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
      </a>

    </div>
  </div>
</nav>

<!-- FLASH -->
<?php if ($flash): ?>
<div class="pv-flash pv-flash--<?= $flash['type'] ?>" role="alert">
  <span><?= $flash['type'] === 'success' ? '✅' : '⚠️' ?></span>
  <?= htmlspecialchars($flash['msg']) ?>
  <button class="pv-flash-close" onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
</div>
<?php endif; ?>

<!-- HERO -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div class="pv-provider-hero-wrap">
      <?php
        $provPhoto    = $provider['profile_photo'] ?? '';
        $provInitials = strtoupper(substr($provider['first_name'] ?? 'P', 0, 1) . substr($provider['last_name'] ?? 'R', 0, 1));
      ?>
      <div class="pv-provider-av" aria-hidden="true" style="overflow:hidden;background:var(--gold);color:#000;font-weight:800;font-size:1.4rem;display:flex;align-items:center;justify-content:center;">
        <?php if ($provPhoto): ?>
          <img src="<?= htmlspecialchars($provPhoto) ?>"
               alt="<?= htmlspecialchars($provider['first_name']) ?>"
               style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;">
        <?php else: ?>
          <?= $provInitials ?>
        <?php endif; ?>
      </div>
      <div class="pv-provider-info">
        <p class="pv-hero-eyebrow">
          <span class="pv-dot-pulse" aria-hidden="true"></span>
          <?= htmlspecialchars($provider['category_name'] ?? 'Service Provider') ?>
        </p>
        <h1 class="pv-hero-name"><?= htmlspecialchars($provider['business_name']) ?></h1>
        <p style="font-size:.82rem;color:rgba(255,255,255,.45);margin:-.3rem 0 .5rem;">
          <?= htmlspecialchars(trim($provider['first_name'] . ' ' . $provider['last_name'])) ?>
        </p>
        <div class="pv-provider-meta">
          <?php if ($provider['avg_rating'] > 0): ?>
          <span class="pv-meta-chip pv-meta-chip--gold">
            ⭐ <?= number_format($provider['avg_rating'], 1) ?>
            <span class="pv-meta-chip-sub">(<?= (int)$provider['total_reviews'] ?> reviews)</span>
          </span>
          <?php endif; ?>
          <span class="pv-meta-chip">
            📍 <?= htmlspecialchars(implode(', ', array_filter([$provider['barangay'] ?? '', $provider['city'] ?? '']))) ?>
          </span>
          <?php if ($offersHome): ?>
          <span class="pv-meta-chip pv-meta-chip--green">🏠 Home Service Available</span>
          <?php endif; ?>
          <?php if (!empty($galleryPhotos)): ?>
          <span class="pv-meta-chip">🖼️ <?= count($galleryPhotos) ?> portfolio photo<?= count($galleryPhotos) !== 1 ? 's' : '' ?></span>
          <?php endif; ?>
        </div>
        <?php if ($provider['bio']): ?>
        <p class="pv-provider-bio"><?= htmlspecialchars($provider['bio']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="pv-provider-quick-stats">
      <div class="pv-qs-item">
        <span class="pv-qs-val"><?= count($services) ?></span>
        <span class="pv-qs-label">Services</span>
      </div>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val"><?= count($availability) ?></span>
        <span class="pv-qs-label">Days Open</span>
      </div>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val gold"><?= (int)$provider['total_reviews'] ?></span>
        <span class="pv-qs-label">Reviews</span>
      </div>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val">₱<?= $minPrice ? number_format((float)$minPrice, 0) : '—' ?></span>
        <span class="pv-qs-label">Starting Price</span>
      </div>
    </div>
  </div>
</header>

<!-- MAIN -->
<main class="pv-page" role="main">
  <div class="pv-layout">

    <!-- ── LEFT COLUMN ── -->
    <div class="pv-main">

      <!-- Breadcrumb -->
      <nav class="pv-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>browse">Browse</a>
        <span aria-hidden="true">›</span>
        <?php if ($provider['category_name']): ?>
          <a href="<?= BASE_URL ?>browse?category=<?= (int)$provider['category_id'] ?>"><?= htmlspecialchars($provider['category_name']) ?></a>
          <span aria-hidden="true">›</span>
        <?php endif; ?>
        <span><?= htmlspecialchars($provider['business_name']) ?></span>
      </nav>

      <!-- ══ GALLERY SECTION ══ -->
      <!-- ══ INTEGRATED GALLERY + SERVICES CARD ══ -->
      <div class="pv-card pp-svc-card">

        <!-- ── Tab header ── -->
        <div class="pp-tabs">
          <button class="pp-tab active" onclick="switchTab('gallery', this)" id="tabGallery">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><rect x=".7" y=".7" width="5.3" height="5.3" rx="1.2" stroke="currentColor" stroke-width="1.3"/><rect x="8" y=".7" width="5.3" height="5.3" rx="1.2" stroke="currentColor" stroke-width="1.3"/><rect x=".7" y="8" width="5.3" height="5.3" rx="1.2" stroke="currentColor" stroke-width="1.3"/><rect x="8" y="8" width="5.3" height="5.3" rx="1.2" stroke="currentColor" stroke-width="1.3"/></svg>
            Portfolio
            <span class="pp-tab-count"><?= count($galleryPhotos) ?></span>
          </button>
          <button class="pp-tab" onclick="switchTab('services', this)" id="tabServices">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M1 3h12M1 7h12M1 11h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            Services
            <span class="pp-tab-count"><?= count($services) ?></span>
          </button>
        </div>

        <!-- ── Gallery panel ── -->
        <div class="pp-panel" id="panelGallery">
          <?php if (empty($galleryPhotos)): ?>
            <div class="pp-gallery-empty">
              <div style="font-size:2rem;margin-bottom:.5rem;">📷</div>
              No portfolio photos yet
            </div>
          <?php else:
            $displayPhotos = array_slice($galleryPhotos, 0, 9);
            $extraCount    = count($galleryPhotos) - 9;
          ?>
          <div class="pp-masonry" id="galleryGrid">
            <?php foreach ($displayPhotos as $idx => $photo):
              $isLast = ($idx === count($displayPhotos) - 1) && $extraCount > 0;
            ?>
            <?php if ($isLast): ?>
              <div class="pp-masonry-item pp-masonry-more"
                   onclick="openLightbox(<?= $idx ?>)" role="button" tabindex="0">
                <img src="<?= htmlspecialchars($photo['image_url']) ?>" alt="" loading="lazy">
                <div class="pp-masonry-more-label">
                  <span>+<?= $extraCount + 1 ?></span>
                  <span>more photos</span>
                </div>
              </div>
            <?php else: ?>
              <div class="pp-masonry-item <?= $idx === 0 ? 'pp-masonry-featured' : '' ?>"
                   onclick="openLightbox(<?= $idx ?>)" role="button" tabindex="0"
                   aria-label="View photo <?= $idx + 1 ?>">
                <img src="<?= htmlspecialchars($photo['image_url']) ?>"
                     alt="<?= htmlspecialchars($photo['caption'] ?? '') ?>"
                     loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>">
                <?php if ($photo['caption']): ?>
                  <div class="pp-masonry-caption"><?= htmlspecialchars($photo['caption']) ?></div>
                <?php endif; ?>
                <div class="pp-masonry-zoom" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.5" stroke="white" stroke-width="1.5"/><path d="M10.5 10.5L14 14" stroke="white" stroke-width="1.5" stroke-linecap="round"/></svg>
                </div>
              </div>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- ── Services panel ── -->
        <div class="pp-panel pp-panel--hidden" id="panelServices">
          <?php if (empty($services)): ?>
          <div class="pv-empty-state" style="padding:2.5rem 1.5rem;">
            <div class="pv-empty-icon" aria-hidden="true">🛠️</div>
            <p>No services listed yet.</p>
            <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Back to Browse →</a>
          </div>
          <?php else: ?>
          <div class="pp-svc-grid" role="list" id="serviceList">
            <?php
            $locIcons = [
              'In-shop'  => '<svg width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 5.5 L6 1.5 L10 5.5 V10.5 H7.5 V7.5 H4.5 V10.5 H2 Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" fill="none"/></svg>',
              'On-site'  => '<svg width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M6 1C4.07 1 2.5 2.57 2.5 4.5c0 2.8 3.5 6.5 3.5 6.5s3.5-3.7 3.5-6.5C9.5 2.57 7.93 1 6 1z" stroke="currentColor" stroke-width="1.3"/><circle cx="6" cy="4.5" r="1.2" stroke="currentColor" stroke-width="1.2"/></svg>',
              'Flexible' => '<svg width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 6h2.5M7.5 6H10M6 2v2.5M6 7.5V10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="6" cy="6" r="1.5" stroke="currentColor" stroke-width="1.3"/></svg>',
              'Remote'   => '<svg width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true"><rect x="1.5" y="3" width="9" height="6" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M4 9.5L6 11L8 9.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            ];
            $colorAccents = [
              'blue'   => '#2563EB', 'purple' => '#7C3AED', 'pink'   => '#DB2777',
              'green'  => '#16A34A', 'yellow' => '#D97706', 'orange' => '#EA580C',
              'teal'   => '#0D9488', 'gold'   => '#C9A84C',
            ];
            foreach ($services as $idx => $s):
              $color     = $serviceTypeColors[$s['service_type'] ?? ''] ?? 'gold';
              $accentHex = $colorAccents[$color] ?? '#C9A84C';
              $locType   = $s['location_type'] ?? 'In-shop';
              $locIcon   = $locIcons[$locType] ?? $locIcons['In-shop'];
              $mins      = (int)($s['duration_minutes'] ?? 0);
              $dur       = $mins >= 60
                ? (($mins % 60 === 0) ? ($mins/60).'h' : floor($mins/60).'h '.($mins%60).'m')
                : $mins.'m';
            ?>
            <div class="pp-svc-item" role="listitem"
                 style="--accent:<?= $accentHex ?>;">
              <div class="pp-svc-item-top">
                <div class="pp-svc-item-icon" aria-hidden="true">
                  <?= $catEmoji ?>
                </div>
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
                  <span class="pp-svc-tag pp-svc-tag--type"
                        style="--tag-color:<?= $accentHex ?>;">
                    <?= htmlspecialchars($s['service_type']) ?>
                  </span>
                  <?php endif; ?>
                  <span class="pp-svc-tag pp-svc-tag--loc">
                    <?= $locIcon ?> <?= htmlspecialchars($locType) ?>
                  </span>
                  <?php if ($mins): ?>
                  <span class="pp-svc-tag pp-svc-tag--dur">
                    <svg width="11" height="11" viewBox="0 0 12 12" fill="none" aria-hidden="true"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.3"/><path d="M6 3.5V6L7.5 7.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?= $dur ?>
                  </span>
                  <?php endif; ?>
                </div>
                <a href="<?= BASE_URL ?>services/<?= (int)$s['id'] ?>"
                   class="pp-svc-book-btn js-book-link"
                   data-service-id="<?= (int)$s['id'] ?>"
                   aria-label="Book <?= htmlspecialchars($s['name']) ?>">
                  Book Now
                  <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 6h7M7 3.5L9.5 6 7 8.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

      </div>

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

      <!-- ══ SERVICES CARD ══ -->
      <div class="pv-card">
        <div class="pv-card-head">
          <div>
            <h2>Services Offered</h2>
            <span class="pv-card-sub"><?= count($services) ?> active service<?= count($services) !== 1 ? 's' : '' ?></span>
          </div>
        </div>

        <?php if (empty($services)): ?>
        <div class="pv-empty-state">
          <div class="pv-empty-icon" aria-hidden="true">🛠️</div>
          <p>This provider hasn't listed any services yet.</p>
          <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Back to Browse →</a>
        </div>
        <?php else: ?>
        <div class="pv-service-list" role="list" id="serviceList">
          <?php foreach ($services as $s):
            $color = $serviceTypeColors[$s['service_type'] ?? ''] ?? 'gold';
          ?>
          <div class="pv-service-item" role="listitem">
            <div class="pv-service-accent pv-service-accent--<?= $color ?>"></div>
            <div class="pv-service-info">
              <div class="pv-service-name"><?= htmlspecialchars($s['name']) ?></div>
              <?php if ($s['description']): ?>
                <div class="pv-service-desc"><?= htmlspecialchars($s['description']) ?></div>
              <?php endif; ?>
              <div class="pv-service-tags">
                <?php if (!empty($s['service_type'])): ?>
                  <span class="pv-stag pv-stag--type"><?= htmlspecialchars($s['service_type']) ?></span>
                <?php endif; ?>
                <?php if (!empty($s['location_type'])): ?>
                  <span class="pv-stag pv-stag--loc">📍 <?= htmlspecialchars($s['location_type']) ?></span>
                <?php endif; ?>
                <?php if (!empty($s['duration_minutes'])): ?>
                  <span class="pv-stag pv-stag--dur">⏱ <?= (int)$s['duration_minutes'] ?> min</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="pv-service-right">
              <div class="pv-service-price">₱<?= number_format((float)$s['price'], 2) ?></div>
              <a href="<?= BASE_URL ?>services/<?= (int)$s['id'] ?>"
                 class="pv-book-btn js-book-link"
                 data-service-id="<?= (int)$s['id'] ?>"
                 aria-label="Book <?= htmlspecialchars($s['name']) ?>">
                Book Now
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- ══ REVIEWS CARD ══ -->
      <?php if (!empty($reviews)): ?>
      <div class="pv-card pp-reviews-section">
        <div class="pv-card-head">
          <div>
            <h2>Recent Reviews</h2>
            <span class="pv-card-sub"><?= (int)$provider['total_reviews'] ?> total review<?= (int)$provider['total_reviews'] !== 1 ? 's' : '' ?></span>
          </div>
        </div>
        <?php foreach ($reviews as $rev): ?>
        <div class="pp-review-item">
          <div class="pp-review-head">
            <div class="pp-review-av">
              <?php if ($rev['avatar_url']): ?>
                <img src="<?= htmlspecialchars($rev['avatar_url']) ?>" alt="">
              <?php else: ?>
                <?= strtoupper(substr($rev['first_name'], 0, 1) . substr($rev['last_name'], 0, 1)) ?>
              <?php endif; ?>
            </div>
            <div>
              <div class="pp-review-name"><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></div>
              <div class="pp-review-date"><?= date('M j, Y', strtotime($rev['created_at'])) ?></div>
            </div>
            <div class="pp-review-stars"><?= renderStars((float)$rev['rating']) ?> <?= number_format((float)$rev['rating'], 1) ?></div>
          </div>
          <?php if (!empty($rev['review_text'])): ?>
            <div class="pp-review-text"><?= htmlspecialchars($rev['review_text']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div><!-- /pv-main -->

    <!-- ── SIDEBAR ── -->
    <aside class="pv-sidebar" aria-label="Provider details">

      <!-- Availability -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Availability</h2></div>
        <?php if (empty($availability)): ?>
          <div class="pv-empty-state" style="padding:1.5rem">
            <p style="font-size:.82rem;color:var(--text-dim)">No schedule set yet.</p>
          </div>
        <?php else: ?>
        <div class="pv-avail-list">
          <?php foreach ($availability as $av): ?>
          <div class="pv-avail-item">
            <span class="pv-avail-day"><?= htmlspecialchars($av['day_of_week']) ?></span>
            <span class="pv-avail-time">
              <?= date('g:i A', strtotime($av['start_time'])) ?> – <?= date('g:i A', strtotime($av['end_time'])) ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Location + Google Maps -->
      <div class="pv-card pp-map-card">
        <div class="pv-card-head"><h2>Location</h2></div>
        <?php
          $addrParts = array_filter([
            $provider['address']  ?? '',
            $provider['barangay'] ?? '',
            $provider['city']     ?? '',
          ]);
          $fullAddress = implode(', ', $addrParts);
          $mapQuery    = urlencode($fullAddress ?: ($provider['city'] ?? 'Bacolod City'));
        ?>
        <!-- Google Maps embed (no API key needed) -->
        <div class="pp-map-wrap">
          <iframe
            title="Shop location map"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            src="https://maps.google.com/maps?q=<?= $mapQuery ?>&output=embed&z=15"
            allowfullscreen>
          </iframe>
          <div class="pp-map-pin-overlay" aria-hidden="true">
            <svg width="20" height="26" viewBox="0 0 20 26" fill="none"><path d="M10 0C4.48 0 0 4.48 0 10c0 7.5 10 16 10 16s10-8.5 10-16C20 4.48 15.52 0 10 0z" fill="var(--gold)"/><circle cx="10" cy="10" r="4" fill="white"/></svg>
          </div>
        </div>
        <div class="pp-map-addr">
          <div class="pp-map-addr-row">
            <span class="pp-map-addr-icon">📍</span>
            <span class="pp-map-addr-text"><?= htmlspecialchars($fullAddress ?: 'Bacolod City') ?></span>
          </div>
          <?php if ($offersHome): ?>
          <div class="pp-map-addr-row pp-map-home">
            <span class="pp-map-addr-icon">🏠</span>
            <span class="pp-map-addr-text">Home service available</span>
          </div>
          <?php endif; ?>
          <?php if ($fullAddress): ?>
          <a href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>"
             target="_blank" rel="noopener noreferrer"
             class="pp-map-directions-btn">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1L13 7L7 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M1 7h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            Get Directions
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Booking CTA -->
      <?php if (!empty($services)): ?>
      <div class="pv-card pv-card--cta">
        <div class="pv-cta-body">
          <div class="pv-cta-title">Ready to book?</div>
          <div class="pv-cta-sub">Choose a service and pick your preferred time.</div>
          <a href="<?= BASE_URL ?>services/<?= (int)$services[0]['id'] ?>"
             class="pv-cta-btn js-cta-link"
             id="ctaBookBtn">
            Book a Service
          </a>
        </div>
      </div>
      <?php endif; ?>

    </aside>

  </div>
</main>

<!-- LIGHTBOX -->
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
const galleryPhotos = <?= json_encode(array_map(fn($p) => [
    'url'     => $p['image_url'],
    'caption' => $p['caption'] ?? ''
], $galleryPhotos)) ?>;
let currentIdx = 0;

function openLightbox(idx) {
    currentIdx = idx % galleryPhotos.length;
    updateLightbox();
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}
function updateLightbox() {
    const p = galleryPhotos[currentIdx];
    document.getElementById('lightboxImg').src = p.url;
    document.getElementById('lightboxImg').alt = p.caption || 'Photo ' + (currentIdx + 1);
    document.getElementById('lightboxCaption').textContent = p.caption || '';
    document.getElementById('lightboxCounter').textContent = (currentIdx + 1) + ' / ' + galleryPhotos.length;
}
function nextPhoto() { currentIdx = (currentIdx + 1) % galleryPhotos.length; updateLightbox(); }
function prevPhoto() { currentIdx = (currentIdx - 1 + galleryPhotos.length) % galleryPhotos.length; updateLightbox(); }

document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});
document.addEventListener('keydown', function(e) {
    if (!document.getElementById('lightbox').classList.contains('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowRight') nextPhoto();
    if (e.key === 'ArrowLeft')  prevPhoto();
});
</script>
<?php endif; ?>

<!-- BOOKING TOGGLE JS -->
<?php if ($showToggle || $offersHome): ?>
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
        btnHome?.classList.add('active', 'home-active');
        addrRow?.classList.add('visible');
        addrInput?.focus();
    } else {
        btnHome?.classList.remove('active', 'home-active');
        btnShop?.classList.add('active');
        addrRow?.classList.remove('visible');
    }

    updateBookLinks();
}

function updateBookLinks() {
    const links = document.querySelectorAll('.js-book-link, .pp-svc-book-btn, .js-cta-link');
    links.forEach(function(link) {
        const base = link.getAttribute('href').split('?')[0];
        const svcId = link.dataset.serviceId;
        const addr  = document.getElementById('homeAddress')?.value || '';
        const params = new URLSearchParams();
        params.set('loc', currentLocType);
        if (currentLocType === 'home' && addr) params.set('addr', addr);
        link.setAttribute('href', base + '?' + params.toString());
    });
}

// Update links when address changes
document.getElementById('homeAddress')?.addEventListener('input', updateBookLinks);
</script>
<?php endif; ?>

<!-- Tab switching -->
<script>
function switchTab(name, btn) {
    document.querySelectorAll('.pp-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.pp-panel').forEach(p => p.classList.add('pp-panel--hidden'));
    btn.classList.add('active');
    document.getElementById('panel' + name.charAt(0).toUpperCase() + name.slice(1))
            .classList.remove('pp-panel--hidden');
}
</script>

<!-- Theme toggle -->
<script>
(function () {
  var btn  = document.querySelector('.pv-theme-toggle');
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

</body>
</html>