<?php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$providerId   = $_SESSION['user_id'] ?? 0;
$providerName = htmlspecialchars($_SESSION['user_name']  ?? 'Provider');
$email        = htmlspecialchars($_SESSION['user_email'] ?? '');
$firstName    = htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'Provider')[0]);

/* ── Provider profile ── */
$stmt = $db->prepare("SELECT * FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$providerId]);
$profile      = $stmt->fetch();
$profileId    = $profile['id'] ?? 0;
$initials     = strtoupper(substr($providerName, 0, 2));
$profilePhoto = $profile['profile_photo'] ?? null;

/* ── Pending bookings count ── */
$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$profileId]);
$pendingBookings = (int)$stmt->fetchColumn();

/* ── Flash ── */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ── Days config ── */
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

/* ── Weekly availability ── */
$stmt = $db->prepare("SELECT * FROM tbl_provider_availability WHERE provider_id = ?");
$stmt->execute([$profileId]);
$rows = $stmt->fetchAll();
$availability = [];
foreach ($rows as $row) { $availability[$row['day_of_week']] = $row; }

/* ── Stats ── */
$activeDays = count(array_filter($availability, fn($r) => $r['is_available'] ?? 0));
$totalHours = 0;
foreach ($availability as $r) {
    if (($r['is_available'] ?? 0) && !empty($r['start_time']) && !empty($r['end_time'])) {
        $s = strtotime($r['start_time']); $e = strtotime($r['end_time']);
        if ($e > $s) $totalHours += ($e - $s) / 3600;
    }
}

/* ── Today's status ── */
$todayDay    = date('l');
$todayAvail  = $availability[$todayDay] ?? null;
$isOpenToday = (bool)($todayAvail['is_available'] ?? false);
$todayStart  = $isOpenToday ? substr($todayAvail['start_time'] ?? '09:00', 0, 5) : null;
$todayEnd    = $isOpenToday ? substr($todayAvail['end_time']   ?? '18:00', 0, 5) : null;

/* ── Today's bookings ── */
$stToday = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND booking_date = CURDATE() AND status NOT IN ('cancelled','declined')");
$stToday->execute([$profileId]);
$todayCount = (int)$stToday->fetchColumn();

/* ── Next appointment ── */
$stNext = $db->prepare("SELECT booking_time FROM tbl_bookings WHERE provider_id = ? AND booking_date = CURDATE() AND status IN ('confirmed','pending') AND booking_time >= CURTIME() ORDER BY booking_time ASC LIMIT 1");
$stNext->execute([$profileId]);
$nextRaw  = $stNext->fetchColumn();
$nextAppt = $nextRaw ? date('g:i A', strtotime($nextRaw)) : null;

/* ── Calendar bookings for current month ── */
$calYear = (int)date('Y'); $calMonth = (int)date('m');
$stCal = $db->prepare("SELECT DATE_FORMAT(booking_date,'%Y-%m-%d') AS d, COUNT(*) AS cnt FROM tbl_bookings WHERE provider_id=? AND YEAR(booking_date)=? AND MONTH(booking_date)=? AND status NOT IN ('cancelled','declined') GROUP BY booking_date");
$stCal->execute([$profileId, $calYear, $calMonth]);
$calBookings = [];
foreach ($stCal->fetchAll() as $r) { $calBookings[$r['d']] = (int)$r['cnt']; }

/* ── Upcoming appointments ── */
$stUp = $db->prepare("SELECT b.booking_date,b.booking_time,b.status,CONCAT(u.first_name,' ',u.last_name) AS cust,s.name AS svc FROM tbl_bookings b JOIN tbl_users u ON b.customer_id=u.id JOIN tbl_services s ON b.service_id=s.id WHERE b.provider_id=? AND b.status IN ('confirmed','pending') AND b.booking_date>=CURDATE() ORDER BY b.booking_date ASC,b.booking_time ASC LIMIT 5");
$stUp->execute([$profileId]);
$upcomingList = $stUp->fetchAll();

/* ── Slot settings (graceful fallback) ── */
$slotDuration = 60; $slotInterval = 30; $maxDaily = 12;
try {
    $st = $db->prepare("SELECT * FROM tbl_provider_slot_settings WHERE provider_id=? LIMIT 1");
    $st->execute([$profileId]);
    $ss = $st->fetch();
    if ($ss) { $slotDuration=(int)($ss['duration_minutes']??60); $slotInterval=(int)($ss['interval_minutes']??30); $maxDaily=(int)($ss['max_daily_bookings']??12); }
} catch (\Throwable $e) {}

/* ── Blocked dates (graceful fallback) ── */
$blockedDates = []; $blockedArr = [];
try {
    $st = $db->prepare("SELECT * FROM tbl_provider_blocked_dates WHERE provider_id=? AND blocked_date>=CURDATE() ORDER BY blocked_date ASC LIMIT 20");
    $st->execute([$profileId]);
    $blockedDates = $st->fetchAll();
    $blockedArr   = array_column($blockedDates, 'blocked_date');
} catch (\Throwable $e) {}

