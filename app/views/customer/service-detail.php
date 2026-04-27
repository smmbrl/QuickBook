<?php

require_once __DIR__ . '/../../../config/database.php';
$db       = Database::getInstance();
$userId   = (int)($_SESSION['user_id'] ?? 0);
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Customer');
$initials = strtoupper(substr($userName, 0, 2));

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

$availableDays = array_column($availability, 'day_of_week');
$availableDaysJs = json_encode($availableDays);

$hoursMap = [];
foreach ($availability as $av) {
    $hoursMap[$av['day_of_week']] = date('g:i A', strtotime($av['start_time'])) . ' – ' . date('g:i A', strtotime($av['end_time']));
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
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
      <div class="pv-points-badge">⭐ <?= number_format($loyaltyPoints) ?> pts</div>
      <button class="pv-notif-btn" aria-label="Notifications"><i class="fa-solid fa-bell"></i><span class="pv-notif-dot" aria-hidden="true"></span></button>
      <div class="pv-nav-av" aria-hidden="true"><?= $initials ?></div>
      <div class="pv-nav-user">
        <div class="pv-nav-user-name"><?= $userName ?></div>
        <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>
  </div>
</nav>

<header class="pv-hero sd-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner sd-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        <?= htmlspecialchars($service['category_name'] ?? 'Service') ?>
      </p>
      <h1 class="pv-hero-name"><?= htmlspecialchars($service['name']) ?></h1>
      <div class="sd-hero-meta">
        <span class="sd-provider-chip">
          <i class="fa-solid fa-store"></i> <?= htmlspecialchars($service['business_name']) ?>
        </span>
        <?php if (!empty($service['category_name'])): ?>
          <span class="sd-chip"><?= htmlspecialchars($service['category_name']) ?></span>
        <?php endif; ?>
        <?php if (!empty($durationLabel)): ?>
          <span class="sd-chip"><i class="fa-regular fa-clock"></i> <?= $durationLabel ?></span>
        <?php endif; ?>
        <span class="sd-chip sd-chip--green"><i class="fa-solid fa-circle-check"></i> Available</span>
      </div>
    </div>
    <a href="<?= BASE_URL ?>browse<?= !empty($service['category_slug']) ? '/' . htmlspecialchars($service['category_slug']) : '' ?>" class="sd-back-btn">
      ← Back to Browse
    </a>
  </div>
</header>

<div class="sd-breadcrumb-wrap">
  <nav class="sd-breadcrumb" aria-label="Breadcrumb">
    <a href="<?= BASE_URL ?>browse">Browse</a>
    <span aria-hidden="true">›</span>
    <?php if (!empty($service['category_name'])): ?>
      <a href="<?= BASE_URL ?>browse/<?= htmlspecialchars($service['category_slug']) ?>">
        <?= htmlspecialchars($service['category_name']) ?>
      </a>
      <span aria-hidden="true">›</span>
    <?php endif; ?>
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

      <div class="sd-card">
        <div class="sd-card-head">
          <div class="sd-svc-av"><?= $icon ?></div>
          <div class="sd-svc-identity">
            <div class="sd-svc-title"><?= htmlspecialchars($service['name']) ?></div>
            <?php if ((float)$service['avg_rating'] > 0): ?>
            <div class="sd-svc-rating-row">
              <span class="sd-stars">
                <?php
                  $rating = round((float)$service['avg_rating'] * 2) / 2;
                  for ($i = 1; $i <= 5; $i++) {
                      if ($rating >= $i) echo '★';
                      elseif ($rating >= $i - 0.5) echo '½';
                      else echo '☆';
                  }
                ?>
              </span>
              <span class="sd-rating-val"><?= number_format((float)$service['avg_rating'], 1) ?></span>
              <span class="sd-review-count">(<?= (int)$service['total_reviews'] ?> review<?= $service['total_reviews'] != 1 ? 's' : '' ?>)</span>
            </div>
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
            <span class="sd-detail-chip-icon"><i class="fa-regular fa-clock"></i></span>
            <span class="sd-detail-chip-label">Duration</span>
            <span class="sd-detail-chip-val"><?= $durationLabel ?></span>
          </div>
          <?php endif; ?>
          <div class="sd-detail-chip">
            <span class="sd-detail-chip-icon"><i class="fa-solid fa-location-dot"></i></span>
            <span class="sd-detail-chip-label">Location</span>
            <span class="sd-detail-chip-val"><?= htmlspecialchars($service['location_type']) ?></span>
          </div>
          <div class="sd-detail-chip">
            <span class="sd-detail-chip-icon"><i class="fa-solid fa-tag"></i></span>
            <span class="sd-detail-chip-label">Type</span>
            <span class="sd-detail-chip-val"><?= htmlspecialchars($service['service_type']) ?></span>
          </div>
          <?php if ($service['offers_home_service']): ?>
          <div class="sd-detail-chip">
            <span class="sd-detail-chip-icon"><i class="fa-solid fa-house"></i></span>
            <span class="sd-detail-chip-label">Home Service</span>
            <span class="sd-detail-chip-val" style="color:#4ADE80">Available</span>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($service['description'])): ?>
        <div class="sd-description">
          <div class="sd-section-label">About this service</div>
          <p><?= nl2br(htmlspecialchars($service['description'])) ?></p>
        </div>
        <?php endif; ?>
      </div>

      <div class="sd-card">
        <div class="sd-provider-card">
          <div class="sd-section-label">Your Provider</div>
          <div class="sd-provider-row">
            <div class="sd-provider-av"><?= $providerInitials ?></div>
            <div class="sd-provider-info">
              <div class="sd-provider-name"><?= htmlspecialchars($service['business_name']) ?></div>
              <div class="sd-provider-person">
                <?= htmlspecialchars($service['provider_first'] . ' ' . $service['provider_last']) ?>
              </div>
              <?php if (!empty($service['address'])): ?>
                <div class="sd-provider-loc">
                  <i class="fa-solid fa-location-dot"></i>
                  <?= htmlspecialchars($service['address']) ?>
                </div>
              <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>providers/<?= (int)$service['profile_id'] ?>" class="sd-view-profile-btn">
              View Profile
            </a>
          </div>
          <?php if (!empty($service['bio'])): ?>
            <p class="sd-provider-bio"><?= htmlspecialchars($service['bio']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($availability)): ?>
      <div class="sd-card">
        <div class="sd-provider-card">
          <div class="sd-section-label">Available Days &amp; Hours</div>
          <div style="display:flex;flex-wrap:wrap;gap:.55rem;margin-top:.25rem">
            <?php foreach ($availability as $av): ?>
            <div style="
              background:var(--surface);border:1px solid var(--border);
              border-radius:var(--r-md);padding:.6rem .9rem;min-width:130px;
            ">
              <div style="font-size:.72rem;font-weight:700;color:var(--gold);margin-bottom:.2rem;text-transform:uppercase;letter-spacing:.06em">
                <?= htmlspecialchars($av['day_of_week']) ?>
              </div>
              <div style="font-size:.8rem;color:var(--off);font-family:var(--font-m)">
                <?= date('g:i A', strtotime($av['start_time'])) ?>
                <span style="color:var(--faint)">–</span>
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
            <div class="sd-book-title">Book this Service</div>
            <div class="sd-book-price">₱<?= number_format((float)$service['price'], 2) ?><?= $durationLabel ? ' · ' . $durationLabel : '' ?></div>
          </div>
          <span class="sd-chip sd-chip--green" style="flex-shrink:0"><i class="fa-solid fa-circle-check"></i> Active</span>
        </div>

        <form method="POST" action="<?= BASE_URL ?>book" class="sd-book-form" id="bookingForm">
          <input type="hidden" name="service_id"  value="<?= (int)$service['id'] ?>">
          <input type="hidden" name="provider_id" value="<?= (int)$service['profile_id'] ?>">

          <div class="sd-form-group">
            <label class="sd-form-label" for="formDate">
              <i class="fa-regular fa-calendar"></i> Booking Date <span class="sd-req">*</span>
              <?php if (!empty($availableDays)): ?>
                <span class="sd-form-hint"><?= implode(', ', array_map(fn($d) => substr($d, 0, 3), $availableDays)) ?></span>
              <?php endif; ?>
            </label>
            <input type="date" class="sd-form-control" id="formDate" name="booking_date"
                   min="<?= date('Y-m-d') ?>" required>
            <div id="dateWarning" style="display:none;font-size:.73rem;color:#FB7185;margin-top:.2rem">
              <i class="fa-solid fa-triangle-exclamation"></i> Provider is not available on this day.
            </div>
          </div>

          <div class="sd-form-group">
            <label class="sd-form-label" for="formTime">
              <i class="fa-regular fa-clock"></i> Preferred Time <span class="sd-req">*</span>
            </label>
            <input type="time" class="sd-form-control" id="formTime" name="booking_time" required>
            <div id="timeHint" style="font-size:.73rem;color:var(--faint);margin-top:.2rem"></div>
          </div>

          <?php if ($service['offers_home_service']): ?>
          <div class="sd-form-group">
            <label class="sd-form-label"><i class="fa-solid fa-location-dot"></i> Service Location</label>
            <div class="sd-radio-group">
              <label class="sd-radio-option">
                <input type="radio" name="location_type" value="In-shop" checked>
                <div class="sd-radio-box">
                  <span class="sd-radio-icon"><i class="fa-solid fa-store"></i></span>
                  <span class="sd-radio-label">In-shop</span>
                </div>
              </label>
              <label class="sd-radio-option">
                <input type="radio" name="location_type" value="Home">
                <div class="sd-radio-box">
                  <span class="sd-radio-icon"><i class="fa-solid fa-house"></i></span>
                  <span class="sd-radio-label">Home Visit</span>
                </div>
              </label>
            </div>
          </div>
          <?php else: ?>
          <input type="hidden" name="location_type" value="In-shop">
          <?php endif; ?>

          <div class="sd-form-group">
            <label class="sd-form-label" for="formNotes">
              <i class="fa-regular fa-note-sticky"></i> Notes
              <span class="sd-form-hint">optional</span>
            </label>
            <textarea class="sd-form-control sd-textarea" id="formNotes" name="notes"
                      rows="3" placeholder="Any special requests for the provider…"></textarea>
          </div>

        
          <div class="sd-book-summary">
            <div class="sd-summary-row">
              <span>Service</span>
              <span><?= htmlspecialchars($service['name']) ?></span>
            </div>
            <div class="sd-summary-row" style="margin-top:.35rem">
              <span>Provider</span>
              <span><?= htmlspecialchars($service['business_name']) ?></span>
            </div>
            <div class="sd-summary-divider"></div>
            <div class="sd-summary-row sd-summary-total">
              <span>Total</span>
              <span>₱<?= number_format((float)$service['price'], 2) ?></span>
            </div>
            <div class="sd-loyalty-note">⭐ You'll earn 10 loyalty points for this booking!</div>
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

const dateInput    = document.getElementById('formDate');
const dateWarning  = document.getElementById('dateWarning');
const timeHint     = document.getElementById('timeHint');
const submitBtn    = document.getElementById('submitBtn');

dateInput.addEventListener('change', function () {
  if (!this.value) return;
  const dayName = dayNames[new Date(this.value + 'T00:00:00').getDay()];
  const isAvailable = availableDays.includes(dayName);

  dateWarning.style.display = isAvailable ? 'none' : 'block';
  submitBtn.disabled = !isAvailable;

  if (isAvailable && hoursMap[dayName]) {
    timeHint.textContent = 'Available ' + hoursMap[dayName] + ' on ' + dayName + 's';
    timeHint.style.color = 'var(--gold)';
  } else {
    timeHint.textContent = '';
  }
});

document.getElementById('bookingForm').addEventListener('submit', function () {
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
});
</script>

</body>
</html>