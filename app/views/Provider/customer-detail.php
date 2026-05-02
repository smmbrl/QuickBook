<?php

require_once __DIR__ . '/../../../config/database.php';
$db     = Database::getInstance();
$userId = (int)($_SESSION['user_id'] ?? 0);

$navUser = $db->prepare("SELECT u.first_name, u.last_name, pp.profile_photo AS nav_photo FROM tbl_users u LEFT JOIN tbl_provider_profiles pp ON pp.user_id = u.id WHERE u.id = ?");
$navUser->execute([$userId]);
$navRow    = $navUser->fetch();
$userName  = htmlspecialchars($navRow['first_name'] ?? '');
$initials  = strtoupper(substr($navRow['first_name'] ?? 'P', 0, 1) . substr($navRow['last_name'] ?? 'R', 0, 1));

$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings b JOIN tbl_provider_profiles pp ON pp.id = b.provider_id WHERE pp.user_id = ? AND b.status = 'pending'");
$stPending->execute([$userId]);
$pendingCount = (int)$stPending->fetchColumn();

$status        = $booking['status'];
$bookDate      = !empty($booking['booking_date']) ? date('l, F j, Y', strtotime($booking['booking_date'])) : '—';
$bookDateShort = !empty($booking['booking_date']) ? date('F j, Y',    strtotime($booking['booking_date'])) : '—';
$bookTime      = !empty($booking['booking_time']) ? date('g:i A',     strtotime($booking['booking_time'])) : null;
$createdAt     = !empty($booking['created_at'])   ? date('M j, Y · g:i A', strtotime($booking['created_at'])) : '—';
// Use the booking's chosen location_type. If customer_address is filled, treat as On-site regardless.
$custAddr      = trim($booking['customer_address'] ?? '');
$shopAddr      = trim($booking['shop_address'] ?? '');
$locType       = $custAddr !== '' ? 'On-site' : ($booking['location_type'] ?? 'In-shop');
$duration      = !empty($booking['duration_minutes']) ? $booking['duration_minutes'] . ' min' : null;

// Customer profile extras
$custPhone   = $booking['customer_phone'] ?? '';
$custGender  = $booking['customer_gender'] ?? '';
$custDob     = !empty($booking['customer_dob']) ? date('F j, Y', strtotime($booking['customer_dob'])) : '';
$custAge     = !empty($booking['customer_dob']) ? (int)date_diff(date_create($booking['customer_dob']), date_create('today'))->y : null;
$custProfAddr = $booking['customer_profile_address'] ?? '';
$custSince   = !empty($booking['customer_since']) ? date('M Y', strtotime($booking['customer_since'])) : '';
$genderLabels = ['male'=>'Male','female'=>'Female','non_binary'=>'Non-binary','prefer_not_to_say'=>'Prefer not to say'];

$statusLabels = [
    'pending'      => ['label' => 'Pending Confirmation', 'color' => 'amber',  'icon' => 'fa-clock'],
    'confirmed'    => ['label' => 'Confirmed',            'color' => 'green',  'icon' => 'fa-circle-check'],
    'in_progress'  => ['label' => 'In Progress',          'color' => 'blue',   'icon' => 'fa-spinner'],
    'completed'    => ['label' => 'Completed',            'color' => 'blue',   'icon' => 'fa-medal'],
    'cancelled'    => ['label' => 'Cancelled',            'color' => 'red',    'icon' => 'fa-circle-xmark'],
    'rejected'     => ['label' => 'Rejected',             'color' => 'red',    'icon' => 'fa-circle-xmark'],
    'rescheduled'  => ['label' => 'Rescheduled',          'color' => 'amber',  'icon' => 'fa-rotate-right'],
];
$statusInfo = $statusLabels[$status] ?? ['label' => ucfirst($status), 'color' => 'muted', 'icon' => 'fa-circle'];

