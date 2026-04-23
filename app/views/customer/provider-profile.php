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
            <span class="pv-avail-day"><?= htmlspecialchars($av['day_of_week']) ?></span>
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

<style>
/* ── Availability schedule strip (inside modal) ─────────────── */
.av-strip {
  display: flex; gap: .3rem; flex-wrap: wrap;
  margin-bottom: 1.1rem;
}
.av-strip-pill {
  display: flex; flex-direction: column; align-items: center;
  padding: .3rem .5rem; border-radius: 8px; font-size: .65rem;
  font-family: var(--font-m, monospace); font-weight: 600;
  border: 1px solid transparent; transition: all .2s; min-width: 44px;
  cursor: default; user-select: none;
}
.av-strip-pill.is-open {
  background: rgba(201,168,76,.10); border-color: rgba(201,168,76,.28);
  color: #C9A84C;
}
.av-strip-pill.is-closed {
  background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.07);
  color: rgba(255,255,255,.22);
}
.av-strip-pill.is-selected {
  background: rgba(201,168,76,.22); border-color: rgba(201,168,76,.6);
  color: #E8C96A; box-shadow: 0 0 0 2px rgba(201,168,76,.2);
  transform: translateY(-2px);
}
.av-strip-pill.is-selected.is-closed {
  background: rgba(244,63,94,.12); border-color: rgba(244,63,94,.4);
  color: #F43F5E; box-shadow: 0 0 0 2px rgba(244,63,94,.15);
}
.av-strip-pill-day  { font-size: .62rem; letter-spacing: .04em; margin-bottom: .15rem; }
.av-strip-pill-dot  { width: 5px; height: 5px; border-radius: 99px; background: currentColor; opacity: .7; }

/* ── Date status banner (replaces raw text error) ────────────── */
.av-date-status {
  display: flex; align-items: flex-start; gap: .6rem;
  padding: .65rem .85rem; border-radius: 10px; margin-top: .5rem;
  font-size: .78rem; line-height: 1.45; font-weight: 500;
  border: 1px solid transparent;
  animation: av-fadein .18s ease;
}
@keyframes av-fadein { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }
.av-date-status.is-ok {
  background: rgba(34,197,94,.08); border-color: rgba(34,197,94,.22); color: #4ade80;
}
.av-date-status.is-err {
  background: rgba(244,63,94,.08); border-color: rgba(244,63,94,.22); color: #F43F5E;
}
.av-date-status.is-warn {
  background: rgba(251,191,36,.08); border-color: rgba(251,191,36,.22); color: #fbbf24;
}
.av-date-status-icon { font-size: 1rem; flex-shrink: 0; line-height: 1; }
.av-date-status-body {}
.av-date-status-title { font-weight: 700; margin-bottom: .1rem; }
.av-date-status-sub   { opacity: .8; font-size: .72rem; }

