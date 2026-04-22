<?php
// app/views/admin/bookings.php
$statusOptions = ['pending','confirmed','in_progress','completed','cancelled'];

$counts  = array_fill_keys($statusOptions, 0);
$revenue = 0;
foreach ($bookings as $b) {
    $s = $b['status'] ?? '';
    if (isset($counts[$s])) $counts[$s]++;
    if ($s === 'completed') $revenue += (float)$b['total_amount'];
}
$total = count($bookings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bookings — QuickBook Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_bookings.css">
</head>
<body>
<div class="grain"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('bookings'); ?>

<div class="admin-page">
<div class="content">

  <div class="page-greeting anim-1">
    <div>
      <div class="eyebrow"><span class="eyebrow-dot"></span>Management</div>
      <h1>All <em>Bookings</em></h1>
      <p>Platform-wide booking history and status control</p>
    </div>
  </div>

  <!-- KPI strip -->
  <div class="kpi-row anim-2">
    <div class="kpi-card">
      <div class="kpi-val"><?= $total ?></div>
      <div class="kpi-lbl">Total</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:var(--yellow)"><?= $counts['pending'] ?></div>
      <div class="kpi-lbl">Pending</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:var(--purple)"><?= $counts['in_progress'] ?></div>
      <div class="kpi-lbl">In Progress</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:#4ADE80"><?= $counts['confirmed'] ?></div>
      <div class="kpi-lbl">Confirmed</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:var(--blue)"><?= $counts['completed'] ?></div>
      <div class="kpi-lbl">Completed</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:var(--gold);font-size:1.15rem">₱<?= number_format($revenue, 0) ?></div>
      <div class="kpi-lbl">Revenue</div>
    </div>
  </div>

  <!-- Bookings panel -->
  <div class="panel anim-3">
    <div class="panel-header">
      <h2>Booking Records</h2>
      <span style="font-family:var(--font-mono);font-size:.6rem;color:var(--faint)"><?= $total ?> total</span>
    </div>

    <!-- Filter + search -->
    <div class="filter-bar-wrap">
      <button class="filter-btn active" data-filter="all">All</button>
      <?php foreach ($statusOptions as $opt): ?>
        <button class="filter-btn" data-filter="<?= $opt ?>"><?= ucfirst(str_replace('_',' ',$opt)) ?></button>
      <?php endforeach ?>
      <input class="search-input" type="search" id="bk-search" placeholder="Search customer, service…">
    </div>

    <div class="table-wrap">
      <table class="data-table" id="bookings-table">
        <thead>
          <tr>
            <th>#ID</th><th>Customer</th><th>Service</th>
            <th>Provider</th><th>Date</th><th>Amount</th>
            <th>Status</th><th>Update</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($bookings)): ?>
          <tr><td colspan="8" class="empty-row">No bookings found.</td></tr>
        <?php else: ?>
          <?php foreach ($bookings as $b): ?>
            <tr data-status="<?= htmlspecialchars($b['status']) ?>"
                data-search="<?= strtolower(htmlspecialchars($b['cust_first'].' '.$b['cust_last'].' '.$b['service_name'])) ?>">
              <td class="td-mono td-dim">#<?= $b['id'] ?></td>
              <td class="td-name"><?= htmlspecialchars($b['cust_first'].' '.$b['cust_last']) ?></td>
              <td><?= htmlspecialchars($b['service_name']) ?></td>
              <td class="td-dim"><?= htmlspecialchars($b['prov_first'].' '.$b['prov_last']) ?></td>
              <td class="td-mono td-dim"><?= htmlspecialchars($b['booking_date']) ?></td>
              <td class="td-gold">₱<?= number_format($b['total_amount'], 2) ?></td>
              <td>
                <?php
                  $sc = in_array($b['status'], $statusOptions) ? $b['status'] : '';
                  echo "<span class='status-pill {$sc}'>" . htmlspecialchars($b['status']) . "</span>";
                ?>
              </td>
              <td>
                <form method="POST" action="<?= BASE_URL ?>admin/bookings/<?= $b['id'] ?>" class="update-form">
                  <select name="status" class="status-select">
                    <?php foreach ($statusOptions as $opt): ?>
                      <option value="<?= $opt ?>" <?= $b['status'] === $opt ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_',' ',$opt)) ?>
                      </option>
                    <?php endforeach ?>
                  </select>
                  <button type="submit" class="btn btn-save">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach ?>
        <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>

<script>
document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active'); applyFilters();
  });
});
document.getElementById('bk-search').addEventListener('input', applyFilters);
function applyFilters() {
  const f = document.querySelector('.filter-btn.active').dataset.filter;
  const q = document.getElementById('bk-search').value.toLowerCase();
  document.querySelectorAll('#bookings-table tbody tr[data-status]').forEach(row => {
    row.style.display = ((f === 'all' || row.dataset.status === f) && (!q || row.dataset.search.includes(q))) ? '' : 'none';
  });
}
</script>
</body>
</html>