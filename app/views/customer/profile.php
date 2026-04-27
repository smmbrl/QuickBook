<?php

require_once __DIR__ . '/../../../config/database.php';
$db     = Database::getInstance();
$userId = (int)($_SESSION['user_id'] ?? 0);

$stUser = $db->prepare("SELECT * FROM tbl_users WHERE id = ? LIMIT 1");
$stUser->execute([$userId]);
$user = $stUser->fetch();

if (!$user) {
    header('Location: ' . BASE_URL . 'auth/logout'); exit;
}

$firstName   = htmlspecialchars($user['first_name'] ?? '');
$lastName    = htmlspecialchars($user['last_name']  ?? '');
$fullName    = trim("$firstName $lastName");
$email       = htmlspecialchars($user['email']      ?? '');
$phone       = htmlspecialchars($user['phone']      ?? '');
$initials    = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
$memberSince = isset($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : 'Unknown';

$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$loyaltyTier   = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};
$tierIcon = match($loyaltyTier) {
    'Gold'   => '<i class="fa-solid fa-medal" style="color:#FFD700"></i>',
    'Silver' => '<i class="fa-solid fa-medal" style="color:#C0C0C0"></i>',
    default  => '<i class="fa-solid fa-medal" style="color:#cd7f32"></i>',
};

$stStats = $db->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'completed')               AS completed,
        SUM(status IN ('pending','confirmed'))   AS upcoming,
        SUM(status IN ('cancelled','rejected'))  AS cancelled
    FROM tbl_bookings WHERE customer_id = ?
");
$stStats->execute([$userId]);
$stats = $stStats->fetch();

