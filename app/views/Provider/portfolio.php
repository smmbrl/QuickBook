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

// ── Active services for upload modal ─────────────────────────────────────────
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

// ── Demo data (when table empty) ─────────────────────────────────────────────
$demoItems = [
    ['id'=>1,'is_featured'=>1,'is_before_after'=>1,'title'=>'Glossy Nude Nails','service_name'=>'Gel Nail Extensions','caption'=>'Soft nude gel extensions with glossy finish.','price'=>'₱900','likes'=>120,'views'=>1400,'created_at'=>'2026-05-25','featured_label'=>'⭐ Customer Favorite','image_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80','before_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80','after_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80'],
    ['id'=>2,'is_featured'=>1,'is_before_after'=>0,'title'=>'Ombre Pink Chrome','service_name'=>'Acrylic Nail Art','caption'=>'Baby pink base with chrome ombre gradient — a bestseller.','price'=>'₱1,200','likes'=>214,'views'=>2400,'created_at'=>'2026-05-20','featured_label'=>'⭐ Most Booked Style','image_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80'],
    ['id'=>3,'is_featured'=>1,'is_before_after'=>0,'title'=>'French Tip Classic','service_name'=>'Nail Care','caption'=>'Timeless French tip with reinforced gel overlay.','price'=>'₱750','likes'=>98,'views'=>980,'created_at'=>'2026-05-18','featured_label'=>'⭐ Trending Design','image_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80'],
    ['id'=>4,'is_featured'=>0,'is_before_after'=>0,'title'=>'Galaxy Night Art','service_name'=>'Nail Art','caption'=>'Deep navy with gold flecks and micro star details.','price'=>'₱1,100','likes'=>67,'views'=>720,'created_at'=>'2026-05-15','image_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80'],
    ['id'=>5,'is_featured'=>0,'is_before_after'=>1,'title'=>'Full Nail Restoration','service_name'=>'Nail Repair','caption'=>'Damage repair with strengthening base + color.','price'=>'₱850','likes'=>143,'views'=>1100,'created_at'=>'2026-05-10','image_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80','before_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80','after_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80'],
    ['id'=>6,'is_featured'=>0,'is_before_after'=>0,'title'=>'Minimalist Beige Set','service_name'=>'Gel Nail Extensions','caption'=>'Clean lines, neutral tone. Perfect for corporate clients.','price'=>'₱900','likes'=>55,'views'=>610,'created_at'=>'2026-05-07','image_url'=>'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80'],
];

$useDemo = empty($portfolioItems);
$items   = $useDemo ? $demoItems : $portfolioItems;
$featured     = array_filter($items, fn($i) => !empty($i['is_featured']));
$galleryItems = array_filter($items, fn($i) => empty($i['is_featured']));
$analytics = $useDemo
    ? ['uploads'=>124,'views'=>2400,'likes'=>850,'bookings_from_portfolio'=>48]
    : ['uploads'=>count($portfolioItems),'views'=>array_sum(array_column($portfolioItems,'views')),'likes'=>array_sum(array_column($portfolioItems,'likes')),'bookings_from_portfolio'=>0];

// ── Flash ─────────────────────────────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
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
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<!-- ═══════════════════════════════════════
     NAV — identical to dashboard
═══════════════════════════════════════ -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">

    <!-- Logo -->
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo"
           style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Provider</span>
    </a>

    <!-- Centre nav links -->
    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/appointments" class="pv-nav-link">
        Appointments<?php if ($pendingCount): ?><sup class="pv-sup"><?= $pendingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/services"     class="pv-nav-link">Services</a>
      <a href="<?= BASE_URL ?>provider/portfolio"    class="pv-nav-link is-active">Portfolio</a>
      <a href="<?= BASE_URL ?>provider/schedule"     class="pv-nav-link">Schedule</a>
    </div>

    <!-- Right-side controls -->
    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <!-- Theme toggle -->
      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22"  y1="4.22"  x2="5.64"  y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1"  y1="12" x2="3"  y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22"  y1="19.78" x2="5.64"  y2="18.36"/><line x1="18.36" y1="5.64"  x2="19.78" y2="4.22"/>
        </svg>
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
            <span class="pv-pd-role">Provider</span>
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
<div class="pf-flash pf-flash--<?= $flash['type'] ?>" role="alert">
  <span><?= $flash['type'] === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?></span>
  <?= htmlspecialchars($flash['msg']) ?>
  <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:inherit;margin-left:.4rem;font-size:1rem;">✕</button>
