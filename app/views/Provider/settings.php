<?php
// app/views/Provider/settings.php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$userId       = (int)($_SESSION['user_id']   ?? 0);
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');

// ── Fetch provider + user data ────────────────────────────────
$stmt = $db->prepare("
    SELECT pp.*, u.first_name, u.last_name, u.email, u.phone,
           c.name AS category_name, c.slug AS category_slug
    FROM tbl_provider_profiles pp
    JOIN tbl_users u ON pp.user_id = u.id
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    WHERE pp.user_id = ?
");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if (!$profile) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Profile not found.'];
    header('Location: ' . BASE_URL . 'provider/dashboard'); exit;
}

$profileId    = (int)$profile['id'];
$firstName    = htmlspecialchars($profile['first_name'] ?? explode(' ', $providerName)[0]);
$provFullName = htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: $providerName);
$bizName      = htmlspecialchars($profile['business_name'] ?? $providerName);
$catSlug      = $profile['category_slug'] ?? '';
$bizCategory  = htmlspecialchars($profile['category_name'] ?? 'Service Provider');
$email        = htmlspecialchars($profile['email'] ?? '');
$phone        = htmlspecialchars($profile['phone'] ?? '');
$profilePhoto = $profile['profile_photo'] ?? null;
$initials     = strtoupper(substr($bizName, 0, 2));
$isVerified   = !empty($profile['is_verified']);
$isActive     = ($profile['status'] ?? 'active') === 'active';

// ── Category icon map (matches profile.php) ───────────────────
$catIconMap = [
    'barbershop'       => '<i class="fa-solid fa-scissors"></i>',
    'hair-salon'       => '<i class="fa-solid fa-spa"></i>',
    'nail-care'        => '<i class="fa-solid fa-hand-sparkles"></i>',
    'massage-therapy'  => '<i class="fa-solid fa-hands"></i>',
    'skincare-facial'  => '<i class="fa-solid fa-face-smile-beam"></i>',
    'fitness-training' => '<i class="fa-solid fa-dumbbell"></i>',
    'home-cleaning'    => '<i class="fa-solid fa-broom"></i>',
    'pet-grooming'     => '<i class="fa-solid fa-paw"></i>',
    'event-styling'    => '<i class="fa-solid fa-wand-magic-sparkles"></i>',
    'dental'           => '<i class="fa-solid fa-tooth"></i>',
    'tutoring'         => '<i class="fa-solid fa-book-open"></i>',
    'makeup-artist'    => '<i class="fa-solid fa-wand-magic-sparkles"></i>',
];
$catIcon = $catIconMap[$catSlug] ?? '<i class="fa-solid fa-briefcase"></i>';

// ── Pending count for nav badge ───────────────────────────────
$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stPending->execute([$profileId]);
$pendingCount = (int)$stPending->fetchColumn();

// ── Ensure notification preferences table exists ──────────────
$db->exec("
    CREATE TABLE IF NOT EXISTS tbl_provider_notification_prefs (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        provider_id      INT NOT NULL,
        pref_key         VARCHAR(80) NOT NULL,
        pref_value       TINYINT(1) NOT NULL DEFAULT 1,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_provider_pref (provider_id, pref_key)
    )
");

// ── Default notification preferences ─────────────────────────
$notifDefaults = [
    // Booking Alerts
    'notif_new_booking'       => 1,
    'notif_booking_confirmed' => 1,
    'notif_booking_cancelled' => 1,
    'notif_reminder_24h'      => 1,
    'notif_reminder_1h'       => 0,
    // Reviews & Feedback
    'notif_new_review'        => 1,
    'notif_low_rating'        => 1,
    // Portfolio
    'notif_portfolio_like'    => 1,
    'notif_portfolio_comment' => 0,
    // System
    'notif_system_updates'    => 1,
    'notif_security_alerts'   => 1,
    // Delivery Channels
    'channel_inapp'           => 1,
    'channel_email'           => 1,
    'channel_sms'             => 0,
    'channel_weekly_digest'   => 1,
    'channel_marketing'       => 0,
];

// ── Load saved preferences ────────────────────────────────────
$stPrefs = $db->prepare("SELECT pref_key, pref_value FROM tbl_provider_notification_prefs WHERE provider_id = ?");
$stPrefs->execute([$profileId]);
$savedPrefs = [];
foreach ($stPrefs->fetchAll() as $row) {
    $savedPrefs[$row['pref_key']] = (int)$row['pref_value'];
}
// Merge with defaults (saved wins)
$notifPrefs = array_merge($notifDefaults, $savedPrefs);

// ── Flash ─────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Helper: checked attribute
function chk(array $prefs, string $key): string {
    return !empty($prefs[$key]) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Provider Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_dashboard.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_settings.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script>(function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();</script>
  <style>
  /* ── Notification section: save bar ─────────────── */
  .ps-notif-save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.8rem;
    border-top: 1.5px solid var(--gold-border);
    background: var(--surface-md);
    border-radius: 0 0 var(--r-xl) var(--r-xl);
    flex-wrap: wrap;
  }
  .ps-notif-status {
    font-family: var(--font-body);
    font-size: .78rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: .4rem;
  }
  .ps-notif-status.is-saved {
    color: #22c55e;
  }
  .ps-notif-status.is-error {
    color: var(--red);
  }
  /* ── Category badge in overview ─────────────────── */
  .ps-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-family: var(--font-mono);
    font-size: .6rem;
    font-weight: 500;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: .22rem .7rem;
    border-radius: 99px;
    background: var(--gold-lt);
    border: 1px solid var(--gold-border-md);
    color: var(--gold-dim);
  }
  /* ── Notification group header ───────────────────── */
  .ps-notif-group {
    margin-bottom: .25rem;
  }
  /* ── Notification save button inline ────────────── */
  #saveNotifBtn {
    min-width: 160px;
  }
  /* ── Toggle row with better spacing ─────────────── */
  .ps-toggle-row {
    padding: .9rem 0;
  }
  /* ── Portfolio notification section icon ─────────── */
  .ps-toggle-ico {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--gold-lt);
    border: 1px solid var(--gold-border);
    color: var(--gold-dim);
    font-size: .68rem;
    flex-shrink: 0;
    margin-right: .45rem;
  }
  /* ── Card section divider with icon ─────────────── */
  .ps-divider-ico {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    color: var(--gold-dim);
  }
  </style>
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAVBAR
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">

    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo"
           style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Provider</span>
    </a>

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
        <svg class="icon-moon" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      </button>

      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av">
          <?php if ($profilePhoto): ?>
            <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div class="pv-nav-user">
          <div class="pv-nav-user-name"><?= $firstName ?></div>
        </div>
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>

      <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="pv-pd-header">
          <div class="pv-pd-avatar">
            <?php if ($profilePhoto): ?>
              <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $provFullName ?></div>
            <div class="pv-pd-email"><?= $email ?></div>
            <!-- Actual category from DB, not hardcoded -->
            <span class="pv-pd-role"><?= $bizCategory ?></span>
          </div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>provider/profile" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-store"></i></span>
          <span>Business Profile</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="<?= BASE_URL ?>provider/settings" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-gear"></i></span>
          <span>Settings</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>auth/logout" class="pv-pd-item pv-pd-item--danger" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
          <span>Sign Out</span>
        </a>
      </div>
    </div>

  </div>
