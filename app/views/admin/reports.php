<?php
// app/views/admin/reports.php
require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

$revenueByMonth = $db->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS mo,
           DATE_FORMAT(created_at,'%Y-%m')  AS sort_key,
           SUM(total_amount) AS revenue, COUNT(*) AS bookings
    FROM tbl_bookings WHERE status='completed'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mo, sort_key ORDER BY sort_key ASC
")->fetchAll();

$topServices = $db->query("
    SELECT s.name, COUNT(b.id) AS bookings, COALESCE(SUM(b.total_amount),0) AS revenue
    FROM tbl_services s LEFT JOIN tbl_bookings b ON b.service_id=s.id AND b.status='completed'
    GROUP BY s.id ORDER BY bookings DESC LIMIT 8
")->fetchAll();

$topProviders = $db->query("
    SELECT u.first_name, u.last_name, pp.business_name,
           COUNT(b.id) AS bookings, COALESCE(SUM(b.total_amount),0) AS revenue
    FROM tbl_provider_profiles pp JOIN tbl_users u ON u.id=pp.user_id
    LEFT JOIN tbl_bookings b ON b.provider_id=pp.id AND b.status='completed'
    GROUP BY pp.id ORDER BY revenue DESC LIMIT 8
")->fetchAll();

$statusBreakdown = $db->query("SELECT status, COUNT(*) AS cnt FROM tbl_bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalBookings   = array_sum($statusBreakdown);
$totalRevenue    = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings WHERE status='completed'")->fetchColumn();
$totalUsers      = (int)$db->query("SELECT COUNT(*) FROM tbl_users")->fetchColumn();
$newThisMonth    = (int)$db->query("SELECT COUNT(*) FROM tbl_users WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

$maxRevenue  = max(1, array_reduce($revenueByMonth, fn($c,$r) => max($c,(float)$r['revenue']), 0));
$maxBookings = max(1, ...array_column($topServices,'bookings') ?: [1]);
$completionRate = $totalBookings > 0 ? round(($statusBreakdown['completed']??0)/$totalBookings*100,1) : 0;

$statusConfig = [
  'pending'     => ['dot'=>'#D97706', 'fill'=>'rgba(217,119,6,.50)',  'color'=>'#D97706'],
  'confirmed'   => ['dot'=>'#16A34A', 'fill'=>'rgba(22,163,74,.50)',  'color'=>'#16A34A'],
  'completed'   => ['dot'=>'#2563EB', 'fill'=>'rgba(37,99,235,.50)',  'color'=>'#2563EB'],
  'cancelled'   => ['dot'=>'#DC2626', 'fill'=>'rgba(220,38,38,.50)',  'color'=>'#DC2626'],
  'in_progress' => ['dot'=>'#C9A84C', 'fill'=>'rgba(201,168,76,.55)', 'color'=>'#A88A38'],
];

// SVG icons for stat cards (no emojis)
$icons = [
  'revenue'    => '<svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
  'bookings'   => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  'users'      => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'completion' => '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reports — QuickBook Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_reports.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
</head>
<body>
<div class="grain"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('reports'); ?>

<div class="admin-page">
<div class="content">

  <!-- Page header -->
  <div class="page-greeting anim-1">
    <div>
      <div class="eyebrow"><span class="eyebrow-dot"></span>Analytics</div>
      <h1>Platform <em>Reports</em></h1>
      <p>Revenue and performance insights for <?= date('F Y') ?></p>
    </div>
    <div class="rpt-btn-group">
      <button class="rpt-export-btn pdf" id="downloadPdfBtn">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="13" x2="12" y2="19"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
        Download PDF
      </button>
      <button class="rpt-export-btn print" onclick="window.print()">
        <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print Report
      </button>
    </div>
  </div>

  <!-- KPI stat cards -->
  <div class="stats-grid anim-2">

    <div class="stat-card gold">
      <div class="stat-icon-row">
        <div class="stat-icon"><?= $icons['revenue'] ?></div>
        <span class="stat-trend up">Completed</span>
      </div>
      <div class="stat-value">₱<?= number_format($totalRevenue, 0) ?></div>
      <div class="stat-label">Total Revenue</div>
      <div class="stat-sub">From completed bookings only</div>
    </div>

    <div class="stat-card blue">
      <div class="stat-icon-row">
        <div class="stat-icon"><?= $icons['bookings'] ?></div>
        <span class="stat-trend neutral">All time</span>
      </div>
      <div class="stat-value"><?= number_format($totalBookings) ?></div>
      <div class="stat-label">Total Bookings</div>
      <div class="stat-sub">Platform-wide orders</div>
    </div>

    <div class="stat-card green">
      <div class="stat-icon-row">
        <div class="stat-icon"><?= $icons['users'] ?></div>
        <span class="stat-trend up">+<?= $newThisMonth ?> this month</span>
      </div>
      <div class="stat-value"><?= number_format($totalUsers) ?></div>
      <div class="stat-label">Total Users</div>
      <div class="stat-sub"><?= $newThisMonth ?> joined this month</div>
    </div>

    <div class="stat-card purple">
      <div class="stat-icon-row">
        <div class="stat-icon"><?= $icons['completion'] ?></div>
        <span class="stat-trend up">Rate</span>
      </div>
      <div class="stat-value"><?= $completionRate ?>%</div>
      <div class="stat-label">Completion Rate</div>
      <div class="stat-sub"><?= number_format($statusBreakdown['completed'] ?? 0) ?> orders completed</div>
    </div>

  </div><!-- /stats-grid -->

  <div class="report-grid anim-3">

    <!-- ── Monthly Revenue Chart ── -->
    <div class="panel span2">
      <div class="panel-header">
        <h2>Monthly Revenue — Last 6 Months</h2>
        <span class="panel-header-meta">Completed bookings only</span>
      </div>
      <div class="line-chart-wrap">
        <?php if (empty($revenueByMonth)): ?>
          <div class="empty-state">
            <p>No completed bookings yet.</p>
          </div>
        <?php else: ?>
          <canvas id="revenueChart" class="revenue-chart"></canvas>
        <?php endif ?>
      </div>
    </div>

    <!-- ── Top Services ── -->
    <div class="panel">
      <div class="panel-header">
        <h2>Top Services</h2>
        <span class="panel-header-meta">By booking count</span>
      </div>
      <?php if (empty($topServices)): ?>
        <div class="empty-state"><p>No service data yet.</p></div>
      <?php else: ?>
        <div class="bar-chart">
          <?php foreach ($topServices as $i => $svc): ?>
            <div class="bar-group">
              <div class="bar-rank"><?= $i + 1 ?></div>
              <div class="bar-label" title="<?= htmlspecialchars($svc['name']) ?>">
                <?= htmlspecialchars(mb_strimwidth($svc['name'], 0, 14, '…')) ?>
              </div>
              <div class="bar-track">
                <div class="bar-fill" style="width:<?= round($svc['bookings'] / $maxBookings * 100) ?>%">
                  <?= (int)$svc['bookings'] ?>
                </div>
              </div>
              <div class="bar-end">₱<?= number_format($svc['revenue'], 0) ?></div>
            </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>

    <!-- ── Booking Status Breakdown ── -->
    <div class="panel">
      <div class="panel-header">
        <h2>Booking Status Breakdown</h2>
        <span class="panel-header-meta"><?= $totalBookings ?> total</span>
      </div>
      <?php if (empty($statusBreakdown)): ?>
        <div class="empty-state"><p>No booking data yet.</p></div>
      <?php else: ?>
        <div class="status-breakdown">
          <?php foreach ($statusConfig as $st => $cfg):
            $cnt = $statusBreakdown[$st] ?? 0;
            $pct = $totalBookings > 0 ? round($cnt / $totalBookings * 100, 1) : 0;
          ?>
            <div class="sb-row">
              <div class="sb-dot" style="background:<?= $cfg['dot'] ?>"></div>
              <div class="sb-name"><?= str_replace('_', ' ', $st) ?></div>
              <div class="sb-bar-wrap">
                <div class="sb-bar-fill" style="width:<?= $pct ?>%;background:<?= $cfg['fill'] ?>"></div>
              </div>
              <div class="sb-cnt" style="color:<?= $cfg['color'] ?>"><?= $cnt ?></div>
              <div class="sb-pct"><?= $pct ?>%</div>
            </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>

    <!-- ── Top Providers Leaderboard ── -->
    <div class="panel span2">
      <div class="panel-header">
        <h2>Top Providers by Revenue</h2>
        <a href="<?= BASE_URL ?>admin/providers" class="panel-link">View all →</a>
      </div>
      <?php if (empty($topProviders)): ?>
        <div class="empty-state"><p>No provider data yet.</p></div>
      <?php else: ?>
        <?php foreach ($topProviders as $i => $p):
          $rank = $i + 1;
        ?>
          <div class="prov-leaderboard-row">
            <div class="plr-rank" data-rank="<?= $rank ?>"><?= $rank ?></div>
            <div class="av av-gold">
              <?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?>
            </div>
            <div class="plr-info">
              <div class="plr-name"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></div>
              <?php if (!empty($p['business_name'])): ?>
                <div class="plr-biz"><?= htmlspecialchars($p['business_name']) ?></div>
              <?php endif ?>
            </div>
            <div class="plr-stats">
              <div class="plr-rev">₱<?= number_format($p['revenue'], 2) ?></div>
              <div class="plr-bkn"><?= (int)$p['bookings'] ?> booking<?= $p['bookings'] != 1 ? 's' : '' ?></div>
            </div>
          </div>
        <?php endforeach ?>
      <?php endif ?>
    </div>

  </div><!-- /report-grid -->

</div><!-- /content -->
</div><!-- /admin-page -->
<script>
document.getElementById('downloadPdfBtn').addEventListener('click', function() {
  const element = document.querySelector('.admin-page');
  const opt = {
    margin: 10,
    filename: 'QuickBook-Reports-' + new Date().toISOString().split('T')[0] + '.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2 },
    jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
  };
  html2pdf().set(opt).from(element).save();
});

<?php if (!empty($revenueByMonth)): ?>
  const revenueCtx = document.getElementById('revenueChart').getContext('2d');
  const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
  const goldColor = isDark() ? '#C9A84C' : '#C9A84C';
  const textColor = isDark() ? 'rgba(237,232,220,.62)' : 'rgba(28,23,16,.65)';
  const textDimColor = isDark() ? 'rgba(237,232,220,.38)' : 'rgba(28,23,16,.42)';
  const gridColor = isDark() ? 'rgba(201,168,76,.08)' : 'rgba(201,168,76,.08)';

  new Chart(revenueCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode(array_column($revenueByMonth, 'mo')) ?>,
      datasets: [{
        label: 'Monthly Revenue',
        data: <?= json_encode(array_column($revenueByMonth, 'revenue')) ?>,
        borderColor: goldColor,
        backgroundColor: isDark() ? 'rgba(201,168,76,.10)' : 'rgba(201,168,76,.08)',
        borderWidth: 2.5,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: goldColor,
        pointBorderColor: isDark() ? '#111827' : '#fff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7,
        pointHoverBackgroundColor: '#E8C96A'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: true,
          labels: {
            font: { family: 'DM Sans, sans-serif', size: 12, weight: '500' },
            color: textColor,
            padding: 15,
            usePointStyle: true,
            pointStyle: 'circle'
          }
        },
        tooltip: {
          backgroundColor: isDark() ? 'rgba(19,29,48,.95)' : 'rgba(255,255,255,.95)',
          borderColor: goldColor,
          borderWidth: 1.5,
          titleColor: isDark() ? '#EDE8DC' : '#1C1710',
          bodyColor: isDark() ? '#EDE8DC' : '#1C1710',
          bodyFont: { family: 'DM Sans, sans-serif', size: 13, weight: '500' },
          padding: 12,
          cornerRadius: 8,
          displayColors: false,
          callbacks: {
            label: function(ctx) {
              return '₱' + ctx.parsed.y.toLocaleString('en-US', {maximumFractionDigits: 0});
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            color: textDimColor,
            font: { family: 'DM Mono, monospace', size: 11 },
            callback: function(value) {
              return '₱' + (value / 1000).toFixed(0) + 'k';
            }
          },
          grid: {
            color: gridColor,
            drawBorder: false,
            lineWidth: 1
          }
        },
        x: {
          ticks: {
            color: textDimColor,
            font: { family: 'DM Mono, monospace', size: 11 }
          },
          grid: {
            display: false,
            drawBorder: false
          }
        }
      }
    }
  });
<?php endif ?>
</script>
</body>
</html>