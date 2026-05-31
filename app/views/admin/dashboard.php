<?php
// app/views/admin/dashboard.php
date_default_timezone_set('Asia/Manila');
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
$today     = date('F j, Y');
$dayName   = date('l, F j, Y');

function admStatusPill(string $s): string {
    $cls   = in_array($s, ['pending','confirmed','completed','cancelled','in_progress','rescheduled']) ? $s : 'default';
    $label = ucfirst(str_replace('_', ' ', $s));
    return "<span class='adm-pill adm-pill--{$cls}'>{$label}</span>";
}
function admRolePill(string $r): string {
    $map = ['admin'=>'adm-role--admin','provider'=>'adm-role--provider','customer'=>'adm-role--customer'];
    $cls = $map[$r] ?? '';
    return "<span class='adm-role {$cls}'>" . htmlspecialchars(ucfirst($r)) . "</span>";
}

$hour  = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

/* ── Unique hero stats ── */
require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

// Completion rate
$stComp = $db->query("SELECT ROUND(SUM(status='completed')/COUNT(*)*100,1) FROM tbl_bookings");
$completionRate = (float)$stComp->fetchColumn();

// New users this month
$stNew = $db->query("SELECT COUNT(*) FROM tbl_users WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
$newThisMonth = (int)$stNew->fetchColumn();

/* ── Analytics: new users per month (last 6 months) ── */
$stGrowth = $db->query("
    SELECT DATE_FORMAT(created_at,'%b') AS mo,
           DATE_FORMAT(created_at,'%Y-%m') AS mo_key,
           SUM(role='customer')  AS customers,
           SUM(role='provider')  AS providers,
           COUNT(*)              AS total
    FROM tbl_users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mo_key, mo ORDER BY mo_key ASC
");
$growthData    = $stGrowth->fetchAll();
$growthLabels  = array_column($growthData, 'mo');
$growthCust    = array_map(fn($r) => (int)$r['customers'], $growthData);
$growthProv    = array_map(fn($r) => (int)$r['providers'], $growthData);
$growthTotal   = array_map(fn($r) => (int)$r['total'],     $growthData);

/* ── Bookings per month (last 6 months) ── */
$stBkMonthly = $db->query("
    SELECT DATE_FORMAT(created_at,'%b') AS mo,
           DATE_FORMAT(created_at,'%Y-%m') AS mo_key,
           COUNT(*) AS cnt
    FROM tbl_bookings
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mo_key, mo ORDER BY mo_key ASC
");
$bkMonthly  = $stBkMonthly->fetchAll();
$bkLabels   = array_column($bkMonthly, 'mo');
$bkCounts   = array_map(fn($r) => (int)$r['cnt'], $bkMonthly);

/* ── Role breakdown ── */
$stRoles = $db->query("SELECT role, COUNT(*) AS cnt FROM tbl_users GROUP BY role");
$roleData = $stRoles->fetchAll(PDO::FETCH_KEY_PAIR);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — QuickBook Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_dashboard.css">
</head>
<body>
<div class="grain"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('dashboard'); ?>

<!-- ══════════════ HERO ══════════════ -->
<div class="admin-hero">
  <div class="admin-hero-overlay"></div>

  <div class="admin-hero-inner">
    <div>
      <div class="admin-hero-eyebrow">
        <span class="admin-dot-pulse"></span><?= $greet ?>
      </div>
      <h1 class="admin-hero-name"><?= $adminName ?> 👋</h1>
      <div class="admin-hero-date"><?= $dayName ?></div>
      <div class="admin-hero-meta">
        <span class="admin-status-badge">
          <span class="admin-status-dot"></span>Administrator
        </span>
      </div>
    </div>
    <?php if ($pendingBookings > 0): ?>
    <a href="<?= BASE_URL ?>admin/bookings" class="admin-pending-chip">
      <span class="admin-pending-dot"></span>
      <?= $pendingBookings ?> pending booking<?= $pendingBookings !== 1 ? 's' : '' ?>
    </a>
    <?php endif ?>
  </div>

  <!-- Stat strip -->
  <div class="admin-hero-stats">
    <div class="admin-hs-item">
      <div class="admin-hs-val accent"><?= number_format($totalUsers) ?></div>
      <div class="admin-hs-label">Total Users</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val"><?= number_format($totalBookings) ?></div>
      <div class="admin-hs-label">All Bookings</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val yellow"><?= $pendingBookings ?></div>
      <div class="admin-hs-label">Pending</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val green">₱<?= number_format($totalRevenue, 0) ?></div>
      <div class="admin-hs-label">Revenue</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val accent"><?= $completionRate ?>%</div>
      <div class="admin-hs-label">Completion Rate</div>
    </div>
    <div class="admin-hs-div"></div>
    <div class="admin-hs-item">
      <div class="admin-hs-val blue">+<?= $newThisMonth ?></div>
      <div class="admin-hs-label">New This Month</div>
    </div>
  </div>
</div><!-- /admin-hero -->

<!-- ══════════════ PAGE ══════════════ -->
<div class="admin-pv-page">

  <!-- Main layout -->
  <div class="admin-layout">

    <!-- Left: main content -->
    <div class="admin-main">

      <!-- ── Analytics Section ── -->
      <div class="adm-analytics-section">

        <div class="adm-analytics-row">

          <!-- User Growth Line Chart -->
          <div class="admin-card adm-chart-card adm-chart-card--wide">
            <div class="admin-card-head">
              <div>
                <div class="adm-chart-card-eyebrow">Last 6 months</div>
                <h2>User Growth</h2>
              </div>
              <div class="adm-chart-legend-inline">
                <span class="adm-legend-dot" style="background:#C9A84C"></span>Customers
                <span class="adm-legend-dot" style="background:#2563EB;margin-left:.75rem"></span>Providers
              </div>
            </div>
            <div class="adm-chart-body">
              <canvas id="usersChart"></canvas>
            </div>
          </div>

          <!-- Roles Doughnut -->
          <div class="admin-card adm-chart-card adm-chart-card--narrow">
            <div class="admin-card-head">
              <div>
                <div class="adm-chart-card-eyebrow">All time</div>
                <h2>User Roles</h2>
              </div>
            </div>
            <div class="adm-chart-body adm-chart-body--donut">
              <div class="adm-donut-wrap">
                <canvas id="rolesChart"></canvas>
                <div class="adm-donut-center">
                  <span class="adm-donut-total"><?= $totalUsers ?></span>
                  <span class="adm-donut-label">Total</span>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /row 1 -->

        <!-- Row 2: Bookings Bar Chart full width -->
        <div class="admin-card adm-chart-card">
          <div class="admin-card-head">
            <div>
              <div class="adm-chart-card-eyebrow">Last 6 months</div>
              <h2>Bookings Overview</h2>
            </div>
            <div class="adm-chart-legend-inline">
              <span class="adm-legend-dot" style="background:#16A34A"></span>Bookings created
            </div>
          </div>
          <div class="adm-chart-body">
            <canvas id="bookingsChart"></canvas>
          </div>
        </div>

      </div><!-- /adm-analytics-section -->

      <!-- Recent Bookings -->
      <div class="admin-card">
        <div class="admin-card-head">
          <h2>Recent Bookings</h2>
          <a href="<?= BASE_URL ?>admin/bookings" class="admin-card-link">View all →</a>
        </div>
        <?php if (empty($recentBookings)): ?>
          <div class="adm-empty">
            <div class="adm-empty-icon"><i class="fa-solid fa-clipboard-list"></i></div>
            <p>No bookings yet.</p>
          </div>
        <?php else: ?>
          <?php foreach (array_slice($recentBookings, 0, 5) as $b): ?>
            <div class="adm-booking-row">
              <div class="adm-booking-av"><i class="fa-solid fa-calendar-check"></i></div>
              <div class="adm-booking-info">
                <div class="adm-booking-service"><?= htmlspecialchars($b['service_name']) ?></div>
                <div class="adm-booking-meta">
                  <?= htmlspecialchars($b['prov_first'].' '.$b['prov_last']) ?>
                </div>
              </div>
              <div class="adm-booking-right">
                <?= admStatusPill($b['status']) ?>
              </div>
            </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>

      <!-- Newest Users -->
      <div class="admin-card">
        <div class="admin-card-head">
          <h2>Newest Users</h2>
          <a href="<?= BASE_URL ?>admin/users" class="admin-card-link">View all →</a>
        </div>
        <?php if (empty($newUsers)): ?>
          <div class="adm-empty">
            <div class="adm-empty-icon">👥</div>
            <p>No users yet.</p>
          </div>
        <?php else: ?>

         <?php foreach (array_slice($newUsers, 0, 3) as $u):
    $init  = strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1));
    $avcls = $u['role'] === 'admin' ? 'adm-av-red' : ($u['role'] === 'provider' ? 'adm-av-gold' : 'adm-av-green');

    if ($u['role'] === 'provider' && !empty($u['provider_profile_id'])) {
        $profileUrl = BASE_URL . 'providers/' . $u['provider_profile_id'];
    } elseif ($u['role'] === 'customer') {
        $profileUrl = BASE_URL . 'admin/users';
    } else {
        $profileUrl = null;
    }
  ?>
    <?php if ($profileUrl): ?>
    <a href="<?= $profileUrl ?>" class="adm-user-row" style="text-decoration:none;cursor:pointer;" title="View profile">
    <?php else: ?>
    <div class="adm-user-row">
    <?php endif ?>

      <div class="adm-av <?= $avcls ?>"><?= $init ?></div>
      <div style="flex:1;min-width:0">
        <div class="adm-user-name"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></div>
        <div class="adm-user-email"><?= htmlspecialchars($u['email']) ?></div>
      </div>
      <?= admRolePill($u['role']) ?>

    <?php if ($profileUrl): ?></a><?php else: ?></div><?php endif ?>
  <?php endforeach ?>

        <?php endif ?>
      </div>

    </div><!-- /admin-main -->

    <!-- Right: sidebar -->
    <div class="admin-sidebar">

      <!-- Quick Actions -->
      <div class="admin-card">
        <div class="admin-card-head"><h2>Quick Actions</h2></div>
        <div class="adm-actions">
          <a href="<?= BASE_URL ?>admin/bookings" class="adm-action is-primary">
            <div class="adm-action-ico"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="adm-action-txt">
              <span class="adm-action-title">All Bookings</span>
              <span class="adm-action-sub">Review &amp; manage</span>
            </div>
          </a>
          <a href="<?= BASE_URL ?>admin/providers" class="adm-action">
            <div class="adm-action-ico"><i class="fa-solid fa-store"></i></div>
            <div class="adm-action-txt">
              <span class="adm-action-title">Manage Providers</span>
              <span class="adm-action-sub">Approve or suspend listings</span>
            </div>
            <span class="adm-action-chevron">›</span>
          </a>
          <a href="<?= BASE_URL ?>admin/users" class="adm-action">
            <div class="adm-action-ico"><i class="fa-solid fa-users"></i></div>
            <div class="adm-action-txt">
              <span class="adm-action-title">Manage Users</span>
              <span class="adm-action-sub">Browse all accounts</span>
            </div>
            <span class="adm-action-chevron">›</span>
          </a>
          <a href="<?= BASE_URL ?>admin/reports" class="adm-action">
            <div class="adm-action-ico"><i class="fa-solid fa-chart-bar"></i></div>
            <div class="adm-action-txt">
              <span class="adm-action-title">Reports &amp; Analytics</span>
              <span class="adm-action-sub">Revenue and performance</span>
            </div>
            <span class="adm-action-chevron">›</span>
          </a>
        </div>
      </div>

      <!-- Platform Snapshot -->
      <div class="admin-card">
        <div class="admin-card-head"><h2>Platform Snapshot</h2></div>
        <div class="adm-snap-item">
          <div class="adm-snap-inline">
            <span class="adm-snap-val"><?= number_format($totalUsers) ?></span>
            <span class="adm-snap-lbl">Registered users</span>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-inline">
            <span class="adm-snap-val"><?= number_format($totalProviders) ?></span>
            <span class="adm-snap-lbl">Active providers</span>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-inline">
            <span class="adm-snap-val"><?= number_format($totalCustomers) ?></span>
            <span class="adm-snap-lbl">Customers</span>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-inline">
            <span class="adm-snap-val"><?= number_format($totalBookings) ?></span>
            <span class="adm-snap-lbl">Total bookings</span>
          </div>
        </div>
        <div class="adm-snap-item">
          <div class="adm-snap-inline">
            <span class="adm-snap-val revenue">₱<?= number_format($totalRevenue, 0) ?></span>
            <span class="adm-snap-lbl">Total revenue</span>
          </div>
        </div>
      </div>

    </div><!-- /admin-sidebar -->
  </div><!-- /admin-layout -->

  <div class="adm-footer">QuickBook Admin · Dashboard · <?= $today ?></div>
</div><!-- /admin-pv-page -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
(function() {
  const isDark    = () => document.documentElement.getAttribute('data-theme') === 'dark';
  const gridColor = () => isDark() ? 'rgba(255,255,255,.06)' : 'rgba(201,168,76,.12)';
  const tickColor = () => isDark() ? 'rgba(237,227,204,.35)' : 'rgba(28,23,16,.38)';
  const fontMono  = () => ({ family:"'DM Mono',monospace", size:10 });
  const tooltipStyle = () => ({
    backgroundColor: isDark() ? '#1a2235' : '#ffffff',
    titleColor:  isDark() ? '#EDE3CC' : '#1C1710',
    bodyColor:   isDark() ? 'rgba(237,227,204,.65)' : 'rgba(28,23,16,.60)',
    borderColor: 'rgba(201,168,76,.35)', borderWidth:1,
    padding:10, cornerRadius:8,
  });
  const baseScales = () => ({
    x: { grid:{ color:gridColor(), drawBorder:false }, ticks:{ color:tickColor(), font:fontMono() }, border:{ display:false } },
    y: { grid:{ color:gridColor(), drawBorder:false }, ticks:{ color:tickColor(), font:fontMono(), maxTicksLimit:5 }, border:{ display:false }, beginAtZero:true }
  });

  // ── 1. User Growth Line Chart ──
  const uCtx   = document.getElementById('usersChart').getContext('2d');
  const uLabels = <?= json_encode(array_values($growthLabels)) ?>;
  const uCust   = <?= json_encode(array_values($growthCust)) ?>;
  const uProv   = <?= json_encode(array_values($growthProv)) ?>;

  const goldGrad = uCtx.createLinearGradient(0,0,0,180);
  goldGrad.addColorStop(0,   'rgba(201,168,76,.45)');
  goldGrad.addColorStop(0.6, 'rgba(201,168,76,.10)');
  goldGrad.addColorStop(1,   'rgba(201,168,76,.00)');

  const blueGrad = uCtx.createLinearGradient(0,0,0,180);
  blueGrad.addColorStop(0,   'rgba(37,99,235,.35)');
  blueGrad.addColorStop(0.6, 'rgba(37,99,235,.08)');
  blueGrad.addColorStop(1,   'rgba(37,99,235,.00)');

  const usersChart = new Chart(uCtx, {
    type: 'line',
    data: {
      labels: uLabels,
      datasets: [
        { label:'Customers', data:uCust, borderColor:'#C9A84C', backgroundColor:goldGrad, borderWidth:2.5, tension:0.42, fill:true, pointRadius:4, pointBackgroundColor:'#C9A84C', pointBorderColor:'#fff', pointBorderWidth:2 },
        { label:'Providers',  data:uProv, borderColor:'#2563EB', backgroundColor:blueGrad, borderWidth:2.5, tension:0.42, fill:true, pointRadius:4, pointBackgroundColor:'#2563EB', pointBorderColor:'#fff', pointBorderWidth:2 }
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      interaction:{ mode:'index', intersect:false },
      plugins:{ legend:{ display:false }, tooltip: { enabled: false } },
      scales: baseScales()
    }
  });

  // ── 2. Bookings Bar Chart ──
  const bCtx    = document.getElementById('bookingsChart').getContext('2d');
  const bLabels = <?= json_encode(array_values($bkLabels)) ?>;
  const bCounts = <?= json_encode(array_values($bkCounts)) ?>;

  const barGrad = bCtx.createLinearGradient(0,0,0,180);
  barGrad.addColorStop(0, 'rgba(22,163,74,.80)');
  barGrad.addColorStop(1, 'rgba(22,163,74,.25)');

  const bookingsChart = new Chart(bCtx, {
    type: 'bar',
    data: {
      labels: bLabels,
      datasets:[{ label:'Bookings', data:bCounts, backgroundColor:barGrad, borderColor:'#16A34A', borderWidth:1.5, borderRadius:8, borderSkipped:false }]
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{ display:false }, tooltip: tooltipStyle() },
      scales: baseScales()
    }
  });

  // ── 3. Roles Doughnut ──
  Chart.Tooltip.positioners.rightOfDonut = function(elements, eventPos) {
    const chart = this.chart;
    const cx = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
    const cy = chart.chartArea.top  + (chart.chartArea.bottom - chart.chartArea.top)  / 2;
    const r  = (chart.chartArea.right - chart.chartArea.left) / 2 + 14;
    const dx = eventPos.x - cx;
    const dy = eventPos.y - cy;
    const angle = Math.atan2(dy, dx);
    return { x: cx + Math.cos(angle) * r, y: cy + Math.sin(angle) * r };
  };

  const rCtx = document.getElementById('rolesChart').getContext('2d');
  new Chart(rCtx, {
    type: 'doughnut',
    data: {
      labels:['Customers','Providers','Admins'],
      datasets:[{
        data:[
          <?= (int)($roleData['customer'] ?? 0) ?>,
          <?= (int)($roleData['provider'] ?? 0) ?>,
          <?= (int)($roleData['admin']    ?? 0) ?>
        ],
        backgroundColor:['rgba(201,168,76,.80)','rgba(37,99,235,.80)','rgba(220,38,38,.80)'],
        borderColor:    ['#C9A84C','#2563EB','#DC2626'],
        borderWidth:2, hoverOffset:6,
      }]
    },
    options:{
      responsive:false, cutout:'70%',
      plugins:{
        legend:{ display:false },
        tooltip: { ...tooltipStyle(), position: 'rightOfDonut' }
      }
    }
  });

  // ── Redraw on theme toggle ──
  const darkBtn = document.getElementById('admDarkToggle');
  if (darkBtn) {
    darkBtn.addEventListener('click', () => {
      setTimeout(() => {
        [usersChart, bookingsChart].forEach(ch => {
          ch.options.scales.x.grid.color  = gridColor();
          ch.options.scales.x.ticks.color = tickColor();
          ch.options.scales.y.grid.color  = gridColor();
          ch.options.scales.y.ticks.color = tickColor();
          ch.update();
        });
      }, 60);
    });
  }
})();
</script>
</body>
</html>