</nav>

<!-- ══════════════════════════════════════
     FLASH MESSAGE
══════════════════════════════════════ -->
<?php if ($flash): ?>
<div class="pv-flash pv-flash--<?= $flash['type'] ?>" id="flashMsg" role="alert">
  <?= $flash['type'] === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?>
  <?= htmlspecialchars($flash['msg']) ?>
  <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:.75rem;opacity:.7;" aria-label="Dismiss">✕</button>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════
     HERO HEADER
══════════════════════════════════════ -->
<header class="ps-hero" role="banner">
  <div class="ps-hero-inner">
    <div class="ps-hero-left">
      <div class="ps-hero-eyebrow">
        <i class="fa-solid fa-gear" style="font-size:.62rem;"></i>
        Provider Settings
      </div>
      <h1 class="ps-hero-title">
        Account &amp;<br><em>Preferences</em>
      </h1>
      <p class="ps-hero-desc">
        Manage your profile, security, notification preferences, and account settings — all in one place.
      </p>
    </div>
    <div class="ps-hero-right">
      <div class="ps-hero-meta">
        <div class="ps-hero-meta-name"><?= $provFullName ?></div>
        <!-- Actual registered category from DB -->
        <div class="ps-hero-meta-role"><?= $bizCategory ?></div>
      </div>
      <div class="ps-hero-av">
        <?php if ($profilePhoto): ?>
          <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
        <?php else: ?>
          <?= $initials ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     MAIN LAYOUT
