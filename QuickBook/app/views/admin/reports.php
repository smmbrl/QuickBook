<?php
// app/views/admin/reports.php
require_once __DIR__ . '/../../config/../models/../models/../../config/database.php';
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

$statusColors = [
  'pending'     => ['color'=>'var(--yellow)','fill'=>'rgba(251,191,36,.5)'],
  'confirmed'   => ['color'=>'#4ADE80',      'fill'=>'rgba(34,197,94,.5)'],
  'completed'   => ['color'=>'#7DD3FC',      'fill'=>'rgba(56,189,248,.5)'],
  'cancelled'   => ['color'=>'#FB7185',      'fill'=>'rgba(244,63,94,.5)'],
  'in_progress' => ['color'=>'var(--gold)',  'fill'=>'rgba(201,168,76,.5)'],
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
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_reports.css">
</head>
<body>
<div class="grain"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('reports'); ?>

<div class="admin-page">
<div class="content">

  <div class="page-greeting anim-1">
    <div>
      <div class="eyebrow"><span class="eyebrow-dot"></span>Analytics</div>
      <h1>Platform <em>Reports</em></h1>
      <p>Revenue and performance insights for <?= date('F Y') ?></p>
    </div>
  </div>

  <!-- Summary stats -->
  <div class="stats-grid anim-2">
    <div class="stat-card gold">
      <div class="stat-icon-row">
        <div class="stat-icon">💰</div>
        <span class="stat-trend up">Revenue</span>
      </div>
      <div class="stat-value" style="font-size:1.6rem">₱<?= number_format($totalRevenue, 0) ?></div>
      <div class="stat-label">Total Revenue</div>
      <div class="stat-sub">From completed bookings</div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon-row">
        <div class="stat-icon">📋</div>
        <span class="stat-trend neutral">All time</span>
      </div>
      <div class="stat-value"><?= $totalBookings ?></div>
      <div class="stat-label">Total Bookings</div>
      <div class="stat-sub">Platform-wide orders</div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon-row">
        <div class="stat-icon">👥</div>
        <span class="stat-trend up">+<?= $newThisMonth ?> this month</span>
      </div>
      <div class="stat-value"><?= $totalUsers ?></div>
      <div class="stat-label">Total Users</div>
      <div class="stat-sub"><?= $newThisMonth ?> joined this month</div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon-row">
        <div class="stat-icon">✅</div>
        <span class="stat-trend up">Completion</span>
      </div>
      <div class="stat-value"><?= $completionRate ?>%</div>
      <div class="stat-label">Completion Rate</div>
      <div class="stat-sub"><?= $statusBreakdown['completed'] ?? 0 ?> orders completed</div>
    </div>
  </div>

  <div class="report-grid anim-3">

    <!-- Revenue chart -->
    <div class="panel span2">
      <div class="panel-header">
        <h2>Monthly Revenue — Last 6 Months</h2>
        <span style="font-family:var(--font-mono);font-size:.6rem;color:var(--faint)">Completed bookings only</span>
      </div>
      <div class="col-chart">
        <?php if (empty($revenueByMonth)): ?>
          <div class="empty-state"><div class="empty-icon">📉</div><p>No completed bookings yet.</p></div>
        <?php else: ?>
          <div class="col-row">
            <?php foreach ($revenueByMonth as $row):
              $h = max(5, round(($row['revenue'] / $maxRevenue) * 100));
            ?>
              <div class="col-item">
                <div class="col-bar" style="height:<?= $h ?>%">
                  <span class="col-bar-tip">₱<?= number_format($row['revenue'],2) ?><br><?= $row['bookings'] ?> bookings</span>
                </div>
                <div class="col-lbl"><?= htmlspecialchars($row['mo']) ?></div>
              </div>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>
    </div>

    <!-- Top services -->
    <div class="panel">
      <div class="panel-header"><h2>Top Services</h2></div>
      <?php if (empty($topServices)): ?>
        <div class="empty-state"><p>No service data.</p></div>
      <?php else: ?>
        <div class="bar-chart">
          <?php foreach ($topServices as $svc): ?>
            <div class="bar-group">
              <div class="bar-label" title="<?= htmlspecialchars($svc['name']) ?>"><?= htmlspecialchars(mb_strimwidth($svc['name'],0,13,'…')) ?></div>
              <div class="bar-track">
                <div class="bar-fill" style="width:<?= round($svc['bookings']/$maxBookings*100) ?>%"><?= (int)$svc['bookings'] ?></div>
              </div>
              <div class="bar-end">₱<?= number_format($svc['revenue'],0) ?></div>
            </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>

    <!-- Booking status breakdown -->
    <div class="panel">
      <div class="panel-header"><h2>Booking Status Breakdown</h2></div>
      <?php if (empty($statusBreakdown)): ?>
        <div class="empty-state"><p>No booking data.</p></div>
      <?php else: ?>
        <div class="status-breakdown">
          <?php foreach ($statusColors as $st => $c):
            $cnt = $statusBreakdown[$st] ?? 0;
            $pct = $totalBookings > 0 ? round($cnt/$totalBookings*100,1) : 0;
          ?>
            <div class="sb-row">
              <div class="sb-name"><?= str_replace('_',' ',$st) ?></div>
              <div class="sb-bar-wrap">
                <div class="sb-bar-fill" style="width:<?= $pct ?>%;background:<?= $c['fill'] ?>"></div>
              </div>
              <div class="sb-cnt" style="color:<?= $c['color'] ?>"><?= $cnt ?></div>
              <div class="sb-pct"><?= $pct ?>%</div>
            </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>

    <!-- Top providers leaderboard -->
    <div class="panel span2">
      <div class="panel-header">
        <h2>Top Providers by Revenue</h2>
        <a href="<?= BASE_URL ?>admin/providers" class="panel-link">View all →</a>
      </div>
      <?php if (empty($topProviders)): ?>
        <div class="empty-state"><p>No provider data yet.</p></div>
      <?php else: ?>
        <?php foreach ($topProviders as $i => $p): ?>
          <div class="prov-leaderboard-row">
            <div class="plr-rank"><?= $i + 1 ?></div>
            <div class="av av-gold"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
            <div class="plr-info">
              <div class="plr-name"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></div>
              <?php if (!empty($p['business_name'])): ?>
                <div class="plr-biz"><?= htmlspecialchars($p['business_name']) ?></div>
              <?php endif ?>
            </div>
            <div class="plr-stats">
              <div class="plr-rev">₱<?= number_format($p['revenue'],2) ?></div>
              <div class="plr-bkn"><?= (int)$p['bookings'] ?> bookings</div>
            </div>
          </div>
        <?php endforeach ?>
      <?php endif ?>
    </div>

  </div><!-- /report-grid -->
</div>
</div>
</body>
</html>