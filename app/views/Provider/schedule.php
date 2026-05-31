<?php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$providerId   = $_SESSION['user_id'] ?? 0;
$providerName = htmlspecialchars($_SESSION['user_name']  ?? 'Provider');
$email        = htmlspecialchars($_SESSION['user_email'] ?? '');
$firstName    = htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'Provider')[0]);

/* ── Provider profile ── */
$stmt = $db->prepare("SELECT pp.*, c.name AS category_name, u.first_name, u.last_name FROM tbl_provider_profiles pp LEFT JOIN tbl_categories c ON pp.category_id = c.id LEFT JOIN tbl_users u ON pp.user_id = u.id WHERE pp.user_id = ? LIMIT 1");
$stmt->execute([$providerId]);
$profile      = $stmt->fetch();
$profileId    = $profile['id'] ?? 0;
$firstName    = htmlspecialchars($profile['first_name'] ?? explode(' ', $providerName)[0]);
$provFullName = htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: $providerName);
$initials     = strtoupper(substr($providerName, 0, 2));
$profilePhoto = $profile['profile_photo'] ?? null;
$bizCategory  = htmlspecialchars($profile['category_name'] ?? 'Service Provider');

/* ── Pending bookings count ── */
$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$profileId]);
$pendingBookings = (int)$stmt->fetchColumn();

/* ── Flash ── */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ── Days config ── */
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

/* ── Ensure break columns exist (graceful migration) ── */
try {
    $db->exec("ALTER TABLE tbl_provider_availability ADD COLUMN break_start TIME DEFAULT NULL");
} catch (\Throwable $e) {}
try {
    $db->exec("ALTER TABLE tbl_provider_availability ADD COLUMN break_end TIME DEFAULT NULL");
} catch (\Throwable $e) {}

/* ── Clean up accidental default break values (12:00/13:00 saved on all days) ── */
/* Only clear breaks on unavailable days — a provider would never intentionally add a
   break to a day they don't work, so any break there is from the bug */