══════════════════════════════════════ -->
<div class="ps-layout">

  <!-- ── Sidebar ── -->
  <aside class="ps-sidebar" aria-label="Settings navigation">
    <div class="ps-sidebar-card">
      <div class="ps-sidebar-header">
        <div class="ps-sidebar-title">Settings Menu</div>
      </div>

      <nav role="navigation">
        <div class="ps-nav-item is-active" data-target="overview">
          <span class="ps-nav-ico"><i class="fa-solid fa-id-card"></i></span>
          Account Overview
        </div>
        <div class="ps-nav-divider"></div>
        <div class="ps-nav-item" data-target="security">
          <span class="ps-nav-ico"><i class="fa-solid fa-shield-halved"></i></span>
          Security Settings
        </div>
        <div class="ps-nav-item" data-target="notifications">
          <span class="ps-nav-ico"><i class="fa-solid fa-bell"></i></span>
          Notification Preferences
        </div>
        <div class="ps-nav-divider"></div>
        <div class="ps-nav-item" data-target="danger">
          <span class="ps-nav-ico" style="color:var(--red);"><i class="fa-solid fa-triangle-exclamation"></i></span>
          <span style="color:var(--red);">Danger Zone</span>
        </div>
      </nav>

      <!-- Rate QuickBook Button -->
      <div style="padding:1rem 1rem 0;">
        <button class="ps-feedback-btn" id="openFeedbackModal" type="button">
          <i class="fa-solid fa-star"></i>
          Rate QuickBook
        </button>
      </div>

    </div>
  </aside>

  <!-- ── Main Content ── -->
  <main class="ps-main">

    <!-- ════════════════════
         1. ACCOUNT OVERVIEW
    ════════════════════ -->
    <div class="ps-section is-visible" id="sec-overview">

      <!-- Profile Summary Card -->
      <div class="ps-card ps-profile-overview-card">
        <div class="ps-card-head">
          <div class="ps-card-icon"><i class="fa-solid fa-id-card"></i></div>
          <div class="ps-card-head-text">
            <h2 class="ps-card-title">Profile Overview</h2>
            <p class="ps-card-subtitle">A summary of your public provider account information.</p>
          </div>
          <a href="<?= BASE_URL ?>provider/profile" class="ps-edit-profile-btn" style="margin-left:auto;">
            <i class="fa-solid fa-pen-to-square"></i>
            Edit Profile
          </a>
        </div>
        <div class="ps-card-body">

          <!-- Profile Photo + Name Banner -->
          <div class="ps-overview-banner">
            <div class="ps-overview-av">
              <?php if ($profilePhoto): ?>
                <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
              <?php else: ?>
                <?= $initials ?>
              <?php endif; ?>
              <div class="ps-overview-av-status <?= $isActive ? 'is-active' : 'is-inactive' ?>" title="<?= $isActive ? 'Active' : 'Inactive' ?>"></div>
            </div>
            <div class="ps-overview-banner-info">
              <div class="ps-overview-fullname"><?= $provFullName ?></div>
              <div class="ps-overview-bizname"><?= $bizName ?></div>
              <div class="ps-overview-badges">
                <!-- Actual registered category with icon from map -->
                <span class="ps-overview-badge ps-overview-badge--cat">
                  <?= $catIcon ?> <?= $bizCategory ?>
                </span>
                <?php if ($isVerified): ?>
                  <span class="ps-overview-badge ps-overview-badge--verified">
                    <i class="fa-solid fa-circle-check"></i> Verified
                  </span>
                <?php else: ?>
                  <span class="ps-overview-badge ps-overview-badge--unverified">
                    <i class="fa-solid fa-circle-exclamation"></i> Unverified
                  </span>
                <?php endif; ?>
                <span class="ps-overview-badge <?= $isActive ? 'ps-overview-badge--active' : 'ps-overview-badge--inactive' ?>">
                  <i class="fa-solid fa-circle" style="font-size:.45rem;"></i>
                  <?= $isActive ? 'Active' : 'Inactive' ?>
                </span>
              </div>
            </div>
          </div>

          <!-- Info Grid -->
          <div class="ps-overview-grid">
            <div class="ps-overview-item">
              <div class="ps-overview-item-ico"><i class="fa-solid fa-envelope"></i></div>
              <div class="ps-overview-item-body">
                <div class="ps-overview-item-label">Email Address</div>
                <div class="ps-overview-item-val"><?= $email ?: '—' ?></div>
              </div>
            </div>
            <div class="ps-overview-item">
              <div class="ps-overview-item-ico"><i class="fa-solid fa-phone"></i></div>
              <div class="ps-overview-item-body">
                <div class="ps-overview-item-label">Phone Number</div>
                <div class="ps-overview-item-val"><?= $phone ?: '—' ?></div>
              </div>
            </div>
            <div class="ps-overview-item">
              <div class="ps-overview-item-ico"><i class="fa-solid fa-briefcase"></i></div>
              <div class="ps-overview-item-body">
                <div class="ps-overview-item-label">Business Name</div>
                <div class="ps-overview-item-val"><?= $bizName ?></div>
              </div>
            </div>
            <div class="ps-overview-item">
              <!-- Category icon from map, not generic tag icon -->
              <div class="ps-overview-item-ico"><?= $catIcon ?></div>
              <div class="ps-overview-item-body">
                <div class="ps-overview-item-label">Service Category</div>
                <div class="ps-overview-item-val"><?= $bizCategory ?></div>
              </div>
            </div>
            <div class="ps-overview-item">
              <div class="ps-overview-item-ico"><i class="fa-solid fa-shield-halved"></i></div>
              <div class="ps-overview-item-body">
                <div class="ps-overview-item-label">Verification Status</div>
                <div class="ps-overview-item-val">
                  <?php if ($isVerified): ?>
                    <span style="color:#22c55e;font-weight:600;"><i class="fa-solid fa-circle-check"></i> Verified Provider</span>
                  <?php else: ?>
                    <span style="color:var(--red);font-weight:600;"><i class="fa-solid fa-circle-exclamation"></i> Not Verified</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="ps-overview-item">
              <div class="ps-overview-item-ico"><i class="fa-solid fa-toggle-on"></i></div>
              <div class="ps-overview-item-body">
                <div class="ps-overview-item-label">Account Status</div>
                <div class="ps-overview-item-val">
                  <?php if ($isActive): ?>
                    <span style="color:#22c55e;font-weight:600;"><i class="fa-solid fa-circle" style="font-size:.5rem;vertical-align:middle;"></i> Active &amp; Visible</span>
                  <?php else: ?>
                    <span style="color:var(--text-muted);font-weight:600;"><i class="fa-solid fa-circle" style="font-size:.5rem;vertical-align:middle;"></i> Deactivated</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div><!-- /sec-overview -->


    <!-- ════════════════════
         2. SECURITY SETTINGS
    ════════════════════ -->
    <div class="ps-section" id="sec-security">

      <!-- Change Password -->
      <div class="ps-card">
        <div class="ps-card-head">
          <div class="ps-card-icon"><i class="fa-solid fa-key"></i></div>
          <div class="ps-card-head-text">
            <h2 class="ps-card-title">Change Password</h2>
            <p class="ps-card-subtitle">Use a strong password of at least 8 characters with mixed case and symbols.</p>
          </div>
        </div>
        <div class="ps-card-body">
          <div class="ps-row ps-row--full">
            <div class="ps-field">
              <label class="ps-label">Current Password <span>*</span></label>
              <div class="ps-input-wrap">
                <span class="ps-input-ico"><i class="fa-solid fa-lock"></i></span>
                <input type="password" class="ps-input" placeholder="Enter your current password" id="currentPw">
                <button type="button" class="ps-pw-toggle" onclick="togglePw('currentPw',this)" aria-label="Show/hide password">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="ps-row">
            <div class="ps-field">
              <label class="ps-label">New Password <span>*</span></label>
              <div class="ps-input-wrap">
                <span class="ps-input-ico"><i class="fa-solid fa-lock-open"></i></span>
                <input type="password" class="ps-input" placeholder="New password" id="newPw" oninput="checkPwStrength(this.value)">
                <button type="button" class="ps-pw-toggle" onclick="togglePw('newPw',this)" aria-label="Show/hide password">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
              <div class="ps-pw-strength">
                <div class="ps-pw-bars">
                  <div class="ps-pw-bar" id="pwBar1"></div>
                  <div class="ps-pw-bar" id="pwBar2"></div>
                  <div class="ps-pw-bar" id="pwBar3"></div>
                  <div class="ps-pw-bar" id="pwBar4"></div>
                </div>
                <span class="ps-pw-label" id="pwLabel">Enter a password</span>
              </div>
            </div>
            <div class="ps-field">
              <label class="ps-label">Confirm New Password <span>*</span></label>
              <div class="ps-input-wrap">
                <span class="ps-input-ico"><i class="fa-solid fa-check-double"></i></span>
                <input type="password" class="ps-input" placeholder="Repeat new password" id="confirmPw">
                <button type="button" class="ps-pw-toggle" onclick="togglePw('confirmPw',this)" aria-label="Show/hide password">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>
          </div>
          <div style="margin-top:.5rem;">
            <button class="ps-av-btn ps-av-btn--primary" type="button" id="changePwBtn">
              <i class="fa-solid fa-key"></i>
              Update Password
            </button>
          </div>
        </div>
      </div>

      <!-- Login Security -->
      <div class="ps-card">
        <div class="ps-card-head">
          <div class="ps-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
          <div class="ps-card-head-text">
            <h2 class="ps-card-title">Login Security</h2>
            <p class="ps-card-subtitle">Devices and browsers currently signed into your account.</p>
          </div>
        </div>
        <div class="ps-card-body">
          <div class="ps-session-list">
            <div class="ps-session-item">
              <div class="ps-session-ico"><i class="fa-solid fa-computer"></i></div>
              <div class="ps-session-info">
                <div class="ps-session-device">Chrome on Windows</div>
                <div class="ps-session-meta">Bacolod City, PH · Active now</div>
              </div>
              <span class="ps-session-badge ps-session-badge--current">Current</span>
            </div>
            <div class="ps-session-item">
              <div class="ps-session-ico" style="background:var(--surface-md);border-color:var(--border);color:var(--text-muted);"><i class="fa-solid fa-mobile-screen"></i></div>
              <div class="ps-session-info">
                <div class="ps-session-device">Safari on iPhone</div>
                <div class="ps-session-meta">Bacolod City, PH · 2 days ago</div>
              </div>
              <button class="ps-av-btn ps-av-btn--ghost" type="button" style="font-size:.76rem;padding:.36rem .8rem;">
                <i class="fa-solid fa-right-from-bracket"></i> Revoke
              </button>
            </div>
          </div>
          <div style="margin-top:1rem;">
            <button class="ps-av-btn ps-av-btn--ghost" type="button" style="color:var(--red);border-color:rgba(239,68,68,.35);">
              <i class="fa-solid fa-circle-xmark"></i> Sign Out All Other Sessions
            </button>
          </div>
        </div>
      </div>

      <!-- Two-Factor Authentication -->
      <div class="ps-card">
        <div class="ps-card-head">
          <div class="ps-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
          <div class="ps-card-head-text">
            <h2 class="ps-card-title">Two-Factor Authentication</h2>
            <p class="ps-card-subtitle">Add a second layer of protection to your provider account.</p>
          </div>
        </div>
        <div class="ps-card-body">
          <div class="ps-2fa-row">
            <div class="ps-2fa-info">
              <div class="ps-2fa-method-icon"><i class="fa-brands fa-google"></i></div>
              <div>
                <div style="font-size:.92rem;font-weight:600;color:var(--text-primary);margin-bottom:.3rem;">Authenticator App (TOTP)</div>
                <div style="font-size:.78rem;color:var(--text-muted);">Use an app like Google Authenticator or Authy to generate one-time codes whenever you sign in.</div>
              </div>
            </div>
            <span class="ps-2fa-badge ps-2fa-badge--off" id="2faBadge">
              <i class="fa-solid fa-circle-xmark"></i> Not Enabled
            </span>
          </div>
          <div class="ps-2fa-row" style="margin-top:1rem;">
            <div class="ps-2fa-info">
              <div class="ps-2fa-method-icon"><i class="fa-solid fa-message"></i></div>
              <div>
                <div style="font-size:.92rem;font-weight:600;color:var(--text-primary);margin-bottom:.3rem;">SMS Verification</div>
                <div style="font-size:.78rem;color:var(--text-muted);">Receive a one-time code via SMS to your registered mobile number: <?= $phone ? substr($phone, 0, -4) . '****' : 'No phone set' ?>.</div>
              </div>
            </div>
            <span class="ps-2fa-badge ps-2fa-badge--off">
              <i class="fa-solid fa-circle-xmark"></i> Not Enabled
            </span>
          </div>
          <div style="display:flex;gap:.65rem;flex-wrap:wrap;margin-top:1.2rem;">
            <a href="<?= BASE_URL ?>auth/2fa/setup" class="ps-av-btn ps-av-btn--primary" style="text-decoration:none;">
              <i class="fa-solid fa-qrcode"></i> Enable 2FA
            </a>
            <button class="ps-av-btn ps-av-btn--ghost" type="button" style="pointer-events:none;opacity:.45;">
              <i class="fa-solid fa-key"></i> View Recovery Codes
            </button>
          </div>
        </div>
      </div>

    </div><!-- /sec-security -->


    <!-- ════════════════════
         3. NOTIFICATION PREFERENCES
    ════════════════════ -->
    <div class="ps-section" id="sec-notifications">
      <div class="ps-card" style="overflow:hidden;">
        <div class="ps-card-head">
          <div class="ps-card-icon"><i class="fa-solid fa-bell"></i></div>
          <div class="ps-card-head-text">
            <h2 class="ps-card-title">Notification Preferences</h2>
            <p class="ps-card-subtitle">Choose what updates you receive and how you receive them. Changes are saved instantly.</p>
          </div>
        </div>
        <div class="ps-card-body" id="notifBody">

          <!-- ── Booking Alerts ── -->
          <div class="ps-divider ps-notif-group">
            <span class="ps-divider-label ps-divider-ico">
              <i class="fa-solid fa-calendar-check"></i> Booking Alerts
            </span>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">New Booking Request</span>
              <span class="ps-toggle-desc">Notify when a customer submits a new booking request to your calendar.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_new_booking" <?= chk($notifPrefs, 'notif_new_booking') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Booking Confirmed</span>
              <span class="ps-toggle-desc">Notify when a booking is confirmed in the system.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_booking_confirmed" <?= chk($notifPrefs, 'notif_booking_confirmed') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Booking Cancelled</span>
              <span class="ps-toggle-desc">Alert when a customer cancels an appointment.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_booking_cancelled" <?= chk($notifPrefs, 'notif_booking_cancelled') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Appointment Reminder (24 hours)</span>
              <span class="ps-toggle-desc">Receive a reminder 24 hours before each upcoming appointment.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_reminder_24h" <?= chk($notifPrefs, 'notif_reminder_24h') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Appointment Reminder (1 hour)</span>
              <span class="ps-toggle-desc">Receive a final reminder 1 hour before each appointment starts.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_reminder_1h" <?= chk($notifPrefs, 'notif_reminder_1h') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>

          <!-- ── Reviews & Feedback ── -->
          <div class="ps-divider ps-notif-group" style="margin-top:.5rem;">
            <span class="ps-divider-label ps-divider-ico">
              <i class="fa-solid fa-star"></i> Reviews &amp; Feedback
            </span>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">New Customer Review</span>
              <span class="ps-toggle-desc">Notify when a customer leaves a review on your provider profile.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_new_review" <?= chk($notifPrefs, 'notif_new_review') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Low Rating Alert</span>
              <span class="ps-toggle-desc">Immediate alert when a review rated 2 stars or below is posted.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_low_rating" <?= chk($notifPrefs, 'notif_low_rating') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>

          <!-- ── Portfolio ── -->
          <div class="ps-divider ps-notif-group" style="margin-top:.5rem;">
            <span class="ps-divider-label ps-divider-ico">
              <i class="fa-solid fa-images"></i> Portfolio Activity
            </span>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Portfolio Likes</span>
              <span class="ps-toggle-desc">Notify when a customer likes or saves one of your portfolio items.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_portfolio_like" <?= chk($notifPrefs, 'notif_portfolio_like') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Portfolio Comments</span>
              <span class="ps-toggle-desc">Notify when a customer comments on your portfolio photos.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_portfolio_comment" <?= chk($notifPrefs, 'notif_portfolio_comment') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>

          <!-- ── System Notifications ── -->
          <div class="ps-divider ps-notif-group" style="margin-top:.5rem;">
            <span class="ps-divider-label ps-divider-ico">
              <i class="fa-solid fa-shield-halved"></i> System &amp; Security
            </span>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Platform Updates</span>
              <span class="ps-toggle-desc">Notify about new QuickBook features, scheduled maintenance, and policy changes.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_system_updates" <?= chk($notifPrefs, 'notif_system_updates') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Security Alerts</span>
              <span class="ps-toggle-desc">Always notify about suspicious login attempts or account changes. <em style="color:var(--gold-dim);font-size:.72rem;">Recommended</em></span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="notif_security_alerts" <?= chk($notifPrefs, 'notif_security_alerts') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>

          <!-- ── Delivery Channels ── -->
          <div class="ps-divider ps-notif-group" style="margin-top:.5rem;">
            <span class="ps-divider-label ps-divider-ico">
              <i class="fa-solid fa-tower-broadcast"></i> Delivery Channels
            </span>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">In-App Notifications</span>
              <span class="ps-toggle-desc">Show notifications inside the QuickBook provider dashboard.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="channel_inapp" <?= chk($notifPrefs, 'channel_inapp') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Email Notifications</span>
              <span class="ps-toggle-desc">Send updates to <strong><?= $email ?></strong>.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="channel_email" <?= chk($notifPrefs, 'channel_email') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">SMS / Text Messages</span>
              <span class="ps-toggle-desc">Receive critical alerts via SMS to your registered mobile number.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="channel_sms" <?= chk($notifPrefs, 'channel_sms') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Weekly Performance Summary</span>
              <span class="ps-toggle-desc">Receive a weekly email digest with bookings, earnings, and rating stats.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="channel_weekly_digest" <?= chk($notifPrefs, 'channel_weekly_digest') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>
          <div class="ps-toggle-row">
            <div class="ps-toggle-info">
              <span class="ps-toggle-label">Promotional &amp; Marketing Emails</span>
              <span class="ps-toggle-desc">Receive tips, QuickBook feature spotlights, and special offers.</span>
            </div>
            <label class="ps-switch">
              <input type="checkbox" data-pref="channel_marketing" <?= chk($notifPrefs, 'channel_marketing') ?>>
              <span class="ps-switch-track"></span>
            </label>
          </div>

        </div><!-- /ps-card-body -->

        <!-- Save Bar — always visible at card bottom -->
        <div class="ps-notif-save-bar">
          <div class="ps-notif-status" id="notifStatus">
            <i class="fa-solid fa-circle-info"></i>
            Toggle any setting to save
          </div>
          <button class="ps-save-btn" type="button" id="saveNotifBtn" style="width:auto;padding:.62rem 1.5rem;">
            <i class="fa-solid fa-floppy-disk"></i>
            Save Preferences
          </button>
        </div>
      </div>
    </div><!-- /sec-notifications -->


    <!-- ════════════════════
         4. DANGER ZONE
    ════════════════════ -->
    <div class="ps-section" id="sec-danger">

      <!-- Export data -->
      <div class="ps-card">
        <div class="ps-card-head">
          <div class="ps-card-icon"><i class="fa-solid fa-download"></i></div>
          <div class="ps-card-head-text">
            <h2 class="ps-card-title">Export My Data</h2>
            <p class="ps-card-subtitle">Download a copy of all your profile data, booking history, and reviews.</p>
          </div>
        </div>
        <div class="ps-card-body">
          <p style="font-size:.88rem;color:var(--text-dim);margin-bottom:1rem;line-height:1.55;">
            You can request a complete export of your QuickBook provider data including your profile info, booking records, reviews, earnings history, and portfolio. The file will be prepared and sent to <strong><?= $email ?></strong> within 24 hours.
          </p>
          <button class="ps-av-btn ps-av-btn--ghost" type="button" style="color:#3b82f6;border-color:rgba(59,130,246,.35);">
            <i class="fa-solid fa-file-export"></i> Request Data Export
          </button>
        </div>
      </div>

      <!-- Danger Zone card -->
      <div class="ps-card ps-danger-card">
        <div class="ps-card-head">
          <div class="ps-card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
          <div class="ps-card-head-text">
            <h2 class="ps-card-title" style="color:var(--red);">Danger Zone</h2>
            <p class="ps-card-subtitle">Irreversible actions — proceed with extreme caution.</p>
          </div>
        </div>
        <div class="ps-card-body">

          <!-- Deactivate -->
          <div class="ps-danger-item">
            <div>
              <div class="ps-danger-item-title">Deactivate Provider Account</div>
              <div class="ps-danger-item-desc">Temporarily hide your profile from Browse and Search. Existing bookings are preserved and customers with active bookings can still reach you. You can reactivate at any time from Settings.</div>
            </div>
            <button class="ps-danger-btn" type="button" id="deactivateBtn">
              <i class="fa-solid fa-eye-slash"></i> Deactivate
            </button>
          </div>

          <!-- Delete -->
          <div class="ps-danger-item">
            <div>
              <div class="ps-danger-item-title">Delete Provider Account</div>
              <div class="ps-danger-item-desc">Permanently delete your QuickBook provider account and all associated data — profile, bookings, reviews, and portfolio. This action <strong>cannot be undone</strong>. Export your data first if needed.</div>
            </div>
            <button class="ps-danger-btn" type="button" style="background:var(--red-soft);border-color:var(--red);" id="deleteAccountBtn">
              <i class="fa-solid fa-trash"></i> Delete Account
            </button>
          </div>

        </div>
      </div>

    </div><!-- /sec-danger -->

  </main>
