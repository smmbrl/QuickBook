<?php

require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$userId       = (int)($_SESSION['user_id'] ?? 0);
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');
$initials     = strtoupper(substr($providerName, 0, 2));

// Fetch provider profile details, including category and approval status
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

$profileId = (int)$profile['id'];

// Stats for header strip and sidebar 
$stTotal = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ?");
$stTotal->execute([$profileId]);
$totalBookings = (int)$stTotal->fetchColumn();

$stRevenue = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings WHERE provider_id = ? AND status = 'completed'");
$stRevenue->execute([$profileId]);
$totalRevenue = (float)$stRevenue->fetchColumn();

$stRating = $db->prepare("SELECT COALESCE(AVG(rating),0), COUNT(*) FROM tbl_reviews WHERE provider_id = ?");
$stRating->execute([$profileId]);
[$avgRating, $totalReviews] = $stRating->fetch(\PDO::FETCH_NUM);

$stServices = $db->prepare("SELECT COUNT(*) FROM tbl_services WHERE provider_id = ? AND is_active = 1");
$stServices->execute([$profileId]);
$totalServices = (int)$stServices->fetchColumn();

// Fetch pending bookings count (nav badge) 
$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stPending->execute([$profileId]);
$pendingCount = (int)$stPending->fetchColumn();

// All categories for select options (if we add category editing later) 
$cats = $db->query("SELECT * FROM tbl_categories ORDER BY name")->fetchAll();

//  Flash message (from redirects after form submissions)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

//  Category icon map
$catEmojiMap = [
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
];
$catEmoji = $catEmojiMap[$profile['category_slug']] ?? '<i class="fa-solid fa-briefcase"></i>';

