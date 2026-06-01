<?php
// app/views/customer/booking-detail.php

require_once __DIR__ . '/../../../config/database.php';
$db       = Database::getInstance();
$userId   = (int)($_SESSION['user_id'] ?? 0);
$userName  = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? '');
$initials  = strtoupper(substr($userName, 0, 2));

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
$isCancellable = in_array($status, ['pending', 'confirmed', 'rescheduled']);
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
    'pending'      => ['label' => 'Pending Confirmation', 'icon' => '⏳', 'color' => 'yellow'],
    'confirmed'    => ['label' => 'Confirmed',            'icon' => '<i class=""></i>', 'color' => 'green'],
    'completed'    => ['label' => 'Completed',            'icon' => '<i class=""></i>', 'color' => 'blue'],
    'cancelled'    => ['label' => 'Cancelled',            'icon' => '✖',  'color' => 'red'],
    'rejected'     => ['label' => 'Rejected',             'icon' => '✖',  'color' => 'red'],
    'rescheduled'  => ['label' => 'Rescheduled',          'icon' => '<i class="fa-solid fa-rotate"></i>', 'color' => 'yellow'],
];
$statusInfo = $statusLabels[$status] ?? ['label' => ucfirst($status), 'icon' => '<i class=""></i>', 'color' => 'white'];

// ── Flash message ─────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Reviews for this provider ─────────────────────────────
$serviceId = (int)($booking['service_id'] ?? 0);
$stRev = $db->prepare("
    SELECT r.rating, r.comment, r.created_at,
           TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name,''))) AS reviewer_name,
           u.avatar_url AS profile_photo
    FROM   tbl_reviews r
    JOIN   tbl_users   u ON u.id = r.customer_id
    WHERE  r.service_id = ?
      AND  r.is_visible  = 1
    ORDER  BY r.created_at DESC
    LIMIT  10
");
$stRev->execute([$serviceId]);
$serviceReviews = $stRev->fetchAll(PDO::FETCH_ASSOC);