</div><!-- /ps-layout -->


<!-- ══════════════════════════════════════
     RATE QUICKBOOK / FEEDBACK MODAL
══════════════════════════════════════ -->
<div class="ps-modal-overlay" id="feedbackModalOverlay"
     role="dialog" aria-modal="true" aria-labelledby="feedbackModalTitle">
  <div class="ps-modal">
 
    <!-- Header -->
    <div class="ps-modal-header">
      <div style="display:flex;align-items:center;gap:.85rem;">
        <div class="ps-card-icon"><i class="fa-solid fa-star"></i></div>
        <div>
          <h2 class="ps-modal-title" id="feedbackModalTitle">Rate QuickBook</h2>
          <p class="ps-modal-subtitle">Your feedback helps us build a better platform for everyone.</p>
        </div>
      </div>
      <button class="ps-modal-close" id="closeFeedbackModal" aria-label="Close modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
 
    <!-- Body -->
    <div class="ps-modal-body">
 
      <!-- 1 · Star Rating -->
      <div class="ps-fb-section">
        <label class="ps-fb-label">Overall Rating <span>*</span></label>
        <div class="ps-star-row" id="starRow" role="radiogroup" aria-label="Rating">
          <button class="ps-star" data-val="1" type="button" aria-label="1 star"><i class="fa-regular fa-star"></i></button>
          <button class="ps-star" data-val="2" type="button" aria-label="2 stars"><i class="fa-regular fa-star"></i></button>
          <button class="ps-star" data-val="3" type="button" aria-label="3 stars"><i class="fa-regular fa-star"></i></button>
          <button class="ps-star" data-val="4" type="button" aria-label="4 stars"><i class="fa-regular fa-star"></i></button>
          <button class="ps-star" data-val="5" type="button" aria-label="5 stars"><i class="fa-regular fa-star"></i></button>
        </div>
        <div class="ps-star-caption" id="starCaption">Tap a star to rate</div>
      </div>
 
      <!-- 2 · Feedback Type -->
      <div class="ps-fb-section">
        <label class="ps-fb-label">Feedback Type <span>*</span></label>
        <div class="ps-fb-types" id="fbTypes">
          <button class="ps-fb-type is-selected" data-type="general" type="button"><i class="fa-solid fa-comment-dots"></i> General</button>
          <button class="ps-fb-type" data-type="suggestion" type="button"><i class="fa-solid fa-lightbulb"></i> Suggestion</button>
          <button class="ps-fb-type" data-type="bug" type="button"><i class="fa-solid fa-bug"></i> Bug Report</button>
          <button class="ps-fb-type" data-type="compliment" type="button"><i class="fa-solid fa-heart"></i> Compliment</button>
        </div>
      </div>
 
      <!-- 3 · Message -->
      <div class="ps-fb-section">
        <label class="ps-fb-label" for="feedbackMsg">Message <span>*</span></label>
        <textarea id="feedbackMsg" class="ps-input" rows="4"
          placeholder="Tell us about your experience, what you love, what could be better, or report a bug…"
          maxlength="1000"></textarea>
        <div class="ps-fb-char-row">
          <span><span id="fbCharCount">0</span> / 1000</span>
        </div>
      </div>
 
    </div><!-- /modal-body -->
 
    <!-- Footer -->
    <div class="ps-modal-footer">
      <button class="ps-av-btn ps-av-btn--ghost" type="button" id="cancelFeedback">Cancel</button>
      <button class="ps-save-btn" type="button" id="submitFeedback">
        <i class="fa-solid fa-paper-plane"></i>
        Send Feedback
      </button>
    </div>
 
  </div><!-- /ps-modal -->
