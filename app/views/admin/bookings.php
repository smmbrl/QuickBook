<?php
// app/views/admin/bookings.php
$statusOptions = ['pending','confirmed','in_progress','completed','cancelled','rescheduled'];

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$counts  = array_fill_keys($statusOptions, 0);
$revenue = 0;
foreach ($bookings as $b) {
    $s = $b['status'] ?? '';
    if (isset($counts[$s])) $counts[$s]++;
    if ($s === 'completed') $revenue += (float)$b['total_amount'];
}
$total = count($bookings);

$kpiConfig = [
    'total'       => ['label'=>'Total Bookings', 'val'=>$total,                'color'=>'#C9A84C'],
    'pending'     => ['label'=>'Pending',         'val'=>$counts['pending'],    'color'=>'#D97706'],
    'in_progress' => ['label'=>'In Progress',     'val'=>$counts['in_progress'],'color'=>'#EA580C'],
    'confirmed'   => ['label'=>'Confirmed',       'val'=>$counts['confirmed'],  'color'=>'#16A34A'],
    'completed'   => ['label'=>'Completed',       'val'=>$counts['completed'],  'color'=>'#2563EB'],
    'cancelled'   => ['label'=>'Cancelled',       'val'=>$counts['cancelled'],  'color'=>'#DC2626'],
    'revenue'     => ['label'=>'Revenue',         'val'=>$revenue,              'color'=>'#C9A84C'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bookings — QuickBook Admin</title>
<script>
  (function(){ var t=localStorage.getItem('qb-admin-theme')||'light'; document.documentElement.setAttribute('data-theme',t); })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_bookings.css">
</head>
<body>
<div class="grain"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('bookings'); ?>

<div class="bk-page">

  <!-- Header -->
  <div class="bk-header anim-1">

  <?php if ($flash): ?>
  <div style="margin-bottom:1rem;padding:.75rem 1rem;border-radius:10px;display:flex;align-items:center;gap:.6rem;font-size:.88rem;font-weight:500;
              background:<?= $flash['type']==='success' ? 'rgba(22,163,74,.1)' : 'rgba(220,38,38,.1)' ?>;
              border:1px solid <?= $flash['type']==='success' ? 'rgba(22,163,74,.3)' : 'rgba(220,38,38,.3)' ?>;
              color:<?= $flash['type']==='success' ? '#16A34A' : '#DC2626' ?>">
    <i class="fa-solid <?= $flash['type']==='success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
  <?php endif ?>

    <div class="bk-eyebrow"><span class="bk-eyebrow-dot"></span>Management</div>
    <h1 class="bk-title">All <em>Bookings</em></h1>
    <p class="bk-subtitle">Platform-wide booking history and status control</p>
  </div>

  <!-- KPI Grid -->
  <div class="bk-kpi-grid anim-2">
    <?php foreach ($kpiConfig as $key => $kpi):
      $accent  = $kpi['color'];
      $rgb     = sscanf(ltrim($accent,'#'),'%02x%02x%02x');
      $glow    = 'rgba(' . implode(',', $rgb) . ',.12)';
      $isRev   = $key === 'revenue';
      $isTotal = $key === 'total';
      $filter  = ($isRev || $isTotal) ? 'all' : $key;
    ?>
    <div class="bk-kpi <?= $key === 'total' ? 'is-kpi-active' : '' ?>"
         style="--kpi-accent:<?= $accent ?>;--kpi-glow:<?= $glow ?>"
         data-kpi-filter="<?= $filter ?>"
         role="button" tabindex="0"
         title="Filter by <?= $kpi['label'] ?>">
      <div class="bk-kpi-val"><?= $isRev ? '₱'.number_format((float)$kpi['val'],0) : number_format((int)$kpi['val']) ?></div>
      <div class="bk-kpi-label"><?= $kpi['label'] ?></div>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Bookings Panel -->
  <div class="bk-panel anim-3">

    <div class="bk-panel-head">
      <span class="bk-panel-title">Booking Records</span>
      <div class="bk-search-wrap">
        <i class="fa fa-magnifying-glass bk-search-icon"></i>
        <input class="bk-search" type="search" id="bk-search" placeholder="Search customer, service">
      </div>
    </div>

    <div class="bk-table-wrap">
      <table class="bk-table" id="bookings-table">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Business / Provider</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($bookings)): ?>
          <tr>
            <td colspan="8">
              <div class="bk-empty">
                <div class="bk-empty-icon">📭</div>
                <p>No bookings found.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($bookings as $b):
            $initials = strtoupper(substr($b['cust_first'],0,1).substr($b['cust_last'],0,1));
            $sc       = in_array($b['status'], $statusOptions) ? $b['status'] : 'default';
            $label    = ucfirst(str_replace('_', ' ', $b['status']));
            $search   = strtolower($b['cust_first'].' '.$b['cust_last'].' '.($b['business_name'] ?? $b['service_name']));
          ?>
          <tr data-status="<?= htmlspecialchars($b['status']) ?>"
              data-search="<?= htmlspecialchars($search) ?>">
            <td class="bk-td-id" data-label="ID">#<?= $b['id'] ?></td>
            <td data-label="Customer">
              <div class="bk-td-customer">
                <div class="bk-td-av"><?= $initials ?></div>
                <span class="bk-td-name"><?= htmlspecialchars($b['cust_first'].' '.$b['cust_last']) ?></span>
              </div>
            </td>
            <td class="bk-td-service" data-label="Service"><?= htmlspecialchars($b['service_name']) ?></td>
            <td class="bk-td-provider" data-label="Provider">
              <div style="font-weight:600"><?= htmlspecialchars($b['business_name'] ?? ($b['prov_first'].' '.$b['prov_last'])) ?></div>
              <div style="font-size:.78rem;opacity:.6"><?= htmlspecialchars($b['prov_first'].' '.$b['prov_last']) ?></div>
            </td>
            <td class="bk-td-date" data-label="Date"><?= date('M d, Y', strtotime($b['booking_date'])) ?></td>
            <td class="bk-td-amount" data-label="Amount">₱<?= number_format($b['total_amount'], 2) ?></td>
            <td data-label="Status"><span class="adm-pill adm-pill--<?= $sc ?>"><?= $label ?></span></td>
            <td data-label="Actions">
              <div class="bk-row-actions">
                <!-- Status update -->
                <form method="POST" action="<?= BASE_URL ?>admin/bookings/<?= (int)$b['id'] ?>" class="bk-status-form">
                  <select name="status" class="bk-status-select" onchange="this.form.submit()" title="Change status">
                    <?php foreach ($statusOptions as $opt): ?>
                      <option value="<?= $opt ?>" <?= $b['status'] === $opt ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_',' ',$opt)) ?>
                      </option>
                    <?php endforeach ?>
                  </select>
                </form>
                <!-- Delete -->
                <form method="POST" action="<?= BASE_URL ?>admin/bookings/<?= (int)$b['id'] ?>/delete"
                      onsubmit="return confirm('Delete booking #<?= $b['id'] ?>? This cannot be undone.')">
                  <button type="submit" class="bk-del-btn" title="Delete booking">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
        <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /bk-page -->

<script>
let activeFilter = 'all';

// ── KPI card click → filter table ──
document.querySelectorAll('.bk-kpi[data-kpi-filter]').forEach(card => {
  card.addEventListener('click', () => {
    activeFilter = card.dataset.kpiFilter;
    document.querySelectorAll('.bk-kpi').forEach(c => c.classList.remove('is-kpi-active'));
    card.classList.add('is-kpi-active');
    applyFilters();
  });
});

document.getElementById('bk-search').addEventListener('input', applyFilters);

function applyFilters() {
  const q = document.getElementById('bk-search').value.toLowerCase().trim();
  document.querySelectorAll('#bookings-table tbody tr[data-status]').forEach(row => {
    const matchStatus = activeFilter === 'all' || row.dataset.status === activeFilter;
    const matchSearch = !q || row.dataset.search.includes(q);
    row.style.display = matchStatus && matchSearch ? '' : 'none';
  });
}

applyFilters();
</script>
</body>
</html>