try {
    $db->prepare("UPDATE tbl_provider_availability
        SET break_start = NULL, break_end = NULL
        WHERE provider_id = ? AND is_available = 0")->execute([$profileId]);
} catch (\Throwable $e) {}

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
$slotDuration = null; $slotInterval = null; $maxDaily = null;
$hasSlotSettings = false;
try {
    $st = $db->prepare("SELECT * FROM tbl_provider_slot_settings WHERE provider_id=? LIMIT 1");
    $st->execute([$profileId]);
    $ss = $st->fetch();
    if ($ss) {
        $slotDuration    = (int)($ss['duration_minutes']   ?? 60);
        $slotInterval    = (int)($ss['interval_minutes']   ?? 30);
        $maxDaily        = (int)($ss['max_daily_bookings'] ?? 12);
        $hasSlotSettings = true;
    }
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
    $possible = max(0, floor(($em-$sm) / max(1, $slotDuration ?? 60)));
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
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const isOpen = <?= $isOpenToday ? 'true' : 'false' ?>;
      const badge = document.querySelector('.pv-hero-meta .pv-status-badge');
      if (badge) {
        if (isOpen) {
          badge.classList.add('status-available');
          badge.classList.remove('status-unavailable');
        } else {
          badge.classList.add('status-unavailable');
          badge.classList.remove('status-available');
        }
      }
    });
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
      <a href="<?= BASE_URL ?>provider/appointments" class="pv-nav-link">
        Appointments
        <?php if ($pendingBookings): ?><sup class="pv-sup"><?= $pendingBookings ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/portfolio"  class="pv-nav-link">Portfolio</a>
      <a href="<?= BASE_URL ?>provider/schedule"   class="pv-nav-link is-active">Schedule</a>
      <a href="<?= BASE_URL ?>provider/reviews"    class="pv-nav-link">Reviews</a>
    </div>

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$providerId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      </button>

      <!-- Profile dropdown trigger -->
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0"
           aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av">
          <?php if ($profilePhoto): ?>
            <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $providerName ?>">
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
              <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $providerName ?>">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $provFullName ?></div>
            <div class="pv-pd-email"><?= $email ?></div>
            <span class="pv-pd-role"><?= $bizCategory ?></span>
          </div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>provider/profile" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-store"></i></span>
          <span>Profile</span>
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
<header class="pv-hero" style="--hero-img: url('<?= BASE_URL ?>assets/img/provider_bg.png');" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow"><span class="pv-dot-pulse" aria-hidden="true"></span>Schedule Management</p>
      <h1 class="pv-hero-name">Manage your <span class="pv-hero-highlight">availability</span><br>and booking <span class="pv-hero-highlight">schedule</span></h1>
      <p class="pv-hero-description">Set your working hours and control appointment availability across all your services.</p>
      <div class="pv-hero-meta">
        <span class="pv-status-badge">
          <?php if($isOpenToday): ?>Available<?php else: ?>Unavailable<?php endif; ?>
        </span>
      </div>
    </div>

    <div class="pv-status-card">
      <div class="pv-status-top">
        <?php if($isOpenToday): ?>
          <span class="pv-status-badge-open"><span class="pv-status-dot"></span>Open Today</span>
        <?php else: ?>
          <span class="pv-status-badge-closed"><span class="pv-status-dot" style="background:var(--red);animation:none;"></span>Closed Today</span>
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
              $row        = $availability[$day] ?? null;
              $isActive   = (bool)($row['is_available'] ?? false);
              $start      = substr($row['start_time']  ?? '09:00', 0, 5);
              $end        = substr($row['end_time']    ?? '18:00', 0, 5);
              $breakStart = !empty($row['break_start']) ? substr($row['break_start'], 0, 5) : '12:00';
              $breakEnd   = !empty($row['break_end'])   ? substr($row['break_end'],   0, 5) : '13:00';
              $hasBreak   = !empty($row['break_start']) && !empty($row['break_end']);
              $isWeekend  = in_array($day, ['Saturday','Sunday']);
            ?>
            <div class="sc-day-row <?= $isActive ? 'is-active' : '' ?> <?= $isWeekend ? 'is-weekend' : '' ?>"
                 id="row-<?= $day ?>" data-day="<?= $day ?>">

              <div class="sc-day-checkbox">
                <label class="sc-checkbox" aria-label="Toggle <?= $day ?>">
                  <input type="checkbox" name="days[<?= $day ?>][is_available]" value="1"
                         <?= $isActive?'checked':'' ?>
                         onchange="toggleDay('<?= $day ?>',this.checked)"
                         class="sc-checkbox-input">
                  <span class="sc-checkbox-mark"></span>
                </label>
              </div>

              <div class="sc-day-meta">
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
                    <input type="time" id="start-<?= $day ?>" name="days[<?= $day ?>][start_time]"
                           value="<?= $start ?>" class="sc-time-input"
                           <?= $isActive?'':'disabled' ?> onchange="updateStatus('<?= $day ?>')">
                  </div>
                </div>
                <span class="sc-time-arrow">→</span>
                <div class="sc-time-group">
                  <label class="sc-time-lbl" for="end-<?= $day ?>">Closes</label>
                  <div class="sc-time-wrap">
                    <i class="fa-regular fa-clock sc-time-ico"></i>
                    <input type="time" id="end-<?= $day ?>" name="days[<?= $day ?>][end_time]"
                           value="<?= $end ?>" class="sc-time-input"
                           <?= $isActive?'':'disabled' ?> onchange="updateStatus('<?= $day ?>')">
                  </div>
                </div>
                <span class="sc-hours-badge" id="badge-<?= $day ?>"></span>
              </div>

              <div class="sc-break-wrap" id="break-wrap-<?= $day ?>" style="<?= $isActive ? '' : 'display:none' ?>">
                <button type="button" class="sc-break-toggle" onclick="toggleBreak('<?= $day ?>')" id="break-btn-<?= $day ?>">
                  <i class="fa-solid <?= $hasBreak ? 'fa-minus' : 'fa-plus' ?>" id="break-icon-<?= $day ?>"></i>
                  <span id="break-btn-label-<?= $day ?>"><?= $hasBreak ? 'Remove Break' : 'Add Break' ?></span>
                </button>
                <div class="sc-break-fields" id="break-fields-<?= $day ?>" style="<?= $hasBreak ? 'display:flex' : 'display:none' ?>">
                  <div class="sc-time-group">
                    <label class="sc-time-lbl" for="bstart-<?= $day ?>">Break Start</label>
                    <div class="sc-time-wrap">
                      <i class="fa-regular fa-clock sc-time-ico"></i>
                      <input type="time" id="bstart-<?= $day ?>" name="days[<?= $day ?>][break_start]" value="<?= $breakStart ?>" class="sc-time-input sc-time-input--break" <?= $hasBreak ? '' : 'disabled' ?>>
                    </div>
                  </div>
                  <span class="sc-time-arrow">→</span>
                  <div class="sc-time-group">
                    <label class="sc-time-lbl" for="bend-<?= $day ?>">Break End</label>
                    <div class="sc-time-wrap">
                      <i class="fa-regular fa-clock sc-time-ico"></i>
                      <input type="time" id="bend-<?= $day ?>" name="days[<?= $day ?>][break_end]" value="<?= $breakEnd ?>" class="sc-time-input sc-time-input--break" <?= $hasBreak ? '' : 'disabled' ?>>
                    </div>
                  </div>
                </div>
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

      <!-- ─── SECTION 2: SLOT SETTINGS ─── -->

      <!-- Slot Settings Modal -->
      <div class="sc-block-modal-overlay" id="slotModalOverlay" onclick="closeSlotModal(event)">
        <div class="sc-block-modal" role="dialog" aria-modal="true" aria-labelledby="slotModalTitle">
          <div class="sc-block-modal-head">
            <div class="sc-block-modal-head-left">
              <span class="sc-block-modal-icon" style="background:var(--blue-lt);border-color:var(--blue-border);color:var(--blue)"><i class="fa-solid fa-sliders"></i></span>
              <div>
                <h3 class="sc-block-modal-title" id="slotModalTitle">Edit Slot Settings</h3>
                <p class="sc-block-modal-sub">Duration, interval &amp; daily capacity</p>
              </div>
            </div>
            <button class="sc-block-modal-close" onclick="closeSlotModal()" aria-label="Close">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
          <div class="sc-block-modal-body">
            <div class="sc-slot-modal-grid">
              <div class="sc-slot-modal-field">
                <div class="sc-slot-icon sc-slot-icon--gold"><i class="fa-regular fa-hourglass-half"></i></div>
                <label class="sc-time-lbl" for="slotDuration">Appointment Duration</label>
                <div class="sc-slot-input-wrap">
                  <input type="number" id="slotDuration" class="sc-slot-input" value="<?= $hasSlotSettings ? $slotDuration : '' ?>" placeholder="e.g. 60" min="15" max="480" step="15" oninput="syncSlotDisplay()">
                  <span class="sc-slot-unit">min</span>
                </div>
                <p class="sc-slot-hint">How long each appointment takes</p>
              </div>
              <div class="sc-slot-modal-field">
                <div class="sc-slot-icon sc-slot-icon--blue"><i class="fa-solid fa-arrows-left-right-to-line"></i></div>
                <label class="sc-time-lbl" for="slotInterval">Booking Interval</label>
                <div class="sc-slot-input-wrap">
                  <input type="number" id="slotInterval" class="sc-slot-input" value="<?= $hasSlotSettings ? $slotInterval : '' ?>" placeholder="e.g. 30" min="0" max="120" step="15" oninput="syncSlotDisplay()">
                  <span class="sc-slot-unit">min</span>
                </div>
                <p class="sc-slot-hint">Gap between consecutive bookings</p>
              </div>
              <div class="sc-slot-modal-field">
                <div class="sc-slot-icon sc-slot-icon--green"><i class="fa-solid fa-users"></i></div>
                <label class="sc-time-lbl" for="maxBookings">Max Daily Bookings</label>
                <div class="sc-slot-input-wrap">
                  <input type="number" id="maxBookings" class="sc-slot-input" value="<?= $hasSlotSettings ? $maxDaily : '' ?>" placeholder="e.g. 12" min="1" max="100" oninput="syncSlotDisplay()">
                  <span class="sc-slot-unit">/ day</span>
                </div>
                <p class="sc-slot-hint">Maximum appointments per day</p>
              </div>
            </div>
          </div>
          <div class="sc-block-modal-footer">
            <button type="button" class="sc-block-modal-cancel" onclick="closeSlotModal()">Cancel</button>
            <button type="button" class="sc-save-btn sc-save-btn--outline" onclick="saveSlotSettings()">
              <i class="fa-solid fa-floppy-disk"></i>Save Settings
            </button>
          </div>
        </div>
      </div>

      <section class="sc-card" id="slotSection" aria-label="Appointment slot settings">
        <div class="sc-card-head">
          <div class="sc-card-head-left">
            <span class="sc-card-icon sc-card-icon--blue"><i class="fa-solid fa-sliders"></i></span>
            <div>
              <h2 class="sc-card-title">Appointment Slot Settings</h2>
              <p class="sc-card-sub">Control appointment duration, intervals, and daily booking capacity</p>
            </div>
          </div>
          <button type="button" class="sc-open-block-btn sc-open-slot-btn" onclick="openSlotModal()">
            <i class="fa-solid fa-pen-to-square"></i>Edit Settings
          </button>
        </div>
        <div class="sc-slot-grid">
          <div class="sc-slot-item">
            <div class="sc-slot-icon sc-slot-icon--gold"><i class="fa-regular fa-hourglass-half"></i></div>
            <span class="sc-slot-label">Appointment Duration</span>
            <div class="sc-slot-display-wrap">
              <span class="sc-slot-display-val" id="displayDuration"><?= $hasSlotSettings ? $slotDuration : '—' ?></span>
              <?php if ($hasSlotSettings): ?><span class="sc-slot-unit">min</span><?php endif; ?>
            </div>
            <p class="sc-slot-hint">How long each appointment takes</p>
          </div>
          <div class="sc-slot-item">
            <div class="sc-slot-icon sc-slot-icon--blue"><i class="fa-solid fa-arrows-left-right-to-line"></i></div>
            <span class="sc-slot-label">Booking Interval</span>
            <div class="sc-slot-display-wrap">
              <span class="sc-slot-display-val" id="displayInterval"><?= $hasSlotSettings ? $slotInterval : '—' ?></span>
              <?php if ($hasSlotSettings): ?><span class="sc-slot-unit">min</span><?php endif; ?>
            </div>
            <p class="sc-slot-hint">Gap between consecutive bookings</p>
          </div>
          <div class="sc-slot-item">
            <div class="sc-slot-icon sc-slot-icon--green"><i class="fa-solid fa-users"></i></div>
            <span class="sc-slot-label">Max Daily Bookings</span>
            <div class="sc-slot-display-wrap">
              <span class="sc-slot-display-val" id="displayMaxBookings"><?= $hasSlotSettings ? $maxDaily : '—' ?></span>
              <?php if ($hasSlotSettings): ?><span class="sc-slot-unit">/ day</span><?php endif; ?>
            </div>
            <p class="sc-slot-hint">Maximum appointments per day</p>
          </div>
        </div>
        <div class="sc-slot-footer">
          <div class="sc-slot-notice">
            <i class="fa-solid fa-circle-info"></i>
            Auto-blocking is active — confirmed bookings automatically become unavailable to prevent double-booking.
          </div>
        </div>
      </section>

      <!-- ─── SECTION 3: BLOCKED DATES ─── -->
      <!-- Block Date Modal -->
      <div class="sc-block-modal-overlay" id="blockModalOverlay" onclick="closeBlockModal(event)">
        <div class="sc-block-modal sc-block-modal--range" role="dialog" aria-modal="true" aria-labelledby="blockModalTitle">
          <div class="sc-block-modal-head">
            <div class="sc-block-modal-head-left">
              <span class="sc-block-modal-icon"><i class="fa-regular fa-calendar-xmark"></i></span>
              <div>
                <h3 class="sc-block-modal-title" id="blockModalTitle">Block Time Off</h3>
                <p class="sc-block-modal-sub">Block a single day or a date range</p>
              </div>
            </div>
            <button class="sc-block-modal-close" onclick="closeBlockModal()" aria-label="Close">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
          <div class="sc-block-modal-body">

            <!-- Date range row -->
            <div class="sc-block-range-row">
              <div class="sc-block-modal-field sc-block-range-field">
                <label class="sc-time-lbl" for="blockDateFrom">
                  <i class="fa-regular fa-calendar"></i> From
                </label>
                <input type="date" id="blockDateFrom" class="sc-date-input"
                       min="<?= date('Y-m-d') ?>"
                       onchange="onBlockRangeChange()">
              </div>
              <div class="sc-block-range-arrow">
                <i class="fa-solid fa-arrow-right"></i>
              </div>
              <div class="sc-block-modal-field sc-block-range-field">
                <label class="sc-time-lbl" for="blockDateTo">
                  <i class="fa-regular fa-calendar"></i> To
                </label>
                <input type="date" id="blockDateTo" class="sc-date-input"
                       min="<?= date('Y-m-d') ?>"
                       onchange="onBlockRangeChange()">
              </div>
            </div>

            <!-- Live day count badge -->
            <div class="sc-block-range-summary" id="blockRangeSummary" style="display:none">
              <i class="fa-solid fa-circle-info"></i>
              <span id="blockRangeSummaryText"></span>
            </div>

            <!-- Reason -->
            <div class="sc-block-modal-field">
              <label class="sc-time-lbl" for="blockReason">
                Reason <span class="sc-block-modal-optional">(Optional)</span>
              </label>
              <input type="text" id="blockReason" class="sc-text-input"
                     placeholder="e.g. Vacation, Holiday, Personal Leave">
            </div>

          </div>
          <div class="sc-block-modal-footer">
            <button type="button" class="sc-block-modal-cancel" onclick="closeBlockModal()">Cancel</button>
            <button type="button" class="sc-add-block-btn" id="blockSubmitBtn" onclick="addBlockDate()">
              <i class="fa-solid fa-ban"></i><span id="blockSubmitLabel">Block Date</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Confirm Delete Modal -->
      <div class="sc-block-modal-overlay" id="confirmDeleteOverlay" onclick="closeConfirmDelete(event)">
        <div class="sc-block-modal sc-confirm-modal" role="dialog" aria-modal="true">
          <div class="sc-confirm-icon-wrap">
            <span class="sc-confirm-icon"><i class="fa-solid fa-trash"></i></span>
          </div>
          <h3 class="sc-confirm-title">Remove Blocked Date?</h3>
          <p class="sc-confirm-msg" id="confirmDeleteMsg"></p>
          <div class="sc-confirm-actions">
            <button type="button" class="sc-block-modal-cancel" onclick="closeConfirmDelete()">Cancel</button>
            <button type="button" class="sc-confirm-delete-btn" id="confirmDeleteBtn" onclick="confirmDelete()">
              <i class="fa-solid fa-trash"></i>Yes, Remove
            </button>
          </div>
        </div>
      </div>

      <!-- Edit Block Modal -->
      <div class="sc-block-modal-overlay" id="editBlockModalOverlay" onclick="closeEditBlockModal(event)">
        <div class="sc-block-modal" role="dialog" aria-modal="true" aria-labelledby="editBlockModalTitle">
          <div class="sc-block-modal-head">
            <div class="sc-block-modal-head-left">
              <span class="sc-block-modal-icon"><i class="fa-solid fa-pen"></i></span>
              <div>
                <h3 class="sc-block-modal-title" id="editBlockModalTitle">Edit Blocked Date</h3>
                <p class="sc-block-modal-sub" id="editBlockModalSub">Update the reason for this block</p>
              </div>
            </div>
            <button class="sc-block-modal-close" onclick="closeEditBlockModal()" aria-label="Close">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
          <div class="sc-block-modal-body">
            <input type="hidden" id="editBlockOriginalDate">
            <div class="sc-block-modal-field">
              <label class="sc-time-lbl" for="editBlockDate">
                <i class="fa-regular fa-calendar"></i> Date
              </label>
              <input type="date" id="editBlockDate" class="sc-date-input"
                     min="<?= date('Y-m-d') ?>">
            </div>
            <div class="sc-block-modal-field">
              <label class="sc-time-lbl" for="editBlockReason">
                Reason <span class="sc-block-modal-optional">(Optional)</span>
              </label>
              <input type="text" id="editBlockReason" class="sc-text-input"
                     placeholder="e.g. Vacation, Holiday, Personal Leave">
            </div>
          </div>
          <div class="sc-block-modal-footer">
            <button type="button" class="sc-block-modal-cancel" onclick="closeEditBlockModal()">Cancel</button>
            <button type="button" class="sc-add-block-btn" onclick="saveEditBlock()">
              <i class="fa-solid fa-floppy-disk"></i>Save Changes
            </button>
          </div>
        </div>
      </div>

      <section class="sc-card" id="blockSection" aria-label="Blocked dates">
        <div class="sc-card-head">
          <div class="sc-card-head-left">
            <span class="sc-card-icon sc-card-icon--red"><i class="fa-regular fa-calendar-xmark"></i></span>
            <div>
              <h2 class="sc-card-title">Blocked Dates &amp; Time Off</h2>
              <p class="sc-card-sub">Block specific dates for vacations, holidays, or emergencies</p>
            </div>
          </div>
          <button type="button" class="sc-open-block-btn" onclick="openBlockModal()">
            <i class="fa-solid fa-ban"></i>Block a Date
          </button>
        </div>
        <div class="sc-block-list" id="blockList">
          <?php if(empty($blockedDates)): ?>
          <div class="sc-block-empty" id="blockEmpty">
            <i class="fa-regular fa-calendar-check"></i>
            <p>No blocked dates — you're available on all working days.</p>
          </div>
          <?php else: ?>
            <?php foreach($blockedDates as $bd): ?>
            <div class="sc-block-item" data-date="<?= htmlspecialchars($bd['blocked_date']) ?>" data-reason="<?= htmlspecialchars($bd['reason'] ?? '') ?>">
              <div class="sc-block-item-left">
                <span class="sc-block-item-ico"><i class="fa-solid fa-ban"></i></span>
                <div>
                  <span class="sc-block-item-date"><?= date('F j, Y', strtotime($bd['blocked_date'])) ?></span>
                  <span class="sc-block-item-reason"><?= htmlspecialchars($bd['reason'] ?? 'No reason given') ?></span>
                </div>
              </div>
              <div class="sc-block-actions">
                <button type="button" class="sc-block-action-btn sc-block-action-btn--edit" onclick="editBlock(this, '<?= htmlspecialchars($bd['blocked_date']) ?>')" aria-label="Edit reason">
                  <i class="fa-solid fa-pen"></i>
                </button>
                <button type="button" class="sc-block-action-btn sc-block-action-btn--remove" onclick="removeBlock(this, '<?= htmlspecialchars($bd['blocked_date']) ?>')" aria-label="Remove block">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
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

        <!-- Row 1: Icon + Title -->
        <div class="sc-cal-title-row">
          <span class="sc-card-icon"><i class="fa-regular fa-calendar"></i></span>
          <h3 class="sc-card-title">Availability Calendar</h3>
        </div>

        <!-- Row 2: Month navigation -->
        <div class="sc-cal-nav-row">
          <button class="sc-cal-nav-btn" id="calPrev" onclick="calNav(-1)" aria-label="Previous month">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <span class="sc-cal-month" id="calMonth"></span>
          <button class="sc-cal-nav-btn" id="calNext" onclick="calNav(1)" aria-label="Next month">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>

        <!-- Calendar grid -->
        <div class="sc-cal-body">
          <div class="sc-cal-weekdays">
            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
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

      <!-- ─── SECTION 5: UPCOMING SCHEDULE ─── -->
      <div class="sc-card">
        <div class="sc-card-head sc-card-head--compact">
          <div class="sc-card-head-left">
            <span class="sc-card-icon sc-card-icon--green"><i class="fa-regular fa-clock"></i></span>
            <h3 class="sc-card-title">Upcoming Schedule</h3>
          </div>
          <a href="<?= BASE_URL ?>provider/appointments" class="sc-link">View all →</a>
        </div>
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

    </aside>
  </div>