</div><!-- /feedbackModalOverlay -->


<!-- ══════════════════════════════════════
     DELETE ACCOUNT CONFIRMATION MODAL
══════════════════════════════════════ -->
<div class="ps-modal-overlay" id="deleteModalOverlay" role="dialog" aria-modal="true">
  <div class="ps-modal" style="max-width:440px;">
    <div class="ps-modal-header">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <div class="ps-card-icon" style="background:var(--red-soft);border-color:var(--red-border);color:var(--red);"><i class="fa-solid fa-trash"></i></div>
        <div>
          <h2 class="ps-modal-title" style="color:var(--red);">Delete Account</h2>
          <p class="ps-modal-subtitle">This action cannot be undone.</p>
        </div>
      </div>
      <button class="ps-modal-close" id="closeDeleteModal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="ps-modal-body">
      <p style="font-size:.9rem;color:var(--text-dim);line-height:1.6;margin-bottom:1.2rem;">
        You are about to permanently delete your QuickBook provider account. All your data including your profile, bookings, reviews, and portfolio will be erased and <strong>cannot be recovered</strong>.
      </p>
      <div class="ps-field">
        <label class="ps-label">Type <strong>DELETE</strong> to confirm</label>
        <input type="text" class="ps-input" id="deleteConfirmInput" placeholder="Type DELETE here">
      </div>
    </div>
    <div class="ps-modal-footer">
      <button class="ps-av-btn ps-av-btn--ghost" type="button" id="cancelDelete">Cancel</button>
      <button class="ps-danger-btn" type="button" id="confirmDeleteBtn" style="opacity:.4;pointer-events:none;background:var(--red-soft);">
        <i class="fa-solid fa-trash"></i> Permanently Delete
      </button>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════
     SCRIPTS