/* ── Available slots today ── */
$availToday = 0;
if ($isOpenToday && $todayStart && $todayEnd) {
    $sm = (int)substr($todayStart,0,2)*60+(int)substr($todayStart,3,2);
    $em = (int)substr($todayEnd,0,2)*60+(int)substr($todayEnd,3,2);
    $possible = max(0, floor(($em-$sm) / max(1,$slotDuration)));
    $availToday = max(0, $possible - $todayCount);
}

/* ── Available weekday names ── */
$availWeekdays = array_keys(array_filter($availability, fn($r) => $r['is_available'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Provider Schedule</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_schedule.css">
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

    <!-- Logo -->
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo"
           style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Provider</span>
    </a>

    <!-- Centre nav links -->
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"     class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/appointments"  class="pv-nav-link">
        Appointments
        <?php if ($pendingBookings): ?><sup class="pv-sup"><?= $pendingBookings ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"   class="pv-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/portfolio"  class="pv-nav-link">Portfolio</a>
      <a href="<?= BASE_URL ?>provider/schedule"   class="pv-nav-link is-active">Schedule</a>
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
          <?php if($profilePhoto):?><img src="<?=htmlspecialchars($profilePhoto)?>" alt="<?=$providerName?>"><?php else:?><?=$initials?><?php endif;?>
        </div>
        <div class="pv-nav-user"><div class="pv-nav-user-name"><?=$firstName?></div></div>
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>

      <!-- Profile dropdown menu -->
      <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="pv-pd-header">
          <div class="pv-pd-avatar"><?php if($profilePhoto):?><img src="<?=htmlspecialchars($profilePhoto)?>" alt=""><?php else:?><?=$initials?><?php endif;?></div>
          <div class="pv-pd-info"><div class="pv-pd-name"><?=$providerName?></div><div class="pv-pd-email"><?=$email?></div><span class="pv-pd-role">Provider</span></div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>provider/profile"   class="pv-pd-item" role="menuitem"><span class="pv-pd-item-ico"><i class="fa-solid fa-store"></i></span><span>Business Profile</span><svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a>
        <a href="<?= BASE_URL ?>provider/settings"  class="pv-pd-item" role="menuitem"><span class="pv-pd-item-ico"><i class="fa-solid fa-gear"></i></span><span>Settings</span><svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>auth/logout" class="pv-pd-item pv-pd-item--danger" role="menuitem"><span class="pv-pd-item-ico"><i class="fa-solid fa-arrow-right-from-bracket"></i></span><span>Sign Out</span></a>
      </div>
    </div>
  </div>
</nav>

<!-- ══════════════════════════════════════
     HERO SECTION WITH BACKGROUND
══════════════════════════════════════ -->
<header class="pv-hero" style="--hero-img: url('<?= BASE_URL ?>assets/img/provider_bg.png');" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  
  <div class="pv-hero-inner">
    <!-- Left content -->
    <div>
      <p class="pv-hero-eyebrow"><span class="pv-dot-pulse" aria-hidden="true"></span>Schedule Management</p>
      <h1 class="pv-hero-name">Manage your <em>availability</em></h1>
      <p class="pv-hero-sub">and booking schedule.</p>
      <p class="pv-hero-description">Set your working hours and control appointment availability across all your services.</p>
      <div class="pv-hero-meta">
        <span class="pv-status-badge">
          <span class="pv-status-dot"></span>
          <?php if($isOpenToday): ?>Open Today<?php else: ?>Closed Today<?php endif; ?>
        </span>
        <span class="pv-hero-stat">
          <strong><?= $activeDays ?></strong> Active Days
        </span>
      </div>
    </div>

    <!-- Right: Status Card -->
    <div class="pv-status-card">
      <div class="pv-status-top">
        <?php if($isOpenToday): ?>
          <span class="pv-status-badge-open">
            <span class="pv-status-dot"></span>Available Today
          </span>
        <?php else: ?>
          <span class="pv-status-badge-closed">
            <span class="pv-status-dot" style="background:var(--red);animation:none;"></span>Unavailable Today
          </span>
        <?php endif; ?>
        <span class="pv-status-day"><?= date('l, M j') ?></span>
      </div>
      <?php if($isOpenToday && $todayStart && $todayEnd): ?>
      <div class="pv-status-hours">
        <i class="fa-regular fa-clock"></i>
        <?= date('g:i A', strtotime($todayStart)) ?> &ndash; <?= date('g:i A', strtotime($todayEnd)) ?>
      </div>
      <?php endif; ?>
      <div class="pv-status-row">
        <div class="pv-status-stat">
          <span class="pv-status-stat-val"><?= $todayCount ?></span>
          <span class="pv-status-stat-lbl">Bookings</span>
        </div>
        <div class="pv-status-divider"></div>
        <div class="pv-status-stat">
          <span class="pv-status-stat-val pv-status-stat-val--green"><?= $availToday ?></span>
          <span class="pv-status-stat-lbl">Available</span>
        </div>
        <div class="pv-status-divider"></div>
        <div class="pv-status-stat">
          <span class="pv-status-stat-val pv-status-stat-val--gold"><?= $nextAppt ?? '—' ?></span>
          <span class="pv-status-stat-lbl">Next Appt.</span>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- ══ QUICK ACTIONS ══ -->
<div class="pv-actions-bar">
  <div class="pv-actions-inner">
    <button class="pv-action-btn pv-action-btn--primary" onclick="document.getElementById('avForm').scrollIntoView({behavior:'smooth'})">
      <i class="fa-solid fa-plus"></i>Add Time Slot
    </button>
    <button class="pv-action-btn" onclick="document.getElementById('blockSection').scrollIntoView({behavior:'smooth'})">
      <i class="fa-regular fa-calendar-xmark"></i>Block Date
    </button>
    <button class="pv-action-btn" id="pauseBtn" onclick="togglePause(this)">
      <i class="fa-solid fa-pause"></i>Pause Bookings
    </button>
    <button class="pv-action-btn" onclick="openCalPreview()">
      <i class="fa-regular fa-eye"></i>Preview Availability
    </button>
  </div>
</div>

<!-- ══ FLASH ══ -->
<?php if($flash): ?>
<div class="sc-flash sc-flash--<?= $flash['type'] ?>" role="alert">
  <i class="fa-solid <?= $flash['type']==='success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
  <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<!-- ══ MAIN PAGE ══ -->
<main class="sc-page" role="main">
  <div class="sc-layout">

    <!-- ══════ LEFT MAIN ══════ -->
    <div class="sc-main">

      <!-- ─── SECTION 1: WEEKLY WORKING HOURS ─── -->
      <section class="sc-card" aria-label="Weekly working hours">
        <div class="sc-card-head">
          <div class="sc-card-head-left">
            <span class="sc-card-icon"><i class="fa-regular fa-calendar-days"></i></span>
            <div>
              <h2 class="sc-card-title">Weekly Working Hours</h2>
              <p class="sc-card-sub">Set your recurring schedule — toggles which days you accept bookings</p>
            </div>
          </div>
          <div class="sc-bulk-btns">
            <button type="button" class="sc-ghost-btn" onclick="setAll(true)">Enable All</button>
            <button type="button" class="sc-ghost-btn sc-ghost-btn--red" onclick="setAll(false)">Clear All</button>
          </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>provider/availability/store" id="avForm">
          <div class="sc-days-list" role="list">
            <?php foreach($days as $day):
              $row      = $availability[$day] ?? null;
              $isActive = (bool)($row['is_available'] ?? false);
              $start    = substr($row['start_time'] ?? '09:00', 0, 5);
              $end      = substr($row['end_time']   ?? '18:00', 0, 5);
              $isWeekend = in_array($day, ['Saturday','Sunday']);
              $abbr      = substr($day, 0, 3);
            ?>
            <div class="sc-day-row <?= $isActive ? 'is-active' : '' ?> <?= $isWeekend ? 'is-weekend' : '' ?>"
                 id="row-<?= $day ?>" data-day="<?= $day ?>">

              <div class="sc-day-meta">
                <div class="sc-day-abbr"><?= $abbr ?></div>
                <div class="sc-day-info">
                  <span class="sc-day-name"><?= $day ?></span>
                  <span class="sc-day-status" id="status-<?= $day ?>"><?= $isActive ? $start.' – '.$end : 'Unavailable' ?></span>
                </div>
              </div>

              <div class="sc-day-times" id="times-<?= $day ?>">
                <div class="sc-time-group">
                  <label class="sc-time-lbl" for="start-<?= $day ?>">Opens</label>
                  <div class="sc-time-wrap">
                    <i class="fa-regular fa-clock sc-time-ico"></i>
                    <input type="time" id="start-<?= $day ?>" name="days[<?= $day ?>][start_time]" value="<?= $start ?>" class="sc-time-input" <?= $isActive?'':'disabled' ?> onchange="updateStatus('<?= $day ?>')">
                  </div>
                </div>
                <span class="sc-time-arrow">→</span>
                <div class="sc-time-group">
                  <label class="sc-time-lbl" for="end-<?= $day ?>">Closes</label>
                  <div class="sc-time-wrap">
                    <i class="fa-regular fa-clock sc-time-ico"></i>
                    <input type="time" id="end-<?= $day ?>" name="days[<?= $day ?>][end_time]" value="<?= $end ?>" class="sc-time-input" <?= $isActive?'':'disabled' ?> onchange="updateStatus('<?= $day ?>')">
                  </div>
                </div>
                <span class="sc-hours-badge" id="badge-<?= $day ?>"></span>
              </div>

              <!-- Break time toggle -->
              <div class="sc-break-wrap" id="break-wrap-<?= $day ?>">
                <button type="button" class="sc-break-toggle" onclick="toggleBreak('<?= $day ?>')" id="break-btn-<?= $day ?>">
                  <i class="fa-solid fa-coffee"></i>
                  <span id="break-btn-label-<?= $day ?>">Add Break</span>
                </button>
                <div class="sc-break-fields" id="break-fields-<?= $day ?>" style="display:none">
                  <div class="sc-time-group">
                    <label class="sc-time-lbl" for="bstart-<?= $day ?>">Break Start</label>
                    <div class="sc-time-wrap">
                      <i class="fa-regular fa-clock sc-time-ico"></i>
                      <input type="time" id="bstart-<?= $day ?>" name="days[<?= $day ?>][break_start]" value="12:00" class="sc-time-input sc-time-input--break">
                    </div>
                  </div>
                  <span class="sc-time-arrow">→</span>
                  <div class="sc-time-group">
                    <label class="sc-time-lbl" for="bend-<?= $day ?>">Break End</label>
                    <div class="sc-time-wrap">
                      <i class="fa-regular fa-clock sc-time-ico"></i>
                      <input type="time" id="bend-<?= $day ?>" name="days[<?= $day ?>][break_end]" value="13:00" class="sc-time-input sc-time-input--break">
                    </div>
                  </div>
                </div>
              </div>

              <div class="sc-day-toggle">
                <label class="sc-toggle" aria-label="Toggle <?= $day ?>">
                  <input type="checkbox" name="days[<?= $day ?>][is_available]" value="1" <?= $isActive?'checked':'' ?> onchange="toggleDay('<?= $day ?>',this.checked)" class="sc-toggle-input">
                  <span class="sc-toggle-track"><span class="sc-toggle-thumb"></span></span>
                </label>
              </div>

            </div>
            <?php endforeach; ?>
          </div>

          <div class="sc-form-footer">
            <div class="sc-unsaved" id="changeIndicator" style="opacity:0">
              <span class="sc-unsaved-dot"></span>Unsaved changes
            </div>
            <button type="submit" class="sc-save-btn">
              <i class="fa-solid fa-floppy-disk"></i>Save Schedule
            </button>
          </div>
        </form>
      </section>

      <!-- ─── SECTION 2: APPOINTMENT SLOT SETTINGS ─── -->
      <section class="sc-card" id="slotSection" aria-label="Appointment slot settings">
        <div class="sc-card-head">
          <div class="sc-card-head-left">
            <span class="sc-card-icon sc-card-icon--blue"><i class="fa-solid fa-sliders"></i></span>
            <div>
              <h2 class="sc-card-title">Appointment Slot Settings</h2>
              <p class="sc-card-sub">Control appointment duration, intervals, and daily booking capacity</p>
            </div>
          </div>
        </div>
        <div class="sc-slot-grid">
          <div class="sc-slot-item">
            <div class="sc-slot-icon sc-slot-icon--gold"><i class="fa-regular fa-hourglass-half"></i></div>
            <label class="sc-slot-label" for="slotDuration">Appointment Duration</label>
            <div class="sc-slot-input-wrap">
              <input type="number" id="slotDuration" class="sc-slot-input" value="<?= $slotDuration ?>" min="15" max="480" step="15">
              <span class="sc-slot-unit">min</span>
            </div>
            <p class="sc-slot-hint">How long each appointment takes</p>
          </div>
          <div class="sc-slot-item">
            <div class="sc-slot-icon sc-slot-icon--blue"><i class="fa-solid fa-arrows-left-right-to-line"></i></div>
            <label class="sc-slot-label" for="slotInterval">Booking Interval</label>
            <div class="sc-slot-input-wrap">
              <input type="number" id="slotInterval" class="sc-slot-input" value="<?= $slotInterval ?>" min="0" max="120" step="15">
              <span class="sc-slot-unit">min</span>
            </div>
            <p class="sc-slot-hint">Gap between consecutive bookings</p>
          </div>
          <div class="sc-slot-item">
            <div class="sc-slot-icon sc-slot-icon--green"><i class="fa-solid fa-users"></i></div>
            <label class="sc-slot-label" for="maxBookings">Max Daily Bookings</label>
            <div class="sc-slot-input-wrap">
              <input type="number" id="maxBookings" class="sc-slot-input" value="<?= $maxDaily ?>" min="1" max="100">
              <span class="sc-slot-unit">/ day</span>
            </div>
            <p class="sc-slot-hint">Maximum appointments per day</p>
          </div>
        </div>
        <div class="sc-slot-footer">
          <div class="sc-slot-notice">
            <i class="fa-solid fa-circle-info"></i>
            Auto-blocking is active — confirmed bookings automatically become unavailable to prevent double-booking.
          </div>
          <button type="button" class="sc-save-btn sc-save-btn--outline" onclick="saveSlotSettings()">
            <i class="fa-solid fa-floppy-disk"></i>Save Settings
          </button>
        </div>
      </section>

      <!-- ─── SECTION 3: BLOCKED DATES ─── -->
      <section class="sc-card" id="blockSection" aria-label="Blocked dates">
        <div class="sc-card-head">
          <div class="sc-card-head-left">
            <span class="sc-card-icon sc-card-icon--red"><i class="fa-regular fa-calendar-xmark"></i></span>
            <div>
              <h2 class="sc-card-title">Blocked Dates &amp; Time Off</h2>
              <p class="sc-card-sub">Block specific dates for vacations, holidays, or emergencies</p>
            </div>
          </div>
        </div>

        <!-- Add block form -->
        <div class="sc-block-form">
          <div class="sc-block-form-row">
            <div class="sc-block-field">
              <label class="sc-time-lbl" for="blockDate">Date to Block</label>
              <input type="date" id="blockDate" class="sc-date-input" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="sc-block-field sc-block-field--grow">
              <label class="sc-time-lbl" for="blockReason">Reason (optional)</label>
              <input type="text" id="blockReason" class="sc-text-input" placeholder="e.g. Vacation, Holiday, Personal Leave">
            </div>
            <button type="button" class="sc-add-block-btn" onclick="addBlockDate()">
              <i class="fa-solid fa-ban"></i>Block Date
            </button>
          </div>
        </div>

        <!-- Blocked dates list -->
        <div class="sc-block-list" id="blockList">
          <?php if(empty($blockedDates)): ?>
          <div class="sc-block-empty" id="blockEmpty">
            <i class="fa-regular fa-calendar-check"></i>
            <p>No blocked dates — you're available on all working days.</p>
          </div>
          <?php else: ?>
            <?php foreach($blockedDates as $bd): ?>
            <div class="sc-block-item" data-date="<?= htmlspecialchars($bd['blocked_date']) ?>">
              <div class="sc-block-item-left">
                <span class="sc-block-item-ico"><i class="fa-solid fa-ban"></i></span>
                <div>
                  <span class="sc-block-item-date"><?= date('F j, Y', strtotime($bd['blocked_date'])) ?></span>
                  <span class="sc-block-item-reason"><?= htmlspecialchars($bd['reason'] ?? 'No reason given') ?></span>
                </div>
              </div>
              <button type="button" class="sc-block-remove" onclick="removeBlock(this, '<?= htmlspecialchars($bd['blocked_date']) ?>')" aria-label="Remove block">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

    </div><!-- /sc-main -->

    <!-- ══════ RIGHT SIDEBAR ══════ -->
    <aside class="sc-sidebar">

      <!-- ─── SECTION 4: CALENDAR VIEW ─── -->
      <div class="sc-card sc-card--calendar">
        <div class="sc-card-head sc-card-head--compact">
          <div class="sc-card-head-left">
            <span class="sc-card-icon sc-card-icon--gold"><i class="fa-regular fa-calendar"></i></span>
            <h3 class="sc-card-title">Availability Calendar</h3>
          </div>
          <div class="sc-cal-nav">
            <button class="sc-cal-nav-btn" id="calPrev" onclick="calNav(-1)" aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></button>
            <span class="sc-cal-month" id="calMonth"></span>
            <button class="sc-cal-nav-btn" id="calNext" onclick="calNav(1)" aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></button>
          </div>
        </div>

        <!-- Calendar grid -->
        <div class="sc-cal-body">
          <div class="sc-cal-weekdays">
            <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
          </div>
          <div class="sc-cal-grid" id="calGrid"></div>
        </div>

        <!-- Legend -->
        <div class="sc-cal-legend">
          <span class="sc-legend-item"><span class="sc-legend-dot sc-legend-dot--avail"></span>Available</span>
          <span class="sc-legend-item"><span class="sc-legend-dot sc-legend-dot--partial"></span>Partial</span>
          <span class="sc-legend-item"><span class="sc-legend-dot sc-legend-dot--full"></span>Full</span>
          <span class="sc-legend-item"><span class="sc-legend-dot sc-legend-dot--blocked"></span>Blocked</span>
        </div>
      </div>

      <!-- ─── SECTION 5: UPCOMING SCHEDULE SUMMARY ─── -->
      <div class="sc-card">
        <div class="sc-card-head sc-card-head--compact">
          <div class="sc-card-head-left">
            <span class="sc-card-icon sc-card-icon--green"><i class="fa-regular fa-clock"></i></span>
            <h3 class="sc-card-title">Upcoming Schedule</h3>
          </div>
          <a href="<?= BASE_URL ?>provider/appointments" class="sc-link">View all →</a>
        </div>

        <!-- Today's quick stats -->
        <div class="sc-today-stats">
          <div class="sc-today-stat">
            <div class="sc-today-val"><?= $todayCount ?></div>
            <div class="sc-today-lbl">Today's Appts.</div>
          </div>
          <div class="sc-today-stat">
            <div class="sc-today-val sc-today-val--green"><?= $availToday ?></div>
            <div class="sc-today-lbl">Available Slots</div>
          </div>
          <div class="sc-today-stat">
            <div class="sc-today-val sc-today-val--gold"><?= $nextAppt ?? '—' ?></div>
            <div class="sc-today-lbl">Next Booking</div>
          </div>
        </div>

        <!-- Upcoming list -->
        <?php if(empty($upcomingList)): ?>
        <div class="sc-empty">
          <i class="fa-regular fa-calendar-check"></i>
          <p>No upcoming appointments</p>
        </div>
        <?php else: ?>
        <div class="sc-upcoming-list">
          <?php foreach($upcomingList as $ap): ?>
          <div class="sc-upcoming-item">
            <div class="sc-upcoming-dot <?= $ap['status']==='confirmed'?'sc-upcoming-dot--confirmed':'sc-upcoming-dot--pending' ?>"></div>
            <div class="sc-upcoming-info">
              <span class="sc-upcoming-name"><?= htmlspecialchars($ap['cust']) ?></span>
              <span class="sc-upcoming-svc"><?= htmlspecialchars($ap['svc']) ?></span>
            </div>
            <div class="sc-upcoming-time">
              <span class="sc-upcoming-date"><?= date('M j', strtotime($ap['booking_date'])) ?></span>
              <span class="sc-upcoming-hour"><?= !empty($ap['booking_time']) ? date('g:i A', strtotime($ap['booking_time'])) : 'TBD' ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- ─── WEEK AT A GLANCE ─── -->
      <div class="sc-card sc-card--glance">
        <div class="sc-card-head sc-card-head--compact">
          <div class="sc-card-head-left">
            <span class="sc-card-icon"><i class="fa-solid fa-chart-simple"></i></span>
            <h3 class="sc-card-title">Week at a Glance</h3>
          </div>
        </div>
        <div class="sc-glance-list" id="glanceList">
          <?php foreach($days as $day):
            $r = $availability[$day] ?? null;
            $on = (bool)($r['is_available'] ?? false);
            $s = $on ? strtotime($r['start_time']??'09:00') : 0;
            $e = $on ? strtotime($r['end_time']??'18:00') : 0;
            $h = ($on && $e>$s) ? round(($e-$s)/3600,1) : 0;
            $pct = $on ? min(100, round($h/12*100)) : 0;
            $weekend = in_array($day,['Saturday','Sunday']);
          ?>
          <div class="sc-glance-row" id="grow-<?= $day ?>">
            <span class="sc-glance-lbl"><?= substr($day,0,3) ?></span>
            <div class="sc-glance-bar-wrap">
              <div class="sc-glance-bar <?= $weekend?'sc-glance-bar--wknd':'' ?> <?= !$on?'sc-glance-bar--off':'' ?>" style="width:<?= $pct ?>%" id="gbar-<?= $day ?>"></div>
            </div>
            <span class="sc-glance-hrs" id="ghrs-<?= $day ?>"><?= $on ? $h.'h' : '—' ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </aside><!-- /sc-sidebar -->
  </div><!-- /sc-layout -->
</main>

<!-- ══ SCRIPTS ══ -->
<script>
// ── Calendar data from PHP ──
const calBookings   = <?= json_encode($calBookings) ?>;
const maxDaily      = <?= (int)$maxDaily ?>;
const availWeekdays = <?= json_encode($availWeekdays) ?>;
const blockedDatesData = <?= json_encode($blockedArr) ?>;

let calYear = <?= $calYear ?>, calMonth = <?= $calMonth - 1 ?>; // JS months 0-indexed

function renderCalendar(year, month) {
  const today = new Date();
  const firstDay = new Date(year, month, 1);
  const lastDay  = new Date(year, month + 1, 0);
  const startWd  = firstDay.getDay();
  const weekdays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

  document.getElementById('calMonth').textContent = monthNames[month] + ' ' + year;

  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';

  // Empty cells
  for (let i = 0; i < startWd; i++) {
    const el = document.createElement('div');
    el.className = 'sc-cal-cell sc-cal-cell--empty';
    grid.appendChild(el);
  }

  for (let d = 1; d <= lastDay.getDate(); d++) {
    const mm  = String(month + 1).padStart(2, '0');
    const dd  = String(d).padStart(2, '0');
    const key = `${year}-${mm}-${dd}`;
    const dayWd = weekdays[new Date(year, month, d).getDay()];

    const cnt       = calBookings[key] || 0;
    const isBlocked = blockedDatesData.includes(key);
    const isDayOff  = !availWeekdays.includes(dayWd);
    const isToday   = (d === today.getDate() && month === today.getMonth() && year === today.getFullYear());
    const isPast    = new Date(year, month, d) < new Date(today.getFullYear(), today.getMonth(), today.getDate());

    let cls = 'sc-cal-cell';
    if (isBlocked)       cls += ' sc-cal-cell--blocked';
    else if (isDayOff)   cls += ' sc-cal-cell--off';
    else if (cnt >= maxDaily) cls += ' sc-cal-cell--full';
    else if (cnt > 0)    cls += ' sc-cal-cell--partial';
    else                 cls += ' sc-cal-cell--avail';
    if (isToday)         cls += ' sc-cal-cell--today';
    if (isPast)          cls += ' sc-cal-cell--past';

    const el = document.createElement('div');
    el.className = cls;
    el.title = isBlocked ? 'Blocked' : (isDayOff ? 'Day off' : (cnt > 0 ? cnt + ' booking(s)' : 'Available'));
    el.innerHTML = `<span class="sc-cal-day">${d}</span>${cnt > 0 ? '<span class="sc-cal-pip"></span>' : ''}`;
    grid.appendChild(el);
  }
}

function calNav(dir) {
  calMonth += dir;
  if (calMonth > 11) { calMonth = 0; calYear++; }
  if (calMonth < 0)  { calMonth = 11; calYear--; }
  renderCalendar(calYear, calMonth);
}

// ── Toggle day on/off ──
function toggleDay(day, active) {
  const row   = document.getElementById('row-' + day);
  const times = document.getElementById('times-' + day);
  const stat  = document.getElementById('status-' + day);
  const bWrap = document.getElementById('break-wrap-' + day);
  row.classList.toggle('is-active', active);
  times.querySelectorAll('input[type="time"]').forEach(i => i.disabled = !active);
  if (!active) {
    stat.textContent = 'Unavailable';
    if (bWrap) bWrap.style.opacity = '0.35';
  } else {
    updateStatus(day);
    if (bWrap) bWrap.style.opacity = '1';
  }
  updateGlance(day, active);
  markUnsaved();
}

function updateStatus(day) {
  const s = document.getElementById('start-' + day);
  const e = document.getElementById('end-'   + day);
  const st = document.getElementById('status-' + day);
  if (!s || !e) return;
  if (s.value && e.value) {
    st.textContent = fmt12(s.value) + ' – ' + fmt12(e.value);
    updateBadge(day, s.value, e.value);
    updateGlanceBar(day, s.value, e.value);
  }
  markUnsaved();
}

function fmt12(t) {
  const [h, m] = t.split(':').map(Number);
  const ap = h >= 12 ? 'PM' : 'AM';
  return (h % 12 || 12) + (m ? ':' + String(m).padStart(2, '0') : '') + ' ' + ap;
}

function hrsFrom(s, e) {
  const [sh,sm] = s.split(':').map(Number), [eh,em] = e.split(':').map(Number);
  const d = (eh*60+em) - (sh*60+sm);
  return d > 0 ? d / 60 : 0;
}

function updateBadge(day, s, e) {
  const b = document.getElementById('badge-' + day);
  if (!b) return;
  const h = hrsFrom(s, e);
  b.textContent = h > 0 ? (h % 1 ? h.toFixed(1) : h) + 'h' : '';
  b.style.opacity = h > 0 ? '1' : '0';
}

function updateGlance(day, active) {
  const bar = document.getElementById('gbar-' + day);
  const hrs = document.getElementById('ghrs-' + day);
  if (!bar) return;
  if (!active) { bar.style.width='0%'; bar.classList.add('sc-glance-bar--off'); if(hrs) hrs.textContent='—'; return; }
  bar.classList.remove('sc-glance-bar--off');
  const s = document.getElementById('start-'+day), e = document.getElementById('end-'+day);
  if (s && e) updateGlanceBar(day, s.value, e.value);
}

function updateGlanceBar(day, s, e) {
  const bar = document.getElementById('gbar-' + day);
  const hrs = document.getElementById('ghrs-' + day);
  if (!bar) return;
  const h = hrsFrom(s, e), pct = Math.min(100, Math.round(h/12*100));
  bar.style.width = pct + '%';
  if (hrs) hrs.textContent = h > 0 ? (h%1?h.toFixed(1):h)+'h' : '—';
}

function setAll(active) {
  ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].forEach(day => {
    const cb = document.querySelector(`input[name="days[${day}][is_available]"]`);
    if (cb) { cb.checked = active; toggleDay(day, active); }
  });
}

function toggleBreak(day) {
  const fields = document.getElementById('break-fields-' + day);
  const btn    = document.getElementById('break-btn-label-' + day);
  const show   = fields.style.display === 'none';
  fields.style.display = show ? 'flex' : 'none';
  if (btn) btn.textContent = show ? 'Remove Break' : 'Add Break';
}

function markUnsaved() {
  const el = document.getElementById('changeIndicator');
  if (el) el.style.opacity = '1';
}

// ── Block date UI (client-side preview; needs backend wiring) ──
function addBlockDate() {
  const dateEl   = document.getElementById('blockDate');
  const reasonEl = document.getElementById('blockReason');
  const date     = dateEl.value;
  if (!date) { dateEl.focus(); return; }
  const reason = reasonEl.value || 'No reason given';

  const empty = document.getElementById('blockEmpty');
  if (empty) empty.remove();

  const list = document.getElementById('blockList');
  const item = document.createElement('div');
  item.className = 'sc-block-item';
  item.dataset.date = date;

  const d = new Date(date + 'T00:00:00');
  const label = d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

  item.innerHTML = `
    <div class="sc-block-item-left">
      <span class="sc-block-item-ico"><i class="fa-solid fa-ban"></i></span>
      <div>
        <span class="sc-block-item-date">${label}</span>
        <span class="sc-block-item-reason">${reason}</span>
      </div>
    </div>
    <button type="button" class="sc-block-remove" onclick="removeBlock(this,'${date}')" aria-label="Remove block">
      <i class="fa-solid fa-xmark"></i>
    </button>`;

  list.appendChild(item);
  dateEl.value = ''; reasonEl.value = '';

  // Re-render calendar to reflect new block
  const yr = parseInt(date.split('-')[0]);
  const mo = parseInt(date.split('-')[1]) - 1;
  if (yr === calYear && mo === calMonth) {
    if (!blockedDatesData.includes(date)) blockedDatesData.push(date);
    renderCalendar(calYear, calMonth);
  }
}

function removeBlock(btn, date) {
  btn.closest('.sc-block-item').remove();
  const idx = blockedDatesData.indexOf(date);
  if (idx > -1) blockedDatesData.splice(idx, 1);
  renderCalendar(calYear, calMonth);
  if (!document.querySelector('#blockList .sc-block-item')) {
    const list = document.getElementById('blockList');
    list.innerHTML = '<div class="sc-block-empty" id="blockEmpty"><i class="fa-regular fa-calendar-check"></i><p>No blocked dates — you\'re available on all working days.</p></div>';
  }
}

// ── Slot settings save (toaster — needs backend wiring) ──
function saveSlotSettings() {
  const d = document.getElementById('slotDuration').value;
  const i = document.getElementById('slotInterval').value;
  const m = document.getElementById('maxBookings').value;
  showToast('Slot settings saved: ' + d + 'min duration, ' + i + 'min interval, max ' + m + '/day');
}

function togglePause(btn) {
  btn.classList.toggle('sc-action-btn--active');
  const paused = btn.classList.contains('sc-action-btn--active');
  btn.innerHTML = paused
    ? '<i class="fa-solid fa-play"></i>Resume Bookings'
    : '<i class="fa-solid fa-pause"></i>Pause Bookings';
  showToast(paused ? 'Bookings paused — new customers cannot book.' : 'Bookings resumed.');
}

function openCalPreview() {
  document.querySelector('.sc-card--calendar').scrollIntoView({ behavior: 'smooth' });
}

function showToast(msg) {
  const t = document.createElement('div');
  t.className = 'sc-toast'; t.textContent = msg;
  document.body.appendChild(t);
  requestAnimationFrame(() => t.classList.add('sc-toast--in'));
  setTimeout(() => { t.classList.remove('sc-toast--in'); setTimeout(() => t.remove(), 300); }, 3000);
}

// ── Init ──
document.addEventListener('DOMContentLoaded', () => {
  renderCalendar(calYear, calMonth);
  document.getElementById('avForm').addEventListener('change', markUnsaved);

  // Init badges + glance
  ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].forEach(day => {
    const row = document.getElementById('row-' + day);
    const s   = document.getElementById('start-' + day);
    const e   = document.getElementById('end-'   + day);
    if (row && row.classList.contains('is-active') && s && e) {
      updateBadge(day, s.value, e.value);
      updateGlanceBar(day, s.value, e.value);
    }
  });

  // Stagger row animation
  document.querySelectorAll('.sc-day-row').forEach((el, i) => {
    el.style.animationDelay = (i * 0.055) + 's';
  });
});
</script>

<script>
/* ── Theme toggle ── */
(function(){
  var btn=document.getElementById('themeToggle');
  var moon=btn&&btn.querySelector('.icon-moon'), sun=btn&&btn.querySelector('.icon-sun');
  function applyTheme(t){
    if(t==='dark'){document.documentElement.setAttribute('data-theme','dark');if(moon)moon.style.display='block';if(sun)sun.style.display='none';}
    else{document.documentElement.removeAttribute('data-theme');if(moon)moon.style.display='none';if(sun)sun.style.display='block';}
  }
  applyTheme(localStorage.getItem('qb-theme')||'light');
  if(btn)btn.addEventListener('click',function(){var n=document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark';localStorage.setItem('qb-theme',n);applyTheme(n);});
})();
/* ── Profile dropdown ── */
(function(){
  var tr=document.getElementById('profileTrigger'),dr=document.getElementById('profileDropdown');
  if(!tr||!dr)return;
  function open(){tr.classList.add('is-open');dr.classList.add('is-open');tr.setAttribute('aria-expanded','true');}
  function close(){tr.classList.remove('is-open');dr.classList.remove('is-open');tr.setAttribute('aria-expanded','false');}
  tr.addEventListener('click',function(e){e.stopPropagation();dr.classList.contains('is-open')?close():open();});
  tr.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();dr.classList.contains('is-open')?close():open();}if(e.key==='Escape')close();});
  document.addEventListener('click',function(e){if(!dr.contains(e.target)&&!tr.contains(e.target))close();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
})();
</script>
</body>
</html>