</main>

<!-- ══ SCRIPTS ══ -->
<script>
const BASE_URL         = '<?= BASE_URL ?>';
const calBookings      = <?= json_encode($calBookings) ?>;
const maxDaily         = <?= $hasSlotSettings ? (int)$maxDaily : 'Infinity' ?>;
const availWeekdays    = <?= json_encode($availWeekdays) ?>;
const blockedDatesData = <?= json_encode($blockedArr) ?>;

let calYear = <?= $calYear ?>, calMonth = <?= $calMonth - 1 ?>;

function renderCalendar(year, month) {
  const today     = new Date();
  const firstDay  = new Date(year, month, 1);
  const lastDay   = new Date(year, month + 1, 0);
  const startWd   = firstDay.getDay();
  const weekdays  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

  document.getElementById('calMonth').textContent = monthNames[month] + ' ' + year;

  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';

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
    if (isBlocked)            cls += ' sc-cal-cell--blocked';
    else if (isDayOff)        cls += ' sc-cal-cell--off';
    else if (cnt >= maxDaily) cls += ' sc-cal-cell--full';
    else if (cnt > 0)         cls += ' sc-cal-cell--partial';
    else                      cls += ' sc-cal-cell--avail';
    if (isToday) cls += ' sc-cal-cell--today';
    if (isPast)  cls += ' sc-cal-cell--past';

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

function toggleDay(day, active) {
  const row    = document.getElementById('row-' + day);
  const times  = document.getElementById('times-' + day);
  const stat   = document.getElementById('status-' + day);
  const bWrap  = document.getElementById('break-wrap-' + day);
  const fields = document.getElementById('break-fields-' + day);
  const bstart = document.getElementById('bstart-' + day);
  const bend   = document.getElementById('bend-'   + day);
  const btn    = document.getElementById('break-btn-label-' + day);
  const icon   = document.getElementById('break-icon-' + day);

  row.classList.toggle('is-active', active);
  times.querySelectorAll('input[type="time"]').forEach(i => i.disabled = !active);

  if (!active) {
    stat.textContent = 'Unavailable';
    // Hide the entire break section and reset it
    if (bWrap)   bWrap.style.display  = 'none';
    if (fields)  fields.style.display = 'none';
    if (bstart)  { bstart.disabled = true;  bstart.value = ''; }
    if (bend)    { bend.disabled   = true;  bend.value   = ''; }
    if (btn)     btn.textContent  = 'Add Break';
    if (icon)    icon.className   = 'fa-solid fa-plus';
  } else {
    updateStatus(day);
    // Show the break button (but not the fields — user must click Add Break)
    if (bWrap) bWrap.style.display = '';
  }
  updateGlance(day, active);
  markUnsaved();
}

function updateStatus(day) {
  const s  = document.getElementById('start-' + day);
  const e  = document.getElementById('end-'   + day);
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
  const fields    = document.getElementById('break-fields-' + day);
  const btn       = document.getElementById('break-btn-label-' + day);
  const icon      = document.getElementById('break-icon-' + day);
  const bstart    = document.getElementById('bstart-' + day);
  const bend      = document.getElementById('bend-'   + day);
  const show      = fields.style.display === 'none';
  fields.style.display = show ? 'flex' : 'none';
  if (btn)  btn.textContent = show ? 'Remove Break' : 'Add Break';
  if (icon) icon.className  = show ? 'fa-solid fa-minus' : 'fa-solid fa-plus';
  // Enable inputs when showing so they submit; disable + clear when hiding
  if (bstart) { bstart.disabled = !show; if (!show) bstart.value = ''; }
  if (bend)   { bend.disabled   = !show; if (!show) bend.value   = ''; }
  markUnsaved();
}

function markUnsaved() {
  const el = document.getElementById('changeIndicator');
  if (el) el.style.opacity = '1';
}

function openBlockModal() {
  document.getElementById('blockModalOverlay').classList.add('sc-block-modal-overlay--open');
  // Reset fields
  document.getElementById('blockDateFrom').value = '';
  document.getElementById('blockDateTo').value   = '';
  document.getElementById('blockReason').value   = '';
  document.getElementById('blockRangeSummary').style.display = 'none';
  document.getElementById('blockSubmitLabel').textContent = 'Block Date';
  setTimeout(() => document.getElementById('blockDateFrom').focus(), 80);
  document.body.style.overflow = 'hidden';
}

function onBlockRangeChange() {
  const fromEl = document.getElementById('blockDateFrom');
  const toEl   = document.getElementById('blockDateTo');
  const sumDiv = document.getElementById('blockRangeSummary');
  const sumTxt = document.getElementById('blockRangeSummaryText');
  const btnLbl = document.getElementById('blockSubmitLabel');

  const from = fromEl.value;
  const to   = toEl.value;

  // Auto-set To min to From value
  if (from) toEl.min = from;

  // If To is before From, reset To
  if (from && to && to < from) { toEl.value = ''; return; }

  if (!from) { sumDiv.style.display = 'none'; btnLbl.textContent = 'Block Date'; return; }

  const fromDate = new Date(from + 'T00:00:00');
  const toDate   = to ? new Date(to + 'T00:00:00') : fromDate;
  const days     = Math.round((toDate - fromDate) / 86400000) + 1;
  const fmt      = { month: 'short', day: 'numeric', year: 'numeric' };
  const fmtShort = { month: 'short', day: 'numeric' };

  const fromLabel = fromDate.toLocaleDateString('en-US', fmt);
  const toLabel   = toDate.toLocaleDateString('en-US', fmt);

  if (!to || from === to) {
    sumTxt.textContent = '1 day blocked  ·  ' + fromLabel;
  } else {
    const fromShort = fromDate.toLocaleDateString('en-US', fmtShort);
    sumTxt.textContent = days + ' days blocked  ·  ' + fromShort + ' – ' + toLabel;
  }
  sumDiv.style.display = 'flex';
  btnLbl.textContent = 'Block Date';
}

function closeBlockModal(e) {
  if (e && e.target !== document.getElementById('blockModalOverlay')) return;
  document.getElementById('blockModalOverlay').classList.remove('sc-block-modal-overlay--open');
  document.body.style.overflow = '';
}

function editBlock(btn, date) {
  const item   = btn.closest('.sc-block-item');
  const reason = item.dataset.reason || '';
  const label  = item.querySelector('.sc-block-item-date').textContent;

  document.getElementById('editBlockOriginalDate').value = date;   // keep track of old date
  document.getElementById('editBlockDate').value         = date;   // editable date field
  document.getElementById('editBlockReason').value       = reason;
  document.getElementById('editBlockModalSub').textContent = label;

  document.getElementById('editBlockModalOverlay').classList.add('sc-block-modal-overlay--open');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('editBlockDate').focus(), 80);
}