</div>
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

    <div class="pf-provider-card">
      <div class="pf-card-avatar">
        <?php if ($photoUrl): ?>
          <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Profile photo">
        <?php else: ?>
          <?= $initials ?>
        <?php endif; ?>
      </div>
      <div class="pf-card-info">
        <!-- Name -->
        <div class="pf-card-name"><?= $fullName ?></div>
        <!-- Category / Service type -->
        <div class="pf-card-role"><?= $categoryName ?></div>
        <!-- Verification status -->
        <?php if ((int)($profile['is_approved'] ?? 0) === 1): ?>
          <div class="pf-card-verified">
            <i class="fa-solid fa-circle-check"></i> Verified Provider
          </div>
        <?php else: ?>
          <div class="pf-card-verified pf-card-verified--pending">
            <i class="fa-solid fa-clock"></i> Pending Verification
          </div>
        <?php endif; ?>
        <!-- Location -->
        <div class="pf-card-location">
          <i class="fa-solid fa-location-dot" style="color:var(--gold-dim);font-size:.75rem;"></i>
          <?= htmlspecialchars($profile['shop_address'] ?? 'Bacolod City, Sum-ag') ?>
        </div>
        <!-- View Profile button -->
        <a href="<?= BASE_URL ?>providers/<?= $profileId ?>" target="_blank" class="pf-card-view-btn">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> View Public Profile
        </a>
      </div>
    </div>

  </div>
</header>

<!-- ═══════════════════════════════════════
     QUICK ACTIONS
═══════════════════════════════════════ -->
<div class="pf-actions-bar">
  <button class="pf-action-btn pf-action-btn--primary" onclick="openUploadModal()">
    <i class="fa-solid fa-plus"></i> Upload Work
  </button>
  <a href="<?= BASE_URL ?>providers/<?= $profileId ?>" target="_blank" class="pf-action-btn pf-action-btn--ghost">
    <i class="fa-solid fa-eye"></i> View Public Profile
  </a>
  <button class="pf-action-btn pf-action-btn--feature" onclick="openFeatureModal()">
    <i class="fa-solid fa-star"></i> Feature Work
  </button>
</div>

<!-- ═══════════════════════════════════════
     MAIN
