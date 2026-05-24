<?php
// app/views/admin/users.php
$customers  = array_filter($users, fn($u) => $u['role'] === 'customer');
$total      = count($customers);
$verified   = count(array_filter($customers, fn($u) => (bool)($u['is_verified'] ?? false)));
$unverified = $total - $verified;

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

// Booking stats + services booked per customer
$stBk = $db->query("
    SELECT b.customer_id,
           COUNT(b.id)               AS total_bk,
           SUM(b.status='completed') AS completed,
           SUM(b.status='pending')   AS pending,
           SUM(b.status='cancelled') AS cancelled,
           MAX(b.booking_date)       AS last_booking,
           COALESCE(SUM(s.price),0)  AS total_spent,
           GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS services_booked
    FROM tbl_bookings b
    JOIN tbl_services s ON b.service_id = s.id
    GROUP BY b.customer_id
");
$bkMap = [];
foreach ($stBk->fetchAll() as $row) {
    $bkMap[$row['customer_id']] = $row;
}

$maxBk = max(1, ...array_map(fn($u) => (int)($bkMap[$u['id']]['total_bk'] ?? 0), $customers) ?: [1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Customers — QuickBook Admin</title>
<script>(function(){ var t=localStorage.getItem('qb-admin-theme')||'light'; document.documentElement.setAttribute('data-theme',t); })();</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_users.css">
</head>
<body>
<div class="grain"></div>
<?php require_once __DIR__ . '/_nav.php'; adminNav('users'); ?>

<div class="usr-page">

  <!-- Header -->
  <div class="usr-header anim-1">
    <div class="usr-eyebrow"><span class="usr-eyebrow-dot"></span>Management</div>
    <h1 class="usr-title">Platform <em>Customers</em></h1>
    <p class="usr-subtitle">All registered customers and their booking activity</p>
  </div>

  <!-- KPI Grid -->
  <div class="usr-kpi-grid anim-2">
    <div class="usr-kpi" style="--kpi-accent:#C9A84C">
      <div class="usr-kpi-val"><?= $total ?></div>
      <div class="usr-kpi-label">Total Customers</div>
    </div>
    <div class="usr-kpi" style="--kpi-accent:#16A34A">
      <div class="usr-kpi-val" style="color:#16A34A"><?= $verified ?></div>
      <div class="usr-kpi-label">Verified</div>
    </div>
    <div class="usr-kpi" style="--kpi-accent:#DC2626">
      <div class="usr-kpi-val" style="color:#DC2626"><?= $unverified ?></div>
      <div class="usr-kpi-label">Unverified</div>
    </div>
    <div class="usr-kpi" style="--kpi-accent:#2563EB">
      <div class="usr-kpi-val" style="color:#2563EB"><?= $maxBk ?></div>
      <div class="usr-kpi-label">Most Bookings</div>
    </div>
  </div>

  <!-- Panel -->
  <div class="usr-panel anim-3">

    <div class="usr-panel-head">
      <span class="usr-panel-title">Customer Directory</span>
      <span class="usr-panel-count"><?= $total ?> registered</span>
    </div>

    <div class="usr-filter-bar">
      <button class="usr-filter-btn active" data-filter="all">All</button>
      <button class="usr-filter-btn" data-filter="verified">Verified</button>
      <button class="usr-filter-btn" data-filter="unverified">Unverified</button>
      <div class="usr-search-wrap">
        <input class="usr-search" type="search" id="usr-search" placeholder="Search name, email…">
      </div>
    </div>

    <div class="usr-table-wrap">
      <table class="usr-table" id="usr-table">
        <thead>
          <tr>
            <th>Customer</th>
            <th>Services Booked</th>
            <th>Bookings</th>
            <th>Total Spent</th>
            <th>Last Booking</th>
            <th>Joined</th>
            <th>Verified</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($customers)): ?>
          <tr><td colspan="7">
            <div class="usr-empty">
              <div class="usr-empty-icon"><i class="fa-solid fa-users"></i></div>
              <p>No customers registered yet.</p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($customers as $u):
            $init    = strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1));
            $isVerif = (bool)($u['is_verified'] ?? false);
            $bk      = $bkMap[$u['id']] ?? [];
            $totalBk = (int)($bk['total_bk']    ?? 0);
            $spent   = (float)($bk['total_spent'] ?? 0);
            $lastBk  = $bk['last_booking']       ?? null;
            $services= $bk['services_booked']    ?? null;
            $barPct  = $maxBk > 0 ? round($totalBk / $maxBk * 100) : 0;
            $search  = strtolower($u['first_name'].' '.$u['last_name'].' '.$u['email']);
          ?>
          <tr data-verified="<?= $isVerif ? '1' : '0' ?>"
              data-search="<?= htmlspecialchars($search) ?>">

            <td>
              <div class="usr-td-customer">
                <div class="usr-av"><?= $init ?></div>
                <div>
                  <div class="usr-td-name"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></div>
                  <div class="usr-td-email"><?= htmlspecialchars($u['email']) ?></div>
                </div>
              </div>
            </td>

            <td>
              <?php if ($services): ?>
                <span class="usr-td-services" title="<?= htmlspecialchars($services) ?>"><?= htmlspecialchars($services) ?></span>
              <?php else: ?>
                <span class="usr-td-none">No bookings yet</span>
              <?php endif ?>
            </td>

            <td style="font-family:var(--font-mono);font-weight:600;color:var(--text-primary)"><?= $totalBk ?></td>

            <td class="usr-td-spent">₱<?= number_format($spent, 0) ?></td>

            <td class="usr-td-date"><?= $lastBk ? date('M j, Y', strtotime($lastBk)) : '—' ?></td>

            <td class="usr-td-date"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>

            <td>
              <span class="usr-pill <?= $isVerif ? 'yes' : 'no' ?>">
                <?= $isVerif ? 'Verified' : 'Unverified' ?>
              </span>
            </td>

          </tr>
          <?php endforeach ?>
        <?php endif ?>
        </tbody>
      </table>
    </div>

  </div>

</div><!-- /usr-page -->

<script>
document.querySelectorAll('.usr-filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.usr-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
  });
});

document.getElementById('usr-search').addEventListener('input', applyFilters);

function applyFilters() {
  const f     = document.querySelector('.usr-filter-btn.active').dataset.filter;
  const q     = document.getElementById('usr-search').value.toLowerCase().trim();
  const tbody = document.querySelector('#usr-table tbody');

  tbody.querySelectorAll('.usr-empty-row').forEach(r => r.remove());

  let visible = 0;
  tbody.querySelectorAll('tr[data-verified]').forEach(row => {
    const matchFilter =
      f === 'all' ||
      (f === 'verified'   && row.dataset.verified === '1') ||
      (f === 'unverified' && row.dataset.verified === '0');
    const matchSearch = !q || row.dataset.search.includes(q);
    const show = matchFilter && matchSearch;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  if (visible === 0) {
    const messages = {
      all:        'No customers found.',
      verified:   'No verified customers yet.',
      unverified: 'No unverified customers.',
    };
    const icons = {
      all:        'fa-users',
      verified:   'fa-circle-check',
      unverified: 'fa-circle-xmark',
    };
    const tr = document.createElement('tr');
    tr.className = 'usr-empty-row';
    tr.innerHTML = `<td colspan="7">
      <div class="usr-empty">
        <div class="usr-empty-icon"><i class="fa-solid ${icons[f]}"></i></div>
        <p>${messages[f]}</p>
      </div>
    </td>`;
    tbody.appendChild(tr);
  }
}
</script>
</body>
</html>