<?php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$userId       = (int)($_SESSION['user_id']     ?? 0);
$providerId   = $userId;
$providerName = htmlspecialchars($_SESSION['user_name']  ?? 'Provider');
$email        = htmlspecialchars($_SESSION['user_email'] ?? '');

/* ── Provider profile ── */
$stBiz = $db->prepare("
    SELECT pp.*, c.name AS category_name
    FROM tbl_provider_profiles pp
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    WHERE pp.user_id = ? LIMIT 1
");
$stBiz->execute([$userId]);
$profile      = $stBiz->fetch() ?: [];
$profileId    = (int)($profile['id'] ?? 0);
$bizName      = htmlspecialchars($profile['business_name'] ?? $providerName);
$firstName    = htmlspecialchars(explode(' ', $providerName)[0]);
$profilePhoto = $profile['profile_photo'] ?? null;
$initials     = strtoupper(substr($bizName, 0, 2));

/* ── View mode ── */
$viewMode = in_array($_GET['view'] ?? '', ['list','calendar']) ? $_GET['view'] : 'list';

/* ── Filters ── */
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter   = $_GET['date']   ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 10;
$todayDate    = date('Y-m-d');

$validStatuses = ['all','pending','confirmed','in_progress','completed','cancelled','rejected','rescheduled'];
if (!in_array($statusFilter, $validStatuses)) $statusFilter = 'all';

$validDates = ['all','today','upcoming','past'];
if (!in_array($dateFilter, $validDates)) $dateFilter = 'all';

/* ── Status counts ── */
$allStatuses = ['pending','confirmed','in_progress','completed','cancelled','rejected','rescheduled'];
$counts = [];
foreach ($allStatuses as $s) {
    $st = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = ? AND deleted_at IS NULL");
    $st->execute([$profileId, $s]);
    $counts[$s] = (int)$st->fetchColumn();
}
$stTotal = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND deleted_at IS NULL");
$stTotal->execute([$profileId]);
$counts['all'] = (int)$stTotal->fetchColumn();

/* ── Revenue ── */
$stRev = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings WHERE provider_id = ? AND status = 'completed' AND deleted_at IS NULL");
$stRev->execute([$profileId]);
$totalRevenue = (float)$stRev->fetchColumn();

/* ── Today's confirmed / in-progress appointments ── */
$stToday = $db->prepare(
    "SELECT b.id, b.booking_date, b.booking_time, b.start_time, b.end_time,
            b.status, b.total_amount, b.notes, b.location_type, b.service_type,
            b.suggested_date, b.suggested_time, b.reschedule_note,
            u.first_name, u.last_name, u.email, u.phone, u.avatar_url,
            s.name AS service_name, s.duration_minutes,
            pay.status AS payment_status, pay.payment_method
     FROM tbl_bookings b
     JOIN tbl_users u    ON u.id = b.customer_id
     JOIN tbl_services s ON s.id = b.service_id
     LEFT JOIN tbl_payments pay ON pay.booking_id = b.id
     WHERE b.provider_id = ?
       AND DATE(b.booking_date) = ?
       AND b.status IN ('confirmed','in_progress')
       AND b.deleted_at IS NULL
     ORDER BY b.start_time ASC"
);
$stToday->execute([$profileId, $todayDate]);
$todayAppointments = $stToday->fetchAll();

/* ── Pending requests ── */
$stPending = $db->prepare(
    "SELECT b.id, b.booking_date, b.booking_time, b.start_time, b.end_time,
            b.status, b.total_amount, b.notes, b.location_type, b.service_type, b.customer_address,
            u.first_name, u.last_name, u.email, u.phone, u.avatar_url,
            s.name AS service_name, s.duration_minutes,
            pay.status AS payment_status, pay.payment_method
     FROM tbl_bookings b
     JOIN tbl_users u    ON u.id = b.customer_id
     JOIN tbl_services s ON s.id = b.service_id
     LEFT JOIN tbl_payments pay ON pay.booking_id = b.id
     WHERE b.provider_id = ? AND b.status = 'pending' AND b.deleted_at IS NULL
     ORDER BY b.created_at DESC"
);
$stPending->execute([$profileId]);
$pendingAppointments = $stPending->fetchAll();

/* ── Rescheduled ── */
$stResched = $db->prepare(
    "SELECT b.id, b.booking_date, b.start_time, b.suggested_date, b.suggested_time,
            b.reschedule_note, b.status,
            u.first_name, u.last_name, u.avatar_url,
            s.name AS service_name
     FROM tbl_bookings b
     JOIN tbl_users u    ON u.id = b.customer_id
     JOIN tbl_services s ON s.id = b.service_id
     WHERE b.provider_id = ? AND b.status = 'rescheduled' AND b.deleted_at IS NULL
     ORDER BY b.updated_at DESC"
);
$stResched->execute([$profileId]);
$rescheduledAppointments = $stResched->fetchAll();

/* ── Build filtered query ── */
$where  = "b.provider_id = :pid AND b.deleted_at IS NULL";
$params = [':pid' => $profileId];

if ($statusFilter !== 'all') {
    $where .= " AND b.status = :status";
    $params[':status'] = $statusFilter;
}
if ($dateFilter === 'today') {
    $where .= " AND DATE(b.booking_date) = :today";
    $params[':today'] = $todayDate;
} elseif ($dateFilter === 'upcoming') {
    $where .= " AND DATE(b.booking_date) > :today";
    $params[':today'] = $todayDate;
} elseif ($dateFilter === 'past') {
    $where .= " AND DATE(b.booking_date) < :today";
    $params[':today'] = $todayDate;
}
if ($search !== '') {
    $where .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR s.name LIKE :q3 OR CAST(b.id AS CHAR) LIKE :q4)";
    $params[':q1'] = '%'.$search.'%';
    $params[':q2'] = '%'.$search.'%';
    $params[':q3'] = '%'.$search.'%';
    $params[':q4'] = '%'.$search.'%';
}

