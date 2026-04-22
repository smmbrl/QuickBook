<?php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$providerId   = $_SESSION['user_id'] ?? 0;
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');

/* ── Provider profile ── */
$stmt = $db->prepare("SELECT * FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$providerId]);
$profile   = $stmt->fetch();
$profileId = $profile['id'] ?? 0;
$initials  = strtoupper(substr($providerName, 0, 1));

/* ── Pending bookings count (for nav badge) ── */
$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$profileId]);
$pendingBookings = (int)$stmt->fetchColumn();

/* ── Flash ── */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ── Days config ── */
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

/* ── Fetch existing availability ── */
$stmt = $db->prepare("SELECT * FROM tbl_provider_availability WHERE provider_id = ?");
$stmt->execute([$profileId]);
$rows = $stmt->fetchAll();

$availability = [];
foreach ($rows as $row) {
    $availability[$row['day_of_week']] = $row;
}

/* ── Count active days ── */
$activeDays = count(array_filter($availability, fn($r) => $r['is_available'] ?? 0));
$totalSlots = 0;
foreach ($availability as $r) {
    if (($r['is_available'] ?? 0) && !empty($r['start_time']) && !empty($r['end_time'])) {
        $start = strtotime($r['start_time']);
        $end   = strtotime($r['end_time']);
        if ($end > $start) {
            $totalSlots += ceil(($end - $start) / 3600);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Availability</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_availability.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAV
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>provider/dashboard" class="pv-logo">
      Quick<em>Book</em><span class="pv-logo-badge">Provider</span>
    </a>
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/bookings"     class="pv-nav-link">
        Bookings
        <?php if ($pendingBookings): ?><sup class="pv-sup"><?= $pendingBookings ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"     class="pv-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/availability" class="pv-nav-link is-active">Availability</a>
      <a href="<?= BASE_URL ?>provider/profile"      class="pv-nav-link">Profile</a>
    </div>
    <div class="pv-nav-end">
      <div class="pv-nav-user">
        <div class="pv-nav-av" aria-hidden="true"><?= $initials ?></div>
        <span><?= $providerName ?></span>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>
  </div>
</nav>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-bg" aria-hidden="true">
    <div class="pv-hero-bg-img pv-hero-bg-img--1"></div>
    <div class="pv-hero-bg-img pv-hero-bg-img--2"></div>
    <div class="pv-hero-bg-img pv-hero-bg-img--3"></div>
  </div>
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Schedule Management
      </p>
      <h1 class="pv-hero-title">Your <em>Availability</em></h1>
      <p class="pv-hero-sub">Set the days and hours you're open for bookings. Customers can only book within your available windows.</p>
    </div>
  </div>
  <div class="pv-hero-stats">
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-gold"><?= $activeDays ?></span>
      <span class="pv-hs-label">Active Days</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val"><?= 7 - $activeDays ?></span>
      <span class="pv-hs-label">Days Off</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-green"><?= $totalSlots ?>h</span>
      <span class="pv-hs-label">Weekly Hours</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-blue"><?= $activeDays > 0 ? round($totalSlots / $activeDays, 1) : 0 ?>h</span>
      <span class="pv-hs-label">Avg / Day</span>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     PAGE
══════════════════════════════════════ -->
<main class="av-page" role="main">

  <?php if ($flash): ?>
    <div class="pv-flash pv-flash--<?= $flash['type'] ?>" role="alert">
      <?= $flash['type'] === 'success' ? '✓' : '✕' ?>
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <div class="av-layout">

    <!-- ── LEFT: Weekly Schedule ── -->
    <section class="av-card av-schedule" aria-label="Weekly schedule">
      <div class="av-card-head">
        <div>
          <h2 class="av-card-title">Weekly Schedule</h2>
          <p class="av-card-sub">Toggle each day and set your working hours</p>
        </div>
        <div class="av-bulk-btns">
          <button type="button" class="av-ghost-btn" onclick="setAll(true)">Enable All</button>
          <button type="button" class="av-ghost-btn av-ghost-btn--red" onclick="setAll(false)">Clear All</button>
        </div>
      </div>

     <form method="POST" action="<?= BASE_URL ?>provider/availability/store" id="avForm">

        <div class="av-days-list" role="list">
          <?php foreach ($days as $i => $day):
            $row       = $availability[$day] ?? null;
            $isActive  = (bool)($row['is_available'] ?? false);
            $startTime = $row['start_time'] ?? '08:00';
            $endTime   = $row['end_time']   ?? '17:00';
            $startTime = substr($startTime, 0, 5);
            $endTime   = substr($endTime, 0, 5);
            $isWeekend = in_array($day, ['Saturday','Sunday']);
            $dayAbbr   = substr($day, 0, 3);
          ?>
          <div class="av-day-row <?= $isActive ? 'is-active' : '' ?> <?= $isWeekend ? 'is-weekend' : '' ?>"
               id="row-<?= $day ?>" role="listitem" data-day="<?= $day ?>">

            <div class="av-day-left">
              <div class="av-day-abbr" aria-hidden="true"><?= $dayAbbr ?></div>
              <div class="av-day-info">
                <span class="av-day-name"><?= $day ?></span>
                <span class="av-day-status" id="status-<?= $day ?>">
                  <?= $isActive ? ($startTime . ' – ' . $endTime) : 'Unavailable' ?>
                </span>
              </div>
            </div>

            <div class="av-day-center" id="times-<?= $day ?>" aria-hidden="<?= $isActive ? 'false' : 'true' ?>">
              <div class="av-time-group">
                <label class="av-time-label" for="start-<?= $day ?>">Opens</label>
                <input
                  type="time" id="start-<?= $day ?>"
                  name="days[<?= $day ?>][start_time]"
                  value="<?= $startTime ?>"
                  class="av-time-input"
                  <?= $isActive ? '' : 'disabled' ?>
                  onchange="updateStatus('<?= $day ?>')"
                >
              </div>
              <div class="av-time-sep" aria-hidden="true">→</div>
              <div class="av-time-group">
                <label class="av-time-label" for="end-<?= $day ?>">Closes</label>
                <input
                  type="time" id="end-<?= $day ?>"
                  name="days[<?= $day ?>][end_time]"
                  value="<?= $endTime ?>"
                  class="av-time-input"
                  <?= $isActive ? '' : 'disabled' ?>
                  onchange="updateStatus('<?= $day ?>')"
                >
              </div>

              <div class="av-hours-badge" id="badge-<?= $day ?>"></div>
            </div>

            <div class="av-day-right">
              <label class="av-toggle" aria-label="Toggle <?= $day ?> availability">
                <input
                  type="checkbox"
                  name="days[<?= $day ?>][is_available]"
                  value="1"
                  <?= $isActive ? 'checked' : '' ?>
                  onchange="toggleDay('<?= $day ?>', this.checked)"
                  class="av-toggle-input"
                >
                <span class="av-toggle-track" aria-hidden="true">
                  <span class="av-toggle-thumb"></span>
                </span>
              </label>
            </div>

          </div>
          <?php endforeach; ?>
        </div>

        <!-- Break time notice -->
        <div class="av-notice">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Hours shown are your general availability window. Break times and buffer between bookings can be configured per service.
        </div>

        <div class="av-form-footer">
          <div class="av-footer-left" id="changeIndicator" style="opacity:0">
            <span class="av-unsaved-dot" aria-hidden="true"></span>
            Unsaved changes
          </div>
          <button type="submit" class="av-save-btn" id="saveBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg>
            Save Schedule
          </button>
        </div>

      </form>
    </section>

    <!-- ── RIGHT: Visual Overview ── -->
    <aside class="av-sidebar" aria-label="Availability overview">

      <!-- Weekly heatmap -->
      <div class="av-card av-heatmap-card">
        <h3 class="av-card-title">Week at a Glance</h3>
        <p class="av-card-sub">Your availability visualised</p>
        <div class="av-heatmap" id="heatmap" role="img" aria-label="Weekly availability heatmap">
          <?php foreach ($days as $day):
            $row      = $availability[$day] ?? null;
            $isActive = (bool)($row['is_available'] ?? false);
            $start    = $isActive ? strtotime($row['start_time'] ?? '08:00') : 0;
            $end      = $isActive ? strtotime($row['end_time']   ?? '17:00') : 0;
            $hours    = ($isActive && $end > $start) ? round(($end - $start) / 3600, 1) : 0;
            $pct      = $isActive ? min(100, round($hours / 12 * 100)) : 0;
            $isWeekend = in_array($day, ['Saturday','Sunday']);
          ?>
          <div class="av-hm-row" id="hm-<?= $day ?>">
            <span class="av-hm-label"><?= substr($day, 0, 3) ?></span>
            <div class="av-hm-bar-wrap">
              <div class="av-hm-bar <?= $isWeekend ? 'av-hm-bar--weekend' : '' ?> <?= !$isActive ? 'av-hm-bar--off' : '' ?>"
                   style="width: <?= $pct ?>%"
                   id="hm-bar-<?= $day ?>">
              </div>
            </div>
            <span class="av-hm-hours" id="hm-hours-<?= $day ?>">
              <?= $isActive ? $hours . 'h' : '—' ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Quick tips -->
      <div class="av-card av-tips-card">
        <h3 class="av-card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26 12,2"/></svg>
          Tips
        </h3>
        <ul class="av-tips-list">
          <li class="av-tip">
            <span class="av-tip-icon av-tip-icon--gold">🕐</span>
            <p>Set realistic hours — your schedule directly affects how customers see and book you.</p>
          </li>
          <li class="av-tip">
            <span class="av-tip-icon av-tip-icon--blue">📅</span>
            <p>Weekends often drive higher booking volume. Consider extending your weekend hours.</p>
          </li>
          <li class="av-tip">
            <span class="av-tip-icon av-tip-icon--green">✓</span>
            <p>Consistent schedules build trust. Try to keep your availability stable week to week.</p>
          </li>
        </ul>
      </div>

      <!-- Summary totals -->
      <div class="av-card av-summary-card">
        <h3 class="av-card-title">Summary</h3>
        <div class="av-summary-grid" id="summaryGrid">
          <div class="av-sum-item">
            <div class="av-sum-val" id="sum-days"><?= $activeDays ?></div>
            <div class="av-sum-label">Working Days</div>
          </div>
          <div class="av-sum-item">
            <div class="av-sum-val av-sum-val--gold" id="sum-hours"><?= $totalSlots ?>h</div>
            <div class="av-sum-label">Total Hours</div>
          </div>
          <div class="av-sum-item av-sum-full">
            <div class="av-sum-weekdays" id="sum-active-days">
              <?php foreach ($days as $d):
                $a = (bool)(($availability[$d]['is_available'] ?? false));
              ?>
              <span class="av-sum-day-dot <?= $a ? 'is-on' : '' ?>" id="dot-<?= $d ?>" title="<?= $d ?>">
                <?= substr($d,0,1) ?>
              </span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

    </aside>
  </div>
</main>

<script>
  // ── Track unsaved state ──
  const form = document.getElementById('avForm');
  const changeIndicator = document.getElementById('changeIndicator');

  form.addEventListener('change', () => {
    changeIndicator.style.opacity = '1';
  });

  // ── Toggle a day on/off ──
  function toggleDay(day, active) {
    const row    = document.getElementById('row-' + day);
    const times  = document.getElementById('times-' + day);
    const status = document.getElementById('status-' + day);
    const inputs = times.querySelectorAll('input[type="time"]');

    row.classList.toggle('is-active', active);
    times.setAttribute('aria-hidden', !active);

    inputs.forEach(inp => {
      inp.disabled = !active;
    });

    if (!active) {
      status.textContent = 'Unavailable';
    } else {
      updateStatus(day);
    }

    updateHeatmap(day, active);
    updateSummaryDot(day, active);
    updateSummaryTotals();
    changeIndicator.style.opacity = '1';
  }

  // ── Update the status text beneath the day name ──
  function updateStatus(day) {
    const startEl = document.getElementById('start-' + day);
    const endEl   = document.getElementById('end-' + day);
    const status  = document.getElementById('status-' + day);
    if (!startEl || !endEl) return;
    const start = startEl.value;
    const end   = endEl.value;
    if (start && end) {
      status.textContent = formatTime(start) + ' – ' + formatTime(end);
      updateHoursbadge(day, start, end);
      updateHeatmapBar(day, start, end);
      updateSummaryTotals();
    }
    changeIndicator.style.opacity = '1';
  }

  function formatTime(t) {
    const [h, m] = t.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hh   = h % 12 || 12;
    return hh + (m ? ':' + String(m).padStart(2,'0') : '') + ' ' + ampm;
  }

  function hoursFromTimes(start, end) {
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const diff = (eh * 60 + em) - (sh * 60 + sm);
    return diff > 0 ? diff / 60 : 0;
  }

  function updateHoursbadge(day, start, end) {
    const badge = document.getElementById('badge-' + day);
    if (!badge) return;
    const h = hoursFromTimes(start, end);
    badge.textContent = h > 0 ? h.toFixed(h % 1 ? 1 : 0) + 'h' : '';
    badge.style.opacity = h > 0 ? '1' : '0';
  }

  function updateHeatmap(day, active) {
    const bar       = document.getElementById('hm-bar-' + day);
    const hoursSpan = document.getElementById('hm-hours-' + day);
    if (!bar) return;
    if (!active) {
      bar.style.width = '0%';
      bar.classList.add('av-hm-bar--off');
      hoursSpan.textContent = '—';
    } else {
      bar.classList.remove('av-hm-bar--off');
      const startEl = document.getElementById('start-' + day);
      const endEl   = document.getElementById('end-' + day);
      updateHeatmapBar(day, startEl?.value, endEl?.value);
    }
  }

  function updateHeatmapBar(day, start, end) {
    const bar       = document.getElementById('hm-bar-' + day);
    const hoursSpan = document.getElementById('hm-hours-' + day);
    if (!bar || !start || !end) return;
    const h   = hoursFromTimes(start, end);
    const pct = Math.min(100, Math.round(h / 12 * 100));
    bar.style.width = pct + '%';
    hoursSpan.textContent = h > 0 ? h.toFixed(h % 1 ? 1 : 0) + 'h' : '—';
  }

  function updateSummaryDot(day, active) {
    const dot = document.getElementById('dot-' + day);
    if (dot) dot.classList.toggle('is-on', active);
  }

  function updateSummaryTotals() {
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    let activeDays = 0, totalH = 0;
    days.forEach(day => {
      const row = document.getElementById('row-' + day);
      if (!row) return;
      if (row.classList.contains('is-active')) {
        activeDays++;
        const startEl = document.getElementById('start-' + day);
        const endEl   = document.getElementById('end-' + day);
        if (startEl && endEl) {
          totalH += hoursFromTimes(startEl.value, endEl.value);
        }
      }
    });
    const daysEl  = document.getElementById('sum-days');
    const hoursEl = document.getElementById('sum-hours');
    if (daysEl)  daysEl.textContent  = activeDays;
    if (hoursEl) hoursEl.textContent = totalH.toFixed(totalH % 1 ? 1 : 0) + 'h';
  }

  function setAll(active) {
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    days.forEach(day => {
      const checkbox = document.querySelector(`input[name="days[${day}][is_available]"]`);
      if (checkbox) {
        checkbox.checked = active;
        toggleDay(day, active);
      }
    });
  }

  // ── Init badges on load ──
  document.addEventListener('DOMContentLoaded', () => {
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    days.forEach(day => {
      const row     = document.getElementById('row-' + day);
      const startEl = document.getElementById('start-' + day);
      const endEl   = document.getElementById('end-' + day);
      if (row && row.classList.contains('is-active') && startEl && endEl) {
        updateHoursbadge(day, startEl.value, endEl.value);
        updateHeatmapBar(day, startEl.value, endEl.value);
      }
    });

    // Animate rows in
    document.querySelectorAll('.av-day-row').forEach((el, i) => {
      el.style.animationDelay = (i * 0.06) + 's';
    });
  });
</script>

</body>
</html>