// Approval status helper 
$statusMap = [
    1  => ['label' => 'Approved',  'cls' => 'pp-status--approved',  'icon' => '<i class="fa-solid fa-circle-check"></i>'],
    0  => ['label' => 'Pending',   'cls' => 'pp-status--pending',   'icon' => '<i class="fa-solid fa-clock"></i>'],
    -1 => ['label' => 'Suspended', 'cls' => 'pp-status--suspended', 'icon' => '<i class="fa-solid fa-ban"></i>'],
];
$approvalStatus = $statusMap[(int)$profile['is_approved']] ?? $statusMap[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — My Provider Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_profile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>

<!--NAV -->
<nav class="pp-nav" role="navigation" aria-label="Provider navigation">
  <div class="pp-nav-inner">
    <a href="<?= BASE_URL ?>provider/dashboard" class="pp-logo">
      Quick<span>Book</span>
      <span class="pp-logo-badge">Provider</span>
    </a>
    <div class="pp-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="pp-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/bookings"     class="pp-nav-link">
        Bookings<?php if ($pendingCount): ?><sup class="pp-sup"><?= $pendingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"     class="pp-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/availability" class="pp-nav-link">Availability</a>
      <a href="<?= BASE_URL ?>provider/profile"      class="pp-nav-link is-active">Profile</a>
    </div>
    <div class="pp-nav-end">
      <div class="pp-nav-status <?= $approvalStatus['cls'] ?>">
        <?= $approvalStatus['icon'] ?> <?= $approvalStatus['label'] ?>
      </div>
      <div class="pp-nav-av" id="navAv" aria-hidden="true">
        <?php if (!empty($profile['profile_photo'])): ?>
          <img id="navAvImg" src="<?= BASE_URL ?>assets/uploads/profiles/<?= htmlspecialchars($profile['profile_photo']) ?>" alt="Profile photo" style="width:34px;height:34px;min-width:34px;min-height:34px;max-width:34px;max-height:34px;object-fit:cover;border-radius:99px;display:block;">
        <?php else: ?>
          <span id="navAvInitials"><?= $initials ?></span>
        <?php endif; ?>
      </div>
      <div class="pp-nav-user">
        <div class="pp-nav-user-name"><?= $providerName ?></div>
        <div class="pp-nav-user-role"><?= htmlspecialchars($profile['category_name'] ?? 'Service Provider') ?></div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pp-nav-logout">Sign out</a>
    </div>
  </div>
</nav>

<!-- FLASH MESSAGE -->
<?php if ($flash): ?>
<div class="pp-flash pp-flash--<?= $flash['type'] ?>" role="alert">
  <span><?= $flash['type'] === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?></span>
  <?= htmlspecialchars($flash['msg']) ?>
  <button class="pp-flash-close" onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
</div>
<?php endif; ?>

<!-- HERO BANNER -->
<header class="pp-hero" role="banner">
  <div class="pp-hero-overlay" aria-hidden="true"></div>
  <div class="pp-hero-inner">

    <div class="pp-hero-profile-row">
      <!-- Avatar / Profile Photo -->
      <?php $photoUrl = !empty($profile['profile_photo']) ? BASE_URL . 'assets/uploads/profiles/' . htmlspecialchars($profile['profile_photo']) : null; ?>
      <div class="pp-hero-av-wrap">
        <div class="pp-hero-av" id="heroAv">
          <?php if ($photoUrl): ?>
            <img src="<?= $photoUrl ?>" alt="Profile photo" id="heroAvImg">
          <?php else: ?>
            <span id="heroAvEmoji"><?= $catEmoji ?></span>
          <?php endif; ?>
        </div>
        <label class="pp-av-upload-btn" for="profilePhotoInput" title="Change profile photo">
          <i class="fa-solid fa-camera"></i>
        </label>
        <form id="photoUploadForm" method="POST" action="<?= BASE_URL ?>provider/profile/upload-photo" enctype="multipart/form-data" style="display:none;">
          <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp" style="display:none;">
        </form>
      </div>

      <!-- Identity -->
      <div class="pp-hero-identity">
        <p class="pp-hero-eyebrow">
          <span class="pp-dot-pulse" aria-hidden="true"></span>
          <?= htmlspecialchars($profile['category_name'] ?? 'Service Provider') ?>
        </p>
        <h1 class="pp-hero-name"><?= htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?></h1>
        <div class="pp-hero-meta">
          <span class="pp-meta-chip">
            <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($profile['email']) ?>
          </span>
          <?php if ($profile['phone']): ?>
          <span class="pp-meta-chip">
            <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($profile['phone']) ?>
          </span>
          <?php endif; ?>
          <span class="pp-meta-chip">
            <i class="fa-solid fa-location-dot"></i> Bacolod City
          </span>
        </div>
      </div>

      <!-- Approval Badge -->
      <div class="pp-hero-approval <?= $approvalStatus['cls'] ?>">
        <span class="pp-approval-icon"><?= $approvalStatus['icon'] ?></span>
        <div>
          <div class="pp-approval-label">Account Status</div>
          <div class="pp-approval-val"><?= $approvalStatus['label'] ?></div>
        </div>
      </div>
    </div>

    <!-- Stat Strip -->
    <div class="pp-hero-stats">
      <div class="pp-hs-item">
        <span class="pp-hs-val"><?= $totalBookings ?></span>
        <span class="pp-hs-label">Total Bookings</span>
      </div>
      <div class="pp-hs-div"></div>
      <div class="pp-hs-item">
        <span class="pp-hs-val">₱<?= number_format($totalRevenue, 0) ?></span>
        <span class="pp-hs-label">Revenue Earned</span>
      </div>
      <div class="pp-hs-div"></div>
      <div class="pp-hs-item">
        <span class="pp-hs-val gold"><?= number_format((float)$avgRating, 1) ?> <i class="fa-solid fa-star"></i></span>
        <span class="pp-hs-label"><?= (int)$totalReviews ?> Reviews</span>
      </div>
      <div class="pp-hs-div"></div>
      <div class="pp-hs-item">
        <span class="pp-hs-val"><?= $totalServices ?></span>
        <span class="pp-hs-label">Active Services</span>
      </div>
    </div>

  </div>
</header>

<!-- MAIN CONTENT -->
<main class="pp-page" role="main">
  <div class="pp-layout">

    <!--LEFT COLUMN — edit forms -->
    <div class="pp-main">

      <!-- Breadcrumb -->
      <nav class="pp-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>provider/dashboard">Dashboard</a>
        <span aria-hidden="true">›</span>
        <span>My Profile</span>
      </nav>

      <!-- Section tabs -->
      <div class="pp-tabs" role="tablist" aria-label="Profile sections">
        <button class="pp-tab is-active" data-tab="personal" role="tab" aria-selected="true">
          <i class="fa-solid fa-user"></i> Personal Details
        </button>
        <button class="pp-tab" data-tab="security" role="tab" aria-selected="false">
          <i class="fa-solid fa-lock"></i> Security
        </button>
      </div>

      <!-- TAB: PERSONAL DETAILS -->
      <div class="pp-tab-panel is-active" id="tab-personal" role="tabpanel">
        <div class="pp-card">
          <div class="pp-card-head">
            <div>
              <h2>Personal Details</h2>
              <span class="pp-card-sub">Your account contact information — not visible on your public profile</span>
            </div>
            <div class="pp-card-head-badge pp-card-head-badge--private">Private</div>
          </div>

          <form method="POST" action="<?= BASE_URL ?>provider/profile/update-personal" class="pp-form" id="personalForm">

            <!-- Bio -->
            <div class="pp-form-group">
              <label class="pp-form-label" for="bio">
                Business Bio
                <span class="pp-label-hint">Shown to customers. Keep it compelling!</span>
              </label>
              <textarea class="pp-form-control pp-textarea" id="bio" name="bio"
                        rows="4"
                        placeholder="Tell customers what makes your business special — your experience, specialties, and why they should choose you…"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
              <div class="pp-char-counter">
                <span id="bioCount"><?= strlen($profile['bio'] ?? '') ?></span>/500 characters
              </div>
            </div>

            <div class="pp-form-row pp-form-row--2">
              <div class="pp-form-group">
                <label class="pp-form-label" for="first_name">First Name <span class="pp-req"></span></label>
                <div class="pp-input-wrap">
                  <span class="pp-input-icon"><i class="fa-solid fa-user"></i></span>
                  <input type="text" class="pp-form-control pp-form-control--icon"
                         id="first_name" name="first_name"
                         value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>"
                         placeholder="First name" required>
                </div>
              </div>
              <div class="pp-form-group">
                <label class="pp-form-label" for="last_name">Last Name <span class="pp-req"></span></label>
                <input type="text" class="pp-form-control"
                       id="last_name" name="last_name"
                       value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>"
                       placeholder="Last name" required>
              </div>
            </div>

            <div class="pp-form-row pp-form-row--2">
              <div class="pp-form-group">
                <label class="pp-form-label" for="email">Email Address <span class="pp-req"></span></label>
                <div class="pp-input-wrap">
                  <span class="pp-input-icon"><i class="fa-solid fa-envelope"></i></span>
                  <input type="email" class="pp-form-control pp-form-control--icon"
                         id="email" name="email"
                         value="<?= htmlspecialchars($profile['email'] ?? '') ?>"
                         placeholder="email@example.com" required>
                </div>
              </div>
              <div class="pp-form-group">
                <label class="pp-form-label" for="phone">Phone Number</label>
                <div class="pp-input-wrap">
                  <span class="pp-input-icon"><i class="fa-solid fa-phone"></i></span>
                  <input type="tel" class="pp-form-control pp-form-control--icon"
                         id="phone" name="phone"
                         value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"
                         placeholder="09XX XXX XXXX">
                </div>
              </div>
            </div>

            <!-- Info notice -->
            <div class="pp-info-notice">
              <span aria-hidden="true"><i class="fa-solid fa-circle-info"></i></span>
              Your email is used for login and booking notifications. Changing it will require re-verification.
            </div>

            <div class="pp-form-actions">
              <button type="reset" class="pp-btn pp-btn--ghost">Reset Changes</button>
              <button type="submit" class="pp-btn pp-btn--primary">
                <span class="pp-btn-icon" aria-hidden="true"><i class="fa-solid fa-floppy-disk"></i></span>
                Save Personal Info
              </button>
            </div>
          </form>
        </div>
      </div><!-- /tab-personal -->

      <!--TAB: SECURITY -->
      <div class="pp-tab-panel" id="tab-security" role="tabpanel" hidden>
        <div class="pp-card">
          <div class="pp-card-head">
            <div>
              <h2>Change Password</h2>
              <span class="pp-card-sub">Keep your account secure with a strong, unique password</span>
            </div>
            <div class="pp-card-head-badge pp-card-head-badge--danger">Sensitive</div>
          </div>

          <form method="POST" action="<?= BASE_URL ?>provider/profile/update-password" class="pp-form" id="passwordForm">

            <div class="pp-form-group">
              <label class="pp-form-label" for="current_password">
                Current Password <span class="pp-req"></span>
              </label>
              <div class="pp-input-wrap">
                <span class="pp-input-icon"><i class="fa-solid fa-key"></i></span>
                <input type="password" class="pp-form-control pp-form-control--icon"
                       id="current_password" name="current_password"
                       placeholder="Enter current password" required>
                <button type="button" class="pp-pw-toggle" data-target="current_password" aria-label="Toggle visibility"><i class="fa-solid fa-eye"></i></button>
              </div>
            </div>

            <div class="pp-form-row pp-form-row--2">
              <div class="pp-form-group">
                <label class="pp-form-label" for="new_password">
                  New Password <span class="pp-req"></span>
                </label>
                <div class="pp-input-wrap">
                  <span class="pp-input-icon"><i class="fa-solid fa-lock"></i></span>
                  <input type="password" class="pp-form-control pp-form-control--icon"
                         id="new_password" name="new_password"
                         placeholder="Min 8 characters" minlength="8" required>
                  <button type="button" class="pp-pw-toggle" data-target="new_password" aria-label="Toggle visibility"><i class="fa-solid fa-eye"></i></button>
                </div>

                <!-- Strength bar -->
                <div class="pp-pw-strength">
                  <div class="pp-pw-strength-bar" id="pwStrengthBar"></div>
                </div>
                <span class="pp-pw-strength-label" id="pwStrengthLabel"></span>
              </div>
              <div class="pp-form-group">
                <label class="pp-form-label" for="confirm_password">
                  Confirm Password <span class="pp-req"></span>
                </label>
                <div class="pp-input-wrap">
                  <span class="pp-input-icon"><i class="fa-solid fa-lock"></i></span>
                  <input type="password" class="pp-form-control pp-form-control--icon"
                         id="confirm_password" name="confirm_password"
                         placeholder="Repeat new password" required>
                  <button type="button" class="pp-pw-toggle" data-target="confirm_password" aria-label="Toggle visibility"><i class="fa-solid fa-eye"></i></button>
                </div>
                <span class="pp-match-hint" id="pwMatchHint"></span>
              </div>
            </div>

            <!-- Password requirements checklist -->
            <div class="pp-pw-requirements">
              <div class="pp-pw-req-title">Password must contain:</div>
              <div class="pp-pw-req-list">
                <div class="pp-pw-req-item" id="req-length">
                  <span class="pp-pw-req-dot"></span> At least 8 characters
                </div>
                <div class="pp-pw-req-item" id="req-upper">
                  <span class="pp-pw-req-dot"></span> One uppercase letter
                </div>
                <div class="pp-pw-req-item" id="req-number">
                  <span class="pp-pw-req-dot"></span> One number
                </div>
                <div class="pp-pw-req-item" id="req-special">
                  <span class="pp-pw-req-dot"></span> One special character
                </div>
              </div>
            </div>

            <div class="pp-form-actions">
              <button type="reset" class="pp-btn pp-btn--ghost" onclick="resetPasswordForm()">Cancel</button>
              <button type="submit" class="pp-btn pp-btn--danger">
                <span class="pp-btn-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
                Update Password
              </button>
            </div>
          </form>
        </div>

        <!-- Danger Zone -->
        <div class="pp-card pp-card--danger">
          <div class="pp-card-head">
            <div>
              <h2>Danger Zone</h2>
              <span class="pp-card-sub">Irreversible actions — proceed with caution</span>
            </div>
          </div>
          <div class="pp-danger-body">
            <div class="pp-danger-row">
              <div class="pp-danger-info">
                <div class="pp-danger-title">Deactivate Account</div>
                <div class="pp-danger-desc">Temporarily hides your profile from customers. You can reactivate by contacting support.</div>
              </div>
              <button class="pp-btn pp-btn--danger-outline" onclick="confirmDeactivate()">Deactivate</button>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- RIGHT SIDEBAR -->
    <aside class="pp-sidebar" aria-label="Profile overview">

      <!-- Profile completeness -->
      <div class="pp-card">
        <div class="pp-card-head"><h2>Profile Completeness</h2></div>
        <?php
          $fields  = ['first_name','last_name','email','phone','bio'];
          $filled  = count(array_filter($fields, fn($f) => !empty($profile[$f])));
          $pct     = (int)(($filled / count($fields)) * 100);
          $pctCls  = $pct >= 80 ? 'good' : ($pct >= 50 ? 'mid' : 'low');
        ?>
        <div class="pp-completeness-body">
          <div class="pp-comp-ring-wrap">
            <svg class="pp-comp-ring" viewBox="0 0 88 88" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <circle cx="44" cy="44" r="36" stroke="rgba(255,255,255,.07)" stroke-width="8"/>
              <circle cx="44" cy="44" r="36"
                      stroke="var(--pp-comp-color)"
                      stroke-width="8"
                      stroke-linecap="round"
                      stroke-dasharray="226.2"
                      stroke-dashoffset="<?= 226.2 - (226.2 * $pct / 100) ?>"
                      transform="rotate(-90 44 44)"
                      class="pp-comp-arc pp-comp-arc--<?= $pctCls ?>"/>
            </svg>
            <div class="pp-comp-ring-val"><?= $pct ?>%</div>
          </div>
          <div class="pp-comp-items">
            <?php
              $compFields = [
                'first_name' => '<i class="fa-solid fa-user"></i> First name',
                'last_name'  => '<i class="fa-solid fa-user"></i> Last name',
                'email'      => '<i class="fa-solid fa-envelope"></i> Email',
                'phone'      => '<i class="fa-solid fa-phone"></i> Phone',
                'bio'        => '<i class="fa-solid fa-file-lines"></i> Bio',
              ];
              foreach ($compFields as $k => $label):
                $done = !empty($profile[$k]);
            ?>
            <div class="pp-comp-item <?= $done ? 'is-done' : '' ?>">
              <span class="pp-comp-check" aria-hidden="true"><?= $done ? '✓' : '○' ?></span>
              <?= $label ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if ($pct < 100): ?>
          <p class="pp-comp-tip">
            Complete your profile to rank higher in customer searches!
          </p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick tips -->
      <div class="pp-card">
        <div class="pp-card-head"><h2><i class="fa-solid fa-lightbulb"></i> Profile Tips</h2></div>
        <div class="pp-tips-body">
          <div class="pp-tip-item">
            <div class="pp-tip-icon" aria-hidden="true"><i class="fa-solid fa-camera"></i></div>
            <div class="pp-tip-text">Add a profile photo to increase bookings by up to <strong>30%</strong></div>
          </div>
          <div class="pp-tip-item">
            <div class="pp-tip-icon" aria-hidden="true"><i class="fa-solid fa-pen-nib"></i></div>
            <div class="pp-tip-text">A detailed bio helps customers trust you before booking</div>
          </div>
          <div class="pp-tip-item">
            <div class="pp-tip-icon" aria-hidden="true"><i class="fa-solid fa-star"></i></div>
            <div class="pp-tip-text">Prompt satisfied customers to leave reviews to build credibility</div>
          </div>
        </div>
      </div>

      <!-- Account info -->
      <div class="pp-card">
        <div class="pp-card-head"><h2>Account Info</h2></div>
        <div class="pp-account-body">
          <div class="pp-account-row">
            <span class="pp-account-label">Account ID</span>
            <span class="pp-account-val pp-mono">#<?= $profileId ?></span>
          </div>
          <div class="pp-account-row">
            <span class="pp-account-label">Member Since</span>
            <span class="pp-account-val"><?= date('M Y', strtotime($profile['created_at'] ?? 'now')) ?></span>
          </div>
          <div class="pp-account-row">
            <span class="pp-account-label">Status</span>
            <span class="pp-account-val <?= $approvalStatus['cls'] ?>"><?= $approvalStatus['icon'] ?> <?= $approvalStatus['label'] ?></span>
          </div>
        </div>
      </div>

    </aside>
  </div>
</main>

<!-- DEACTIVATION CONFIRM MODAL -->
<div class="pp-modal-overlay" id="deactivateModal" role="dialog" aria-modal="true" aria-labelledby="deactivateTitle" hidden>
  <div class="pp-modal pp-modal--danger">
    <div class="pp-modal-head">
      <h2 class="pp-modal-title" id="deactivateTitle"><i class="fa-solid fa-triangle-exclamation"></i> Deactivate Account</h2>
      <button class="pp-modal-close" onclick="document.getElementById('deactivateModal').hidden=true" aria-label="Close">✕</button>
    </div>
    <div class="pp-modal-body">
      <p>Are you sure you want to deactivate your provider account? Your profile will be hidden from customers and you won't receive new bookings.</p>
      <p class="pp-modal-note">Existing confirmed bookings will not be affected. You can reactivate by contacting QuickBook support.</p>
    </div>
    <div class="pp-modal-footer">
      <button class="pp-btn pp-btn--ghost" onclick="document.getElementById('deactivateModal').hidden=true">Cancel</button>
      <form method="POST" action="<?= BASE_URL ?>provider/profile/deactivate" style="display:inline;">
        <button type="submit" class="pp-btn pp-btn--danger">Yes, Deactivate</button>
      </form>
    </div>
  </div>
</div>

<!-- SCRIPTS -->
<script>
(function () {
  const tabs   = document.querySelectorAll('.pp-tab');
  const panels = document.querySelectorAll('.pp-tab-panel');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.tab;
      tabs.forEach(t => { t.classList.remove('is-active'); t.setAttribute('aria-selected', 'false'); });
      panels.forEach(p => { p.classList.remove('is-active'); p.hidden = true; });
      tab.classList.add('is-active'); tab.setAttribute('aria-selected', 'true');
      const panel = document.getElementById('tab-' + target);
      if (panel) { panel.classList.add('is-active'); panel.hidden = false; }
    });
  });

  // Bio character counter 
  const bio   = document.getElementById('bio');
  const count = document.getElementById('bioCount');
  if (bio && count) {
    bio.addEventListener('input', () => {
      const len = bio.value.length;
      count.textContent = len;
      count.style.color = len > 450 ? 'var(--red)' : len > 380 ? 'var(--yellow)' : '';
      if (len > 500) bio.value = bio.value.slice(0, 500);
    });
  }

  document.querySelectorAll('.pp-pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.innerHTML = input.type === 'password' ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
    });
  });

  // Password strength and match checking 
  const pwInput     = document.getElementById('new_password');
  const confirmInput = document.getElementById('confirm_password');
  const strengthBar  = document.getElementById('pwStrengthBar');
  const strengthLbl  = document.getElementById('pwStrengthLabel');
  const matchHint    = document.getElementById('pwMatchHint');

  const reqLength  = document.getElementById('req-length');
  const reqUpper   = document.getElementById('req-upper');
  const reqNumber  = document.getElementById('req-number');
  const reqSpecial = document.getElementById('req-special');

  function checkReq(el, met) {
    if (!el) return;
    el.classList.toggle('is-met', met);
  }

  if (pwInput) {
    pwInput.addEventListener('input', () => {
      const v = pwInput.value;
      const hasLen     = v.length >= 8;
      const hasUpper   = /[A-Z]/.test(v);
      const hasNum     = /\d/.test(v);
      const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(v);
      checkReq(reqLength,  hasLen);
      checkReq(reqUpper,   hasUpper);
      checkReq(reqNumber,  hasNum);
      checkReq(reqSpecial, hasSpecial);
      const score = [hasLen, hasUpper, hasNum, hasSpecial].filter(Boolean).length;
      const levels = ['', 'weak', 'fair', 'good', 'strong'];
      const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
      if (strengthBar) {
        strengthBar.className = 'pp-pw-strength-bar pp-pw-strength-bar--' + (levels[score] || '');
        strengthBar.style.width = (score * 25) + '%';
      }
      if (strengthLbl) strengthLbl.textContent = labels[score] || '';
    });
  }

  if (confirmInput) {
    confirmInput.addEventListener('input', () => {
      if (!matchHint || !pwInput) return;
      const val   = confirmInput.value;
      const match = val === pwInput.value;
      if (!val) {
        matchHint.innerHTML = '';
        matchHint.className = 'pp-match-hint';
        confirmInput.style.borderColor = '';
      } else if (match) {
        matchHint.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match';
        matchHint.className = 'pp-match-hint is-match';
        confirmInput.style.borderColor = 'rgba(74,222,128,.5)';
      } else {
        matchHint.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';
        matchHint.className = 'pp-match-hint is-no-match';
        confirmInput.style.borderColor = 'rgba(244,63,94,.5)';
      }
    });
  }

  // Profile photo upload 
  const photoInput = document.getElementById('profilePhotoInput');
  const photoForm  = document.getElementById('photoUploadForm');
  const heroAv     = document.getElementById('heroAv');

  if (photoInput && photoForm) {
    photoInput.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      // Client-side validation
      const allowed = ['image/jpeg', 'image/png', 'image/webp'];
      if (!allowed.includes(file.type)) {
        showPhotoToast('Only JPG, PNG or WebP images are allowed.', 'error');
        return;
      }
      if (file.size > 3 * 1024 * 1024) {
        showPhotoToast('Image must be under 3 MB.', 'error');
        return;
      }

      // Preview immediately
      const reader = new FileReader();
      reader.onload = function (e) {
        let img = document.getElementById('heroAvImg');
        const emoji = document.getElementById('heroAvEmoji');
        if (emoji) emoji.remove();
        if (!img) {
          img = document.createElement('img');
          img.id = 'heroAvImg';
          heroAv.appendChild(img);
        }
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);

      // Submit
      const fd = new FormData(photoForm);
      fd.append('profile_photo', file);

      const uploadBtn = document.querySelector('.pp-av-upload-btn');
      uploadBtn.classList.add('pp-av-upload-btn--loading');

      fetch(photoForm.action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          uploadBtn.classList.remove('pp-av-upload-btn--loading');
          if (data.success) {
            showPhotoToast('Profile photo updated!', 'success');

            // Update nav avatar
            const navAv       = document.getElementById('navAv');
            const navInitials = document.getElementById('navAvInitials');
            let   navImg      = document.getElementById('navAvImg');
            if (navInitials) navInitials.remove();
            if (!navImg) {
              navImg    = document.createElement('img');
              navImg.id = 'navAvImg';
              navAv.appendChild(navImg);
            }
            navImg.src = document.getElementById('heroAvImg').src;
          } else {
            showPhotoToast(data.error || 'Upload failed.', 'error');
          }
        })
        .catch(() => {
          uploadBtn.classList.remove('pp-av-upload-btn--loading');
          showPhotoToast('Upload failed. Please try again.', 'error');
        });
    });
  }

  function showPhotoToast(msg, type) {
    const existing = document.getElementById('pp-photo-toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.id = 'pp-photo-toast';
    toast.className = 'pp-photo-toast pp-photo-toast--' + type;
    toast.innerHTML = (type === 'success'
      ? '<i class="fa-solid fa-circle-check"></i> '
      : '<i class="fa-solid fa-triangle-exclamation"></i> ') + msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('pp-photo-toast--show'), 10);
    setTimeout(() => { toast.classList.remove('pp-photo-toast--show'); setTimeout(() => toast.remove(), 400); }, 3500);
  }

  // Deactivate modal 
  window.confirmDeactivate = function () {
    document.getElementById('deactivateModal').hidden = false;
  };

  document.getElementById('deactivateModal')?.addEventListener('click', function (e) {
    if (e.target === this) this.hidden = true;
  });

  document.addEventListener('keydown', function (e) {
    const m = document.getElementById('deactivateModal');
    if (e.key === 'Escape' && m && !m.hidden) m.hidden = true;
  });

  // Prevent double submit on forms 
  document.querySelectorAll('.pp-form').forEach(form => {
    form.addEventListener('submit', function () {
      const btn = this.querySelector('[type="submit"]');
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="pp-btn-icon"><i class="fa-solid fa-spinner fa-spin"></i></span> Saving…'; }
    });
  });

  window.resetPasswordForm = function () {
    ['current_password','new_password','confirm_password'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    if (strengthBar) { strengthBar.className = 'pp-pw-strength-bar'; strengthBar.style.width = '0'; }
    if (strengthLbl) strengthLbl.textContent = '';
    if (matchHint)   matchHint.textContent   = '';
  };

})();
</script>

</body>
</html>