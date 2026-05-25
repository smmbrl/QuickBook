<?php
// app/views/provider/appointments.php — ENHANCED VERSION
// Added: Hero section with gradient overlays, stats chips, improved visual hierarchy

$name        = htmlspecialchars($_SESSION['user_name']  ?? 'Provider');
$email       = htmlspecialchars($_SESSION['user_email'] ?? '');
$userId      = (int)($_SESSION['user_id']    ?? 0);
$providerId  = (int)($_SESSION['provider_id'] ?? 0);
$firstName   = explode(' ', $name)[0];

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

/* ── Look up providerId if missing ── */
if (!$providerId && $userId) {
    $st = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
    $st->execute([$userId]);
    $providerId = (int)$st->fetchColumn();
}

/* ── Business info ── */
$stBiz = $db->prepare("
    SELECT pp.*, c.name AS category_name
    FROM tbl_provider_profiles pp
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    WHERE pp.id = ? LIMIT 1
");
$stBiz->execute([$providerId]);
$biz          = $stBiz->fetch() ?: [];
$bizName      = htmlspecialchars($biz['business_name'] ?? $name);
$profilePhoto = $biz['profile_photo'] ?? null;
$initials     = strtoupper(substr($bizName, 0, 2));

/* ── Filter params ── */
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter   = $_GET['date']   ?? '';
$search       = trim($_GET['search'] ?? '');
$viewMode     = $_GET['view']   ?? 'list';
$isFiltered   = !empty($search) || !empty($dateFilter) || $statusFilter !== 'all';

/* ── Status counts ── */
$statusCounts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $s) {
    $q = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = ?");
    $q->execute([$providerId, $s]);
    $statusCounts[$s] = (int)$q->fetchColumn();
}
// Add rejected + rescheduled into the cancelled tab count
$qExtra = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status IN ('rejected','rescheduled')");
$qExtra->execute([$providerId]);
$statusCounts['cancelled'] += (int)$qExtra->fetchColumn();
$statusCounts['all'] = array_sum(array_diff_key($statusCounts, ['all' => 0]));
$pendingCount = $statusCounts['pending'];

/* ── Shared SELECT fragment ── */
$sel = "
    SELECT b.id, b.booking_date, b.booking_time, b.status,
           b.total_amount, b.notes, b.created_at,
           b.suggested_date, b.suggested_time, b.reschedule_note,
           b.service_type, b.customer_address,
           COALESCE(p.status, 'pending')        AS payment_status,
           COALESCE(p.payment_method, 'cash')   AS payment_method,
           CONCAT(u.first_name,' ',u.last_name) AS customer_name,
           u.avatar_url  AS customer_avatar,
           u.phone       AS customer_phone,
           s.name        AS service_name,
           s.duration_minutes
    FROM tbl_bookings b
    JOIN tbl_users    u ON b.customer_id = u.id
    JOIN tbl_services s ON b.service_id  = s.id
    LEFT JOIN tbl_payments p ON p.booking_id = b.id
";

/* ── Filtered flat list ── */
$filteredApts = [];
if ($isFiltered) {
    $w = "WHERE b.provider_id = ?";
    $p = [$providerId];
    if (!empty($search))         { $w .= " AND CONCAT(u.first_name,' ',u.last_name) LIKE ?"; $p[] = "%$search%"; }
    if (!empty($dateFilter))     { $w .= " AND b.booking_date = ?";  $p[] = $dateFilter; }
    if ($statusFilter !== 'all') {
        if ($statusFilter === 'cancelled') {
            $w .= " AND b.status IN ('cancelled','rejected','rescheduled')";
        } else {
            $w .= " AND b.status = ?";
            $p[] = $statusFilter;
        }
    }
    $st = $db->prepare($sel . $w . " ORDER BY b.booking_date DESC, b.booking_time ASC");
    $st->execute($p);
    $filteredApts = $st->fetchAll();
}

/* ── Section queries (only when not filtered) ── */
$todayApts = $pendingApts = $upcomingApts = $historyApts = [];
if (!$isFiltered) {
    $q1 = $db->prepare($sel . "WHERE b.provider_id=? AND b.booking_date=CURDATE() AND b.status IN ('confirmed','pending','in_progress') ORDER BY b.booking_time ASC");
    $q1->execute([$providerId]);
    $todayApts = $q1->fetchAll();

    $q2 = $db->prepare($sel . "WHERE b.provider_id=? AND b.status='pending' ORDER BY b.created_at DESC");
    $q2->execute([$providerId]);
    $pendingApts = $q2->fetchAll();

    $q3 = $db->prepare($sel . "WHERE b.provider_id=? AND b.status IN ('confirmed','in_progress') AND b.booking_date>CURDATE() ORDER BY b.booking_date ASC, b.booking_time ASC LIMIT 20");
    $q3->execute([$providerId]);
    $upcomingApts = $q3->fetchAll();

    $q4 = $db->prepare($sel . "WHERE b.provider_id=? AND b.status IN ('completed','cancelled','rejected','rescheduled') ORDER BY b.booking_date DESC LIMIT 30");
    $q4->execute([$providerId]);
    $historyApts = $q4->fetchAll();
}

