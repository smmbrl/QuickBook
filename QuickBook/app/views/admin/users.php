<?php
// app/views/admin/users.php
$total     = count($users);
$customers = count(array_filter($users, fn($u) => $u['role'] === 'customer'));
$providers = count(array_filter($users, fn($u) => $u['role'] === 'provider'));
$admins    = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users — QuickBook Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_users.css">
</head>
<body>
<div class="grain"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('users'); ?>

<div class="admin-page">
<div class="content">

  <div class="page-greeting anim-1">
    <div>
      <div class="eyebrow"><span class="eyebrow-dot"></span>Management</div>
      <h1>Platform <em>Users</em></h1>
      <p>All registered accounts across every role</p>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpi-row anim-2">
    <div class="kpi-card">
      <div class="kpi-val"><?= $total ?></div>
      <div class="kpi-lbl">Total Users</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:#4ADE80"><?= $customers ?></div>
      <div class="kpi-lbl">Customers</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:var(--gold)"><?= $providers ?></div>
      <div class="kpi-lbl">Providers</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-val" style="color:#FB7185"><?= $admins ?></div>
      <div class="kpi-lbl">Admins</div>
    </div>
  </div>

  <!-- Users panel -->
  <div class="panel anim-3">
    <div class="panel-header">
      <h2>User Directory</h2>
      <span style="font-family:var(--font-mono);font-size:.6rem;color:var(--faint)"><?= $total ?> accounts</span>
    </div>

    <div class="filter-bar-wrap">
      <button class="filter-btn active" data-filter="all">All</button>
      <button class="filter-btn" data-filter="customer">Customers</button>
      <button class="filter-btn" data-filter="provider">Providers</button>
      <button class="filter-btn" data-filter="admin">Admins</button>
      <input class="search-input" type="search" id="usr-search" placeholder="Search name, email…">
    </div>

    <div class="table-wrap">
      <table class="data-table" id="usr-table">
        <thead>
          <tr>
            <th>User</th><th>Email</th><th>Role</th><th>Verified</th><th>Joined</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="5" class="empty-row">No users registered yet.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $u):
            $initials = strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1));
            $role     = $u['role'];
            $avcls    = $role === 'admin' ? 'av-red' : ($role === 'provider' ? 'av-gold' : 'av-green');
            $verified = (bool)($u['is_verified'] ?? false);
            $search   = strtolower($u['first_name'].' '.$u['last_name'].' '.$u['email']);
          ?>
            <tr data-role="<?= htmlspecialchars($role) ?>" data-search="<?= htmlspecialchars($search) ?>">
              <td>
                <div class="user-cell">
                  <div class="av <?= $avcls ?>"><?= $initials ?></div>
                  <span class="user-full"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></span>
                </div>
              </td>
              <td class="td-dim" style="font-size:.75rem"><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="role-pill <?= $role ?>"><?= $role ?></span></td>
              <td>
                <?php if ($verified): ?>
                  <span class="verified-label yes"><span class="verified-dot"></span>Verified</span>
                <?php else: ?>
                  <span class="verified-label no"><span class="verified-dot"></span>Unverified</span>
                <?php endif ?>
              </td>
              <td class="td-mono td-dim"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
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
document.getElementById('usr-search').addEventListener('input', applyFilters);
function applyFilters() {
  const f = document.querySelector('.filter-btn.active').dataset.filter;
  const q = document.getElementById('usr-search').value.toLowerCase();
  document.querySelectorAll('#usr-table tbody tr[data-role]').forEach(row => {
    row.style.display = ((f === 'all' || row.dataset.role === f) && (!q || row.dataset.search.includes(q))) ? '' : 'none';
  });
}
</script>
</body>
</html>