<?php

require_once __DIR__ . '/../../../config/database.php';
$db       = Database::getInstance();
$userId   = (int)($_SESSION['user_id'] ?? 0);
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Customer');
$initials = strtoupper(substr($userName, 0, 2));

$stAv = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAv->execute([$userId]);
$avatarUrl = ($av = $stAv->fetchColumn()) ? BASE_URL . 'assets/uploads/profiles/' . htmlspecialchars($av) : null;

$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$loyaltyTier   = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};

$stUpcoming = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE()");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();

$stCustAddr = $db->prepare("SELECT address, phone FROM tbl_users WHERE id = ? LIMIT 1");
$stCustAddr->execute([$userId]);
$custRow = $stCustAddr->fetch();
$customerSavedAddress = htmlspecialchars($custRow['address'] ?? '');

$catIconMap = [
    'barbershop'       => '<i class="fa-solid fa-scissors"></i>',
    'hair-salon'       => '<i class="fa-solid fa-scissors"></i>',
    'nail-care'        => '<i class="fa-solid fa-hand-sparkles"></i>',
    'massage-therapy'  => '<i class="fa-solid fa-spa"></i>',
    'skincare-facial'  => '<i class="fa-solid fa-pump-soap"></i>',
    'fitness-training' => '<i class="fa-solid fa-dumbbell"></i>',
    'home-cleaning'    => '<i class="fa-solid fa-broom"></i>',
    'pet-grooming'     => '<i class="fa-solid fa-paw"></i>',
    'event-styling'    => '<i class="fa-solid fa-palette"></i>',
    'makeup'           => '<i class="fa-solid fa-wand-sparkles"></i>',
];
$icon = $catIconMap[$service['category_slug'] ?? ''] ?? '<i class="fa-solid fa-screwdriver-wrench"></i>';

$providerInitials = strtoupper(
    substr($service['provider_first'], 0, 1) .
    substr($service['provider_last'],  0, 1)
);

$durationLabel = '';
if (!empty($service['duration_minutes'])) {
    $mins = (int)$service['duration_minutes'];
    $durationLabel = $mins >= 60
        ? ($mins % 60 === 0 ? ($mins / 60) . ' hr' : floor($mins / 60) . 'h ' . ($mins % 60) . 'm')
        : $mins . ' min';
}

$availableDays   = array_column($availability, 'day_of_week');
$availableDaysJs = json_encode($availableDays);

