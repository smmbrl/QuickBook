<?php
// app/views/customer/loyalty.php

require_once __DIR__ . '/../../../config/database.php';
$db     = Database::getInstance();
$userId = (int)($_SESSION['user_id'] ?? 0);
$name   = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$email  = htmlspecialchars($_SESSION['user_email'] ?? '');
$initials = strtoupper(substr($name, 0, 2));

/* ── Loyalty Points Total ── */
$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();

/* ── Tier logic ── */
$loyaltyTier = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};

$tierConfig = [
    'Bronze' => ['icon' => '🥉', 'color' => 'bronze', 'next' => 'Silver',  'threshold' => 1000, 'perks' => ['5% discount on first monthly booking', 'Priority booking for select services', 'Birthday bonus: +50 pts']],
    'Silver' => ['icon' => '🥈', 'color' => 'silver', 'next' => 'Gold',    'threshold' => 2000, 'perks' => ['10% discount on all bookings', 'Free cancellation up to 2 hrs', 'Birthday bonus: +150 pts', 'Early access to new providers']],
    'Gold'   => ['icon' => '🥇', 'color' => 'gold',   'next' => null,      'threshold' => null, 'perks' => ['15% discount on all bookings', 'Free cancellation anytime', 'Birthday bonus: +500 pts', 'Dedicated support line', 'Exclusive Gold-only services']],
];
$tier        = $tierConfig[$loyaltyTier];
$nextTier    = $tier['next'];
$nextThr     = $tier['threshold'];
$ptsToNext   = $nextThr ? max(0, $nextThr - $loyaltyPoints) : 0;

// progress within current tier
$tierFloor = match($loyaltyTier) { 'Gold' => 2000, 'Silver' => 1000, default => 0 };
$tierCeil  = $nextThr ?? ($tierFloor + 1000);
$progress  = min(100, round(($loyaltyPoints - $tierFloor) / ($tierCeil - $tierFloor) * 100));

