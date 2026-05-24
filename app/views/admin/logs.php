<?php
// app/views/admin/logs.php

$actionLabels = [
    'admin_login'           => ['label' => 'Admin Login',        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>', 'color' => 'var(--blue)'],
    'update_booking_status' => ['label' => 'Booking Updated',    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="15" x2="15" y2="15"/></svg>', 'color' => 'var(--yellow)'],
    'delete_booking'        => ['label' => 'Booking Deleted',    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>', 'color' => '#FB7185'],
    'approve_provider'      => ['label' => 'Provider Approved',  'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>', 'color' => '#4ADE80'],
    'suspend_provider'      => ['label' => 'Provider Suspended', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>', 'color' => '#FB7185'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activity Logs — QuickBook Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_logs.css">
</head>
<body>
<div class="grain"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('logs'); ?>

<div class="admin-page">
<div class="content">

  <div class="page-greeting anim-1">
    <div>
      <div class="eyebrow"><span class="eyebrow-dot"></span>Audit Trail</div>
      <h1>Activity <em>Logs</em></h1>
      <p>A record of all administrator actions on the platform</p>
    </div>
  </div>

  <?php
    $totalLogs   = count($logs);
    $loginCount  = count(array_filter($logs, fn($l) => $l['action'] === 'admin_login'));
    $approvals   = count(array_filter($logs, fn($l) => $l['action'] === 'approve_provider'));
    $suspensions = count(array_filter($logs, fn($l) => $l['action'] === 'suspend_provider'));
    $deletions   = count(array_filter($logs, fn($l) => $l['action'] === 'delete_booking'));
  ?>

  <!-- KPI strip -->
  <div class="log-kpi anim-2">
    <div class="log-kpi-card">
      <div class="log-kpi-val"><?= $totalLogs ?></div>
      <div class="log-kpi-lbl">Total Entries</div>
    </div>
    <div class="log-kpi-card">
      <div class="log-kpi-val" style="color:var(--blue)"><?= $loginCount ?></div>
      <div class="log-kpi-lbl">Admin Logins</div>
    </div>
    <div class="log-kpi-card">
      <div class="log-kpi-val" style="color:#4ADE80"><?= $approvals ?></div>
      <div class="log-kpi-lbl">Approvals</div>
    </div>
    <div class="log-kpi-card">
      <div class="log-kpi-val" style="color:#FB7185"><?= $suspensions ?></div>
      <div class="log-kpi-lbl">Suspensions</div>
    </div>
    <div class="log-kpi-card">
      <div class="log-kpi-val" style="color:#FB7185"><?= $deletions ?></div>
      <div class="log-kpi-lbl">Deletions</div>
    </div>
  </div>

  <!-- Logs panel -->
  <div class="panel anim-3">
    <div class="panel-header">
      <h2>Audit Trail</h2>
      <span style="font-family:var(--font-mono);font-size:.6rem;color:var(--faint)"><?= $totalLogs ?> entries (latest 200)</span>
    </div>

    <!-- Filter + search bar -->
    <div class="filter-bar-wrap">
      <button class="filter-btn active" data-filter="all">All</button>
      <button class="filter-btn" data-filter="admin_login">Logins</button>
      <button class="filter-btn" data-filter="approve_provider">Approvals</button>
      <button class="filter-btn" data-filter="suspend_provider">Suspensions</button>
      <button class="filter-btn" data-filter="update_booking_status">Booking Updates</button>
      <button class="filter-btn" data-filter="delete_booking">Deletions</button>
      <div class="log-search-wrap">
        <svg class="log-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>
        <input class="log-search" type="search" id="log-search" placeholder="Search admin details…">
      </div>
    </div>

    <div style="padding: .5rem 1rem 1.25rem;">
      <?php if (empty($logs)): ?>
        <div class="empty-row">No activity logged yet. Actions taken by admins will appear here.</div>
      <?php else: ?>
        <div class="logs-wrap" id="logs-list">
          <?php foreach ($logs as $log):
            $meta   = $actionLabels[$log['action']] ?? ['label' => ucwords(str_replace('_', ' ', $log['action'])), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>', 'color' => 'var(--faint)'];
            $admin  = htmlspecialchars($log['first_name'] . ' ' . $log['last_name']);
            $time   = date('M d, Y · g:i A', strtotime($log['created_at']));
            $detail = htmlspecialchars($log['details'] ?? '');
            $ip     = htmlspecialchars($log['ip_address'] ?? '—');
            $search = strtolower($log['first_name'].' '.$log['last_name'].' '.$log['details'].' '.$log['action']);
          ?>
            <div class="log-row" data-action="<?= htmlspecialchars($log['action']) ?>" data-search="<?= htmlspecialchars($search) ?>">

              <div class="log-icon"><?= $meta['icon'] ?></div>

              <div class="log-body">
                <div class="log-action" style="color:<?= $meta['color'] ?>"><?= $meta['label'] ?></div>
                <div class="log-meta">
                  <span><svg class="log-meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><?= $admin ?></span>
                  <?php if ($log['target_type'] && $log['target_id']): ?>
                    <span><?= ucfirst($log['target_type']) ?> #<?= $log['target_id'] ?></span>
                  <?php endif ?>
                  <span><svg class="log-meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg><?= $ip ?></span>
                </div>
                <?php if ($detail): ?>
                  <div class="log-details" title="<?= $detail ?>"><?= $detail ?></div>
                <?php endif ?>
              </div>

              <div class="log-time"><?= $time ?></div>
            </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>
  </div>

</div>
</div>

<script>
const filterBtns = document.querySelectorAll('.filter-btn');
const search = document.getElementById('log-search');

filterBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    filterBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
  });
});
search?.addEventListener('input', applyFilters);

function applyFilters() {
  const activeFilter = document.querySelector('.filter-btn.active')?.dataset.filter ?? 'all';
  const q = search?.value.toLowerCase() ?? '';
  document.querySelectorAll('#logs-list .log-row').forEach(row => {
    const matchAction = activeFilter === 'all' || row.dataset.action === activeFilter;
    const matchSearch = !q || row.dataset.search.includes(q);
    row.style.display = matchAction && matchSearch ? '' : 'none';
  });
}
</script>
</body>
</html>