$locConfig = [
    'On-site'  => ['label'=>'Home Service', 'icon'=>'fa-house-chimney-medical','color'=>'#fbbf24','bg'=>'rgba(251,191,36,.09)','border'=>'rgba(251,191,36,.25)'],
    'In-shop'  => ['label'=>'In-shop', 'icon'=>'fa-store',   'color'=>'#4ade80','bg'=>'rgba(74,222,128,.07)','border'=>'rgba(74,222,128,.2)'],
    'Remote'   => ['label'=>'Remote',  'icon'=>'fa-wifi',    'color'=>'#60a5fa','bg'=>'rgba(96,165,250,.08)','border'=>'rgba(96,165,250,.2)'],
    'Flexible' => ['label'=>'Flexible','icon'=>'fa-sliders', 'color'=>'#a78bfa','bg'=>'rgba(167,139,250,.09)','border'=>'rgba(167,139,250,.22)'],
];
$locInfo = $locConfig[$locType] ?? $locConfig['In-shop'];

$canConfirm  = $status === 'pending';
$canStart    = $status === 'confirmed';
$canComplete = $status === 'in_progress';
$canResched  = in_array($status, ['pending', 'confirmed', 'rescheduled']);
$isActive    = in_array($status, ['pending','confirmed','in_progress','rescheduled']);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$timelineSteps = [
    'pending'     => ['icon'=>'fa-clipboard-list','label'=>'Booking Placed',  'sub'=>'Customer submitted this booking'],
    'confirmed'   => ['icon'=>'fa-circle-check',  'label'=>'Confirmed',        'sub'=>'You accepted the booking'],
    'in_progress' => ['icon'=>'fa-spinner',        'label'=>'In Progress',      'sub'=>'Service is underway'],
    'completed'   => ['icon'=>'fa-medal',          'label'=>'Completed',        'sub'=>'Service finished successfully'],
];
$statusOrder = array_keys($timelineSteps);
$currentIdx  = array_search($status, $statusOrder);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Booking #<?= str_pad((int)$booking['id'], 4, '0', STR_PAD_LEFT) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_bookings.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_booking_detail.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_customer_detail.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- NAV -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>provider/dashboard" class="pv-logo">
      Quick<em>Book</em>
      <span class="pv-logo-badge">Provider</span>
    </a>
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/bookings"     class="pv-nav-link is-active">
        Bookings
        <?php if ($pendingCount): ?><sup class="pv-sup"><?= $pendingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"     class="pv-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/availability" class="pv-nav-link">Availability</a>
      <a href="<?= BASE_URL ?>provider/profile"      class="pv-nav-link">Profile</a>
    </div>
    <div class="pv-nav-end">
      <div class="pv-nav-user">
        <div class="pv-nav-av" aria-hidden="true">
          <?php if (!empty($navRow['nav_photo'])): ?>
            <img src="<?= BASE_URL ?>assets/uploads/profiles/<?= htmlspecialchars($navRow['nav_photo']) ?>" alt="Profile photo" style="width:34px;height:34px;min-width:34px;min-height:34px;object-fit:cover;border-radius:99px;display:block;">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <span><?= $userName ?></span>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Booking #<?= str_pad((int)$booking['id'], 4, '0', STR_PAD_LEFT) ?>
      </p>
      <h1 class="pv-hero-name"><?= htmlspecialchars($booking['service_name'] ?? '—') ?></h1>
      <p class="pv-hero-date"><?= $bookDate ?><?= $bookTime ? ' · ' . $bookTime : '' ?></p>
      <div style="display:flex;gap:.65rem;flex-wrap:wrap;margin-top:.75rem">
        <span class="pv-status-badge pv-status-badge--<?= $statusInfo['color'] ?>">
          <i class="fa-solid <?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['label'] ?>
        </span>
      </div>
    </div>
    <a href="<?= BASE_URL ?>provider/bookings" class="pv-back-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
      Back to Bookings
    </a>
  </div>
</header>