/* ── Calendar events (±2 months) ── */
$qCal = $db->prepare("
    SELECT b.id, b.booking_date, b.booking_time, b.status,
           CONCAT(u.first_name,' ',u.last_name) AS customer_name,
           s.name AS service_name
    FROM tbl_bookings b
    JOIN tbl_users    u ON b.customer_id = u.id
    JOIN tbl_services s ON b.service_id  = s.id
    WHERE b.provider_id = ?
      AND b.booking_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 1 MONTH)
                             AND DATE_ADD(CURDATE(),INTERVAL 2 MONTH)
    ORDER BY b.booking_date, b.booking_time
");
$qCal->execute([$providerId]);
$calEvents = $qCal->fetchAll(PDO::FETCH_ASSOC);

/* ── Week count for sidebar ── */
$qW = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id=? AND booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND status IN ('confirmed','pending','in_progress')");
$qW->execute([$providerId]);
$weekCount = (int)$qW->fetchColumn();

/* ── Helpers ── */
function fmtDate(string $d): string { return date('M d, Y', strtotime($d)); }
function fmtTime(string $t): string { return $t ? date('g:i A', strtotime($t)) : '—'; }
function fmtMoney2(float $v): string { return $v >= 1000 ? '₱'.number_format($v/1000,1).'k' : '₱'.number_format($v,0); }