═══════════════════════════════════════ -->
<main class="pf-page" role="main">

  <!-- Analytics -->
  <div class="pf-analytics">
    <div class="pf-stat-card">
      <div class="pf-stat-icon pf-stat-icon--gold"><i class="fa-solid fa-images"></i></div>
      <div class="pf-stat-body">
        <div class="pf-stat-val"><?= number_format($analytics['uploads']) ?></div>
        <div class="pf-stat-label">Total Uploads</div>
      </div>
    </div>
    <div class="pf-stat-card">
      <div class="pf-stat-icon pf-stat-icon--blue"><i class="fa-solid fa-eye"></i></div>
      <div class="pf-stat-body">
        <div class="pf-stat-val"><?= $analytics['views'] >= 1000 ? number_format($analytics['views']/1000,1).'k' : $analytics['views'] ?></div>
        <div class="pf-stat-label">Most Viewed Work</div>
      </div>
    </div>
    <div class="pf-stat-card">
      <div class="pf-stat-icon pf-stat-icon--amber"><i class="fa-solid fa-heart"></i></div>
      <div class="pf-stat-body">
        <div class="pf-stat-val"><?= number_format($analytics['likes']) ?></div>
        <div class="pf-stat-label">Likes &amp; Saves</div>
      </div>
    </div>
    <div class="pf-stat-card">
      <div class="pf-stat-icon pf-stat-icon--green"><i class="fa-solid fa-calendar-check"></i></div>
      <div class="pf-stat-body">
        <div class="pf-stat-val"><?= number_format($analytics['bookings_from_portfolio']) ?></div>
        <div class="pf-stat-label">Bookings from Portfolio</div>
        <?php if ($analytics['bookings_from_portfolio'] > 0): ?>
          <div class="pf-stat-note">↑ Portfolio converts</div>
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
    <div class="pf-section-title">All Works <span class="pf-section-badge"><?= count($items) ?> items</span></div>
    <div class="pf-filter-row">
      <button class="pf-filter-btn is-active" data-filter="all">All</button>
      <button class="pf-filter-btn" data-filter="before-after">Before &amp; After</button>
      <button class="pf-filter-btn" data-filter="featured">Featured</button>
    </div>
  </div>

  <?php if (empty($items)): ?>
  <div class="pf-empty">
    <div class="pf-empty-icon"><i class="fa-solid fa-images"></i></div>
    <div class="pf-empty-title">No portfolio items yet</div>
    <p class="pf-empty-text">Start building your creative portfolio! Upload your best work to attract more customers.</p>
    <button class="pf-action-btn pf-action-btn--primary" onclick="openUploadModal()">
      <i class="fa-solid fa-plus"></i> Upload Your First Work
    </button>
  </div>
  <?php else: ?>

  <div class="pf-gallery" id="portfolioGallery">
    <?php foreach ($items as $item):
      $isBA    = !empty($item['is_before_after']);
      $imgUrl  = htmlspecialchars($item['image_url'] ?? '');
      $bUrl    = htmlspecialchars($item['before_url'] ?? $imgUrl);
      $aUrl    = htmlspecialchars($item['after_url']  ?? $imgUrl);
    ?>
    <div class="pf-card" data-featured="<?= !empty($item['is_featured'])?'1':'0' ?>" data-ba="<?= $isBA?'1':'0' ?>">

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

      <div class="pf-card-hover-actions">
        <button class="pf-card-icon-btn" title="Feature" onclick="event.stopPropagation();toggleFeature(<?= $item['id'] ?>)">
          <i class="fa-<?= !empty($item['is_featured'])?'solid':'regular' ?> fa-star"></i>
        </button>
        <button class="pf-card-icon-btn" title="Edit" onclick="event.stopPropagation();openEditModal(<?= $item['id'] ?>)">
          <i class="fa-solid fa-pen"></i>
        </button>
        <button class="pf-card-icon-btn pf-card-icon-btn--danger" title="Delete" onclick="event.stopPropagation();confirmDelete(<?= $item['id'] ?>)">
          <i class="fa-solid fa-trash"></i>
        </button>
      </div>

      <div class="pf-card-body">
        <?php if (!empty($item['service_name'])): ?>
        <div class="pf-card-service-tag"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($item['service_name']) ?></div>
        <?php endif; ?>
        <div class="pf-card-title"><?= htmlspecialchars($item['title']) ?></div>
        <?php if (!empty($item['caption'])): ?>
        <div class="pf-card-caption"><?= htmlspecialchars($item['caption']) ?></div>
        <?php endif; ?>
        <div class="pf-card-footer">
          <?php if (!empty($item['price'])): ?>
          <div class="pf-card-price"><?= htmlspecialchars($item['price']) ?></div>
          <?php endif; ?>
          <div class="pf-card-stats">
            <span class="liked"><i class="fa-solid fa-heart"></i> <?= number_format($item['likes']??0) ?></span>
            <span><i class="fa-solid fa-eye"></i> <?= ($item['views']??0)>=1000?number_format(($item['views']??0)/1000,1).'k':number_format($item['views']??0) ?></span>
          </div>
        </div>
        <div class="pf-card-date">
          <i class="fa-regular fa-calendar"></i>
          <?= date('M j, Y', strtotime($item['created_at']??'now')) ?>
          <?php if (!empty($item['is_featured'])): ?>&nbsp;·&nbsp;<i class="fa-solid fa-star" style="color:var(--gold-dim);"></i> Featured<?php endif; ?>
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
<div class="pf-modal-overlay" id="uploadModal" hidden role="dialog" aria-modal="true">
  <div class="pf-modal">
    <div class="pf-modal-head">
      <h2 class="pf-modal-title"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Work</h2>
      <button class="pf-modal-close" onclick="closeUploadModal()">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>provider/portfolio/upload" enctype="multipart/form-data" class="pf-modal-body" id="uploadForm">

      <div class="pf-drop-zone" id="dropZone" onclick="document.getElementById('portfolioFileInput').click()">
        <input type="file" id="portfolioFileInput" name="portfolio_images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none" onchange="previewFiles(this)">
        <div class="pf-drop-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <p class="pf-drop-text">Drag &amp; drop images, or <strong>click to browse</strong></p>
        <p class="pf-drop-text" style="font-size:.78rem;margin-top:.3rem;opacity:.7;">JPG, PNG, WebP — max 5 MB each</p>
        <div class="pf-drop-preview" id="dropPreview"></div>
      </div>

      <div class="pf-toggle-row">
        <span class="pf-toggle-label"><i class="fa-solid fa-left-right" style="color:#2563EB;"></i> Before &amp; After Slider</span>
        <label class="pf-toggle">
          <input type="checkbox" name="is_before_after" id="baToggle" onchange="toggleBAFields(this)">
          <span class="pf-toggle-track"></span><span class="pf-toggle-thumb"></span>
        </label>
      </div>

      <div id="baFields" style="display:none;flex-direction:column;gap:.75rem;">
        <div class="pf-form-group">
          <label class="pf-form-label">Before Image</label>
          <input type="file" name="before_image" accept="image/*" class="pf-form-control" style="padding:.5rem;">
        </div>
        <div class="pf-form-group">
          <label class="pf-form-label">After Image</label>
          <input type="file" name="after_image" accept="image/*" class="pf-form-control" style="padding:.5rem;">
        </div>
      </div>

      <div class="pf-form-group">
        <label class="pf-form-label">Work Title <span style="color:#DC2626;">*</span></label>
        <input type="text" name="title" id="portfolioTitle" class="pf-form-control" placeholder="e.g. Glossy Nude Nails" required>
      </div>

      <div class="pf-form-group">
        <label class="pf-form-label">Short Caption</label>
        <textarea name="caption" class="pf-form-control" rows="2" placeholder="Describe this work briefly..."></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="pf-form-group">
          <label class="pf-form-label">Related Service</label>
          <select name="service_id" class="pf-form-control">
            <option value="">— Select service —</option>
            <?php foreach ($services as $svc): ?>
            <option value="<?= $svc['id'] ?>"><?= htmlspecialchars($svc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pf-form-group">
          <label class="pf-form-label">Price (optional)</label>
          <input type="text" name="price" class="pf-form-control" placeholder="₱900">
        </div>
      </div>

      <div class="pf-toggle-row">
        <span class="pf-toggle-label"><i class="fa-solid fa-star" style="color:var(--gold-dim);"></i> Feature this work</span>
        <label class="pf-toggle">
          <input type="checkbox" name="is_featured">
          <span class="pf-toggle-track"></span><span class="pf-toggle-thumb"></span>
        </label>
      </div>

    </form>
    <div class="pf-modal-footer">
      <button class="pf-btn pf-btn--ghost" onclick="closeUploadModal()">Cancel</button>
      <button class="pf-btn pf-btn--primary" onclick="submitUpload()">
        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Work
      </button>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div id="lightbox" style="display:none;position:fixed;inset:0;z-index:990;background:rgba(15,12,8,.9);cursor:pointer;align-items:center;justify-content:center;padding:2rem;" onclick="closeLightbox()">
  <button onclick="event.stopPropagation();closeLightbox()" style="position:absolute;top:1.25rem;right:1.5rem;background:rgba(201,168,76,.18);border:1px solid rgba(201,168,76,.35);color:#E8C96A;width:40px;height:40px;border-radius:99px;font-size:1rem;cursor:pointer;">✕</button>
  <img id="lightboxImg" src="" alt="" style="max-width:90vw;max-height:90vh;border-radius:12px;object-fit:contain;box-shadow:0 24px 80px rgba(0,0,0,.6);">
</div>

<!-- Delete modal -->
<div class="pf-modal-overlay" id="deleteModal" hidden role="dialog" aria-modal="true">
  <div class="pf-modal" style="max-width:400px;">
    <div class="pf-modal-head">
      <h2 class="pf-modal-title" style="color:#DC2626;"><i class="fa-solid fa-triangle-exclamation"></i> Delete Work</h2>
      <button class="pf-modal-close" onclick="document.getElementById('deleteModal').hidden=true">✕</button>
    </div>
    <div class="pf-modal-body">
      <p style="color:var(--text-muted);font-size:.92rem;">Are you sure you want to delete this portfolio item? This cannot be undone.</p>
    </div>
    <div class="pf-modal-footer">
      <button class="pf-btn pf-btn--ghost" onclick="document.getElementById('deleteModal').hidden=true">Cancel</button>
      <form id="deleteForm" method="POST" style="display:inline;">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="pf-btn" style="background:#DC2626;color:#fff;border-color:#DC2626;"><i class="fa-solid fa-trash"></i> Delete</button>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════ -->
<script>
(function () {

  // ── Theme toggle ──────────────────────────────────────────────────────────
  var html = document.documentElement;
  var btn  = document.getElementById('themeToggle');
  var moon = btn ? btn.querySelector('.icon-moon') : null;
  var sun  = btn ? btn.querySelector('.icon-sun')  : null;
  function applyTheme(t) {
    if (t==='dark'){ html.setAttribute('data-theme','dark'); if(moon)moon.style.display='block'; if(sun)sun.style.display='none'; }
    else { html.removeAttribute('data-theme'); if(moon)moon.style.display='none'; if(sun)sun.style.display='block'; }
  }
  applyTheme(localStorage.getItem('qb-theme')||'light');
  if (btn) btn.addEventListener('click', function() {
    var n = html.getAttribute('data-theme')==='dark'?'light':'dark';
    localStorage.setItem('qb-theme',n); applyTheme(n);
  });

  // ── Profile dropdown ──────────────────────────────────────────────────────
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
      if (e.key==='Enter'||e.key===' ') { e.preventDefault(); trigger.click(); }
    });
    document.addEventListener('click', function(e) {
      if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('is-open');
        trigger.classList.remove('is-open');
        trigger.setAttribute('aria-expanded','false');
      }
    });
    document.addEventListener('keydown', function(e) {
      if (e.key==='Escape') { dropdown.classList.remove('is-open'); trigger.classList.remove('is-open'); }
    });
  }

  // ── Upload modal ──────────────────────────────────────────────────────────
  window.openUploadModal  = function() { document.getElementById('uploadModal').hidden=false; document.getElementById('portfolioTitle').focus(); };
  window.closeUploadModal = function() { document.getElementById('uploadModal').hidden=true; };
  document.getElementById('uploadModal')?.addEventListener('click', function(e){ if(e.target===this) closeUploadModal(); });

  window.toggleBAFields = function(cb) {
    var f = document.getElementById('baFields');
    f.style.display = cb.checked ? 'flex' : 'none';
  };

  window.previewFiles = function(input) {
    var preview = document.getElementById('dropPreview');
    preview.innerHTML = '';
    Array.from(input.files).forEach(function(f) {
      var r = new FileReader();
      r.onload = function(e) {
        var img = document.createElement('img'); img.src = e.target.result;
        img.className = 'pf-drop-preview-img'; preview.appendChild(img);
      };
      r.readAsDataURL(f);
    });
  };

  var dz = document.getElementById('dropZone');
  if (dz) {
    dz.addEventListener('dragover', function(e){ e.preventDefault(); this.classList.add('is-dragover'); });
    dz.addEventListener('dragleave', function(){ this.classList.remove('is-dragover'); });
    dz.addEventListener('drop', function(e){
      e.preventDefault(); this.classList.remove('is-dragover');
      var input = document.getElementById('portfolioFileInput');
      if (e.dataTransfer.files.length){ input.files=e.dataTransfer.files; previewFiles(input); }
    });
  }

  window.submitUpload = function() {
    var title = document.getElementById('portfolioTitle').value.trim();
    if (!title){ alert('Please enter a title for your work.'); return; }
    document.getElementById('uploadForm').submit();
  };

  // ── Delete confirm ────────────────────────────────────────────────────────
  window.confirmDelete = function(id) {
    document.getElementById('deleteForm').action = '<?= BASE_URL ?>provider/portfolio/delete/' + id;
    document.getElementById('deleteModal').hidden = false;
  };
  document.getElementById('deleteModal')?.addEventListener('click', function(e){ if(e.target===this) this.hidden=true; });

  window.toggleFeature = function(id) {
    fetch('<?= BASE_URL ?>provider/portfolio/feature/'+id, {method:'POST'})
      .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); }).catch(()=>{});
  };
  window.openEditModal   = function(id) { alert('Edit modal for item #'+id); };
  window.openFeatureModal = function() { alert('Select which works to pin as featured.'); };

  // ── Lightbox ──────────────────────────────────────────────────────────────
  window.openLightbox = function(url) {
    if (!url) return;
    var lb = document.getElementById('lightbox');
    document.getElementById('lightboxImg').src = url;
    lb.style.display = 'flex';
  };
  window.closeLightbox = function() { document.getElementById('lightbox').style.display='none'; };
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeLightbox(); });

  // ── Gallery filter ────────────────────────────────────────────────────────
  document.querySelectorAll('.pf-filter-btn').forEach(function(b) {
    b.addEventListener('click', function() {
      document.querySelectorAll('.pf-filter-btn').forEach(x=>x.classList.remove('is-active'));
      this.classList.add('is-active');
      var f = this.dataset.filter;
      document.querySelectorAll('.pf-card').forEach(function(card) {
        if (f==='all') { card.style.display=''; return; }
        if (f==='featured' && card.dataset.featured==='1') { card.style.display=''; return; }
        if (f==='before-after' && card.dataset.ba==='1') { card.style.display=''; return; }
        card.style.display = 'none';
      });
    });
  });

  // ── Before/After sliders ──────────────────────────────────────────────────
  document.querySelectorAll('[data-ba-wrap]').forEach(function(wrap) {
    var slider = wrap.querySelector('[data-ba-slider]');
    var after  = wrap.querySelector('.pf-ba-after');
    var drag   = false;
    function setPos(x) {
      var r = wrap.getBoundingClientRect();
      var p = Math.max(0, Math.min(1, (x - r.left) / r.width));
      slider.style.left = (p*100)+'%'; after.style.width = (p*100)+'%';
    }
    wrap.addEventListener('mousedown', function(e){ drag=true; setPos(e.clientX); e.preventDefault(); });
    document.addEventListener('mousemove', function(e){ if(drag) setPos(e.clientX); });
    document.addEventListener('mouseup',   function()  { drag=false; });
    wrap.addEventListener('touchstart', function(e){ drag=true; setPos(e.touches[0].clientX); },{passive:true});
    document.addEventListener('touchmove', function(e){ if(drag) setPos(e.touches[0].clientX); },{passive:true});
    document.addEventListener('touchend',  function(){ drag=false; });
  });

})();
</script>

</body>
</html>