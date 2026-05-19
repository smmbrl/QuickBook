<?php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$providerId   = $_SESSION['user_id'] ?? 0;
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');

$stmt = $db->prepare("SELECT * FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$providerId]);
$profile   = $stmt->fetch();
$profileId = $profile['id'] ?? 0;

$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 12;

$validStatuses = ['all','pending','confirmed','in_progress','completed','cancelled'];
if (!in_array($statusFilter, $validStatuses)) $statusFilter = 'all';

/* ── Counts per status ── */
$statuses = ['pending','confirmed','in_progress','completed','cancelled'];
$counts   = [];
foreach ($statuses as $s) {
    $st = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = ?");
    $st->execute([$profileId, $s]);
    $counts[$s] = (int)$st->fetchColumn();
}
$stTotal = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ?");
$stTotal->execute([$profileId]);
$counts['all'] = (int)$stTotal->fetchColumn();

/* ── Revenue ── */
$stRev = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings WHERE provider_id = ? AND status = 'completed'");
$stRev->execute([$profileId]);
$totalRevenue = (float)$stRev->fetchColumn();

/* ── Filtered query ── */
$where  = "b.provider_id = :pid";
$params = [':pid' => $profileId];
if ($statusFilter !== 'all') {
    $where .= " AND b.status = :status";
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND (u.first_name LIKE :q OR u.last_name LIKE :q OR s.name LIKE :q OR b.id LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

$stCount = $db->prepare("SELECT COUNT(*) FROM tbl_bookings b
    JOIN tbl_users u    ON u.id = b.customer_id
    JOIN tbl_services s ON s.id = b.service_id
    WHERE $where");
$stCount->execute($params);
$totalFiltered = (int)$stCount->fetchColumn();
$totalPages    = max(1, (int)ceil($totalFiltered / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

$sql = "SELECT b.id, b.booking_date, b.status, b.total_amount, b.notes, b.created_at,
               b.location_type, b.customer_address,
               u.first_name, u.last_name, u.email, u.avatar_url,
               s.name AS service_name, s.service_type, s.shop_address
        FROM tbl_bookings b
        JOIN tbl_users u    ON u.id = b.customer_id
        JOIN tbl_services s ON s.id = b.service_id
        WHERE $where
        ORDER BY
          CASE b.status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END,
          b.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stBookings = $db->prepare($sql);
$stBookings->execute($params);
$bookings = $stBookings->fetchAll();

$flash    = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$initials = strtoupper(substr($providerName, 0, 2));

$tabLabels = [
    'all'         => 'All',
    'pending'     => 'Pending',
    'confirmed'   => 'Confirmed',
    'in_progress' => 'In Progress',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — My Bookings</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_bookings.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>(function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();</script>
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAV
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">

    <a href="<?= BASE_URL ?>provider/dashboard" class="pv-logo">
      Quick<em>Book</em>
      <span class="pv-logo-badge">Provider</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/bookings"     class="pv-nav-link is-active">
        Bookings
        <?php if ($counts['pending']): ?>
          <sup class="pv-sup"><?= $counts['pending'] ?></sup>
        <?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"     class="pv-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/availability" class="pv-nav-link">Availability</a>
      <a href="<?= BASE_URL ?>provider/profile"      class="pv-nav-link">Profile</a>
    </div>

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$providerId; require __DIR__ . '/../_partials/notification_panel.php'; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <svg class="icon-moon" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      </button>

      <div class="pv-nav-av" aria-hidden="true">
        <?php if (!empty($profile['profile_photo'])): ?>
          <img src="<?= htmlspecialchars($profile['profile_photo']) ?>" alt="Profile photo" style="width:34px;height:34px;min-width:34px;min-height:34px;max-width:34px;max-height:34px;object-fit:cover;border-radius:99px;display:block;">
        <?php else: ?>
          <span><?= $initials ?></span>
        <?php endif; ?>
      </div>
      <div class="pv-nav-user">
        <div class="pv-nav-user-name"><?= $providerName ?></div>
        <div class="pv-nav-user-role">Provider</div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout-icon" title="Sign out" aria-label="Sign out">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
      </a>
    </div>

  </div>
</nav>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>

  <div class="pv-hero-inner">
    <div class="pv-hero-text">
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Booking Management
      </p>
      <h1 class="pv-hero-name">My <em>Bookings</em></h1>
      <p class="pv-hero-date">
        <?= date('l, F j, Y') ?> &mdash;
        <?= number_format($counts['all']) ?> total booking<?= $counts['all'] !== 1 ? 's' : '' ?>
      </p>
    </div>


  </div>

</header>

<!-- ══════════════════════════════════════
     MAIN
══════════════════════════════════════ -->
<main class="pv-page" role="main">

  <!-- Flash is now shown as a toast via JS below -->

  <!-- ── STATUS CARDS ── -->
  <div class="bk-cards" role="region" aria-label="Filter by status">

    <a href="?status=all<?= $search ? '&q='.urlencode($search) : '' ?>"
       class="bk-card bk-card--gold <?= $statusFilter === 'all' ? 'is-active' : '' ?>">
      <div class="bk-card-top">
        <span class="bk-card-label">All Bookings</span>
        <span class="bk-card-tag bk-tag--gold">Total</span>
      </div>
      <div class="bk-card-middle">
        <div class="bk-card-val"><?= number_format($counts['all']) ?></div>
        <div class="bk-card-of">&#8369;<?= number_format($totalRevenue, 0) ?> earned</div>
      </div>
      <div class="bk-card-bar">
        <div class="bk-card-fill bk-fill--gold" style="width:100%"></div>
      </div>
    </a>

    <a href="?status=confirmed<?= $search ? '&q='.urlencode($search) : '' ?>"
       class="bk-card bk-card--green <?= $statusFilter === 'confirmed' ? 'is-active' : '' ?>">
      <div class="bk-card-top">
        <span class="bk-card-label">Confirmed</span>
        <span class="bk-card-tag bk-tag--green">Upcoming</span>
      </div>
      <div class="bk-card-middle">
        <div class="bk-card-val"><?= number_format($counts['confirmed']) ?></div>
        <div class="bk-card-of">of <?= $counts['all'] ?> total</div>
      </div>
      <div class="bk-card-bar">
        <div class="bk-card-fill bk-fill--green"
             style="width:<?= $counts['all'] > 0 ? round($counts['confirmed']/$counts['all']*100) : 0 ?>%"></div>
      </div>
    </a>

    <a href="?status=in_progress<?= $search ? '&q='.urlencode($search) : '' ?>"
       class="bk-card bk-card--purple <?= $statusFilter === 'in_progress' ? 'is-active' : '' ?>">
      <div class="bk-card-top">
        <span class="bk-card-label">In Progress</span>
        <span class="bk-card-tag bk-tag--purple">Live</span>
      </div>
      <div class="bk-card-middle">
        <div class="bk-card-val"><?= number_format($counts['in_progress']) ?></div>
        <div class="bk-card-of">of <?= $counts['all'] ?> total</div>
      </div>
      <div class="bk-card-bar">
        <div class="bk-card-fill bk-fill--purple"
             style="width:<?= $counts['all'] > 0 ? round($counts['in_progress']/$counts['all']*100) : 0 ?>%"></div>
      </div>
    </a>

    <a href="?status=completed<?= $search ? '&q='.urlencode($search) : '' ?>"
       class="bk-card bk-card--blue <?= $statusFilter === 'completed' ? 'is-active' : '' ?>">
      <div class="bk-card-top">
        <span class="bk-card-label">Completed</span>
        <span class="bk-card-tag bk-tag--blue">Done</span>
      </div>
      <div class="bk-card-middle">
        <div class="bk-card-val"><?= number_format($counts['completed']) ?></div>
        <div class="bk-card-of">of <?= $counts['all'] ?> total</div>
      </div>
      <div class="bk-card-bar">
        <div class="bk-card-fill bk-fill--blue"
             style="width:<?= $counts['all'] > 0 ? round($counts['completed']/$counts['all']*100) : 0 ?>%"></div>
      </div>
    </a>

    <a href="?status=cancelled<?= $search ? '&q='.urlencode($search) : '' ?>"
       class="bk-card bk-card--red <?= $statusFilter === 'cancelled' ? 'is-active' : '' ?>">
      <div class="bk-card-top">
        <span class="bk-card-label">Cancelled</span>
        <span class="bk-card-tag bk-tag--red">Closed</span>
      </div>
      <div class="bk-card-middle">
        <div class="bk-card-val"><?= number_format($counts['cancelled']) ?></div>
        <div class="bk-card-of">of <?= $counts['all'] ?> total</div>
      </div>
      <div class="bk-card-bar">
        <div class="bk-card-fill bk-fill--red"
             style="width:<?= $counts['all'] > 0 ? round($counts['cancelled']/$counts['all']*100) : 0 ?>%"></div>
      </div>
    </a>

    <a href="?status=pending<?= $search ? '&q='.urlencode($search) : '' ?>"
       class="bk-card bk-card--amber <?= $statusFilter === 'pending' ? 'is-active' : '' ?>">
      <div class="bk-card-top">
        <span class="bk-card-label">Pending</span>
        <span class="bk-card-tag bk-tag--amber">Action needed</span>
      </div>
      <div class="bk-card-middle">
        <div class="bk-card-val"><?= number_format($counts['pending']) ?></div>
        <div class="bk-card-of">of <?= $counts['all'] ?> total</div>
      </div>
      <div class="bk-card-bar">
        <div class="bk-card-fill bk-fill--amber"
             style="width:<?= $counts['all'] > 0 ? round($counts['pending']/$counts['all']*100) : 0 ?>%"></div>
      </div>
    </a>

  </div>

  <!-- ── TABLE PANEL ── -->
  <div class="pv-panel">
    <div class="pv-panel-head">
      <div>
        <h2><?= $tabLabels[$statusFilter] ?> Bookings</h2>
        <div class="pv-panel-sub">
          <?= number_format($totalFiltered) ?> result<?= $totalFiltered !== 1 ? 's' : '' ?>
          &mdash; page <?= $page ?> of <?= $totalPages ?>
        </div>
      </div>
    </div>

    <div class="pv-table-wrap">
      <table class="pv-table" aria-label="Bookings list">
        <colgroup>
          <col><!-- REF -->
          <col><!-- CUSTOMER -->
          <col><!-- SERVICE -->
          <col><!-- DATE -->
          <col><!-- AMOUNT -->
          <col><!-- BOOKED ON -->
          <col><!-- STATUS -->
          <col><!-- ACTIONS -->
        </colgroup>
        <thead>
          <tr>
            <th scope="col">Ref</th>
            <th scope="col">Customer</th>
            <th scope="col">Service</th>
            <th scope="col">Date</th>
            <th scope="col">Amount</th>
            <th scope="col">Booked On</th>
            <th scope="col">Status</th>
            <th scope="col" style="text-align:center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bookings)): ?>
          <tr>
            <td colspan="8" class="pv-empty">No bookings found for this filter.</td>
          </tr>
          <?php else: foreach ($bookings as $b): ?>
          <tr class="pv-row-clickable" onclick="window.location='<?= BASE_URL ?>provider/bookings/<?= (int)$b['id'] ?>'" style="cursor:pointer;">
            <td>
              <span class="pv-ref">#<?= str_pad($b['id'], 4, '0', STR_PAD_LEFT) ?></span>
            </td>

            <td>
              <div class="pv-cust">
                <div class="pv-cust-av" aria-hidden="true">
                  <?php if (!empty($b['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($b['avatar_url']) ?>"
                         alt="<?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?>">
                  <?php else: ?>
                    <?= strtoupper(substr($b['first_name'],0,1) . substr($b['last_name'],0,1)) ?>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="pv-cust-name"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></div>
                  <div class="pv-cust-email"><?= htmlspecialchars($b['email']) ?></div>
                </div>
              </div>
            </td>

            <td>
              <div class="pv-svc-name" title="<?= htmlspecialchars($b['service_name']) ?>">
                <?= htmlspecialchars($b['service_name']) ?>
              </div>
            </td>

            <td class="pv-mono pv-muted"><?= date('M d, Y', strtotime($b['booking_date'])) ?></td>
            <td class="pv-mono pv-gold">₱<?= number_format($b['total_amount'], 2) ?></td>
            <td class="pv-mono pv-faint" style="font-size:.71rem"><?= date('M d, Y', strtotime($b['created_at'])) ?></td>

            <td>
              <span class="pv-pill pv-pill--<?= $b['status'] ?>">
                <?= ucfirst(str_replace('_', ' ', $b['status'])) ?>
              </span>
            </td>

            <td>
              <div class="pv-actions-cell">
                <a href="<?= BASE_URL ?>provider/bookings/<?= (int)$b['id'] ?>"
                   class="pv-act-view"
                   onclick="event.stopPropagation()">View</a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1):
      $qs = fn(int $p) => '?status='.urlencode($statusFilter).'&q='.urlencode($search).'&page='.$p;
    ?>
    <nav class="pv-pagination" aria-label="Pagination">
      <a href="<?= $qs(1) ?>"            class="pv-page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" aria-label="First page">&laquo;</a>
      <a href="<?= $qs($page - 1) ?>"   class="pv-page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" aria-label="Previous page">&lsaquo;</a>

      <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <a href="<?= $qs($i) ?>"
         class="pv-page-btn <?= $i === $page ? 'is-active' : '' ?>"
         aria-label="Page <?= $i ?>"
         <?= $i === $page ? 'aria-current="page"' : '' ?>>
        <?= $i ?>
      </a>
      <?php endfor; ?>

      <a href="<?= $qs($page + 1) ?>"    class="pv-page-btn <?= $page >= $totalPages ? 'is-disabled' : '' ?>" aria-label="Next page">&rsaquo;</a>
      <a href="<?= $qs($totalPages) ?>"  class="pv-page-btn <?= $page >= $totalPages ? 'is-disabled' : '' ?>" aria-label="Last page">&raquo;</a>
    </nav>
    <?php endif; ?>

  </div><!-- /pv-panel -->

</main>

<!-- ══════════════════════════════════════
     DELETE BOOKING MODAL
══════════════════════════════════════ -->
<div class="pv-modal-overlay" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
  <div class="pv-modal pv-modal--delete">

    <button class="pv-modal-close pv-modal-close--abs" onclick="closeDeleteModal()" aria-label="Close">✕</button>

    <div class="modal-centered-header" aria-hidden="true">
      <div class="modal-icon-ring modal-icon-ring--red">
        <svg viewBox="0 0 24 24" fill="none" width="26" height="26">
          <path d="M12 8v5M12 16v.5" stroke="#FB7185" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
      </div>
      <h2 class="modal-title" id="deleteModalTitle">Cancel &amp; Delete Order</h2>
      <p class="modal-sub">
        You are about to cancel the booking for <strong id="delCustomerName"></strong> (<em id="delServiceName"></em>).<br>
        The customer will be <span class="hl-red">immediately notified</span> with your reason. This action <span class="hl-red">cannot be undone.</span>
      </p>
    </div>

    <form method="POST" id="deleteForm">
      <input type="hidden" name="action" value="delete">
      <label class="modal-field-label" for="delReason">
        Reason for cancellation <span class="modal-required">* required</span>
      </label>
      <textarea id="delReason"
                name="reason"
                class="pv-textarea"
                placeholder="e.g. Schedule conflict, Equipment issue, Emergency unavailability…"
                maxlength="400"
                required></textarea>
      <div class="modal-char-count"><span id="delCharCount">0</span> / 400</div>

      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--red" id="delSubmitBtn" disabled>
          Yes
        </button>
        <button type="button" class="modal-btn modal-btn--no" onclick="closeDeleteModal()">
          No
        </button>
      </div>
    </form>

  </div>
</div>

<!-- ══════════════════════════════════════
     CONFIRM BOOKING MODAL
══════════════════════════════════════ -->
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
        <strong id="confCustomerName"></strong> (<em id="confServiceName"></em>).<br>
        The customer will be notified immediately.
      </p>
    </div>

    <form method="POST" id="confirmForm">
      <input type="hidden" name="action" value="confirm">
      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--green">
          Yes
        </button>
        <button type="button" class="modal-btn modal-btn--no" onclick="closeConfirmModal()">
          No
        </button>
      </div>
    </form>

  </div>
</div>

<!-- ══════════════════════════════════════
     RESCHEDULE MODAL
══════════════════════════════════════ -->
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
        Suggest a new schedule for <strong id="reschedCustomerName"></strong> (<em id="reschedServiceName"></em>).<br>
        <span class="modal-sub-note">Current booking: <span id="reschedCurrentDate" class="hl-amber"></span></span>
      </p>
    </div>

    <form method="POST" id="reschedForm">
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
      <textarea id="reschedNote"
                name="resched_reason"
                class="pv-textarea"
                placeholder="e.g. I have a conflict at the original time. I'm suggesting this new slot as it works better for my schedule…"
                maxlength="500"
                required></textarea>
      <div class="modal-char-count"><span id="reschedCharCount">0</span> / 500</div>

      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--amber" id="reschedSubmitBtn" disabled>
          Send Reschedule Suggestion
        </button>
      </div>
    </form>

  </div>
</div>

<!-- ══════════════════════════════════════
     TOAST CONTAINER
══════════════════════════════════════ -->
<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

<script>
/* ── Delete modal ── */
function openDeleteModal(id, customerName, serviceName) {
  document.getElementById('deleteForm').action = '<?= BASE_URL ?>provider/bookings/' + id;
  document.getElementById('delCustomerName').textContent  = customerName;
  document.getElementById('delServiceName').textContent   = serviceName;
  document.getElementById('delReason').value              = '';
  document.getElementById('delCharCount').textContent     = '0';
  document.getElementById('delSubmitBtn').disabled        = true;
  document.getElementById('deleteModal').classList.add('is-open');
  setTimeout(function() { document.getElementById('delReason').focus(); }, 120);
}

function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('is-open');
}

/* Char counter + enable submit only when reason has content */
document.getElementById('delReason').addEventListener('input', function() {
  var len = this.value.trim().length;
  document.getElementById('delCharCount').textContent     = this.value.length;
  document.getElementById('delSubmitBtn').disabled        = len < 5;
});

/* Close on backdrop click */
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});

/* ── Confirm modal ── */
function openConfirmModal(id, customerName, serviceName) {
  document.getElementById('confirmForm').action = '<?= BASE_URL ?>provider/bookings/' + id;
  document.getElementById('confCustomerName').textContent = customerName;
  document.getElementById('confServiceName').textContent  = serviceName;
  document.getElementById('confirmModal').classList.add('is-open');
}

function closeConfirmModal() {
  document.getElementById('confirmModal').classList.remove('is-open');
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
  if (e.target === this) closeConfirmModal();
});

/* ── Reschedule modal ── */
function openReschedModal(id, customerName, serviceName, currentDate) {
  document.getElementById('reschedForm').action = '<?= BASE_URL ?>provider/bookings/' + id;
  document.getElementById('reschedCustomerName').textContent = customerName;
  document.getElementById('reschedServiceName').textContent  = serviceName;

  // Format current date nicely
  var d = new Date(currentDate);
  var opts = { year: 'numeric', month: 'long', day: 'numeric' };
  document.getElementById('reschedCurrentDate').textContent = isNaN(d) ? currentDate : d.toLocaleDateString('en-US', opts);

  // Reset fields
  document.getElementById('reschedDate').value        = '';
  document.getElementById('reschedTime').value        = '';
  document.getElementById('reschedNote').value        = '';
  document.getElementById('reschedCharCount').textContent = '0';
  document.getElementById('reschedSubmitBtn').disabled    = true;
  document.getElementById('reschedModal').classList.add('is-open');
  setTimeout(function() { document.getElementById('reschedDate').focus(); }, 120);
}

function closeReschedModal() {
  document.getElementById('reschedModal').classList.remove('is-open');
}

document.getElementById('reschedModal').addEventListener('click', function(e) {
  if (e.target === this) closeReschedModal();
});

/* Reschedule char counter + validate all fields */
function validateReschedForm() {
  var date  = document.getElementById('reschedDate').value.trim();
  var time  = document.getElementById('reschedTime').value.trim();
  var note  = document.getElementById('reschedNote').value.trim();
  document.getElementById('reschedSubmitBtn').disabled = !(date && time && note.length >= 5);
}

document.getElementById('reschedNote').addEventListener('input', function() {
  document.getElementById('reschedCharCount').textContent = this.value.length;
  validateReschedForm();
});
document.getElementById('reschedDate').addEventListener('change', validateReschedForm);
document.getElementById('reschedTime').addEventListener('change', validateReschedForm);

/* Close on Escape */
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    if (document.getElementById('deleteModal').classList.contains('is-open'))  closeDeleteModal();
    if (document.getElementById('confirmModal').classList.contains('is-open')) closeConfirmModal();
    if (document.getElementById('reschedModal').classList.contains('is-open')) closeReschedModal();
  }
});

/* ── Toast helper ── */
function showToast(message, type) {
  type = type || 'success';
  var container = document.getElementById('toastContainer');
  var toast = document.createElement('div');
  toast.className = 'toast toast--' + type;

  var icon = type === 'success'
    ? '<svg viewBox="0 0 16 16" fill="none"><path d="M3 8l3.5 3.5L13 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    : '<svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.6"/><path d="M8 5v3.5M8 10.5v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';

  toast.innerHTML = '<span class="toast-icon">' + icon + '</span><span class="toast-msg">' + message + '</span>';
  container.appendChild(toast);

  /* Animate in */
  requestAnimationFrame(function() {
    requestAnimationFrame(function() { toast.classList.add('is-visible'); });
  });

  /* Auto-dismiss after 4 s */
  setTimeout(function() {
    toast.classList.remove('is-visible');
    toast.addEventListener('transitionend', function() { toast.remove(); }, { once: true });
  }, 4000);
}

/* Show toast for flash message (from PHP session) */
<?php if ($flash): ?>
showToast(
  '<?= addslashes(htmlspecialchars_decode($flash['msg'])) ?>',
  '<?= $flash['type'] === 'success' ? 'success' : 'error' ?>'
);
<?php endif; ?>
</script>

<script>
  (function () {
    const html = document.documentElement;
    const btn  = document.getElementById('themeToggle');
    const moon = btn ? btn.querySelector('.icon-moon') : null;
    const sun  = btn ? btn.querySelector('.icon-sun')  : null;
    function applyTheme(t) {
      if (t === 'dark') {
        html.setAttribute('data-theme', 'dark');
        if (moon) moon.style.display = 'block';
        if (sun)  sun.style.display  = 'none';
      } else {
        html.removeAttribute('data-theme');
        if (moon) moon.style.display = 'none';
        if (sun)  sun.style.display  = 'block';
      }
    }
    applyTheme(localStorage.getItem('qb-theme') || 'light');
    if (btn) {
      btn.addEventListener('click', function() {
        const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('qb-theme', next);
        applyTheme(next);
      });
    }
  })();
</script>
</body>
</html>