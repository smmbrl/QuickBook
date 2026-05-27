<?php
// app/views/Provider/customer-detail.php
// $booking is pre-loaded by ProviderDashController::bookingDetail()

require_once __DIR__ . '/../../../config/database.php';
$db     = Database::getInstance();
$userId = (int)($_SESSION['user_id'] ?? 0);

$navUser = $db->prepare("SELECT u.first_name, u.last_name, u.email, pp.profile_photo AS nav_photo, pp.business_name FROM tbl_users u LEFT JOIN tbl_provider_profiles pp ON pp.user_id = u.id WHERE u.id = ?");
$navUser->execute([$userId]);
$navRow       = $navUser->fetch();
$firstName    = htmlspecialchars($navRow['first_name'] ?? 'Provider');
$bizName      = htmlspecialchars($navRow['business_name'] ?? $firstName);
$email        = htmlspecialchars($navRow['email'] ?? '');
$profilePhoto = $navRow['nav_photo'] ?? null;
$initials     = strtoupper(substr($bizName, 0, 2));

$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings b JOIN tbl_provider_profiles pp ON pp.id = b.provider_id WHERE pp.user_id = ? AND b.status = 'pending'");
$stPending->execute([$userId]);
$pendingCount = (int)$stPending->fetchColumn();

$status        = $booking['status'];
$bookDate      = !empty($booking['booking_date']) ? date('l, F j, Y', strtotime($booking['booking_date'])) : '—';
$bookDateShort = !empty($booking['booking_date']) ? date('F j, Y',    strtotime($booking['booking_date'])) : '—';
$bookTime      = !empty($booking['booking_time']) ? date('g:i A',     strtotime($booking['booking_time'])) : null;
$createdAt     = !empty($booking['created_at'])   ? date('M j, Y · g:i A', strtotime($booking['created_at'])) : '—';
// Use the booking's chosen location_type. If customer_address is filled, treat as On-site regardless.
$custAddr      = trim($booking['customer_address'] ?? '');
$shopAddr      = trim($booking['shop_address'] ?? '');
$locType       = $custAddr !== '' ? 'On-site' : ($booking['location_type'] ?? 'In-shop');
$duration      = !empty($booking['duration_minutes']) ? $booking['duration_minutes'] . ' min' : null;

// Customer profile extras
$custPhone   = $booking['customer_phone'] ?? '';
$custGender  = $booking['customer_gender'] ?? '';
$custDob     = !empty($booking['customer_dob']) ? date('F j, Y', strtotime($booking['customer_dob'])) : '';
$custAge     = !empty($booking['customer_dob']) ? (int)date_diff(date_create($booking['customer_dob']), date_create('today'))->y : null;
$custProfAddr = $booking['customer_profile_address'] ?? '';
$custSince   = !empty($booking['customer_since']) ? date('M Y', strtotime($booking['customer_since'])) : '';
$genderLabels = ['male'=>'Male','female'=>'Female','non_binary'=>'Non-binary','prefer_not_to_say'=>'Prefer not to say'];

$statusLabels = [
    'pending'      => ['label' => 'Pending Confirmation', 'color' => 'amber',  'icon' => 'fa-clock'],
    'confirmed'    => ['label' => 'Confirmed',            'color' => 'green',  'icon' => 'fa-circle-check'],
    'in_progress'  => ['label' => 'In Progress',          'color' => 'blue',   'icon' => 'fa-spinner'],
    'completed'    => ['label' => 'Completed',            'color' => 'blue',   'icon' => 'fa-medal'],
    'cancelled'    => ['label' => 'Cancelled',            'color' => 'red',    'icon' => 'fa-circle-xmark'],
    'rejected'     => ['label' => 'Rejected',             'color' => 'red',    'icon' => 'fa-circle-xmark'],
    'rescheduled'  => ['label' => 'Rescheduled',          'color' => 'amber',  'icon' => 'fa-rotate-right'],
];
$statusInfo = $statusLabels[$status] ?? ['label' => ucfirst($status), 'color' => 'muted', 'icon' => 'fa-circle'];

$locConfig = [
    'On-site'  => ['label'=>'Home Service', 'icon'=>'fa-house-chimney-medical','color'=>'#fbbf24','bg'=>'rgba(251,191,36,.09)','border'=>'rgba(251,191,36,.25)'],
    'In-shop'  => ['label'=>'In-shop', 'icon'=>'fa-store',   'color'=>'#4ade80','bg'=>'rgba(74,222,128,.07)','border'=>'rgba(74,222,128,.2)'],
    'Remote'   => ['label'=>'Remote',  'icon'=>'fa-wifi',    'color'=>'#60a5fa','bg'=>'rgba(96,165,250,.08)','border'=>'rgba(96,165,250,.2)'],
    'Flexible' => ['label'=>'Flexible','icon'=>'fa-sliders', 'color'=>'#a78bfa','bg'=>'rgba(167,139,250,.09)','border'=>'rgba(167,139,250,.22)'],
];
$locInfo = $locConfig[$locType] ?? $locConfig['In-shop'];