<!-- MAIN -->
<main class="pv-page">

  <div class="bd-grid">

    <!-- LEFT COLUMN -->
    <div class="bd-main">

      <!-- Customer Profile -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-user-circle"></i> Customer Profile</div>

        <!-- Identity Row -->
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem">
          <div style="width:56px;height:56px;border-radius:50%;background:var(--gold);color:#000;font-weight:800;font-size:1.1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 0 3px rgba(251,191,36,.2);overflow:hidden;">
            <?php if (!empty($booking['customer_avatar'])): ?>
              <img src="<?= BASE_URL ?>assets/uploads/profiles/<?= htmlspecialchars($booking['customer_avatar']) ?>" alt="<?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
            <?php else: ?>
              <?= strtoupper(substr($booking['customer_first'] ?? 'C', 0, 1) . substr($booking['customer_last'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
          </div>
          <div>
            <div style="font-weight:800;font-size:1.05rem;color:#fff">
              <?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?>
            </div>
            <?php if ($custSince): ?>
            <div style="font-size:.73rem;color:var(--muted);margin-top:.2rem">
              <i class="fa-solid fa-calendar-plus" style="margin-right:.3rem"></i>Member since <?= $custSince ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Contact & Info Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem 1rem">

          <!-- Email -->
          <div style="grid-column:1/-1;display:flex;align-items:flex-start;gap:.65rem;padding:.7rem .9rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px">
            <i class="fa-solid fa-envelope" style="color:var(--gold);margin-top:.15rem;font-size:.85rem;flex-shrink:0"></i>
            <div>
              <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:.2rem">Email</div>
              <div style="font-size:.88rem;color:#fff;font-weight:500"><?= htmlspecialchars($booking['customer_email'] ?? '—') ?></div>
            </div>
          </div>

          <!-- Phone -->
          <div style="display:flex;align-items:flex-start;gap:.65rem;padding:.7rem .9rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px">
            <i class="fa-solid fa-phone" style="color:#4ade80;margin-top:.15rem;font-size:.85rem;flex-shrink:0"></i>
            <div>
              <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:.2rem">Phone</div>
              <div style="font-size:.88rem;color:#fff;font-weight:500">
                <?= $custPhone ? htmlspecialchars($custPhone) : '<span style="color:var(--muted);font-style:italic">Not provided</span>' ?>
              </div>
            </div>
          </div>

          <!-- Gender -->
          <div style="display:flex;align-items:flex-start;gap:.65rem;padding:.7rem .9rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px">
            <i class="fa-solid fa-venus-mars" style="color:#a78bfa;margin-top:.15rem;font-size:.85rem;flex-shrink:0"></i>
            <div>
              <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:.2rem">Gender</div>
              <div style="font-size:.88rem;color:#fff;font-weight:500">
                <?= ($custGender && isset($genderLabels[$custGender])) ? $genderLabels[$custGender] : '<span style="color:var(--muted);font-style:italic">Not provided</span>' ?>
              </div>
            </div>
          </div>

          <!-- Date of Birth -->
          <div style="display:flex;align-items:flex-start;gap:.65rem;padding:.7rem .9rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px">
            <i class="fa-solid fa-cake-candles" style="color:#f472b6;margin-top:.15rem;font-size:.85rem;flex-shrink:0"></i>
            <div>
              <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:.2rem">Date of Birth</div>
              <div style="font-size:.88rem;color:#fff;font-weight:500">
                <?php if ($custDob): ?>
                  <?= htmlspecialchars($custDob) ?>
                  <?php if ($custAge !== null): ?><span style="font-size:.75rem;color:var(--muted);margin-left:.4rem">(<?= $custAge ?> yrs)</span><?php endif; ?>
                <?php else: ?>
                  <span style="color:var(--muted);font-style:italic">Not provided</span>
                <?php endif; ?>
              </div>
            </div>
          </div>



        </div>
      </div>

      <!-- Service -->
      <div class="pv-card bd-card">
        <div class="bd-card-header">
          <div class="bd-service-av"><i class="fa-solid fa-screwdriver-wrench"></i></div>
          <div>
            <div class="bd-card-title"><?= htmlspecialchars($booking['service_name'] ?? '—') ?></div>
            <div class="bd-card-sub"><?= htmlspecialchars($booking['service_type'] ?? '') ?></div>
          </div>
        </div>
      </div>

      <?php if ($locType === 'On-site' && $custAddr): ?>
      <!-- Home Service Address — prominent dedicated card -->
      <div class="pv-card bd-card" style="border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.05)">
        <div class="bd-section-title" style="color:#fbbf24">
          <i class="fa-solid fa-house-chimney-medical"></i> Home Service Address
        </div>
        <div style="display:flex;align-items:flex-start;gap:1rem;margin-top:.25rem">
          <div style="width:42px;height:42px;border-radius:10px;background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fa-solid fa-location-dot" style="color:#fbbf24;font-size:1.1rem"></i>
          </div>
          <div style="flex:1">
            <div style="font-size:1rem;font-weight:700;color:#fff;line-height:1.5;margin-bottom:.35rem">
              <?= htmlspecialchars($custAddr) ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem">
              <a href="https://www.google.com/maps/search/<?= urlencode($custAddr) ?>"
                 target="_blank" rel="noopener noreferrer"
                 style="display:inline-flex;align-items:center;gap:.4rem;padding:.38rem .8rem;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.3);border-radius:999px;font-size:.73rem;font-weight:700;color:#fbbf24;text-decoration:none;transition:background .18s"
                 onmouseover="this.style.background='rgba(251,191,36,.22)'" onmouseout="this.style.background='rgba(251,191,36,.12)'">
                <i class="fa-solid fa-map"></i> Open in Google Maps
              </a>
            </div>
            <div style="font-size:.72rem;color:rgba(255,255,255,.4);margin-top:.5rem">
              <i class="fa-solid fa-circle-info"></i> Travel to this address to perform the service
            </div>
          </div>
        </div>
      </div>
      <?php elseif ($locType === 'On-site' && !$custAddr): ?>
      <!-- On-site but no address provided -->
      <div class="pv-card bd-card" style="border-color:rgba(244,63,94,.25);background:rgba(244,63,94,.05)">
        <div class="bd-section-title" style="color:#f43f5e">
          <i class="fa-solid fa-triangle-exclamation"></i> Home Service Address Missing
        </div>
        <p style="font-size:.84rem;color:rgba(255,255,255,.55);margin:.5rem 0 0">
          This booking is marked as Home Service but the customer did not provide a service address.
          Please contact the customer to confirm the location before proceeding.
        </p>
        <?php if ($custProfAddr): ?>
        <div style="margin-top:.75rem;padding:.7rem .9rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:10px;font-size:.84rem;color:#fff">
          <div style="font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.4);margin-bottom:.25rem">Profile home address (may be used as reference)</div>
          <?= htmlspecialchars($custProfAddr) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Appointment Details -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-calendar-days"></i> Appointment Details</div>
        <div class="bd-detail-grid">
          <div class="bd-detail-item">
            <div class="bd-detail-label">Date</div>
            <div class="bd-detail-val"><?= $bookDateShort ?></div>
          </div>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Time</div>
            <div class="bd-detail-val"><?= $bookTime ?? '—' ?></div>
          </div>
          <?php if ($duration): ?>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Duration</div>
            <div class="bd-detail-val">⏱ <?= $duration ?></div>
          </div>
          <?php endif; ?>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Location Type</div>
            <div class="bd-detail-val" style="color:<?= $locInfo['color'] ?>">
              <i class="fa-solid <?= $locInfo['icon'] ?>"></i> <?= $locInfo['label'] ?>
            </div>
          </div>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Booked On</div>
            <div class="bd-detail-val" style="font-size:.8rem"><?= $createdAt ?></div>
          </div>
          <div class="bd-detail-item">
            <div class="bd-detail-label">Booking ID</div>
            <div class="bd-detail-val bd-mono">#<?= str_pad((int)$booking['id'], 4, '0', STR_PAD_LEFT) ?></div>
          </div>
        </div>

        <?php if (in_array($locType, ['In-shop','Flexible']) && $shopAddr): ?>
        <div style="display:flex;align-items:flex-start;gap:.7rem;padding:.85rem 1rem;border-radius:12px;border:1px solid rgba(74,222,128,.2);background:rgba(74,222,128,.07);margin-top:1rem">
          <i class="fa-solid fa-map-pin" style="color:#4ade80;margin-top:.1rem;flex-shrink:0"></i>
          <div>
            <div style="font-size:.7rem;font-family:var(--font-m);letter-spacing:.08em;text-transform:uppercase;color:#4ade80;margin-bottom:.25rem">Shop Address</div>
            <div style="font-size:.9rem;font-weight:700;color:#fff;line-height:1.4"><?= htmlspecialchars($shopAddr) ?></div>
            <div style="font-size:.72rem;color:var(--muted);margin-top:.2rem"><i class="fa-solid fa-circle-info"></i> Customer will come to your shop</div>
          </div>
        </div>
        <?php elseif ($locType === 'Remote'): ?>
        <div style="padding:.75rem 1rem;border-radius:12px;border:1px solid rgba(96,165,250,.2);background:rgba(96,165,250,.07);margin-top:1rem;font-size:.84rem;color:#60a5fa">
          <i class="fa-solid fa-wifi"></i> This service will be delivered online / remotely
        </div>
        <?php endif; ?>
      </div>

      <!-- Customer Notes -->
      <?php if (!empty($booking['notes'])): ?>
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-note-sticky"></i> Customer Notes</div>
        <p class="bd-notes"><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
      </div>
      <?php endif; ?>

      <!-- Cancellation Reason -->
      <?php if (in_array($status, ['cancelled','rejected']) && !empty($booking['cancellation_reason'])): ?>
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-circle-xmark"></i> Cancellation Details</div>
        <div class="cancel-reason-box">
          <div class="cancel-reason-lbl">Reason</div>
          <?= nl2br(htmlspecialchars($booking['cancellation_reason'])) ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- SIDEBAR -->
    <div class="bd-sidebar">

      <!-- Payment -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-credit-card"></i> Payment Summary</div>

        <?php
          $servicePrice   = (float)($booking['service_price'] ?? $booking['total_amount'] ?? 0);
          $totalAmount    = (float)($booking['total_amount'] ?? 0);
          $homeServiceFee = $totalAmount - $servicePrice;
          $isHomeService  = $locType === 'On-site';
          $payMethod      = $booking['payment_method'] ?? null;
          $payIcons = [
            'cash'          => '<i class="fa-solid fa-money-bill-wave"></i>',
            'gcash'         => '<i class="fa-solid fa-mobile-screen"></i>',
            'paymaya'       => '<i class="fa-solid fa-mobile-screen"></i>',
            'card'          => '<i class="fa-solid fa-credit-card"></i>',
            'credit_card'   => '<i class="fa-solid fa-credit-card"></i>',
            'debit_card'    => '<i class="fa-solid fa-credit-card"></i>',
            'bank_transfer' => '<i class="fa-solid fa-building-columns"></i>',
          ];
          $payIcon  = $payIcons[strtolower($payMethod ?? '')] ?? '<i class="fa-solid fa-wallet"></i>';
          $payLabel = $payMethod ? ucwords(str_replace('_', ' ', $payMethod)) : null;
        ?>

        <?php if ($payLabel): ?>
        <div class="bd-price-row bd-pay-method-row">
          <span class="bd-pay-method-label">Payment Type</span>
          <span class="bd-pay-method-val"><?= $payIcon ?> <?= htmlspecialchars($payLabel) ?></span>
        </div>
        <div class="bd-price-divider"></div>
        <?php endif; ?>

        <div class="bd-price-row">
          <span>Amount</span>
          <span class="bd-price-val">₱<?= number_format($servicePrice, 2) ?></span>
        </div>

        <?php if ($isHomeService && $homeServiceFee > 0): ?>
        <div class="bd-price-row">
          <span>Home service fee</span>
          <span class="bd-price-val">₱<?= number_format($homeServiceFee, 2) ?></span>
        </div>
        <?php endif; ?>

        <div class="bd-price-divider"></div>
        <div class="bd-price-row bd-price-row--total">
          <span>Total</span>
          <span class="bd-price-total">₱<?= number_format($totalAmount, 2) ?></span>
        </div>
        <?php if (!empty($booking['loyalty_points_earned'])): ?>
        <div class="bd-loyalty-note">⭐ +<?= (int)$booking['loyalty_points_earned'] ?> loyalty points earned by customer</div>
        <?php endif; ?>
      </div>

      <!-- Timeline -->
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-rotate"></i> Status Timeline</div>
        <div class="bd-timeline">
          <?php if (in_array($status, ['cancelled','rejected','rescheduled'])): ?>
            <?php foreach ($timelineSteps as $key => $info): $done = ($key === 'pending'); ?>
            <div class="bd-timeline-step <?= $done ? 'done' : 'muted' ?>">
              <div class="bd-tl-dot <?= $done ? 'done' : '' ?>"></div>
              <div class="bd-tl-content"><div class="bd-tl-label"><i class="fa-solid <?= $info['icon'] ?>"></i> <?= $info['label'] ?></div></div>
            </div>
            <?php endforeach; ?>
            <div class="bd-timeline-step bd-timeline-step--cancel">
              <div class="bd-tl-dot bd-tl-dot--cancel"></div>
              <div class="bd-tl-content"><div class="bd-tl-label">✖ <?= ucfirst($status) ?></div></div>
            </div>
          <?php else: ?>
            <?php foreach ($timelineSteps as $key => $info):
              $stepIdx = array_search($key, $statusOrder);
              $done    = ($currentIdx !== false && $stepIdx <= $currentIdx);
              $active  = ($key === $status);
            ?>
            <div class="bd-timeline-step <?= $done ? 'done' : 'muted' ?> <?= $active ? 'active' : '' ?>">
              <div class="bd-tl-dot <?= $done ? 'done' : '' ?> <?= $active ? 'active' : '' ?>"></div>
              <div class="bd-tl-content">
                <div class="bd-tl-label"><i class="fa-solid <?= $info['icon'] ?>"></i> <?= $info['label'] ?></div>
                <?php if ($active): ?><div style="font-size:.72rem;color:var(--muted);margin-top:.15rem"><?= $info['sub'] ?></div><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Actions -->
      <?php if ($isActive): ?>
      <div class="pv-card bd-card">
        <div class="bd-section-title"><i class="fa-solid fa-bolt"></i> Actions</div>
        <div class="bd-actions">
          <?php if ($canStart): ?>
          <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>">
            <input type="hidden" name="action" value="start">
            <button type="submit" class="cd-btn cd-btn--start"><i class="fa-solid fa-play"></i> Start Service</button>
          </form>
          <?php endif; ?>
          <?php if ($canComplete): ?>
          <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>">
            <input type="hidden" name="action" value="complete">
            <button type="submit" class="cd-btn cd-btn--complete"><i class="fa-solid fa-medal"></i> Mark as Completed</button>
          </form>
          <?php endif; ?>
          <?php if ($canConfirm): ?>
          <button type="button" class="cd-btn cd-btn--confirm" onclick="openConfirmModal()"><i class="fa-solid fa-circle-check"></i> Confirm Booking</button>
          <?php endif; ?>
          <button type="button" class="cd-btn cd-btn--delete" onclick="openDeleteModal()">
            <i class="fa-solid fa-xmark"></i> Cancel &amp; Delete Order
          </button>
          <button type="button" class="cd-btn cd-btn--resched" onclick="openReschedModal()">
            <i class="fa-solid fa-rotate-right"></i> Suggest Reschedule
          </button>
        </div>
      </div>
      <?php endif; ?>

      <div class="bd-actions">
        <a href="<?= BASE_URL ?>provider/bookings" class="cd-btn cd-btn--ghost"><i class="fa-solid fa-arrow-left"></i> All Bookings</a>
      </div>

    </div>
  </div>
</main>

<!-- DELETE MODAL -->
<div class="pv-modal-overlay" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
  <div class="pv-modal pv-modal--delete">

    <button class="pv-modal-close pv-modal-close--abs" onclick="closeDeleteModal()" aria-label="Close">✕</button>

    <div class="modal-centered-header" aria-hidden="true">
      <div class="modal-icon-ring modal-icon-ring--red">
        <svg viewBox="0 0 24 24" fill="none" width="26" height="26">
          <path d="M12 8v5M12 15.5v.5" stroke="#FB7185" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
      </div>
      <h2 class="modal-title" id="deleteModalTitle">Cancel &amp; Delete Order</h2>
      <p class="modal-sub">
        You are about to cancel the booking for
        <strong><?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?></strong>
        (<em><?= htmlspecialchars($booking['service_name'] ?? '') ?></em>).<br>
        The customer will be <span class="hl-red">immediately notified</span> with your reason.
        This action <span class="hl-red">cannot be undone.</span>
      </p>
    </div>

    <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>">
      <input type="hidden" name="action" value="delete">
      <label class="modal-field-label" for="delReason">
        Reason for cancellation <span class="modal-required">* required</span>
      </label>
      <textarea id="delReason" name="reason" class="pv-textarea"
                placeholder="e.g. Schedule conflict, Equipment issue, Emergency unavailability…"
                maxlength="400" required></textarea>
      <div class="modal-char-count"><span id="delCharCount">0</span> / 400</div>
      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--red" id="delSubmitBtn" disabled>Yes</button>
        <button type="button" class="modal-btn modal-btn--no" onclick="closeDeleteModal()">No</button>
      </div>
    </form>

  </div>
</div>

<div id="toastContainer" class="toast-container" aria-live="polite"></div>

<!-- CONFIRM MODAL -->
<div class="pv-modal-overlay" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
  <div class="pv-modal pv-modal--confirm">

    <button class="pv-modal-close pv-modal-close--abs" onclick="closeConfirmModal()" aria-label="Close">✕</button>

    <div class="modal-centered-header" aria-hidden="true">
      <div class="modal-icon-ring modal-icon-ring--green">
        <svg viewBox="0 0 24 24" fill="none" width="26" height="26">
          <path d="M5 12l5 5L19 7" stroke="#4ADE80" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 class="modal-title" id="confirmModalTitle">Confirm Booking</h2>
      <p class="modal-sub">
        You are about to <span class="hl-green">confirm</span> the booking for
        <strong><?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?></strong>
        (<em><?= htmlspecialchars($booking['service_name'] ?? '') ?></em>).<br>
        The customer will be notified immediately.
      </p>
    </div>

    <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>" id="confirmForm">
      <input type="hidden" name="action" value="confirm">
      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--green">Yes</button>
        <button type="button" class="modal-btn modal-btn--no" onclick="closeConfirmModal()">No</button>
      </div>
    </form>

  </div>
</div>

<!-- RESCHEDULE MODAL -->
<div class="pv-modal-overlay" id="reschedModal" role="dialog" aria-modal="true" aria-labelledby="reschedModalTitle">
  <div class="pv-modal pv-modal--resched">

    <button class="pv-modal-close pv-modal-close--abs" onclick="closeReschedModal()" aria-label="Close">✕</button>

    <div class="modal-centered-header">
      <div class="modal-icon-ring modal-icon-ring--amber">
        <svg viewBox="0 0 24 24" fill="none" width="26" height="26">
          <path d="M21 12A9 9 0 1 1 12 3" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/>
          <path d="M21 3v5h-5" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 class="modal-title" id="reschedModalTitle">Suggest Reschedule</h2>
      <p class="modal-sub">
        Suggest a new schedule for
        <strong><?= htmlspecialchars(($booking['customer_first'] ?? '') . ' ' . ($booking['customer_last'] ?? '')) ?></strong>
        (<em><?= htmlspecialchars($booking['service_name'] ?? '') ?></em>).<br>
        <span class="modal-sub-note">Current booking: <span class="hl-amber"><?= $bookDate ?><?= $bookTime ? ' · ' . $bookTime : '' ?></span></span>
      </p>
    </div>

    <form method="POST" action="<?= BASE_URL ?>provider/bookings/<?= (int)$booking['id'] ?>">
      <input type="hidden" name="action" value="reschedule">

      <div class="resch-row">
        <div class="resch-field">
          <label class="modal-field-label" for="reschedDate">Suggested Date <span class="modal-required">* required</span></label>
          <input type="date" id="reschedDate" name="suggested_date" class="pv-input" required>
        </div>
        <div class="resch-field">
          <label class="modal-field-label" for="reschedTime">Suggested Time <span class="modal-required">* required</span></label>
          <input type="time" id="reschedTime" name="suggested_time" class="pv-input" required>
        </div>
      </div>

      <label class="modal-field-label" for="reschedNote" style="display:block;margin-top:.85rem">
        Reason / Note to Customer <span class="modal-required">* required</span>
      </label>
      <textarea id="reschedNote" name="resched_reason" class="pv-textarea"
                placeholder="e.g. I have a conflict at the original time. I'm suggesting this new slot as it works better for my schedule…"
                maxlength="500" required></textarea>
      <div class="modal-char-count"><span id="reschedCharCount">0</span> / 500</div>

      <div class="modal-foot">
        <button type="submit" class="modal-btn modal-btn--amber" id="reschedSubmitBtn" disabled>
          Send Reschedule Suggestion
        </button>
      </div>
    </form>

  </div>
</div>

<script>
/* Confirm modal */
function openConfirmModal() {
  document.getElementById('confirmModal').classList.add('is-open');
}
function closeConfirmModal() {
  document.getElementById('confirmModal').classList.remove('is-open');
}
document.getElementById('confirmModal').addEventListener('click', function(e) {
  if (e.target === this) closeConfirmModal();
});

/* Delete modal */
function openDeleteModal() {
  document.getElementById('delReason').value = '';
  document.getElementById('delCharCount').textContent = '0';
  document.getElementById('delSubmitBtn').disabled = true;
  document.getElementById('deleteModal').classList.add('is-open');
  setTimeout(function() { document.getElementById('delReason').focus(); }, 120);
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('is-open');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});
document.getElementById('delReason').addEventListener('input', function() {
  document.getElementById('delCharCount').textContent = this.value.length;
  document.getElementById('delSubmitBtn').disabled = this.value.trim().length < 5;
});

/* Reschedule modal */
function openReschedModal() {
  document.getElementById('reschedDate').value = '';
  document.getElementById('reschedTime').value = '';
  document.getElementById('reschedNote').value = '';
  document.getElementById('reschedCharCount').textContent = '0';
  document.getElementById('reschedSubmitBtn').disabled = true;
  document.getElementById('reschedModal').classList.add('is-open');
  setTimeout(function() { document.getElementById('reschedDate').focus(); }, 120);
}
function closeReschedModal() {
  document.getElementById('reschedModal').classList.remove('is-open');
}
document.getElementById('reschedModal').addEventListener('click', function(e) {
  if (e.target === this) closeReschedModal();
});
function validateReschedForm() {
  var date = document.getElementById('reschedDate').value.trim();
  var time = document.getElementById('reschedTime').value.trim();
  var note = document.getElementById('reschedNote').value.trim();
  document.getElementById('reschedSubmitBtn').disabled = !(date && time && note.length >= 5);
}
document.getElementById('reschedNote').addEventListener('input', function() {
  document.getElementById('reschedCharCount').textContent = this.value.length;
  validateReschedForm();
});
document.getElementById('reschedDate').addEventListener('change', validateReschedForm);
document.getElementById('reschedTime').addEventListener('change', validateReschedForm);

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeConfirmModal();
    closeDeleteModal();
    closeReschedModal();
  }
});

function showToast(msg, type) {
  var c = document.getElementById('toastContainer'), t = document.createElement('div');
  t.className = 'toast toast--' + (type || 'success');
  var ico = type === 'success'
    ? '<svg viewBox="0 0 16 16" fill="none"><path d="M3 8l3.5 3.5L13 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    : '<svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.6"/><path d="M8 5v3.5M8 10.5v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
  t.innerHTML = '<span style="display:flex;flex-shrink:0">' + ico + '</span><span>' + msg + '</span>';
  c.appendChild(t);
  requestAnimationFrame(function(){ requestAnimationFrame(function(){ t.classList.add('is-visible'); }); });
  setTimeout(function(){ t.classList.remove('is-visible'); t.addEventListener('transitionend', function(){ t.remove(); },{once:true}); }, 4000);
}
<?php if ($flash): ?>
showToast('<?= addslashes(htmlspecialchars_decode($flash['msg'])) ?>', '<?= $flash['type'] === 'success' ? 'success' : 'error' ?>');
<?php endif; ?>
</script>
</body>
</html>