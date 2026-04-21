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
               u.first_name, u.last_name, u.email,
               s.name AS service_name, s.service_type
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
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_bookings.css">
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

    <?php if ($counts['pending'] > 0): ?>
    <a href="?status=pending" class="pv-pending-chip">
      <span class="pv-pending-dot" aria-hidden="true"></span>
      <?= $counts['pending'] ?> booking<?= $counts['pending'] > 1 ? 's' : '' ?> pending confirmation
      <span aria-hidden="true">→</span>
    </a>
    <?php endif; ?>
  </div>

  <!-- Stat strip -->
  <div class="pv-hero-stats" role="region" aria-label="Booking metrics">
    <div class="pv-hs-item">
      <span class="pv-hs-val">₱<?= number_format($totalRevenue, 0) ?></span>
      <span class="pv-hs-label">Revenue Earned</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val"><?= $counts['all'] ?></span>
      <span class="pv-hs-label">All Bookings</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-amber"><?= $counts['pending'] ?></span>
      <span class="pv-hs-label">Pending</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-green"><?= $counts['confirmed'] ?></span>
      <span class="pv-hs-label">Confirmed</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-blue"><?= $counts['completed'] ?></span>
      <span class="pv-hs-label">Completed</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-red"><?= $counts['cancelled'] ?></span>
      <span class="pv-hs-label">Cancelled</span>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     MAIN
══════════════════════════════════════ -->
<main class="pv-page" role="main">

  <!-- Flash message -->
  <?php if ($flash): ?>
  <div class="pv-flash pv-flash--<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="alert">
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- ── STATUS CARDS ── -->
  <div class="bk-cards" role="region" aria-label="Filter by status">

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

    <a href="?status=all<?= $search ? '&q='.urlencode($search) : '' ?>"
       class="bk-card bk-card--gold <?= $statusFilter === 'all' ? 'is-active' : '' ?>">
      <div class="bk-card-top">
        <span class="bk-card-label">All Bookings</span>
        <span class="bk-card-tag bk-tag--gold">Total</span>
      </div>
      <div class="bk-card-middle">
        <div class="bk-card-val"><?= number_format($counts['all']) ?></div>
        <div class="bk-card-of">₱<?= number_format($totalRevenue, 0) ?> earned</div>
      </div>
      <div class="bk-card-bar">
        <div class="bk-card-fill bk-fill--gold" style="width:100%"></div>
      </div>
    </a>

  </div>

  <!-- ── FILTER BAR ── -->
  <div class="pv-filter-bar">
    <div class="pv-tab-row" role="tablist" aria-label="Filter bookings by status">
      <?php foreach ($tabLabels as $key => $label):
        $active = $statusFilter === $key ? ' is-active' : '';
        $qs     = http_build_query(array_merge($_GET, ['status' => $key, 'page' => 1]));
      ?>
      <a href="?<?= $qs ?>" class="pv-tab<?= $active ?>"
         role="tab" aria-selected="<?= $statusFilter === $key ? 'true' : 'false' ?>">
        <?= $label ?>
        <span class="pv-tab-count"><?= $counts[$key] ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <form method="GET" class="pv-search-form" role="search">
      <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
      <input type="hidden" name="page"   value="1">
      <div class="pv-search-wrap">
        <svg class="pv-search-ico" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.5"/>
          <path d="M13.5 13.5L17 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <input type="text" name="q"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="Search customer, service, or ID…"
               aria-label="Search bookings">
        <?php if ($search): ?>
        <a href="?status=<?= urlencode($statusFilter) ?>" class="pv-search-clear" aria-label="Clear search">✕</a>
        <?php endif; ?>
      </div>
    </form>
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
        <thead>
          <tr>
            <th scope="col">Ref</th>
            <th scope="col">Customer</th>
            <th scope="col">Service</th>
            <th scope="col">Date</th>
            <th scope="col">Amount</th>
            <th scope="col">Booked On</th>
            <th scope="col">Status</th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bookings)): ?>
          <tr>
            <td colspan="8" class="pv-empty">No bookings found for this filter.</td>
          </tr>
          <?php else: foreach ($bookings as $b): ?>
          <tr>
            <td>
              <span class="pv-ref">#<?= str_pad($b['id'], 4, '0', STR_PAD_LEFT) ?></span>
            </td>

            <td>
              <div class="pv-cust">
                <div class="pv-cust-av" aria-hidden="true">
                  <?= strtoupper(substr($b['first_name'],0,1) . substr($b['last_name'],0,1)) ?>
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
              <?php if (!empty($b['service_type'])): ?>
              <span class="pv-svc-tag pv-svc-tag--<?= $b['service_type'] === 'home_service' ? 'home' : 'shop' ?>">
                <?= $b['service_type'] === 'home_service' ? '🏠 Home' : '🏪 Shop' ?>
              </span>
              <?php endif; ?>
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

                <?php if ($b['status'] === 'pending'): ?>
                  <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= $b['id'] ?>">
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="pv-act pv-act--confirm" title="Confirm booking">
                      <svg viewBox="0 0 16 16" fill="none"><path d="M3 8l3.5 3.5L13 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                  </form>
                  <button class="pv-act pv-act--reject" title="Reject booking"
                          onclick="openModal('rejectModal', <?= $b['id'] ?>, '<?= htmlspecialchars($b['first_name'].' '.$b['last_name'], ENT_QUOTES) ?>')">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  </button>

                <?php elseif ($b['status'] === 'confirmed'): ?>
                  <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= $b['id'] ?>">
                    <input type="hidden" name="action" value="start">
                    <button type="submit" class="pv-act pv-act--start" title="Mark In Progress">
                      <svg viewBox="0 0 16 16" fill="none"><polygon points="5,3 13,8 5,13" fill="currentColor"/></svg>
                    </button>
                  </form>
                  <button class="pv-act pv-act--cancel" title="Cancel booking"
                          onclick="openModal('cancelModal', <?= $b['id'] ?>, '<?= htmlspecialchars($b['first_name'].' '.$b['last_name'], ENT_QUOTES) ?>')">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                  </button>

                <?php elseif ($b['status'] === 'in_progress'): ?>
                  <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= $b['id'] ?>">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="pv-act pv-act--complete" title="Mark Complete">
                      <svg viewBox="0 0 16 16" fill="none"><path d="M2 8l4 4L14 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                  </form>

                <?php else: ?>
                  <span class="pv-no-act">&mdash;</span>
                <?php endif; ?>

                <?php if (!empty($b['notes'])): ?>
                <button class="pv-act pv-act--note" title="<?= htmlspecialchars($b['notes']) ?>" aria-label="View note">
                  <svg viewBox="0 0 16 16" fill="none">
                    <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M5 6h6M5 9h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  </svg>
                </button>
                <?php endif; ?>

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
     REJECT MODAL