function closeEditBlockModal(e) {
  if (e && e.target !== document.getElementById('editBlockModalOverlay')) return;
  document.getElementById('editBlockModalOverlay').classList.remove('sc-block-modal-overlay--open');
  document.body.style.overflow = '';
}

function saveEditBlock() {
  const originalDate = document.getElementById('editBlockOriginalDate').value;
  const newDate      = document.getElementById('editBlockDate').value;
  const reason       = document.getElementById('editBlockReason').value.trim();

  if (!newDate) {
    document.getElementById('editBlockDate').focus();
    return;
  }

  document.getElementById('editBlockModalOverlay').classList.remove('sc-block-modal-overlay--open');
  document.body.style.overflow = '';

  const fmt   = { month: 'long', day: 'numeric', year: 'numeric' };
  const dNew  = new Date(newDate + 'T00:00:00');
  const label = dNew.toLocaleDateString('en-US', fmt);
  const dateChanged = originalDate !== newDate;

  const doUpdate = () => {
    const fd = new FormData();
    fd.append('blocked_date', newDate);
    fd.append('reason', reason);
    return fetch(BASE_URL + 'provider/schedule/block/edit', { method: 'POST', body: fd, redirect: 'follow' });
  };

  const doUnblock = () =>
    fetch(BASE_URL + 'provider/schedule/unblock/' + originalDate, { method: 'GET', redirect: 'follow' });

  const doBlock = () => {
    const fd = new FormData();
    fd.append('blocked_date', newDate);
    fd.append('reason', reason);
    return fetch(BASE_URL + 'provider/schedule/block', { method: 'POST', body: fd, redirect: 'follow' });
  };

  // If date changed: delete old date, insert new one. Otherwise just update reason.
  const serverOp = dateChanged ? doUnblock().then(doBlock) : doUpdate();

  serverOp
    .then(res => {
      if (res && !res.ok && !res.redirected) throw new Error('Server error ' + res.status);

      // Update DOM
      const oldItem = document.querySelector(`.sc-block-item[data-date="${originalDate}"]`);
      if (oldItem) {
        oldItem.dataset.date   = newDate;
        oldItem.dataset.reason = reason;
        oldItem.querySelector('.sc-block-item-date').textContent   = label;
        oldItem.querySelector('.sc-block-item-reason').textContent = reason || 'No reason given';
        // Update inline onclick attributes on the two action buttons
        oldItem.querySelectorAll('[onclick]').forEach(el => {
          el.setAttribute('onclick', el.getAttribute('onclick').replace(originalDate, newDate));
        });

        // Update calendar data
        const oldIdx = blockedDatesData.indexOf(originalDate);
        if (oldIdx > -1) blockedDatesData.splice(oldIdx, 1);
        if (!blockedDatesData.includes(newDate)) blockedDatesData.push(newDate);
        renderCalendar(calYear, calMonth);
      }

      showToast('check','Updated successfully!');
    })
    .catch(err => {
      console.error('saveEditBlock error:', err);
      showToast('check', 'Could not save changes — please try again.');
      // Re-open modal so user doesn't lose their edits
      document.getElementById('editBlockModalOverlay').classList.add('sc-block-modal-overlay--open');
      document.body.style.overflow = 'hidden';
    });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    ['blockModalOverlay','editBlockModalOverlay','slotModalOverlay','confirmDeleteOverlay'].forEach(id => {
      document.getElementById(id).classList.remove('sc-block-modal-overlay--open');
    });
    document.body.style.overflow = '';
  }
});

