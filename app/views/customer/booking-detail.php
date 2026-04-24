<?php
// app/views/customer/booking-detail.php

require_once __DIR__ . '/../../../config/database.php';
$db       = Database::getInstance();
$userId   = (int)($_SESSION['user_id'] ?? 0);
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Customer');
$initials = strtoupper(substr($userName, 0, 2));

// $booking is already fetched & validated by CustomerController::bookingDetail()

// ── Loyalty points (for nav badge) ──────────────────────
$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$userId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$loyaltyTier   = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};

// ── Upcoming count for nav badge ─────────────────────────
$stUpcoming = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE()");
$stUpcoming->execute([$userId]);
$upcomingCount = (int)$stUpcoming->fetchColumn();

// ── Helpers ──────────────────────────────────────────────
$status        = $booking['status'];
$isCancellable = in_array($status, ['pending', 'confirmed']);
$isCompleted   = $status === 'completed';
$bookingTime   = !empty($booking['booking_time']) ? date('g:i A', strtotime($booking['booking_time'])) : null;
$duration      = !empty($booking['duration_minutes']) ? $booking['duration_minutes'] . ' min' : null;

$catEmojiMap = [
    'barbershop'       => '<i class="fa-solid fa-scissors"></i>',
    'hair-salon'       => '<i class="fa-solid fa-scissors"></i>',
    'nail-care'        => '<i class="fa-solid fa-hand-sparkles"></i>',
    'massage-therapy'  => '<i class="fa-solid fa-spa"></i>',
    'skincare-facial'  => '<i class="fa-solid fa-pump-soap"></i>',
    'fitness-training' => '<i class="fa-solid fa-dumbbell"></i>',
    'home-cleaning'    => '<i class="fa-solid fa-broom"></i>',
    'pet-grooming'     => '<i class="fa-solid fa-paw"></i>',
    'event-styling'    => '<i class="fa-solid fa-palette"></i>',
    'dental'           => '<i class="fa-solid fa-tooth"></i>',
    'tutoring'         => '<i class="fa-solid fa-book"></i>',
];
$emoji = $catEmojiMap[$booking['category_slug'] ?? ''] ?? '<i class="fa-solid fa-screwdriver-wrench"></i>';

$statusLabels = [
    'pending'   => ['label' => 'Pending Confirmation', 'icon' => '⏳', 'color' => 'yellow'],
    'confirmed' => ['label' => 'Confirmed',            'icon' => '<i class="fa-solid fa-circle-check"></i>', 'color' => 'green'],
    'completed' => ['label' => 'Completed',            'icon' => '<i class="fa-solid fa-medal"></i>', 'color' => 'blue'],
    'cancelled' => ['label' => 'Cancelled',            'icon' => '✖',  'color' => 'red'],
    'rejected'  => ['label' => 'Rejected',             'icon' => '✖',  'color' => 'red'],
];
$statusInfo = $statusLabels[$status] ?? ['label' => ucfirst($status), 'icon' => '<i class="fa-solid fa-clipboard-list"></i>', 'color' => 'white'];

// ── Flash message ─────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Booking #<?= (int)$booking['id'] ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_bookings.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_booking_detail.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>

<!-- ══ NAV ══ -->
<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard" class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings"  class="pv-nav-link is-active">
        Bookings
        <?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse"    class="pv-nav-link">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty"   class="pv-nav-link">Loyalty</a>
      <a href="<?= BASE_URL ?>profile"   class="pv-nav-link">Profile</a>
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

<!-- ══ HERO ══ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Booking #<?= (int)$booking['id'] ?>
      </p>
      <h1 class="pv-hero-name"><?= htmlspecialchars($booking['service_name']) ?></h1>
      <p class="pv-hero-date"><?= date('l, F j, Y', strtotime($booking['booking_date'])) ?><?= $bookingTime ? ' · ' . $bookingTime : '' ?></p>
      <div class="pv-hero-meta">
        <span class="pv-status-badge bd-status-badge--<?= $statusInfo['color'] ?>">
          <span class="pv-status-dot<?= $statusInfo['color'] ?>" aria-hidden="true"></span>
          <?= $statusInfo['icon'] ?> <?= $statusInfo['label'] ?>
        </span>
        <span class="pv-tier-badge"><i class="fa-solid"></i> <?= htmlspecialchars($booking['business_name']) ?></span>
      </div>
    </div>
    <a href="<?= BASE_URL ?>bookings" class="pv-points-chip">
      ← Back to Bookings
    </a>
  </div>
</header>