══════════════════════════════════════ -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>

/* ── Sidebar navigation ── */
(function () {
  const navItems = document.querySelectorAll('.ps-nav-item[data-target]');
  const sections = document.querySelectorAll('.ps-section');

  function activateSection(target) {
    navItems.forEach(n => n.classList.remove('is-active'));
    sections.forEach(s => s.classList.remove('is-visible'));
    const activeNav = document.querySelector(`.ps-nav-item[data-target="${target}"]`);
    const activeSec = document.getElementById(`sec-${target}`);
    if (activeNav) activeNav.classList.add('is-active');
    if (activeSec) activeSec.classList.add('is-visible');
    history.replaceState(null, '', '#' + target);
  }

  navItems.forEach(item => {
    item.addEventListener('click', () => activateSection(item.dataset.target));
  });

  const hash = location.hash.replace('#', '');
  if (hash && document.getElementById(`sec-${hash}`)) activateSection(hash);
})();

/* ── Profile dropdown ── */
(function () {
  const trigger  = document.getElementById('profileTrigger');
  const dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = dropdown.classList.toggle('is-open');
    trigger.classList.toggle('is-open', open);
    trigger.setAttribute('aria-expanded', open);
  });
  document.addEventListener('click', () => {
    dropdown.classList.remove('is-open');
    trigger.classList.remove('is-open');
    trigger.setAttribute('aria-expanded', false);
  });
})();