$stSpent = $db->prepare("
    SELECT COALESCE(SUM(s.price), 0)
    FROM tbl_bookings b
    JOIN tbl_services s ON b.service_id = s.id
    WHERE b.customer_id = ? AND b.status = 'completed'
");
$stSpent->execute([$userId]);
$totalSpent    = (float)$stSpent->fetchColumn();
$upcomingCount = (int)($stats['upcoming'] ?? 0);

$stFavs = $db->prepare("
    SELECT pp.business_name, pp.id AS profile_id,
           COUNT(*) AS booking_count,
           MAX(b.booking_date) AS last_booked
    FROM tbl_bookings b
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    WHERE b.customer_id = ?
    GROUP BY pp.id, pp.business_name
    ORDER BY booking_count DESC LIMIT 3
");
$stFavs->execute([$userId]);
$favourites = $stFavs->fetchAll();

$stRecent = $db->prepare("
    SELECT b.id, b.booking_date, b.status,
           s.name AS service_name, s.price,
           pp.business_name
    FROM tbl_bookings b
    JOIN tbl_services s ON b.service_id = s.id
    JOIN tbl_provider_profiles pp ON b.provider_id = pp.id
    WHERE b.customer_id = ?
    ORDER BY b.created_at DESC LIMIT 5
");
$stRecent->execute([$userId]);
$recentActivity = $stRecent->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);


function fmtMoney(float $v): string {
    return $v >= 1000 ? '&#x20B1;' . number_format($v / 1000, 1) . 'k' : '&#x20B1;' . number_format($v, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook &mdash; My Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_profile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard"  class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings"   class="pv-nav-link">
        Bookings
        <?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse"     class="pv-nav-link">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty"    class="pv-nav-link">Loyalty</a>
      <a href="<?= BASE_URL ?>profile"    class="pv-nav-link is-active">Profile</a>
    </div>
    <div class="pv-nav-end">
      <div class="pv-points-badge">&#9733; <?= number_format($loyaltyPoints) ?> pts</div>
      <button class="pv-notif-btn" aria-label="Notifications">
        <i class="fa-solid fa-bell"></i><span class="pv-notif-dot" aria-hidden="true"></span>
      </button>
      <div class="pv-nav-av" aria-hidden="true"><?= $initials ?></div>
      <div class="pv-nav-user">
        <div class="pv-nav-user-name"><?= $fullName ?></div>
        <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>
  </div>
</nav>

<header class="pr-hero" role="banner">
  <div class="pr-hero-overlay" aria-hidden="true"></div>
  <div class="pr-hero-inner">

    <div class="pr-identity">
      <div class="pr-avatar"><?= $initials ?></div>
      <div class="pr-identity-text">
        <p class="pr-hero-eyebrow">
          <span class="pv-dot-pulse" aria-hidden="true"></span>
          Active Member &middot; Since <?= $memberSince ?>
        </p>
        <h1 class="pr-hero-name"><?= $fullName ?></h1>
        <p class="pr-hero-email"><?= $email ?></p>
        <div class="pr-hero-badges">
          <span class="pr-tier-badge tier-<?= strtolower($loyaltyTier) ?>"><?= $tierIcon ?> <?= $loyaltyTier ?> Tier</span>
          <?php if ($user['is_verified'] ?? false): ?>
          <span class="pr-verified-badge"><i class="fa-solid fa-circle-check"></i> Verified</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="pr-hero-stats">
      <div class="pr-hs-item">
        <span class="pr-hs-val"><?= $stats['total'] ?? 0 ?></span>
        <span class="pr-hs-label">Total Bookings</span>
      </div>
      <div class="pr-hs-div" aria-hidden="true"></div>
      <div class="pr-hs-item">
        <span class="pr-hs-val green"><?= $stats['completed'] ?? 0 ?></span>
        <span class="pr-hs-label">Completed</span>
      </div>
      <div class="pr-hs-div" aria-hidden="true"></div>
      <div class="pr-hs-item">
        <span class="pr-hs-val gold"><?= number_format($loyaltyPoints) ?></span>
        <span class="pr-hs-label">Loyalty Points</span>
      </div>
      <div class="pr-hs-div" aria-hidden="true"></div>
      <div class="pr-hs-item">
        <span class="pr-hs-val"><?= fmtMoney($totalSpent) ?></span>
        <span class="pr-hs-label">Total Spent</span>
      </div>
    </div>

  </div>
</header>

<?php if ($flash): ?>
<div class="pr-flash pr-flash--<?= $flash['type'] ?>" role="alert">
  <?= htmlspecialchars($flash['msg']) ?>
</div>
<?php endif; ?>

<main class="pr-page" role="main">

  <div class="pr-col-forms">

    <?php if ($flash): ?>
    <div class="pr-error-box pr-error-box--<?= htmlspecialchars($flash['type']) ?>" role="alert">
      <?= $flash['type'] === 'success'
          ? '<i class="fa-solid fa-circle-check"></i> ' . htmlspecialchars($flash['msg'])
          : '<i class="fa-solid fa-triangle-exclamation"></i> ' . htmlspecialchars($flash['msg']) ?>
    </div>
    <?php endif; ?>

    <section class="pr-card" aria-label="Personal information">
      <div class="pr-card-head">
        <div>
          <h2 class="pr-card-title">Personal Information</h2>
          <p class="pr-card-sub">Update your name, email, and contact number.</p>
        </div>
        <span class="pr-card-icon"><i class="fa-solid fa-user"></i></span>
      </div>
      <form method="POST" action="<?= BASE_URL ?>profile" class="pr-form" novalidate>
        <input type="hidden" name="action" value="update_profile">
        <div class="pr-form-row">
          <div class="pr-form-group">
            <label class="pr-label" for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" class="pr-input"
              value="<?= $firstName ?>" placeholder="Maria" required autocomplete="given-name">
          </div>
          <div class="pr-form-group">
            <label class="pr-label" for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" class="pr-input"
              value="<?= $lastName ?>" placeholder="Santos" required autocomplete="family-name">
          </div>
        </div>
        <div class="pr-form-group">
          <label class="pr-label" for="email">Email Address</label>
          <input type="email" id="email" name="email" class="pr-input"
            value="<?= $email ?>" placeholder="you@example.com" required autocomplete="email">
        </div>
        <div class="pr-form-group">
          <label class="pr-label" for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" class="pr-input"
            value="<?= $phone ?>" placeholder="+63 917 000 0000" autocomplete="tel">
        </div>
        <div class="pr-form-footer">
          <button type="submit" class="pr-btn-primary">Save Changes</button>
        </div>
      </form>
    </section>

    <section class="pr-card" aria-label="Change password">
      <div class="pr-card-head">
        <div>
          <h2 class="pr-card-title">Change Password</h2>
          <p class="pr-card-sub">Use a strong password of at least 8 characters.</p>
        </div>
        <span class="pr-card-icon"><i class="fa-solid fa-lock"></i></span>
      </div>
      <form method="POST" action="<?= BASE_URL ?>profile" class="pr-form" novalidate>
        <input type="hidden" name="action" value="change_password">
        <div class="pr-form-group">
          <label class="pr-label" for="current_password">Current Password</label>
          <div class="pr-pw-wrap">
            <input type="password" id="current_password" name="current_password" class="pr-input"
              placeholder="Enter current password" autocomplete="current-password">
            <button type="button" class="pr-pw-toggle" aria-label="Toggle visibility"
              onclick="togglePw('current_password', this)"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>
        <div class="pr-form-row">
          <div class="pr-form-group">
            <label class="pr-label" for="new_password">New Password</label>
            <div class="pr-pw-wrap">
              <input type="password" id="new_password" name="new_password" class="pr-input"
                placeholder="Min. 8 characters" autocomplete="new-password"
                oninput="checkStrength(this.value)">
              <button type="button" class="pr-pw-toggle" aria-label="Toggle visibility"
                onclick="togglePw('new_password', this)"><i class="fa-solid fa-eye"></i></button>
            </div>
            <div class="pr-strength-wrap" id="strength-wrap" aria-live="polite">
              <div class="pr-strength-bar">
                <div class="pr-strength-fill" id="strength-fill"></div>
              </div>
              <span class="pr-strength-label" id="strength-label"></span>
            </div>
          </div>
          <div class="pr-form-group">
            <label class="pr-label" for="confirm_password">Confirm New Password</label>
            <div class="pr-pw-wrap">
              <input type="password" id="confirm_password" name="confirm_password" class="pr-input"
                placeholder="Repeat new password" autocomplete="new-password">
              <button type="button" class="pr-pw-toggle" aria-label="Toggle visibility"
                onclick="togglePw('confirm_password', this)"><i class="fa-solid fa-eye"></i></button>
            </div>
          </div>
        </div>
        <div class="pr-form-footer">
          <button type="submit" class="pr-btn-primary">Update Password</button>
        </div>
      </form>
    </section>

    <section class="pr-card pr-card--danger" aria-label="Account actions">
      <div class="pr-card-head">
        <div>
          <h2 class="pr-card-title">Account</h2>
          <p class="pr-card-sub">Manage your session and account access.</p>
        </div>
        <span class="pr-card-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
      </div>
      <div class="pr-danger-actions">
        <div class="pr-danger-item">
          <div>
            <div class="pr-danger-label">Sign Out</div>
            <div class="pr-danger-desc">Ends your current session securely.</div>
          </div>
          <a href="<?= BASE_URL ?>auth/logout" class="pr-btn-danger-outline">Sign Out</a>
        </div>
      </div>
    </section>

  </div>

  <aside class="pr-col-side" aria-label="Account overview">

    <div class="pr-membership-card tier-<?= strtolower($loyaltyTier) ?>">
      <div class="pr-mc-top">
        <div class="pr-mc-avatar"><?= $initials ?></div>
        <div class="pr-mc-info">
          <div class="pr-mc-name"><?= $fullName ?></div>
          <div class="pr-mc-email"><?= $email ?></div>
        </div>
      </div>
      <div class="pr-mc-divider" aria-hidden="true"></div>
      <div class="pr-mc-tier-row">
        <span class="pr-mc-tier-label">Membership Tier</span>
        <span class="pr-mc-tier-val"><?= $tierIcon ?> <?= $loyaltyTier ?></span>
      </div>
      <div class="pr-mc-pts-row">
        <span class="pr-mc-pts-val"><?= number_format($loyaltyPoints) ?></span>
        <span class="pr-mc-pts-label">points available</span>
      </div>
      <a href="<?= BASE_URL ?>loyalty" class="pr-mc-link">View Rewards &rarr;</a>
    </div>

    <div class="pr-side-card">
      <h3 class="pr-side-title">Activity Summary</h3>
      <div class="pr-activity-grid">
        <div class="pr-act-item">
          <span class="pr-act-val"><?= $stats['total'] ?? 0 ?></span>
          <span class="pr-act-label">Total</span>
        </div>
        <div class="pr-act-item">
          <span class="pr-act-val green"><?= $stats['completed'] ?? 0 ?></span>
          <span class="pr-act-label">Completed</span>
        </div>
        <div class="pr-act-item">
          <span class="pr-act-val gold"><?= $stats['upcoming'] ?? 0 ?></span>
          <span class="pr-act-label">Upcoming</span>
        </div>
        <div class="pr-act-item">
          <span class="pr-act-val muted"><?= $stats['cancelled'] ?? 0 ?></span>
          <span class="pr-act-label">Cancelled</span>
        </div>
      </div>
    </div>

    <?php if (!empty($favourites)): ?>
    <div class="pr-side-card">
      <h3 class="pr-side-title">Top Providers</h3>
      <ul class="pr-favs-list">
        <?php foreach ($favourites as $i => $fav): ?>
        <li class="pr-fav-item">
          <div class="pr-fav-rank"><?= $i + 1 ?></div>
          <div class="pr-fav-info">
            <div class="pr-fav-name"><?= htmlspecialchars($fav['business_name']) ?></div>
            <div class="pr-fav-meta">
              <?= $fav['booking_count'] ?> booking<?= $fav['booking_count'] > 1 ? 's' : '' ?>
              &middot; Last: <?= date('M j', strtotime($fav['last_booked'])) ?>
            </div>
          </div>
          <a href="<?= BASE_URL ?>provider/<?= $fav['profile_id'] ?>" class="pr-fav-link">&rarr;</a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($recentActivity)): ?>
    <div class="pr-side-card">
      <div class="pr-side-head">
        <h3 class="pr-side-title">Recent Activity</h3>
        <a href="<?= BASE_URL ?>bookings" class="pr-side-link">All &rarr;</a>
      </div>
      <ul class="pr-activity-list">
        <?php foreach ($recentActivity as $r):
          $sc = match($r['status']) {
            'completed' => 'green', 'confirmed' => 'blue',
            'pending'   => 'gold',  default     => 'red',
          };
        ?>
        <li class="pr-act-row">
          <div class="pr-act-dot pr-act-dot--<?= $sc ?>"></div>
          <div class="pr-act-body">
            <div class="pr-act-service"><?= htmlspecialchars($r['service_name']) ?></div>
            <div class="pr-act-provider"><?= htmlspecialchars($r['business_name']) ?></div>
          </div>
          <div class="pr-act-right">
            <div class="pr-act-price">&#x20B1;<?= number_format($r['price'], 0) ?></div>
            <div class="pr-act-date"><?= date('M j', strtotime($r['booking_date'])) ?></div>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="pr-side-card">
      <h3 class="pr-side-title">Account Details</h3>
      <dl class="pr-info-list">
        <div class="pr-info-row">
          <dt>Member Since</dt>
          <dd><?= $memberSince ?></dd>
        </div>
        <div class="pr-info-row">
          <dt>Status</dt>
          <dd>
            <span class="pr-status-pill <?= ($user['is_active'] ?? false) ? 'is-active' : 'is-inactive' ?>">
              <?= ($user['is_active'] ?? false) ? 'Active' : 'Inactive' ?>
            </span>
          </dd>
        </div>
        <div class="pr-info-row">
          <dt>Verified</dt>
          <dd>
            <span class="pr-status-pill <?= ($user['is_verified'] ?? false) ? 'is-active' : 'is-inactive' ?>">
              <?= ($user['is_verified'] ?? false) ? 'Yes' : 'No' ?>
            </span>
          </dd>
        </div>
        <div class="pr-info-row">
          <dt>Role</dt>
          <dd><?= ucfirst(htmlspecialchars($user['role'] ?? 'customer')) ?></dd>
        </div>
      </dl>
    </div>

  </aside>
</main>

<!-- ══ FOOTER ══ -->
<footer class="pr-footer" role="contentinfo">
  <div class="pr-footer-inner">
    <span>&copy; <?= date('Y') ?> QuickBook. All rights reserved.</span>
    <span>Need help? <a href="mailto:support@quickbook.ph">support@quickbook.ph</a></span>
  </div>
</footer>

<script>
function togglePw(id, btn) {
  const el = document.getElementById(id);
  const hidden = el.type === 'password';
  el.type = hidden ? 'text' : 'password';
  btn.innerHTML = hidden
    ? '<i class="fa-solid fa-eye-slash"></i>'
    : '<i class="fa-solid fa-eye"></i>';
}

function checkStrength(val) {
  const wrap  = document.getElementById('strength-wrap');
  const fill  = document.getElementById('strength-fill');
  const label = document.getElementById('strength-label');
  if (!val) { wrap.classList.remove('is-visible'); return; }
  wrap.classList.add('is-visible');
  let score = 0;
  if (val.length >= 8)            score++;
  if (val.length >= 12)           score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    { pct:20,  cls:'weak',   text:'Weak'   },
    { pct:40,  cls:'weak',   text:'Weak'   },
    { pct:60,  cls:'fair',   text:'Fair'   },
    { pct:80,  cls:'good',   text:'Good'   },
    { pct:100, cls:'strong', text:'Strong' },
  ];
  const lvl = levels[score - 1] ?? levels[0];
  fill.style.width = lvl.pct + '%';
  fill.className   = 'pr-strength-fill ' + lvl.cls;
  label.textContent = lvl.text;
  label.className  = 'pr-strength-label ' + lvl.cls;
}
</script>
</body>
</html>