// Rating breakdown
$stBd = $db->prepare("SELECT rating, COUNT(*) AS cnt FROM tbl_reviews WHERE service_id = ? AND is_visible = 1 GROUP BY rating");
$stBd->execute([$serviceId]);
$bdBreakdown = array_fill(1, 5, 0);
foreach ($stBd->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $bdBreakdown[(int)$row['rating']] = (int)$row['cnt'];
}
$bdTotal  = array_sum($bdBreakdown);
$bdAvg    = $bdTotal
    ? round(array_sum(array_map(fn($s,$c) => $s * $c, array_keys($bdBreakdown), $bdBreakdown)) / $bdTotal, 1)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Booking #<?= (int)$booking['id'] ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_bookings.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_booking_detail.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>
    (function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();
  </script>
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
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <!-- THEME TOGGLE -->
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

     <!-- Profile dropdown trigger -->
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av">
          <?php if ($avatarUrl): ?>
            <img src="<?= $avatarUrl ?>" alt="<?= $name ?>" style="width:34px;height:34px;object-fit:cover;border-radius:99px;display:block;">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div class="pv-nav-user">
          <div class="pv-nav-user-name"><?= $userName ?></div>
          <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
        </div>
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

      <!-- Profile dropdown panel -->
      <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="pv-pd-header">
          <div class="pv-pd-avatar">
            <?php if ($avatarUrl): ?>
              <img src="<?= $avatarUrl ?>" alt="<?= $userName ?>">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $userName ?></div>
            <div class="pv-pd-email"><?= $userEmail ?></div>
            <span class="pv-pd-tier"><?= $loyaltyTier ?> Member</span>
          </div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>profile" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-user"></i></span>
          <span>My Profile</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>auth/logout" class="pv-pd-item pv-pd-item--danger" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
          <span>Sign Out</span>
        </a>
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
          <span class="pv-status-dot bd-status-dot--<?= $statusInfo['color'] ?>" aria-hidden="true"></span>
          <?= $statusInfo['label'] ?>
        </span>
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
              <?= in_array($booking['location_type'], ['On-site', 'Home']) ? '<i class="fa-solid fa-house"></i> Home Service' : '<i class="fa-solid fa-store"></i> ' . htmlspecialchars($booking['location_type']) ?>
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

      <?php if ($status === 'rescheduled' && !empty($booking['suggested_date'])): ?>
      <div class="pv-card bd-card" style="border-color:rgba(255,255,255,.1);">
        <div class="bd-section-title" style="font-size:.72rem;"><i class="fa-solid fa-rotate-right" style="color:#f59e0b"></i> Reschedule Suggested</div>

        <div style="display:flex;gap:.75rem;margin:.6rem 0 .75rem;">
          <div class="bd-reschedule-panel" style="flex:1">
            <div class="panel-label">Date</div>
            <div class="panel-val"><?= date('M j, Y', strtotime($booking['suggested_date'])) ?></div>
            <div class="panel-sub"><?= date('l', strtotime($booking['suggested_date'])) ?></div>
          </div>
          <div class="bd-reschedule-panel" style="flex:1">
            <div class="panel-label">Time</div>
            <div class="panel-val"><?= date('g:i A', strtotime($booking['suggested_time'])) ?></div>
          </div>
        </div>

        <?php if (!empty($booking['reschedule_note'])): ?>
        <div class="bd-detail-label" style="margin-bottom:.28rem">Provider's Note</div>
        <p style="font-size:.83rem;color:var(--text-muted);line-height:1.6;margin:0 0 .85rem;"><?= nl2br(htmlspecialchars($booking['reschedule_note'])) ?></p>
        <?php endif; ?>

        <div style="display:flex;gap:.6rem;">
          <form method="POST" action="<?= BASE_URL ?>bookings/<?= (int)$booking['id'] ?>/accept-reschedule" style="flex:1;">
            <button type="submit"
                    onclick="return confirm('Accept the new schedule?')"
                    class="pv-btn pv-btn--primary bd-btn-full" style="border:none;cursor:pointer;">
              <i class="fa-solid fa-circle-check"></i> Accept
            </button>
          </form>
          <form method="POST" action="<?= BASE_URL ?>bookings/<?= (int)$booking['id'] ?>/cancel" style="flex:1;">
            <button type="submit"
                    onclick="return confirm('Decline this reschedule and cancel your booking?')"
                    class="pv-btn pv-btn--ghost bd-btn-full" style="cursor:pointer;">
              <i class="fa-solid fa-xmark"></i> Decline
            </button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if (in_array($status, ['cancelled','rejected']) && !empty($booking['cancellation_reason'])): ?>
      <div class="pv-card bd-card bd-cancel-reason-card">
        <div class="bd-section-title"><i class="fa-solid fa-circle-xmark" style="color:#FB7185"></i> Reason for Cancellation</div>
        <div class="bd-cancel-reason-body">
          <div class="bd-cancel-reason-icon">
            <svg viewBox="0 0 24 24" fill="none" width="18" height="18">
              <path d="M12 8v5M12 15.5v.5" stroke="#FB7185" stroke-width="2.2" stroke-linecap="round"/>
            </svg>
          </div>
          <p class="bd-cancel-reason-text"><?= nl2br(htmlspecialchars($booking['cancellation_reason'])) ?></p>
        </div>
        <div class="bd-cancel-reason-note">This message was sent by the provider.</div>
      </div>
      <?php endif; ?>

      <!-- ── Reviews Section ── -->
      <div class="pv-card bd-card bd-reviews-card">
        <div class="bd-section-title">Customer Reviews</div>

        <?php if ($bdTotal > 0): ?>
        <!-- Rating summary -->
        <div class="bd-rev-summary">
          <div class="bd-rev-avg-block">
            <div class="bd-rev-avg-num"><?= number_format($bdAvg, 1) ?></div>
            <div class="bd-rev-avg-stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="fa-solid fa-star<?= $i > round($bdAvg) ? ' bd-star-empty' : '' ?>"></i>
              <?php endfor; ?>
            </div>

          </div>
          <div class="bd-rev-bars">
            <?php foreach ([5,4,3,2,1] as $star):
              $cnt = $bdBreakdown[$star];
              $pct = $bdTotal ? round($cnt / $bdTotal * 100) : 0;
            ?>
            <div class="bd-rev-bar-row">
              <span class="bd-rev-bar-lbl"><?= $star ?></span>
              <i class="fa-solid fa-star bd-rev-bar-star"></i>
              <div class="bd-rev-bar-track">
                <div class="bd-rev-bar-fill" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="bd-rev-bar-cnt"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="bd-rev-divider"></div>
        <?php endif; ?>

        <!-- Review list -->
        <?php if (empty($serviceReviews)): ?>
        <div class="bd-rev-empty">
          <i class="fa-regular fa-comment-dots"></i>
          <span>No reviews yet for this provider.</span>
        </div>
        <?php else: ?>
        <div class="bd-rev-list">
          <?php foreach ($serviceReviews as $rev):
            $rName   = htmlspecialchars($rev['reviewer_name'] ?? 'Anonymous');
            $rInit   = strtoupper(substr($rName, 0, 2));
            $rRating = (int)$rev['rating'];
            $rDate   = !empty($rev['created_at']) ? date('j M Y', strtotime($rev['created_at'])) : '';
            $rText   = htmlspecialchars($rev['comment'] ?? '');
          ?>
          <div class="bd-rev-row">
            <div class="bd-rev-avatar">
              <?php if (!empty($rev['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($rev['profile_photo']) ?>" alt="<?= $rName ?>">
              <?php else: ?>
                <?= $rInit ?>
              <?php endif; ?>
            </div>
            <div class="bd-rev-content">
              <div class="bd-rev-top-row">
                <div class="bd-rev-name-group">
                  <span class="bd-rev-name"><?= $rName ?></span>
                  <div class="bd-rev-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="fa-solid fa-star<?= $i > $rRating ? ' bd-star-empty' : '' ?>"></i>
                    <?php endfor; ?>
                  </div>
                </div>
                <?php if ($rDate): ?>
                  <span class="bd-rev-date"><?= $rDate ?></span>
                <?php endif; ?>
              </div>
              <?php if ($rText): ?>
                <p class="bd-rev-text"><?= $rText ?></p>
              <?php else: ?>
                <p class="bd-rev-text bd-rev-text--empty">No written comment.</p>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($isCompleted && !$booking['has_review']): ?>
        <a href="<?= BASE_URL ?>bookings/<?= (int)$booking['id'] ?>/review" class="bd-rev-cta">
          <i class="fa-solid fa-pen-to-square"></i> Write Your Review
        </a>
        <?php endif; ?>

      </div><!-- /bd-reviews-card -->

    </div><!-- /bd-main -->

    <!-- ── RIGHT: Summary + Actions ── -->
    <div class="bd-sidebar">

      <!-- Price summary -->
      <div class="pv-card bd-card">
        <?php
          $isHomeService  = in_array($booking['location_type'] ?? '', ['On-site', 'Home']);
          $homeServiceFee = 50;
          $total          = $booking['price'] + ($isHomeService ? $homeServiceFee : 0);
          $payMethod = $booking['payment_method'] ?? null;
          $payIcons  = [
            'cash'         => '<i class="fa-solid fa-money-bill-wave"></i>',
            'gcash'        => '<i class="fa-solid fa-mobile-screen"></i>',
            'paymaya'      => '<i class="fa-solid fa-mobile-screen"></i>',
            'card'         => '<i class="fa-solid fa-credit-card"></i>',
            'credit_card'  => '<i class="fa-solid fa-credit-card"></i>',
            'debit_card'   => '<i class="fa-solid fa-credit-card"></i>',
            'bank_transfer'=> '<i class="fa-solid fa-building-columns"></i>',
          ];
          $payIcon  = $payIcons[strtolower($payMethod ?? '')] ?? '<i class="fa-solid fa-wallet"></i>';
          $payLabel = $payMethod ? ucwords(str_replace('_', ' ', $payMethod)) : null;
        ?>
        <div class="bd-section-title"><i class="fa-solid fa-credit-card"></i> Payment Summary</div>

        <?php if ($payLabel): ?>
        <div class="bd-price-row bd-pay-method-row">
          <span class="bd-pay-method-label">Payment Method</span>
          <span class="bd-pay-method-val"><?= $payIcon ?> <?= htmlspecialchars($payLabel) ?></span>
        </div>
        <div class="bd-price-divider"></div>
        <?php endif; ?>

        <div class="bd-price-row">
          <span>Amount</span>
          <span class="bd-price-val">₱<?= number_format($booking['price'], 2) ?></span>
        </div>

        <?php if ($isHomeService): ?>
        <div class="bd-price-row">
          <span>Home service fee</span>
          <span class="bd-price-val">₱<?= number_format($homeServiceFee, 2) ?></span>
        </div>
        <?php endif; ?>

        <div class="bd-price-divider"></div>
        <div class="bd-price-row bd-price-row--total">
          <span>Total</span>
          <span class="bd-price-total">₱<?= number_format($total, 2) ?></span>
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

      <div class="bd-actions">
        <?php if ($isCancellable): ?>
         <form method="POST" action="<?= BASE_URL ?>bookings/<?= (int)$booking['id'] ?>/cancel">
  <button type="submit" onclick="return confirm('Cancel this booking?')">✖ Cancel Booking</button>
</form>
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