/* ── Theme toggle ── */
(function () {
  const btn  = document.getElementById('themeToggle');
  const moon = btn?.querySelector('.icon-moon');
  const sun  = btn?.querySelector('.icon-sun');
  const html = document.documentElement;
  function applyTheme(t) {
    if (t === 'dark') {
      html.setAttribute('data-theme', 'dark');
      if (moon) moon.style.display = 'block';
      if (sun)  sun.style.display  = 'none';
    } else {
      html.removeAttribute('data-theme');
      if (moon) moon.style.display = 'none';
      if (sun)  sun.style.display  = 'block';
    }
    localStorage.setItem('qb-theme', t);
  }
  const current = localStorage.getItem('qb-theme') || 'light';
  applyTheme(current);
  if (btn) btn.addEventListener('click', () => {
    applyTheme(html.hasAttribute('data-theme') ? 'light' : 'dark');
  });
})();

/* ── Password strength meter ── */
function checkPwStrength(val) {
  const bars  = [1, 2, 3, 4].map(i => document.getElementById(`pwBar${i}`));
  const label = document.getElementById('pwLabel');
  const levels = ['Too short', 'Weak', 'Fair', 'Strong', 'Very strong'];
  let score = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  bars.forEach((b, idx) => {
    b.className = 'ps-pw-bar' + (idx < score ? ` on-${score}` : '');
  });
  if (label) label.textContent = val.length === 0 ? 'Enter a password' : levels[score];
}

/* ── Password visibility toggle ── */
function togglePw(id, btn) {
  const input = document.getElementById(id);
  if (!input) return;
  const isText = input.type === 'text';
  input.type = isText ? 'password' : 'text';
  const ico = btn.querySelector('i');
  if (ico) ico.className = isText ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
}

/* ── Change password button ── */
(function () {
  const btn = document.getElementById('changePwBtn');
  if (!btn) return;
  btn.addEventListener('click', () => {
    const cur = document.getElementById('currentPw').value;
    const nw  = document.getElementById('newPw').value;
    const cn  = document.getElementById('confirmPw').value;
    if (!cur || !nw || !cn) { alert('Please fill in all password fields.'); return; }
    if (nw !== cn)           { alert('New passwords do not match.');         return; }
    if (nw.length < 8)       { alert('Password must be at least 8 characters.'); return; }
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating…';
    btn.disabled  = true;
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = '<?= BASE_URL ?>provider/settings/update-password';
    const fields = {current_password: cur, new_password: nw, confirm_password: cn};
    Object.entries(fields).forEach(([k, v]) => {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = v;
      f.appendChild(i);
    });
    document.body.appendChild(f); f.submit();
  });
})();

/* ── Flash auto-dismiss ── */
(function () {
  const flash = document.getElementById('flashMsg');
  if (flash) setTimeout(() => flash.remove(), 5000);
})();

/* ══════════════════════════════════════════
   NOTIFICATION PREFERENCES — AJAX SAVE
══════════════════════════════════════════ */
(function () {
  const saveBtn   = document.getElementById('saveNotifBtn');
  const statusEl  = document.getElementById('notifStatus');
  const toggles   = document.querySelectorAll('#notifBody input[data-pref]');

  let isDirty = false;
  let saveTimeout = null;

  function markDirty() {
    isDirty = true;
    setStatus('unsaved', '<i class="fa-solid fa-circle-exclamation"></i> You have unsaved changes');
    // Auto-save after 1.5 s of inactivity
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(savePreferences, 1500);
  }

  function setStatus(type, html) {
    if (!statusEl) return;
    statusEl.className = 'ps-notif-status';
    if (type === 'saved')   statusEl.classList.add('is-saved');
    if (type === 'error')   statusEl.classList.add('is-error');
    statusEl.innerHTML = html;
  }

  function collectPrefs() {
    const prefs = {};
    toggles.forEach(cb => {
      prefs[cb.dataset.pref] = cb.checked ? 1 : 0;
    });
    return prefs;
  }

  function savePreferences() {
    if (!isDirty) return;
    if (saveBtn) {
      saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
      saveBtn.disabled  = true;
    }
    setStatus('', '<i class="fa-solid fa-spinner fa-spin"></i> Saving preferences…');

    const prefs = collectPrefs();

    fetch('<?= BASE_URL ?>provider/settings/save-notifications', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ preferences: prefs })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        isDirty = false;
        setStatus('saved', '<i class="fa-solid fa-circle-check"></i> Preferences saved successfully');
        if (saveBtn) {
          saveBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Saved';
          saveBtn.disabled  = false;
          setTimeout(() => {
            saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Preferences';
          }, 2500);
        }
      } else {
        throw new Error(data.message || 'Save failed');
      }
    })
    .catch(err => {
      setStatus('error', '<i class="fa-solid fa-triangle-exclamation"></i> Could not save — please try again');
      if (saveBtn) {
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Preferences';
        saveBtn.disabled  = false;
      }
    });
  }

  // Listen to all toggles
  toggles.forEach(cb => {
    cb.addEventListener('change', markDirty);
  });

  // Manual save button
  if (saveBtn) {
    saveBtn.addEventListener('click', () => {
      isDirty = true; // Force save even if nothing changed (manual click)
      clearTimeout(saveTimeout);
      savePreferences();
    });
  }
})();