/* ── Point History ── */
$stHistory = $db->prepare("
    SELECT lp.points, lp.description, lp.created_at,
           b.id AS booking_id, s.name AS service_name, pp.business_name
    FROM tbl_loyalty_points lp
    LEFT JOIN tbl_bookings b           ON lp.booking_id = b.id
    LEFT JOIN tbl_services s           ON b.service_id  = s.id
    LEFT JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    WHERE lp.user_id = ?
    ORDER BY lp.created_at DESC
    LIMIT 20
");
$stHistory->execute([$userId]);
$history = $stHistory->fetchAll();

/* ── Total earned / redeemed ── */
$stEarned = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ? AND points > 0");
$stEarned->execute([$userId]);
$totalEarned = (int)$stEarned->fetchColumn();

$stRedeemed = $db->prepare("SELECT COALESCE(SUM(ABS(points)),0) FROM tbl_loyalty_points WHERE user_id = ? AND points < 0");
$stRedeemed->execute([$userId]);
$totalRedeemed = (int)$stRedeemed->fetchColumn();

/* ── Redeemable rewards catalog ── */
$rewards = [
    ['id' => 1, 'title' => '₱50 Booking Credit',      'cost' => 200,  'icon' => '💳', 'desc' => 'Applied on your next booking'],
    ['id' => 2, 'title' => '₱150 Booking Credit',     'cost' => 500,  'icon' => '💳', 'desc' => 'Applied on your next booking'],
    ['id' => 3, 'title' => 'Free Service Upgrade',     'cost' => 750,  'icon' => '⬆️',  'desc' => 'Upgrade any standard service'],
    ['id' => 4, 'title' => '20% Off Next Booking',    'cost' => 1000, 'icon' => '🏷️',  'desc' => 'One-time use discount code'],
    ['id' => 5, 'title' => 'Priority Scheduling',     'cost' => 400,  'icon' => '⚡',  'desc' => 'Jump the queue for 30 days'],
    ['id' => 6, 'title' => 'Free Home Visit Add-on',  'cost' => 600,  'icon' => '🏠',  'desc' => 'Free transport for one booking'],
];

/* ── Upcoming bookings count (for nav badge) ── */
$stUpcoming = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE()");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();

/* ── Flash message ── */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Loyalty</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_loyalty.css">
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<!-- ══ NAV ══ -->
<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">

    <a href="<?= BASE_URL ?>home" class="pv-logo">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard"   class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings"    class="pv-nav-link">
        Bookings
        <?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse"      class="pv-nav-link">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty"     class="pv-nav-link is-active">Loyalty</a>
      <a href="<?= BASE_URL ?>profile"     class="pv-nav-link">Profile</a>
    </div>

    <div class="pv-nav-end">
      <div class="pv-points-badge">⭐ <?= number_format($loyaltyPoints) ?> pts</div>
      <button class="pv-notif-btn" aria-label="Notifications">
        🔔
        <span class="pv-notif-dot" aria-hidden="true"></span>
      </button>
      <div class="pv-nav-av" aria-hidden="true"><?= $initials ?></div>
      <div class="pv-nav-user">
        <div class="pv-nav-user-name"><?= $name ?></div>
        <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>

  </div>
</nav>

<!-- ══ HERO ══ -->
<header class="ly-hero" role="banner">
  <div class="ly-hero-overlay" aria-hidden="true"></div>
  <div class="ly-hero-inner">
    <div class="ly-hero-text">
      <p class="ly-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Your Rewards
      </p>
      <h1 class="ly-hero-title">Loyalty Program</h1>
      <p class="ly-hero-sub">Earn points with every booking. Redeem for credits, upgrades &amp; more.</p>
    </div>

    <!-- Tier badge hero card -->
    <div class="ly-tier-hero-card tier-<?= strtolower($loyaltyTier) ?>">
      <div class="ly-tier-hero-icon"><?= $tier['icon'] ?></div>
      <div class="ly-tier-hero-info">
        <div class="ly-tier-hero-label">Current Tier</div>
        <div class="ly-tier-hero-name"><?= $loyaltyTier ?> Member</div>
      </div>
      <div class="ly-tier-hero-pts">
        <div class="ly-tier-hero-pts-val"><?= number_format($loyaltyPoints) ?></div>
        <div class="ly-tier-hero-pts-label">points</div>
      </div>
    </div>
  </div>
</header>

<?php if ($flash): ?>
<div class="ly-flash ly-flash--<?= $flash['type'] ?>" role="alert">
  <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<!-- ══ MAIN ══ -->
<main class="ly-page" role="main">

  <!-- ── Row 1: stats + tier progress ── -->
  <section class="ly-stats-row" aria-label="Points summary">

    <div class="ly-stat-card">
      <div class="ly-stat-icon" style="background:var(--gold-soft); color:var(--gold);">⭐</div>
      <div>
        <div class="ly-stat-val"><?= number_format($loyaltyPoints) ?></div>
        <div class="ly-stat-label">Available Points</div>
      </div>
    </div>

    <div class="ly-stat-card">
      <div class="ly-stat-icon" style="background:var(--green-soft); color:var(--green);">📈</div>
      <div>
        <div class="ly-stat-val"><?= number_format($totalEarned) ?></div>
        <div class="ly-stat-label">Total Earned</div>
      </div>
    </div>

    <div class="ly-stat-card">
      <div class="ly-stat-icon" style="background:var(--indigo-soft); color:var(--indigo);">🎁</div>
      <div>
        <div class="ly-stat-val"><?= number_format($totalRedeemed) ?></div>
        <div class="ly-stat-label">Total Redeemed</div>
      </div>
    </div>

    <!-- Tier progress card (wider) -->
    <div class="ly-tier-progress-card">
      <div class="ly-tier-prog-header">
        <span class="ly-tier-prog-label">Tier Progress</span>
        <span class="ly-tier-badge tier-<?= strtolower($loyaltyTier) ?>"><?= $tier['icon'] ?> <?= $loyaltyTier ?></span>
      </div>
      <?php if ($nextTier): ?>
      <div class="ly-tier-track">
        <span><?= $loyaltyTier ?></span>
        <span><?= $nextTier ?></span>
      </div>
      <div class="ly-tier-bar-wrap" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="ly-tier-bar-fill" style="width:<?= $progress ?>%"></div>
      </div>
      <p class="ly-tier-hint">
        <?= number_format($ptsToNext) ?> more pts to unlock <strong><?= $nextTier ?></strong>
      </p>
      <?php else: ?>
      <div class="ly-tier-bar-wrap" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
        <div class="ly-tier-bar-fill" style="width:100%"></div>
      </div>
      <p class="ly-tier-hint">🏆 You're at the highest tier! Enjoy your Gold benefits.</p>
      <?php endif; ?>
    </div>

  </section>

  <!-- ── Row 2: tier journey ── -->
  <section class="ly-section" aria-label="Tier journey">
    <h2 class="ly-section-title">Membership Tiers</h2>
    <div class="ly-tiers-grid">

      <?php foreach (['Bronze', 'Silver', 'Gold'] as $t):
        $tc  = $tierConfig[$t];
        $active = $loyaltyTier === $t;
      ?>
      <div class="ly-tier-card <?= $active ? 'is-active' : '' ?> tier-<?= strtolower($t) ?>">
        <?php if ($active): ?>
        <div class="ly-tier-card-badge">Current</div>
        <?php endif; ?>
        <div class="ly-tier-card-icon"><?= $tc['icon'] ?></div>
        <div class="ly-tier-card-name"><?= $t ?></div>
        <div class="ly-tier-card-threshold">
          <?php if ($t === 'Bronze'): ?>0 – 999 pts
          <?php elseif ($t === 'Silver'): ?>1,000 – 1,999 pts
          <?php else: ?>2,000+ pts<?php endif; ?>
        </div>
        <ul class="ly-tier-card-perks" aria-label="<?= $t ?> perks">
          <?php foreach ($tc['perks'] as $perk): ?>
          <li><?= htmlspecialchars($perk) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>

    </div>
  </section>

  <!-- ── Row 3: rewards catalog ── -->
  <section class="ly-section" aria-label="Redeem rewards">
    <div class="ly-section-head">
      <h2 class="ly-section-title">Redeem Points</h2>
      <span class="ly-section-sub">You have <strong><?= number_format($loyaltyPoints) ?></strong> points available</span>
    </div>
    <div class="ly-rewards-grid">
      <?php foreach ($rewards as $r):
        $canRedeem = $loyaltyPoints >= $r['cost'];
      ?>
      <div class="ly-reward-card <?= $canRedeem ? '' : 'is-locked' ?>">
        <div class="ly-reward-icon"><?= $r['icon'] ?></div>
        <div class="ly-reward-info">
          <div class="ly-reward-title"><?= htmlspecialchars($r['title']) ?></div>
          <div class="ly-reward-desc"><?= htmlspecialchars($r['desc']) ?></div>
        </div>
        <div class="ly-reward-footer">
          <span class="ly-reward-cost">⭐ <?= number_format($r['cost']) ?> pts</span>
          <?php if ($canRedeem): ?>
          <form method="POST" action="<?= BASE_URL ?>loyalty/redeem">
            <input type="hidden" name="reward_id" value="<?= $r['id'] ?>">
            <button type="submit" class="ly-redeem-btn">Redeem</button>
          </form>
          <?php else: ?>
          <span class="ly-locked-label">
            Need <?= number_format($r['cost'] - $loyaltyPoints) ?> more
          </span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── Row 4: points history ── -->
  <section class="ly-section" aria-label="Points history">
    <h2 class="ly-section-title">Points History</h2>

    <?php if (empty($history)): ?>
    <div class="ly-empty">
      <div class="ly-empty-icon">📭</div>
      <p>No points activity yet.</p>
      <a href="<?= BASE_URL ?>browse" class="ly-empty-cta">Book a Service →</a>
    </div>
    <?php else: ?>
    <div class="ly-history-table-wrap">
      <table class="ly-history-table" role="table" aria-label="Points transaction history">
        <thead>
          <tr>
            <th>Date</th>
            <th>Description</th>
            <th>Service</th>
            <th class="ly-th-pts">Points</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $row): ?>
          <tr>
            <td class="ly-td-date">
              <span class="ly-date-main"><?= date('M j, Y', strtotime($row['created_at'])) ?></span>
              <span class="ly-date-time"><?= date('g:ia', strtotime($row['created_at'])) ?></span>
            </td>
            <td class="ly-td-desc"><?= htmlspecialchars($row['description'] ?? 'Loyalty transaction') ?></td>
            <td class="ly-td-service">
              <?php if ($row['service_name']): ?>
              <span><?= htmlspecialchars($row['service_name']) ?></span>
              <?php if ($row['business_name']): ?>
              <span class="ly-td-provider"><?= htmlspecialchars($row['business_name']) ?></span>
              <?php endif; ?>
              <?php else: ?>
              <span class="ly-td-na">—</span>
              <?php endif; ?>
            </td>
            <td class="ly-td-pts">
              <span class="ly-pts-pill <?= $row['points'] > 0 ? 'is-earn' : 'is-redeem' ?>">
                <?= $row['points'] > 0 ? '+' : '' ?><?= number_format($row['points']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <!-- ── How it works ── -->
  <section class="ly-section ly-how" aria-label="How loyalty points work">
    <h2 class="ly-section-title">How It Works</h2>
    <div class="ly-how-grid">
      <div class="ly-how-card">
        <div class="ly-how-icon">📅</div>
        <div class="ly-how-title">Book a Service</div>
        <div class="ly-how-body">Browse and book from any provider in the QuickBook network.</div>
      </div>
      <div class="ly-how-arrow" aria-hidden="true">→</div>
      <div class="ly-how-card">
        <div class="ly-how-icon">✅</div>
        <div class="ly-how-title">Complete &amp; Earn</div>
        <div class="ly-how-body">Earn 10 pts per ₱100 spent on every completed booking.</div>
      </div>
      <div class="ly-how-arrow" aria-hidden="true">→</div>
      <div class="ly-how-card">
        <div class="ly-how-icon">🎁</div>
        <div class="ly-how-title">Redeem Rewards</div>
        <div class="ly-how-body">Use points for booking credits, discounts, and exclusive perks.</div>
      </div>
      <div class="ly-how-arrow" aria-hidden="true">→</div>
      <div class="ly-how-card">
        <div class="ly-how-icon">⭐</div>
        <div class="ly-how-title">Level Up</div>
        <div class="ly-how-body">Accumulate points to unlock Silver and Gold tier benefits.</div>
      </div>
    </div>
  </section>

</main>

<!-- ══ FOOTER ══ -->
<footer class="ly-footer" role="contentinfo">
  <div class="ly-footer-inner">
    <span>© <?= date('Y') ?> QuickBook. All rights reserved.</span>
    <span>Questions? <a href="mailto:support@quickbook.ph">support@quickbook.ph</a></span>
  </div>
</footer>

</body>
</html>