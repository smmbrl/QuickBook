<?php
// app/views/customer/profile.php

require_once __DIR__ . '/../../../config/database.php';
$db     = Database::getInstance();
$userId = (int)($_SESSION['user_id'] ?? 0);

/* -- Full user record -- */
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
$gender      = $user['gender']        ?? '';
$dateOfBirth = $user['date_of_birth'] ?? '';
$initials    = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
$memberSince = isset($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : 'Unknown';
$avatarUrl   = !empty($user['avatar_url']) ? $user['avatar_url'] : null;

/* -- Loyalty -- */
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

/* -- Booking stats -- */
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

/* -- Favourite providers -- */
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

/* -- Recent activity -- */
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

/* -- Flash -- */
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

<!-- ══ NAV ══ -->
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
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <!-- Theme Toggle -->
      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <svg class="icon-moon" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
      </button>

      <div class="pv-nav-av" aria-hidden="true" id="navAv">
        <?php if ($avatarUrl): ?>
          <img id="navAvImg" src="<?= $avatarUrl ?>" alt="<?= $fullName ?>" style="width:34px;height:34px;object-fit:cover;border-radius:99px;display:block;">
        <?php else: ?>
          <span id="navAvInitials"><?= $initials ?></span>
        <?php endif; ?>
      </div>
      <div class="pv-nav-user">
        <div class="pv-nav-user-name"><?= $fullName ?></div>
        <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout-icon" title="Sign out" aria-label="Sign out">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
      </a>
    </div>
  </div>
</nav>

<!-- ══ HERO ══ -->
<header class="pp-hero" role="banner">
  <div class="pp-hero-overlay" aria-hidden="true"></div>
  <div class="pp-hero-inner">

    <div class="pp-hero-profile-row">

      <!-- Avatar -->
      <div class="pp-hero-av-wrap">
        <div class="pp-hero-av" id="heroAv">
          <?php if ($avatarUrl): ?>
            <img src="<?= $avatarUrl ?>" alt="<?= $fullName ?>" id="heroAvImg">
          <?php else: ?>
            <span id="heroAvInitials"><?= $initials ?></span>
          <?php endif; ?>
        </div>
        <label class="pp-av-upload-btn" for="avatarInput" title="Change profile picture" aria-label="Change profile picture">
          <i class="fa-solid fa-camera"></i>
        </label>
        <form id="avatarForm" method="POST" action="<?= BASE_URL ?>profile" enctype="multipart/form-data" style="display:none">
          <input type="hidden" name="action" value="upload_avatar">
          <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" onchange="document.getElementById('avatarForm').submit()">
        </form>
      </div>

      <!-- Identity -->
      <div class="pp-hero-identity">
        <p class="pp-hero-eyebrow">
          <span class="pp-dot-pulse" aria-hidden="true"></span>
          Customer
        </p>
        <h1 class="pp-hero-name"><?= $fullName ?></h1>
        <div class="pp-hero-meta">
          <span class="pp-meta-chip"><i class="fa-solid fa-envelope"></i> <?= $email ?></span>
          <?php if ($phone): ?>
          <span class="pp-meta-chip"><i class="fa-solid fa-phone"></i> <?= $phone ?></span>
          <?php endif; ?>
          <?php if ($user['is_verified'] ?? false): ?>
          <span class="pp-meta-chip pp-meta-chip--green"><i class="fa-solid fa-circle-check"></i> Verified</span>
          <?php endif; ?>
        </div>
        <p class="pp-hero-eyebrow" style="margin-top:.5rem">
          <span>Since <?= $memberSince ?></span>
        </p>
      </div>



    </div>

    <!-- Stat strip -->


  </div>
</header>

<!-- ══ FLASH ══ -->
<?php if ($flash): ?>
<div class="pp-flash pp-flash--<?= $flash['type'] ?>" role="alert">
  <span><?= $flash['type'] === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?></span>
  <?= htmlspecialchars($flash['msg']) ?>
  <button class="pp-flash-close" onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
</div>
<?php endif; ?>

<!-- ══ MAIN ══ -->
<main class="pr-page" role="main">

  <!-- Left col: forms -->
  <div class="pr-col-forms">

    <!-- Personal info -->
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
        <div class="pr-form-row">
          <div class="pr-form-group">
            <label class="pr-label" for="gender">Gender</label>
            <select id="gender" name="gender" class="pr-input" style="cursor:pointer">
              <option value="" <?= !$gender ? 'selected' : '' ?>>Prefer not to say</option>
              <option value="male"            <?= $gender === 'male'            ? 'selected' : '' ?>>Male</option>
              <option value="female"          <?= $gender === 'female'          ? 'selected' : '' ?>>Female</option>
              <option value="non_binary"      <?= $gender === 'non_binary'      ? 'selected' : '' ?>>Non-binary</option>
              <option value="prefer_not_to_say" <?= $gender === 'prefer_not_to_say' ? 'selected' : '' ?>>Prefer not to say</option>
            </select>
          </div>
          <div class="pr-form-group">
            <label class="pr-label" for="date_of_birth">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" class="pr-input"
              value="<?= htmlspecialchars($dateOfBirth) ?>"
              max="<?= date('Y-m-d', strtotime('-13 years')) ?>"
              style="color-scheme:dark">
          </div>
        </div>
        <div class="pr-form-footer">
          <button type="submit" class="pr-btn-primary">Save Changes</button>
        </div>
      </form>
    </section>

    <!-- Change password -->
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
                placeholder="Repeat new password" autocomplete="new-password"
                oninput="checkPasswordMatch()">
              <button type="button" class="pr-pw-toggle" aria-label="Toggle visibility"
                onclick="togglePw('confirm_password', this)"><i class="fa-solid fa-eye"></i></button>
            </div>
            <div id="pwMatchMsg" style="display:none;margin-top:.4rem;font-size:.75rem;font-weight:600;display:flex;align-items:center;gap:.35rem"></div>
          </div>
        </div>
        <div class="pr-form-footer">
          <button type="submit" class="pr-btn-primary">Update Password</button>
        </div>
      </form>
    </section>

    <!-- Two-Factor Authentication -->
    <section class="pr-card" aria-label="Two-factor authentication">
      <div class="pr-card-head">
        <div>
          <h2 class="pr-card-title">Two-Factor Authentication</h2>
          <p class="pr-card-sub">Add an extra layer of security to your account.</p>
        </div>
        <span class="pr-card-icon"><i class="fa-solid fa-shield"></i></span>
      </div>
      <div class="pr-form">
        <?php if ($user['totp_enabled'] ?? false): ?>
          <p style="margin-bottom:1rem;color:#4ADE80;font-weight:600;">
            <i class="fa-solid fa-circle-check"></i> 2FA is enabled on your account.
          </p>
          <form method="POST" action="<?= BASE_URL ?>auth/2fa/disable">
            <div class="pr-form-group">
              <label class="pr-label" for="disable_otp">Enter 6-digit code from your authenticator app</label>
              <input type="text" id="disable_otp" name="otp" placeholder="Enter 6-digit code" maxlength="6" required class="pr-input">
            </div>
            <div class="pr-form-footer">
              <button type="submit" class="pr-btn-danger">Disable 2FA</button>
            </div>
          </form>
        <?php else: ?>
          <p style="margin-bottom:1rem;">Secure your account with two-factor authentication.</p>
          <a href="<?= BASE_URL ?>auth/2fa/setup" class="pr-btn-primary" style="display:inline-block;text-decoration:none;">Enable Two-Factor Authentication</a>
        <?php endif; ?>
      </div>
    </section>

    <!-- Danger zone -->
    <section class="pr-card pr-card--danger" aria-label="Account actions">
      <div class="pr-card-head">
        <div>
          <h2 class="pr-card-title">Account</h2>
          <p class="pr-card-sub">Manage your session and account access.</p>
        </div>
        <span class="pr-card-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
      </div>
      <div class="pr-danger-actions">

        <!-- Deactivate Account -->
        <div class="pr-danger-item">
          <div>
            <div class="pr-danger-label"><i class="fa-solid fa-circle-pause" style="color:var(--red);margin-right:.4rem;font-size:.85rem"></i>Deactivate Account</div>
            <div class="pr-danger-desc">Temporarily disable your account. You can reactivate it anytime by logging back in.</div>
          </div>
          <button type="button" class="pr-btn-danger-outline" onclick="document.getElementById('deactivateModal').style.display='flex'">Deactivate</button>
        </div>

        <!-- Delete Account -->
        <div class="pr-danger-item pr-danger-item--delete">
          <div>
            <div class="pr-danger-label"><i class="fa-solid fa-trash" style="color:var(--red);margin-right:.4rem;font-size:.85rem"></i>Delete Account</div>
            <div class="pr-danger-desc">Permanently delete your account and all associated data. This action <strong>cannot be undone</strong>.</div>
          </div>
          <button type="button" class="pr-btn-danger-solid" onclick="document.getElementById('deleteModal').style.display='flex'">Delete</button>
        </div>

      </div>
    </section>

    <!-- Deactivate Modal -->
    <div id="deactivateModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
      <div class="pr-modal">
        <div class="pr-modal-icon pr-modal-icon--warn"><i class="fa-solid fa-circle-pause"></i></div>
        <h3 class="pr-modal-title">Deactivate Account?</h3>
        <p class="pr-modal-desc">Your account will be temporarily disabled. You can reactivate it anytime by simply logging back in.</p>
        <div class="pr-modal-actions">
          <form method="POST" action="<?= BASE_URL ?>profile/deactivate" style="margin:0">
            <button type="submit" class="pr-modal-confirm pr-modal-confirm--warn">Yes</button>
          </form>
          <button class="pr-modal-cancel" onclick="document.getElementById('deactivateModal').style.display='none'">No</button>
        </div>
      </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
      <div class="pr-modal">
        <div class="pr-modal-icon pr-modal-icon--danger"><i class="fa-solid fa-trash"></i></div>
        <h3 class="pr-modal-title">Delete Account?</h3>
        <p class="pr-modal-desc">This will permanently delete your account, bookings, and all data. <strong>This cannot be undone.</strong></p>
        <div class="pr-modal-actions">
          <form method="POST" action="<?= BASE_URL ?>profile/delete" style="margin:0">
            <button type="submit" class="pr-modal-confirm pr-modal-confirm--danger">Yes</button>
          </form>
          <button class="pr-modal-cancel" onclick="document.getElementById('deleteModal').style.display='none'">No</button>
        </div>
      </div>
    </div>

  </div>

  <!-- Right col: sidebar -->
  <aside class="pr-col-side" aria-label="Account overview">

    <!-- Membership card -->
    <div class="pr-membership-card tier-<?= strtolower($loyaltyTier) ?>">
      <div class="pr-mc-top">
        <div class="pr-mc-avatar">
          <?php if ($avatarUrl): ?>
            <img src="<?= $avatarUrl ?>" alt="<?= $fullName ?>" class="pr-avatar-img">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
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

    <!-- Activity summary -->
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

    <!-- Top providers -->
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

    <!-- Recent activity -->
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

    <!-- Account details -->
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

function checkPasswordMatch() {
  const newPw  = document.getElementById('new_password').value;
  const confPw = document.getElementById('confirm_password').value;
  const msg    = document.getElementById('pwMatchMsg');
  const confInput = document.getElementById('confirm_password');
  if (!confPw) {
    msg.style.display = 'none';
    confInput.style.borderColor = '';
    return;
  }
  if (newPw === confPw) {
    msg.style.display = 'flex';
    msg.style.color = '#4ADE80';
    msg.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match';
    confInput.style.borderColor = 'rgba(74,222,128,.5)';
  } else {
    msg.style.display = 'flex';
    msg.style.color = '#FB7185';
    msg.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';
    confInput.style.borderColor = 'rgba(244,63,94,.5)';
  }
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

// ── Avatar instant preview ──────────────────────────────────────
(function () {
  const input    = document.getElementById('avatarInput');
  const heroAv   = document.getElementById('heroAv');
  const navAv    = document.getElementById('navAv');

  if (!input || !heroAv) return;

  input.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;

    const allowed = ['image/jpeg','image/png','image/webp'];
    if (!allowed.includes(file.type)) {
      alert('Only JPG, PNG or WEBP images are allowed.');
      return;
    }
    if (file.size > 3 * 1024 * 1024) {
      alert('Image must be under 3 MB.');
      return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
      // Update hero avatar
      let heroImg = document.getElementById('heroAvImg');
      const heroInitials = document.getElementById('heroAvInitials');
      if (heroInitials) heroInitials.remove();
      if (!heroImg) {
        heroImg = document.createElement('img');
        heroImg.id = 'heroAvImg';
        heroAv.appendChild(heroImg);
      }
      heroImg.src = e.target.result;

      // Update nav avatar
      if (navAv) {
        let navImg = document.getElementById('navAvImg');
        const navInitials = document.getElementById('navAvInitials');
        if (navInitials) navInitials.remove();
        if (!navImg) {
          navImg = document.createElement('img');
          navImg.id = 'navAvImg';
          navImg.style.cssText = 'width:34px;height:34px;object-fit:cover;border-radius:99px;display:block;';
          navAv.appendChild(navImg);
        }
        navImg.src = e.target.result;
      }
    };
    reader.readAsDataURL(file);

    // Submit the form
    document.getElementById('avatarForm').submit();
  });
})();
</script>
<script>
(function () {
  var btn  = document.getElementById('themeToggle');
  var moon = document.querySelector('.icon-moon');
  var sun  = document.querySelector('.icon-sun');
  function applyTheme(theme) {
    if (theme === 'light') {
      document.documentElement.removeAttribute('data-theme');
      if (moon) moon.style.display = 'none';
      if (sun)  sun.style.display  = 'block';
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      if (moon) moon.style.display = 'block';
      if (sun)  sun.style.display  = 'none';
    }
  }
  applyTheme(localStorage.getItem('qb-theme') || 'light');
  if (btn) {
    btn.addEventListener('click', function () {
      var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem('qb-theme', next);
      applyTheme(next);
    });
  }
})();
</script>
</body>
</html>