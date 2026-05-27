<?php

require_once __DIR__ . '/../../../config/database.php';
$db       = Database::getInstance();
$userId   = (int)($_SESSION['user_id'] ?? 0);
$name     = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$email    = htmlspecialchars($_SESSION['user_email'] ?? '');
$initials = strtoupper(substr($name, 0, 2));

$stAv = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAv->execute([$userId]);
$avatarUrl = ($av = $stAv->fetchColumn()) ? ($av) : null;

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
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_browse.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_service_detail.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>
    (function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();
  </script>
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- NAV -->
<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo" style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard"  class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>browse"     class="pv-nav-link is-active">Browse</a>
      <a href="<?= BASE_URL ?>bookings"   class="pv-nav-link">
        Bookings<?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>loyalty"    class="pv-nav-link">Loyalty</a>
    </div>

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <!-- Theme toggle -->
      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/>
          <line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22"  y1="4.22"  x2="5.64"  y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1"  y1="12" x2="3"  y2="12"/>
          <line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22"  y1="19.78" x2="5.64"  y2="18.36"/>
          <line x1="18.36" y1="5.64"  x2="19.78" y2="4.22"/>
        </svg>
      </button>

      <!-- Profile dropdown trigger -->
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false" title="Profile menu">
        <div class="pv-nav-av">
          <?php if ($avatarUrl): ?>
            <img src="<?= $avatarUrl ?>" alt="<?= $name ?>" style="width:36px;height:36px;object-fit:cover;border-radius:50%;display:block;">
          <?php else: ?>
            <span class="pv-av-initials"><?= $initials ?></span>
          <?php endif; ?>
        </div>
        <div class="pv-nav-user">
          <div class="pv-nav-user-name"><?= $name ?></div>
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
              <img src="<?= $avatarUrl ?>" alt="<?= $name ?>">
            <?php else: ?>
              <span class="pv-av-initials"><?= $initials ?></span>
            <?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $name ?></div>
            <div class="pv-pd-email"><?= $email ?></div>
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

    </div><!-- /pv-nav-end -->
  </div><!-- /pv-nav-inner -->
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
      <?php if (!empty($service['description'])): ?>
      <p class="sd-hero-description"><?= htmlspecialchars($service['description']) ?></p>
      <?php endif; ?>
    </div>
    <a href="<?= BASE_URL ?>browse" class="sd-back-btn">
      <i class="fa-solid fa-arrow-left"></i> Back to Browse
    </a>
  </div>
</header>

<!-- BREADCRUMB -->
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

    <!-- ═══════════════ LEFT COLUMN ═══════════════ -->
    <div class="sd-main">

      <!-- PROVIDER CARD -->
      <div class="sd-card">
        <div class="sd-provider-card">
          <div class="sd-section-label">Your Provider</div>
          <div class="sd-provider-row">
            <div class="sd-provider-av" style="overflow:hidden;display:flex;align-items:center;justify-content:center;font-weight:800;">
              <?php if (!empty($service['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($service['profile_photo']) ?>"
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

      <!-- CUSTOMER REVIEWS CARD -->
      <?php
        // Fetch reviews for this specific service only
        $sdRevStmt = $db->prepare("
            SELECT r.rating, r.comment, r.created_at,
                   r.customer_id,
                   TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS reviewer_name,
                   u.avatar_url AS profile_photo,
                   u.gender,
                   u.date_of_birth,
                   (SELECT COUNT(*) FROM tbl_bookings b2 WHERE b2.customer_id = u.id AND b2.status = 'completed') AS total_bookings
            FROM   tbl_reviews r
            JOIN   tbl_users u ON u.id = r.customer_id
            WHERE  r.service_id = ? AND r.is_visible = 1
            ORDER  BY r.created_at DESC
            LIMIT  20
        ");
        $sdRevStmt->execute([$service['id']]);
        $sdAllReviews = $sdRevStmt->fetchAll();

        // Rating breakdown for this service only
        $sdBrkStmt = $db->prepare("
            SELECT rating, COUNT(*) AS cnt FROM tbl_reviews
            WHERE service_id = ? AND is_visible = 1 GROUP BY rating
        ");
        $sdBrkStmt->execute([$service['id']]);
        $sdBreakdown = array_fill(1, 5, 0);
        foreach ($sdBrkStmt->fetchAll() as $brow) { $sdBreakdown[(int)$brow['rating']] = (int)$brow['cnt']; }
        $sdTotalRev  = array_sum($sdBreakdown);
        $sdAvgRating = $sdTotalRev
            ? round(array_sum(array_map(fn($s,$c)=>$s*$c, array_keys($sdBreakdown), $sdBreakdown)) / $sdTotalRev, 1)
            : 0;

        // Check if this customer has a completed, un-reviewed booking for this specific service
        $sdCanReviewStmt = $db->prepare("
            SELECT b.id FROM tbl_bookings b
            WHERE  b.customer_id = ? AND b.service_id = ? AND b.status = 'completed'
              AND  NOT EXISTS (SELECT 1 FROM tbl_reviews r WHERE r.booking_id = b.id)
            LIMIT 1
        ");
        $sdCanReviewStmt->execute([$userId, $service['id']]);
        $sdReviewableBooking = $sdCanReviewStmt->fetch();

        if (!function_exists('sdRenderStars')) {
            function sdRenderStars(float $r): string {
                $f = floor($r); $h = ($r - $f) >= .5 ? 1 : 0; $e = 5 - $f - $h;
                return str_repeat('★', $f) . ($h ? '½' : '') . str_repeat('☆', $e);
            }
        }
      ?>
      <div class="sd-card">

        <!-- Card header -->
        <div class="sd-reviews-head">
          <div class="sd-reviews-head-left">
            <h2 class="sd-reviews-title">Customer Reviews</h2>
            <?php if ($sdTotalRev > 0): ?>
            <p class="sd-reviews-sub">
              <span class="sd-rv-gold">⭐ <?= number_format($sdAvgRating, 1) ?></span>
              · <?= $sdTotalRev ?> review<?= $sdTotalRev !== 1 ? 's' : '' ?>
            </p>
            <?php endif; ?>
          </div>
          <?php if ($sdReviewableBooking): ?>
          <a href="<?= BASE_URL ?>bookings/<?= (int)$sdReviewableBooking['id'] ?>/review"
             class="sd-review-cta-btn">
            <i class="fa-solid fa-star" style="font-size:.65rem"></i> Leave a Review
          </a>
          <?php endif; ?>
        </div>

        <div class="sd-reviews-body">

          <?php if ($sdTotalRev > 0): ?>
          <!-- Rating breakdown -->
          <div class="sd-rb-wrap">
            <div class="sd-rb-score">
              <div class="sd-rb-big"><?= number_format($sdAvgRating, 1) ?></div>
              <div class="sd-rb-stars"><?= sdRenderStars($sdAvgRating) ?></div>
              <div class="sd-rb-count"><?= $sdTotalRev ?> ratings</div>
            </div>
            <div class="sd-rb-bars">
              <?php foreach ([5,4,3,2,1] as $star):
                $cnt = $sdBreakdown[$star];
                $pct = $sdTotalRev ? round($cnt / $sdTotalRev * 100) : 0;
              ?>
              <div class="sd-rb-row">
                <span class="sd-rb-lbl"><i class="fa-solid fa-star" style="font-size:.55rem"></i> <?= $star ?></span>
                <div class="sd-rb-track"><div class="sd-rb-fill" style="width:<?= $pct ?>%"></div></div>
                <span class="sd-rb-num"><?= $cnt ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (empty($sdAllReviews)): ?>
          <div class="sd-reviews-empty">
            <p>No reviews yet. Be the first!</p>
            <?php if ($sdReviewableBooking): ?>
            <a href="<?= BASE_URL ?>bookings/<?= (int)$sdReviewableBooking['id'] ?>/review"
               class="sd-review-cta-btn">
              <i class="fa-solid fa-star" style="font-size:.65rem"></i> Write the first review
            </a>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <div class="sd-review-list">
            <?php foreach ($sdAllReviews as $r):
              $rName       = htmlspecialchars($r['reviewer_name'] ?? 'Anonymous');
              $rInit       = strtoupper(substr($rName, 0, 2));
              $rDate       = !empty($r['created_at']) ? date('M d, Y', strtotime($r['created_at'])) : '';
              $isOwnReview = ((int)$r['customer_id'] === (int)$userId);
              $profileHref = $isOwnReview ? (BASE_URL . 'profile') : null;
              $totalBooks  = (int)($r['total_bookings'] ?? 0);
            ?>
            <div class="sd-review-item">

              <!-- Avatar -->
              <?php if ($profileHref): ?>
              <a href="<?= $profileHref ?>" class="sd-review-av" title="View your profile" style="text-decoration:none;">
              <?php else: ?>
              <div class="sd-review-av">
              <?php endif; ?>
                <?php if (!empty($r['profile_photo'])): ?>
                  <img src="<?= $r['profile_photo'] ?>" alt="<?= $rName ?>">
                <?php else: ?>
                  <?= $rInit ?>
                <?php endif; ?>
              <?php if ($profileHref): ?>
              </a>
              <?php else: ?>
              </div>
              <?php endif; ?>

              <div class="sd-review-content">
                <div class="sd-review-meta">
                  <?php if ($profileHref): ?>
                  <a href="<?= $profileHref ?>" class="sd-review-name" style="text-decoration:none;color:inherit;" title="View your profile">
                    <?= $rName ?>
                    <span style="font-size:.68rem;font-weight:400;color:var(--gold-dim);margin-left:.3rem">(You)</span>
                  </a>
                  <?php else: ?>
                  <span class="sd-review-name"><?= $rName ?></span>
                  <?php endif; ?>
                  <span class="sd-review-stars"><?= sdRenderStars((float)$r['rating']) ?></span>
                  <span class="sd-review-date"><?= $rDate ?></span>
                </div>
                <?php if ($r['comment']): ?>
                  <p class="sd-review-text"><?= htmlspecialchars($r['comment']) ?></p>
                <?php else: ?>
                  <p class="sd-review-text sd-review-text--empty">No written comment.</p>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

        </div><!-- /sd-reviews-body -->
      </div>

    </div>

    <!-- ═══════════════ SIDEBAR ═══════════════ -->
    <aside class="sd-sidebar">
      <div class="sd-card sd-book-card">

        <div class="sd-book-header">
          <div>
            <div class="sd-book-title">Book this Service</div>
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
              <i class="fa-regular fa-calendar"></i> Booking Date <span class="sd-req">*</span>
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
              <i class="fa-regular fa-clock"></i> Preferred Time <span class="sd-req">*</span>
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
                <i class="fa-solid fa-location-dot"></i> Your Address <span class="sd-req">*</span>
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
                <i class=""></i>
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
                Where would you like the service? <span class="sd-req">*</span>
              </div>

              <div class="sd-loc-tabs">
                <label class="sd-loc-tab">
                  <input type="radio" name="location_type" value="In-shop" checked onchange="handleFlexLoc(this.value)">
                  <div class="sd-loc-tab-box">
                    <div class="sd-loc-tab-icon">🏪</div>
                    <div class="sd-loc-tab-label">In-shop</div>
                  </div>
                </label>
                <label class="sd-loc-tab">
                  <input type="radio" name="location_type" value="On-site" onchange="handleFlexLoc(this.value)">
                  <div class="sd-loc-tab-box">
                    <div class="sd-loc-tab-icon">🏠</div>
                    <div class="sd-loc-tab-label">Home Service</div>
                  </div>
                </label>
                <label class="sd-loc-tab">
                  <input type="radio" name="location_type" value="Remote" onchange="handleFlexLoc(this.value)">
                  <div class="sd-loc-tab-box">
                    <div class="sd-loc-tab-icon">💻</div>
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
              <i class="fa-solid fa-credit-card"></i> Payment Method <span class="sd-req">*</span>
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

/* ── Payment method toggle ── */
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

/* ── Flexible location toggle ── */
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

<script>
/* ── Profile Dropdown ── */
(function () {
  const trigger  = document.getElementById('profileTrigger');
  const dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;
  function open()   { trigger.classList.add('is-open'); dropdown.classList.add('is-open'); trigger.setAttribute('aria-expanded','true'); }
  function close()  { trigger.classList.remove('is-open'); dropdown.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); }
  function toggle() { dropdown.classList.contains('is-open') ? close() : open(); }
  trigger.addEventListener('click', e => { e.stopPropagation(); toggle(); });
  trigger.addEventListener('keydown', e => {
    if (e.key==='Enter'||e.key===' ') { e.preventDefault(); toggle(); }
    if (e.key==='Escape') close();
  });
  document.addEventListener('click', e => {
    if (!dropdown.contains(e.target) && !trigger.contains(e.target)) close();
  });
  document.addEventListener('keydown', e => { if (e.key==='Escape') close(); });
})();
</script>
</body>
</html>