/* ── Feedback Modal ── */
(function () {
  const overlay  = document.getElementById('feedbackModalOverlay');
  const openBtn  = document.getElementById('openFeedbackModal');
  const closeBtn = document.getElementById('closeFeedbackModal');
  const cancelBtn= document.getElementById('cancelFeedback');
  const submitBtn= document.getElementById('submitFeedback');
  const textarea = document.getElementById('feedbackMsg');
  const charCount= document.getElementById('fbCharCount');
  const starCap  = document.getElementById('starCaption');
  const stars    = document.querySelectorAll('.ps-star');
  const fbTypes  = document.querySelectorAll('.ps-fb-type');
  const captions = ['', 'Poor 😞', 'Fair 😐', 'Good 🙂', 'Great 😊', 'Excellent! 🌟'];

  let selectedRating = 0;

  function openModal()  { overlay.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
  function closeModal() { overlay.classList.remove('is-open'); document.body.style.overflow = ''; }

  if (openBtn)  openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn)cancelBtn.addEventListener('click', closeModal);
  overlay?.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

  stars.forEach(star => {
    star.addEventListener('click', () => {
      selectedRating = +star.dataset.val;
      stars.forEach((s, i) => {
        s.classList.toggle('is-selected', i < selectedRating);
        s.querySelector('i').className = i < selectedRating ? 'fa-solid fa-star' : 'fa-regular fa-star';
      });
      if (starCap) starCap.textContent = captions[selectedRating];
    });
    star.addEventListener('mouseenter', () => {
      const val = +star.dataset.val;
      stars.forEach((s, i) => {
        s.querySelector('i').className = i < val ? 'fa-solid fa-star' : (i < selectedRating ? 'fa-solid fa-star' : 'fa-regular fa-star');
      });
    });
    star.addEventListener('mouseleave', () => {
      stars.forEach((s, i) => {
        s.querySelector('i').className = i < selectedRating ? 'fa-solid fa-star' : 'fa-regular fa-star';
      });
    });
  });

  fbTypes.forEach(btn => {
    btn.addEventListener('click', () => {
      fbTypes.forEach(b => b.classList.remove('is-selected'));
      btn.classList.add('is-selected');
    });
  });

  if (textarea && charCount) {
    textarea.addEventListener('input', () => charCount.textContent = textarea.value.length);
  }

  if (submitBtn) {
    submitBtn.addEventListener('click', () => {
      if (!selectedRating) { alert('Please select a star rating.'); return; }
      if (!textarea.value.trim()) { alert('Please enter a message.'); return; }
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…';
      submitBtn.disabled  = true;
      setTimeout(() => {
        closeModal();
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Feedback';
        submitBtn.disabled  = false;
        textarea.value = '';
        if (charCount) charCount.textContent = '0';
        selectedRating = 0;
        stars.forEach(s => { s.classList.remove('is-selected'); s.querySelector('i').className = 'fa-regular fa-star'; });
        if (starCap) starCap.textContent = 'Tap a star to rate';
        const toast = document.createElement('div');
        toast.className = 'pv-flash pv-flash--success';
        toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Thank you for your feedback! ✨ <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:.75rem;opacity:.7;">✕</button>';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
      }, 1500);
    });
  }
})();

/* ── Delete Account Modal ── */
(function () {
  const overlay    = document.getElementById('deleteModalOverlay');
  const openBtn    = document.getElementById('deleteAccountBtn');
  const closeBtn   = document.getElementById('closeDeleteModal');
  const cancelBtn  = document.getElementById('cancelDelete');
  const confirmBtn = document.getElementById('confirmDeleteBtn');
  const input      = document.getElementById('deleteConfirmInput');

  function openModal()  { overlay.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
  function closeModal() { overlay.classList.remove('is-open'); document.body.style.overflow = ''; if(input) input.value = ''; updateDeleteBtn(); }

  function updateDeleteBtn() {
    const ok = input?.value.trim() === 'DELETE';
    confirmBtn.style.opacity        = ok ? '1' : '.4';
    confirmBtn.style.pointerEvents  = ok ? 'auto' : 'none';
  }

  if (openBtn)  openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn)cancelBtn.addEventListener('click', closeModal);
  overlay?.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
  input?.addEventListener('input', updateDeleteBtn);

  confirmBtn?.addEventListener('click', () => {
    confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting…';
    confirmBtn.disabled  = true;
    setTimeout(() => {
      const f = document.createElement('form');
      f.method = 'POST';
      f.action = '<?= BASE_URL ?>provider/settings/delete';
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'confirm'; inp.value = 'DELETE';
      f.appendChild(inp); document.body.appendChild(f); f.submit();
    }, 1000);
  });
})();

/* ── Deactivate button ── */
(function () {
  const btn = document.getElementById('deactivateBtn');
  if (!btn) return;
  btn.addEventListener('click', () => {
    if (!confirm('Are you sure you want to deactivate your provider account? Your profile will be hidden from Browse until you reactivate.')) return;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deactivating…';
    btn.disabled  = true;
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = '<?= BASE_URL ?>provider/settings/deactivate';
    document.body.appendChild(f); f.submit();
  });
})();

</script>
</body>
</html>
