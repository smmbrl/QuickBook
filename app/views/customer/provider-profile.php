<?php
// app/views/customer/provider-profile.php

require_once __DIR__ . '/../../../config/database.php';
$db         = Database::getInstance();
$customerId = (int)($_SESSION['user_id']  ?? 0);
$userName   = htmlspecialchars($_SESSION['user_name']  ?? 'Customer');
$initials   = strtoupper(substr($userName, 0, 2));

// ── Provider profile ID comes from URL ───────────────────────
// $id is passed from ProviderController::show(string $id)
$providerId = (int)($id ?? 0);

// ── Fetch provider profile ────────────────────────────────────
$stmt = $db->prepare("
    SELECT pp.*, u.first_name, u.last_name, u.email, u.avatar_url,
           c.name as category_name, c.slug as category_slug
    FROM tbl_provider_profiles pp
    JOIN tbl_users u ON pp.user_id = u.id
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    WHERE pp.id = ? AND pp.is_approved = 1 AND u.is_active = 1
");
$stmt->execute([$providerId]);
$provider = $stmt->fetch();

if (!$provider) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider not found.'];
    header('Location: ' . BASE_URL . 'browse'); exit;
}

// ── Services ──────────────────────────────────────────────────
$svcStmt = $db->prepare("
    SELECT * FROM tbl_services
    WHERE provider_id = ? AND is_active = 1
    ORDER BY service_type, price ASC
");
$svcStmt->execute([$providerId]);
$services = $svcStmt->fetchAll();

// ── Availability ──────────────────────────────────────────────
$avStmt = $db->prepare("
    SELECT * FROM tbl_provider_availability
    WHERE provider_id = ? AND is_available = 1
    ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')
");
$avStmt->execute([$providerId]);
$availability = $avStmt->fetchAll();

// ── Reviews ───────────────────────────────────────────────────
$revStmt = $db->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM tbl_reviews r
    JOIN tbl_users u ON r.customer_id = u.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC LIMIT 5
");
$revStmt->execute([$providerId]);
$reviews = $revStmt->fetchAll();

// ── Loyalty points (nav) ──────────────────────────────────────
$stPoints = $db->prepare("SELECT COALESCE(SUM(points),0) FROM tbl_loyalty_points WHERE user_id = ?");
$stPoints->execute([$customerId]);
$loyaltyPoints = (int)$stPoints->fetchColumn();
$loyaltyTier   = match(true) {
    $loyaltyPoints >= 2000 => 'Gold',
    $loyaltyPoints >= 1000 => 'Silver',
    default                => 'Bronze',
};

// ── Upcoming count (nav badge) ────────────────────────────────
$stUp = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE customer_id = ? AND status IN ('pending','confirmed') AND booking_date >= CURDATE()");
$stUp->execute([$customerId]);
$upcomingCount = (int)$stUp->fetchColumn();

// ── Flash message ─────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Helpers ───────────────────────────────────────────────────
$catEmojiMap = [
    'barbershop'       => '✂️',
    'hair-salon'       => '💇',
    'nail-care'        => '💅',
    'massage-therapy'  => '💆',
    'skincare-facial'  => '🧴',
    'fitness-training' => '🏋️',
    'home-cleaning'    => '🧹',
    'pet-grooming'     => '🐾',
    'event-styling'    => '🎨',
    'dental'           => '🦷',
    'tutoring'         => '📚',
];
$catEmoji = $catEmojiMap[$provider['category_slug']] ?? '🛠️';

function renderStars(float $r): string {
    $f = floor($r); $h = ($r - $f) >= .5 ? 1 : 0; $e = 5 - $f - $h;
    return str_repeat('★', $f) . ($h ? '½' : '') . str_repeat('☆', $e);
}

$serviceTypeColors = [
    'Barber'       => 'blue',
    'Hair Stylist' => 'purple',
    'Nail Tech'    => 'pink',
    'Massage'      => 'green',
    'Facial'       => 'yellow',
    'Trainer'      => 'orange',
    'Cleaner'      => 'teal',
    'Pet Groomer'  => 'gold',
];

$dayAbbr = ['Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed',
            'Thursday'=>'Thu','Friday'=>'Fri','Saturday'=>'Sat','Sunday'=>'Sun'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — <?= htmlspecialchars($provider['business_name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_provider.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>
<div class="bg-orb bg-orb-1" aria-hidden="true"></div>
<div class="bg-orb bg-orb-2" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAV
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Customer navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard"  class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings"   class="pv-nav-link">
        Bookings<?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse"     class="pv-nav-link is-active">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty"    class="pv-nav-link">Loyalty</a>
      <a href="<?= BASE_URL ?>profile"    class="pv-nav-link">Profile</a>
    </div>
    <div class="pv-nav-end">
      <div class="pv-points-badge">⭐ <?= number_format($loyaltyPoints) ?> pts</div>
      <div class="pv-nav-av" aria-hidden="true"><?= $initials ?></div>
      <div class="pv-nav-user">
        <div class="pv-nav-user-name"><?= $userName ?></div>
        <div class="pv-nav-user-role"><?= $loyaltyTier ?> Member</div>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>
  </div>
</nav>

<!-- ══════════════════════════════════════
     FLASH MESSAGE
══════════════════════════════════════ -->
<?php if ($flash): ?>
<div class="pv-flash pv-flash--<?= $flash['type'] ?>" role="alert">
  <span><?= $flash['type'] === 'success' ? '✅' : '⚠️' ?></span>
  <?= htmlspecialchars($flash['msg']) ?>
  <button class="pv-flash-close" onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════
     HERO — provider banner
══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div class="pv-provider-hero-wrap">
      <!-- Avatar -->
      <div class="pv-provider-av" aria-hidden="true"><?= $catEmoji ?></div>
      <!-- Info -->
      <div class="pv-provider-info">
        <p class="pv-hero-eyebrow">
          <span class="pv-dot-pulse" aria-hidden="true"></span>
          <?= htmlspecialchars($provider['category_name'] ?? 'Service Provider') ?>
        </p>
        <h1 class="pv-hero-name"><?= htmlspecialchars($provider['business_name']) ?></h1>
        <div class="pv-provider-meta">
          <?php if ($provider['avg_rating'] > 0): ?>
          <span class="pv-meta-chip pv-meta-chip--gold">
            ⭐ <?= number_format($provider['avg_rating'], 1) ?>
            <span class="pv-meta-chip-sub">(<?= (int)$provider['total_reviews'] ?> reviews)</span>
          </span>
          <?php endif; ?>
          <span class="pv-meta-chip">
            📍 <?= htmlspecialchars(($provider['barangay'] ? $provider['barangay'].', ' : '') . $provider['city']) ?>
          </span>
          <?php if ($provider['offers_home_service']): ?>
          <span class="pv-meta-chip pv-meta-chip--green">🏠 Home Service Available</span>
          <?php endif; ?>
        </div>
        <?php if ($provider['bio']): ?>
        <p class="pv-provider-bio"><?= htmlspecialchars($provider['bio']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick stats -->
    <div class="pv-provider-quick-stats">
      <div class="pv-qs-item">
        <span class="pv-qs-val"><?= count($services) ?></span>
        <span class="pv-qs-label">Services</span>
      </div>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val"><?= count($availability) ?></span>
        <span class="pv-qs-label">Days Open</span>
      </div>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <span class="pv-qs-val gold"><?= (int)$provider['total_reviews'] ?></span>
        <span class="pv-qs-label">Reviews</span>
      </div>
      <div class="pv-qs-div"></div>
      <div class="pv-qs-item">
        <?php $minPrice = $services ? min(array_column($services, 'price')) : 0; ?>
        <span class="pv-qs-val">₱<?= $minPrice ? number_format($minPrice, 0) : '—' ?></span>
        <span class="pv-qs-label">Starting Price</span>
      </div>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     MAIN
══════════════════════════════════════ -->
<main class="pv-page" role="main">
  <div class="pv-layout">

    <!-- ── LEFT: Services ── -->
    <div class="pv-main">

      <!-- Breadcrumb -->
      <nav class="pv-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>browse">Browse</a>
        <span aria-hidden="true">›</span>
        <?php if ($provider['category_name']): ?>
          <a href="<?= BASE_URL ?>browse?category=<?= (int)$provider['category_id'] ?>"><?= htmlspecialchars($provider['category_name']) ?></a>
          <span aria-hidden="true">›</span>
        <?php endif; ?>
        <span><?= htmlspecialchars($provider['business_name']) ?></span>
      </nav>

      <!-- Services card -->
      <div class="pv-card">
        <div class="pv-card-head">
          <div>
            <h2>Services Offered</h2>
            <span class="pv-card-sub"><?= count($services) ?> active service<?= count($services) !== 1 ? 's' : '' ?></span>
          </div>
        </div>

        <?php if (empty($services)): ?>
        <div class="pv-empty-state">
          <div class="pv-empty-icon" aria-hidden="true">🛠️</div>
          <p>This provider hasn't listed any services yet.</p>
          <a href="<?= BASE_URL ?>browse" class="pv-empty-cta">Back to Browse →</a>
        </div>
        <?php else: ?>
        <div class="pv-service-list" role="list">
          <?php foreach ($services as $s):
            $color = $serviceTypeColors[$s['service_type']] ?? 'gold';
          ?>
          <div class="pv-service-item" role="listitem">
            <div class="pv-service-accent pv-service-accent--<?= $color ?>"></div>
            <div class="pv-service-info">
              <div class="pv-service-name"><?= htmlspecialchars($s['name']) ?></div>
              <?php if ($s['description']): ?>
                <div class="pv-service-desc"><?= htmlspecialchars($s['description']) ?></div>
              <?php endif; ?>
              <div class="pv-service-tags">
                <span class="pv-stag pv-stag--type"><?= htmlspecialchars($s['service_type']) ?></span>
                <span class="pv-stag pv-stag--loc">📍 <?= htmlspecialchars($s['location_type']) ?></span>
                <span class="pv-stag pv-stag--dur">⏱ <?= (int)$s['duration_minutes'] ?> min</span>
              </div>
            </div>
            <div class="pv-service-right">
              <div class="pv-service-price">₱<?= number_format((float)$s['price'], 2) ?></div>
              <button class="pv-book-btn"
                      onclick="openBookingModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>', '<?= number_format((float)$s['price'], 2) ?>', '<?= (int)$s['duration_minutes'] ?>', '<?= htmlspecialchars(addslashes($s['location_type'])) ?>')"
                      aria-label="Book <?= htmlspecialchars($s['name']) ?>">
                Book Now
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Reviews card -->
      <?php if (!empty($reviews)): ?>
      <div class="pv-card">
        <div class="pv-card-head">
          <div>
            <h2>Customer Reviews</h2>
            <span class="pv-card-sub">
              ⭐ <?= number_format((float)$provider['avg_rating'], 1) ?> · <?= (int)$provider['total_reviews'] ?> review<?= (int)$provider['total_reviews'] !== 1 ? 's' : '' ?>
            </span>
          </div>
        </div>
        <div class="pv-review-list">
          <?php foreach ($reviews as $r): ?>
          <div class="pv-review-item">
            <div class="pv-review-av"><?= strtoupper(substr($r['first_name'], 0, 1)) ?></div>
            <div class="pv-review-body">
              <div class="pv-review-header">
                <span class="pv-review-name"><?= htmlspecialchars($r['first_name'].' '.substr($r['last_name'],0,1).'.') ?></span>
                <span class="pv-review-stars"><?= renderStars((float)$r['rating']) ?></span>
                <span class="pv-review-date"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
              </div>
              <?php if ($r['comment']): ?>
                <p class="pv-review-text"><?= htmlspecialchars($r['comment']) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /pv-main -->

    <!-- ── SIDEBAR ── -->
    <aside class="pv-sidebar" aria-label="Provider details">

      <!-- Availability card -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Availability</h2></div>
        <?php if (empty($availability)): ?>
          <div class="pv-empty-state" style="padding:1.5rem">
            <p style="font-size:.82rem;color:var(--muted)">No schedule set yet.</p>
          </div>
        <?php else: ?>
        <div class="pv-avail-list">
          <?php foreach ($availability as $av): ?>
          <div class="pv-avail-item">
            <span class="pv-avail-day"><?= $dayAbbr[$av['day_of_week']] ?? $av['day_of_week'] ?></span>
            <span class="pv-avail-time">
              <?= date('g:i A', strtotime($av['start_time'])) ?> – <?= date('g:i A', strtotime($av['end_time'])) ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Location card -->
      <div class="pv-card">
        <div class="pv-card-head"><h2>Location</h2></div>
        <div class="pv-location-body">
          <div class="pv-location-row">
            <span class="pv-location-icon">📍</span>
            <span><?= htmlspecialchars(implode(', ', array_filter([
                $provider['address']  ?? '',
                $provider['barangay'] ?? '',
                $provider['city']     ?? '',
            ]))) ?></span>
          </div>
          <?php if ($provider['offers_home_service']): ?>
          <div class="pv-location-row pv-location-home">
            <span class="pv-location-icon">🏠</span>
            <span>Home service available</span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick booking CTA -->
      <?php if (!empty($services)): ?>
      <div class="pv-card pv-card--cta">
        <div class="pv-cta-body">
          <div class="pv-cta-title">Ready to book?</div>
          <div class="pv-cta-sub">Choose a service above and pick your preferred time.</div>
          <button class="pv-cta-btn"
                  onclick="openBookingModal(<?= $services[0]['id'] ?>, '<?= htmlspecialchars(addslashes($services[0]['name'])) ?>', '<?= number_format((float)$services[0]['price'], 2) ?>', '<?= (int)$services[0]['duration_minutes'] ?>', '<?= htmlspecialchars(addslashes($services[0]['location_type'])) ?>')">
            Book a Service
          </button>
        </div>
      </div>
      <?php endif; ?>

    </aside>

  </div><!-- /pv-layout -->
</main>

<!-- ══════════════════════════════════════
     BOOKING MODAL
══════════════════════════════════════ -->
<div class="pv-modal-overlay" id="bookingModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" hidden>
  <div class="pv-modal">

    <div class="pv-modal-head">
      <div>
        <h2 class="pv-modal-title" id="modalTitle">Book a Service</h2>
        <p class="pv-modal-sub" id="modalServiceName">—</p>
      </div>
      <button class="pv-modal-close" onclick="closeBookingModal()" aria-label="Close">✕</button>
    </div>

    <!-- Selected service summary -->
    <div class="pv-modal-summary">
      <div class="pv-modal-summary-item">
        <span class="pv-modal-summary-label">Price</span>
        <span class="pv-modal-summary-val" id="modalPrice">—</span>
      </div>
      <div class="pv-modal-summary-item">
        <span class="pv-modal-summary-label">Duration</span>
        <span class="pv-modal-summary-val" id="modalDuration">—</span>
      </div>
      <div class="pv-modal-summary-item">
        <span class="pv-modal-summary-label">Location</span>
        <span class="pv-modal-summary-val" id="modalLocation">—</span>
      </div>
    </div>

    <form method="POST" action="<?= BASE_URL ?>book" class="pv-modal-form" id="bookingForm">
      <input type="hidden" name="service_id"  id="formServiceId"  value="">
      <input type="hidden" name="provider_id" value="<?= $providerId ?>">

      <!-- Service selector (if multiple) -->
      <?php if (count($services) > 1): ?>
      <div class="pv-form-group">
        <label class="pv-form-label" for="formServiceSelect">Service</label>
        <select class="pv-form-control" id="formServiceSelect" onchange="onServiceChange(this)" required>
          <?php foreach ($services as $s): ?>
          <option value="<?= $s['id'] ?>"
                  data-price="<?= number_format((float)$s['price'], 2) ?>"
                  data-duration="<?= (int)$s['duration_minutes'] ?>"
                  data-location="<?= htmlspecialchars($s['location_type']) ?>"
                  data-name="<?= htmlspecialchars($s['name']) ?>">
            <?= htmlspecialchars($s['name']) ?> — ₱<?= number_format((float)$s['price'], 2) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <div class="pv-form-row">
        <!-- Date -->
        <div class="pv-form-group">
          <label class="pv-form-label" for="formDate">Booking Date <span class="pv-req">*</span></label>
          <input type="date" class="pv-form-control" id="formDate" name="booking_date"
                 min="<?= date('Y-m-d') ?>" required>
        </div>

        <!-- Time -->
        <div class="pv-form-group">
          <label class="pv-form-label" for="formTime">Preferred Time <span class="pv-req">*</span></label>
          <input type="time" class="pv-form-control" id="formTime" name="booking_time" required>
        </div>
      </div>

      <!-- Location type -->
      <?php if ($provider['offers_home_service']): ?>
      <div class="pv-form-group">
        <label class="pv-form-label">Service Location</label>
        <div class="pv-radio-row">
          <label class="pv-radio-label">
            <input type="radio" name="location_type" value="In-shop" checked> In-shop
          </label>
          <label class="pv-radio-label">
            <input type="radio" name="location_type" value="Home"> Home Service
          </label>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="location_type" value="In-shop">
      <?php endif; ?>

      <!-- Notes -->
      <div class="pv-form-group">
        <label class="pv-form-label" for="formNotes">Notes <span class="pv-optional">(optional)</span></label>
        <textarea class="pv-form-control pv-textarea" id="formNotes" name="notes"
                  rows="3" placeholder="Any special requests or notes for the provider…"></textarea>
      </div>

      <!-- Loyalty notice -->
      <div class="pv-loyalty-notice">
        ⭐ You'll earn <strong>10 loyalty points</strong> for placing this booking!
      </div>

      <div class="pv-modal-actions">
        <button type="button" class="pv-btn pv-btn--ghost" onclick="closeBookingModal()">Cancel</button>
        <button type="submit" class="pv-btn pv-btn--primary" id="submitBtn">Confirm Booking</button>
      </div>
    </form>

  </div>
</div>

<script>
(function () {
  const modal        = document.getElementById('bookingModal');
  const formSvcId    = document.getElementById('formServiceId');
  const modalSvcName = document.getElementById('modalServiceName');
  const modalPrice   = document.getElementById('modalPrice');
  const modalDur     = document.getElementById('modalDuration');
  const modalLoc     = document.getElementById('modalLocation');
  const svcSelect    = document.getElementById('formServiceSelect');

  window.openBookingModal = function (svcId, svcName, price, duration, location) {
    formSvcId.value       = svcId;
    modalSvcName.textContent = svcName;
    modalPrice.textContent   = '₱' + price;
    modalDur.textContent     = duration + ' min';
    modalLoc.textContent     = location;

    // Sync select if present
    if (svcSelect) {
      svcSelect.value = svcId;
    }

    // Set min date to today
    const dateInput = document.getElementById('formDate');
    dateInput.min = new Date().toISOString().split('T')[0];

    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    modal.querySelector('.pv-modal').focus?.();
  };

  window.closeBookingModal = function () {
    modal.hidden = true;
    document.body.style.overflow = '';
  };

  window.onServiceChange = function (sel) {
    const opt = sel.options[sel.selectedIndex];
    formSvcId.value           = opt.value;
    modalSvcName.textContent  = opt.dataset.name;
    modalPrice.textContent    = '₱' + opt.dataset.price;
    modalDur.textContent      = opt.dataset.duration + ' min';
    modalLoc.textContent      = opt.dataset.location;
  };

  // Close on overlay click
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeBookingModal();
  });

  // Close on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden) closeBookingModal();
  });

  // Prevent double submit
  document.getElementById('bookingForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting…';
  });
})();
</script>

</body>
</html>