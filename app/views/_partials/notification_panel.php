<?php
/**
 * app/views/_partials/notification_panel.php
 *
 * Reusable notification bell + dropdown panel.
 * Expects: $db (PDO), $notifUserId (int)
 * Optional: $notifLimit (int, default 15)
 */

$notifLimit = (int)($notifLimit ?? 15);

// Safely fetch — link_url column may not exist on older DBs yet
try {
    $stNotif = $db->prepare("
        SELECT id, type, title, message, body,
               COALESCE(link_url, '') AS link_url,
               is_read, created_at
        FROM   tbl_notifications
        WHERE  user_id = ?
        ORDER  BY created_at DESC
        LIMIT  $notifLimit
    ");
    $stNotif->execute([$notifUserId]);
    $notifications = $stNotif->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback query without link_url if column doesn't exist yet
    $stNotif = $db->prepare("
        SELECT id, type, title, message, body, '' AS link_url,
               is_read, created_at
        FROM   tbl_notifications
        WHERE  user_id = ?
        ORDER  BY created_at DESC
        LIMIT  $notifLimit
    ");
    $stNotif->execute([$notifUserId]);
    $notifications = $stNotif->fetchAll(PDO::FETCH_ASSOC);
}

$unreadCount = count(array_filter($notifications, fn($n) => !(int)$n['is_read']));
$hasUnread   = $unreadCount > 0;

// Detect current role from session
$sessionRole = $_SESSION['role'] ?? 'customer';

/**
 * Build a smart fallback URL for old notifications that have no link_url stored.
 * Uses the notification type + session role to route correctly.
 */
function qb_notif_url(array $n, string $role, string $baseUrl): string
{
    // If a link was already stored, use it directly
    if (!empty($n['link_url'])) {
        return $n['link_url'];
    }

    $type = $n['type'];

    // Try to extract a booking ID from the title or message (e.g. "Booking #42")
    $bookingId = null;
    if (preg_match('/#(\d+)/i', $n['title'] . ' ' . $n['message'], $m)) {
        $bookingId = (int)$m[1];
    }

    switch ($type) {
        case 'booking':
        case 'reschedule':
        case 'reschedule_accepted':
            if ($role === 'admin') {
                return $baseUrl . 'admin/bookings';
            } elseif ($role === 'provider') {
                return $bookingId
                    ? $baseUrl . 'provider/bookings/' . $bookingId
                    : $baseUrl . 'provider/bookings';
            } else {
                return $bookingId
                    ? $baseUrl . 'bookings/' . $bookingId
                    : $baseUrl . 'bookings';
            }

        case 'booking_cancelled':
            if ($role === 'admin') {
                return $baseUrl . 'admin/bookings';
            } elseif ($role === 'provider') {
                return $bookingId
                    ? $baseUrl . 'provider/bookings/' . $bookingId
                    : $baseUrl . 'provider/bookings';
            } else {
                return $bookingId
                    ? $baseUrl . 'bookings/' . $bookingId
                    : $baseUrl . 'bookings';
            }

        case 'review':
            if ($role === 'admin') {
                return $baseUrl . 'admin/bookings';
            } elseif ($role === 'provider') {
                return $bookingId
                    ? $baseUrl . 'provider/bookings/' . $bookingId
                    : $baseUrl . 'provider/bookings';
            } else {
                return $bookingId
                    ? $baseUrl . 'bookings/' . $bookingId
                    : $baseUrl . 'bookings';
            }

        case 'payment':
            if ($role === 'admin') return $baseUrl . 'admin/bookings';
            if ($role === 'provider') return $baseUrl . 'provider/bookings';
            return $baseUrl . 'bookings';

        case 'system':
            if ($role === 'admin') return $baseUrl . 'admin/dashboard';
            if ($role === 'provider') return $baseUrl . 'provider/dashboard';
            return $baseUrl . 'dashboard';

        default:
            if ($role === 'admin') return $baseUrl . 'admin/dashboard';
            if ($role === 'provider') return $baseUrl . 'provider/dashboard';
            return $baseUrl . 'dashboard';
    }
}


$typeColor = [
    'review'              => '#c9a84c',
    'booking'             => '#34d399',
    'booking_cancelled'   => '#fb7185',
    'reschedule'          => '#60a5fa',
    'reschedule_accepted' => '#34d399',
    'payment'             => '#a78bfa',
    'msg'                 => '#f472b6',
    'system'              => '#94a3b8',
];
?>

<!-- ══ NOTIFICATION BELL ══ -->
<div class="qb-notif-wrapper" id="qbNotifWrapper">

  <button class="pv-notif-btn qb-notif-trigger<?= $hasUnread ? ' has-unread' : '' ?>"
          id="qbNotifBtn"
          aria-label="Notifications<?= $hasUnread ? ' (' . $unreadCount . ' unread)' : '' ?>"
          aria-expanded="false"
          aria-controls="qbNotifPanel"
          type="button">
    <i class="fa-solid fa-bell"></i>
    <?php if ($hasUnread): ?>
      <span class="pv-notif-dot" aria-hidden="true"></span>
    <?php endif; ?>
  </button>

  <!-- ── Dropdown panel ── -->
  <div class="qb-notif-panel" id="qbNotifPanel" role="dialog"
       aria-label="Notifications" aria-modal="false" hidden>

    <div class="qb-np-header">
      <div class="qb-np-title">
        <i class="fa-solid fa-bell"></i>
        Notifications
        <?php if ($hasUnread): ?>
          <span class="qb-np-badge"><?= $unreadCount ?> new</span>
        <?php endif; ?>
      </div>
      <?php if ($hasUnread): ?>
        <button class="qb-np-mark-all" id="qbMarkAllRead" type="button">
          Mark all read
        </button>
      <?php endif; ?>
    </div>

    <div class="qb-np-body" id="qbNpBody">
      <?php if (empty($notifications)): ?>
        <div class="qb-np-empty">
          <i class="fa-regular fa-bell-slash"></i>
          <p>No notifications yet</p>
        </div>
      <?php else: ?>
        <?php foreach ($notifications as $n):
          $icon    = 'fa-solid ' . ($typeIcon[$n['type']] ?? 'fa-circle-info');
          $color   = $typeColor[$n['type']] ?? '#c9a84c';
          $isNew   = !(int)$n['is_read'];
          $ts      = strtotime($n['created_at']);
          $diff    = time() - $ts;

          // Always resolve a URL — uses stored link_url or smart fallback
          $resolvedUrl = qb_notif_url($n, $sessionRole, BASE_URL);

          if      ($diff < 60)     $ago = 'Just now';
          elseif  ($diff < 3600)   $ago = floor($diff / 60)   . 'm ago';
          elseif  ($diff < 86400)  $ago = floor($diff / 3600)  . 'h ago';
          elseif  ($diff < 604800) $ago = floor($diff / 86400) . 'd ago';
          else                     $ago = date('M j', $ts);
        ?>
        <div class="qb-np-item qb-np-item--clickable<?= $isNew ? ' qb-np-item--unread' : '' ?>"
             data-id="<?= (int)$n['id'] ?>"
             data-read="<?= $isNew ? '0' : '1' ?>"
             data-url="<?= htmlspecialchars($resolvedUrl) ?>"
             role="button"
             tabindex="0"
             aria-label="<?= htmlspecialchars($n['title']) ?>">
          <div class="qb-np-icon" style="--ic:<?= $color ?>">
            <i class="<?= $icon ?>"></i>
          </div>
          <div class="qb-np-content">
            <div class="qb-np-item-title">
              <?= htmlspecialchars($n['title']) ?>
              <span class="qb-np-item-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
            </div>
            <?php if (!empty($n['message'])): ?>
              <div class="qb-np-item-msg"><?= htmlspecialchars($n['message']) ?></div>
            <?php endif; ?>
            <?php if (!empty($n['body'])): ?>
              <div class="qb-np-item-body"><?= htmlspecialchars($n['body']) ?></div>
            <?php endif; ?>
            <div class="qb-np-item-time"><?= $ago ?></div>
          </div>
          <?php if ($isNew): ?>
            <div class="qb-np-unread-dot" aria-hidden="true"></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (count($notifications) >= $notifLimit): ?>
      <div class="qb-np-footer">Showing latest <?= $notifLimit ?> notifications</div>
    <?php endif; ?>

  </div>
</div><!-- /qb-notif-wrapper -->

<style>
/* ══ Bell wrapper ══════════════════════════════════════════════ */
.qb-notif-wrapper { position: relative; display: inline-flex; }

/* ══ The bell button ═══════════════════════════════════════════ */
.pv-notif-btn {
  position: relative;
  width: 36px; height: 36px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,.06);
  background: rgba(255,255,255,.03);
  color: rgba(201,168,76,.5);
  display: flex; align-items: center; justify-content: center;
  font-size: .92rem;
  cursor: pointer;
  transition: width .2s ease, height .2s ease,
              color .22s ease, background .22s ease,
              border-color .22s ease, box-shadow .22s ease,
              font-size .2s ease;
  flex-shrink: 0;
  outline: none;
}
.pv-notif-btn:hover {
  width: 40px; height: 40px;
  color: #C9A84C;
  border-color: rgba(201,168,76,.35);
  background: rgba(201,168,76,.10);
  box-shadow: 0 0 0 3px rgba(201,168,76,.12), 0 0 16px rgba(201,168,76,.18);
  font-size: 1rem;
}
.pv-notif-btn.has-unread {
  color: rgba(201,168,76,.65);
  border-color: rgba(201,168,76,.18);
  background: rgba(201,168,76,.05);
}
.pv-notif-btn.has-unread:hover {
  width: 40px; height: 40px;
  color: #E8C96A;
  border-color: rgba(201,168,76,.5);
  background: rgba(201,168,76,.13);
  box-shadow: 0 0 0 3px rgba(201,168,76,.14), 0 0 20px rgba(201,168,76,.22);
}
.pv-notif-btn[aria-expanded="true"] {
  width: 40px; height: 40px;
  color: #C9A84C;
  border-color: rgba(201,168,76,.4);
  background: rgba(201,168,76,.10);
  box-shadow: 0 0 0 3px rgba(201,168,76,.12);
}
.pv-notif-dot {
  position: absolute; top: 7px; right: 7px;
  width: 7px; height: 7px; border-radius: 99px;
  background: rgba(201,168,76,.7);
  border: 1.5px solid #0A0B0D;
  transition: background .22s, transform .22s, box-shadow .22s;
  pointer-events: none;
}
.pv-notif-btn:hover .pv-notif-dot,
.pv-notif-btn[aria-expanded="true"] .pv-notif-dot {
  background: #C9A84C;
  box-shadow: 0 0 6px rgba(201,168,76,.5);
  transform: scale(1.15);
  animation: none;
}
.pv-notif-btn:not(:hover):not([aria-expanded="true"]) .pv-notif-dot {
  animation: qbPulseDim 2.5s ease infinite;
}
@keyframes qbPulseDim {
  0%, 100% { opacity: .7;  transform: scale(1); }
  50%       { opacity: .35; transform: scale(.7); }
}
@keyframes qbPulse {
  0%, 100% { opacity: 1;  transform: scale(1); }
  50%       { opacity: .5; transform: scale(.75); }
}

/* ══ Dropdown panel ════════════════════════════════════════════ */
.qb-notif-panel {
  position: absolute; top: calc(100% + 10px); right: 0;
  width: 360px; max-width: calc(100vw - 20px);
  background: #12161c;
  border: 1px solid rgba(201,168,76,.16);
  border-radius: 14px;
  box-shadow: 0 24px 64px rgba(0,0,0,.65), 0 0 0 1px rgba(201,168,76,.06);
  z-index: 9999; overflow: hidden;
  animation: qbPanelIn .18s cubic-bezier(.22,1,.36,1) both;
}
.qb-notif-panel[hidden] { display: none; }
@keyframes qbPanelIn {
  from { opacity: 0; transform: translateY(-8px) scale(.97); }
  to   { opacity: 1; transform: translateY(0)    scale(1);   }
}

.qb-np-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px 12px;
  border-bottom: 1px solid rgba(255,255,255,.055);
}
.qb-np-title {
  display: flex; align-items: center; gap: 8px;
  font-family: 'Syne', sans-serif; font-weight: 700; font-size: .87rem;
  color: #e2d5a0;
}
.qb-np-title .fa-bell { color: #C9A84C; font-size: .78rem; }
.qb-np-badge {
  background: rgba(201,168,76,.14); color: #C9A84C;
  border: 1px solid rgba(201,168,76,.25);
  font-size: .62rem; font-weight: 700; padding: 2px 7px; border-radius: 99px;
  font-family: 'Syne', sans-serif;
}
.qb-np-mark-all {
  font-size: .7rem; color: rgba(201,168,76,.65);
  background: none; border: none; cursor: pointer;
  padding: 4px 8px; border-radius: 6px;
  transition: color .2s, background .2s;
}
.qb-np-mark-all:hover { color: #C9A84C; background: rgba(201,168,76,.09); }

.qb-np-body { max-height: 380px; overflow-y: auto; }
.qb-np-body::-webkit-scrollbar { width: 4px; }
.qb-np-body::-webkit-scrollbar-track { background: transparent; }
.qb-np-body::-webkit-scrollbar-thumb { background: rgba(201,168,76,.18); border-radius: 99px; }

.qb-np-empty {
  display: flex; flex-direction: column; align-items: center; gap: 10px;
  padding: 44px 16px; color: rgba(255,255,255,.22); font-size: .82rem;
}
.qb-np-empty i { font-size: 2rem; }

/* ── Every item is now always clickable ─── */
.qb-np-item {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255,255,255,.04);
  transition: background .18s, box-shadow .18s;
  position: relative;
  cursor: pointer;
  user-select: none;
  -webkit-user-select: none;
}
.qb-np-item:last-child { border-bottom: none; }

.qb-np-item:hover,
.qb-np-item:focus {
  background: rgba(201,168,76,.07);
  outline: none;
}
.qb-np-item:focus {
  box-shadow: inset 0 0 0 1.5px rgba(201,168,76,.35);
}
.qb-np-item:active {
  background: rgba(201,168,76,.12);
}
.qb-np-item--unread { background: rgba(201,168,76,.04); }
.qb-np-item--unread:hover { background: rgba(201,168,76,.09); }

/* Arrow animates in on hover */
.qb-np-item-arrow {
  display: inline-flex; align-items: center;
  margin-left: 5px;
  font-size: .58rem;
  color: rgba(201,168,76,.4);
  opacity: 0;
  transform: translateX(-5px);
  transition: opacity .15s ease, transform .15s ease, color .15s ease;
  vertical-align: middle;
}
.qb-np-item:hover .qb-np-item-arrow,
.qb-np-item:focus .qb-np-item-arrow {
  opacity: 1;
  transform: translateX(0);
  color: #C9A84C;
}

.qb-np-icon {
  flex-shrink: 0; width: 34px; height: 34px; border-radius: 9px;
  background: rgba(from var(--ic) r g b / .14);
  border: 1px solid rgba(201,168,76,.2);
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; color: var(--ic);
  transition: transform .15s ease;
}
.qb-np-item:hover .qb-np-icon { transform: scale(1.08); }

.qb-np-content { flex: 1; min-width: 0; }
.qb-np-item-title {
  font-size: .8rem; font-weight: 600; color: #e2d5a0; margin-bottom: 2px;
  display: flex; align-items: center;
  transition: color .15s;
}
.qb-np-item:hover .qb-np-item-title { color: #C9A84C; }
.qb-np-item-msg  { font-size: .74rem; color: rgba(255,255,255,.54); line-height: 1.42; }
.qb-np-item-body {
  font-size: .7rem; color: rgba(255,255,255,.32); line-height: 1.4; margin-top: 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.qb-np-item-time { font-size: .65rem; color: rgba(255,255,255,.28); margin-top: 4px; }

.qb-np-unread-dot {
  flex-shrink: 0; width: 7px; height: 7px; border-radius: 99px;
  background: #C9A84C; margin-top: 6px;
  animation: qbPulse 2s ease infinite;
}
.qb-np-footer {
  padding: 8px 16px; text-align: center;
  font-size: .67rem; color: rgba(255,255,255,.18);
  border-top: 1px solid rgba(255,255,255,.05);
}
</style>

<script>
(function () {
  'use strict';

  var btn     = document.getElementById('qbNotifBtn');
  var panel   = document.getElementById('qbNotifPanel');
  var wrapper = document.getElementById('qbNotifWrapper');
  var markAll = document.getElementById('qbMarkAllRead');

  if (!btn || !panel) return;

  /* ── Open / close ─────────────────────────────────────────── */
  function openPanel()  { panel.hidden = false; btn.setAttribute('aria-expanded', 'true');  }
  function closePanel() { panel.hidden = true;  btn.setAttribute('aria-expanded', 'false'); }

  btn.addEventListener('click', function (e) { e.stopPropagation(); panel.hidden ? openPanel() : closePanel(); });
  document.addEventListener('click', function (e) { if (!wrapper.contains(e.target)) closePanel(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePanel(); });

  /* ── Build mark-read URL from PHP BASE_URL ────────────────── */
  // BASE_URL is echoed directly from PHP so it is always correct
  var markReadUrl  = '<?= rtrim(BASE_URL, '/') ?>/notifications/mark-read';
  var markAllUrl   = '<?= rtrim(BASE_URL, '/') ?>/notifications/mark-all-read';

  function postJSON(url, data) {
    return fetch(url, {
      method : 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body   : new URLSearchParams(data)
    }).then(function (r) { return r.json(); }).catch(function () { return {}; });
  }

  /* ── Refresh badge / bell dot ─────────────────────────────── */
  function refreshBadge() {
    var unread = panel.querySelectorAll('.qb-np-item[data-read="0"]').length;
    var bellDot = btn.querySelector('.pv-notif-dot');

    if (unread > 0) {
      if (!bellDot) {
        bellDot = document.createElement('span');
        bellDot.className = 'pv-notif-dot';
        bellDot.setAttribute('aria-hidden', 'true');
        btn.appendChild(bellDot);
      }
      btn.classList.add('has-unread');
      btn.setAttribute('aria-label', 'Notifications (' + unread + ' unread)');
    } else {
      if (bellDot) bellDot.remove();
      btn.classList.remove('has-unread');
      btn.setAttribute('aria-label', 'Notifications');
    }

    var badge = panel.querySelector('.qb-np-badge');
    if (badge) {
      badge.textContent = unread + ' new';
      badge.style.display = unread > 0 ? '' : 'none';
    }
    if (markAll) markAll.style.display = unread > 0 ? '' : 'none';
  }

  /* ── Mark item read, then run callback ────────────────────── */
  function markItemRead(item, cb) {
    if (item.dataset.read === '1') { if (cb) cb(); return; }

    // Optimistically update UI immediately
    item.dataset.read = '1';
    item.classList.remove('qb-np-item--unread');
    var dot = item.querySelector('.qb-np-unread-dot');
    if (dot) dot.remove();
    refreshBadge();

    // Fire-and-forget to server, then run callback regardless
    postJSON(markReadUrl, { id: item.dataset.id }).finally(function () {
      if (cb) cb();
    });

    // If fetch doesn't support .finally (old browsers), run cb after short delay too
    if (cb) setTimeout(cb, 150);
  }

  /* ── Click ANY item → mark read + navigate ────────────────── */
  var npBody = document.getElementById('qbNpBody');
  if (npBody) {
    npBody.addEventListener('click', function (e) {
      // Don't trigger if user clicked the scrollbar area
      var item = e.target.closest('.qb-np-item');
      if (!item) return;

      var url = item.getAttribute('data-url');

      // Mark read optimistically, navigate immediately
      item.dataset.read = '1';
      item.classList.remove('qb-np-item--unread');
      var dot = item.querySelector('.qb-np-unread-dot');
      if (dot) dot.remove();
      refreshBadge();

      // Fire mark-read to server (don't await)
      postJSON(markReadUrl, { id: item.dataset.id });

      // Navigate
      if (url && url.length > 1) {
        window.location.href = url;
      }
    });

    /* ── Keyboard support ─────────────────────────────────────── */
    npBody.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      var item = e.target.closest('.qb-np-item');
      if (!item) return;
      e.preventDefault();
      item.click();
    });
  }

  /* ── Mark all read ────────────────────────────────────────── */
  if (markAll) {
    markAll.addEventListener('click', function (e) {
      e.stopPropagation();
      panel.querySelectorAll('.qb-np-item').forEach(function (item) {
        item.dataset.read = '1';
        item.classList.remove('qb-np-item--unread');
        var dot = item.querySelector('.qb-np-unread-dot');
        if (dot) dot.remove();
      });
      refreshBadge();
      postJSON(markAllUrl, {});
    });
  }

})();
</script>