function addBlockDate() {
  const fromEl   = document.getElementById('blockDateFrom');
  const toEl     = document.getElementById('blockDateTo');
  const reasonEl = document.getElementById('blockReason');
  const from     = fromEl.value;
  if (!from) { fromEl.focus(); return; }
  const to     = toEl.value || from;
  const reason = reasonEl.value.trim();

  // Build list of all dates in range
  const dates = [];
  const cursor = new Date(from + 'T00:00:00');
  const end    = new Date(to   + 'T00:00:00');
  while (cursor <= end) {
    dates.push(cursor.toISOString().slice(0, 10));
    cursor.setDate(cursor.getDate() + 1);
  }

  // Close modal immediately
  document.getElementById('blockModalOverlay').classList.remove('sc-block-modal-overlay--open');
  document.body.style.overflow = '';

  const fmt = { month: 'long', day: 'numeric', year: 'numeric' };
  const list  = document.getElementById('blockList');
  const empty = document.getElementById('blockEmpty');

  // POST each date to server and add to DOM on success
  const postDate = (dateStr) => {
    const fd = new FormData();
    fd.append('blocked_date', dateStr);
    fd.append('reason', reason);
    return fetch(BASE_URL + 'provider/schedule/block', { method: 'POST', body: fd, redirect: 'follow' })
      .then(res => {
        if (res.ok || res.redirected) {
          if (blockedDatesData.includes(dateStr)) return; // already blocked
          if (empty && empty.parentNode) empty.remove();

          const d     = new Date(dateStr + 'T00:00:00');
          const label = d.toLocaleDateString('en-US', fmt);
          const item  = document.createElement('div');
          item.className   = 'sc-block-item';
          item.dataset.date = dateStr;
          item.dataset.reason = reason;
          item.innerHTML = `
            <div class="sc-block-item-left">
              <span class="sc-block-item-ico"><i class="fa-solid fa-ban"></i></span>
              <div>
                <span class="sc-block-item-date">${label}</span>
                <span class="sc-block-item-reason">${reason || 'No reason given'}</span>
              </div>
            </div>
            <div class="sc-block-actions">
              <button type="button" class="sc-block-action-btn sc-block-action-btn--edit" onclick="editBlock(this,'${dateStr}')" aria-label="Edit reason">
                <i class="fa-solid fa-pen"></i>
              </button>
              <button type="button" class="sc-block-action-btn sc-block-action-btn--remove" onclick="removeBlock(this,'${dateStr}')" aria-label="Remove block">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>`;
          list.appendChild(item);
          blockedDatesData.push(dateStr);
        }
      });
  };

  // Fire all requests, then refresh calendar + toast once
  Promise.all(dates.map(postDate))
    .then(() => {
      renderCalendar(calYear, calMonth);
      const count = dates.length;
      if (count === 1) {
        const label = new Date(dates[0] + 'T00:00:00').toLocaleDateString('en-US', fmt);
        showToast('ban','Blocked successfully!');
      } else {
        const fromLabel = new Date(dates[0]           + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        const toLabel   = new Date(dates[count - 1]   + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        showToast('ban','Blocked successfully!');
      }
    })
    .catch(() => showToast('check','Something went wrong — please try again.'));
}

// Holds pending delete state
let _pendingDelete = null;

function removeBlock(btn, date) {
  const item      = btn.closest('.sc-block-item');
  const dateLabel = item.querySelector('.sc-block-item-date').textContent;

  // Store pending state
  _pendingDelete = { btn, date, item, dateLabel, snapshot: item.outerHTML, nextSib: item.nextSibling };

  // Populate and open confirm modal
  document.getElementById('confirmDeleteMsg').textContent =
    dateLabel + ' will be unblocked and available for bookings again.';
  document.getElementById('confirmDeleteOverlay').classList.add('sc-block-modal-overlay--open');
  document.body.style.overflow = 'hidden';
}

function closeConfirmDelete(e) {
  if (e && e.target !== document.getElementById('confirmDeleteOverlay')) return;
  document.getElementById('confirmDeleteOverlay').classList.remove('sc-block-modal-overlay--open');
  document.body.style.overflow = '';
  _pendingDelete = null;
}

function confirmDelete() {
  if (!_pendingDelete) return;
  const { date, item, dateLabel, snapshot, nextSib } = _pendingDelete;
  _pendingDelete = null;

  document.getElementById('confirmDeleteOverlay').classList.remove('sc-block-modal-overlay--open');
  document.body.style.overflow = '';

  const list = document.getElementById('blockList');

  // Optimistic remove
  item.remove();
  const idx = blockedDatesData.indexOf(date);
  if (idx > -1) blockedDatesData.splice(idx, 1);
  renderCalendar(calYear, calMonth);

  if (!document.querySelector('#blockList .sc-block-item')) {
    list.innerHTML = '<div class="sc-block-empty" id="blockEmpty"><i class="fa-regular fa-calendar-check"></i><p>No blocked dates — you\'re available on all working days.</p></div>';
  }

  fetch(BASE_URL + 'provider/schedule/unblock/' + date, { method: 'GET', redirect: 'follow' })
    .then(res => {
      if (res.ok || res.redirected) {
        showToast('check', 'Removed successfully!');
      } else {
        throw new Error('Server returned ' + res.status);
      }
    })
    .catch(err => {
      console.error('confirmDelete error:', err);
      // Rollback
      const emptyEl = document.getElementById('blockEmpty');
      if (emptyEl) emptyEl.remove();
      list.insertAdjacentHTML(nextSib ? 'afterbegin' : 'beforeend', snapshot);
      if (!blockedDatesData.includes(date)) blockedDatesData.push(date);
      renderCalendar(calYear, calMonth);
      showToast('check', 'Could not remove — please try again.');
    });
}

function openSlotModal() {
  document.getElementById('slotModalOverlay').classList.add('sc-block-modal-overlay--open');
  document.body.style.overflow = 'hidden';
}

function closeSlotModal(e) {
  if (e && e.target !== document.getElementById('slotModalOverlay')) return;
  document.getElementById('slotModalOverlay').classList.remove('sc-block-modal-overlay--open');
  document.body.style.overflow = '';
}

function syncSlotDisplay() {
  const d = document.getElementById('slotDuration').value;
  const i = document.getElementById('slotInterval').value;
  const m = document.getElementById('maxBookings').value;
  if (d) document.getElementById('displayDuration').textContent   = d;
  if (i !== '') document.getElementById('displayInterval').textContent   = i;
  if (m) document.getElementById('displayMaxBookings').textContent = m;
}

function saveSlotSettings() {
  const duration    = document.getElementById('slotDuration').value;
  const interval    = document.getElementById('slotInterval').value;
  const maxBookings = document.getElementById('maxBookings').value;

  // Close modal immediately
  syncSlotDisplay();
  document.getElementById('slotModalOverlay').classList.remove('sc-block-modal-overlay--open');
  document.body.style.overflow = '';

  // POST to server
  const fd = new FormData();
  fd.append('duration_minutes',  duration);
  fd.append('interval_minutes',  interval);
  fd.append('max_daily_bookings', maxBookings);

  fetch(BASE_URL + 'provider/schedule/slots', { method: 'POST', body: fd, redirect: 'follow' })
    .then(res => {
      if (res.ok || res.redirected) {
        showToast('check','Saved successfully!');
      } else {
        showToast('check','Could not save — please try again.');
      }
    })
    .catch(() => showToast('check','Something went wrong — please try again.'));
}

function saveSlotDuration() {
  const d = document.getElementById('slotDuration').value;
  showToast('check','Saved successfully!');
}

function saveSlotInterval() {
  const i = document.getElementById('slotInterval').value;
  showToast('check','Saved successfully!');
}

function saveMaxBookings() {
  const m = document.getElementById('maxBookings').value;
  showToast('check','Saved successfully!');
}

function showToast(icon, msg) {
  // Remove any existing toast
  document.querySelectorAll('.sc-toast').forEach(el => el.remove());
  const icons = {
    check:  'fa-circle-check',
    ban:    'fa-ban',
    trash:  'fa-circle-check',
    clock:  'fa-clock',
    save:   'fa-floppy-disk'
  };
  const t = document.createElement('div');
  t.className = 'sc-toast';
  t.innerHTML = `<i class="fa-solid ${icons[icon] || 'fa-circle-check'}"></i><span>${msg}</span>`;
  document.body.appendChild(t);
  requestAnimationFrame(() => t.classList.add('sc-toast--in'));
  setTimeout(() => { t.classList.remove('sc-toast--in'); setTimeout(() => t.remove(), 350); }, 3200);
}

document.addEventListener('DOMContentLoaded', () => {
  renderCalendar(calYear, calMonth);
  document.getElementById('avForm').addEventListener('change', markUnsaved);

  ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].forEach(day => {
    const row = document.getElementById('row-' + day);
    const s   = document.getElementById('start-' + day);
    const e   = document.getElementById('end-'   + day);
    if (row && row.classList.contains('is-active') && s && e) {
      updateBadge(day, s.value, e.value);
      updateGlanceBar(day, s.value, e.value);
    }
  });

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