function aptCard(array $a): string {
    $id    = (int)$a['id'];
    $st    = $a['status'];
    $pay   = $a['payment_status'] ?? 'pending';
    $payMethod = $a['payment_method'] ?? 'cash';
    $phone = htmlspecialchars($a['customer_phone'] ?? '');
    $dur   = !empty($a['duration_minutes']) ? (int)$a['duration_minutes'].' min' : '';
    $notes = htmlspecialchars($a['notes'] ?? '');

    $av = !empty($a['customer_avatar'])
        ? '<img src="'.htmlspecialchars($a['customer_avatar']).'" alt="">'
        : strtoupper(substr($a['customer_name'], 0, 2));

    $actions = '';
    if ($st === 'pending') {
        $actions = '
            <a href="'.BASE_URL.'provider/appointments/accept/'.$id.'" class="apt-btn apt-btn--accept">
                <i class="fa-solid fa-check"></i><span>Accept</span>
            </a>
            <a href="'.BASE_URL.'provider/appointments/decline/'.$id.'" class="apt-btn apt-btn--decline"
               onclick="return confirm(\'Decline this booking?\')">
                <i class="fa-solid fa-xmark"></i><span>Decline</span>
            </a>';
    } elseif ($st === 'confirmed' || $st === 'in_progress') {
        $actions = '
            <a href="'.BASE_URL.'provider/appointments/complete/'.$id.'" class="apt-btn apt-btn--complete"
               onclick="return confirm(\'Mark as completed?\')">
                <i class="fa-solid fa-circle-check"></i><span>Complete</span>
            </a>';
        if ($st === 'confirmed') {
            $actions .= '
            <button class="apt-btn apt-btn--reschedule" onclick="openReschedule('.$id.')">
                <i class="fa-solid fa-calendar-pen"></i><span>Reschedule</span>
            </button>';
        }
    } elseif ($st === 'rescheduled') {
        $sugDate = !empty($a['suggested_date']) ? fmtDate($a['suggested_date']) : '—';
        $sugTime = !empty($a['suggested_time']) ? fmtTime($a['suggested_time']) : '—';
        $actions = '
            <a href="'.BASE_URL.'provider/appointments/'.$id.'" class="apt-btn apt-btn--view">
                <i class="fa-solid fa-eye"></i><span>Details</span>
            </a>';
    } else {
        $actions = '
            <a href="'.BASE_URL.'provider/appointments/'.$id.'" class="apt-btn apt-btn--view">
                <i class="fa-solid fa-eye"></i><span>Details</span>
            </a>';
    }

    $contact = $phone
        ? '<a href="tel:'.$phone.'" class="apt-btn apt-btn--contact" title="Call '.$phone.'"><i class="fa-solid fa-phone"></i></a>'
        : '';

    $statusLabels = [
        'pending'     => 'Pending',
        'confirmed'   => 'Confirmed',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
        'rejected'    => 'Rejected',
        'rescheduled' => 'Rescheduled',
    ];
    $statusLabel = $statusLabels[$st] ?? ucfirst($st);

    $payLabels = ['pending' => 'Unpaid', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'];
    $payLabel  = $payLabels[$pay] ?? ucfirst($pay);
    $payCss = ($pay === 'paid') ? 'paid' : 'unpaid';

    $rescheduleStrip = '';
    if ($st === 'rescheduled' && !empty($a['suggested_date'])) {
        $rescheduleStrip = '<div class="apt-reschedule-strip">
            <i class="fa-solid fa-calendar-pen"></i>
            Suggested: '.fmtDate($a['suggested_date']).
            (!empty($a['suggested_time']) ? ' at '.fmtTime($a['suggested_time']) : '').
        '</div>';
    }

    return '
    <div class="apt-card apt-card--'.$st.'">
      <div class="apt-card-av">'.$av.'</div>
      <div class="apt-card-body">
        <div class="apt-card-top">
          <div class="apt-card-info">
            <div class="apt-cname">'.htmlspecialchars($a['customer_name']).'</div>
            <div class="apt-sname">'.htmlspecialchars($a['service_name']).'</div>
          </div>
          <div class="apt-card-badges">
            <span class="apt-sbadge apt-sbadge--'.$st.'">'.$statusLabel.'</span>
            <span class="apt-pbadge apt-pbadge--'.$payCss.'">'.$payLabel.'</span>
          </div>
        </div>
        <div class="apt-meta">
          <span><i class="fa-regular fa-calendar-days"></i> '.fmtDate($a['booking_date']).'</span>
          <span><i class="fa-regular fa-clock"></i> '.fmtTime($a['booking_time']).'</span>
          '.($dur ? '<span><i class="fa-solid fa-hourglass-half"></i> '.$dur.'</span>' : '').'
          <span class="apt-meta-price"><i class="fa-solid fa-peso-sign"></i> '.number_format((float)($a['total_amount']??0),2).'</span>
          <span><i class="fa-solid fa-credit-card"></i> '.ucfirst($payMethod).'</span>
        </div>
        '.($rescheduleStrip).'
        '.($notes ? '<div class="apt-notes"><i class="fa-regular fa-note-sticky"></i> '.$notes.'</div>' : '').'
      </div>
      <div class="apt-card-actions">'.$contact.$actions.'</div>
    </div>';
}

function emptyState(string $icon, string $msg, string $sub = ''): string {
    return '<div class="apt-empty">
        <div class="apt-empty-icon">'.$icon.'</div>
        <p class="apt-empty-msg">'.$msg.'</p>
        '.($sub ? '<p class="apt-empty-sub">'.$sub.'</p>' : '').'
    </div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Appointments</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_dashboard.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_appointments.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>
    (function(){
      var t = localStorage.getItem('qb-theme') || 'light';
      if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
    })();
  </script>
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAVBAR
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">

    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo"
           style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Provider</span>
    </a>

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

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
      </button>

      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
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
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

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
        <a href="<?= BASE_URL ?>provider/profile"  class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-store"></i></span>
          <span>Business Profile</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="<?= BASE_URL ?>provider/settings" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-gear"></i></span>
          <span>Settings</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
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
     HERO BANNER
══════════════════════════════════════ -->
<header class="apt-hero" role="banner">
  <div class="apt-hero-inner">
    <div class="apt-hero-left">
      <h1 class="apt-hero-title">Appointments</h1>
      <p class="apt-hero-meta">
        <span><?= date('l, F j, Y') ?></span>
        <?php if ($pendingCount): ?>
          · <span style="color: var(--yellow); font-weight: 600;"><?= $pendingCount ?> pending</span>
        <?php endif; ?>
      </p>
      <div class="apt-hero-stat-line">
        <div class="apt-hero-stat-chip">
          <span class="apt-hero-stat-dot"></span>
          <strong><?= $statusCounts['all'] ?></strong> Total Bookings
        </div>
        <?php if ($pendingCount): ?>
        <a href="?status=pending" class="apt-hero-stat-chip" style="text-decoration: none;">
          <i class="fa-solid fa-clock-rotate-left" style="color: var(--yellow); font-size: .8rem;"></i>
          <strong><?= $pendingCount ?></strong> Pending
        </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="apt-hero-right">
      <a href="<?= BASE_URL ?>provider/appointments?date=today" class="apt-hero-stat-chip">
        <i class="fa-regular fa-calendar-check" style="font-size: .75rem; color: var(--green);"></i>
        <strong><?= count($todayApts) ?></strong> Today
      </a>
    </div>
  </div>
</header>

<!-- STATS STRIP -->
<div class="apt-stats-strip">
  <div class="apt-stat-box">
    <div class="apt-stat-val pending"><?= $statusCounts['pending'] ?></div>
    <div class="apt-stat-label">Pending</div>
  </div>
  <div class="apt-stat-box">
    <div class="apt-stat-val confirmed"><?= $statusCounts['confirmed'] ?></div>
    <div class="apt-stat-label">Confirmed</div>
  </div>
  <div class="apt-stat-box">
    <div class="apt-stat-val completed"><?= $statusCounts['completed'] ?></div>
    <div class="apt-stat-label">Completed</div>
  </div>
  <div class="apt-stat-box">
    <div class="apt-stat-val this-week"><?= $weekCount ?></div>
    <div class="apt-stat-label">This Week</div>
  </div>
</div>

<!-- ══════════════════════════════════════
     PAGE HEADER
══════════════════════════════════════ -->
<div class="apt-page-header">
  <div class="apt-ph-inner">

    <!-- Title -->
    <div class="apt-ph-title">
      <h2 class="apt-ph-heading">Browse Appointments</h2>
    </div>

    <!-- Controls -->
    <div class="apt-ph-controls">
      <form class="apt-filter-form" method="get" id="filterForm">
        <input type="hidden" name="status" id="statusInput" value="<?= htmlspecialchars($statusFilter) ?>">
        <input type="hidden" name="view"   id="viewInput"   value="<?= htmlspecialchars($viewMode) ?>">

        <div class="apt-search-wrap">
          <i class="fa-solid fa-magnifying-glass apt-search-ico"></i>
          <input type="search" name="search" class="apt-search-input"
                 placeholder="Search customer…"
                 value="<?= htmlspecialchars($search) ?>"
                 autocomplete="off">
          <?php if (!empty($search)): ?>
            <button type="button" class="apt-search-clear" onclick="clearSearch()">
              <i class="fa-solid fa-xmark"></i>
            </button>
          <?php endif; ?>
        </div>

        <div class="apt-date-wrap">
          <i class="fa-regular fa-calendar apt-date-ico"></i>
          <input type="date" name="date" class="apt-date-input"
                 value="<?= htmlspecialchars($dateFilter) ?>"
                 onchange="this.form.submit()">
          <?php if (!empty($dateFilter)): ?>
            <button type="button" class="apt-date-clear" onclick="clearDate()">
              <i class="fa-solid fa-xmark"></i>
            </button>
          <?php endif; ?>
        </div>

        <button type="submit" class="apt-filter-btn">
          <i class="fa-solid fa-sliders"></i> Filter
        </button>
      </form>

      <!-- View Toggle -->
      <div class="apt-view-toggle" role="group" aria-label="View mode">
        <button class="apt-view-btn <?= $viewMode === 'list' ? 'is-active' : '' ?>"
                onclick="setView('list')" aria-label="List view">
          <i class="fa-solid fa-list-ul"></i>
          <span>List</span>
        </button>
        <button class="apt-view-btn <?= $viewMode === 'calendar' ? 'is-active' : '' ?>"
                onclick="setView('calendar')" aria-label="Calendar view">
          <i class="fa-solid fa-calendar-days"></i>
          <span>Calendar</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Status Tabs -->
  <div class="apt-tabs" role="tablist">
    <?php
    $tabs = [
      'all'       => ['label' => 'All',       'icon' => ''],
      'pending'   => ['label' => 'Pending',   'icon' => ''],
      'confirmed' => ['label' => 'Confirmed', 'icon' => ''],
      'completed' => ['label' => 'Completed', 'icon' => ''],
      'cancelled' => ['label' => 'Cancelled', 'icon' => ''],
    ];
    foreach ($tabs as $key => $tab):
      $isActive  = $statusFilter === $key;
      $cnt       = $statusCounts[$key];
      $params    = http_build_query(array_filter(['status' => $key, 'view' => $viewMode, 'search' => $search, 'date' => $dateFilter]));
    ?>
    <a href="?<?= $params ?>"
       class="apt-tab apt-tab--<?= $key ?> <?= $isActive ? 'is-active' : '' ?>"
       role="tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>">
      <?= $tab['label'] ?>
      <?php if ($cnt > 0): ?>
        <span class="apt-tab-badge <?= $key === 'pending' ? 'apt-tab-badge--urgent' : '' ?>"><?= $cnt ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════════════════════════════════
     MAIN PAGE
══════════════════════════════════════ -->
<main class="apt-page" role="main">
  <div class="apt-layout">

    <!-- ────────────────── MAIN CONTENT ────────────────── -->
    <div class="apt-main">

      <?php if ($viewMode === 'list'): ?>
      <!-- ── LIST VIEW ── -->

        <?php if ($isFiltered): ?>
        <!-- ── FILTERED RESULTS ── -->
        <div class="apt-section">
          <div class="apt-section-head">
            <div class="apt-section-title">
              <h2>
                <?php if (!empty($search)): ?>
                  Results for "<?= htmlspecialchars($search) ?>"
                <?php elseif (!empty($dateFilter)): ?>
                  <?= date('M d, Y', strtotime($dateFilter)) ?>
                <?php else: ?>
                  <?= ucfirst($statusFilter) ?> Appointments
                <?php endif; ?>
              </h2>
              <span class="apt-section-count"><?= count($filteredApts) ?></span>
            </div>
            <a href="<?= BASE_URL ?>provider/appointments" class="pv-link">Clear filters ×</a>
          </div>
          <?php if (empty($filteredApts)): ?>
            <?= emptyState('🔍', 'No appointments found', 'Try adjusting your search or filters.') ?>
          <?php else: ?>
            <div class="apt-cards">
              <?php foreach ($filteredApts as $a): ?>
                <?= aptCard($a) ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- ── SECTIONS VIEW ── -->

          <!-- TODAY -->
          <?php if (!empty($todayApts)): ?>
          <div class="apt-section apt-section--today">
            <div class="apt-section-head">
              <div class="apt-section-title">
                <span class="apt-section-ico apt-ico--today">
                  <i class="fa-solid fa-sun"></i>
                </span>
                <h2>Today's Schedule</h2>
                <span class="apt-section-count apt-section-count--today"><?= count($todayApts) ?></span>
              </div>
              <span class="apt-section-date"><?= date('l, F j') ?></span>
            </div>
            <div class="apt-cards">
              <?php foreach ($todayApts as $a): ?>
                <?= aptCard($a) ?>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- PENDING REQUESTS -->
          <div class="apt-section apt-section--pending">
            <div class="apt-section-head">
              <div class="apt-section-title">
                <span class="apt-section-ico apt-ico--pending">
                  <i class="fa-solid fa-clock-rotate-left"></i>
                </span>
                <h2>Pending Requests</h2>
                <?php if (count($pendingApts) > 0): ?>
                  <span class="apt-section-count apt-section-count--urgent"><?= count($pendingApts) ?></span>
                <?php else: ?>
                  <span class="apt-section-count">0</span>
                <?php endif; ?>
              </div>
              <?php if (!empty($pendingApts)): ?>
                <a href="?status=pending" class="pv-link">View all →</a>
              <?php endif; ?>
            </div>
            <?php if (empty($pendingApts)): ?>
              <?= emptyState('✅', 'No pending requests', 'All caught up!') ?>
            <?php else: ?>
              <div class="apt-cards">
                <?php foreach ($pendingApts as $a): ?>
                  <?= aptCard($a) ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- UPCOMING CONFIRMED + IN PROGRESS -->
          <div class="apt-section">
            <div class="apt-section-head">
              <div class="apt-section-title">
                <span class="apt-section-ico apt-ico--upcoming">
                  <i class="fa-solid fa-calendar-check"></i>
                </span>
                <h2>Upcoming Appointments</h2>
                <span class="apt-section-count"><?= count($upcomingApts) ?></span>
              </div>
              <?php if (!empty($upcomingApts)): ?>
                <a href="?status=confirmed" class="pv-link">View all →</a>
              <?php endif; ?>
            </div>
            <?php if (empty($upcomingApts)): ?>
              <?= emptyState('📭', 'No upcoming appointments', 'Confirmed bookings will appear here.') ?>
            <?php else: ?>
              <div class="apt-cards">
                <?php foreach ($upcomingApts as $a): ?>
                  <?= aptCard($a) ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- HISTORY -->
          <div class="apt-section">
            <div class="apt-section-head">
              <div class="apt-section-title">
                <span class="apt-section-ico apt-ico--history">
                  <i class="fa-solid fa-rectangle-history"></i>
                </span>
                <h2>Appointment History</h2>
                <span class="apt-section-count"><?= count($historyApts) ?></span>
              </div>
              <a href="?status=completed" class="pv-link">Completed →</a>
            </div>
            <?php if (empty($historyApts)): ?>
              <?= emptyState('📋', 'No history yet', 'Completed and cancelled appointments appear here.') ?>
            <?php else: ?>
              <div class="apt-cards apt-cards--muted">
                <?php foreach ($historyApts as $a): ?>
                  <?= aptCard($a) ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

        <?php endif; /* end isFiltered */ ?>

      <?php else: ?>
      <!-- ── CALENDAR VIEW ── -->
      <div class="apt-cal-wrap pv-card">
        <div class="apt-cal-header">
          <button class="apt-cal-nav-btn" id="calPrev" aria-label="Previous month">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <h2 class="apt-cal-title" id="calTitle"></h2>
          <button class="apt-cal-nav-btn" id="calNext" aria-label="Next month">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
          <button class="apt-cal-today-btn" id="calTodayBtn">Today</button>
        </div>

        <div class="apt-cal-dow">
          <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
            <div class="apt-cal-dow-label"><?= $d ?></div>
          <?php endforeach; ?>
        </div>

        <div class="apt-cal-grid" id="calGrid"></div>

        <div class="apt-cal-legend">
          <span class="apt-cal-leg apt-cal-leg--pending">Pending</span>
          <span class="apt-cal-leg apt-cal-leg--confirmed">Confirmed</span>
          <span class="apt-cal-leg apt-cal-leg--in_progress">In Progress</span>
          <span class="apt-cal-leg apt-cal-leg--completed">Completed</span>
        </div>
      </div>

      <!-- Day detail panel -->
      <div class="apt-cal-detail" id="calDetail" style="display:none">
        <div class="apt-cal-detail-head">
          <h3 id="calDetailTitle"></h3>
          <button class="apt-cal-detail-close" onclick="closeCalDetail()">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="apt-cal-detail-body" id="calDetailBody"></div>
      </div>

      <?php endif; /* end calendar view */ ?>

    </div><!-- /apt-main -->

    <!-- ────────────────── SIDEBAR ────────────────── -->
    <aside class="apt-sidebar" aria-label="Appointments sidebar">

      <!-- Overview stats -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Overview</h2></div>
        <div class="apt-stat-grid">
          <a href="?status=pending" class="apt-stat-item apt-stat-item--yellow">
            <div class="apt-stat-val"><?= $statusCounts['pending'] ?></div>
            <div class="apt-stat-label">Pending</div>
          </a>
          <a href="?status=confirmed" class="apt-stat-item apt-stat-item--green">
            <div class="apt-stat-val"><?= $statusCounts['confirmed'] ?></div>
            <div class="apt-stat-label">Confirmed</div>
          </a>
          <a href="?status=completed" class="apt-stat-item apt-stat-item--blue">
            <div class="apt-stat-val"><?= $statusCounts['completed'] ?></div>
            <div class="apt-stat-label">Completed</div>
          </a>
          <div class="apt-stat-item apt-stat-item--gold">
            <div class="apt-stat-val"><?= $weekCount ?></div>
            <div class="apt-stat-label">This Week</div>
          </div>
        </div>
      </div>

      <!-- Today's timeline -->
      <div class="pv-card">
        <div class="pv-card-head">
          <h2>Today's Timeline</h2>
          <span class="apt-today-date"><?= date('M d') ?></span>
        </div>
        <div class="apt-timeline">
          <?php
          $qTL = $db->prepare("
              SELECT b.booking_time, b.status,
                     CONCAT(u.first_name,' ',u.last_name) AS customer_name,
                     s.name AS service_name, s.duration_minutes
              FROM tbl_bookings b
              JOIN tbl_users    u ON b.customer_id = u.id
              JOIN tbl_services s ON b.service_id  = s.id
              WHERE b.provider_id = ?
                AND b.booking_date = CURDATE()
                AND b.status NOT IN ('cancelled','rejected')
              ORDER BY b.booking_time ASC
          ");
          $qTL->execute([$providerId]);
          $tlSlots = $qTL->fetchAll();
          ?>
          <?php if (empty($tlSlots)): ?>
            <div class="apt-timeline-empty">
              <i class="fa-regular fa-calendar-xmark"></i>
              <span>No appointments today</span>
            </div>
          <?php else: ?>
            <?php foreach ($tlSlots as $slot): ?>
            <div class="apt-tl-item apt-tl-item--<?= $slot['status'] ?>">
              <div class="apt-tl-dot"></div>
              <div class="apt-tl-line"></div>
              <div class="apt-tl-content">
                <div class="apt-tl-time"><?= fmtTime($slot['booking_time']) ?></div>
                <div class="apt-tl-name"><?= htmlspecialchars($slot['customer_name']) ?></div>
                <div class="apt-tl-service"><?= htmlspecialchars($slot['service_name']) ?>
                  <?= !empty($slot['duration_minutes']) ? '· '.(int)$slot['duration_minutes'].'min' : '' ?>
                </div>
                <span class="apt-tl-badge apt-tl-badge--<?= $slot['status'] ?>"><?= ucfirst(str_replace('_',' ',$slot['status'])) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Quick Actions</h2></div>
        <div class="pv-actions" style="padding:.75rem 1rem 1rem">
          <a href="<?= BASE_URL ?>provider/appointments?status=pending" class="pv-action">
            <span class="pv-action-ico"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <div class="pv-action-txt">
              <strong>Pending Requests</strong>
              <span><?= $pendingCount ?> awaiting response</span>
            </div>
          </a>
          <a href="<?= BASE_URL ?>provider/schedule" class="pv-action">
            <span class="pv-action-ico"><i class="fa-solid fa-calendar-week"></i></span>
            <div class="pv-action-txt">
              <strong>Manage Schedule</strong>
              <span>Update availability</span>
            </div>
          </a>
          <a href="<?= BASE_URL ?>provider/dashboard" class="pv-action">
            <span class="pv-action-ico"><i class="fa-solid fa-gauge-high"></i></span>
            <div class="pv-action-txt">
              <strong>Dashboard</strong>
              <span>View overview</span>
            </div>
          </a>
        </div>
      </div>

      <!-- Status Flow reference -->
      <div class="pv-card apt-flow-card">
        <div class="pv-card-head"><h2>Status Flow</h2></div>
        <div class="apt-flow">
          <div class="apt-flow-step apt-flow-step--pending">Pending</div>
          <div class="apt-flow-arrow"><i class="fa-solid fa-arrow-down"></i></div>
          <div class="apt-flow-step apt-flow-step--confirmed">Confirmed</div>
          <div class="apt-flow-arrow"><i class="fa-solid fa-arrow-down"></i></div>
          <div class="apt-flow-step apt-flow-step--in_progress" style="background: rgba(99,102,241,.12); color: #6366f1; border-color: rgba(99,102,241,.30);">In Progress</div>
          <div class="apt-flow-arrow"><i class="fa-solid fa-arrow-down"></i></div>
          <div class="apt-flow-step apt-flow-step--completed">Completed</div>
          <div class="apt-flow-alt">
            <span class="apt-flow-alt-path">Pending → Rejected</span>
            <span class="apt-flow-alt-path">Confirmed → Cancelled</span>
            <span class="apt-flow-alt-path">Confirmed → Rescheduled</span>
          </div>
        </div>
      </div>

    </aside>

  </div><!-- /apt-layout -->
</main>

<!-- ══════════════════════════════════════
     RESCHEDULE MODAL
══════════════════════════════════════ -->
<div class="apt-modal-overlay" id="rescheduleOverlay" onclick="closeReschedule()"></div>
<div class="apt-modal" id="rescheduleModal" role="dialog" aria-modal="true" aria-label="Reschedule appointment">
  <div class="apt-modal-head">
    <h2 class="apt-modal-title"><i class="fa-solid fa-calendar-pen"></i> Reschedule Appointment</h2>
    <button class="apt-modal-close" onclick="closeReschedule()">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>
  <form class="apt-modal-body" method="post" id="rescheduleForm">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <div class="apt-form-group">
      <label class="apt-form-label">New Date</label>
      <input type="date" name="new_date" class="apt-form-input"
             min="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="apt-form-group">
      <label class="apt-form-label">New Time</label>
      <input type="time" name="new_time" class="apt-form-input" required>
    </div>
    <div class="apt-form-group">
      <label class="apt-form-label">Note to Customer <span style="opacity:.5">(optional)</span></label>
      <textarea name="reschedule_note" class="apt-form-textarea"
                placeholder="Reason for rescheduling…" rows="3"></textarea>
    </div>
    <div class="apt-modal-actions">
      <button type="button" class="apt-btn apt-btn--decline" onclick="closeReschedule()">Cancel</button>
      <button type="submit" class="apt-btn apt-btn--complete">
        <i class="fa-solid fa-calendar-pen"></i> Confirm Reschedule
      </button>
    </div>
  </form>
</div>

<!-- ══════════════════════════════════════
     SCRIPTS
══════════════════════════════════════ -->
<script>
/* ── Calendar data from PHP ── */
var CALENDAR_EVENTS = <?= json_encode(array_values($calEvents)) ?>;
var TODAY_STR       = '<?= date('Y-m-d') ?>';

/* ── Theme toggle ── */
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
  var saved = localStorage.getItem('qb-theme') || 'light';
  applyTheme(saved);
  if (btn) btn.addEventListener('click', function () {
    var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    localStorage.setItem('qb-theme', next);
    applyTheme(next);
  });
})();

/* ── Profile dropdown ── */
(function () {
  var trigger  = document.getElementById('profileTrigger');
  var dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;
  function open()  { trigger.classList.add('is-open'); dropdown.classList.add('is-open'); trigger.setAttribute('aria-expanded','true'); }
  function close() { trigger.classList.remove('is-open'); dropdown.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); }
  trigger.addEventListener('click', function(e){ e.stopPropagation(); dropdown.classList.contains('is-open') ? close() : open(); });
  document.addEventListener('click', function(e){ if(!dropdown.contains(e.target)&&!trigger.contains(e.target)) close(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
})();

/* ── Filter helpers ── */
function clearSearch() {
  var inp = document.querySelector('.apt-search-input');
  if (inp) { inp.value = ''; document.getElementById('filterForm').submit(); }
}
function clearDate() {
  var inp = document.querySelector('.apt-date-input');
  if (inp) { inp.value = ''; document.getElementById('filterForm').submit(); }
}
function setView(v) {
  document.getElementById('viewInput').value = v;
  document.getElementById('filterForm').submit();
}

/* ── Reschedule Modal ── */
function openReschedule(id) {
  var form = document.getElementById('rescheduleForm');
  form.action = '<?= BASE_URL ?>provider/appointments/reschedule/' + id;
  document.getElementById('rescheduleOverlay').classList.add('is-open');
  document.getElementById('rescheduleModal').classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
function closeReschedule() {
  document.getElementById('rescheduleOverlay').classList.remove('is-open');
  document.getElementById('rescheduleModal').classList.remove('is-open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { closeReschedule(); } });

/* ── Calendar ── */
(function () {
  var grid = document.getElementById('calGrid');
  if (!grid) return;

  var now      = new Date();
  var curYear  = now.getFullYear();
  var curMonth = now.getMonth();

  var MONTH_NAMES = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];

  function eventsForDate(dateStr) {
    return CALENDAR_EVENTS.filter(function(e){ return e.booking_date === dateStr; });
  }

  function render() {
    document.getElementById('calTitle').textContent = MONTH_NAMES[curMonth] + ' ' + curYear;
    grid.innerHTML = '';

    var firstDay    = new Date(curYear, curMonth, 1).getDay();
    var daysInMonth = new Date(curYear, curMonth + 1, 0).getDate();

    for (var b = 0; b < firstDay; b++) {
      var blank = document.createElement('div');
      blank.className = 'apt-cal-cell apt-cal-cell--blank';
      grid.appendChild(blank);
    }

    for (var d = 1; d <= daysInMonth; d++) {
      var dateStr = curYear + '-' +
        String(curMonth + 1).padStart(2,'0') + '-' +
        String(d).padStart(2,'0');

      var evs     = eventsForDate(dateStr);
      var isToday = dateStr === TODAY_STR;
      var isPast  = dateStr < TODAY_STR;

      var cell = document.createElement('div');
      cell.className = 'apt-cal-cell' +
        (isToday   ? ' apt-cal-cell--today' : '') +
        (isPast    ? ' apt-cal-cell--past'  : '') +
        (evs.length ? ' has-events' : '');

      var num = document.createElement('span');
      num.className   = 'apt-cal-day-num';
      num.textContent = d;
      cell.appendChild(num);

      if (evs.length) {
        var dots  = document.createElement('div');
        dots.className = 'apt-cal-dots';
        var shown = {};
        evs.slice(0, 4).forEach(function(ev) {
          if (!shown[ev.status]) {
            var dot = document.createElement('span');
            dot.className = 'apt-cal-dot apt-cal-dot--' + ev.status;
            dots.appendChild(dot);
            shown[ev.status] = true;
          }
        });
        if (evs.length > 1) {
          var count = document.createElement('span');
          count.className   = 'apt-cal-count';
          count.textContent = evs.length;
          dots.appendChild(count);
        }
        cell.appendChild(dots);
        (function(ds, evList){
          cell.addEventListener('click', function(){ showDayDetail(ds, evList); });
        })(dateStr, evs);
      }

      grid.appendChild(cell);
    }
  }

  document.getElementById('calPrev').addEventListener('click', function(){
    curMonth--; if (curMonth < 0) { curMonth = 11; curYear--; } render();
  });
  document.getElementById('calNext').addEventListener('click', function(){
    curMonth++; if (curMonth > 11) { curMonth = 0; curYear++; } render();
  });
  var todayBtn = document.getElementById('calTodayBtn');
  if (todayBtn) todayBtn.addEventListener('click', function(){
    curYear = now.getFullYear(); curMonth = now.getMonth(); render();
  });

  render();

  function showDayDetail(dateStr, evs) {
    var detail  = document.getElementById('calDetail');
    var titleEl = document.getElementById('calDetailTitle');
    var bodyEl  = document.getElementById('calDetailBody');
    if (!detail || !titleEl || !bodyEl) return;

    var d = new Date(dateStr + 'T00:00:00');
    titleEl.textContent = d.toLocaleDateString('en-PH', { weekday:'long', month:'long', day:'numeric' });

    var statusLabels = {
      pending: 'Pending', confirmed: 'Confirmed', in_progress: 'In Progress',
      completed: 'Completed', cancelled: 'Cancelled',
      rejected: 'Rejected', rescheduled: 'Rescheduled'
    };

    var html = '';
    evs.forEach(function(ev) {
      var time = ev.booking_time
        ? new Date('1970-01-01T'+ev.booking_time).toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit'})
        : '—';
      var label = statusLabels[ev.status] || (ev.status.charAt(0).toUpperCase() + ev.status.slice(1));
      html += '<div class="apt-cal-ev apt-cal-ev--'+ev.status+'">' +
        '<div class="apt-cal-ev-time">'+time+'</div>' +
        '<div class="apt-cal-ev-info">' +
          '<div class="apt-cal-ev-customer">'+ev.customer_name+'</div>' +
          '<div class="apt-cal-ev-service">'+ev.service_name+'</div>' +
        '</div>' +
        '<span class="apt-sbadge apt-sbadge--'+ev.status+'">'+label+'</span>' +
      '</div>';
    });
    bodyEl.innerHTML = html;

    detail.style.display = '';
    setTimeout(function(){ detail.classList.add('is-visible'); }, 10);
  }

  window.closeCalDetail = function() {
    var detail = document.getElementById('calDetail');
    if (!detail) return;
    detail.classList.remove('is-visible');
    setTimeout(function(){ detail.style.display = 'none'; }, 250);
  };

})();
</script>

</body>
</html>