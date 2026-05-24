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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_providers.css">
</head>
<body>
<div class="grain"></div>
<?php require_once __DIR__ . '/_nav.php'; adminNav('providers'); ?>

<div class="pv-page">

  <!-- Hero -->
  <div class="pv-hero">
    <div class="pv-eyebrow"><span class="pv-eyebrow-dot"></span>Management</div>
    <h1>Service <em>Providers</em></h1>
    <p>Approve, review, and manage all providers on the platform</p>
  </div>

  <!-- KPIs -->
  <div class="pv-kpis">
    <div class="pv-kpi pv-kpi--gold">
      <div class="pv-kpi-val"><?= $total ?></div>
      <div class="pv-kpi-label">Total Providers</div>
    </div>
    <div class="pv-kpi pv-kpi--green">
      <div class="pv-kpi-val"><?= $approved ?></div>
      <div class="pv-kpi-label">Approved</div>
    </div>
    <div class="pv-kpi pv-kpi--yellow">
      <div class="pv-kpi-val"><?= $pending ?></div>
      <div class="pv-kpi-label">Pending Review</div>
    </div>
  </div>

  <!-- Panel -->
  <div class="pv-panel">

    <div class="pv-panel-head">
      <h2>Provider Directory</h2>
      <span class="pv-badge" id="pv-count-badge"><?= $total ?> registered</span>
    </div>

    <div class="pv-filters">
      <button class="pv-fbtn active" data-filter="all">All</button>
      <button class="pv-fbtn" data-filter="1">Approved</button>
      <button class="pv-fbtn" data-filter="0">Pending</button>
      <input class="pv-search" type="search" id="pv-search" placeholder="Search name, business…">
    </div>

    <div class="pv-table-wrap">
      <table class="pv-table" id="pv-table">
        <colgroup>
          <col class="c-provider">
          <col class="c-email">
          <col class="c-svc">
          <col class="c-joined">
          <col class="c-status">
          <col class="c-action">
        </colgroup>
        <thead>
          <tr>
            <th class="c-h-provider">Provider</th>
            <th class="c-h-email">Email</th>
            <th class="c-h-svc">Service</th>
            <th class="c-h-joined">Joined</th>
            <th class="c-h-status">Status</th>
            <th class="c-h-action">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($providers)): ?>
          <tr><td colspan="6">
            <div class="pv-empty">
              <div class="pv-empty-icon">🏪</div>
              <p>No providers registered yet.</p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($providers as $p):
            $initials   = strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1));
            $isApproved = (bool)$p['is_approved'];
            $bizLabel   = !empty($p['category_name'])
              ? $p['category_name']
              : ((!empty($p['business_name']) && strtolower(trim($p['business_name'])) !== strtolower(trim($p['first_name'].' '.$p['last_name'])))
                ? $p['business_name']
                : '');
            $search = strtolower($p['first_name'].' '.$p['last_name'].' '.($p['business_name'] ?? ''));
          ?>
          <tr data-approved="<?= (int)$p['is_approved'] ?>" data-search="<?= htmlspecialchars($search) ?>">

            <td class="c-h-provider">
              <div class="pv-cell">
                <div class="pv-av"><?= $initials ?></div>
                <div>
                  <div class="pv-name"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></div>
                  <?php if ($bizLabel): ?>
                    <div class="pv-sub"><?= htmlspecialchars($bizLabel) ?></div>
                  <?php endif ?>
                </div>
              </div>
            </td>

            <td class="c-h-email"><?= htmlspecialchars($p['email']) ?></td>

            <td class="c-h-svc"><?= htmlspecialchars($p['service_names'] ?? '—') ?></td>

            <td class="c-h-joined"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>

            <td class="c-h-status">
              <?php if ($isApproved): ?>
                <span class="pv-pill pv-pill--approved">Approved</span>
              <?php else: ?>
                <span class="pv-pill pv-pill--pending">Pending</span>
              <?php endif ?>
            </td>

            <td class="c-h-action">
              <div class="pv-actions">
                <!-- View profile -->
                <a href="<?= BASE_URL ?>admin/providers/<?= $p['id'] ?>/profile"
                   class="pv-icon-btn pv-icon-btn--view" title="View Profile">
                  <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
                <?php if (!$isApproved): ?>
                <!-- Approve -->
                <button type="button"
                  class="pv-icon-btn pv-icon-btn--approve"
                  title="Approve Provider"
                  onclick="openModal('approve', <?= $p['id'] ?>, '<?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?>')">
                  <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
                <?php endif ?>
                <?php if ($isApproved): ?>
                <!-- Suspend -->
                <button type="button"
                  class="pv-icon-btn pv-icon-btn--suspend"
                  title="Suspend Provider"
                  onclick="openModal('suspend', <?= $p['id'] ?>, '<?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?>')">
                  <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <?php endif ?>
              </div>
            </td>

          </tr>
          <?php endforeach ?>
        <?php endif ?>
        </tbody>
      </table>
    </div>

  </div>

  <div class="pv-foot">QuickBook Admin · Providers · <?= date('F j, Y') ?></div>

