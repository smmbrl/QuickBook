<?php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$providerId   = $_SESSION['user_id'] ?? 0;
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');

$stmt = $db->prepare("SELECT * FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$providerId]);
$profile   = $stmt->fetch();
$profileId = $profile['id'] ?? 0;

/* ── Pending bookings for nav badge ── */
$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stmt->execute([$profileId]);
$pendingBookings = (int)$stmt->fetchColumn();

/* ── Service stats ── */
$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_services WHERE provider_id = ?");
$stmt->execute([$profileId]);
$totalServices = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM tbl_services WHERE provider_id = ? AND is_active = 1");
$stmt->execute([$profileId]);
$activeServices = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(AVG(price), 0) FROM tbl_services WHERE provider_id = ? AND is_active = 1");
$stmt->execute([$profileId]);
$avgPrice = round((float)$stmt->fetchColumn(), 2);

$stmt = $db->prepare("SELECT COALESCE(MIN(price), 0) FROM tbl_services WHERE provider_id = ? AND is_active = 1");
$stmt->execute([$profileId]);
$minPrice = round((float)$stmt->fetchColumn(), 2);

/* ── Fetch services ── */
$typeFilter = $_GET['type'] ?? 'all';
$search     = trim($_GET['q'] ?? '');

$where  = "provider_id = :pid";
$params = [':pid' => $profileId];
if ($typeFilter !== 'all') {
    $where .= " AND service_type = :type";
    $params[':type'] = $typeFilter;
}
if ($search !== '') {
    $where .= " AND (name LIKE :q OR description LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

$stServices = $db->prepare("SELECT * FROM tbl_services WHERE $where ORDER BY is_active DESC, created_at DESC");
$stServices->execute($params);
$services = $stServices->fetchAll();

/* ── Service types for filter ── */
$stTypes = $db->prepare("SELECT DISTINCT service_type FROM tbl_services WHERE provider_id = ? ORDER BY service_type");
$stTypes->execute([$profileId]);
$serviceTypes = $stTypes->fetchAll(PDO::FETCH_COLUMN);

/* ── Flash ── */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$initials = strtoupper(substr($providerName, 0, 2));

/* Category image & accent maps — 9 fixed categories */
$SERVICE_TYPES = [
    'Barber', 'Hair Stylist', 'Nail Tech', 'Massage',
    'Skincare', 'Fitness', 'Home Cleaning', 'Pet Groomer', 'Event Stylist'
];
$accentMap = [
    'Barber'        => 'blue',
    'Hair Stylist'  => 'indigo',
    'Nail Tech'     => 'red',
    'Massage'       => 'green',
    'Skincare'      => 'indigo',
    'Fitness'       => 'amber',
    'Home Cleaning' => 'green',
    'Pet Groomer'   => 'amber',
    'Event Stylist' => 'red',
];
$imageMap = [
    'Barber'        => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=80&h=80&fit=crop&q=70',
    'Hair Stylist'  => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=80&h=80&fit=crop&q=70',
    'Nail Tech'     => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=80&h=80&fit=crop&q=70',
    'Massage'       => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=80&h=80&fit=crop&q=70',
    'Skincare'      => 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=80&h=80&fit=crop&q=70',
    'Fitness'       => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=80&h=80&fit=crop&q=70',
    'Home Cleaning' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=80&h=80&fit=crop&q=70',
    'Pet Groomer'   => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=80&h=80&fit=crop&q=70',
    'Event Stylist' => 'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=80&h=80&fit=crop&q=70',
];
function serviceAccent($type, $map) { return $map[$type] ?? 'gold'; }
function serviceImage($type, $map)  { return $map[$type] ?? 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=80&h=80&fit=crop&q=70'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — My Services</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_services.css">
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════════
     NAV
══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>provider/dashboard" class="pv-logo">
      Quick<em>Book</em><span class="pv-logo-badge">Provider</span>
    </a>
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/bookings"     class="pv-nav-link">
        Bookings
        <?php if ($pendingBookings): ?><sup class="pv-sup"><?= $pendingBookings ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"     class="pv-nav-link is-active">Services</a>
      <a href="<?= BASE_URL ?>provider/availability" class="pv-nav-link">Availability</a>
      <a href="<?= BASE_URL ?>provider/profile"      class="pv-nav-link">Profile</a>
    </div>
    <div class="pv-nav-end">
      <div class="pv-nav-user">
        <div class="pv-nav-av" aria-hidden="true"><?= $initials ?></div>
        <span><?= $providerName ?></span>
      </div>
      <a href="<?= BASE_URL ?>auth/logout" class="pv-nav-logout">Sign out</a>
    </div>
  </div>
</nav>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<header class="pv-hero" role="banner">
  <div class="pv-hero-overlay" aria-hidden="true"></div>
  <div class="pv-hero-inner">
    <div>
      <p class="pv-hero-eyebrow">
        <span class="pv-dot-pulse" aria-hidden="true"></span>
        Services Management
      </p>
      <h1 class="pv-hero-title">Your <em>Service</em> Catalogue</h1>
      <p class="pv-hero-sub">Manage everything you offer — set pricing, toggle availability, and keep your listings sharp.</p>
    </div>
    <!-- Add Service button removed from hero; only one button lives in the toolbar -->
  </div>

  <!-- Stat strip -->
  <div class="pv-hero-stats">
    <div class="pv-hs-item">
      <span class="pv-hs-val"><?= $totalServices ?></span>
      <span class="pv-hs-label">Total Services</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-green"><?= $activeServices ?></span>
      <span class="pv-hs-label">Active</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-red"><?= $totalServices - $activeServices ?></span>
      <span class="pv-hs-label">Inactive</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-gold">₱<?= number_format($avgPrice, 2) ?></span>
      <span class="pv-hs-label">Avg. Price</span>
    </div>
    <div class="pv-hs-div" aria-hidden="true"></div>
    <div class="pv-hs-item">
      <span class="pv-hs-val pv-hs-blue">₱<?= number_format($minPrice, 2) ?></span>
      <span class="pv-hs-label">Starting From</span>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     PAGE
══════════════════════════════════════ -->
<main class="sv-page" role="main">

  <?php if ($flash): ?>
    <div class="pv-flash pv-flash--<?= $flash['type'] ?>">
      <?= $flash['type'] === 'success' ? '✓' : '✕' ?>
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- TOOLBAR -->
  <div class="sv-toolbar" role="toolbar">
    <div class="sv-toolbar-left">
      <form method="GET" action="" style="display:contents">
        <div class="sv-search-wrap">
          <svg class="sv-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            type="search" name="q" value="<?= htmlspecialchars($search) ?>"
            class="sv-search" placeholder="Search services…" autocomplete="off"
            oninput="this.form.submit()"
          >
        </div>
        <select name="type" class="sv-filter-select" onchange="this.form.submit()">
          <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
          <?php foreach ($serviceTypes as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= $typeFilter === $t ? 'selected' : '' ?>>
              <?= htmlspecialchars($t) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <div style="display:flex;align-items:center;gap:.75rem">
      <!-- View toggle -->
      <div class="sv-view-toggle" role="group" aria-label="View mode">
        <button class="sv-view-btn is-active" id="btn-grid" onclick="setView('grid')" title="Grid view">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        </button>
        <button class="sv-view-btn" id="btn-list" onclick="setView('list')" title="List view">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </button>
      </div>
      <!-- Single Add Service button -->
      <button class="sv-add-btn" onclick="openAddModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Service
      </button>
    </div>
  </div>

  <!-- GRID VIEW -->
  <div class="sv-grid" id="view-grid">
    <?php if (empty($services)): ?>
      <div class="sv-empty">
        <div class="sv-empty-icon">
          <img src="https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=60&h=60&fit=crop" alt="" style="width:36px;height:36px;border-radius:6px;object-fit:cover;opacity:.6">
        </div>
        <h3>No services yet</h3>
        <p>Add your first service to start accepting bookings from customers.</p>
      </div>
    <?php else: ?>
      <?php foreach ($services as $i => $svc): ?>
        <?php
          $accent = serviceAccent($svc['service_type'] ?? 'Home Cleaning', $accentMap);
          $imgSrc = serviceImage($svc['service_type'] ?? 'Home Cleaning', $imageMap);
          $active = (bool)($svc['is_active'] ?? true);
        ?>
        <article class="sv-card <?= $active ? '' : 'is-inactive' ?>" style="animation-delay:<?= $i * 0.04 ?>s">
          <div class="sv-card-accent accent-<?= $accent ?>"></div>
          <div class="sv-card-body">
            <div class="sv-card-header">
              <div class="sv-card-icon"><img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($svc['service_type'] ?? '') ?>" class="sv-card-img"></div>
              <div class="sv-card-title-wrap">
                <div class="sv-card-name"><?= htmlspecialchars($svc['name']) ?></div>
                <div class="sv-card-type"><?= htmlspecialchars($svc['service_type'] ?? '—') ?></div>
              </div>
              <div class="sv-card-actions">
                <!-- Active toggle -->
                <form method="POST" action="<?= BASE_URL ?>provider/service/toggle/<?= $svc['id'] ?>" style="display:inline">
                  <label class="sv-toggle-label" title="<?= $active ? 'Deactivate' : 'Activate' ?>">
                    <input
                      class="sv-toggle-input"
                      type="checkbox"
                      <?= $active ? 'checked' : '' ?>
                      onchange="this.closest('form').submit()"
                    >
                    <div class="sv-toggle-track">
                      <div class="sv-toggle-thumb"></div>
                    </div>
                  </label>
                </form>
                <!-- Edit -->
                <button
                  class="sv-icon-btn is-edit"
                  onclick='openEditModal(<?= json_encode($svc) ?>)'
                  title="Edit service"
                  aria-label="Edit <?= htmlspecialchars($svc['name']) ?>"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <!-- Delete -->
                <button
                  class="sv-icon-btn is-delete"
                  onclick="openDeleteModal(<?= $svc['id'] ?>, '<?= htmlspecialchars(addslashes($svc['name'])) ?>')"
                  title="Delete service"
                  aria-label="Delete <?= htmlspecialchars($svc['name']) ?>"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
              </div>
            </div>

            <p class="sv-card-desc"><?= htmlspecialchars($svc['description'] ?? 'No description provided.') ?></p>

            <div class="sv-card-meta">
              <span class="sv-meta-chip is-price">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                ₱<?= number_format((float)($svc['price'] ?? 0), 2) ?>
              </span>
              <?php if (!empty($svc['duration_minutes'])): ?>
                <?php
                  $dm = (int)$svc['duration_minutes'];
                  $dLabel = ($dm >= 60 && $dm % 60 === 0)
                    ? ($dm / 60) . ' hr'
                    : $dm . ' min';
                ?>
                <span class="sv-meta-chip">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?= $dLabel ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($svc['location_type'])): ?>
                <span class="sv-meta-chip">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <?= htmlspecialchars($svc['location_type']) ?>
                </span>
              <?php endif; ?>
              <span class="sv-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- LIST VIEW TABLE -->
  <div class="sv-table-wrap" id="view-list" style="display:none">
    <table class="sv-table">
      <thead>
        <tr>
          <th>Service</th>
          <th>Type</th>
          <th>Price</th>
          <th>Duration</th>
          <th>Location</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($services)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--faint);padding:3rem;">No services found.</td></tr>
        <?php else: ?>
          <?php foreach ($services as $svc): ?>
            <?php
              $imgSrc = serviceImage($svc['service_type'] ?? 'Home Cleaning', $imageMap);
              $active = (bool)($svc['is_active'] ?? true);
              $dm     = (int)($svc['duration_minutes'] ?? 0);
              $dLabel = $dm ? (($dm >= 60 && $dm % 60 === 0) ? ($dm/60).' hr' : $dm.' min') : '—';
            ?>
            <tr>
              <td>
                <div class="sv-table-name">
                  <div class="sv-table-icon"><img src="<?= $imgSrc ?>" alt="" class="sv-card-img"></div>
                  <?= htmlspecialchars($svc['name']) ?>
                </div>
              </td>
              <td><?= htmlspecialchars($svc['service_type'] ?? '—') ?></td>
              <td style="font-family:var(--font-m);color:var(--gold-bright)">₱<?= number_format((float)($svc['price'] ?? 0), 2) ?></td>
              <td><?= $dLabel ?></td>
              <td><?= htmlspecialchars($svc['location_type'] ?? '—') ?></td>
              <td><span class="sv-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span></td>
              <td>
                <div class="sv-table-actions">
                  <button class="sv-icon-btn is-edit" onclick='openEditModal(<?= json_encode($svc) ?>)' title="Edit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  <button class="sv-icon-btn is-delete" onclick="openDeleteModal(<?= $svc['id'] ?>, '<?= htmlspecialchars(addslashes($svc['name'])) ?>')" title="Delete">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>

<!-- ══════════════════════════════════════
     ADD / EDIT SERVICE MODAL
══════════════════════════════════════ -->
<div class="sv-modal-backdrop" id="serviceModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="sv-modal">

    <!-- Header -->
    <div class="sv-modal-header">
      <div class="sv-modal-title-wrap">
        <div class="sv-modal-icon-badge" id="modalBadge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div>
          <h2 class="sv-modal-title" id="modalTitle">Add New Service</h2>
          <p class="sv-modal-subtitle" id="modalSubtitle">Fill in the details below to create a listing</p>
        </div>
      </div>
      <button class="sv-modal-close" onclick="closeModal('serviceModal')" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <form id="serviceForm" method="POST" action="<?= BASE_URL ?>provider/services/store">
      <input type="hidden" name="service_id" id="field_service_id" value="">

      <div class="sv-modal-body">

        <!-- Section 01: Basic Info -->
        <div class="sv-section-label">
          <span class="sv-section-num">01</span>
          <span>Basic Information</span>
        </div>

        <div class="sv-form-group" style="margin-bottom:1rem">
          <label class="sv-label" for="field_name">Service Name <span>*</span></label>
          <input type="text" class="sv-input" id="field_name" name="name"
            placeholder="e.g. Deep House Cleaning" required maxlength="120" autocomplete="off">
        </div>

        <div class="sv-form-row">
          <div class="sv-form-group">
            <label class="sv-label" for="field_type">Service Type <span>*</span></label>
            <div class="sv-select-wrap">
              <svg class="sv-select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h8M4 18h5"/></svg>
              <select class="sv-select" id="field_type" name="service_type" required>
                <option value="">Select type…</option>
                <?php foreach ($SERVICE_TYPES as $t): ?>
                  <option value="<?= $t ?>"><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="sv-form-group">
            <label class="sv-label" for="field_location">Location Type</label>
            <div class="sv-select-wrap">
              <svg class="sv-select-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <select class="sv-select" id="field_location" name="location_type">
                <option value="On-site">On-site (at customer)</option>
                <option value="Remote">Remote / Online</option>
                <option value="In-shop">In-shop</option>
                <option value="Flexible">Flexible</option>
              </select>
            </div>
          </div>
        </div>

        <div class="sv-modal-divider"></div>

        <!-- Section 02: Pricing & Duration -->
        <div class="sv-section-label">
          <span class="sv-section-num">02</span>
          <span>Pricing & Duration</span>
        </div>

        <div class="sv-form-row">
          <!-- Price -->
          <div class="sv-form-group">
            <label class="sv-label" for="field_price">Price <span>*</span></label>
            <div class="sv-input-addon-wrap">
              <div class="sv-input-addon">₱</div>
              <input type="number" class="sv-input sv-input-with-addon"
                id="field_price" name="price"
                placeholder="0.00" min="0" step="0.01" required>
            </div>
          </div>
          <!-- Duration with hr/min unit selector -->
          <div class="sv-form-group">
            <label class="sv-label" for="field_duration">Duration</label>
            <div class="sv-input-addon-wrap">
              <input type="number" class="sv-input sv-input-with-addon-right"
                id="field_duration" name="duration_minutes"
                placeholder="60" min="1" max="999">
              <select class="sv-duration-unit" id="field_duration_unit" name="duration_unit" title="Unit">
                <option value="min">min</option>
                <option value="hr">hr</option>
              </select>
            </div>
          </div>
        </div>

        <div class="sv-modal-divider"></div>

        <!-- Section 03: Description -->
        <div class="sv-section-label">
          <span class="sv-section-num">03</span>
          <span>Description <span style="color:var(--faint);font-weight:400;letter-spacing:0">(optional)</span></span>
        </div>

        <div class="sv-form-group">
          <textarea class="sv-textarea" id="field_desc" name="description"
            placeholder="Describe what customers can expect — tools used, areas covered, special techniques, etc."
            maxlength="500" rows="3"></textarea>
          <div class="sv-char-hint">
            <span id="charCount">0</span> / 500
          </div>
        </div>

      </div><!-- /.sv-modal-body -->

      <div class="sv-modal-footer">
        <button type="button" class="sv-btn-ghost" onclick="closeModal('serviceModal')">
          Cancel
        </button>
        <button type="submit" class="sv-btn-primary" id="modalSubmitBtn">
          Save Service
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     DELETE CONFIRM MODAL
══════════════════════════════════════ -->
<div class="sv-modal-backdrop" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
  <div class="sv-confirm-modal">
    <div class="sv-confirm-icon">🗑️</div>
    <div class="sv-confirm-title" id="deleteTitle">Delete Service</div>
    <p class="sv-confirm-msg" id="deleteMsg">Are you sure you want to delete this service? This cannot be undone.</p>
    <div class="sv-confirm-btns">
      <button class="sv-btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
      <form id="deleteForm" method="POST" style="display:inline">
        <input type="hidden" name="_method" value="DELETE">
        <button type="submit" class="sv-btn-danger">Delete</button>
      </form>
    </div>
  </div>
</div>

<script>
/* ── View Toggle ── */
function setView(mode) {
  const isGrid = mode === 'grid';
  document.getElementById('view-grid').style.display = isGrid ? '' : 'none';
  document.getElementById('view-list').style.display = isGrid ? 'none' : '';
  document.getElementById('btn-grid').classList.toggle('is-active', isGrid);
  document.getElementById('btn-list').classList.toggle('is-active', !isGrid);
  localStorage.setItem('sv-view', mode);
}
(function() { const v = localStorage.getItem('sv-view'); if (v) setView(v); })();

/* ── Modal helpers ── */
function openModal(id) {
  document.getElementById(id).classList.add('is-open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('is-open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.sv-modal-backdrop').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) closeModal(el.id); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.sv-modal-backdrop.is-open').forEach(el => closeModal(el.id));
});

/* ── Add Modal ── */
function openAddModal() {
  document.getElementById('modalTitle').textContent    = 'Add New Service';
  document.getElementById('modalSubtitle').textContent = 'Fill in the details below to create a listing';
  document.getElementById('modalSubmitBtn').textContent = 'Save Service';
  document.getElementById('serviceForm').action = '<?= BASE_URL ?>provider/services/store';
  document.getElementById('field_service_id').value = '';
  document.getElementById('serviceForm').reset();
  document.getElementById('field_duration_unit').value = 'min';
  updateCharCount();
  openModal('serviceModal');
}

/* ── Edit Modal ── */
function openEditModal(svc) {
  document.getElementById('modalTitle').textContent    = 'Edit Service';
  document.getElementById('modalSubtitle').textContent = 'Update the details for this listing';
  document.getElementById('modalSubmitBtn').textContent = 'Update Service';
  document.getElementById('serviceForm').action = '<?= BASE_URL ?>provider/service/update/' + svc.id;
  document.getElementById('field_service_id').value = svc.id;
  document.getElementById('field_name').value        = svc.name        || '';
  document.getElementById('field_type').value        = svc.service_type || '';
  document.getElementById('field_location').value    = svc.location_type || 'On-site';
  document.getElementById('field_price').value       = svc.price        || '';
  document.getElementById('field_desc').value        = svc.description  || '';

  /* Smart unit detection: show hrs if cleanly divisible */
  const mins = parseInt(svc.duration_minutes) || 0;
  if (mins >= 60 && mins % 60 === 0) {
    document.getElementById('field_duration').value      = mins / 60;
    document.getElementById('field_duration_unit').value = 'hr';
  } else {
    document.getElementById('field_duration').value      = mins || '';
    document.getElementById('field_duration_unit').value = 'min';
  }

  updateCharCount();
  openModal('serviceModal');
}

/* ── Char counter ── */
function updateCharCount() {
  const ta = document.getElementById('field_desc');
  const el = document.getElementById('charCount');
  if (ta && el) el.textContent = ta.value.length;
}
document.getElementById('field_desc')?.addEventListener('input', updateCharCount);

/* ── Delete Modal ── */
function openDeleteModal(id, name) {
  document.getElementById('deleteMsg').textContent =
    `Are you sure you want to delete "${name}"? This action cannot be undone and any related data will be lost.`;
  document.getElementById('deleteForm').action = '<?= BASE_URL ?>provider/service/delete/' + id;
  openModal('deleteModal');
}

/* ── Custom number spinners ── */
document.querySelectorAll('.sv-input[type="number"]').forEach(input => {
  const wrap = document.createElement('div');
  wrap.className = 'sv-spin-wrap';
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);

  const btns = document.createElement('div');
  btns.className = 'sv-spin-btns';
  btns.innerHTML = `
    <button type="button" class="sv-spin-btn" data-dir="up">▲</button>
    <button type="button" class="sv-spin-btn" data-dir="down">▼</button>
  `;
  wrap.appendChild(btns);

  btns.addEventListener('mousedown', e => {
    const btn = e.target.closest('.sv-spin-btn');
    if (!btn) return;
    const step = parseFloat(input.step) || 1;
    const min  = parseFloat(input.min);
    const max  = parseFloat(input.max);
    const dir  = btn.dataset.dir === 'up' ? 1 : -1;
    const apply = () => {
      let val = (parseFloat(input.value) || 0) + dir * step;
      if (!isNaN(min)) val = Math.max(min, val);
      if (!isNaN(max)) val = Math.min(max, val);
      input.value = parseFloat(val.toFixed(10));
      input.dispatchEvent(new Event('input'));
    };
    apply();
    const hold = setInterval(apply, 80);
    document.addEventListener('mouseup', () => clearInterval(hold), { once: true });
    e.preventDefault();
  });
});
</script>

</body>
</html>