$canConfirm  = $status === 'pending';
$canStart    = $status === 'confirmed';
$canComplete = $status === 'in_progress';
$canResched  = in_array($status, ['pending', 'confirmed', 'rescheduled']);
$isActive    = in_array($status, ['pending','confirmed','in_progress','rescheduled']);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$timelineSteps = [
    'pending'     => ['icon'=>'fa-clipboard-list','label'=>'Booking Placed',  'sub'=>'Customer submitted this booking'],
    'confirmed'   => ['icon'=>'fa-circle-check',  'label'=>'Confirmed',        'sub'=>'You accepted the booking'],
    'in_progress' => ['icon'=>'fa-spinner',        'label'=>'In Progress',      'sub'=>'Service is underway'],
    'completed'   => ['icon'=>'fa-medal',          'label'=>'Completed',        'sub'=>'Service finished successfully'],
];
$statusOrder = array_keys($timelineSteps);
$currentIdx  = array_search($status, $statusOrder);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Booking #<?= str_pad((int)$booking['id'], 4, '0', STR_PAD_LEFT) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_bookings.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_booking_detail.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>(function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();</script>
  <style>
    [hidden] { display: none !important; }
    :root { --yellow:#fbbf24; --yellow-soft:rgba(251,191,36,.10); --yellow-border:rgba(251,191,36,.28); }

    .pv-nav { position:sticky;top:0;z-index:100; }

    /* Profile dropdown trigger */
    .pv-profile-trigger { display:flex;align-items:center;gap:.65rem;padding:.3rem .55rem .3rem .3rem;border-radius:99px;border:1px solid transparent;cursor:pointer;position:relative;transition:background .2s,border-color .2s;user-select:none; }
    .pv-profile-trigger:hover, .pv-profile-trigger.is-open { background:var(--surface-md);border-color:var(--gold-border); }
    .pv-nav-av { width:34px;height:34px;border-radius:99px;background:linear-gradient(135deg,var(--gold-dim),var(--gold));color:#fff8e8;font-family:var(--font-display);font-weight:700;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 0 2px var(--gold-border),0 2px 10px rgba(201,168,76,.25);overflow:hidden; }
    .pv-nav-av img { width:100%;height:100%;object-fit:cover;border-radius:99px;display:block; }
    .pv-nav-user { display:flex;flex-direction:column;line-height:1.2; }
    .pv-nav-user-name { font-size:.82rem;font-weight:600;color:var(--text-primary);white-space:nowrap; }
    .pv-profile-chevron { color:var(--text-dim);transition:transform .25s,color .2s;flex-shrink:0; }
    .pv-profile-trigger.is-open .pv-profile-chevron { transform:rotate(180deg);color:var(--gold-dim); }
    /* Dropdown panel */
    .pv-profile-dropdown { position:absolute;top:calc(100% + 10px);right:0;width:260px;background:rgba(255,255,255,0.92);backdrop-filter:blur(28px) saturate(1.8);-webkit-backdrop-filter:blur(28px) saturate(1.8);border:1.5px solid rgba(255,255,255,0.80);border-radius:var(--r-xl);box-shadow:0 20px 60px rgba(139,110,60,.18),0 4px 16px rgba(139,110,60,.10);z-index:900;opacity:0;transform:translateY(-8px) scale(0.97);pointer-events:none;transition:opacity .22s,transform .22s;overflow:hidden; }
    .pv-profile-dropdown.is-open { opacity:1;transform:translateY(0) scale(1);pointer-events:auto; }
    .pv-pd-header { display:flex;align-items:center;gap:.85rem;padding:1.1rem 1.2rem 1rem;background:linear-gradient(135deg,#FBF6EC 0%,#F5EDDA 100%); }
    .pv-pd-avatar { width:44px;height:44px;border-radius:99px;flex-shrink:0;background:linear-gradient(135deg,var(--gold-dim),var(--gold));color:#fff8e8;font-family:var(--font-display);font-weight:700;font-size:.88rem;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 2.5px var(--gold-border),0 3px 12px rgba(201,168,76,.28);overflow:hidden; }
    .pv-pd-avatar img { width:100%;height:100%;object-fit:cover;display:block;border-radius:99px; }
    .pv-pd-info { min-width:0;flex:1; }
    .pv-pd-name { font-family:var(--font-display);font-size:.9rem;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .pv-pd-email { font-family:var(--font-mono);font-size:.6rem;color:var(--text-muted);margin-top:.1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .pv-pd-role { display:inline-block;margin-top:.3rem;font-family:var(--font-mono);font-size:.52rem;font-weight:500;letter-spacing:.08em;text-transform:uppercase;background:var(--gold-lt);color:var(--gold-dim);border:1px solid var(--gold-border);padding:.14rem .5rem;border-radius:99px; }
    .pv-pd-divider { height:1px;background:linear-gradient(90deg,transparent,rgba(201,168,76,.25) 30%,rgba(201,168,76,.25) 70%,transparent); }
    .pv-pd-item { display:flex;align-items:center;gap:.75rem;padding:.82rem 1.2rem;font-size:.84rem;font-weight:500;color:var(--text-primary);transition:background .15s,color .15s;cursor:pointer; }
    .pv-pd-item:hover { background:rgba(201,168,76,.07);color:var(--gold-dim); }
    .pv-pd-item--danger { color:var(--text-muted); }
    .pv-pd-item--danger:hover { background:var(--red-soft);color:var(--red); }
    .pv-pd-item-ico { width:30px;height:30px;border-radius:var(--r-sm);flex-shrink:0;background:linear-gradient(135deg,#FBF6EC,#F0E7CC);border:1px solid var(--gold-border);display:flex;align-items:center;justify-content:center;font-size:.8rem;color:var(--gold-dim); }
    .pv-pd-item--danger .pv-pd-item-ico { background:var(--red-soft);border-color:var(--red-border);color:var(--red); }
    .pv-pd-item-arrow { margin-left:auto;color:var(--text-dim);flex-shrink:0; }
    [data-theme="dark"] .pv-profile-dropdown { background:rgba(20,16,8,0.95);border-color:rgba(201,168,76,.18); }
    [data-theme="dark"] .pv-pd-header { background:linear-gradient(135deg,rgba(28,22,10,.95) 0%,rgba(20,16,8,.98) 100%); }

    .pv-hero { min-height:260px; }
    .pv-hero-inner { display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding-bottom:2rem; }

    .pv-status-badge { display:inline-flex;align-items:center;gap:.5rem;padding:.38rem .9rem;border-radius:999px;font-size:.73rem;font-weight:700;letter-spacing:.04em;border:1px solid transparent; }
    .pv-status-badge--amber  { background:var(--orange-soft);  color:var(--orange); border-color:var(--orange-border); }
    .pv-status-badge--green  { background:var(--green-soft);   color:var(--green);  border-color:var(--green-border); }
    .pv-status-badge--blue   { background:var(--blue-soft);    color:var(--blue);   border-color:var(--blue-border); }
    .pv-status-badge--red    { background:var(--red-soft);     color:var(--red);    border-color:var(--red-border); }
    .pv-status-badge--muted  { background:var(--surface);      color:var(--text-muted); border-color:var(--border); }

    .pv-tier-badge { display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .85rem;border-radius:999px;font-size:.73rem;font-weight:600;background:var(--gold-soft);color:var(--gold);border:1px solid var(--gold-border); }

    .pv-card { background:rgba(255,255,255,.55);backdrop-filter:blur(22px) saturate(1.6);-webkit-backdrop-filter:blur(22px) saturate(1.6);border:1.5px solid rgba(255,255,255,.70);border-radius:var(--r-xl);box-shadow:0 4px 28px rgba(139,110,60,.09),0 1px 0 rgba(255,255,255,.85) inset; }

    .cd-btn { display:flex;align-items:center;justify-content:center;gap:.5rem;width:100%;padding:.65rem 1rem;border-radius:var(--r-sm);font-size:.82rem;font-weight:700;cursor:pointer;border:1px solid transparent;text-decoration:none;transition:background .18s; }
    .cd-btn--confirm { background:var(--green-soft); color:var(--green); border-color:var(--green-border); }
    .cd-btn--confirm:hover { background:rgba(22,163,74,.18); }
    .cd-btn--start   { background:var(--blue-soft);  color:var(--blue);  border-color:var(--blue-border); }
    .cd-btn--start:hover { background:rgba(37,99,235,.18); }
    .cd-btn--complete{ background:var(--gold-lt);    color:var(--gold-dim); border-color:var(--gold-border); }
    .cd-btn--complete:hover { background:var(--gold-soft-md); }
    .cd-btn--delete  { background:var(--red-soft);   color:var(--red);   border-color:var(--red-border); }
    .cd-btn--delete:hover { background:rgba(220,38,38,.18); }
    .cd-btn--ghost   { background:var(--surface);    color:var(--text-muted); border-color:var(--border); }
    .cd-btn--ghost:hover { background:var(--surface-md); color:var(--text-primary); }

    /* Delete modal */
    .cd-btn--resched { background:rgba(245,158,11,.1);color:#f59e0b;border-color:rgba(245,158,11,.25); }
    .resch-row  { display:flex;gap:.6rem;margin-bottom:.75rem; }
    .resch-field{ flex:1; }

    /* Cancel reason box */
    .cancel-reason-box { background:var(--red-soft);border:1px solid var(--red-border);border-radius:10px;padding:.85rem 1rem;margin-top:.75rem;font-size:.82rem;color:var(--text-muted);line-height:1.55; }
    .cancel-reason-lbl { font-size:.62rem;font-family:var(--font-mono);letter-spacing:.08em;text-transform:uppercase;color:var(--red);margin-bottom:.3rem; }

    /* Toast */
    .toast-container{ position:fixed;bottom:1.5rem;right:1.5rem;display:flex;flex-direction:column;gap:.6rem;z-index:9999;pointer-events:none; }
    .toast{ display:flex;align-items:center;gap:.65rem;padding:.7rem 1rem;border-radius:10px;min-width:260px;max-width:380px;font-size:.82rem;font-weight:500;backdrop-filter:blur(12px);box-shadow:0 8px 30px rgba(0,0,0,.35);transform:translateX(120%);opacity:0;transition:transform .32s cubic-bezier(.22,1,.36,1),opacity .28s;pointer-events:auto; }
    .toast.is-visible{ transform:translateX(0);opacity:1; }
    .toast--success{ background:rgba(255,252,240,.97);border:1px solid var(--green-border);color:var(--green); }
    .toast--error  { background:rgba(255,252,240,.97);border:1px solid var(--red-border);color:var(--red); }

    @media(max-width:900px){ .bd-grid{grid-template-columns:1fr} .bd-sidebar{order:-1} }
    @media(max-width:540px){ .bd-detail-grid{grid-template-columns:1fr} .bd-card{padding:1.2rem 1.1rem} }
  </style>
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAVBAR
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
      <a href="<?= BASE_URL ?>provider/appointments" class="pv-nav-link is-active">
        Appointments
        <?php if ($pendingCount): ?><sup class="pv-sup"><?= $pendingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"  class="pv-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/portfolio" class="pv-nav-link">Portfolio</a>
      <a href="<?= BASE_URL ?>provider/schedule"  class="pv-nav-link">Schedule</a>
    </div>

    <!-- Right-side controls -->
    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

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
            <?= $initials ?>
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
              <?= $initials ?>
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

<!-- HERO -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Booking #<?= str_pad((int)$booking['id'], 4, '0', STR_PAD_LEFT) ?>
      </p>
      <h1 class="pv-hero-name"><?= htmlspecialchars($booking['service_name'] ?? '—') ?></h1>
      <p class="pv-hero-date"><?= $bookDate ?><?= $bookTime ? ' · ' . $bookTime : '' ?></p>
      <div style="display:flex;gap:.65rem;flex-wrap:wrap;margin-top:.75rem">
        <span class="pv-status-badge pv-status-badge--<?= $statusInfo['color'] ?>">
          <i class="fa-solid <?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['label'] ?>
        </span>
      </div>
    </div>
    <a href="<?= BASE_URL ?>provider/bookings" style="display:inline-flex;align-items:center;gap:.5rem;padding:.55rem 1.1rem;border-radius:99px;background:rgba(255,255,255,.60);backdrop-filter:blur(12px);border:1px solid var(--gold-border);color:var(--gold-dim);font-family:var(--font-mono);font-size:.68rem;font-weight:500;letter-spacing:.04em;text-decoration:none;transition:background .2s,box-shadow .2s;" onmouseover="this.style.background='rgba(255,255,255,.85)';this.style.boxShadow='0 4px 16px rgba(201,168,76,.20)'" onmouseout="this.style.background='rgba(255,255,255,.60)';this.style.boxShadow='none'">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
      Back to Bookings
    </a>
  </div>
</header>

<!-- MAIN -->
<main class="pv-page">

  <div class="bd-grid">

    <!-- LEFT COLUMN -->
    <div class="bd-main">

      <!-- Customer Profile -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-user-circle"></i> Customer Profile</div>

        <!-- Identity Row -->
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem">
          <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--gold-dim),var(--gold));color:#fff8e8;font-family:var(--font-display);font-weight:700;font-size:1.1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 0 3px var(--gold-border),0 2px 12px rgba(201,168,76,.25);overflow:hidden;">
            <?php if (!empty($booking['customer_avatar'])): ?>
              <img src="<?= htmlspecialchars($booking['customer_avatar']) ?>" alt="<?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
            <?php else: ?>
              <?= strtoupper(substr($booking['customer_first'] ?? 'C', 0, 1) . substr($booking['customer_last'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
          </div>
          <div>
            <div style="font-weight:700;font-size:1.05rem;color:var(--text-primary);font-family:var(--font-display);font-style:italic;">
              <?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?>
            </div>
            <?php if ($custSince): ?>
            <div style="font-size:.73rem;color:var(--text-dim);margin-top:.2rem">
              <i class="fa-solid fa-calendar-plus" style="margin-right:.3rem"></i>Member since <?= $custSince ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Contact & Info Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem 1rem">

          <!-- Email -->
          <div style="grid-column:1/-1;display:flex;align-items:flex-start;gap:.65rem;padding:.7rem .9rem;background:rgba(255,255,255,.55);border:1px solid var(--gold-border);border-radius:10px">
            <i class="fa-solid fa-envelope" style="color:var(--gold);margin-top:.15rem;font-size:.85rem;flex-shrink:0"></i>
            <div>
              <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:.2rem">Email</div>
              <div style="font-size:.88rem;color:var(--text-primary);font-weight:500"><?= htmlspecialchars($booking['customer_email'] ?? '—') ?></div>
            </div>
          </div>

          <!-- Phone -->
          <div style="display:flex;align-items:flex-start;gap:.65rem;padding:.7rem .9rem;background:rgba(255,255,255,.55);border:1px solid var(--gold-border);border-radius:10px">
            <i class="fa-solid fa-phone" style="color:#4ade80;margin-top:.15rem;font-size:.85rem;flex-shrink:0"></i>
            <div>
              <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:.2rem">Phone</div>
              <div style="font-size:.88rem;color:var(--text-primary);font-weight:500">
                <?= $custPhone ? htmlspecialchars($custPhone) : '<span style="color:var(--text-dim);font-style:italic">Not provided</span>' ?>
              </div>
            </div>
          </div>

          <!-- Gender -->
          <div style="display:flex;align-items:flex-start;gap:.65rem;padding:.7rem .9rem;background:rgba(255,255,255,.55);border:1px solid var(--gold-border);border-radius:10px">
            <i class="fa-solid fa-venus-mars" style="color:#a78bfa;margin-top:.15rem;font-size:.85rem;flex-shrink:0"></i>
            <div>
              <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:.2rem">Gender</div>
              <div style="font-size:.88rem;color:var(--text-primary);font-weight:500">
                <?= ($custGender && isset($genderLabels[$custGender])) ? $genderLabels[$custGender] : '<span style="color:var(--text-dim);font-style:italic">Not provided</span>' ?>
              </div>
            </div>
          </div>

          <!-- Date of Birth -->
          <div style="display:flex;align-items:flex-start;gap:.65rem;padding:.7rem .9rem;background:rgba(255,255,255,.55);border:1px solid var(--gold-border);border-radius:10px">
            <i class="fa-solid fa-cake-candles" style="color:#f472b6;margin-top:.15rem;font-size:.85rem;flex-shrink:0"></i>
            <div>
              <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:.2rem">Date of Birth</div>
              <div style="font-size:.88rem;color:var(--text-primary);font-weight:500">
                <?php if ($custDob): ?>
                  <?= htmlspecialchars($custDob) ?>
                  <?php if ($custAge !== null): ?><span style="font-size:.75rem;color:var(--muted);margin-left:.4rem">(<?= $custAge ?> yrs)</span><?php endif; ?>
                <?php else: ?>
                  <span style="color:var(--text-dim);font-style:italic">Not provided</span>
                <?php endif; ?>
              </div>
            </div>
          </div>



        </div>
      </div>

      <!-- Service -->
      <div class="pv-card bd-card">
        <div class="bd-card-header">
          <div class="bd-service-av"><i class="fa-solid fa-screwdriver-wrench"></i></div>
          <div>
            <div class="bd-card-title"><?= htmlspecialchars($booking['service_name'] ?? '—') ?></div>
            <div class="bd-card-sub"><?= htmlspecialchars($booking['service_type'] ?? '') ?></div>
          </div>
        </div>
      </div>

      <?php if ($locType === 'On-site' && $custAddr): ?>
      <!-- Home Service Address — prominent dedicated card -->
      <div class="pv-card bd-card" style="border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.05)">
        <div class="bd-section-title" style="color:#fbbf24">
          <i class="fa-solid fa-house-chimney-medical"></i> Home Service Address
        </div>
        <div style="display:flex;align-items:flex-start;gap:1rem;margin-top:.25rem">
          <div style="width:42px;height:42px;border-radius:10px;background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fa-solid fa-location-dot" style="color:#fbbf24;font-size:1.1rem"></i>
          </div>
          <div style="flex:1">
            <div style="font-size:1rem;font-weight:700;color:var(--text-primary);line-height:1.5;margin-bottom:.35rem">
              <?= htmlspecialchars($custAddr) ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem">
              <a href="https://www.google.com/maps/search/<?= urlencode($custAddr) ?>"
                 target="_blank" rel="noopener noreferrer"
                 style="display:inline-flex;align-items:center;gap:.4rem;padding:.38rem .8rem;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.3);border-radius:999px;font-size:.73rem;font-weight:700;color:#fbbf24;text-decoration:none;transition:background .18s"
                 onmouseover="this.style.background='rgba(251,191,36,.22)'" onmouseout="this.style.background='rgba(251,191,36,.12)'">
                <i class="fa-solid fa-map"></i> Open in Google Maps
              </a>
            </div>
            <div style="font-size:.72rem;color:rgba(255,255,255,.4);margin-top:.5rem">
              <i class="fa-solid fa-circle-info"></i> Travel to this address to perform the service
            </div>
          </div>
        </div>
      </div>
      <?php elseif ($locType === 'On-site' && !$custAddr): ?>
      <!-- On-site but no address provided -->
      <div class="pv-card bd-card" style="border-color:rgba(244,63,94,.25);background:rgba(244,63,94,.05)">
        <div class="bd-section-title" style="color:#f43f5e">
          <i class="fa-solid fa-triangle-exclamation"></i> Home Service Address Missing
        </div>
        <p style="font-size:.84rem;color:var(--text-muted);margin:.5rem 0 0">
          This booking is marked as Home Service but the customer did not provide a service address.
          Please contact the customer to confirm the location before proceeding.
        </p>
        <?php if ($custProfAddr): ?>
        <div style="margin-top:.75rem;padding:.7rem .9rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:10px;font-size:.84rem;color:#fff">
          <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--text-dim);font-family:var(--font-mono);margin-bottom:.25rem">Profile home address (may be used as reference)</div>
          <?= htmlspecialchars($custProfAddr) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Appointment Details -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-calendar-days"></i> Appointment Details</div>
        <div class="bd-detail-grid">
          <div class="bd-detail-item">
            <div class="bd-detail-label">Date</div>
            <div class="bd-detail-val"><?= $bookDateShort ?></div>
          </div>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Time</div>
            <div class="bd-detail-val"><?= $bookTime ?? '—' ?></div>
          </div>
          <?php if ($duration): ?>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Duration</div>
            <div class="bd-detail-val">⏱ <?= $duration ?></div>
          </div>
          <?php endif; ?>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Location Type</div>
            <div class="bd-detail-val" style="color:<?= $locInfo['color'] ?>">
              <i class="fa-solid <?= $locInfo['icon'] ?>"></i> <?= $locInfo['label'] ?>
            </div>
          </div>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Booked On</div>
            <div class="bd-detail-val" style="font-size:.8rem"><?= $createdAt ?></div>
          </div>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Booking ID</div>
            <div class="bd-detail-val bd-mono">#<?= str_pad((int)$booking['id'], 4, '0', STR_PAD_LEFT) ?></div>
          </div>
        </div>

        <?php if (in_array($locType, ['In-shop','Flexible']) && $shopAddr): ?>
        <div style="display:flex;align-items:flex-start;gap:.7rem;padding:.85rem 1rem;border-radius:12px;border:1px solid rgba(74,222,128,.2);background:rgba(74,222,128,.07);margin-top:1rem">
          <i class="fa-solid fa-map-pin" style="color:#4ade80;margin-top:.1rem;flex-shrink:0"></i>
          <div>
            <div style="font-size:.7rem;font-family:var(--font-m);letter-spacing:.08em;text-transform:uppercase;color:#4ade80;margin-bottom:.25rem">Shop Address</div>
            <div style="font-size:.9rem;font-weight:700;color:var(--text-primary);line-height:1.4;margin-bottom:.4rem"><?= htmlspecialchars($shopAddr) ?></div>
            <a href="https://www.google.com/maps/search/<?= urlencode($shopAddr) ?>"
               target="_blank" rel="noopener noreferrer"
               style="display:inline-flex;align-items:center;gap:.4rem;padding:.32rem .75rem;background:rgba(22,163,74,.10);border:1px solid rgba(22,163,74,.25);border-radius:999px;font-size:.72rem;font-weight:700;color:var(--green);text-decoration:none;transition:background .18s;margin-bottom:.4rem"
               onmouseover="this.style.background='rgba(22,163,74,.20)'" onmouseout="this.style.background='rgba(22,163,74,.10)'">
              <i class="fa-solid fa-map"></i> Open in Google Maps
            </a>
            <div style="font-size:.72rem;color:var(--text-dim);margin-top:.1rem"><i class="fa-solid fa-circle-info"></i> Customer will come to your shop</div>
          </div>
        </div>
        <?php elseif ($locType === 'Remote'): ?>
        <div style="padding:.75rem 1rem;border-radius:12px;border:1px solid rgba(96,165,250,.2);background:rgba(96,165,250,.07);margin-top:1rem;font-size:.84rem;color:#60a5fa">
          <i class="fa-solid fa-wifi"></i> This service will be delivered online / remotely
        </div>
        <?php endif; ?>
      </div>

      <!-- Customer Notes -->
      <?php if (!empty($booking['notes'])): ?>
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-note-sticky"></i> Customer Notes</div>
        <p class="bd-notes"><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
      </div>
      <?php endif; ?>

      <!-- Cancellation Reason -->
      <?php if (in_array($status, ['cancelled','rejected']) && !empty($booking['cancellation_reason'])): ?>
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-circle-xmark"></i> Cancellation Details</div>
        <div class="cancel-reason-box">
          <div class="cancel-reason-lbl">Reason</div>
          <?= nl2br(htmlspecialchars($booking['cancellation_reason'])) ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- SIDEBAR -->
    <div class="bd-sidebar">

      <!-- Payment -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-credit-card"></i> Payment Summary</div>

        <?php
          $servicePrice   = (float)($booking['service_price'] ?? $booking['total_amount'] ?? 0);
          $totalAmount    = (float)($booking['total_amount'] ?? 0);
          $homeServiceFee = $totalAmount - $servicePrice;
          $isHomeService  = $locType === 'On-site';
          $payMethod      = $booking['payment_method'] ?? null;
          $payIcons = [
            'cash'          => '<i class="fa-solid fa-money-bill-wave"></i>',
            'gcash'         => '<i class="fa-solid fa-mobile-screen"></i>',
            'paymaya'       => '<i class="fa-solid fa-mobile-screen"></i>',
            'card'          => '<i class="fa-solid fa-credit-card"></i>',
            'credit_card'   => '<i class="fa-solid fa-credit-card"></i>',
            'debit_card'    => '<i class="fa-solid fa-credit-card"></i>',
            'bank_transfer' => '<i class="fa-solid fa-building-columns"></i>',
          ];
          $payIcon  = $payIcons[strtolower($payMethod ?? '')] ?? '<i class="fa-solid fa-wallet"></i>';
          $payLabel = $payMethod ? ucwords(str_replace('_', ' ', $payMethod)) : null;
        ?>

        <?php if ($payLabel): ?>
        <div class="bd-price-row bd-pay-method-row">
          <span class="bd-pay-method-label">Payment Type</span>
          <span class="bd-pay-method-val"><?= $payIcon ?> <?= htmlspecialchars($payLabel) ?></span>
        </div>
        <div class="bd-price-divider"></div>
        <?php endif; ?>

        <div class="bd-price-row">
          <span>Amount</span>
          <span class="bd-price-val">₱<?= number_format($servicePrice, 2) ?></span>
        </div>

        <?php if ($isHomeService && $homeServiceFee > 0): ?>
        <div class="bd-price-row">
          <span>Home service fee</span>
          <span class="bd-price-val">₱<?= number_format($homeServiceFee, 2) ?></span>
        </div>
        <?php endif; ?>

        <div class="bd-price-divider"></div>
        <div class="bd-price-row bd-price-row--total">
          <span>Total</span>
          <span class="bd-price-total">₱<?= number_format($totalAmount, 2) ?></span>
        </div>
        <?php if (!empty($booking['loyalty_points_earned'])): ?>
        <div class="bd-loyalty-note">⭐ +<?= (int)$booking['loyalty_points_earned'] ?> loyalty points earned by customer</div>
        <?php endif; ?>
      </div>

      <!-- Timeline -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-rotate"></i> Status Timeline</div>
        <div class="bd-timeline">
          <?php if (in_array($status, ['cancelled','rejected','rescheduled'])): ?>
            <?php foreach ($timelineSteps as $key => $info): $done = ($key === 'pending'); ?>
            <div class="bd-timeline-step <?= $done ? 'done' : 'muted' ?>">
              <div class="bd-tl-dot <?= $done ? 'done' : '' ?>"></div>
              <div class="bd-tl-content"><div class="bd-tl-label"><i class="fa-solid <?= $info['icon'] ?>"></i> <?= $info['label'] ?></div></div>
            </div>
            <?php endforeach; ?>
            <div class="bd-timeline-step bd-timeline-step--cancel">
              <div class="bd-tl-dot bd-tl-dot--cancel"></div>
              <div class="bd-tl-content"><div class="bd-tl-label">✖ <?= ucfirst($status) ?></div></div>
            </div>
          <?php else: ?>
            <?php foreach ($timelineSteps as $key => $info):
              $stepIdx = array_search($key, $statusOrder);
              $done    = ($currentIdx !== false && $stepIdx <= $currentIdx);
              $active  = ($key === $status);
            ?>
            <div class="bd-timeline-step <?= $done ? 'done' : 'muted' ?> <?= $active ? 'active' : '' ?>">
              <div class="bd-tl-dot <?= $done ? 'done' : '' ?> <?= $active ? 'active' : '' ?>"></div>
              <div class="bd-tl-content">
                <div class="bd-tl-label"><i class="fa-solid <?= $info['icon'] ?>"></i> <?= $info['label'] ?></div>
                <?php if ($active): ?><div style="font-size:.72rem;color:var(--muted);margin-top:.15rem"><?= $info['sub'] ?></div><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Actions -->
      <?php if ($isActive): ?>
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-bolt"></i> Actions</div>
        <div class="bd-actions">
          <?php if ($canStart): ?>
          <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>">
            <input type="hidden" name="action" value="start">
            <button type="submit" class="cd-btn cd-btn--start"><i class="fa-solid fa-play"></i> Start Service</button>
          </form>
          <?php endif; ?>
          <?php if ($canComplete): ?>
          <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>">
            <input type="hidden" name="action" value="complete">
            <button type="submit" class="cd-btn cd-btn--complete"><i class="fa-solid fa-medal"></i> Mark as Completed</button>
          </form>
          <?php endif; ?>
          <?php if ($canConfirm): ?>
          <button type="button" class="cd-btn cd-btn--confirm" onclick="openConfirmModal()"><i class="fa-solid fa-circle-check"></i> Confirm Booking</button>
          <?php endif; ?>
          <button type="button" class="cd-btn cd-btn--delete" onclick="openDeleteModal()">
            <i class="fa-solid fa-xmark"></i> Cancel &amp; Delete Order
          </button>
          <button type="button" class="cd-btn cd-btn--resched" onclick="openReschedModal()">
            <i class="fa-solid fa-rotate-right"></i> Suggest Reschedule
          </button>
        </div>
      </div>
      <?php endif; ?>

      <div class="bd-actions">
        <a href="<?= BASE_URL ?>provider/bookings" class="cd-btn cd-btn--ghost"><i class="fa-solid fa-arrow-left"></i> All Bookings</a>
      </div>

    </div>
  </div>
</main>

<!-- DELETE MODAL -->
<div class="pv-modal-overlay" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
  <div class="pv-modal pv-modal--delete">

    <button class="pv-modal-close pv-modal-close--abs" onclick="closeDeleteModal()" aria-label="Close">✕</button>

    <div class="modal-centered-header" aria-hidden="true">
      <div class="modal-icon-ring modal-icon-ring--red">
        <svg viewBox="0 0 24 24" fill="none" width="26" height="26">
          <path d="M12 8v5M12 15.5v.5" stroke="#FB7185" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
      </div>
      <h2 class="modal-title" id="deleteModalTitle">Cancel &amp; Delete Order</h2>
      <p class="modal-sub">
        You are about to cancel the booking for
        <strong><?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?></strong>
        (<em><?= htmlspecialchars($booking['service_name'] ?? '') ?></em>).<br>
        The customer will be <span class="hl-red">immediately notified</span> with your reason.
        This action <span class="hl-red">cannot be undone.</span>
      </p>
    </div>

    <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>">
      <input type="hidden" name="action" value="delete">
      <label class="modal-field-label" for="delReason">
        Reason for cancellation <span class="modal-required">* required</span>
      </label>
      <textarea id="delReason" name="reason" class="pv-textarea"
                placeholder="e.g. Schedule conflict, Equipment issue, Emergency unavailability…"
                maxlength="400" required></textarea>
      <div class="modal-char-count"><span id="delCharCount">0</span> / 400</div>
      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--red" id="delSubmitBtn" disabled>Yes</button>
        <button type="button" class="modal-btn modal-btn--no" onclick="closeDeleteModal()">No</button>
      </div>
    </form>

  </div>
</div>

<div id="toastContainer" class="toast-container" aria-live="polite"></div>

<!-- CONFIRM MODAL -->
<div class="pv-modal-overlay" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
  <div class="pv-modal pv-modal--confirm">

    <button class="pv-modal-close pv-modal-close--abs" onclick="closeConfirmModal()" aria-label="Close">✕</button>

    <div class="modal-centered-header" aria-hidden="true">
      <div class="modal-icon-ring modal-icon-ring--green">
        <svg viewBox="0 0 24 24" fill="none" width="26" height="26">
          <path d="M5 12l5 5L19 7" stroke="#4ADE80" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 class="modal-title" id="confirmModalTitle">Confirm Booking</h2>
      <p class="modal-sub">
        You are about to <span class="hl-green">confirm</span> the booking for
        <strong><?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?></strong>
        (<em><?= htmlspecialchars($booking['service_name'] ?? '') ?></em>).<br>
        The customer will be notified immediately.
      </p>
    </div>

    <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>" id="confirmForm">
      <input type="hidden" name="action" value="confirm">
      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--green">Yes</button>
        <button type="button" class="modal-btn modal-btn--no" onclick="closeConfirmModal()">No</button>
      </div>
    </form>

  </div>
</div>

<!-- RESCHEDULE MODAL -->
<div class="pv-modal-overlay" id="reschedModal" role="dialog" aria-modal="true" aria-labelledby="reschedModalTitle">
  <div class="pv-modal pv-modal--resched">

    <button class="pv-modal-close pv-modal-close--abs" onclick="closeReschedModal()" aria-label="Close">✕</button>

    <div class="modal-centered-header">
      <div class="modal-icon-ring modal-icon-ring--amber">
        <svg viewBox="0 0 24 24" fill="none" width="26" height="26">
          <path d="M21 12A9 9 0 1 1 12 3" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/>
          <path d="M21 3v5h-5" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 class="modal-title" id="reschedModalTitle">Suggest Reschedule</h2>
      <p class="modal-sub">
        Suggest a new schedule for
        <strong><?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?></strong>
        (<em><?= htmlspecialchars($booking['service_name'] ?? '') ?></em>).<br>
        <span class="modal-sub-note">Current booking: <span class="hl-amber"><?= $bookDate ?><?= $bookTime ? ' · ' . $bookTime : '' ?></span></span>
      </p>
    </div>

    <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>">
      <input type="hidden" name="action" value="reschedule">

      <div class="resch-row">
        <div class="resch-field">
          <label class="modal-field-label" for="reschedDate">Suggested Date <span class="modal-required">* required</span></label>
          <input type="date" id="reschedDate" name="suggested_date" class="pv-input" required>
        </div>
        <div class="resch-field">
          <label class="modal-field-label" for="reschedTime">Suggested Time <span class="modal-required">* required</span></label>
          <input type="time" id="reschedTime" name="suggested_time" class="pv-input" required>
        </div>
      </div>

      <label class="modal-field-label" for="reschedNote" style="display:block;margin-top:.85rem">
        Reason / Note to Customer <span class="modal-required">* required</span>
      </label>
      <textarea id="reschedNote" name="resched_reason" class="pv-textarea"
                placeholder="e.g. I have a conflict at the original time. I'm suggesting this new slot as it works better for my schedule…"
                maxlength="500" required></textarea>
      <div class="modal-char-count"><span id="reschedCharCount">0</span> / 500</div>

      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--amber" id="reschedSubmitBtn" disabled>
          Send Reschedule Suggestion
        </button>
      </div>
    </form>

  </div>
</div>

<script>
/* ── Confirm modal ── */
function openConfirmModal() {
  document.getElementById('confirmModal').classList.add('is-open');
}
function closeConfirmModal() {
  document.getElementById('confirmModal').classList.remove('is-open');
}
document.getElementById('confirmModal').addEventListener('click', function(e) {
  if (e.target === this) closeConfirmModal();
});

/* ── Delete modal ── */
function openDeleteModal() {
  document.getElementById('delReason').value = '';
  document.getElementById('delCharCount').textContent = '0';
  document.getElementById('delSubmitBtn').disabled = true;
  document.getElementById('deleteModal').classList.add('is-open');
  setTimeout(function() { document.getElementById('delReason').focus(); }, 120);
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('is-open');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});
document.getElementById('delReason').addEventListener('input', function() {
  document.getElementById('delCharCount').textContent = this.value.length;
  document.getElementById('delSubmitBtn').disabled = this.value.trim().length < 5;
});

/* ── Reschedule modal ── */
function openReschedModal() {
  document.getElementById('reschedDate').value = '';
  document.getElementById('reschedTime').value = '';
  document.getElementById('reschedNote').value = '';
  document.getElementById('reschedCharCount').textContent = '0';
  document.getElementById('reschedSubmitBtn').disabled = true;
  document.getElementById('reschedModal').classList.add('is-open');
  setTimeout(function() { document.getElementById('reschedDate').focus(); }, 120);
}
function closeReschedModal() {
  document.getElementById('reschedModal').classList.remove('is-open');
}
document.getElementById('reschedModal').addEventListener('click', function(e) {
  if (e.target === this) closeReschedModal();
});
function validateReschedForm() {
  var date = document.getElementById('reschedDate').value.trim();
  var time = document.getElementById('reschedTime').value.trim();
  var note = document.getElementById('reschedNote').value.trim();
  document.getElementById('reschedSubmitBtn').disabled = !(date && time && note.length >= 5);
}
document.getElementById('reschedNote').addEventListener('input', function() {
  document.getElementById('reschedCharCount').textContent = this.value.length;
  validateReschedForm();
});
document.getElementById('reschedDate').addEventListener('change', validateReschedForm);
document.getElementById('reschedTime').addEventListener('change', validateReschedForm);

/* ── Escape key ── */
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeConfirmModal();
    closeDeleteModal();
    closeReschedModal();
  }
});

/* ── Toast ── */
function showToast(msg, type) {
  var c = document.getElementById('toastContainer'), t = document.createElement('div');
  t.className = 'toast toast--' + (type || 'success');
  var ico = type === 'success'
    ? '<svg viewBox="0 0 16 16" fill="none"><path d="M3 8l3.5 3.5L13 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    : '<svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.6"/><path d="M8 5v3.5M8 10.5v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
  t.innerHTML = '<span style="display:flex;flex-shrink:0">' + ico + '</span><span>' + msg + '</span>';
  c.appendChild(t);
  requestAnimationFrame(function(){ requestAnimationFrame(function(){ t.classList.add('is-visible'); }); });
  setTimeout(function(){ t.classList.remove('is-visible'); t.addEventListener('transitionend', function(){ t.remove(); },{once:true}); }, 4000);
}
<?php if ($flash): ?>
showToast('<?= addslashes(htmlspecialchars_decode($flash['msg'])) ?>', '<?= $flash['type'] === 'success' ? 'success' : 'error' ?>');
<?php endif; ?>
</script>
<script>
  (function(){
    var html=document.documentElement,btn=document.getElementById('themeToggle');
    var moon=btn?btn.querySelector('.icon-moon'):null,sun=btn?btn.querySelector('.icon-sun'):null;
    function applyTheme(t){
      if(t==='dark'){ html.setAttribute('data-theme','dark'); if(moon)moon.style.display='block'; if(sun)sun.style.display='none'; }
      else{ html.removeAttribute('data-theme'); if(moon)moon.style.display='none'; if(sun)sun.style.display='block'; }
    }
    applyTheme(localStorage.getItem('qb-theme')||'light');
    if(btn) btn.addEventListener('click',function(){ var n=html.getAttribute('data-theme')==='dark'?'light':'dark'; localStorage.setItem('qb-theme',n); applyTheme(n); });

    // Profile dropdown
    var trigger=document.getElementById('profileTrigger'),
        dropdown=document.getElementById('profileDropdown');
    if(trigger&&dropdown){
      trigger.addEventListener('click',function(e){
        e.stopPropagation();
        var open=dropdown.classList.toggle('is-open');
        trigger.setAttribute('aria-expanded',open);
      });
      document.addEventListener('click',function(){ dropdown.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); });
      dropdown.addEventListener('click',function(e){ e.stopPropagation(); });
    }
  })();
</script>
</body>
</html>