/* ── Toast container ─────────────────────────────────────────── */
#qb-toast-rack {
  position: fixed; bottom: 1.5rem; right: 1.5rem;
  display: flex; flex-direction: column-reverse; gap: .55rem;
  z-index: 9999; pointer-events: none;
  width: min(340px, calc(100vw - 3rem));
}
.qb-toast {
  display: flex; align-items: flex-start; gap: .75rem;
  padding: .85rem 1rem; border-radius: 12px;
  backdrop-filter: blur(20px) saturate(160%);
  border: 1px solid transparent;
  pointer-events: auto; cursor: pointer;
  font-size: .8rem; font-weight: 500; line-height: 1.45;
  animation: toast-in .28s cubic-bezier(.34,1.56,.64,1) both;
  transition: opacity .22s, transform .22s;
  box-shadow: 0 8px 32px rgba(0,0,0,.45);
}
.qb-toast.is-hiding {
  opacity: 0; transform: translateX(16px);
  pointer-events: none;
}
@keyframes toast-in {
  from { opacity:0; transform: translateX(24px) scale(.95); }
  to   { opacity:1; transform: translateX(0) scale(1); }
}
.qb-toast--error {
  background: rgba(20,5,8,.88); border-color: rgba(244,63,94,.35); color: #fca5a5;
}
.qb-toast--warn {
  background: rgba(15,12,3,.88); border-color: rgba(251,191,36,.3); color: #fde68a;
}
.qb-toast--ok {
  background: rgba(3,13,8,.88); border-color: rgba(34,197,94,.28); color: #86efac;
}
.qb-toast-icon { font-size: 1.1rem; flex-shrink: 0; line-height: 1; }
.qb-toast-body {}
.qb-toast-title { font-weight: 700; font-size: .82rem; margin-bottom: .12rem; }
.qb-toast-msg   { opacity: .85; }
.qb-toast-bar {
  position: absolute; bottom: 0; left: 0; height: 3px; border-radius: 0 0 12px 12px;
  transition: width linear;
}
.qb-toast { position: relative; overflow: hidden; }
.qb-toast--error .qb-toast-bar { background: #F43F5E; }
.qb-toast--warn  .qb-toast-bar { background: #fbbf24; }
.qb-toast--ok    .qb-toast-bar { background: #4ade80; }
</style>

<!-- Toast rack (appended to body, shared) -->
<div id="qb-toast-rack" aria-live="polite" aria-label="Notifications"></div>

<script>
(function () {
  /* ═══════════════════════════════════════════════════════════
     TOAST SYSTEM
  ═══════════════════════════════════════════════════════════ */
  const rack = document.getElementById('qb-toast-rack');

  function showToast({ type = 'error', title, msg, duration = 4500 }) {
    const icons = { error: '🚫', warn: '⚠️', ok: '✅' };
    const toast = document.createElement('div');
    toast.className = `qb-toast qb-toast--${type}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
      <span class="qb-toast-icon">${icons[type]}</span>
      <div class="qb-toast-body">
        <div class="qb-toast-title">${title}</div>
        <div class="qb-toast-msg">${msg}</div>
      </div>
      <div class="qb-toast-bar" id="tbar-${Date.now()}"></div>`;
    rack.appendChild(toast);

    // Progress bar
    const bar = toast.querySelector('.qb-toast-bar');
    bar.style.width = '100%';
    requestAnimationFrame(() => {
      bar.style.transition = `width ${duration}ms linear`;
      bar.style.width = '0%';
    });

    // Auto-dismiss
    const timer = setTimeout(() => dismissToast(toast), duration);
    toast.addEventListener('click', () => { clearTimeout(timer); dismissToast(toast); });
  }

  function dismissToast(toast) {
    toast.classList.add('is-hiding');
    toast.addEventListener('transitionend', () => toast.remove(), { once: true });
  }

  /* ═══════════════════════════════════════════════════════════
     AVAILABILITY DATA (PHP-injected)
  ═══════════════════════════════════════════════════════════ */
  const providerAvailability = <?php
    $avMap = [];
    foreach ($availability as $av) {
        $avMap[$av['day_of_week']] = [
            'start' => substr($av['start_time'], 0, 5),
            'end'   => substr($av['end_time'],   0, 5),
        ];
    }
    echo json_encode($avMap, JSON_UNESCAPED_UNICODE);
  ?>;

  const DAY_NAMES    = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const DAY_ABBR     = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const ORDERED_DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
  const availableDays = new Set(Object.keys(providerAvailability));

  /* ═══════════════════════════════════════════════════════════
     ELEMENTS
  ═══════════════════════════════════════════════════════════ */
  const modal        = document.getElementById('bookingModal');
  const formSvcId    = document.getElementById('formServiceId');
  const modalSvcName = document.getElementById('modalServiceName');
  const modalPrice   = document.getElementById('modalPrice');
  const modalDur     = document.getElementById('modalDuration');
  const modalLoc     = document.getElementById('modalLocation');
  const svcSelect    = document.getElementById('formServiceSelect');
  const dateInput    = document.getElementById('formDate');
  const timeInput    = document.getElementById('formTime');
  const submitBtn    = document.getElementById('submitBtn');

  /* ═══════════════════════════════════════════════════════════
     HELPERS
  ═══════════════════════════════════════════════════════════ */
  function dayNameFromDateStr(s) {
    if (!s) return null;
    const [y, m, d] = s.split('-').map(Number);
    return DAY_NAMES[new Date(y, m - 1, d).getDay()];
  }

  function fmtTime(t) {
    const [h, m] = t.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    return (h % 12 || 12) + ':' + String(m).padStart(2, '0') + ' ' + ampm;
  }

  /* ═══════════════════════════════════════════════════════════
     AVAILABILITY STRIP (day pills inside the modal)
  ═══════════════════════════════════════════════════════════ */
  let stripEl = null;

  function buildStrip() {
    stripEl = document.createElement('div');
    stripEl.className = 'av-strip';
    stripEl.id = 'avStrip';
    ORDERED_DAYS.forEach(day => {
      const abbr = DAY_ABBR[DAY_NAMES.indexOf(day)];
      const open = availableDays.has(day);
      const pill = document.createElement('div');
      pill.className = `av-strip-pill ${open ? 'is-open' : 'is-closed'}`;
      pill.id = `avpill-${day}`;
      pill.title = open
        ? `${day}: ${fmtTime(providerAvailability[day].start)} – ${fmtTime(providerAvailability[day].end)}`
        : `${day}: Unavailable`;
      pill.innerHTML = `<span class="av-strip-pill-day">${abbr}</span><span class="av-strip-pill-dot"></span>`;
      stripEl.appendChild(pill);
    });
    // Insert before the date/time row
    const formRow = document.querySelector('.pv-form-row');
    formRow.parentNode.insertBefore(stripEl, formRow);
  }

  function updateStrip(selectedDay) {
    ORDERED_DAYS.forEach(day => {
      const pill = document.getElementById(`avpill-${day}`);
      if (!pill) return;
      pill.classList.toggle('is-selected', day === selectedDay);
    });
  }

  /* ═══════════════════════════════════════════════════════════
     DATE STATUS BANNER
  ═══════════════════════════════════════════════════════════ */
  let statusEl = null;

  function ensureStatusEl() {
    if (statusEl) return;
    statusEl = document.createElement('div');
    statusEl.id = 'avDateStatus';
    const formRow = document.querySelector('.pv-form-row');
    formRow.parentNode.insertBefore(statusEl, formRow.nextSibling);
  }

  function setDateStatus(type, icon, title, sub) {
    ensureStatusEl();
    statusEl.className = `av-date-status is-${type}`;
    statusEl.innerHTML = `
      <span class="av-date-status-icon">${icon}</span>
      <div class="av-date-status-body">
        <div class="av-date-status-title">${title}</div>
        ${sub ? `<div class="av-date-status-sub">${sub}</div>` : ''}
      </div>`;
  }

  function clearDateStatus() {
    if (statusEl) { statusEl.className = ''; statusEl.innerHTML = ''; }
  }

  /* ═══════════════════════════════════════════════════════════
     TIME CONSTRAINTS
  ═══════════════════════════════════════════════════════════ */
  function updateTimeConstraints(dayName) {
    const avail = dayName ? providerAvailability[dayName] : null;
    if (avail) {
      timeInput.min = avail.start;
      timeInput.max = avail.end;
      timeInput.disabled = false;
      if (timeInput.value && (timeInput.value < avail.start || timeInput.value > avail.end)) {
        timeInput.value = avail.start;
      }
    } else {
      timeInput.min = '';
      timeInput.max = '';
      timeInput.value = '';
      timeInput.disabled = !dateInput.value ? false : true;
    }
  }

  /* ═══════════════════════════════════════════════════════════
     DATE CHANGE HANDLER
  ═══════════════════════════════════════════════════════════ */
  function onDateChange() {
    const dayName = dayNameFromDateStr(dateInput.value);
    if (!dayName) { clearDateStatus(); updateStrip(null); return; }

    updateStrip(dayName);

    if (availableDays.has(dayName)) {
      const av = providerAvailability[dayName];
      setDateStatus(
        'ok', '✅',
        `${dayName} is available!`,
        `Hours: ${fmtTime(av.start)} – ${fmtTime(av.end)}`
      );
      dateInput.setCustomValidity('');
      updateTimeConstraints(dayName);
    } else {
      // Find nearest available date suggestion
      const suggestion = findNextAvailableDate(dateInput.value);
      setDateStatus(
        'err', '🚫',
        `${dayName}s are not available`,
        suggestion
          ? `Try <strong>${suggestion.label}</strong> instead — it's a ${suggestion.day}.`
          : 'Please pick a different date.'
      );
      dateInput.setCustomValidity('Not an available day.');
      updateTimeConstraints(null);
    }
  }

  function findNextAvailableDate(fromDateStr) {
    if (!availableDays.size) return null;
    const [y, m, d] = fromDateStr.split('-').map(Number);
    const base = new Date(y, m - 1, d);
    for (let i = 1; i <= 14; i++) {
      const candidate = new Date(base);
      candidate.setDate(base.getDate() + i);
      const name = DAY_NAMES[candidate.getDay()];
      if (availableDays.has(name)) {
        const label = candidate.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', weekday: 'short' });
        return { label, day: name };
      }
    }
    return null;
  }

  /* ═══════════════════════════════════════════════════════════
     OPEN / CLOSE
  ═══════════════════════════════════════════════════════════ */
  window.openBookingModal = function (svcId, svcName, price, duration, location) {
    formSvcId.value          = svcId;
    modalSvcName.textContent = svcName;
    modalPrice.textContent   = '₱' + price;
    modalDur.textContent     = duration + ' min';
    modalLoc.textContent     = location;

    if (svcSelect) svcSelect.value = svcId;

    dateInput.value = '';
    timeInput.value = '';
    timeInput.disabled = false;
    dateInput.min   = new Date().toISOString().split('T')[0];
    dateInput.setCustomValidity('');
    clearDateStatus();

    // Build strip once
    if (!document.getElementById('avStrip')) buildStrip();
    updateStrip(null);

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
    formSvcId.value          = opt.value;
    modalSvcName.textContent = opt.dataset.name;
    modalPrice.textContent   = '₱' + opt.dataset.price;
    modalDur.textContent     = opt.dataset.duration + ' min';
    modalLoc.textContent     = opt.dataset.location;
  };

  /* ═══════════════════════════════════════════════════════════
     LISTENERS
  ═══════════════════════════════════════════════════════════ */
  dateInput.addEventListener('change', onDateChange);

  document.getElementById('bookingForm').addEventListener('submit', function (e) {
    const dayName = dayNameFromDateStr(dateInput.value);

    // Day not available
    if (dateInput.value && !availableDays.has(dayName)) {
      e.preventDefault();
      const suggestion = findNextAvailableDate(dateInput.value);
      showToast({
        type: 'error',
        title: `${dayName}s are not available`,
        msg: suggestion
          ? `Nearest open date: ${suggestion.label} (${suggestion.day})`
          : 'Please pick an available day.',
      });
      return;
    }

    // Time out of range
    if (dateInput.value && timeInput.value) {
      const avail = providerAvailability[dayName];
      if (avail && (timeInput.value < avail.start || timeInput.value > avail.end)) {
        e.preventDefault();
        showToast({
          type: 'warn',
          title: 'Time is outside working hours',
          msg: `${dayName} hours are ${fmtTime(avail.start)} – ${fmtTime(avail.end)}. Please pick a time within that window.`,
        });
        return;
      }
    }

    submitBtn.disabled    = true;
    submitBtn.textContent = 'Submitting…';
  });

  modal.addEventListener('click', e => { if (e.target === modal) closeBookingModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) closeBookingModal(); });

})();
</script>

</body>
</html>