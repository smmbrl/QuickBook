<?php
// app/views/admin/providers.php
$total    = count($providers);
$approved = count(array_filter($providers, fn($p) => $p['is_approved']));
$pending  = $total - $approved;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Providers — QuickBook Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_providers.css">
</head>
<body>
<div class="grain"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('providers'); ?>

<div class="admin-page">
<div class="content">

  <div class="page-greeting anim-1">
    <div>
      <div class="eyebrow"><span class="eyebrow-dot"></span>Management</div>
      <h1>Service <em>Providers</em></h1>
      <p>Approve, review, and manage all providers on the platform</p>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpi-row anim-2">
    <div class="kpi-card">
      <div class="kpi-val"><?= $total ?></div>
      <div class="kpi-lbl">Total Providers</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:#4ADE80"><?= $approved ?></div>
      <div class="kpi-lbl">Approved</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:var(--yellow)"><?= $pending ?></div>
      <div class="kpi-lbl">Pending Review</div>
    </div>
  </div>

  <!-- Panel -->
  <div class="panel anim-3">
    <div class="panel-header">
      <h2>Provider Directory</h2>
      <span style="font-family:var(--font-mono);font-size:.6rem;color:var(--faint)"><?= $total ?> registered</span>
    </div>

    <div class="filter-bar-wrap">
      <button class="filter-btn active" data-filter="all">All</button>
      <button class="filter-btn" data-filter="1">Approved</button>
      <button class="filter-btn" data-filter="0">Pending</button>
      <input class="search-input" type="search" id="prov-search" placeholder="Search name, business…">
    </div>

    <div class="table-wrap">
      <table class="data-table" id="prov-table">
        <thead>
          <tr>
            <th>Provider</th><th>Email</th><th>Services</th>
            <th>Joined</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($providers)): ?>
          <tr><td colspan="6" class="empty-row">No providers registered yet.</td></tr>
        <?php else: ?>
          <?php foreach ($providers as $p):
            $initials  = strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1));
            $isApproved = (bool)$p['is_approved'];
            $search    = strtolower($p['first_name'].' '.$p['last_name'].' '.($p['business_name']??''));
          ?>
            <tr data-approved="<?= (int)$p['is_approved'] ?>" data-search="<?= htmlspecialchars($search) ?>">
              <td>
                <div class="prov-cell">
                  <div class="av av-gold"><?= $initials ?></div>
                  <div>
                    <div class="prov-name"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></div>
                    <?php if (!empty($p['business_name'])): ?>
                      <div class="prov-biz"><?= htmlspecialchars($p['business_name']) ?></div>
                    <?php endif ?>
                  </div>
                </div>
              </td>
              <td class="td-dim" style="font-size:.75rem"><?= htmlspecialchars($p['email']) ?></td>
              <td class="td-mono"><?= (int)$p['service_count'] ?></td>
              <td class="td-mono td-dim"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
              <td>
                <?php if ($isApproved): ?>
                  <span class="status-pill approved">Approved</span>
                <?php else: ?>
                  <span class="status-pill pending">Pending</span>
                <?php endif ?>
              </td>
              <td>
                <form method="POST" action="<?= BASE_URL ?>admin/providers/<?= $p['id'] ?>">
                  <?php if ($isApproved): ?>
                    <input type="hidden" name="action" value="suspend">
                    <button type="submit" class="btn btn-suspend">Suspend</button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-approve">Approve ✓</button>
                  <?php endif ?>
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
document.getElementById('prov-search').addEventListener('input', applyFilters);
function applyFilters() {
  const f = document.querySelector('.filter-btn.active').dataset.filter;
  const q = document.getElementById('prov-search').value.toLowerCase();
  document.querySelectorAll('#prov-table tbody tr[data-approved]').forEach(row => {
    row.style.display = ((f === 'all' || row.dataset.approved === f) && (!q || row.dataset.search.includes(q))) ? '' : 'none';
  });
}
</script>
</body>
</html>