</div>

<!-- Confirm Modal -->
<div class="pv-modal-overlay" id="pv-modal-overlay">
  <div class="pv-modal" id="pv-modal">
    <div class="pv-modal-icon" id="pv-modal-icon"></div>
    <h3 id="pv-modal-title"></h3>
    <p id="pv-modal-body"></p>
    <div class="pv-modal-btns">
      <button class="pv-modal-btn pv-modal-btn--cancel" onclick="closeModal()">Cancel</button>
      <button class="pv-modal-btn" id="pv-modal-confirm" onclick="submitAction()"></button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="pv-toast" id="pv-toast">
  <span class="pv-toast-icon" id="pv-toast-icon"></span>
  <span id="pv-toast-msg"></span>
</div>

<script>
let currentFilter = 'all';
let pendingAction = null;
let pendingId     = null;

// ── Modal ──────────────────────────────────────────────────
function openModal(action, id, name) {
  pendingAction = action;
  pendingId     = id;
  const overlay = document.getElementById('pv-modal-overlay');
  const title   = document.getElementById('pv-modal-title');
  const body    = document.getElementById('pv-modal-body');
  const confirm = document.getElementById('pv-modal-confirm');

  if (action === 'approve') {
    title.textContent   = 'Approve Provider';
    body.textContent    = `Are you sure you want to approve ${name}? They will be able to offer services on the platform.`;
    confirm.textContent = 'Approve';
    confirm.className   = 'pv-modal-btn pv-modal-btn--confirm-approve';
  } else {
    title.textContent   = 'Suspend Provider';
    body.textContent    = `Are you sure you want to suspend ${name}? They will lose access to the platform.`;
    confirm.textContent = 'Suspend';
    confirm.className   = 'pv-modal-btn pv-modal-btn--confirm-suspend';
  }
  overlay.classList.add('show');
}

function closeModal() {
  document.getElementById('pv-modal-overlay').classList.remove('show');
  pendingAction = null;
  pendingId     = null;
}

function submitAction() {
  if (!pendingAction || !pendingId) return;
  const form  = document.createElement('form');
  form.method = 'POST';
  form.action = `<?= BASE_URL ?>admin/providers/${pendingId}`;
  const input = document.createElement('input');
  input.type  = 'hidden';
  input.name  = 'action';
  input.value = pendingAction;
  form.appendChild(input);
  document.body.appendChild(form);
  showToast(
    pendingAction === 'approve' ? 'success' : 'danger',
    pendingAction === 'approve' ? '✅  Provider approved successfully.' : '⛔  Provider suspended.'
  );
  closeModal();
  setTimeout(() => form.submit(), 900);
}

document.getElementById('pv-modal-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ── Toast ──────────────────────────────────────────────────
function showToast(type, msg) {
  const toast = document.getElementById('pv-toast');
  document.getElementById('pv-toast-msg').textContent = msg;
  toast.className = `pv-toast pv-toast--${type}`;
  requestAnimationFrame(() => toast.classList.add('show'));
  setTimeout(() => toast.classList.remove('show'), 3200);
}

// ── Filters ────────────────────────────────────────────────
document.querySelectorAll('.pv-fbtn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.pv-fbtn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentFilter = btn.dataset.filter;
    applyFilters();
  });
});

document.getElementById('pv-search').addEventListener('input', applyFilters);

function applyFilters() {
  const q = document.getElementById('pv-search').value.toLowerCase().trim();
  let visibleCount = 0;

  document.querySelectorAll('#pv-table tbody tr[data-approved]').forEach(row => {
    const okFilter = currentFilter === 'all' || row.dataset.approved === currentFilter;
    const okSearch = !q || row.dataset.search.split(' ').some(word => word.startsWith(q));
    const show     = okFilter && okSearch;
    row.style.display = show ? '' : 'none';
    if (show) visibleCount++;
  });

  const prev = document.getElementById('pv-empty-state');
  if (prev) prev.remove();

  const badge = document.getElementById('pv-count-badge');
  if (badge) {
    const label = currentFilter === '1' ? 'approved' : currentFilter === '0' ? 'pending' : 'registered';
    badge.textContent = visibleCount + ' ' + label;
  }

  if (visibleCount === 0) {
    let icon = '🏪', msg = 'No providers found.';
    if      (currentFilter === '1') { icon = '✅'; msg = 'No approved providers yet.'; }
    else if (currentFilter === '0') { icon = '⏳'; msg = 'No pending providers.'; }
    else if (q)                     { icon = '🔍'; msg = 'No providers match your search.'; }

    const tbody = document.querySelector('#pv-table tbody');
    const tr    = document.createElement('tr');
    tr.id       = 'pv-empty-state';
    tr.innerHTML = `<td colspan="6">
      <div class="pv-empty">
        <div class="pv-empty-icon">${icon}</div>
        <p>${msg}</p>
      </div>
    </td>`;
    tbody.appendChild(tr);
  }
}
</script>
</body>
</html>