══════════════════════════════════════ -->
<div class="pv-modal-overlay" id="rejectModal" role="dialog" aria-modal="true" aria-labelledby="rejectTitle">
  <div class="pv-modal">
    <div class="pv-modal-head">
      <span class="pv-modal-title" id="rejectTitle">Reject Booking</span>
      <button class="pv-modal-close" onclick="closeModal('rejectModal')" aria-label="Close">✕</button>
    </div>
    <p class="pv-modal-desc">
      You are rejecting the booking from <strong id="rejectName"></strong>.
      An optional reason will be sent to the customer.
    </p>
    <form method="POST" id="rejectForm">
      <input type="hidden" name="action" value="reject">
      <textarea name="reason" class="pv-textarea" placeholder="Reason for rejection (optional)…"></textarea>
      <div class="pv-modal-foot">
        <button type="button" class="pv-btn-ghost" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="pv-btn-danger">Reject Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     CANCEL MODAL
══════════════════════════════════════ -->
<div class="pv-modal-overlay" id="cancelModal" role="dialog" aria-modal="true" aria-labelledby="cancelTitle">
  <div class="pv-modal">
    <div class="pv-modal-head">
      <span class="pv-modal-title" id="cancelTitle">Cancel Booking</span>
      <button class="pv-modal-close" onclick="closeModal('cancelModal')" aria-label="Close">✕</button>
    </div>
    <p class="pv-modal-desc">
      You are cancelling the confirmed booking for <strong id="cancelName"></strong>.
      The customer will be notified immediately.
    </p>
    <form method="POST" id="cancelForm">
      <input type="hidden" name="action" value="cancel">
      <textarea name="reason" class="pv-textarea" placeholder="Reason for cancellation (optional)…"></textarea>
      <div class="pv-modal-foot">
        <button type="button" class="pv-btn-ghost" onclick="closeModal('cancelModal')">Back</button>
        <button type="submit" class="pv-btn-danger">Cancel Booking</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(modalId, id, name) {
  if (modalId === 'rejectModal') {
    document.getElementById('rejectForm').action = '<?= BASE_URL ?>provider/bookings/' + id;
    document.getElementById('rejectName').textContent = name;
  } else {
    document.getElementById('cancelForm').action = '<?= BASE_URL ?>provider/bookings/' + id;
    document.getElementById('cancelName').textContent = name;
  }
  var el = document.getElementById(modalId);
  el.classList.add('is-open');
  el.querySelector('.pv-modal-close').focus();
}

function closeModal(id) {
  document.getElementById(id).classList.remove('is-open');
}

/* Close on backdrop click */
document.querySelectorAll('.pv-modal-overlay').forEach(function(el) {
  el.addEventListener('click', function(e) {
    if (e.target === el) closeModal(el.id);
  });
});

/* Close on Escape */
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.pv-modal-overlay.is-open').forEach(function(el) {
      closeModal(el.id);
    });
  }
});
</script>

</body>
</html>