<!-- ══ MAIN ══ -->
<main class="pv-page">

  <?php if ($flash): ?>
  <div class="bd-flash bd-flash--<?= htmlspecialchars($flash['type']) ?>">
    <?= $flash['type'] === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?> <?= htmlspecialchars($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <div class="bd-grid">

    <!-- ── LEFT: Main detail card ── -->
    <div class="bd-main">

      <!-- Service card -->
      <div class="pv-card bd-card">
        <div class="bd-card-header">
          <div class="bd-service-av"><?= $emoji ?></div>
          <div>
            <div class="bd-card-title"><?= htmlspecialchars($booking['service_name']) ?></div>
            <div class="bd-card-sub">by <?= htmlspecialchars($booking['business_name']) ?></div>
            <?php if (!empty($booking['category_name'])): ?>
              <span class="pv-tag pv-tag--cat" style="margin-top:.45rem;display:inline-block"><?= htmlspecialchars($booking['category_name']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($booking['service_description'])): ?>
        <p class="bd-service-desc"><?= htmlspecialchars($booking['service_description']) ?></p>
        <?php endif; ?>
      </div>

      <!-- Date / time / location card -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-calendar-days"></i> Appointment Details</div>
        <div class="bd-detail-grid">
          <div class="bd-detail-item">
            <div class="bd-detail-label">Date</div>
            <div class="bd-detail-val"><?= date('F j, Y', strtotime($booking['booking_date'])) ?></div>
          </div>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Time</div>
            <div class="bd-detail-val"><?= $bookingTime ?? '—' ?></div>
          </div>
          <?php if ($duration): ?>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Duration</div>
            <div class="bd-detail-val">⏱ <?= $duration ?></div>
          </div>
          <?php endif; ?>
          <?php if (!empty($booking['location_type'])): ?>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Location Type</div>
            <div class="bd-detail-val">
              <?= $booking['location_type'] === 'Home' ? '<i class="fa-solid fa-house"></i> Home Service' : '<i class="fa-solid fa-store"></i> ' . htmlspecialchars($booking['location_type']) ?>
            </div>
          </div>
          <?php endif; ?>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Booked On</div>
            <div class="bd-detail-val"><?= date('M j, Y · g:i A', strtotime($booking['created_at'])) ?></div>
          </div>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Booking ID</div>
            <div class="bd-detail-val bd-mono">#<?= (int)$booking['id'] ?></div>
          </div>
        </div>
      </div>

      <!-- Notes card -->
      <?php if (!empty($booking['notes'])): ?>
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-pen-to-square"></i> Your Notes</div>
        <p class="bd-notes"><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
      </div>
      <?php endif; ?>

    </div><!-- /bd-main -->

    <!-- ── RIGHT: Summary + Actions ── -->
    <div class="bd-sidebar">

      <!-- Price summary -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-credit-card"></i> Payment Summary</div>
        <div class="bd-price-row">
          <span>Service fee</span>
          <span class="bd-price-val">₱<?= number_format($booking['price'], 2) ?></span>
        </div>
        <div class="bd-price-divider"></div>
        <div class="bd-price-row bd-price-row--total">
          <span>Total</span>
          <span class="bd-price-total">₱<?= number_format($booking['price'], 2) ?></span>
        </div>
        <div class="bd-loyalty-note">⭐ +10 loyalty points earned</div>
      </div>

      <!-- Status timeline -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-rotate"></i> Status Timeline</div>
        <div class="bd-timeline">
          <?php
          $steps = [
              'pending'   => ['icon' => '<i class="fa-solid fa-clipboard-list"></i>', 'label' => 'Booking Submitted'],
              'confirmed' => ['icon' => '<i class="fa-solid fa-circle-check"></i>', 'label' => 'Confirmed by Provider'],
              'completed' => ['icon' => '<i class="fa-solid fa-medal"></i>', 'label' => 'Service Completed'],
          ];
          $cancelSteps = ['cancelled' => ['icon' => '✖', 'label' => 'Booking Cancelled'], 'rejected' => ['icon' => '✖', 'label' => 'Booking Rejected']];
          $order = ['pending','confirmed','completed'];
          $currentIdx = array_search($status, $order);

          if (in_array($status, ['cancelled','rejected'])):
              foreach ($steps as $key => $step):
                  $done = ($key === 'pending'); ?>
              <div class="bd-timeline-step <?= $done ? 'done' : 'muted' ?>">
                <div class="bd-tl-dot <?= $done ? 'done' : '' ?>"></div>
                <div class="bd-tl-content">
                  <div class="bd-tl-label"><?= $step['icon'] ?> <?= $step['label'] ?></div>
                </div>
              </div>
              <?php endforeach;
              $info = $cancelSteps[$status]; ?>
              <div class="bd-timeline-step bd-timeline-step--cancel">
                <div class="bd-tl-dot bd-tl-dot--cancel"></div>
                <div class="bd-tl-content">
                  <div class="bd-tl-label"><?= $info['icon'] ?> <?= $info['label'] ?></div>
                </div>
              </div>
          <?php else:
              foreach ($steps as $key => $step):
                  $stepIdx = array_search($key, $order);
                  $done    = $stepIdx <= $currentIdx;
                  $active  = $stepIdx === $currentIdx; ?>
              <div class="bd-timeline-step <?= $done ? 'done' : 'muted' ?> <?= $active ? 'active' : '' ?>">
                <div class="bd-tl-dot <?= $done ? 'done' : '' ?> <?= $active ? 'active' : '' ?>"></div>
                <div class="bd-tl-content">
                  <div class="bd-tl-label"><?= $step['icon'] ?> <?= $step['label'] ?></div>
                </div>
              </div>
              <?php endforeach;
          endif; ?>
        </div>
      </div>

      <!-- Actions -->
      <div class="bd-actions">
        <?php if ($isCancellable): ?>
          <a href="<?= BASE_URL ?>bookings/<?= (int)$booking['id'] ?>/cancel"
             class="pv-btn pv-btn--ghost bd-btn-full"
             onclick="return confirm('Are you sure you want to cancel this booking?')">
            ✖ Cancel Booking
          </a>
        <?php elseif ($isCompleted && !$booking['has_review']): ?>
          <a href="<?= BASE_URL ?>bookings/<?= (int)$booking['id'] ?>/review"
             class="pv-btn pv-btn--review bd-btn-full">
            ⭐ Leave a Review
          </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>bookings" class="pv-btn pv-btn--ghost bd-btn-full">← All Bookings</a>
        <a href="<?= BASE_URL ?>providers/<?= (int)$booking['profile_id'] ?>" class="pv-btn pv-btn--ghost bd-btn-full">
          <i class="fa-solid fa-location-dot"></i> View Provider
        </a>
      </div>

    </div><!-- /bd-sidebar -->
  </div><!-- /bd-grid -->

</main>

</body>
</html>