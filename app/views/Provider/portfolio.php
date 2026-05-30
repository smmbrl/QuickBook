<?php
// app/views/Provider/portfolio.php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$userId       = (int)($_SESSION['user_id'] ?? 0);
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');
$email        = htmlspecialchars($_SESSION['user_email'] ?? '');
$firstName    = htmlspecialchars(explode(' ', $providerName)[0]);
$initials     = strtoupper(substr($providerName, 0, 2));

// ── Fetch provider profile ────────────────────────────────────────────────────
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

$profileId    = (int)$profile['id'];
$fullName     = htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')));
$categoryName = htmlspecialchars($profile['category_name'] ?? 'Service Provider');
$photoUrl     = !empty($profile['profile_photo']) ? $profile['profile_photo'] : null;

// ── Pending count (nav badge) ─────────────────────────────────────────────────
$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stPending->execute([$profileId]);
$pendingCount = (int)$stPending->fetchColumn();

// ── Active services for upload/edit modal ─────────────────────────────────────
$stServices = $db->prepare("SELECT id, name FROM tbl_services WHERE provider_id = ? AND is_active = 1 ORDER BY name");
$stServices->execute([$profileId]);
$services = $stServices->fetchAll();

// ── Portfolio items ───────────────────────────────────────────────────────────
$portfolioItems = [];
try {
    $stPort = $db->prepare("
        SELECT p.*, s.name AS service_name
        FROM tbl_portfolio p
        LEFT JOIN tbl_services s ON s.id = p.service_id
        WHERE p.provider_id = ?
        ORDER BY p.is_featured DESC, p.created_at DESC
    ");
    $stPort->execute([$profileId]);
    $portfolioItems = $stPort->fetchAll();
} catch (\Exception $e) { $portfolioItems = []; }

// ── Verification status ───────────────────────────────────────────────────────
$isVerified = !empty($profile['is_verified']) && (int)$profile['is_verified'] === 1;

// No demo data — show real data only, or empty state for unverified/new providers
$items        = $portfolioItems;
$featured     = array_filter($items, fn($i) => !empty($i['is_featured']));
$galleryItems = array_filter($items, fn($i) => empty($i['is_featured']));
$analytics    = [
    'uploads'                => count($portfolioItems),
    'views'                  => array_sum(array_column($portfolioItems, 'views')),
    'likes'                  => array_sum(array_column($portfolioItems, 'likes')),
    'bookings_from_portfolio' => 0,
];

// ── Flash ─────────────────────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

// ── Build items JSON for JS ───────────────────────────────────────────────────
$itemsJson = json_encode(array_values($items));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — My Portfolio</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_dashboard.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_portfolio.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script>(function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();</script>

  <style>
    /* ── Feature-toggle star animation on card ── */
    .pf-card-icon-btn.is-featured { color: var(--gold-dim) !important; background: var(--gold-lt) !important; border-color: var(--gold-border-md) !important; }
    .pf-card-icon-btn .fa-star { transition: transform .25s cubic-bezier(.34,1.56,.64,1); }
    .pf-card-icon-btn.is-featured .fa-star { transform: scale(1.25) rotate(-8deg); }

    /* ── Edit modal image preview ── */
/* Edit modal image panel */
.pf-edit-img-wrap {
  position: relative; flex: 1; border-radius: var(--r-lg); overflow: hidden;
  background: var(--surface-md); border: 1.5px solid var(--border);
  min-height: 240px; display: flex; align-items: center; justify-content: center;
}
#editCurrentImg {
  width: 100%; height: 100%; object-fit: cover;
  display: block; border-radius: var(--r-lg);
}
.pf-edit-img-overlay {
  position: absolute; inset: 0; display: flex;
  align-items: flex-end; justify-content: center;
  padding-bottom: .85rem;
  background: linear-gradient(0deg, rgba(0,0,0,.55) 0%, transparent 55%);
  opacity: 0; transition: opacity .22s;
}
.pf-edit-img-wrap:hover .pf-edit-img-overlay { opacity: 1; }
[data-theme="dark"] .pf-edit-img-wrap { border-color: rgba(201,168,76,.20); background: rgba(18,23,35,.60); }

    #editImgPreview { width:72px;height:72px;object-fit:cover;border-radius:var(--r-sm);border:1.5px solid var(--gold-border-md);display:none; }

    /* ── Confirm delete highlight ── */
    #deleteItemTitle { font-weight:700; color:var(--text-primary); }

    /* ── Toast notifications ── */
    #toastContainer {
      position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%);
      z-index: 9999; display: flex; flex-direction: column; align-items: center;
      gap: .5rem; pointer-events: none;
    }
    .qb-toast {
      display: inline-flex; align-items: center; gap: .55rem;
      padding: .6rem 1.25rem; border-radius: 99px;
      font-size: .85rem; font-weight: 600; white-space: nowrap;
      pointer-events: auto;
      box-shadow: 0 4px 20px rgba(0,0,0,.18);
      animation: toastIn .28s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes toastIn  { from { transform: translateY(12px) scale(.92); opacity: 0; } to { transform: none; opacity: 1; } }
    @keyframes toastOut { to   { transform: translateY(8px);  opacity: 0; } }
    .qb-toast.is-leaving { animation: toastOut .22s ease forwards; }
    .qb-toast--success { background: #166534; color: #fff; }
    .qb-toast--error   { background: #991B1B; color: #fff; }
    .qb-toast--info    { background: #1a1000; color: #E8C96A; border: 1px solid rgba(201,168,76,.4); }
    [data-theme="dark"] .qb-toast--info { background: #0D1117; border-color: rgba(201,168,76,.35); }


    /* Spinner for async buttons */
    .btn-loading { opacity:.7; pointer-events:none; }
    .btn-loading::after { content:''; display:inline-block; width:12px;height:12px; margin-left:.5rem;
      border:2px solid currentColor;border-top-color:transparent;border-radius:50%;
      animation:spin .6s linear infinite;vertical-align:middle; }
    @keyframes spin { to { transform:rotate(360deg); } }

    /* ── Account verification banner ── */
    .pf-verify-banner {
      display: flex; align-items: center; gap: .75rem;
      padding: .7rem 2rem;
      background: linear-gradient(135deg, rgba(201,168,76,.12) 0%, rgba(201,168,76,.05) 100%);
      border-bottom: 1px solid rgba(201,168,76,.28);
      font-size: .84rem; color: #7a5b14;
    }
    .pf-verify-banner__icon { color: #C9A84C; font-size: .95rem; flex-shrink: 0; }
    .pf-verify-banner__title { color: #C9A84C; font-weight: 700; }
    [data-theme="dark"] .pf-verify-banner { color: #c9a84c; background: linear-gradient(135deg, rgba(201,168,76,.10) 0%, rgba(0,0,0,.15) 100%); }
  </style>
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<!-- ── Toast Container ── -->
<div id="toastContainer" aria-live="polite"></div>

<!-- ═══════════════════════════════════════
     NAV
═══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo"
           style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Provider</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/appointments" class="pv-nav-link">
        Appointments<?php if ($pendingCount): ?><sup class="pv-sup"><?= $pendingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/portfolio"    class="pv-nav-link is-active">Portfolio</a>
      <a href="<?= BASE_URL ?>provider/schedule"     class="pv-nav-link">Schedule</a>
       <a href="<?= BASE_URL ?>provider/reviews"     class="pv-nav-link">Reviews</a>
    </div>

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      </button>

      <!-- Profile dropdown trigger -->
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0"
           aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av">
          <?php if ($photoUrl): ?>
            <img src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= $fullName ?>">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div class="pv-nav-user">
          <div class="pv-nav-user-name"><?= $firstName ?></div>
        </div>
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

      <!-- Profile dropdown panel -->
      <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="pv-pd-header">
          <div class="pv-pd-avatar">
            <?php if ($photoUrl): ?>
              <img src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= $fullName ?>">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $fullName ?></div>
            <div class="pv-pd-email"><?= $email ?></div>
            <span class="pv-pd-role"><?= $categoryName ?></span>
          </div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>provider/profile" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-store"></i></span>
          <span>Business Profile</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="<?= BASE_URL ?>provider/settings" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-gear"></i></span>
          <span>Settings</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>auth/logout" class="pv-pd-item pv-pd-item--danger" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
          <span>Sign Out</span>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- ═══════════════════════════════════════
     FLASH
═══════════════════════════════════════ -->
<?php if ($flash): ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var type     = '<?= $flash['type'] ?>';
    var raw      = <?= json_encode($flash['msg']) ?>;
    var colonIdx = raw.indexOf(':');
    var prefix   = colonIdx !== -1 ? raw.substring(0, colonIdx).toLowerCase() : '';
    var icon, text;
    if (type === 'success') {
      if      (prefix === 'uploaded') { icon = 'fa-cloud-arrow-up'; text = 'Work uploaded successfully!'; }
      else if (prefix === 'updated')  { icon = 'fa-pen-to-square';  text = 'Work updated successfully!'; }
      else if (prefix === 'deleted')  { icon = 'fa-trash-can';      text = 'Work deleted successfully!'; }
      else if (prefix === 'featured') { icon = 'fa-star';           text = 'Featured list updated!'; }
      else                            { icon = 'fa-circle-check';   text = 'Done!'; }
    } else {
      icon = 'fa-circle-xmark';
      text = colonIdx !== -1 ? raw.substring(colonIdx + 1).trim() : raw;
    }
    window.showToast(icon, text, type);
  });
</script>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     HERO
═══════════════════════════════════════ -->
<header class="pf-hero">
  <div class="pf-hero-bg" aria-hidden="true"></div>
  <div class="pf-hero-overlay" aria-hidden="true"></div>
  <div class="pf-hero-inner">
    <div class="pf-hero-left">
      <p class="pf-hero-eyebrow"><span class="pv-dot-pulse" aria-hidden="true"></span>Portfolio Showcase</p>
      <h1 class="pf-hero-title">Your <em>Creative</em><br>Portfolio</h1>
      <p class="pf-hero-sub">Showcase your best works, attract more customers, and convert viewers into bookings.</p>
    </div>


  </div>
</header>

<?php if (!$isVerified): ?>
<div class="pf-verify-banner" role="alert">
  <i class="fa-solid fa-clock pf-verify-banner__icon"></i>
  <span>
    <strong class="pf-verify-banner__title">Account pending verification.</strong>
    An admin needs to verify your account before you can upload portfolio works.
  </span>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     QUICK ACTIONS
═══════════════════════════════════════ -->
<div class="pf-actions-bar">
  <div class="pf-actions-right">
    <?php if ($isVerified): ?>
    <button class="pf-action-btn pf-action-btn--primary" onclick="openUploadModal()">
      <i class="fa-solid fa-cloud-arrow-up"></i> Upload Work
    </button>
    <button class="pf-action-btn pf-action-btn--feature" onclick="openBulkFeatureModal()">
      <i class="fa-solid fa-star"></i> Manage Featured
    </button>
    <?php else: ?>
    <button class="pf-action-btn pf-action-btn--primary" disabled
            style="opacity:.5;cursor:not-allowed;"
            title="Account verification required to upload portfolio works"
            onclick="showToast('fa-shield-halved','Your account must be verified by an admin before uploading.','error'); return false;">
      <i class="fa-solid fa-cloud-arrow-up"></i> Upload Work
    </button>
    <button class="pf-action-btn pf-action-btn--feature" disabled
            style="opacity:.5;cursor:not-allowed;"
            title="Account verification required"
            onclick="showToast('fa-shield-halved','Your account must be verified by an admin before managing featured works.','error'); return false;">
      <i class="fa-solid fa-star"></i> Manage Featured
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════════════════
     MAIN
═══════════════════════════════════════ -->
<main class="pf-page" role="main">

  <!-- Analytics -->
  <div class="pf-analytics">
    <div class="pf-stat-card">
      <div class="pf-stat-card-bg"></div>
      <div class="pf-stat-icon pf-stat-icon--gold"><i class="fa-solid fa-images"></i></div>
      <div class="pf-stat-body">
        <div class="pf-stat-val"><?= $analytics['uploads'] > 0 ? number_format($analytics['uploads']) : '0' ?></div>
        <div class="pf-stat-label">Total Uploads</div>
        <div class="pf-stat-trend pf-stat-trend--gold"><i class="fa-solid fa-layer-group"></i> Portfolio works</div>
      </div>
    </div>
    <div class="pf-stat-card">
      <div class="pf-stat-card-bg pf-stat-card-bg--blue"></div>
      <div class="pf-stat-icon pf-stat-icon--blue"><i class="fa-solid fa-eye"></i></div>
      <div class="pf-stat-body">
        <div class="pf-stat-val"><?= $analytics['views'] > 0 ? ($analytics['views'] >= 1000 ? number_format($analytics['views']/1000,1).'k' : $analytics['views']) : '0' ?></div>
        <div class="pf-stat-label">Total Views</div>
        <div class="pf-stat-trend pf-stat-trend--blue"><i class="fa-solid fa-chart-line"></i> Profile impressions</div>
      </div>
    </div>
    <div class="pf-stat-card">
      <div class="pf-stat-card-bg pf-stat-card-bg--amber"></div>
      <div class="pf-stat-icon pf-stat-icon--amber"><i class="fa-solid fa-heart"></i></div>
      <div class="pf-stat-body">
        <div class="pf-stat-val"><?= $analytics['likes'] > 0 ? number_format($analytics['likes']) : '0' ?></div>
        <div class="pf-stat-label">Likes &amp; Saves</div>
        <div class="pf-stat-trend pf-stat-trend--amber"><i class="fa-solid fa-fire"></i> Engagement score</div>
      </div>
    </div>
    <div class="pf-stat-card">
      <div class="pf-stat-card-bg pf-stat-card-bg--green"></div>
      <div class="pf-stat-icon pf-stat-icon--green"><i class="fa-solid fa-calendar-check"></i></div>
      <div class="pf-stat-body">
        <div class="pf-stat-val"><?= number_format($analytics['bookings_from_portfolio']) ?></div>
        <div class="pf-stat-label">Bookings from Portfolio</div>
        <?php if ($analytics['bookings_from_portfolio'] > 0): ?>
          <div class="pf-stat-trend pf-stat-trend--green"><i class="fa-solid fa-arrow-trend-up"></i> Portfolio converts</div>
        <?php else: ?>
          <div class="pf-stat-trend pf-stat-trend--green"><i class="fa-solid fa-bullseye"></i> Start converting</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Featured -->
  <?php if (!empty($featured)): ?>
  <div class="pf-section-head">
    <div class="pf-section-title">Featured Works <span class="pf-section-badge">Pinned</span></div>
  </div>
  <div class="pf-featured-strip">
    <?php foreach (array_slice($featured, 0, 3) as $item): ?>
    <div class="pf-featured-card" onclick="openLightbox('<?= htmlspecialchars($item['image_url'] ?? '') ?>')">
      <img src="<?= htmlspecialchars($item['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($item['title']) ?>">
      <div class="pf-featured-overlay"></div>
      <div class="pf-featured-badge"><i class="fa-solid fa-star"></i> <?= htmlspecialchars($item['featured_label'] ?? 'Featured') ?></div>
      <div class="pf-featured-body">
        <div class="pf-featured-service"><?= htmlspecialchars($item['service_name'] ?? '') ?></div>
        <div class="pf-featured-name"><?= htmlspecialchars($item['title']) ?></div>
        <div class="pf-featured-meta">
          <span><i class="fa-solid fa-heart"></i> <?= number_format($item['likes'] ?? 0) ?></span>
          <span><i class="fa-solid fa-eye"></i> <?= ($item['views']??0)>=1000 ? number_format(($item['views']??0)/1000,1).'k' : number_format($item['views']??0) ?></span>
          <?php if (!empty($item['price'])): ?><span><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($item['price']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Gallery -->
  <div class="pf-section-head">
    <div class="pf-section-title">All Works <span class="pf-section-badge" id="galleryCount"><?= count($items) ?> items</span></div>
    <div class="pf-filter-row">
      <button class="pf-filter-btn is-active" data-filter="all">All</button>
      <button class="pf-filter-btn" data-filter="before-after">Before &amp; After</button>
      <button class="pf-filter-btn" data-filter="featured">Featured</button>
    </div>
  </div>

  <?php if (empty($items)): ?>
  <div class="pf-empty">
    <?php if (!$isVerified): ?>
      <div class="pf-empty-icon" style="color:#C9A84C;"><i class="fa-solid fa-shield-halved"></i></div>
      <div class="pf-empty-title">Account Pending Verification</div>
      <p class="pf-empty-text">
        Your account is currently being reviewed by our admin team.<br>
        Once verified, you'll be able to upload your portfolio works and start attracting clients.
      </p>
      <div style="display:inline-flex;align-items:center;gap:.55rem;padding:.55rem 1.1rem;border-radius:99px;background:rgba(201,168,76,.10);border:1px solid rgba(201,168,76,.3);font-size:.82rem;font-weight:600;color:#C9A84C;margin-top:.25rem;">
        <i class="fa-solid fa-clock"></i> Verification in progress
      </div>
    <?php else: ?>
      <div class="pf-empty-icon"><i class="fa-solid fa-images"></i></div>
      <div class="pf-empty-title">No portfolio items yet</div>
      <p class="pf-empty-text">Start building your creative portfolio! Upload your best work to attract more customers.</p>
      <button class="pf-action-btn pf-action-btn--primary" onclick="openUploadModal()">
        <i class="fa-solid fa-plus"></i> Upload Your First Work
      </button>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <div class="pf-gallery" id="portfolioGallery">
    <?php foreach ($items as $item):
      $isBA   = !empty($item['is_before_after']);
      $imgUrl = htmlspecialchars($item['image_url'] ?? '');
      $bUrl   = htmlspecialchars($item['before_url'] ?? $imgUrl);
      $aUrl   = htmlspecialchars($item['after_url']  ?? $imgUrl);
      $isFeat = !empty($item['is_featured']);
    ?>
    <div class="pf-card"
         id="card-<?= $item['id'] ?>"
         data-featured="<?= $isFeat?'1':'0' ?>"
         data-ba="<?= $isBA?'1':'0' ?>">

      <?php if ($isBA): ?>
      <div class="pf-ba-wrap" data-ba-wrap>
        <img src="<?= $bUrl ?>" alt="Before" style="width:100%;display:block;">
        <div class="pf-ba-after" style="width:50%;"><img src="<?= $aUrl ?>" alt="After"></div>
        <div class="pf-ba-slider" data-ba-slider></div>
        <span class="pf-ba-label pf-ba-label--before">Before</span>
        <span class="pf-ba-label pf-ba-label--after">After</span>
      </div>
      <?php else: ?>
      <div class="pf-card-img-wrap">
        <?php if (!empty($item['image_url'])): ?>
          <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy">
        <?php else: ?>
          <div class="pf-card-img-placeholder"><i class="fa-solid fa-image"></i></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Hover action buttons -->
      <div class="pf-card-hover-actions">
        <button
          class="pf-card-icon-btn <?= $isFeat ? 'is-featured' : '' ?>"
          id="feat-btn-<?= $item['id'] ?>"
          title="<?= $isFeat ? 'Unfeature' : 'Feature' ?>"
          onclick="event.stopPropagation(); toggleFeature(<?= $item['id'] ?>, this)">
          <i class="fa-<?= $isFeat ? 'solid' : 'regular' ?> fa-star"></i>
        </button>
        <button
          class="pf-card-icon-btn"
          title="Edit"
          onclick="event.stopPropagation(); openEditModal(<?= $item['id'] ?>)">
          <i class="fa-solid fa-pen"></i>
        </button>
        <button
          class="pf-card-icon-btn pf-card-icon-btn--danger"
          title="Delete"
          onclick="event.stopPropagation(); confirmDelete(<?= $item['id'] ?>, '<?= addslashes(htmlspecialchars($item['title'])) ?>')">
          <i class="fa-solid fa-trash"></i>
        </button>
      </div>

      <div class="pf-card-body">
        <?php if (!empty($item['service_name'])): ?>
        <div class="pf-card-service-tag"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($item['service_name']) ?></div>
        <?php endif; ?>
        <div class="pf-card-title" id="card-title-<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></div>
        <?php if (!empty($item['caption'])): ?>
        <div class="pf-card-caption" id="card-caption-<?= $item['id'] ?>"><?= htmlspecialchars($item['caption']) ?></div>
        <?php endif; ?>
        <div class="pf-card-footer">
          <?php if (!empty($item['price'])): ?>
          <div class="pf-card-price" id="card-price-<?= $item['id'] ?>"><?= htmlspecialchars($item['price']) ?></div>
          <?php endif; ?>
          <div class="pf-card-stats">
            <span class="liked"><i class="fa-solid fa-heart"></i> <?= number_format($item['likes']??0) ?></span>
            <span><i class="fa-solid fa-eye"></i> <?= ($item['views']??0)>=1000?number_format(($item['views']??0)/1000,1).'k':number_format($item['views']??0) ?></span>
          </div>
        </div>
        <div class="pf-card-date">
          <i class="fa-regular fa-calendar"></i>
          <?= date('M j, Y', strtotime($item['created_at']??'now')) ?>
          <?php if ($isFeat): ?>&nbsp;·&nbsp;<i class="fa-solid fa-star" style="color:var(--gold-dim);"></i> <span class="feat-label-<?= $item['id'] ?>">Featured</span><?php endif; ?>
          <?php if ($isBA): ?>&nbsp;·&nbsp;<i class="fa-solid fa-left-right" style="color:#2563EB;"></i> Before &amp; After<?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</main>

<!-- ═══════════════════════════════════════
     UPLOAD MODAL
═══════════════════════════════════════ -->
<div class="pf-modal-overlay" id="uploadModal" hidden role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
  <div class="pf-modal pf-modal--split">

    <div class="pf-modal-steps-header">
      <button class="pf-modal-close" onclick="closeUploadModal()" aria-label="Close">✕</button>
    </div>

    <form method="POST" action="<?= BASE_URL ?>provider/portfolio/upload" enctype="multipart/form-data" id="uploadForm">
      <div class="pf-modal-split-body">

        <!-- LEFT: Drop zone -->
        <div class="pf-split-left">

          <!-- Single drop zone (default) -->
          <div id="singleDropWrap" style="flex:1;display:flex;flex-direction:column;">
            <div class="pf-drop-zone" id="dropZone" onclick="document.getElementById('portfolioFileInput').click()">
              <input type="file" id="portfolioFileInput" name="portfolio_images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none" onchange="previewFiles(this)">
              <div class="pf-drop-preview" id="dropPreview">
                <div class="pf-drop-placeholder">
                  <div class="pf-drop-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                  <p class="pf-drop-main">Drop file here</p>
                  <p class="pf-drop-or">OR</p>
                  <span class="pf-drop-browse-btn">Browse File</span>
                  <p class="pf-drop-hint">JPG, PNG, WebP — max 5 MB each</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Dual drop zones (Before & After) — hidden by default -->
          <div id="baDropWrap" style="display:none;flex:1;flex-direction:column;gap:.75rem;">
            <!-- Before -->
            <div class="pf-ba-drop-zone" id="beforeDropZone" onclick="document.getElementById('beforeFileInput').click()">
              <input type="file" id="beforeFileInput" name="before_image" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="previewBAFile(this,'beforePreview','Before')">
              <div class="pf-drop-preview" id="beforePreview">
                <div class="pf-drop-placeholder">
                  <div class="pf-drop-icon" style="font-size:1.6rem;"><i class="fa-solid fa-clock-rotate-left"></i></div>
                  <p class="pf-drop-main" style="font-size:.88rem;">Before</p>
                  <span class="pf-drop-browse-btn" style="font-size:.72rem;padding:.3rem .9rem;">Browse</span>
                </div>
              </div>
            </div>
            <!-- After -->
            <div class="pf-ba-drop-zone" id="afterDropZone" onclick="document.getElementById('afterFileInput').click()">
              <input type="file" id="afterFileInput" name="after_image" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="previewBAFile(this,'afterPreview','After')">
              <div class="pf-drop-preview" id="afterPreview">
                <div class="pf-drop-placeholder">
                  <div class="pf-drop-icon" style="font-size:1.6rem;"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                  <p class="pf-drop-main" style="font-size:.88rem;">After</p>
                  <span class="pf-drop-browse-btn" style="font-size:.72rem;padding:.3rem .9rem;">Browse</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Before & After toggle -->
          <div class="pf-toggle-row" style="margin-top:1rem;">
            <span class="pf-toggle-label"><i class="fa-solid fa-left-right" style="color:#2563EB;"></i> Before &amp; After Slider</span>
            <label class="pf-toggle">
              <input type="checkbox" name="is_before_after" id="baToggle" onchange="toggleBAFields(this)">
              <span class="pf-toggle-track"></span><span class="pf-toggle-thumb"></span>
            </label>
          </div>
        </div>

        <!-- RIGHT: Form fields -->
        <div class="pf-split-right">
          <h2 class="pf-split-title" id="uploadModalTitle">Work Details</h2>

          <!-- Provider info chips (read-only) -->
          <div class="pf-info-chips">
            <div class="pf-info-chip">
              <i class="fa-solid fa-tag"></i>
              <?= $categoryName ?>
            </div>
            <?php if (!empty($profile['business_name'])): ?>
            <div class="pf-info-chip">
              <i class="fa-solid fa-store"></i>
              <?= htmlspecialchars($profile['business_name']) ?>
            </div>
            <?php endif; ?>
          </div>

          <div class="pf-form-group">
            <label class="pf-form-label" for="portfolioTitle">Work Title <span style="color:#DC2626;">*</span></label>
            <input type="text" name="title" id="portfolioTitle" class="pf-form-control" placeholder="e.g. Glossy Nude Nails" required>
          </div>

          <div class="pf-form-group">
            <label class="pf-form-label" for="portfolioCaption">Short Caption</label>
            <textarea name="caption" id="portfolioCaption" class="pf-form-control" rows="3" placeholder="Describe this work briefly..."></textarea>
          </div>

          <div class="pf-form-group">
            <label class="pf-form-label" for="portfolioPrice">Price (optional)</label>
            <input type="text" name="price" id="portfolioPrice" class="pf-form-control" placeholder="e.g. ₱900">
          </div>

          <div class="pf-toggle-row">
            <span class="pf-toggle-label"><i class="fa-solid fa-star" style="color:var(--gold-dim);"></i> Feature this work</span>
            <label class="pf-toggle">
              <input type="checkbox" name="is_featured">
              <span class="pf-toggle-track"></span><span class="pf-toggle-thumb"></span>
            </label>
          </div>

          <div class="pf-split-footer">
            <button type="button" class="pf-btn pf-btn--ghost" onclick="closeUploadModal()">Cancel</button>
            <button type="button" class="pf-btn pf-btn--primary" id="uploadSubmitBtn" onclick="submitUpload()">
              <i class="fa-solid fa-cloud-arrow-up"></i> Upload Work
            </button>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════
     EDIT MODAL
═══════════════════════════════════════ -->
<div class="pf-modal-overlay" id="editModal" hidden role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
  <div class="pf-modal pf-modal--split">

    <div class="pf-modal-steps-header">
      <button class="pf-modal-close" onclick="closeEditModal()" aria-label="Close">✕</button>
    </div>

    <form method="POST" action="" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="portfolio_id" id="editItemId">

      <div class="pf-modal-split-body">

        <!-- LEFT: Image preview / replace -->
        <div class="pf-split-left">
          <p class="pf-form-label" style="margin-bottom:.75rem;">Current Image</p>
          <div class="pf-edit-img-wrap" id="editImgWrap">
            <img id="editCurrentImg" src="" alt="Current work">
            <div class="pf-edit-img-overlay">
              <button type="button" class="pf-drop-full-change"
                      onclick="document.getElementById('editFileInput').click()">
                <i class="fa-solid fa-arrows-rotate"></i> Replace Image
              </button>
            </div>
          </div>
          <input type="file" id="editFileInput" name="portfolio_image"
                 accept="image/jpeg,image/png,image/webp"
                 style="display:none" onchange="previewEditFile(this)">
          <p style="font-size:.72rem;color:var(--text-faint);margin-top:.6rem;text-align:center;">
            Leave empty to keep current image
          </p>
        </div>

        <!-- RIGHT: Form fields -->
        <div class="pf-split-right">
          <h2 class="pf-split-title" id="editModalTitle">
            <i class="fa-solid fa-pen-to-square" style="font-size:.9rem;color:var(--gold-dim);"></i>
            Edit Work
          </h2>

          <!-- Provider info chips -->
          <div class="pf-info-chips">
            <div class="pf-info-chip"><i class="fa-solid fa-tag"></i> <?= $categoryName ?></div>
            <?php if (!empty($profile['business_name'])): ?>
            <div class="pf-info-chip"><i class="fa-solid fa-store"></i> <?= htmlspecialchars($profile['business_name']) ?></div>
            <?php endif; ?>
          </div>

          <div class="pf-form-group">
            <label class="pf-form-label" for="editTitle">Work Title <span style="color:#DC2626;">*</span></label>
            <input type="text" name="title" id="editTitle" class="pf-form-control"
                   placeholder="e.g. Glossy Nude Nails" required>
          </div>

          <div class="pf-form-group">
            <label class="pf-form-label" for="editCaption">Short Caption</label>
            <textarea name="caption" id="editCaption" class="pf-form-control"
                      rows="3" placeholder="Describe this work briefly..."></textarea>
          </div>

          <div class="pf-form-group">
            <label class="pf-form-label" for="editPrice">Price (optional)</label>
            <input type="text" name="price" id="editPrice" class="pf-form-control" placeholder="e.g. ₱900">
          </div>

          <div class="pf-toggle-row">
            <span class="pf-toggle-label">
              <i class="fa-solid fa-star" style="color:var(--gold-dim);"></i> Feature this work
            </span>
            <label class="pf-toggle">
              <input type="checkbox" name="is_featured" id="editFeatured">
              <span class="pf-toggle-track"></span><span class="pf-toggle-thumb"></span>
            </label>
          </div>

          <div class="pf-split-footer">
            <button type="button" class="pf-btn pf-btn--ghost" onclick="closeEditModal()">Cancel</button>
            <button type="button" class="pf-btn pf-btn--primary" id="editSubmitBtn" onclick="submitEdit()">
              <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════
     DELETE CONFIRM MODAL
═══════════════════════════════════════ -->
<div class="pf-modal-overlay" id="deleteModal" hidden role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
  <div class="pf-modal" style="max-width:420px;">
    <div class="pf-modal-head">
      <h2 class="pf-modal-title" id="deleteModalTitle" style="color:#DC2626;"><i class="fa-solid fa-triangle-exclamation"></i> Delete Work</h2>
      <button class="pf-modal-close" onclick="closeDeleteModal()" aria-label="Close">✕</button>
    </div>
    <div class="pf-modal-body">
      <p style="color:var(--text-muted);font-size:.92rem;line-height:1.6;">
        Are you sure you want to delete <span id="deleteItemTitle" style="font-weight:700;color:var(--text-primary);">"this item"</span>?
        <br><span style="font-size:.82rem;opacity:.75;">This action cannot be undone.</span>
      </p>
    </div>
    <div class="pf-modal-footer">
      <button class="pf-btn pf-btn--ghost" onclick="closeDeleteModal()">Cancel</button>
      <form id="deleteForm" method="POST" style="display:inline;">
        <input type="hidden" name="action" value="delete">
        <button type="submit" id="deleteSubmitBtn" class="pf-btn" style="background:#DC2626;color:#fff;border-color:#DC2626;">
          <i class="fa-solid fa-trash"></i> Yes, Delete
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════
     BULK FEATURE MODAL
═══════════════════════════════════════ -->
<div class="pf-modal-overlay" id="bulkFeatureModal" hidden role="dialog" aria-modal="true" aria-labelledby="bulkFeatureTitle">
  <div class="pf-modal" style="max-width:500px;">
    <div class="pf-modal-head">
      <h2 class="pf-modal-title" id="bulkFeatureTitle"><i class="fa-solid fa-star"></i> Manage Featured Works</h2>
      <button class="pf-modal-close" onclick="closeBulkFeatureModal()" aria-label="Close">✕</button>
    </div>
    <div class="pf-modal-body" style="max-height:55vh;overflow-y:auto;" id="bulkFeatureList">
      <!-- Populated by JS -->
    </div>
    <div class="pf-modal-footer">
      <button class="pf-btn pf-btn--ghost" onclick="closeBulkFeatureModal()">Close</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════
     LIGHTBOX
═══════════════════════════════════════ -->
<div id="lightbox" style="display:none;position:fixed;inset:0;z-index:990;background:rgba(15,12,8,.9);cursor:pointer;align-items:center;justify-content:center;padding:2rem;" onclick="closeLightbox()">
  <button onclick="event.stopPropagation();closeLightbox()" style="position:absolute;top:1.25rem;right:1.5rem;background:rgba(201,168,76,.18);border:1px solid rgba(201,168,76,.35);color:#E8C96A;width:40px;height:40px;border-radius:99px;font-size:1rem;cursor:pointer;">✕</button>
  <img id="lightboxImg" src="" alt="" style="max-width:90vw;max-height:90vh;border-radius:12px;object-fit:contain;box-shadow:0 24px 80px rgba(0,0,0,.6);">
</div>

<!-- ═══════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════ -->
<script>
(function () {
  'use strict';

  /* ─── Portfolio items data from PHP ─────────────────────────────────────── */
  var portfolioItems = <?= $itemsJson ?>;
  var baseUrl        = '<?= BASE_URL ?>';

  /* ─── Helper: find item by id ────────────────────────────────────────────── */
  function getItem(id) {
    return portfolioItems.find(function(i){ return String(i.id) === String(id); }) || null;
  }

  /* ─── Toast ─────────────────────────────────────────────────────────────── */
  window.showToast = function(icon, text, type) {
    type = type || 'info';
    var tc    = document.getElementById('toastContainer');
    var toast = document.createElement('div');
    toast.className = 'qb-toast qb-toast--' + type;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + text;
    tc.appendChild(toast);
    setTimeout(function() { dismissToast(toast); }, 3000);
  };
  window.dismissToast = function(toast) {
    if (!toast || !toast.parentElement) return;
    toast.classList.add('is-leaving');
    setTimeout(function() { if (toast.parentElement) toast.remove(); }, 220);
  };
  function showToast(icon, text, type) { window.showToast(icon, text, type); }
  function dismissToast(toast) { window.dismissToast(toast); }

  /* ─── Theme toggle ───────────────────────────────────────────────────────── */
  var html = document.documentElement;
  var btn  = document.getElementById('themeToggle');
  var moon = btn ? btn.querySelector('.icon-moon') : null;
  var sun  = btn ? btn.querySelector('.icon-sun')  : null;
  function applyTheme(t) {
    if (t === 'dark') {
      html.setAttribute('data-theme', 'dark');
      if (moon) moon.style.display = 'block';
      if (sun)  sun.style.display  = 'none';
    } else {
      html.removeAttribute('data-theme');
      if (moon) moon.style.display = 'none';
      if (sun)  sun.style.display  = 'block';
    }
  }
  applyTheme(localStorage.getItem('qb-theme') || 'light');
  if (btn) btn.addEventListener('click', function() {
    var n = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    localStorage.setItem('qb-theme', n);
    applyTheme(n);
  });

  /* ─── Profile dropdown ───────────────────────────────────────────────────── */
  var trigger  = document.getElementById('profileTrigger');
  var dropdown = document.getElementById('profileDropdown');
  if (trigger && dropdown) {
    trigger.addEventListener('click', function(e) {
      e.stopPropagation();
      var open = dropdown.classList.toggle('is-open');
      trigger.classList.toggle('is-open', open);
      trigger.setAttribute('aria-expanded', open);
    });
    trigger.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger.click(); }
    });
    document.addEventListener('click', function(e) {
      if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('is-open');
        trigger.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') { dropdown.classList.remove('is-open'); trigger.classList.remove('is-open'); }
    });
  }

  /* ═══════════════════════════════════════
     UPLOAD MODAL
  ═══════════════════════════════════════ */
  window.openUploadModal = function() {
    document.getElementById('uploadModal').hidden = false;
    setTimeout(function(){ document.getElementById('portfolioTitle').focus(); }, 100);
  };
  window.closeUploadModal = function() {
    document.getElementById('uploadModal').hidden = true;
  };
  document.getElementById('uploadModal').addEventListener('click', function(e) {
    if (e.target === this) closeUploadModal();
  });

  window.toggleBAFields = function(cb) {
    var single = document.getElementById('singleDropWrap');
    var dual   = document.getElementById('baDropWrap');
    if (cb.checked) {
      single.style.display = 'none';
      dual.style.display   = 'flex';
    } else {
      single.style.display = 'flex';
      dual.style.display   = 'none';
    }
  };

  window.previewBAFile = function(input, previewId, label) {
    if (!input.files || !input.files[0]) return;
    var r = new FileReader();
    r.onload = function(e) {
      var preview = document.getElementById(previewId);
      preview.innerHTML =
        '<div class="pf-drop-full-preview" style="background-image:url(\'' + e.target.result + '\')">' +
          '<div class="pf-drop-full-overlay">' +
            '<span class="pf-drop-full-badge"><i class="fa-solid fa-image"></i> ' + label + '</span>' +
            '<button type="button" class="pf-drop-full-change" onclick="document.getElementById(\'' + input.id + '\').click()">' +
              '<i class="fa-solid fa-arrows-rotate"></i>' +
            '</button>' +
          '</div>' +
        '</div>';
    };
    r.readAsDataURL(input.files[0]);
  };

  window.previewFiles = function(input) {
    var preview = document.getElementById('dropPreview');
    var dropZone = document.getElementById('dropZone');
    preview.innerHTML = '';
    if (!input.files || !input.files.length) return;

    // Use first file as the big cover preview
    var f = input.files[0];
    var r = new FileReader();
    r.onload = function(e) {
      preview.innerHTML =
        '<div class="pf-drop-full-preview" style="background-image:url(\'' + e.target.result + '\')">' +
          '<div class="pf-drop-full-overlay">' +
            '<span class="pf-drop-full-badge"><i class="fa-solid fa-image"></i> ' + f.name + '</span>' +
            '<button type="button" class="pf-drop-full-change" onclick="document.getElementById(\'portfolioFileInput\').click()">' +
              '<i class="fa-solid fa-arrows-rotate"></i> Change Image' +
            '</button>' +
          '</div>' +
        '</div>';
      dropZone.style.padding = '0';
    };
    r.readAsDataURL(f);
  };

  var dz = document.getElementById('dropZone');
  if (dz) {
    dz.addEventListener('dragover',  function(e){ e.preventDefault(); this.classList.add('is-dragover'); });
    dz.addEventListener('dragleave', function(){  this.classList.remove('is-dragover'); });
    dz.addEventListener('drop', function(e) {
      e.preventDefault(); this.classList.remove('is-dragover');
      var input = document.getElementById('portfolioFileInput');
      if (e.dataTransfer.files.length){ input.files = e.dataTransfer.files; previewFiles(input); }
    });
  }

  window.submitUpload = function() {
    var title = document.getElementById('portfolioTitle').value.trim();
    if (!title) { showToast('fa-circle-xmark', 'Please enter a work title.', 'error'); return; }
    var submitBtn = document.getElementById('uploadSubmitBtn');
    submitBtn.classList.add('btn-loading');
    submitBtn.disabled = true;
    document.getElementById('uploadForm').submit();
  };

  /* ═══════════════════════════════════════
     EDIT MODAL
  ═══════════════════════════════════════ */
  window.openEditModal = function(id) {
    var item = getItem(id);
    if (!item) { showToast('fa-circle-xmark', 'Item not found.', 'error'); return; }

    // Set form action
    document.getElementById('editForm').action = baseUrl + 'provider/portfolio/update/' + id;
    document.getElementById('editItemId').value = id;

    // Populate fields
    document.getElementById('editTitle').value   = item.title   || '';
    document.getElementById('editCaption').value = item.caption || '';
    document.getElementById('editPrice').value   = item.price   || '';
    document.getElementById('editFeatured').checked = !!item.is_featured;

    // Show current image as big cover
    var curImg  = document.getElementById('editCurrentImg');
    if (item.image_url) {
      curImg.src = item.image_url;
      curImg.style.display = 'block';
    } else {
      curImg.style.display = 'none';
    }

    // Clear file input
    document.getElementById('editFileInput').value = '';

    // Open modal
    document.getElementById('editModal').hidden = false;
    setTimeout(function(){ document.getElementById('editTitle').focus(); }, 100);
  };

  window.closeEditModal = function() {
    document.getElementById('editModal').hidden = true;
  };

  document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
  });

  // Preview new image on file select — updates the cover in the left panel
  window.previewEditFile = function(input) {
    if (!input.files || !input.files[0]) return;
    var r = new FileReader();
    r.onload = function(e) {
      var curImg = document.getElementById('editCurrentImg');
      curImg.src = e.target.result;
      curImg.style.display = 'block';
    };
    r.readAsDataURL(input.files[0]);
  };

  window.submitEdit = function() {
    var title = document.getElementById('editTitle').value.trim();
    if (!title) { showToast('fa-circle-xmark', 'Please enter a work title.', 'error'); return; }
    var submitBtn = document.getElementById('editSubmitBtn');
    submitBtn.classList.add('btn-loading');
    submitBtn.disabled = true;
    document.getElementById('editForm').submit();
  };

  /* ═══════════════════════════════════════
     DELETE MODAL
  ═══════════════════════════════════════ */
  window.confirmDelete = function(id, title) {
    document.getElementById('deleteItemTitle').textContent = '"' + (title || 'this item') + '"';
    document.getElementById('deleteForm').action = baseUrl + 'provider/portfolio/delete/' + id;
    document.getElementById('deleteModal').hidden = false;
  };

  window.closeDeleteModal = function() {
    document.getElementById('deleteModal').hidden = true;
  };

  document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
  });

  document.getElementById('deleteForm').addEventListener('submit', function() {
    var btn = document.getElementById('deleteSubmitBtn');
    btn.classList.add('btn-loading');
    btn.disabled = true;
    // Toast will show on redirect via flash session
  });

  /* ═══════════════════════════════════════
     FEATURE TOGGLE (individual card star)
  ═══════════════════════════════════════ */
  window.toggleFeature = function(id, btnEl) {
    var item = getItem(id);
    if (!item) return;

    // Optimistic UI update
    var isFeatNow    = !!item.is_featured;
    var willFeat     = !isFeatNow;
    var iconEl       = btnEl.querySelector('i');

    btnEl.classList.toggle('is-featured', willFeat);
    iconEl.className = willFeat ? 'fa-solid fa-star' : 'fa-regular fa-star';
    btnEl.title      = willFeat ? 'Unfeature' : 'Feature';

    // Update local data
    item.is_featured = willFeat ? 1 : 0;

    // Update card data-featured attribute
    var card = document.getElementById('card-' + id);
    if (card) card.dataset.featured = willFeat ? '1' : '0';

    // Send to server
    fetch(baseUrl + 'provider/portfolio/feature/' + id, { method: 'POST' })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.success) {
          showToast(willFeat ? 'fa-star' : 'fa-star-half-stroke', willFeat ? 'Work featured!' : 'Feature removed.', willFeat ? 'success' : 'info');
          setTimeout(function(){ location.reload(); }, 1600);
        } else {
          // Revert on failure
          item.is_featured = isFeatNow ? 1 : 0;
          btnEl.classList.toggle('is-featured', isFeatNow);
          iconEl.className = isFeatNow ? 'fa-solid fa-star' : 'fa-regular fa-star';
          showToast('fa-circle-xmark', 'Could not update featured status.', 'error');
        }
      })
      .catch(function() {
        // Revert on network error
        item.is_featured = isFeatNow ? 1 : 0;
        btnEl.classList.toggle('is-featured', isFeatNow);
        iconEl.className = isFeatNow ? 'fa-solid fa-star' : 'fa-regular fa-star';
        showToast('fa-wifi', 'Network error. Please try again.', 'error');
      });
  };

  /* ═══════════════════════════════════════
     BULK FEATURE MODAL
  ═══════════════════════════════════════ */
  window.openBulkFeatureModal = function() {
    var list = document.getElementById('bulkFeatureList');
    list.innerHTML = '';

    if (!portfolioItems.length) {
      list.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:2rem;">No portfolio items yet.</p>';
      document.getElementById('bulkFeatureModal').hidden = false;
      return;
    }

    portfolioItems.forEach(function(item) {
      var row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:.85rem;padding:.75rem 0;border-bottom:1px solid var(--border);';

      var img = item.image_url
        ? '<img src="' + item.image_url + '" style="width:48px;height:48px;object-fit:cover;border-radius:var(--r-sm);border:1px solid var(--gold-border);flex-shrink:0;">'
        : '<div style="width:48px;height:48px;border-radius:var(--r-sm);background:var(--surface);display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:1.1rem;flex-shrink:0;"><i class="fa-solid fa-image"></i></div>';

      var isFeat = !!item.is_featured;
      row.innerHTML = img +
        '<div style="flex:1;min-width:0;">' +
          '<div style="font-weight:600;font-size:.9rem;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + item.title + '</div>' +
          '<div style="font-size:.75rem;color:var(--text-dim);">' + (item.service_name || '') + '</div>' +
        '</div>' +
        '<button class="pf-btn ' + (isFeat ? 'pf-btn--primary' : 'pf-btn--ghost') + '" ' +
                'id="bulk-btn-' + item.id + '" ' +
                'style="font-size:.75rem;padding:.38rem .9rem;white-space:nowrap;" ' +
                'onclick="bulkToggleFeat(' + item.id + ', this)">' +
          '<i class="fa-' + (isFeat ? 'solid' : 'regular') + ' fa-star"></i> ' + (isFeat ? 'Featured' : 'Feature') +
        '</button>';

      list.appendChild(row);
    });

    document.getElementById('bulkFeatureModal').hidden = false;
  };

  window.closeBulkFeatureModal = function() {
    document.getElementById('bulkFeatureModal').hidden = true;
  };

  document.getElementById('bulkFeatureModal').addEventListener('click', function(e) {
    if (e.target === this) closeBulkFeatureModal();
  });

  window.bulkToggleFeat = function(id, btnEl) {
    var item = getItem(id);
    if (!item) return;
    var willFeat = !item.is_featured;

    // Optimistic update in this modal
    item.is_featured = willFeat ? 1 : 0;
    var iconEl = btnEl.querySelector('i');
    iconEl.className = willFeat ? 'fa-solid fa-star' : 'fa-regular fa-star';
    btnEl.textContent = '';
    btnEl.appendChild(iconEl);
    btnEl.appendChild(document.createTextNode(' ' + (willFeat ? 'Featured' : 'Feature')));
    if (willFeat) {
      btnEl.classList.remove('pf-btn--ghost'); btnEl.classList.add('pf-btn--primary');
    } else {
      btnEl.classList.remove('pf-btn--primary'); btnEl.classList.add('pf-btn--ghost');
    }

    fetch(baseUrl + 'provider/portfolio/feature/' + id, { method: 'POST' })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.success) {
          showToast(willFeat ? 'fa-star' : 'fa-star-half-stroke', willFeat ? 'Work featured!' : 'Feature removed.', willFeat ? 'success' : 'info');
        } else {
          showToast('fa-circle-xmark', 'Could not update featured status.', 'error');
          // Revert
          item.is_featured = willFeat ? 0 : 1;
        }
      })
      .catch(function() { showToast('fa-wifi', 'Network error. Please try again.', 'error'); });
  };

  /* ═══════════════════════════════════════
     LIGHTBOX
  ═══════════════════════════════════════ */
  window.openLightbox = function(url) {
    if (!url) return;
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightbox').style.display = 'flex';
  };
  window.closeLightbox = function() {
    document.getElementById('lightbox').style.display = 'none';
  };
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeLightbox();
      closeEditModal();
      closeDeleteModal();
      closeUploadModal();
      closeBulkFeatureModal();
    }
  });

  /* ═══════════════════════════════════════
     GALLERY FILTER
  ═══════════════════════════════════════ */
  document.querySelectorAll('.pf-filter-btn').forEach(function(b) {
    b.addEventListener('click', function() {
      document.querySelectorAll('.pf-filter-btn').forEach(function(x){ x.classList.remove('is-active'); });
      this.classList.add('is-active');
      var f = this.dataset.filter;
      var visible = 0;
      document.querySelectorAll('.pf-card').forEach(function(card) {
        var show = f === 'all'
          || (f === 'featured'     && card.dataset.featured === '1')
          || (f === 'before-after' && card.dataset.ba       === '1');
        card.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      var badge = document.getElementById('galleryCount');
      if (badge) badge.textContent = visible + ' item' + (visible !== 1 ? 's' : '');
    });
  });

  /* ═══════════════════════════════════════
     BEFORE / AFTER SLIDERS
  ═══════════════════════════════════════ */
  document.querySelectorAll('[data-ba-wrap]').forEach(function(wrap) {
    var slider = wrap.querySelector('[data-ba-slider]');
    var after  = wrap.querySelector('.pf-ba-after');
    var drag   = false;
    function setPos(x) {
      var r = wrap.getBoundingClientRect();
      var p = Math.max(0, Math.min(1, (x - r.left) / r.width));
      slider.style.left = (p * 100) + '%';
      after.style.width = (p * 100) + '%';
    }
    wrap.addEventListener('mousedown',  function(e){ drag = true; setPos(e.clientX); e.preventDefault(); });
    document.addEventListener('mousemove', function(e){ if (drag) setPos(e.clientX); });
    document.addEventListener('mouseup',   function(){ drag = false; });
    wrap.addEventListener('touchstart', function(e){ drag = true; setPos(e.touches[0].clientX); }, { passive: true });
    document.addEventListener('touchmove', function(e){ if (drag) setPos(e.touches[0].clientX); }, { passive: true });
    document.addEventListener('touchend',  function(){ drag = false; });
  });

})();
</script>

</body>
</html>