$hoursMap = [];
foreach ($availability as $av) {
    $hoursMap[$av['day_of_week']] = date('g:i A', strtotime($av['start_time'])) . ' – ' . date('g:i A', strtotime($av['end_time']));
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$svcLocType = $service['location_type'] ?? 'In-shop';
// Use provider address as shop address for In-shop / Flexible
$shopAddr = $service['address'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — <?= htmlspecialchars($service['name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_browse.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_service_detail.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>

<!-- NAV -->
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
      <a href="<?= BASE_URL ?>browse"     class="pv-nav-link is-active">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty"    class="pv-nav-link">Loyalty</a>
      <a href="<?= BASE_URL ?>profile"    class="pv-nav-link">Profile</a>
    </div>
    <div class="pv-nav-end">
      <button class="pv-notif-btn" aria-label="Notifications">
        <i class="fa-solid fa-bell"></i>
        <span class="pv-notif-dot" aria-hidden="true"></span>
      </button>
      <div class="pv-nav-av" aria-hidden="true">
        <?php if ($avatarUrl): ?>
          <img src="<?= $avatarUrl ?>" alt="<?= $userName ?>" style="width:34px;height:34px;object-fit:cover;border-radius:99px;display:block;">
        <?php else: ?>
          <?= $initials ?>
        <?php endif; ?>
      </div>
      <div class="pv-nav-user">
        <div class="pv-nav-user-name"><?= $userName ?></div>
        <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<header class="sd-hero pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner sd-hero-inner">
    <div>
      <p class="sd-hero-eyebrow">
        <span class="pulse" aria-hidden="true"></span>
        <?= htmlspecialchars($service['category_name'] ?? 'Service') ?>
      </p>
      <h1><?= htmlspecialchars($service['name']) ?></h1>
      <div class="sd-hero-chips">
        <?php if (!empty($durationLabel)): ?>
          <span class="sd-hero-chip">
            <i class="fa-regular fa-clock"></i> <?= $durationLabel ?>
          </span>
        <?php endif; ?>
        <?php
          $locTypeLabel = match(strtolower($svcLocType)) {
            'on-site'  => ['<i class="fa-solid fa-house"></i> Home Service', ''],
            'remote'   => ['<i class="fa-solid fa-wifi"></i> Remote', ''],
            'flexible' => ['<i class="fa-solid fa-arrows-up-down-left-right"></i> Flexible', ''],
            default    => ['<i class="fa-solid fa-store"></i> In-shop', ''],
          };
        ?>
        <span class="sd-hero-chip"><?= $locTypeLabel[0] ?></span>
        <span class="sd-hero-chip sd-hero-chip--green">
          <i class="fa-solid fa-circle-check"></i> Available
        </span>
      </div>
    </div>
    <a href="<?= BASE_URL ?>browse" class="sd-back-btn">
      <i class="fa-solid fa-arrow-left"></i> Back to Browse
    </a>
  </div>
</header>


<div class="sd-breadcrumb-wrap">
  <nav class="sd-breadcrumb" aria-label="Breadcrumb">
    <a href="<?= BASE_URL ?>browse">Browse</a>
    <span aria-hidden="true">›</span>
    <span><?= htmlspecialchars($service['name']) ?></span>
  </nav>
</div>

<main class="sd-page">

  <?php if ($flash): ?>
  <div class="sd-flash sd-flash--<?= htmlspecialchars($flash['type']) ?>" role="alert">
    <?= $flash['type'] === 'success'
        ? '<i class="fa-solid fa-circle-check"></i>'
        : '<i class="fa-solid fa-triangle-exclamation"></i>' ?>
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <div class="sd-grid">

    <div class="sd-main">

      <!-- SERVICE OVERVIEW CARD -->
      <div class="sd-card">
        <div class="sd-card-head">
          <div class="sd-svc-av"><?= $icon ?></div>
          <div class="sd-svc-identity">
            <div class="sd-svc-title"><?= htmlspecialchars($service['name']) ?></div>
            <?php if ((float)$service['avg_rating'] > 0): ?>
            <div class="sd-svc-rating-row">
              <span class="sd-stars">
                <?php
                  $r = round((float)$service['avg_rating'] * 2) / 2;
                  for ($i = 1; $i <= 5; $i++) {
                      echo $r >= $i ? '★' : ($r >= $i - 0.5 ? '½' : '☆');
                  }
                ?>
              </span>
              <span class="sd-rating-val"><?= number_format((float)$service['avg_rating'], 1) ?></span>
              <span class="sd-review-count">(<?= (int)$service['total_reviews'] ?> review<?= $service['total_reviews'] != 1 ? 's' : '' ?>)</span>
            </div>
            <?php else: ?>
              <div style="font-size:.76rem;color:var(--faint);">No reviews yet</div>
            <?php endif; ?>
          </div>
          <div class="sd-price-badge">
            <div class="sd-price-val">₱<?= number_format((float)$service['price'], 2) ?></div>
            <?php if (!empty($durationLabel)): ?>
              <div class="sd-price-per">per <?= $durationLabel ?></div>
            <?php endif; ?>
          </div>
        </div>


        <div class="sd-detail-chips">
          <?php if (!empty($durationLabel)): ?>
          <div class="sd-detail-chip">
            <div class="sd-detail-chip-text">
              <span class="sd-detail-chip-label">Duration</span>
              <span class="sd-detail-chip-val"><?= $durationLabel ?></span>
            </div>
            <span class="sd-detail-chip-icon"><i class="fa-regular fa-clock"></i></span>
          </div>
          <?php endif; ?>
          <div class="sd-detail-chip">
            <div class="sd-detail-chip-text">
              <span class="sd-detail-chip-label">Service Type</span>
              <span class="sd-detail-chip-val"><?= htmlspecialchars($svcLocType) ?></span>
            </div>
            <span class="sd-detail-chip-icon"><i class="fa-solid fa-location-dot"></i></span>
          </div>
          <?php if (!empty($service['service_type'])): ?>
          <div class="sd-detail-chip">
            <div class="sd-detail-chip-text">
              <span class="sd-detail-chip-label">Provider Type</span>
              <span class="sd-detail-chip-val"><?= htmlspecialchars($service['service_type']) ?></span>
            </div>
            <span class="sd-detail-chip-icon"><i class="fa-solid fa-tag"></i></span>
          </div>
          <?php endif; ?>
          <div class="sd-detail-chip">
            <div class="sd-detail-chip-text">
              <span class="sd-detail-chip-label">Price</span>
              <span class="sd-detail-chip-val">₱<?= number_format((float)$service['price'], 2) ?></span>
            </div>
            <span class="sd-detail-chip-icon"><i class="fa-solid fa-peso-sign"></i></span>
          </div>
        </div>

        <!-- Description -->
        <?php if (!empty($service['description'])): ?>
        <div class="sd-description">
          <div class="sd-section-label">About this service</div>
          <p><?= nl2br(htmlspecialchars($service['description'])) ?></p>
        </div>
        <?php endif; ?>
      </div>

      <!-- PROVIDER CARD -->
      <div class="sd-card">
        <div class="sd-provider-card">
          <div class="sd-section-label">Your Provider</div>
          <div class="sd-provider-row">
            <div class="sd-provider-av" style="overflow:hidden;display:flex;align-items:center;justify-content:center;font-weight:800;">
              <?php if (!empty($service['profile_photo'])): ?>
                <img src="<?= BASE_URL ?>assets/uploads/profiles/<?= htmlspecialchars($service['profile_photo']) ?>"
                     alt="<?= htmlspecialchars($service['provider_first']) ?>"
                     style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;">
              <?php else: ?>
                <?= $providerInitials ?>
              <?php endif; ?>
            </div>
            <div class="sd-provider-info">
              <div class="sd-provider-name"><?= htmlspecialchars($service['provider_first'] . ' ' . $service['provider_last']) ?></div>
              <?php if (!empty($service['avg_rating']) && (float)$service['avg_rating'] > 0): ?>
              <div class="sd-provider-person" style="margin-top:.1rem">
                <span style="color:var(--gold);">★ <?= number_format((float)$service['avg_rating'],1) ?></span>
                <span style="color:var(--faint);"> · <?= (int)$service['total_reviews'] ?> review<?= $service['total_reviews'] != 1 ? 's' : '' ?></span>
              </div>
              <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>providers/<?= (int)$service['profile_id'] ?>" class="sd-view-profile-btn">
              <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:.65rem"></i>
              View Profile
            </a>
          </div>

          <?php if (!empty($service['bio'])): ?>
            <p class="sd-provider-bio"><?= htmlspecialchars($service['bio']) ?></p>
          <?php endif; ?>

          <!-- Show shop address for In-shop or Flexible -->
          <?php if (in_array($svcLocType, ['In-shop', 'Flexible']) && !empty($shopAddr)): ?>
          <div class="sd-shop-address-block">
            <div class="addr-icon"><i class="fa-solid fa-map-pin"></i></div>
            <div class="addr-body">
              <div class="addr-label">Shop Address</div>
              <div class="addr-text"><?= htmlspecialchars($shopAddr) ?></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- AVAILABILITY CARD -->
      <?php if (!empty($availability)): ?>
      <div class="sd-card">
        <div class="sd-provider-card">
          <div class="sd-section-label">Availability</div>
          <div class="sd-avail-grid">
            <?php foreach ($availability as $av): ?>
            <div class="sd-avail-pill">
              <div class="sd-avail-day"><?= htmlspecialchars($av['day_of_week']) ?></div>
              <div class="sd-avail-time">
                <?= date('g:i A', strtotime($av['start_time'])) ?>
                <span style="color:var(--faint);margin:0 .3rem">–</span>
                <?= date('g:i A', strtotime($av['end_time'])) ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>


    <aside class="sd-sidebar">
      <div class="sd-card sd-book-card">

        <div class="sd-book-header">
          <div>
            <div class="sd-book-title">Book Service</div>
          </div>
          <span class="sd-status-badge">
            <span class="dot"></span> Active
          </span>
        </div>

        <form method="POST" action="<?= BASE_URL ?>book" class="sd-book-form" id="bookingForm">
          <input type="hidden" name="service_id"  value="<?= (int)$service['id'] ?>">
          <input type="hidden" name="provider_id" value="<?= (int)$service['profile_id'] ?>">

          <!-- DATE -->
          <div class="sd-form-group">
            <label class="sd-form-label" for="formDate">
              <i class="fa-regular fa-calendar"></i> Booking Date <span class="sd-req"></span>
            </label>
            <input type="date" class="sd-form-control" id="formDate" name="booking_date"
                   min="<?= date('Y-m-d') ?>" required>
            <div id="dateWarning" class="sd-date-warning" style="display:none">
              <i class="fa-solid fa-triangle-exclamation"></i> Provider is not available on this day.
            </div>
          </div>

          <!-- TIME -->
          <div class="sd-form-group">
            <label class="sd-form-label" for="formTime">
              <i class="fa-regular fa-clock"></i> Preferred Time <span class="sd-req"></span>
            </label>
            <input type="time" class="sd-form-control" id="formTime" name="booking_time" required>
            <div id="timeHint" style="font-size:.72rem;color:var(--faint);margin-top:.15rem"></div>
          </div>

          <!-- ══ LOCATION SECTION ══ -->

          <?php if ($svcLocType === 'On-site'): ?>
            <!-- ON-SITE: provider comes to customer -->
            <input type="hidden" name="location_type" value="On-site">
            <div class="sd-form-group">
              <label class="sd-form-label" for="customerAddress">
                <i class="fa-solid fa-location-dot"></i> Your Address <span class="sd-req"></span>
              </label>
              <div class="sd-loc-panel sd-loc-panel--onsite" style="margin-bottom:.45rem">
                <div class="panel-icon"><i class="fa-solid fa-house-chimney-medical"></i></div>
                <div class="panel-body">
                  <div class="panel-title">Provider comes to you</div>
                  <div class="panel-desc">Enter the address where you'd like the service done.</div>
                </div>
              </div>
              <input type="text" class="sd-form-control" id="customerAddress" name="customer_address"
                     placeholder="e.g. 123 Rizal St, Bacolod City" required autocomplete="street-address"
                     value="<?= $customerSavedAddress ?>">
              <?php if ($customerSavedAddress): ?>
              <div class="sd-addr-hint sd-addr-hint--gold">
                <i class="fa-solid fa-circle-check"></i> Pre-filled from your profile — update if different
              </div>
              <?php else: ?>
              <div class="sd-addr-hint sd-addr-hint--faint">
                <i class="fa-solid fa-circle-info"></i>
                Your address will be saved and the provider will see it for this booking
              </div>
              <?php endif; ?>
            </div>

          <?php elseif ($svcLocType === 'In-shop'): ?>
            <!-- IN-SHOP: customer visits the shop -->
            <input type="hidden" name="location_type" value="In-shop">
            <div class="sd-form-group">
              <label class="sd-form-label">
                <i class="fa-solid fa-store"></i> Shop Location
              </label>
              <div class="sd-loc-panel sd-loc-panel--inshop">
                <div class="panel-icon"><i class="fa-solid fa-map-pin"></i></div>
                <div class="panel-body">
                  <div class="panel-title">Visit the provider's shop</div>
                  <?php if (!empty($shopAddr)): ?>
                    <div class="panel-desc" style="color:rgba(74,222,128,.9);font-weight:600"><?= htmlspecialchars($shopAddr) ?></div>
                  <?php else: ?>
                    <div class="panel-desc">The provider will share exact location upon confirmation.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

          <?php elseif ($svcLocType === 'Remote'): ?>
            <!-- REMOTE: online session -->
            <input type="hidden" name="location_type" value="Remote">
            <div class="sd-form-group">
              <label class="sd-form-label"><i class="fa-solid fa-wifi"></i> Service Mode</label>
              <div class="sd-loc-panel sd-loc-panel--remote">
                <div class="panel-icon"><i class="fa-solid fa-video"></i></div>
                <div class="panel-body">
                  <div class="panel-title">Online / Remote Session</div>
                  <div class="panel-desc">No address needed. Provider will send meeting details after confirmation.</div>
                </div>
              </div>
            </div>

          <?php elseif ($svcLocType === 'Flexible'): ?>
            <!-- FLEXIBLE: customer chooses -->
            <div class="sd-form-group">
              <div class="sd-loc-selector-label">
                <i class="fa-solid fa-location-dot"></i>
                Where would you like the service? <span class="sd-req"></span>
              </div>

              <div class="sd-loc-tabs">
                <label class="sd-loc-tab">
                  <input type="radio" name="location_type" value="In-shop" checked onchange="handleFlexLoc(this.value)">
                  <div class="sd-loc-tab-box">
                    <div class="sd-loc-tab-icon"></div>
                    <div class="sd-loc-tab-label">In-shop</div>
                  </div>
                </label>
                <label class="sd-loc-tab">
                  <input type="radio" name="location_type" value="On-site" onchange="handleFlexLoc(this.value)">
                  <div class="sd-loc-tab-box">
                    <div class="sd-loc-tab-icon"></div>
                    <div class="sd-loc-tab-label">Home Service</div>
                  </div>
                </label>
                <label class="sd-loc-tab">
                  <input type="radio" name="location_type" value="Remote" onchange="handleFlexLoc(this.value)">
                  <div class="sd-loc-tab-box">
                    <div class="sd-loc-tab-icon"></div>
                    <div class="sd-loc-tab-label">Remote</div>
                  </div>
                </label>
              </div>

              <!-- In-shop panel (default) -->
              <div id="flexInshopPanel">
                <div class="sd-loc-panel sd-loc-panel--inshop">
                  <div class="panel-icon"><i class="fa-solid fa-map-pin"></i></div>
                  <div class="panel-body">
                    <div class="panel-title">Visit the provider's shop</div>
                    <?php if (!empty($shopAddr)): ?>
                      <div class="panel-desc" style="color:rgba(74,222,128,.9);font-weight:600"><?= htmlspecialchars($shopAddr) ?></div>
                    <?php else: ?>
                      <div class="panel-desc">Address will be confirmed upon booking.</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- On-site panel -->
              <div id="flexOnsitePanel" style="display:none">
                <div class="sd-loc-panel sd-loc-panel--onsite" style="margin-bottom:.5rem">
                  <div class="panel-icon"><i class="fa-solid fa-house-chimney-medical"></i></div>
                  <div class="panel-body">
                    <div class="panel-title">Provider comes to you</div>
                    <div class="panel-desc">Enter the address where you'd like the service done.</div>
                  </div>
                </div>
                <div class="sd-addr-group">
                  <input type="text" class="sd-form-control" id="flexCustomerAddress" name="customer_address"
                         placeholder="e.g. 123 Rizal St, Bacolod City" autocomplete="street-address"
                         value="<?= $customerSavedAddress ?>">
                  <?php if ($customerSavedAddress): ?>
                  <div class="sd-addr-hint sd-addr-hint--gold">
                    <i class="fa-solid fa-circle-check"></i> Pre-filled from your profile — update if different
                  </div>
                  <?php else: ?>
                  <div class="sd-addr-hint sd-addr-hint--faint">
                    <i class="fa-solid fa-circle-info"></i>
                    Your address will be saved and the provider will see it for this booking
                  </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Remote panel -->
              <div id="flexRemotePanel" style="display:none">
                <div class="sd-loc-panel sd-loc-panel--remote">
                  <div class="panel-icon"><i class="fa-solid fa-video"></i></div>
                  <div class="panel-body">
                    <div class="panel-title">Online / Remote Session</div>
                    <div class="panel-desc">No address needed. Provider sends meeting details after confirmation.</div>
                  </div>
                </div>
              </div>
            </div>

          <?php else: ?>
            <input type="hidden" name="location_type" value="In-shop">
          <?php endif; ?>

          <!-- NOTES -->
          <div class="sd-form-group">
            <label class="sd-form-label" for="formNotes">
              <i class="fa-regular fa-note-sticky"></i> Notes
              <span class="sd-form-hint">optional</span>
            </label>
            <textarea class="sd-form-control sd-textarea" id="formNotes" name="notes"
                      rows="3" placeholder="Any special requests for the provider…"></textarea>
          </div>

          <!-- PAYMENT METHOD -->
          <div class="sd-form-group">
            <label class="sd-form-label">
              <i class="fa-solid fa-credit-card"></i> Payment Method <span class="sd-req"></span>
            </label>
            <div class="sd-pay-grid">
              <label class="sd-pay-option">
                <input type="radio" name="payment_method" value="gcash" required>
                <div class="sd-pay-box">
                  <div class="sd-pay-logo sd-pay-logo--gcash">
                    <svg width="22" height="22" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="20" r="20" fill="#007DFF"/><text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" font-size="11" font-weight="800" fill="white" font-family="Arial">G</text></svg>
                  </div>
                  <span class="sd-pay-name">GCash</span>
                </div>
              </label>
              <label class="sd-pay-option">
                <input type="radio" name="payment_method" value="paymaya">
                <div class="sd-pay-box">
                  <div class="sd-pay-logo sd-pay-logo--paymaya">
                    <svg width="22" height="22" viewBox="0 0 40 40" fill="none"><circle cx="20" cy="20" r="20" fill="#6B3FA0"/><text x="50%" y="56%" dominant-baseline="middle" text-anchor="middle" font-size="11" font-weight="800" fill="white" font-family="Arial">M</text></svg>
                  </div>
                  <span class="sd-pay-name">PayMaya</span>
                </div>
              </label>
              <label class="sd-pay-option">
                <input type="radio" name="payment_method" value="card">
                <div class="sd-pay-box">
                  <div class="sd-pay-logo sd-pay-logo--card">
                    <i class="fa-solid fa-credit-card" style="color:#60a5fa;font-size:1rem;"></i>
                  </div>
                  <span class="sd-pay-name">Card</span>
                </div>
              </label>
              <label class="sd-pay-option">
                <input type="radio" name="payment_method" value="cash">
                <div class="sd-pay-box">
                  <div class="sd-pay-logo sd-pay-logo--cash">
                    <i class="fa-solid fa-money-bill-wave" style="color:#4ade80;font-size:1rem;"></i>
                  </div>
                  <span class="sd-pay-name">Cash</span>
                </div>
              </label>
            </div>
            <!-- Card details panel -->
            <div id="cardDetailsPanel" style="display:none;margin-top:.6rem">
              <div style="background:rgba(96,165,250,.06);border:1px solid rgba(96,165,250,.2);border-radius:10px;padding:.85rem 1rem;margin-bottom:.6rem">
                <div style="display:flex;align-items:center;gap:.4rem;font-size:.72rem;color:#60a5fa;font-weight:600;margin-bottom:.3rem">
                  <i class="fa-solid fa-lock" style="font-size:.65rem"></i> Secured via encrypted payment gateway
                </div>
                <div style="font-size:.7rem;color:var(--faint)">Your card details are never stored on our servers.</div>
              </div>
              <input type="text" class="sd-form-control" id="cardNumber" name="card_number"
                     placeholder="Card number" maxlength="19" autocomplete="cc-number"
                     style="margin-bottom:.45rem">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.45rem">
                <input type="text" class="sd-form-control" name="card_expiry"
                       placeholder="MM / YY" maxlength="7" autocomplete="cc-exp">
                <input type="text" class="sd-form-control" name="card_cvv"
                       placeholder="CVV" maxlength="4" autocomplete="cc-csc">
              </div>
              <input type="text" class="sd-form-control" name="card_name"
                     placeholder="Name on card" autocomplete="cc-name"
                     style="margin-top:.45rem">
            </div>
            <!-- GCash / PayMaya info panel -->
            <div id="ewalletInfoPanel" style="display:none;margin-top:.6rem">
              <div class="sd-loc-panel" id="ewalletPanel" style="background:rgba(0,125,255,.07);border-color:rgba(0,125,255,.25);color:#60a5fa">
                <div class="panel-icon" style="background:rgba(0,125,255,.12)"><i class="fa-solid fa-mobile-screen"></i></div>
                <div class="panel-body">
                  <div class="panel-title" id="ewalletTitle">Pay via GCash</div>
                  <div class="panel-desc">You'll receive a payment link after booking confirmation.</div>
                </div>
              </div>
            </div>
            <!-- Cash info panel -->
            <div id="cashInfoPanel" style="display:none;margin-top:.6rem">
              <div class="sd-loc-panel sd-loc-panel--inshop">
                <div class="panel-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                <div class="panel-body">
                  <div class="panel-title">Pay on service day</div>
                  <div class="panel-desc">Bring exact cash. Provider confirms upon arrival.</div>
                </div>
              </div>
            </div>
          </div>

          <!-- SUMMARY -->
          <?php
            $serviceFee   = 50.00;
            $basePrice    = (float)$service['price'];
            $isOnSite     = ($svcLocType === 'On-site');
            $isFlexible   = ($svcLocType === 'Flexible');
            $totalDefault = $isOnSite ? $basePrice + $serviceFee : $basePrice;
          ?>
          <div class="sd-book-summary">
            <div class="sd-summary-row">
              <span>Amount</span>
              <span style="font-weight:600;color:var(--off)">₱<?= number_format($basePrice, 2) ?></span>
            </div>
            <?php if ($isOnSite || $isFlexible): ?>
            <div class="sd-summary-row" id="homeServiceFeeRow"<?= $isFlexible ? ' style="display:none"' : '' ?>>
              <span style="display:flex;align-items:center;gap:.35rem">
                <i class="fa-solid fa-house" style="color:var(--gold);font-size:.7rem"></i> Home service fee
              </span>
              <span style="color:#fbbf24">+₱<?= number_format($serviceFee, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="sd-summary-divider"></div>
            <div class="sd-summary-row sd-summary-total">
              <span>Total</span>
              <span id="summaryTotal" style="color:var(--gold-bright)">₱<?= number_format($totalDefault, 2) ?></span>
            </div>
            <input type="hidden" name="total_amount" id="totalAmountInput" value="<?= $totalDefault ?>">
            <div class="sd-loyalty-note">
              <i class="fa-solid fa-star"></i> You'll earn 10 loyalty points for this booking!
            </div>
          </div>

          <button type="submit" class="sd-submit-btn" id="submitBtn">
            <i class="fa-solid fa-calendar-check"></i> Confirm Booking
          </button>
          <p class="sd-submit-note">
            The provider will confirm your appointment shortly after submission.
          </p>

        </form>
      </div>
    </aside>

  </div>
</main>

<script>
const availableDays = <?= $availableDaysJs ?>;
const hoursMap      = <?= json_encode($hoursMap) ?>;
const dayNames      = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

const dateInput   = document.getElementById('formDate');
const dateWarning = document.getElementById('dateWarning');
const timeHint    = document.getElementById('timeHint');
const submitBtn   = document.getElementById('submitBtn');

dateInput.addEventListener('change', function () {
  if (!this.value) return;
  const dayName     = dayNames[new Date(this.value + 'T00:00:00').getDay()];
  const isAvailable = availableDays.includes(dayName);

  dateWarning.style.display = isAvailable ? 'none' : 'flex';
  submitBtn.disabled = !isAvailable;

  if (isAvailable && hoursMap[dayName]) {
    timeHint.textContent = '✓ Available ' + hoursMap[dayName] + ' on ' + dayName + 's';
    timeHint.style.color = 'var(--gold)';
  } else {
    timeHint.textContent = '';
  }
});

document.getElementById('bookingForm').addEventListener('submit', function () {
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
});


document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
  radio.addEventListener('change', function() {
    const val = this.value;
    const cardPanel    = document.getElementById('cardDetailsPanel');
    const ewalletPanel = document.getElementById('ewalletInfoPanel');
    const cashPanel    = document.getElementById('cashInfoPanel');
    const ewalletTitle = document.getElementById('ewalletTitle');

    if (cardPanel)    cardPanel.style.display    = val === 'card'   ? 'block' : 'none';
    if (ewalletPanel) ewalletPanel.style.display = (val === 'gcash' || val === 'paymaya') ? 'block' : 'none';
    if (cashPanel)    cashPanel.style.display    = val === 'cash'   ? 'block' : 'none';

    if (ewalletTitle) {
      ewalletTitle.textContent = val === 'gcash' ? 'Pay via GCash' : 'Pay via PayMaya';
    }
    const ep = document.getElementById('ewalletPanel');
    if (ep && val === 'paymaya') {
      ep.style.background = 'rgba(107,63,160,.07)';
      ep.style.borderColor = 'rgba(107,63,160,.3)';
      ep.style.color = '#c084fc';
      ep.querySelector('.panel-icon').style.background = 'rgba(107,63,160,.12)';
    } else if (ep && val === 'gcash') {
      ep.style.background = 'rgba(0,125,255,.07)';
      ep.style.borderColor = 'rgba(0,125,255,.25)';
      ep.style.color = '#60a5fa';
      ep.querySelector('.panel-icon').style.background = 'rgba(0,125,255,.12)';
    }

    // Card number formatting
    const cardNum = document.getElementById('cardNumber');
    if (cardNum) {
      cardNum.required = val === 'card';
    }
  });
});

// Card number formatter
const cardNumInput = document.getElementById('cardNumber');
if (cardNumInput) {
  cardNumInput.addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '').slice(0, 16);
    this.value = v.replace(/(.{4})/g, '$1 ').trim();
  });
}


