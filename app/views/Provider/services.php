<?php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$providerId   = $_SESSION['user_id'] ?? 0;
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');

$stmt = $db->prepare("SELECT * FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$providerId]);
$profile   = $stmt->fetch();
$profileId = $profile['id'] ?? 0;

/* ── Pending bookings for nav badge ── */
$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$profileId]);
$pendingBookings = (int)$stmt->fetchColumn();

/* ── Service stats ── */
$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_services WHERE provider_id = ?");
$stmt->execute([$profileId]);
$totalServices = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_services WHERE provider_id = ? AND is_active = 1");
$stmt->execute([$profileId]);
$activeServices = (int)$stmt->fetchColumn();

/* ── Total bookings across all services ── */
$stmt = $db->prepare("
    SELECT COUNT(*) FROM tbl_bookings
    WHERE provider_id = ?
");
$stmt->execute([$profileId]);
$totalServiceBookings = (int)$stmt->fetchColumn();

/* ── Starting price ── */
$stmt = $db->prepare("SELECT COALESCE(MIN(price), 0) FROM tbl_services WHERE provider_id = ? AND is_active = 1");
$stmt->execute([$profileId]);
$minPrice = round((float)$stmt->fetchColumn(), 2);

/* ── Most booked service ── */
$stmt = $db->prepare("
    SELECT s.name, COUNT(b.id) as booking_count
    FROM tbl_services s
    LEFT JOIN tbl_bookings b ON s.id = b.service_id AND b.status IN ('completed', 'confirmed')
    WHERE s.provider_id = ?
    GROUP BY s.id, s.name
    ORDER BY booking_count DESC
    LIMIT 1
");
$stmt->execute([$profileId]);
$mostBooked        = $stmt->fetch();
$mostBookedService = $mostBooked['name'] ?? 'N/A';
$mostBookedCount   = (int)($mostBooked['booking_count'] ?? 0);

/* ── Fetch services with per-service booking counts ── */
$typeFilter = $_GET['type'] ?? 'all';
$search     = trim($_GET['q'] ?? '');

$where  = "s.provider_id = :pid";
$params = [':pid' => $profileId];
if ($typeFilter !== 'all') {
    $where .= " AND s.service_type = :type";
    $params[':type'] = $typeFilter;
}
if ($search !== '') {
    $where .= " AND (s.name LIKE :q OR s.description LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

$stServices = $db->prepare("
    SELECT s.*,
        COUNT(DISTINCT CASE WHEN b.status IN ('completed','confirmed','pending') THEN b.id END) AS booking_count
    FROM tbl_services s
    LEFT JOIN tbl_bookings b ON s.id = b.service_id
    WHERE $where
    GROUP BY s.id
    ORDER BY s.is_active DESC, booking_count DESC, s.created_at DESC
");
$stServices->execute($params);
$services = $stServices->fetchAll();

/* ── Distinct service types for filter ── */
$stTypes = $db->prepare("SELECT DISTINCT service_type FROM tbl_services WHERE provider_id = ? AND service_type IS NOT NULL ORDER BY service_type");
$stTypes->execute([$profileId]);
$serviceTypes = $stTypes->fetchAll(PDO::FETCH_COLUMN);

/* ── Flash ── */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$initials     = strtoupper(substr($providerName, 0, 2));

/* ── Extra variables for dashboard-style nav ── */
$email        = htmlspecialchars($_SESSION['user_email'] ?? '');
$bizName      = htmlspecialchars($profile['business_name'] ?? $providerName);
$firstName    = htmlspecialchars(explode(' ', $providerName)[0]);
$profilePhoto = $profile['profile_photo'] ?? null;
$navInitials  = strtoupper(substr($bizName, 0, 2));

$accentMap = [
    'Barber'        => 'blue',
    'Hair Stylist'  => 'indigo',
    'Nail Tech'     => 'red',
    'Massage'       => 'green',
    'Skincare'      => 'indigo',
    'Fitness'       => 'amber',
    'Home Cleaning' => 'green',
    'Pet Groomer'   => 'amber',
    'Event Stylist' => 'red',
    'Makeup'        => 'red',
];
$imageMap = [
    'Barber'        => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=80&h=80&fit=crop&q=70',
    'Hair Stylist'  => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=80&h=80&fit=crop&q=70',
    'Nail Tech'     => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=80&h=80&fit=crop&q=70',
    'Massage'       => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=80&h=80&fit=crop&q=70',
    'Skincare'      => 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=80&h=80&fit=crop&q=70',
    'Fitness'       => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=80&h=80&fit=crop&q=70',
    'Home Cleaning' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=80&h=80&fit=crop&q=70',
    'Pet Groomer'   => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=80&h=80&fit=crop&q=70',
    'Event Stylist' => 'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=80&h=80&fit=crop&q=70',
    'Makeup'        => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=80&h=80&fit=crop&q=70',
];

/* Readable service mode labels */
$modeLabels = [
    'On-site'  => 'Home Service',
    'In-shop'  => 'In-Shop',
    'Remote'   => 'Online',
    'Flexible' => 'Flexible',
];

function serviceAccent($type, $map) { return $map[$type] ?? 'gold'; }
function serviceImage($type, $map)  { return $map[$type] ?? 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=80&h=80&fit=crop&q=70'; }
function modeLabel($val, $map)      { return $map[$val]  ?? $val; }
function shortName(string $s, int $max = 15): string {
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — My Services</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_services.css">
  <!-- New additions that extend provider_services.css — paste rules into that file or keep inline -->
  <style>
    /* ── Featured badge on card ── */
    .sv-card { position: relative; }
    .sv-featured-badge {
      position: absolute; top: .7rem; left: .75rem; z-index: 2;
      display: inline-flex; align-items: center; gap: .3rem;
      font-family: var(--font-m); font-size: .56rem; font-weight: 600;
      letter-spacing: .08em; text-transform: uppercase;
      padding: .22rem .62rem; border-radius: 99px;
      background: linear-gradient(135deg, rgba(201,168,76,.22), rgba(201,168,76,.12));
      border: 1px solid var(--gold-border-md); color: var(--gold-dim);
      pointer-events: none;
    }
    .sv-featured-badge svg { flex-shrink: 0; }
    .sv-card.is-featured { border-color: rgba(201,168,76,.48); }
    .sv-card.is-featured::after {
      content: ''; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
      box-shadow: 0 0 0 1.5px rgba(201,168,76,.22) inset;
    }

    /* Featured row in list view */
    .sv-table tbody tr.is-featured-row { background: rgba(201,168,76,.04); }

    /* Booking count under service name in card */
    .sv-card-bookings {
      font-family: var(--font-m); font-size: .62rem; font-weight: 500;
      color: var(--text-dim); margin-top: .15rem;
      display: flex; align-items: center; gap: .25rem;
    }
    .sv-card-bookings::before {
      content: ''; display: inline-block;
      width: 5px; height: 5px; border-radius: 99px;
      background: var(--text-faint); flex-shrink: 0;
    }

    /* Star / featured icon button state */
    .sv-icon-btn.is-featured       { color: var(--text-faint); }
    .sv-icon-btn.is-featured svg   { fill: var(--text-faint); }
    .sv-icon-btn.is-featured:hover, .sv-icon-btn.is-featured.is-active {
      color: var(--gold); background: var(--gold-lt); border-color: var(--gold-border);
    }
    .sv-icon-btn.is-featured:hover svg,
    .sv-icon-btn.is-featured.is-active svg { fill: var(--gold); }

    /* "View Profile" button in toolbar */
    .sv-view-profile-btn {
      display: inline-flex; align-items: center; gap: .45rem;
      padding: .6rem 1.1rem; border-radius: var(--r-sm);
      font-family: var(--font-m); font-size: .7rem; font-weight: 500; letter-spacing: .06em;
      text-transform: uppercase; color: var(--text-muted);
      background: rgba(255,255,255,.55); backdrop-filter: blur(12px);
      border: 1.5px solid rgba(255,255,255,.70);
      transition: color .2s, border-color .2s, background .2s;
      white-space: nowrap;
    }
    .sv-view-profile-btn:hover {
      color: var(--gold-dim); border-color: var(--gold-border-md); background: var(--gold-lt);
    }
    .sv-view-profile-btn svg { width: 14px; height: 14px; flex-shrink: 0; }

    /* Most booked inline stat pill under hero ── */
    .sv-most-booked-pill {
      display: inline-flex; align-items: center; gap: .45rem;
      font-family: var(--font-m); font-size: .65rem; letter-spacing: .06em;
      color: var(--gold-dim); padding: .28rem .75rem; border-radius: 99px;
      background: var(--gold-lt); border: 1px solid var(--gold-border); margin-top: .3rem;
    }

    /* "Bookings" column in list table: styled count */
    td .sv-booking-count {
      display: inline-flex; align-items: center; gap: .3rem;
      font-family: var(--font-m); font-size: .75rem; font-weight: 600;
      color: var(--text-primary);
    }
    td .sv-booking-count span {
      font-size: .6rem; color: var(--text-dim); font-weight: 400;
    }
    td .sv-booking-count.is-hot { color: var(--gold-dim); }

    /* Service mode chip in meta row — replaces plain location text */
    .sv-meta-chip.is-mode {
      color: var(--blue); background: var(--blue-soft);
      border-color: var(--blue-border);
    }

    /* Card body spacing when featured badge is present */
    .sv-card.is-featured .sv-card-body { padding-top: 1.6rem; }

    /* ── Profile dropdown (ported from provider_dashboard.css) ── */
    .pv-nav-end { position: relative; }
    .pv-profile-trigger {
      display: flex; align-items: center; gap: .65rem;
      padding: .3rem .55rem .3rem .3rem; border-radius: 99px;
      border: 1px solid transparent; cursor: pointer; position: relative;
      transition: background .2s, border-color .2s; user-select: none;
    }
    .pv-profile-trigger:hover, .pv-profile-trigger.is-open {
      background: var(--surface-md); border-color: var(--gold-border);
    }
    .pv-profile-chevron {
      color: var(--text-dim); transition: transform .25s, color .2s; flex-shrink: 0;
    }
    .pv-profile-trigger.is-open .pv-profile-chevron { transform: rotate(180deg); color: var(--gold-dim); }

    .pv-profile-dropdown {
      position: absolute; top: calc(100% + 10px); right: 0; width: 260px;
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(28px) saturate(1.8); -webkit-backdrop-filter: blur(28px) saturate(1.8);
      border: 1.5px solid rgba(255,255,255,0.80); border-top: 1.5px solid rgba(255,255,255,0.95);
      border-left: 1.5px solid rgba(255,255,255,0.40); border-radius: var(--r-xl);
      box-shadow: 0 20px 60px rgba(139,110,60,.18), 0 4px 16px rgba(139,110,60,.10);
      z-index: 900; opacity: 0; transform: translateY(-8px) scale(0.97); pointer-events: none;
      transition: opacity .22s, transform .22s; overflow: hidden;
    }
    .pv-profile-dropdown.is-open { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }

    .pv-pd-header {
      display: flex; align-items: center; gap: .85rem; padding: 1.1rem 1.2rem 1rem;
      background: linear-gradient(135deg, #FBF6EC 0%, #F5EDDA 100%);
    }
    .pv-pd-avatar {
      width: 44px; height: 44px; border-radius: 99px; flex-shrink: 0;
      background: linear-gradient(135deg, var(--gold-dim), var(--gold));
      color: #fff8e8; font-family: var(--font-display); font-weight: 700; font-size: .88rem;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 0 2.5px var(--gold-border), 0 3px 12px rgba(201,168,76,.28);
      overflow: hidden;
    }
    .pv-pd-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 99px; }
    .pv-pd-info { min-width: 0; flex: 1; }
    .pv-pd-name {
      font-family: var(--font-display); font-size: .9rem; font-weight: 700;
      color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pv-pd-email {
      font-family: var(--font-mono); font-size: .6rem; color: var(--text-muted); margin-top: .1rem;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pv-pd-role {
      display: inline-block; margin-top: .3rem;
      font-family: var(--font-mono); font-size: .52rem; font-weight: 500;
      letter-spacing: .08em; text-transform: uppercase;
      background: var(--gold-lt); color: var(--gold-dim);
      border: 1px solid var(--gold-border); padding: .14rem .5rem; border-radius: 99px;
    }
    .pv-pd-divider {
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(201,168,76,.25) 30%, rgba(201,168,76,.25) 70%, transparent);
    }
    .pv-pd-item {
      display: flex; align-items: center; gap: .75rem; padding: .82rem 1.2rem;
      font-size: .84rem; font-weight: 500; color: var(--text-primary);
      transition: background .15s, color .15s; cursor: pointer; text-decoration: none;
    }
    .pv-pd-item:hover { background: rgba(201,168,76,.07); color: var(--gold-dim); }
    .pv-pd-item--danger { color: var(--text-muted); }
    .pv-pd-item--danger:hover { background: var(--red-soft); color: var(--red); }
    .pv-pd-item-ico {
      width: 30px; height: 30px; border-radius: var(--r-sm); flex-shrink: 0;
      background: linear-gradient(135deg, #FBF6EC, #F0E7CC); border: 1px solid var(--gold-border);
      display: flex; align-items: center; justify-content: center; font-size: .8rem; color: var(--gold-dim);
      transition: background .15s, border-color .15s;
    }
    .pv-pd-item--danger .pv-pd-item-ico { background: var(--red-soft); border-color: var(--red-border); color: var(--red); }
    .pv-pd-item-arrow { margin-left: auto; color: var(--text-dim); flex-shrink: 0; }

    [data-theme="dark"] .pv-profile-dropdown {
      background: rgba(18,24,38,.95); border-color: rgba(255,255,255,.08);
      box-shadow: 0 20px 60px rgba(0,0,0,.50), 0 4px 16px rgba(0,0,0,.30);
    }
    [data-theme="dark"] .pv-pd-header { background: linear-gradient(135deg, rgba(28,22,10,.95) 0%, rgba(20,16,8,.98) 100%); }
    [data-theme="dark"] .pv-pd-item:hover { background: rgba(201,168,76,.08); }
    [data-theme="dark"] .pv-pd-item--danger:hover { background: var(--red-soft); }
    [data-theme="dark"] .pv-pd-item-ico { background: linear-gradient(135deg, rgba(38,30,14,.90) 0%, rgba(50,40,18,.90) 100%); border-color: var(--gold-border); }
    [data-theme="dark"] .sv-featured-badge {
      background: linear-gradient(135deg, rgba(201,168,76,.18), rgba(201,168,76,.08));
    }
    [data-theme="dark"] .sv-card.is-featured { border-color: rgba(201,168,76,.35); }
    [data-theme="dark"] .sv-view-profile-btn {
      background: rgba(255,255,255,.06); border-color: var(--gold-border); color: var(--text-muted);
    }
    [data-theme="dark"] .sv-table tbody tr.is-featured-row { background: rgba(201,168,76,.05); }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>(function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();</script>
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAVBAR  (dashboard-style)
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">

    <!-- Logo -->
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo"
           style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Provider</span>
    </a>

    <!-- Centre nav links -->
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/appointments" class="pv-nav-link">
        Appointments
        <?php if ($pendingBookings): ?><sup class="pv-sup"><?= $pendingBookings ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"     class="pv-nav-link is-active">Services</a>
      <a href="<?= BASE_URL ?>provider/portfolio"    class="pv-nav-link">Portfolio</a>
      <a href="<?= BASE_URL ?>provider/schedule"     class="pv-nav-link">Schedule</a>
    </div>

    <!-- Right-side controls -->
    <div class="pv-nav-end">
      <?php $notifUserId = (int)$providerId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <!-- Theme toggle -->
      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22"  y1="4.22"  x2="5.64"  y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1"  y1="12" x2="3"  y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22"  y1="19.78" x2="5.64"  y2="18.36"/><line x1="18.36" y1="5.64"  x2="19.78" y2="4.22"/>
        </svg>
      </button>

      <!-- Profile dropdown trigger -->
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0"
           aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av">
          <?php if ($profilePhoto): ?>
            <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
          <?php else: ?>
            <?= $navInitials ?>
          <?php endif; ?>
        </div>
        <div class="pv-nav-user">
          <div class="pv-nav-user-name"><?= $firstName ?></div>
        </div>
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

      <!-- Profile dropdown panel -->
      <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="pv-pd-header">
          <div class="pv-pd-avatar">
            <?php if ($profilePhoto): ?>
              <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
            <?php else: ?>
              <?= $navInitials ?>
            <?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $bizName ?></div>
            <div class="pv-pd-email"><?= $email ?></div>
            <span class="pv-pd-role">Provider</span>
          </div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>provider/profile" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-store"></i></span>
          <span>Business Profile</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="<?= BASE_URL ?>provider/settings" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-gear"></i></span>
          <span>Settings</span>
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
  </div>
</nav>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Service Catalogue
      </p>
      <h1 class="pv-hero-title">Your <em>Service</em> Menu</h1>
      <p class="pv-hero-sub">Build your business catalogue. Manage pricing, availability, and showcase your best work to customers.</p>
      <?php if ($mostBookedCount > 0): ?>
      <div class="sv-most-booked-pill">
        <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" width="10" height="10"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Most booked: <?= htmlspecialchars($mostBookedService) ?> &mdash; <?= $mostBookedCount ?> bookings
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Updated stat strip ── -->
  <div class="pv-hero-stats">
    <div class="pv-hs-item">
      <span class="pv-hs-val"><?= $totalServices ?></span>
      <span class="pv-hs-label">Total Services</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-green"><?= $activeServices ?></span>
      <span class="pv-hs-label">Active</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-red"><?= $totalServices - $activeServices ?></span>
      <span class="pv-hs-label">Inactive</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <!-- CHANGED: was Avg Price, now Total Bookings -->
      <span class="pv-hs-val pv-hs-blue"><?= number_format($totalServiceBookings) ?></span>
      <span class="pv-hs-label">Total Bookings</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-gold">₱<?= number_format($minPrice, 2) ?></span>
      <span class="pv-hs-label">Starting From</span>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     PAGE
══════════════════════════════════════ -->
<main class="sv-page" role="main">

  <?php if ($flash): ?>
    <div class="pv-flash pv-flash--<?= $flash['type'] ?>">
      <?= $flash['type'] === 'success' ? '✓' : '✕' ?>
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- ── TOOLBAR (improved) ── -->
  <div class="sv-toolbar" role="toolbar">
    <div class="sv-toolbar-left">
      <form method="GET" action="" style="display:contents">
        <div class="sv-search-wrap">
          <svg class="sv-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            type="search" name="q" value="<?= htmlspecialchars($search) ?>"
            class="sv-search" placeholder="Search services…" autocomplete="off"
            oninput="this.form.submit()"
          >
        </div>
        <?php if (!empty($serviceTypes)): ?>
        <!-- CHANGED: "All Types" → "All Services"; filter label improved -->
        <select name="type" class="sv-filter-select" onchange="this.form.submit()">
          <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Services</option>
          <?php foreach ($serviceTypes as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $typeFilter === $t ? 'selected' : '' ?>>
              <?= htmlspecialchars($t) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
      </form>
    </div>
    <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
      <!-- View toggle -->
      <div class="sv-view-toggle" role="group" aria-label="View mode">
        <button class="sv-view-btn is-active" id="btn-grid" onclick="setView('grid')" title="Grid view">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        </button>
        <button class="sv-view-btn" id="btn-list" onclick="setView('list')" title="List view">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </button>
      </div>
      <!-- NEW: View public profile button -->
      <a href="<?= BASE_URL ?>provider/profile" class="sv-view-profile-btn" title="See how customers see your profile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        View Profile
      </a>
      <!-- Add Service -->
      <button class="sv-add-btn" onclick="openAddModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Service
      </button>
    </div>
  </div>

  <!-- ══════════════════════════════════════
       GRID VIEW (improved)
  ══════════════════════════════════════ -->
  <div class="sv-grid" id="view-grid">
    <?php if (empty($services)): ?>
      <div class="sv-empty">
        <div class="sv-empty-icon">
          <img src="https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=60&h=60&fit=crop" alt="" style="width:36px;height:36px;border-radius:6px;object-fit:cover;opacity:.6">
        </div>
        <h3>No services yet</h3>
        <p>Add your first service to start accepting bookings from customers.</p>
      </div>
    <?php else: ?>
      <?php foreach ($services as $i => $svc):
        $accent       = serviceAccent($svc['service_type'] ?? '', $accentMap);
        $imgSrc       = serviceImage($svc['service_type'] ?? '', $imageMap);
        $active       = (bool)($svc['is_active']  ?? true);
        $featured     = false; // TODO: run ALTER TABLE tbl_services ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0
        $bookingCount = (int)($svc['booking_count'] ?? 0);
        $mode         = modeLabel($svc['location_type'] ?? '', $modeLabels);
      ?>
      <article
        class="sv-card <?= $active ? '' : 'is-inactive' ?> <?= $featured ? 'is-featured' : '' ?>"
        style="animation-delay:<?= $i * 0.04 ?>s"
      >
        <!-- Accent stripe -->
        <div class="sv-card-accent accent-<?= $accent ?>"></div>

        <!-- ADDED: Featured badge -->
        <?php if ($featured): ?>
        <div class="sv-featured-badge">
          <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" width="10" height="10">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
          </svg>
          Featured
        </div>
        <?php endif; ?>

        <div class="sv-card-body">
          <div class="sv-card-header">
            <div class="sv-card-icon">
              <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($svc['service_type'] ?? '') ?>" class="sv-card-img">
            </div>
            <div class="sv-card-title-wrap">
              <div class="sv-card-name"><?= htmlspecialchars($svc['name']) ?></div>
              <!-- ADDED: booking count under name -->
              <div class="sv-card-bookings">
                <?= $bookingCount ?> booking<?= $bookingCount !== 1 ? 's' : '' ?>
              </div>
            </div>
            <div class="sv-card-actions">
              <!-- ADDED: Featured star toggle -->
              <form method="POST" action="<?= BASE_URL ?>provider/service/toggle-featured/<?= (int)$svc['id'] ?>" style="display:inline">
                <button type="submit"
                  class="sv-icon-btn is-featured <?= $featured ? 'is-active' : '' ?>"
                  title="<?= $featured ? 'Remove featured' : 'Mark as featured' ?>"
                  aria-label="<?= $featured ? 'Remove from featured' : 'Mark as featured' ?>">
                  <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                  </svg>
                </button>
              </form>
              <!-- ON/OFF availability toggle (existing) -->
              <form method="POST" action="<?= BASE_URL ?>provider/service/toggle/<?= (int)$svc['id'] ?>" style="display:inline">
                <label class="sv-toggle-label" title="<?= $active ? 'Pause service' : 'Activate service' ?>">
                  <input class="sv-toggle-input" type="checkbox" <?= $active ? 'checked' : '' ?>
                    onchange="this.closest('form').submit()">
                  <div class="sv-toggle-track">
                    <div class="sv-toggle-thumb"></div>
                  </div>
                </label>
              </form>
              <!-- Edit -->
              <button class="sv-icon-btn is-edit"
                onclick='openEditModal(<?= json_encode($svc) ?>)'
                title="Edit service"
                aria-label="Edit <?= htmlspecialchars($svc['name']) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </button>
              <!-- Delete -->
              <button class="sv-icon-btn is-delete"
                onclick="openDeleteModal(<?= (int)$svc['id'] ?>, '<?= htmlspecialchars(addslashes($svc['name'])) ?>')"
                title="Delete service"
                aria-label="Delete <?= htmlspecialchars($svc['name']) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6"/>
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                  <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                </svg>
              </button>
            </div>
          </div>

          <p class="sv-card-desc"><?= htmlspecialchars($svc['description'] ?? 'No description provided.') ?></p>

          <div class="sv-card-meta">
            <span class="sv-meta-chip is-price">
              ₱<?= number_format((float)($svc['price'] ?? 0), 2) ?>
            </span>
            <?php if (!empty($svc['duration_minutes'])): ?>
              <?php $dm = (int)$svc['duration_minutes'];
                    $dLabel = ($dm >= 60 && $dm % 60 === 0) ? ($dm / 60).' hr' : $dm.' min'; ?>
              <span class="sv-meta-chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= $dLabel ?>
              </span>
            <?php endif; ?>
            <?php if (!empty($svc['location_type'])): ?>
              <!-- CHANGED: plain location → labelled "Service Mode" chip -->
              <span class="sv-meta-chip is-mode">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                <?= htmlspecialchars($mode) ?>
              </span>
            <?php endif; ?>
            <span class="sv-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>">
              <?= $active ? 'Active' : 'Inactive' ?>
            </span>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ══════════════════════════════════════
       LIST VIEW TABLE (improved columns)
  ══════════════════════════════════════ -->
  <div class="sv-table-wrap" id="view-list" style="display:none">
    <table class="sv-table">
      <thead>
        <tr>
          <th>Service</th>
          <!-- REMOVED: Type column (redundant — provider belongs to one category) -->
          <th>Price</th>
          <th>Duration</th>
          <!-- RENAMED: Location → Service Mode -->
          <th>Service Mode</th>
          <!-- ADDED: Bookings count -->
          <th>Bookings</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($services)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--faint);padding:3rem;">No services found.</td></tr>
        <?php else: ?>
          <?php foreach ($services as $svc):
            $imgSrc       = serviceImage($svc['service_type'] ?? '', $imageMap);
            $active       = (bool)($svc['is_active']  ?? true);
            $featured     = false; // TODO: run ALTER TABLE tbl_services ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0
            $bookingCount = (int)($svc['booking_count'] ?? 0);
            $isHot        = $bookingCount >= 10;
            $dm           = (int)($svc['duration_minutes'] ?? 0);
            $dLabel       = $dm ? (($dm >= 60 && $dm % 60 === 0) ? ($dm / 60).' hr' : $dm.' min') : '—';
            $mode         = modeLabel($svc['location_type'] ?? '—', $modeLabels);
          ?>
          <tr class="<?= $featured ? 'is-featured-row' : '' ?>">
            <td>
              <div class="sv-table-name">
                <div class="sv-table-icon">
                  <img src="<?= $imgSrc ?>" alt="" class="sv-card-img">
                </div>
                <div>
                  <div><?= htmlspecialchars($svc['name']) ?></div>
                  <?php if ($featured): ?>
                  <div style="display:inline-flex;align-items:center;gap:.2rem;font-family:var(--font-m);font-size:.6rem;color:var(--gold-dim);margin-top:.18rem">
                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="none" style="width:9px;height:9px;flex-shrink:0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    Featured
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td style="font-family:var(--font-m);color:var(--gold-dim);font-weight:600">
              ₱<?= number_format((float)($svc['price'] ?? 0), 2) ?>
            </td>
            <td style="font-family:var(--font-m);font-size:.78rem;color:var(--text-dim)"><?= $dLabel ?></td>
            <td>
              <span style="font-family:var(--font-m);font-size:.72rem;color:var(--blue)">
                <?= htmlspecialchars($mode) ?>
              </span>
            </td>
            <!-- ADDED: bookings count with hot indicator -->
            <td>
              <span class="sv-booking-count <?= $isHot ? 'is-hot' : '' ?>">
                <?= $bookingCount ?>
                <?php if ($isHot): ?>
                  <span style="font-size:.58rem;color:var(--gold-dim);font-family:var(--font-m)">🔥</span>
                <?php endif; ?>
              </span>
            </td>
            <td>
              <span class="sv-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>">
                <?= $active ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td>
              <div class="sv-table-actions">
                <!-- Featured toggle -->
                <form id="ftf-<?= $svc['id'] ?>" method="POST" action="<?= BASE_URL ?>provider/service/toggle-featured/<?= (int)$svc['id'] ?>" style="display:none"></form>
                <button type="button"
                  class="sv-icon-btn is-featured <?= $featured ? 'is-active' : '' ?>"
                  onclick="document.getElementById('ftf-<?= $svc['id'] ?>').submit()"
                  title="<?= $featured ? 'Remove featured' : 'Mark featured' ?>">
                  <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                  </svg>
                </button>
                <button class="sv-icon-btn is-edit" onclick='openEditModal(<?= json_encode($svc) ?>)' title="Edit">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="sv-icon-btn is-delete" onclick="openDeleteModal(<?= (int)$svc['id'] ?>, '<?= htmlspecialchars(addslashes($svc['name'])) ?>')" title="Delete">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>

<!-- ══════════════════════════════════════
     ADD / EDIT SERVICE MODAL
══════════════════════════════════════ -->
<div class="sv-modal-backdrop" id="serviceModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="sv-modal">
    <div class="sv-modal-header">
      <div class="sv-modal-title-wrap">
        <div class="sv-modal-icon-badge" id="modalBadge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div>
          <h2 class="sv-modal-title" id="modalTitle">Add New Service</h2>
          <p class="sv-modal-subtitle" id="modalSubtitle">Fill in the details below to create a listing</p>
        </div>
      </div>
      <button class="sv-modal-close" onclick="closeModal('serviceModal')" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <form id="serviceForm" method="POST" action="<?= BASE_URL ?>provider/services/store">
      <input type="hidden" name="service_id" id="field_service_id" value="">

      <div class="sv-modal-body">

        <!-- 01 Basic Info -->
        <div class="sv-section-label">
          <span class="sv-section-num">01</span>
          <span>Basic Information</span>
        </div>

        <div class="sv-form-group" style="margin-bottom:1rem">
          <label class="sv-label" for="field_name">Service Name <span>*</span></label>
          <input type="text" class="sv-input" id="field_name" name="name"
            placeholder="e.g. Gel Nail Extensions" required maxlength="120" autocomplete="off">
        </div>

        <div class="sv-form-row">
          <div class="sv-form-group">
            <!-- CHANGED label: "Service Type" → "Service Category" -->
            <label class="sv-label" for="field_type">Service Category <span>*</span></label>
            <div class="sv-select-wrap">
              <svg class="sv-select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h8M4 18h5"/></svg>
              <select class="sv-select" id="field_type" name="service_type" required>
                <option value="">Select category…</option>
                <?php
                  $ALL_TYPES = ['Barber','Hair Stylist','Nail Tech','Massage','Skincare','Fitness','Home Cleaning','Pet Groomer','Event Stylist','Makeup'];
                  foreach ($ALL_TYPES as $t):
                ?>
                  <option value="<?= $t ?>"><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="sv-form-group">
            <!-- RENAMED: "Location Type" → "Service Mode" -->
            <label class="sv-label" for="field_location">Service Mode</label>
            <div class="sv-select-wrap">
              <svg class="sv-select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <select class="sv-select" id="field_location" name="location_type" onchange="handleLocationType(this.value)">
                <option value="On-site">Home Service (provider travels to customer)</option>
                <option value="In-shop">In-Shop (customer visits your shop)</option>
                <option value="Remote">Online / Remote</option>
                <option value="Flexible">Flexible (customer can choose)</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Shop address (conditional) -->
        <div class="sv-form-group" id="shopAddressGroup" style="display:none">
          <label class="sv-label" for="field_shop_address">
            Shop Address <span id="shopAddressRequired">*</span>
          </label>
          <div style="font-size:.73rem;color:var(--faint);margin-bottom:.3rem" id="shopAddressHint">
            Enter the address where customers should come for this service.
          </div>
          <input type="text" class="sv-input" id="field_shop_address" name="shop_address"
            placeholder="e.g. 2F Lopez Bldg, Lacson St, Bacolod City" maxlength="400" autocomplete="street-address">
        </div>

        <div class="sv-loc-notice sv-loc-notice--onsite" id="onsiteNotice" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          The customer will be asked for their address when booking. You travel to them.
        </div>
        <div class="sv-loc-notice sv-loc-notice--remote" id="remoteNotice" style="display:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          This service will be delivered online or remotely — no physical address required.
        </div>

        <div class="sv-modal-divider"></div>

        <!-- 02 Pricing & Duration -->
        <div class="sv-section-label">
          <span class="sv-section-num">02</span>
          <span>Pricing & Duration</span>
        </div>

        <div class="sv-form-row">
          <div class="sv-form-group">
            <label class="sv-label" for="field_price">Price <span>*</span></label>
            <div class="sv-input-addon-wrap">
              <div class="sv-input-addon">₱</div>
              <input type="number" class="sv-input sv-input-with-addon"
                id="field_price" name="price"
                placeholder="0.00" min="0" step="0.01" required>
            </div>
          </div>
          <div class="sv-form-group">
            <label class="sv-label" for="field_duration">Duration</label>
            <div class="sv-input-addon-wrap">
              <input type="number" class="sv-input sv-input-with-addon-right"
                id="field_duration" name="duration_minutes"
                placeholder="60" min="1" max="999">
              <select class="sv-duration-unit" id="field_duration_unit" name="duration_unit" title="Unit">
                <option value="min">min</option>
                <option value="hr">hr</option>
              </select>
            </div>
          </div>
        </div>

        <div class="sv-modal-divider"></div>

        <!-- 03 Description -->
        <div class="sv-section-label">
          <span class="sv-section-num">03</span>
          <span>Description <span style="color:var(--faint);font-weight:400;letter-spacing:0">(optional)</span></span>
        </div>

        <div class="sv-form-group">
          <textarea class="sv-textarea" id="field_desc" name="description"
            placeholder="Describe what customers can expect — tools used, areas covered, special techniques, what's included, etc."
            maxlength="500" rows="3"></textarea>
          <div class="sv-char-hint"><span id="charCount">0</span> / 500</div>
        </div>

      </div><!-- /.sv-modal-body -->

      <div class="sv-modal-footer">
        <button type="button" class="sv-btn-ghost" onclick="closeModal('serviceModal')">Cancel</button>
        <button type="submit" class="sv-btn-primary" id="modalSubmitBtn">
          Save Service
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     DELETE CONFIRM MODAL
══════════════════════════════════════ -->
<div class="sv-modal-backdrop" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
  <div class="sv-confirm-modal">
    <div class="sv-confirm-icon">🗑️</div>
    <div class="sv-confirm-title" id="deleteTitle">Delete Service</div>
    <p class="sv-confirm-msg" id="deleteMsg">Are you sure you want to delete this service? This cannot be undone.</p>
    <div class="sv-confirm-btns">
      <button class="sv-btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
      <form id="deleteForm" method="POST" style="display:inline">
        <input type="hidden" name="_method" value="DELETE">
        <button type="submit" class="sv-btn-danger">Delete</button>
      </form>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════
     SCRIPTS
══════════════════════════════════════ -->
<script>
/* ── View toggle ── */
function setView(mode) {
  const isGrid = mode === 'grid';
  document.getElementById('view-grid').style.display = isGrid ? '' : 'none';
  document.getElementById('view-list').style.display = isGrid ? 'none' : '';
  document.getElementById('btn-grid').classList.toggle('is-active', isGrid);
  document.getElementById('btn-list').classList.toggle('is-active', !isGrid);
  localStorage.setItem('sv-view', mode);
}
(function() { const v = localStorage.getItem('sv-view'); if (v) setView(v); })();

/* ── Modal helpers ── */
function openModal(id) { document.getElementById(id).classList.add('is-open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('is-open'); document.body.style.overflow = ''; }

document.querySelectorAll('.sv-modal-backdrop').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) closeModal(el.id); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.sv-modal-backdrop.is-open').forEach(el => closeModal(el.id));
});

/* ── Service Mode dynamic UI ── */
function handleLocationType(val) {
  const shopGroup    = document.getElementById('shopAddressGroup');
  const shopInput    = document.getElementById('field_shop_address');
  const shopReq      = document.getElementById('shopAddressRequired');
  const shopHint     = document.getElementById('shopAddressHint');
  const onsiteNotice = document.getElementById('onsiteNotice');
  const remoteNotice = document.getElementById('remoteNotice');

  shopGroup.style.display    = (val === 'In-shop' || val === 'Flexible') ? 'flex' : 'none';
  onsiteNotice.style.display = (val === 'On-site') ? 'flex' : 'none';
  remoteNotice.style.display = (val === 'Remote')  ? 'flex' : 'none';

  if (val === 'In-shop') {
    shopInput.required    = true;
    shopReq.style.display = 'inline';
    shopHint.textContent  = 'Required — customers will see this address when booking.';
  } else {
    shopInput.required    = false;
    shopReq.style.display = 'none';
    shopHint.textContent  = 'Optional — enter if you have a shop customers can visit.';
  }
}

/* ── Add modal ── */
function openAddModal() {
  document.getElementById('modalTitle').textContent     = 'Add New Service';
  document.getElementById('modalSubtitle').textContent  = 'Fill in the details below to create a listing';
  document.getElementById('modalSubmitBtn').textContent = 'Save Service';
  document.getElementById('serviceForm').action = '<?= BASE_URL ?>provider/services/store';
  document.getElementById('field_service_id').value = '';
  document.getElementById('serviceForm').reset();
  document.getElementById('field_duration_unit').value = 'min';
  document.getElementById('field_location').value = 'On-site';
  handleLocationType('On-site');
  updateCharCount();
  openModal('serviceModal');
}

/* ── Edit modal ── */
function openEditModal(svc) {
  document.getElementById('modalTitle').textContent     = 'Edit Service';
  document.getElementById('modalSubtitle').textContent  = 'Update the details for this listing';
  document.getElementById('modalSubmitBtn').textContent = 'Update Service';
  document.getElementById('serviceForm').action = '<?= BASE_URL ?>provider/service/update/' + svc.id;
  document.getElementById('field_service_id').value = svc.id;
  document.getElementById('field_name').value        = svc.name         || '';
  document.getElementById('field_type').value        = svc.service_type || '';
  document.getElementById('field_price').value       = svc.price        || '';
  document.getElementById('field_desc').value        = svc.description  || '';

  const locVal = svc.location_type || 'On-site';
  document.getElementById('field_location').value      = locVal;
  handleLocationType(locVal);
  document.getElementById('field_shop_address').value = svc.shop_address || '';

  const mins = parseInt(svc.duration_minutes) || 0;
  if (mins >= 60 && mins % 60 === 0) {
    document.getElementById('field_duration').value      = mins / 60;
    document.getElementById('field_duration_unit').value = 'hr';
  } else {
    document.getElementById('field_duration').value      = mins || '';
    document.getElementById('field_duration_unit').value = 'min';
  }
  updateCharCount();
  openModal('serviceModal');
}

/* ── Char counter ── */
function updateCharCount() {
  const ta = document.getElementById('field_desc');
  const el = document.getElementById('charCount');
  if (ta && el) el.textContent = ta.value.length;
}
document.getElementById('field_desc')?.addEventListener('input', updateCharCount);

/* ── Delete modal ── */
function openDeleteModal(id, name) {
  document.getElementById('deleteMsg').textContent =
    `Are you sure you want to delete "${name}"? This action cannot be undone.`;
  document.getElementById('deleteForm').action = '<?= BASE_URL ?>provider/service/delete/' + id;
  openModal('deleteModal');
}

/* ── Custom number spinners ── */
document.querySelectorAll('.sv-input[type="number"]').forEach(input => {
  const wrap = document.createElement('div');
  wrap.className = 'sv-spin-wrap';
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);
  const btns = document.createElement('div');
  btns.className = 'sv-spin-btns';
  btns.innerHTML = `<button type="button" class="sv-spin-btn" data-dir="up">▲</button><button type="button" class="sv-spin-btn" data-dir="down">▼</button>`;
  wrap.appendChild(btns);
  btns.addEventListener('mousedown', e => {
    const btn = e.target.closest('.sv-spin-btn');
    if (!btn) return;
    const step = parseFloat(input.step) || 1;
    const min  = parseFloat(input.min);
    const max  = parseFloat(input.max);
    const dir  = btn.dataset.dir === 'up' ? 1 : -1;
    const apply = () => {
      let val = (parseFloat(input.value) || 0) + dir * step;
      if (!isNaN(min)) val = Math.max(min, val);
      if (!isNaN(max)) val = Math.min(max, val);
      input.value = parseFloat(val.toFixed(10));
      input.dispatchEvent(new Event('input'));
    };
    apply();
    const hold = setInterval(apply, 80);
    document.addEventListener('mouseup', () => clearInterval(hold), { once: true });
    e.preventDefault();
  });
});

/* ── Theme toggle ── */
(function(){
  var html = document.documentElement;
  var btn  = document.getElementById('themeToggle');
  var moon = btn ? btn.querySelector('.icon-moon') : null;
  var sun  = btn ? btn.querySelector('.icon-sun')  : null;
  function applyTheme(t) {
    if (t === 'dark') {
      html.setAttribute('data-theme','dark');
      if (moon) moon.style.display = 'block';
      if (sun)  sun.style.display  = 'none';
    } else {
      html.removeAttribute('data-theme');
      if (moon) moon.style.display = 'none';
      if (sun)  sun.style.display  = 'block';
    }
  }
  applyTheme(localStorage.getItem('qb-theme') || 'light');
  if (btn) btn.addEventListener('click', function() {
    var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    localStorage.setItem('qb-theme', next);
    applyTheme(next);
  });
})();

/* ── Profile dropdown ── */
(function () {
  var trigger  = document.getElementById('profileTrigger');
  var dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;

  function open()   { trigger.classList.add('is-open'); dropdown.classList.add('is-open'); trigger.setAttribute('aria-expanded','true'); }
  function close()  { trigger.classList.remove('is-open'); dropdown.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); }
  function toggle() { dropdown.classList.contains('is-open') ? close() : open(); }

  trigger.addEventListener('click', function(e){ e.stopPropagation(); toggle(); });
  trigger.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); toggle(); } if(e.key==='Escape') close(); });
  document.addEventListener('click', function(e){ if(!dropdown.contains(e.target)&&!trigger.contains(e.target)) close(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
})();
</script>

</body>
</html>