/* ── Count filtered ── */
$stCount = $db->prepare(
    "SELECT COUNT(*) FROM tbl_bookings b
     JOIN tbl_users u    ON u.id = b.customer_id
     JOIN tbl_services s ON s.id = b.service_id
     WHERE $where"
);
$stCount->execute($params);
$totalFiltered = (int)$stCount->fetchColumn();
$totalPages    = max(1, (int)ceil($totalFiltered / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

/* ── Main list ── */
$sqlMain = "SELECT b.id, b.booking_date, b.booking_time, b.start_time, b.end_time,
                   b.status, b.total_amount, b.notes, b.location_type, b.service_type,
                   b.customer_address, b.reschedule_count, b.created_at,
                   b.suggested_date, b.suggested_time,
                   u.first_name, u.last_name, u.email, u.phone, u.avatar_url,
                   s.name AS service_name, s.duration_minutes,
                   pay.status AS payment_status, pay.payment_method
            FROM tbl_bookings b
            JOIN tbl_users u    ON u.id = b.customer_id
            JOIN tbl_services s ON s.id = b.service_id
            LEFT JOIN tbl_payments pay ON pay.booking_id = b.id
            WHERE $where
            ORDER BY
              CASE b.status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1
                            WHEN 'in_progress' THEN 2 WHEN 'rescheduled' THEN 3 ELSE 4 END,
              b.booking_date ASC, b.start_time ASC
            LIMIT $perPage OFFSET $offset";
$stMain = $db->prepare($sqlMain);
$stMain->execute($params);
$appointments = $stMain->fetchAll();

/* ── Calendar data ── */
$stCal = $db->prepare(
    "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.status,
            u.first_name, u.last_name, s.name AS service_name
     FROM tbl_bookings b
     JOIN tbl_users u    ON u.id = b.customer_id
     JOIN tbl_services s ON s.id = b.service_id
     WHERE b.provider_id = ?
       AND DATE(b.booking_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
       AND b.deleted_at IS NULL
     ORDER BY b.booking_date ASC, b.start_time ASC"
);
$stCal->execute([$profileId]);
$calEvents = [];
foreach ($stCal->fetchAll() as $row) {
    $colorMap = [
        'pending'     => '#CA8A04',
        'confirmed'   => '#16A34A',
        'in_progress' => '#EA580C',
        'completed'   => '#2563EB',
        'rescheduled' => '#7C3AED',
        'cancelled'   => '#DC2626',
        'rejected'    => '#EC4899',
    ];
    $calEvents[] = [
        'id'     => $row['id'],
        'title'  => $row['first_name'].' '.$row['last_name'].' — '.$row['service_name'],
        'start'  => $row['booking_date'].'T'.$row['start_time'],
        'end'    => $row['booking_date'].'T'.$row['end_time'],
        'color'  => $colorMap[$row['status']] ?? '#888',
        'status' => $row['status'],
        'url'    => BASE_URL.'provider/appointments/'.(int)$row['id'],
    ];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$initials = strtoupper(substr($providerName, 0, 2));

$tabLabels = [
    'all'         => 'All Appointments',
    'pending'     => 'Pending',
    'confirmed'   => 'Confirmed',
    'in_progress' => 'In Progress',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
    'rejected'    => 'Rejected',
    'rescheduled' => 'Rescheduled',
];

$dateLabels = [
    'all'      => 'All Dates',
    'today'    => 'Today',
    'upcoming' => 'Upcoming',
    'past'     => 'Past',
];

function timeLabel(string $time): string {
    return date('h:i A', strtotime($time));
}
function payBadgeClass(?string $ps): string {
    return match($ps) {
        'paid'     => 'apt-pay--paid',
        'failed'   => 'apt-pay--failed',
        'refunded' => 'apt-pay--refunded',
        default    => 'apt-pay--pending'
    };
}
function payLabel(?string $ps): string {
    return $ps ? ucfirst($ps) : 'Pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — My Appointments</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_appointments.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>

    /* ════════════════  TABLE  ════════════════ */
    .pv-table-wrap { overflow-x:visible !important; overflow-y:visible !important; width:100%; }
    .pv-table { width:100%; table-layout:auto; }
    .pv-table tbody tr { display:flex; flex-direction:column; margin-bottom:1rem; padding:1rem; border:1px solid var(--border); border-radius:.375rem; background:var(--bg-secondary); }
    @media (min-width:768px) {
      .pv-table tbody tr { display:table-row; margin-bottom:0; padding:0; border:none; background:transparent; }
    }

    /* ════════════════  PAGINATION  ════════════════ */
    .pv-pagination { display:flex; align-items:center; justify-content:center; gap:1.5rem; margin-top:1.5rem; padding:1rem 0; }
    .pv-page-btn {
      display:inline-flex; align-items:center; justify-content:center;
      width:2.5rem; height:2.5rem; border:1px solid var(--border);
      border-radius:.375rem; background:var(--bg-secondary); color:var(--text);
      text-decoration:none; font-weight:600; transition:all .2s ease; cursor:pointer;
    }
    .pv-page-btn:hover:not(.is-disabled) { background:var(--gold); border-color:var(--gold); color:white; transform:translateY(-2px); }
    .pv-page-btn.is-disabled { opacity:.5; cursor:not-allowed; }

    /* ════════════════  CALENDAR v2  ════════════════ */

    /* Strip the inline padding so our nav sits flush */
    #aptCalendar { padding: 0 !important; }

    /* ── Nav bar (cream header strip) ── */
    .apt-cal-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem 1.5rem;
      background: linear-gradient(135deg, #F8F4ED 0%, #F0E7D4 100%);
      border-bottom: 1px solid rgba(201,168,76,.15);
      gap: 1rem;
    }
    .apt-cal-nav-right { display:flex; align-items:center; gap:.5rem; }

    /* Square icon buttons */
    .apt-cal-nav-btn {
      width: 36px; height: 36px; border-radius: 10px;
      background: rgba(255,255,255,.70);
      border: 1px solid rgba(201,168,76,.28);
      color: var(--gold-dim);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; font-size: .78rem;
      transition: background .18s, border-color .18s, transform .15s, box-shadow .15s;
    }
    .apt-cal-nav-btn:hover {
      background: rgba(201,168,76,.18);
      border-color: rgba(201,168,76,.45);
      transform: scale(1.06);
      box-shadow: 0 2px 8px rgba(201,168,76,.14);
    }

    /* Month/year label */
    .apt-cal-month-title {
      font-family: var(--font-display);
      font-size: 1.18rem; font-weight: 700; font-style: italic;
      color: var(--text-primary);
      flex: 1; text-align: center;
    }

    /* "Today" pill */
    .apt-cal-today-btn {
      padding: .38rem 1.05rem; border-radius: 99px;
      background: rgba(255,255,255,.70);
      border: 1px solid rgba(201,168,76,.30);
      color: var(--gold-dim);
      font-family: var(--font-mono); font-size: .7rem; font-weight: 600;
      letter-spacing: .06em; text-transform: uppercase;
      cursor: pointer;
      transition: background .18s, color .18s, box-shadow .18s;
    }
    .apt-cal-today-btn:hover {
      background: var(--gold); color: #fff8e8;
      box-shadow: 0 4px 12px rgba(201,168,76,.28);
    }

    /* ── Grid container (white area) ── */
    .apt-cal-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: .4rem;
      padding: .9rem 1rem 1.3rem;
      background: #ffffff;
    }
    [data-theme="dark"] .apt-cal-grid { background: transparent; }

    /* Day-of-week headers */
    .apt-cal-dow {
      font-family: var(--font-mono); font-size: .62rem; font-weight: 600;
      letter-spacing: .10em; text-transform: uppercase;
      color: var(--text-dim); text-align: center;
      padding: .6rem 0 .65rem;
      border-bottom: 1.5px solid rgba(201,168,76,.13);
      margin-bottom: .25rem;
    }

    /* ── Day cells ── */
    .apt-cal-cell {
      min-height: 90px;
      padding: .55rem .6rem .5rem;
      border-radius: 12px;
      display: flex; flex-direction: column;
      border: 1.5px solid transparent;
      transition: background .15s, border-color .15s, box-shadow .15s, transform .15s;
      position: relative;
    }

    /* Empty filler cells */
    .apt-cal-cell--empty { pointer-events: none; }

    /* Plain days with no events — subtle hover */
    .apt-cal-cell:not(.apt-cal-cell--empty):not(.apt-cal-cell--today):not(.apt-cal-cell--has-events):hover {
      background: rgba(201,168,76,.04);
      border-color: rgba(201,168,76,.10);
    }

    /* Today */
    .apt-cal-cell--today {
      background: linear-gradient(135deg, rgba(248,244,237,.97) 0%, rgba(240,231,212,.80) 100%);
      border-color: rgba(201,168,76,.38) !important;
      box-shadow: 0 2px 12px rgba(139,110,60,.09), 0 0 0 1px rgba(201,168,76,.10);
    }

    /* Has events */
    .apt-cal-cell--has-events {
      background: rgba(255,255,255,.88);
      border-color: rgba(201,168,76,.20);
      box-shadow: 0 2px 10px rgba(139,110,60,.08);
      cursor: pointer;
    }
    .apt-cal-cell--has-events:hover {
      background: #ffffff;
      border-color: rgba(201,168,76,.40);
      box-shadow: 0 4px 18px rgba(139,110,60,.13);
      transform: translateY(-2px);
    }

    /* Today + has events */
    .apt-cal-cell--today.apt-cal-cell--has-events {
      background: linear-gradient(135deg, rgba(248,244,237,.98), rgba(240,231,212,.88));
      border-color: rgba(201,168,76,.48) !important;
    }

    /* Day number */
    .apt-cal-day-num {
      font-family: var(--font-mono); font-size: .8rem; font-weight: 700;
      color: var(--text-dim); line-height: 1; display: block;
      margin-bottom: auto;
    }
    .apt-cal-cell--today       .apt-cal-day-num { color: var(--gold-dim); }
    .apt-cal-cell--has-events  .apt-cal-day-num { color: var(--text-primary); }

    /* Dots row */
    .apt-cal-dots {
      display: flex; align-items: center; gap: .3rem;
      flex-wrap: wrap; padding-top: .5rem;
    }
    .apt-cal-dot {
      width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
      transition: transform .15s;
    }
    .apt-cal-cell--has-events:hover .apt-cal-dot { transform: scale(1.3); }

    /* Count label (shown when > 3 events) */
    .apt-cal-count {
      font-family: var(--font-mono); font-size: .58rem; font-weight: 600;
      color: var(--text-dim); margin-top: .22rem; display: block;
    }

    /* ── Dark mode overrides ── */
    [data-theme="dark"] .apt-cal-nav {
      background: linear-gradient(135deg, rgba(22,17,8,.96) 0%, rgba(14,10,2,.98) 100%);
      border-bottom-color: rgba(201,168,76,.18);
    }
    [data-theme="dark"] .apt-cal-nav-btn {
      background: rgba(201,168,76,.10); border-color: rgba(201,168,76,.25); color: var(--gold);
    }
    [data-theme="dark"] .apt-cal-nav-btn:hover { background: rgba(201,168,76,.22); }
    [data-theme="dark"] .apt-cal-month-title { color: #EDE3CC; }
    [data-theme="dark"] .apt-cal-today-btn {
      background: rgba(201,168,76,.10); border-color: rgba(201,168,76,.28); color: var(--gold);
    }
    [data-theme="dark"] .apt-cal-today-btn:hover { background: var(--gold); color: #1C1710; }
    [data-theme="dark"] .apt-cal-dow { color: rgba(237,227,204,.35); border-bottom-color: rgba(201,168,76,.18); }
    [data-theme="dark"] .apt-cal-cell--today {
      background: linear-gradient(135deg, rgba(40,32,12,.72), rgba(28,22,6,.55));
      border-color: rgba(201,168,76,.40) !important;
    }
    [data-theme="dark"] .apt-cal-cell--has-events {
      background: rgba(22,28,48,.65); border-color: rgba(201,168,76,.20);
    }
    [data-theme="dark"] .apt-cal-cell--has-events:hover {
      background: rgba(26,34,58,.85); border-color: rgba(201,168,76,.38);
    }
    [data-theme="dark"] .apt-cal-cell--today.apt-cal-cell--has-events {
      background: linear-gradient(135deg, rgba(40,32,12,.85), rgba(28,22,6,.70));
    }
    [data-theme="dark"] .apt-cal-day-num            { color: rgba(237,227,204,.32); }
    [data-theme="dark"] .apt-cal-cell--today       .apt-cal-day-num { color: var(--gold); }
    [data-theme="dark"] .apt-cal-cell--has-events  .apt-cal-day-num { color: rgba(237,227,204,.82); }
    [data-theme="dark"] .apt-cal-count { color: rgba(237,227,204,.40); }

    /* ── Responsive ── */
    @media (max-width:1100px) {
      .apt-cal-cell { min-height:72px; }
    }
    @media (max-width:768px) {
      .apt-cal-grid { gap:.18rem; padding:.5rem .6rem .8rem; }
      .apt-cal-cell { min-height:56px; padding:.38rem .38rem .32rem; border-radius:8px; }
      .apt-cal-day-num { font-size:.7rem; }
      .apt-cal-dot { width:5px; height:5px; }
      .apt-cal-count { font-size:.52rem; }
      .apt-cal-month-title { font-size:.95rem; }
      .apt-cal-nav { padding:.75rem 1rem; }
    }
    @media (max-width:480px) {
      .apt-cal-grid { gap:.1rem; }
      .apt-cal-cell { min-height:44px; border-radius:6px; padding:.3rem .3rem .25rem; }
      .apt-cal-dots { padding-top:.3rem; gap:.2rem; }
    }

  </style>
  <script>(function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();</script>
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════  NAV  ══════════════════════════════════════ -->
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
        <?php if ($counts['pending']): ?>
          <sup class="pv-sup"><?= $counts['pending'] ?></sup>
        <?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"   class="pv-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/portfolio"  class="pv-nav-link">Portfolio</a>
      <a href="<?= BASE_URL ?>provider/schedule"   class="pv-nav-link">Schedule</a>
    </div>

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . '/../_partials/notification_panel.php'; ?>

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

<!-- ══════════════════════════════════════  HERO  ══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div class="pv-hero-text">
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Schedule Management
      </p>
      <h1 class="pv-hero-name">My <em>Appointments</em></h1>
      <p class="pv-hero-date">
        <?= date('l, F j, Y') ?> &mdash;
        <?= number_format($counts['all']) ?> total &bull;
        ₱<?= number_format($totalRevenue, 0) ?> earned
      </p>
    </div>
    <div class="pv-hero-chips" aria-hidden="true">
      <?php if ($counts['pending']): ?>
      <div class="pv-chip pv-chip--amber">
        <i class="fa-solid fa-hourglass-half"></i>
        <?= $counts['pending'] ?> pending
      </div>
      <?php endif; ?>
      <?php if ($counts['confirmed']): ?>
      <div class="pv-chip pv-chip--green">
        <i class="fa-solid fa-calendar-check"></i>
        <?= $counts['confirmed'] ?> confirmed
      </div>
      <?php endif; ?>
      <?php if (!empty($todayAppointments)): ?>
      <div class="pv-chip pv-chip--blue">
        <i class="fa-solid fa-clock"></i>
        <?= count($todayAppointments) ?> today
      </div>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════  MAIN  ══════════════════════════════════════ -->
<main class="pv-page" role="main">

  <!-- ── FILTERS ── -->
  <div class="apt-filters-row">
    <form method="GET" id="filterForm" class="apt-filter-form">
      <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">

      <div class="apt-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="q" class="apt-search-input"
               placeholder="Search customer or service…"
               value="<?= htmlspecialchars($search) ?>">
        <?php if ($search): ?>
          <a href="?view=<?= $viewMode ?>&status=<?= $statusFilter ?>&date=<?= $dateFilter ?>"
             class="apt-search-clear" title="Clear search">
            <i class="fa-solid fa-xmark"></i>
          </a>
        <?php endif; ?>
      </div>

      <button type="submit" class="apt-filter-btn">
        <i class="fa-solid fa-filter"></i> Filter
      </button>

      <select name="status" class="apt-select" onchange="this.form.submit()">
        <?php foreach ($tabLabels as $val => $label): ?>
        <option value="<?= $val ?>" <?= $statusFilter === $val ? 'selected' : '' ?>>
          <?= $label ?>
        </option>
        <?php endforeach; ?>
      </select>

      <select name="date" class="apt-select" onchange="this.form.submit()">
        <?php foreach ($dateLabels as $val => $label): ?>
        <option value="<?= $val ?>" <?= $dateFilter === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <div class="apt-view-toggle" role="group" aria-label="View mode">
      <a href="?view=list&status=<?= $statusFilter ?>&date=<?= $dateFilter ?>&q=<?= urlencode($search) ?>"
         class="apt-view-btn <?= $viewMode === 'list' ? 'is-active' : '' ?>" title="List view">
        <i class="fa-solid fa-table-list"></i>
      </a>
      <a href="?view=calendar&status=<?= $statusFilter ?>&date=<?= $dateFilter ?>&q=<?= urlencode($search) ?>"
         class="apt-view-btn <?= $viewMode === 'calendar' ? 'is-active' : '' ?>" title="Calendar view">
        <i class="fa-solid fa-calendar-days"></i>
      </a>
    </div>
  </div>

  <?php if ($viewMode === 'calendar'): ?>
  <!-- ══════════════════════════════
       CALENDAR VIEW
  ══════════════════════════════ -->
  <div class="apt-calendar-panel pv-panel">
    <div class="pv-panel-head">
      <div>
        <h2><i class="fa-solid fa-calendar-days" style="color:var(--gold);margin-right:.45rem"></i>Appointment Calendar</h2>
        <div class="pv-panel-sub">Colours indicate appointment status — hover dots for details</div>
      </div>
      <!-- Legend -->
      <div class="apt-cal-legend">
        <span class="apt-legend-dot" style="background:#CA8A04"></span><span>Pending</span>
        <span class="apt-legend-dot" style="background:#16A34A"></span><span>Confirmed</span>
        <span class="apt-legend-dot" style="background:#EA580C"></span><span>In Progress</span>
        <span class="apt-legend-dot" style="background:#2563EB"></span><span>Completed</span>
        <span class="apt-legend-dot" style="background:#7C3AED"></span><span>Rescheduled</span>
        <span class="apt-legend-dot" style="background:#DC2626"></span><span>Cancelled</span>
        <span class="apt-legend-dot" style="background:#EC4899"></span><span>Rejected</span>
      </div>
    </div>
    <!-- Calendar renders here — nav + grid injected by JS -->
    <div id="aptCalendar"></div>
  </div>

  <script>
  window.__calEvents = <?= json_encode($calEvents) ?>;
  </script>

  <?php else: ?>
  <!-- ══════════════════════════════
       LIST VIEW
  ══════════════════════════════ -->

  <!-- ── TODAY'S SCHEDULE ── -->
  <?php if (!empty($todayAppointments)): ?>
  <section class="apt-section" aria-label="Today's schedule">
    <div class="apt-section-hd">
      <span class="apt-section-icon apt-section-icon--blue"><i class="fa-solid fa-calendar-day"></i></span>
      <h2>Today's Schedule</h2>
      <span class="apt-count-badge apt-count-badge--blue"><?= count($todayAppointments) ?></span>
    </div>
    <div class="apt-today-grid">
      <?php foreach ($todayAppointments as $apt):
        $fullName = htmlspecialchars($apt['first_name'].' '.$apt['last_name']);
        $initAv   = strtoupper(substr($apt['first_name'],0,1).substr($apt['last_name'],0,1));
      ?>
      <div class="apt-today-card apt-today-card--<?= $apt['status'] ?>">
        <div class="apt-tc-stripe"></div>
        <div class="apt-tc-body">
          <div class="apt-tc-time">
            <span class="apt-tc-time-main"><?= timeLabel($apt['start_time']) ?></span>
            <span class="apt-tc-time-sep">→</span>
            <span class="apt-tc-time-end"><?= timeLabel($apt['end_time']) ?></span>
          </div>
          <div class="apt-tc-customer">
            <div class="apt-avatar">
              <?php if (!empty($apt['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($apt['avatar_url']) ?>" alt="<?= $fullName ?>">
              <?php else: ?><?= $initAv ?><?php endif; ?>
            </div>
            <div class="apt-tc-info">
              <strong><?= $fullName ?></strong>
              <span><?= htmlspecialchars($apt['service_name']) ?></span>
            </div>
          </div>
          <div class="apt-tc-meta">
            <span class="apt-pill apt-pill--<?= $apt['status'] ?>">
              <?= ucfirst(str_replace('_',' ',$apt['status'])) ?>
            </span>
            <span class="apt-pay-badge <?= payBadgeClass($apt['payment_status'] ?? null) ?>">
              <?= payLabel($apt['payment_status'] ?? null) ?>
            </span>
            <span class="apt-tc-dur">
              <i class="fa-regular fa-clock"></i> <?= $apt['duration_minutes'] ?> min
            </span>
            <?php if ($apt['phone']): ?>
            <a href="tel:<?= htmlspecialchars($apt['phone']) ?>" class="apt-tc-call" title="Call customer">
              <i class="fa-solid fa-phone"></i>
            </a>
            <?php endif; ?>
          </div>
          <div class="apt-tc-actions">
            <?php if ($apt['status'] === 'confirmed'): ?>
              <form method="POST" action="<?= BASE_URL ?>provider/appointments/<?= (int)$apt['id'] ?>">
                <input type="hidden" name="action" value="start">
                <button type="submit" class="apt-btn apt-btn--indigo">
                  <i class="fa-solid fa-play"></i> Start
                </button>
              </form>
              <button class="apt-btn apt-btn--amber"
                onclick="openReschedModal(<?= $apt['id'] ?>,'<?= addslashes($fullName) ?>','<?= addslashes($apt['service_name']) ?>','<?= $apt['booking_date'] ?>')">
                <i class="fa-solid fa-rotate-right"></i> Reschedule
              </button>
            <?php elseif ($apt['status'] === 'in_progress'): ?>
              <form method="POST" action="<?= BASE_URL ?>provider/appointments/<?= (int)$apt['id'] ?>">
                <input type="hidden" name="action" value="complete">
                <button type="submit" class="apt-btn apt-btn--green">
                  <i class="fa-solid fa-check"></i> Mark Complete
                </button>
              </form>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>provider/appointments/<?= (int)$apt['id'] ?>" class="apt-btn apt-btn--ghost">
              <i class="fa-solid fa-arrow-right"></i> View
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── PENDING REQUESTS ── -->
  <?php if (!empty($pendingAppointments)): ?>
  <section class="apt-section" aria-label="Pending requests">
    <div class="apt-section-hd">
      <span class="apt-section-icon apt-section-icon--amber"><i class="fa-solid fa-hourglass-half"></i></span>
      <h2>Pending Requests</h2>
      <span class="apt-count-badge apt-count-badge--amber"><?= count($pendingAppointments) ?> need action</span>
    </div>
    <div class="apt-pending-list">
      <?php foreach ($pendingAppointments as $apt):
        $fullName = htmlspecialchars($apt['first_name'].' '.$apt['last_name']);
        $initAv   = strtoupper(substr($apt['first_name'],0,1).substr($apt['last_name'],0,1));
      ?>
      <div class="apt-pending-row">
        <div class="apt-avatar apt-avatar--md">
          <?php if (!empty($apt['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($apt['avatar_url']) ?>" alt="<?= $fullName ?>">
          <?php else: ?><?= $initAv ?><?php endif; ?>
        </div>
        <div class="apt-pr-main">
          <div class="apt-pr-name"><?= $fullName ?></div>
          <div class="apt-pr-sub"><?= htmlspecialchars($apt['service_name']) ?></div>
        </div>
        <div class="apt-pr-when">
          <div class="apt-pr-date"><?= date('D, M j', strtotime($apt['booking_date'])) ?></div>
          <div class="apt-pr-time"><?= timeLabel($apt['start_time']) ?> → <?= timeLabel($apt['end_time']) ?></div>
          <div class="apt-pr-type">
            <i class="fa-solid fa-location-dot"></i>
            <?= htmlspecialchars($apt['location_type']) ?>
            <?php if ($apt['customer_address']): ?>
              &mdash; <?= htmlspecialchars(mb_strimwidth($apt['customer_address'], 0, 30, '…')) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="apt-pr-amount">
          <div class="apt-pr-peso">₱<?= number_format($apt['total_amount'], 2) ?></div>
          <span class="apt-pay-badge <?= payBadgeClass($apt['payment_status'] ?? null) ?>">
            <?= payLabel($apt['payment_status'] ?? null) ?>
          </span>
        </div>
        <div class="apt-pr-actions">
          <button class="apt-action-btn apt-action-btn--accept"
            onclick="openAcceptModal(<?= $apt['id'] ?>,'<?= addslashes($fullName) ?>','<?= addslashes($apt['service_name']) ?>')">
            <i class="fa-solid fa-check"></i> Accept
          </button>
          <button class="apt-action-btn apt-action-btn--decline"
            onclick="openDeclineModal(<?= $apt['id'] ?>,'<?= addslashes($fullName) ?>')">
            <i class="fa-solid fa-xmark"></i> Decline
          </button>
          <a href="<?= BASE_URL ?>provider/appointments/<?= (int)$apt['id'] ?>"
             class="apt-action-btn apt-action-btn--view">
            <i class="fa-solid fa-eye"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── ALL APPOINTMENTS TABLE ── -->
  <section class="apt-section" aria-label="Appointments list">
    <div class="apt-section-hd">
      <span class="apt-section-icon apt-section-icon--gold"><i class="fa-solid fa-calendar-alt"></i></span>
      <h2><?= $tabLabels[$statusFilter] ?></h2>
      <span class="apt-count-badge" style="font-weight:400">
        <?= number_format($totalFiltered) ?> result<?= $totalFiltered !== 1 ? 's' : '' ?>
      </span>
    </div>

    <div class="pv-panel">
      <div class="pv-table-wrap">
        <table class="pv-table" aria-label="Appointments list">
          <colgroup>
            <col style="width:70px">
            <col style="width:22%">
            <col style="width:18%">
            <col style="width:14%">
            <col style="width:8%">
            <col style="width:10%">
            <col style="width:10%">
            <col style="width:10%">
            <col>
          </colgroup>
          <thead>
            <tr>
              <th>Ref</th>
              <th>Customer</th>
              <th>Service</th>
              <th>Date</th>
              <th>Duration</th>
              <th>Amount</th>
              <th>Payment</th>
              <th>Status</th>
              <th style="text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($appointments)): ?>
            <tr>
              <td colspan="9" class="pv-empty">
                <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;color:var(--gold);opacity:.4;display:block;margin-bottom:.6rem"></i>
                No appointments found for this filter.
              </td>
            </tr>
            <?php else: foreach ($appointments as $apt):
              $fullName = htmlspecialchars($apt['first_name'].' '.$apt['last_name']);
              $initAv   = strtoupper(substr($apt['first_name'],0,1).substr($apt['last_name'],0,1));
            ?>
            <tr class="pv-row-clickable"
                onclick="window.location='<?= BASE_URL ?>provider/appointments/<?= (int)$apt['id'] ?>'"
                style="cursor:pointer">
              <td><span class="pv-ref">#<?= str_pad($apt['id'],4,'0',STR_PAD_LEFT) ?></span></td>
              <td>
                <div class="pv-cust">
                  <div class="pv-cust-av">
                    <?php if (!empty($apt['avatar_url'])): ?>
                      <img src="<?= htmlspecialchars($apt['avatar_url']) ?>" alt="<?= $fullName ?>">
                    <?php else: ?><?= $initAv ?><?php endif; ?>
                  </div>
                  <div>
                    <div class="pv-cust-name"><?= $fullName ?></div>
                    <div class="pv-cust-email"><?= htmlspecialchars($apt['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="pv-svc-name" title="<?= htmlspecialchars($apt['service_name']) ?>">
                  <?= htmlspecialchars($apt['service_name']) ?>
                </div>
                <div style="font-size:.68rem;color:var(--text-dim);margin-top:.15rem">
                  <i class="fa-solid fa-location-dot" style="font-size:.6rem"></i>
                  <?= htmlspecialchars($apt['location_type']) ?>
                </div>
              </td>
              <td class="pv-mono pv-muted"><?= date('M d, Y', strtotime($apt['booking_date'])) ?></td>
              <td class="pv-mono pv-muted"><?= $apt['duration_minutes'] ?> min</td>
              <td class="pv-mono pv-gold">₱<?= number_format($apt['total_amount'],2) ?></td>
              <td>
                <span class="apt-pay-badge <?= payBadgeClass($apt['payment_status'] ?? null) ?>">
                  <?= payLabel($apt['payment_status'] ?? null) ?>
                </span>
              </td>
              <td>
                <span class="pv-pill pv-pill--<?= $apt['status'] ?>">
                  <?= ucfirst(str_replace('_',' ',$apt['status'])) ?>
                </span>
                <?php if ($apt['reschedule_count'] > 0): ?>
                <div style="font-size:.62rem;color:var(--purple);margin-top:.2rem">
                  ↺ <?= $apt['reschedule_count'] ?>× rescheduled
                </div>
                <?php endif; ?>
              </td>
              <td>
                <div class="pv-actions-cell" onclick="event.stopPropagation()">
                  <a href="<?= BASE_URL ?>provider/appointments/<?= (int)$apt['id'] ?>"
                     class="pv-act pv-act--view-eye" title="View" onclick="event.stopPropagation()">
                    <i class="fa-solid fa-eye"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1):
        $qs = fn(int $p) => '?view='.$viewMode.'&status='.urlencode($statusFilter).'&date='.urlencode($dateFilter).'&q='.urlencode($search).'&page='.$p;
      ?>
      <nav class="pv-pagination" aria-label="Pagination">
        <a href="<?= $qs($page - 1) ?>"
           class="pv-page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>"
           title="Previous" style="border:none;background:none;">&lt;</a>

        <?php
          $window = 2;
          $start  = max(1, $page - $window);
          $end    = min($totalPages, $page + $window);
          if ($start > 1): ?>
            <a href="<?= $qs(1) ?>" class="pv-page-btn">1</a>
            <?php if ($start > 2): ?>
              <span class="pv-page-btn is-disabled" style="border:none;background:none;width:auto;padding:0 .2rem">…</span>
            <?php endif;
          endif;
          for ($p = $start; $p <= $end; $p++): ?>
            <a href="<?= $qs($p) ?>" class="pv-page-btn <?= $p === $page ? 'is-active' : '' ?>"><?= $p ?></a>
          <?php endfor;
          if ($end < $totalPages):
            if ($end < $totalPages - 1): ?>
              <span class="pv-page-btn is-disabled" style="border:none;background:none;width:auto;padding:0 .2rem">…</span>
            <?php endif; ?>
            <a href="<?= $qs($totalPages) ?>" class="pv-page-btn"><?= $totalPages ?></a>
          <?php endif; ?>

        <a href="<?= $qs($page + 1) ?>"
           class="pv-page-btn <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
           title="Next" style="border:none;background:none;">&gt;</a>
      </nav>
      <?php endif; ?>

    </div><!-- /pv-panel -->
  </section>

  <?php endif; ?>

</main>

<!-- ══════════════════════════════════════  MODALS  ══════════════════════════════════════ -->

<!-- Accept Modal -->
<div class="pv-modal-overlay" id="acceptModal" role="dialog" aria-modal="true" aria-labelledby="acceptModalTitle">
  <div class="pv-modal pv-modal--confirm">
    <button class="pv-modal-close pv-modal-close--abs" onclick="closeModal('acceptModal')">✕</button>
    <div class="modal-centered-header">
      <div class="modal-icon-ring modal-icon-ring--green">
        <i class="fa-solid fa-check" style="color:var(--green);font-size:1.3rem"></i>
      </div>
      <h2 class="modal-title" id="acceptModalTitle">Accept Appointment</h2>
      <p class="modal-sub">
        Confirm appointment for <strong id="acceptName"></strong><br>
        (<em id="acceptService"></em>).<br>
        The customer will be <span class="hl-green">notified immediately.</span>
      </p>
    </div>
    <form method="POST" id="acceptForm">
      <input type="hidden" name="action" value="confirm">
      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--green">
          <i class="fa-solid fa-check"></i> Accept
        </button>
        <button type="button" class="modal-btn modal-btn--no" onclick="closeModal('acceptModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Decline Modal -->
<div class="pv-modal-overlay" id="declineModal" role="dialog" aria-modal="true" aria-labelledby="declineModalTitle">
  <div class="pv-modal pv-modal--delete">
    <button class="pv-modal-close pv-modal-close--abs" onclick="closeModal('declineModal')">✕</button>
    <div class="modal-centered-header">
      <div class="modal-icon-ring modal-icon-ring--red">
        <i class="fa-solid fa-xmark" style="color:var(--red);font-size:1.3rem"></i>
      </div>
      <h2 class="modal-title" id="declineModalTitle">Decline Appointment</h2>
      <p class="modal-sub">
        You are declining the appointment for <strong id="declineName"></strong>.<br>
        The customer will be <span class="hl-red">notified immediately.</span>
        This <span class="hl-red">cannot be undone.</span>
      </p>
    </div>
    <form method="POST" id="declineForm">
      <input type="hidden" name="action" value="reject">
      <label class="modal-field-label" for="declineReason">
        Reason for declining <span class="modal-required">* required</span>
      </label>
      <textarea id="declineReason" name="rejection_reason" class="pv-textarea"
                placeholder="e.g. Schedule fully booked, Not available on requested date…"
                maxlength="400" required></textarea>
      <div class="modal-char-count"><span id="declineCharCount">0</span> / 400</div>
      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--red" id="declineSubmitBtn" disabled>
          <i class="fa-solid fa-xmark"></i> Decline
        </button>
        <button type="button" class="modal-btn modal-btn--no" onclick="closeModal('declineModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Cancel Modal -->
<div class="pv-modal-overlay" id="cancelModal" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
  <div class="pv-modal pv-modal--delete">
    <button class="pv-modal-close pv-modal-close--abs" onclick="closeModal('cancelModal')">✕</button>
    <div class="modal-centered-header">
      <div class="modal-icon-ring modal-icon-ring--red">
        <svg viewBox="0 0 24 24" fill="none" width="26" height="26">
          <path d="M12 8v5M12 16v.5" stroke="#FB7185" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
      </div>
      <h2 class="modal-title" id="cancelModalTitle">Cancel Appointment</h2>
      <p class="modal-sub">
        Cancel the confirmed booking for <strong id="cancelName"></strong>
        (<em id="cancelService"></em>).<br>
        The customer will be <span class="hl-red">immediately notified.</span>
      </p>
    </div>
    <form method="POST" id="cancelForm">
      <input type="hidden" name="action" value="cancel">
      <label class="modal-field-label" for="cancelReason">
        Reason for cancellation <span class="modal-required">* required</span>
      </label>
      <textarea id="cancelReason" name="cancellation_reason" class="pv-textarea"
                placeholder="e.g. Schedule conflict, Emergency unavailability…"
                maxlength="400" required></textarea>
      <div class="modal-char-count"><span id="cancelCharCount">0</span> / 400</div>
      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--red" id="cancelSubmitBtn" disabled>
          <i class="fa-solid fa-ban"></i> Cancel Appointment
        </button>
        <button type="button" class="modal-btn modal-btn--no" onclick="closeModal('cancelModal')">Go Back</button>
      </div>
    </form>
  </div>
</div>

<!-- Reschedule Modal -->
<div class="pv-modal-overlay" id="reschedModal" role="dialog" aria-modal="true" aria-labelledby="reschedModalTitle">
  <div class="pv-modal pv-modal--resched">
    <button class="pv-modal-close pv-modal-close--abs" onclick="closeModal('reschedModal')">✕</button>
    <div class="modal-centered-header">
      <div class="modal-icon-ring modal-icon-ring--amber">
        <i class="fa-solid fa-rotate-right" style="color:var(--amber);font-size:1.25rem"></i>
      </div>
      <h2 class="modal-title" id="reschedModalTitle">Suggest Reschedule</h2>
      <p class="modal-sub">
        Suggest a new schedule for <strong id="reschedName"></strong>
        (<em id="reschedService"></em>).<br>
        <span class="modal-sub-note">
          Current date: <span id="reschedCurrentDate" class="hl-amber"></span>
        </span>
      </p>
    </div>
    <form method="POST" id="reschedForm">
      <input type="hidden" name="action" value="reschedule">
      <div class="resch-row">
        <div class="resch-field">
          <label class="modal-field-label" for="reschedDate">
            Suggested Date <span class="modal-required">*</span>
          </label>
          <input type="date" id="reschedDate" name="suggested_date" class="pv-input" required>
        </div>
        <div class="resch-field">
          <label class="modal-field-label" for="reschedTime">
            Suggested Time <span class="modal-required">*</span>
          </label>
          <input type="time" id="reschedTime" name="suggested_time" class="pv-input" required>
        </div>
      </div>
      <label class="modal-field-label" for="reschedNote" style="display:block;margin-top:.85rem">
        Note to Customer <span class="modal-required">*</span>
      </label>
      <textarea id="reschedNote" name="reschedule_note" class="pv-textarea"
                placeholder="e.g. I have a conflict at the original time. This new slot works better for me…"
                maxlength="500" required></textarea>
      <div class="modal-char-count"><span id="reschedCharCount">0</span> / 500</div>
      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--amber" id="reschedSubmitBtn" disabled>
          <i class="fa-solid fa-paper-plane"></i> Send Suggestion
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Toast container -->
<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- ══════════════════════════════════════  SCRIPTS  ══════════════════════════════════════ -->
<script>
/* ── Generic modal helpers ── */
function closeModal(id) { document.getElementById(id).classList.remove('is-open'); }

document.querySelectorAll('.pv-modal-overlay').forEach(function(o) {
  o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('is-open'); });
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape')
    document.querySelectorAll('.pv-modal-overlay.is-open').forEach(function(m) { m.classList.remove('is-open'); });
});

/* ── Accept ── */
function openAcceptModal(id, name, service) {
  document.getElementById('acceptForm').action = '<?= BASE_URL ?>provider/appointments/' + id;
  document.getElementById('acceptName').textContent    = name;
  document.getElementById('acceptService').textContent = service;
  document.getElementById('acceptModal').classList.add('is-open');
}

/* ── Decline ── */
function openDeclineModal(id, name) {
  document.getElementById('declineForm').action = '<?= BASE_URL ?>provider/appointments/' + id;
  document.getElementById('declineName').textContent = name;
  document.getElementById('declineReason').value = '';
  document.getElementById('declineCharCount').textContent = '0';
  document.getElementById('declineSubmitBtn').disabled = true;
  document.getElementById('declineModal').classList.add('is-open');
  setTimeout(function() { document.getElementById('declineReason').focus(); }, 120);
}
document.getElementById('declineReason').addEventListener('input', function() {
  document.getElementById('declineCharCount').textContent = this.value.length;
  document.getElementById('declineSubmitBtn').disabled = this.value.trim().length < 5;
});

/* ── Cancel ── */
function openCancelModal(id, name, service) {
  document.getElementById('cancelForm').action = '<?= BASE_URL ?>provider/appointments/' + id;
  document.getElementById('cancelName').textContent    = name;
  document.getElementById('cancelService').textContent = service;
  document.getElementById('cancelReason').value = '';
  document.getElementById('cancelCharCount').textContent = '0';
  document.getElementById('cancelSubmitBtn').disabled = true;
  document.getElementById('cancelModal').classList.add('is-open');
  setTimeout(function() { document.getElementById('cancelReason').focus(); }, 120);
}
document.getElementById('cancelReason').addEventListener('input', function() {
  document.getElementById('cancelCharCount').textContent = this.value.length;
  document.getElementById('cancelSubmitBtn').disabled = this.value.trim().length < 5;
});

/* ── Reschedule ── */
function openReschedModal(id, name, service, currentDate) {
  document.getElementById('reschedForm').action = '<?= BASE_URL ?>provider/appointments/' + id;
  document.getElementById('reschedName').textContent    = name;
  document.getElementById('reschedService').textContent = service;
  var d = new Date(currentDate);
  document.getElementById('reschedCurrentDate').textContent =
    isNaN(d) ? currentDate : d.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
  document.getElementById('reschedDate').value  = '';
  document.getElementById('reschedTime').value  = '';
  document.getElementById('reschedNote').value  = '';
  document.getElementById('reschedCharCount').textContent = '0';
  document.getElementById('reschedSubmitBtn').disabled = true;
  document.getElementById('reschedModal').classList.add('is-open');
  setTimeout(function() { document.getElementById('reschedDate').focus(); }, 120);
}
function validateResched() {
  var ok = document.getElementById('reschedDate').value &&
           document.getElementById('reschedTime').value &&
           document.getElementById('reschedNote').value.trim().length >= 5;
  document.getElementById('reschedSubmitBtn').disabled = !ok;
}
document.getElementById('reschedNote').addEventListener('input', function() {
  document.getElementById('reschedCharCount').textContent = this.value.length;
  validateResched();
});
document.getElementById('reschedDate').addEventListener('change', validateResched);
document.getElementById('reschedTime').addEventListener('change', validateResched);

/* ── Toast ── */
function showToast(message, type) {
  type = type || 'success';
  var c = document.getElementById('toastContainer');
  var t = document.createElement('div');
  t.className = 'toast toast--' + type;
  var icon = type === 'success'
    ? '<svg viewBox="0 0 16 16" fill="none"><path d="M3 8l3.5 3.5L13 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    : '<svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.6"/><path d="M8 5v3.5M8 10.5v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
  t.innerHTML = '<span class="toast-icon">' + icon + '</span><span class="toast-msg">' + message + '</span>';
  c.appendChild(t);
  requestAnimationFrame(function() { requestAnimationFrame(function() { t.classList.add('is-visible'); }); });
  setTimeout(function() {
    t.classList.remove('is-visible');
    t.addEventListener('transitionend', function() { t.remove(); }, { once:true });
  }, 4500);
}
<?php if ($flash): ?>
showToast('<?= addslashes(htmlspecialchars_decode($flash['msg'])) ?>','<?= $flash['type'] === 'success' ? 'success' : 'error' ?>');
<?php endif; ?>

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
  applyTheme(localStorage.getItem('qb-theme') || 'light');
  if (btn) btn.addEventListener('click', function () {
    var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    localStorage.setItem('qb-theme', next);
    applyTheme(next);
  });
})();
</script>

<?php if ($viewMode === 'calendar'): ?>
<!-- ════════════════  CALENDAR SCRIPT  ════════════════ -->
<script>
(function () {
  /* ── Status → dot colour ── */
  var DOT_COLOR = {
    pending:     '#CA8A04',   /* yellow  */
    confirmed:   '#16A34A',   /* green   */
    in_progress: '#EA580C',   /* orange  */
    completed:   '#2563EB',   /* blue    */
    rescheduled: '#7C3AED',   /* purple  */
    cancelled:   '#DC2626',   /* red     */
    rejected:    '#EC4899'    /* pink    */
  };

  var events = window.__calEvents || [];
  var today  = new Date();
  var cy     = today.getFullYear();
  var cm     = today.getMonth();

  var MONTHS = ['January','February','March','April','May','June',
                'July','August','September','October','November','December'];
  var DAYS   = ['SUN','MON','TUE','WED','THU','FRI','SAT'];

  /* ── Group events by YYYY-MM-DD ── */
  function groupByDate(evs) {
    var map = {};
    evs.forEach(function (ev) {
      var d = ev.start.substring(0, 10);
      if (!map[d]) map[d] = { total: 0, statuses: {} };
      map[d].total++;
      var s = ev.status || 'pending';
      map[d].statuses[s] = (map[d].statuses[s] || 0) + 1;
    });
    return map;
  }

  /* ── Build & inject HTML ── */
  function buildCalendar(year, month) {
    var container = document.getElementById('aptCalendar');
    if (!container) return;

    var firstDay    = new Date(year, month, 1).getDay();
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var evMap       = groupByDate(events);
    var html        = '';

    /* Nav bar */
    html  = '<div class="apt-cal-nav">'
      + '<button class="apt-cal-nav-btn" onclick="calPrev()" title="Previous month">'
      +   '<i class="fa-solid fa-chevron-left" style="font-size:.72rem;pointer-events:none"></i>'
      + '</button>'
      + '<span class="apt-cal-month-title">' + MONTHS[month] + ' ' + year + '</span>'
      + '<div class="apt-cal-nav-right">'
      +   '<button class="apt-cal-nav-btn" onclick="calNext()" title="Next month">'
      +     '<i class="fa-solid fa-chevron-right" style="font-size:.72rem;pointer-events:none"></i>'
      +   '</button>'
      +   '<button class="apt-cal-today-btn" onclick="calToday()">Today</button>'
      + '</div>'
      + '</div>';

    /* Grid */
    html += '<div class="apt-cal-grid">';

    /* Day-of-week headers */
    DAYS.forEach(function (d) { html += '<div class="apt-cal-dow">' + d + '</div>'; });

    /* Empty leading cells */
    for (var i = 0; i < firstDay; i++) {
      html += '<div class="apt-cal-cell apt-cal-cell--empty"></div>';
    }

    /* Day cells */
    for (var day = 1; day <= daysInMonth; day++) {
      var dateStr = year + '-'
        + String(month + 1).padStart(2, '0') + '-'
        + String(day).padStart(2, '0');

      var isToday = (year  === today.getFullYear()
                  && month === today.getMonth()
                  && day   === today.getDate());
      var data    = evMap[dateStr];
      var hasEvs  = !!data;

      var cls = 'apt-cal-cell';
      if (isToday) cls += ' apt-cal-cell--today';
      if (hasEvs)  cls += ' apt-cal-cell--has-events';

      html += '<div class="' + cls + '">';
      html += '<span class="apt-cal-day-num">' + day + '</span>';

      if (hasEvs) {
        /* Up to 6 unique status dots */
        var statuses = Object.keys(data.statuses);
        html += '<div class="apt-cal-dots">';
        statuses.slice(0, 6).forEach(function (s) {
          var color = DOT_COLOR[s] || '#888';
          var label = s.replace('_', ' ');
          html += '<span class="apt-cal-dot"'
                + ' style="background:' + color + '"'
                + ' title="' + data.statuses[s] + ' ' + label + '"></span>';
        });
        html += '</div>';

        /* Count label when > 3 appointments */
        if (data.total > 3) {
          html += '<span class="apt-cal-count">' + data.total + ' appointments</span>';
        }
      }

      html += '</div>';
    }

    html += '</div>'; /* /apt-cal-grid */
    container.innerHTML = html;
  }

  /* ── Navigation (global so inline onclick works) ── */
  window.calToday = function () { cy = today.getFullYear(); cm = today.getMonth(); buildCalendar(cy, cm); };
  window.calPrev  = function () { if (--cm < 0)  { cm = 11; cy--; } buildCalendar(cy, cm); };
  window.calNext  = function () { if (++cm > 11) { cm = 0;  cy++; } buildCalendar(cy, cm); };

  buildCalendar(cy, cm);
})();
</script>
<?php endif; ?>

<script>
/* ── Profile dropdown ── */
(function () {
  var trigger  = document.getElementById('profileTrigger');
  var dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;
  function open()   { trigger.classList.add('is-open');    dropdown.classList.add('is-open');    trigger.setAttribute('aria-expanded','true'); }
  function close()  { trigger.classList.remove('is-open'); dropdown.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); }
  function toggle() { dropdown.classList.contains('is-open') ? close() : open(); }
  trigger.addEventListener('click',   function(e){ e.stopPropagation(); toggle(); });
  trigger.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); toggle(); } if(e.key==='Escape') close(); });
  document.addEventListener('click',   function(e){ if(!dropdown.contains(e.target)&&!trigger.contains(e.target)) close(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
})();
</script>
</body>
</html>