const BASE_PRICE   = <?= $basePrice ?>;
const SERVICE_FEE  = <?= $serviceFee ?>;

function updateTotal(includeHomeFee) {
  const feeRow   = document.getElementById('homeServiceFeeRow');
  const totalEl  = document.getElementById('summaryTotal');
  const totalInp = document.getElementById('totalAmountInput');
  const total    = includeHomeFee ? BASE_PRICE + SERVICE_FEE : BASE_PRICE;
  if (feeRow)   feeRow.style.display  = includeHomeFee ? 'flex' : 'none';
  if (totalEl)  totalEl.textContent   = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  if (totalInp) totalInp.value        = total.toFixed(2);
}

function handleFlexLoc(val) {
  const inshop  = document.getElementById('flexInshopPanel');
  const onsite  = document.getElementById('flexOnsitePanel');
  const remote  = document.getElementById('flexRemotePanel');
  const addrIn  = document.getElementById('flexCustomerAddress');

  if (inshop) inshop.style.display = val === 'In-shop' ? 'block' : 'none';
  if (onsite) onsite.style.display = val === 'On-site' ? 'block' : 'none';
  if (remote) remote.style.display = val === 'Remote'  ? 'block' : 'none';
  if (addrIn) addrIn.required      = val === 'On-site';

  updateTotal(val === 'On-site');
}
</script>

</body>
</html>