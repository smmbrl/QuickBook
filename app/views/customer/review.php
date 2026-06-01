<?php
// app/views/customer/review.php
// $booking is injected by CustomerController::review()

require_once __DIR__ . '/../../../config/database.php';
$db       = Database::getInstance();
$userId   = (int)($_SESSION['user_id'] ?? 0);
$userName  = htmlspecialchars($_SESSION['user_name'] ?? 'Customer');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? '');
$initials  = strtoupper(substr($userName, 0, 2));

// Fetch avatar
$stAvR = $db->prepare("SELECT avatar_url FROM tbl_users WHERE id = ? LIMIT 1");
$stAvR->execute([$userId]);
$avatarUrl = ($av = $stAvR->fetchColumn()) ? $av : null;

// Nav badges
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

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$bookingDateFmt = !empty($booking['booking_date'])
    ? date('M j, Y', strtotime($booking['booking_date']))
    : '';

$providerId = (int)($booking['provider_id'] ?? 0);

$stReviews = $db->prepare("
    SELECT r.rating, r.comment, r.created_at,
           TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) AS reviewer_name,
           u.avatar_url AS profile_photo
    FROM   tbl_reviews r
    JOIN   tbl_users   u ON u.id = r.customer_id
    WHERE  r.provider_id = ?
      AND  r.is_visible  = 1
    ORDER  BY r.created_at DESC
    LIMIT  20
");
$stReviews->execute([$providerId]);
$reviews = $stReviews->fetchAll(PDO::FETCH_ASSOC);

// Rating breakdown
$stBreakdown = $db->prepare("
    SELECT rating, COUNT(*) AS cnt
    FROM   tbl_reviews
    WHERE  provider_id = ?
      AND  is_visible  = 1
    GROUP  BY rating
");
$stBreakdown->execute([$providerId]);
$breakdown = array_fill(1, 5, 0);
foreach ($stBreakdown->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $breakdown[(int)$row['rating']] = (int)$row['cnt'];
}
$totalReviews = array_sum($breakdown);
$avgRating    = $totalReviews
    ? round(array_sum(array_map(fn($s,$c) => $s * $c, array_keys($breakdown), $breakdown)) / $totalReviews, 1)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Leave a Review</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_bookings.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer_review.css">
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
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo" style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Customer</span>
    </a>

    <div class="pv-nav-links">
      <a href="<?= BASE_URL ?>dashboard" class="pv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>bookings" class="pv-nav-link is-active">
        Bookings
        <?php if ($upcomingCount): ?><sup class="pv-sup"><?= $upcomingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>browse" class="pv-nav-link">Browse Services</a>
      <a href="<?= BASE_URL ?>loyalty" class="pv-nav-link">Loyalty</a>
      <a href="<?= BASE_URL ?>profile" class="pv-nav-link">Profile</a>
    </div>

    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av">
          <?php if ($avatarUrl): ?>
            <img src="<?= $avatarUrl ?>" alt="<?= $userName ?>" style="width:34px;height:34px;object-fit:cover;border-radius:99px;display:block;">
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

<!-- ══ ROOT ══ -->
<div class="rv-root">

  <!-- HERO -->
  <section class="rv-hero">
    <div class="rv-hero-inner">
      <div>
        <p class="rv-hero-crumb">
          <span class="rv-hero-dot" aria-hidden="true"></span>
          Booking #<?= (int)$booking['id'] ?> &nbsp;·&nbsp; <?= htmlspecialchars($booking['business_name']) ?>
        </p>
        <h1 class="rv-hero-h1">Leave a <em>Review</em></h1>
        <p class="rv-hero-sub">Your honest feedback helps other customers make better decisions.</p>
      </div>
      <div class="rv-hero-actions">
        <a href="<?= BASE_URL ?>bookings/<?= (int)$booking['id'] ?>" class="rv-hero-back">
          <i class="fa-solid fa-arrow-left"></i> Back to Booking
        </a>
        <a href="<?= BASE_URL ?>providers/<?= $providerId ?>" class="rv-hero-back">
          <i class="fa-solid fa-store"></i> View Provider Profile
        </a>
      </div>
    </div>
  </section>

  <!-- ══ BODY — two columns ══ -->
  <div class="rv-body">

    <!-- ══════════════════════════════
         LEFT — All Reviews list
         ══════════════════════════════ -->
    <div class="rv-left">

      <?php if ($flash): ?>
      <div class="rv-flash-bar rv-flash-bar--<?= htmlspecialchars($flash['type']) ?>" id="flashBar">
        <?= $flash['type'] === 'success'
            ? '<i class="fa-solid fa-circle-check"></i>'
            : '<i class="fa-solid fa-triangle-exclamation"></i>' ?>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <!-- Section header -->
      <div class="rv-sec-head">
        <span class="rv-sec-title">All Reviews</span>
        <?php if ($totalReviews): ?>
        <span class="rv-sec-badge"><?= $totalReviews ?></span>
        <?php endif; ?>
      </div>

      <!-- Review rows -->
      <?php if (empty($reviews)): ?>
      <div class="rv-empty">
        <div class="rv-empty-icon"><i class="fa-regular fa-comment-dots"></i></div>
        <div class="rv-empty-msg">No reviews yet. Be the first to leave one!</div>
      </div>
      <?php else: ?>
        <?php foreach ($reviews as $rev):
          $rName    = htmlspecialchars($rev['reviewer_name'] ?? 'Anonymous');
          $rInit    = strtoupper(substr($rName, 0, 2));
          $rDate    = !empty($rev['created_at']) ? date('j M Y', strtotime($rev['created_at'])) : '';
          $rRating  = (int)$rev['rating'];
          $rComment = htmlspecialchars($rev['comment'] ?? '');
        ?>
        <div class="rv-row">
          <!-- Avatar -->
          <div class="rv-row-avatar">
            <?php if (!empty($rev['profile_photo'])): ?>
              <img src="<?= BASE_URL . htmlspecialchars($rev['profile_photo']) ?>" alt="<?= $rName ?>">
            <?php else: ?>
              <?= $rInit ?>
            <?php endif; ?>
          </div>

          <!-- Name + stars column -->
          <div class="rv-row-meta">
            <div class="rv-row-name"><?= $rName ?></div>
            <div class="rv-row-stars" aria-label="<?= $rRating ?> out of 5 stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="fa-solid fa-star<?= $i > $rRating ? ' rv-star-empty' : '' ?>"></i>
              <?php endfor; ?>
            </div>
          </div>

          <!-- Review text + footer -->
          <div class="rv-row-body">
            <?php if ($rComment): ?>
              <p class="rv-row-text"><?= $rComment ?></p>
            <?php else: ?>
              <p class="rv-row-text rv-row-text--empty">No written review.</p>
            <?php endif; ?>
            <div class="rv-row-foot">
              <span class="rv-row-thumbs">
                <span class="rv-thumb rv-thumb--up"><i class="fa-regular fa-thumbs-up"></i> <span>12</span></span>
                <span class="rv-thumb rv-thumb--dn"><i class="fa-regular fa-thumbs-down"></i> <span>5</span></span>
              </span>
              <?php if ($rDate): ?>
              <span class="rv-row-date"><?= $rDate ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div><!-- /rv-row -->
        <?php endforeach; ?>
      <?php endif; ?>

    </div><!-- /rv-left -->

    <!-- ══════════════════════════════
         RIGHT — Assessment + Add Review
         ══════════════════════════════ -->
    <div class="rv-right">
      <div class="rv-panel">

        <!-- Panel title -->
        <div class="rv-panel-title">Assessment Reviews</div>

        <!-- Star breakdown rows -->
        <div class="rv-assess-bars">
          <?php foreach ([5,4,3,2,1] as $star):
            $cnt = $breakdown[$star];
            $pct = $totalReviews ? round($cnt / $totalReviews * 100) : 0;
          ?>
          <div class="rv-assess-row">
            <div class="rv-assess-stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="fa-solid fa-star<?= $i > $star ? ' rv-star-empty' : '' ?>"></i>
              <?php endfor; ?>
            </div>
            <div class="rv-assess-track">
              <div class="rv-assess-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="rv-assess-pct"><?= $pct ?>%</div>
            <div class="rv-assess-cnt"><?= $cnt ?> <?= $cnt === 1 ? 'Review' : 'Reviews' ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Add Review button — opens modal -->
        <button class="rv-btn-add-review" id="openModalBtn" type="button">
          <i class="fa-solid fa-pen-to-square"></i> Add Review
        </button>

      </div><!-- /rv-panel -->
    </div><!-- /rv-right -->

  </div><!-- /rv-body -->
</div><!-- /rv-root -->

<!-- ══ REVIEW MODAL ══ -->
<div class="rv-modal-overlay" id="reviewModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="rv-modal-box">

    <!-- Modal header -->
    <div class="rv-modal-header">
      <div>
        <div class="rv-modal-title" id="modalTitle">
          <i class="fa-solid fa-pen-to-square"></i> Leave a Review
        </div>
        <div class="rv-modal-sub">
          Reviewing <strong><?= htmlspecialchars($booking['service_name']) ?></strong>
          &nbsp;·&nbsp; Booking #<?= (int)$booking['id'] ?>
        </div>
      </div>
      <button class="rv-modal-close" id="closeModalBtn" aria-label="Close">
        <i class="fa-solid fa-times"></i>
      </button>
    </div>

    <!-- Modal body -->
    <div class="rv-modal-body">
      <form method="POST" action="<?= BASE_URL ?>bookings/<?= (int)$booking['id'] ?>/review"
            id="reviewForm" novalidate>

        <!-- Stars -->
        <div class="rv-flabel">Your Rating</div>
        <div class="rv-fstars-box" id="starsWrap" role="group" aria-label="Star rating">
          <div class="rv-fstars-row">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button" class="rv-star-btn" data-v="<?= $i ?>"
                    aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
              <i class="fa-solid fa-star"></i>
            </button>
            <?php endfor; ?>
          </div>
          <div class="rv-star-verdict">
            <div class="rv-verdict-text" id="starLbl">Select your rating</div>
            <div class="rv-verdict-sub"  id="starSub">Tap a star or choose below</div>
          </div>
        </div>
        <input type="hidden" name="rating" id="ratingVal" value="0">

        <div class="rv-fpills" role="group" aria-label="Quick rating">
          <button type="button" class="rv-fpill" data-q="1"><i class="fa-solid fa-star"></i> Poor</button>
          <button type="button" class="rv-fpill" data-q="2"><i class="fa-solid fa-star"></i> Fair</button>
          <button type="button" class="rv-fpill" data-q="3"><i class="fa-solid fa-star"></i> Good</button>
          <button type="button" class="rv-fpill" data-q="4"><i class="fa-solid fa-star"></i> Very Good</button>
          <button type="button" class="rv-fpill" data-q="5"><i class="fa-solid fa-star"></i> Excellent</button>
        </div>

        <!-- Comment -->
        <div class="rv-flabel" style="margin-top:1.25rem;">
          Your Comment
          <span style="text-transform:none;letter-spacing:0;font-size:.6rem;">(optional)</span>
        </div>
        <textarea class="rv-textarea" id="commentField" name="comment" maxlength="1000"
          placeholder="Tell others about your experience — service quality, punctuality, professionalism…"
          oninput="onType(this)"></textarea>
        <div class="rv-textarea-foot">
          <div class="rv-tprogress"><div class="rv-tprogress-fill" id="progFill"></div></div>
          <span class="rv-tcount"><span id="charNum">0</span> / 1000</span>
        </div>
        <div class="rv-fchips">
          <span class="rv-fchip" onclick="inject('Great service!')">Great service!</span>
          <span class="rv-fchip" onclick="inject('Would book again.')">Would book again.</span>
          <span class="rv-fchip" onclick="inject('Highly recommended!')">Highly recommended!</span>
          <span class="rv-fchip" onclick="inject('Very professional and on time.')">Very professional.</span>
          <span class="rv-fchip" onclick="inject('Excellent value for money.')">Great value!</span>
        </div>

        <!-- Modal footer -->
        <div class="rv-modal-footer">
          <button type="submit" class="rv-btn-submit" id="submitBtn" disabled>
            <i class="fa-solid fa-paper-plane"></i> Submit Review
          </button>
        </div>

      </form>
    </div><!-- /rv-modal-body -->

  </div><!-- /rv-modal-box -->
</div><!-- /rv-modal-overlay -->

<script>
  // ── Modal open / close ──
  const modal        = document.getElementById('reviewModal');
  const openModalBtn = document.getElementById('openModalBtn');
  const closeModalBtn= document.getElementById('closeModalBtn');

  function openModal() {
    modal.classList.add('rv-modal-overlay--open');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    modal.classList.remove('rv-modal-overlay--open');
    document.body.style.overflow = '';
  }

  openModalBtn.addEventListener('click', openModal);
  closeModalBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e) {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
  });

  // ── Star rating ──
  const stars     = document.querySelectorAll('.rv-star-btn');
  const pills     = document.querySelectorAll('.rv-fpill');
  const ratingVal = document.getElementById('ratingVal');
  const starLbl   = document.getElementById('starLbl');
  const starSub   = document.getElementById('starSub');
  const starsWrap = document.getElementById('starsWrap');
  const submitBtn = document.getElementById('submitBtn');
  const textarea  = document.getElementById('commentField');
  const progFill  = document.getElementById('progFill');

  const MAIN = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
  const SUBS = ['', "We're sorry to hear that.", 'Could definitely be better.', 'A decent experience overall.', 'Really happy with the service!', 'Absolutely loved it! 🎉'];

  let cur = 0;

  function pick(v) {
    cur = v;
    ratingVal.value = v;
    renderStars(v);
    pills.forEach(p => p.classList.toggle('on', +p.dataset.q === v));
    starsWrap.classList.toggle('lit', v > 0);
    submitBtn.disabled = v < 1;
    if (v > 0) {
      starLbl.textContent = MAIN[v];
      starLbl.classList.add('on');
      starSub.textContent = SUBS[v];
      starSub.classList.add('on');
    }
  }

  function renderStars(v) {
    stars.forEach(s => s.classList.toggle('on', +s.dataset.v <= v));
  }

  stars.forEach(s => {
    s.addEventListener('mouseenter', () => renderStars(+s.dataset.v));
    s.addEventListener('mouseleave', () => renderStars(cur));
    s.addEventListener('click',      () => pick(+s.dataset.v));
  });

  pills.forEach(p => p.addEventListener('click', () => pick(+p.dataset.q)));

  function onType(el) {
    const n = el.value.length;
    document.getElementById('charNum').textContent = n;
    progFill.style.width = (n / 1000 * 100) + '%';
  }

  function inject(text) {
    if (textarea.value && !textarea.value.endsWith(' ')) textarea.value += ' ';
    textarea.value += text;
    onType(textarea);
    textarea.focus();
  }

  document.getElementById('reviewForm').addEventListener('submit', function (e) {
    if (cur < 1) {
      e.preventDefault();
      starLbl.textContent = 'Please select a rating first!';
      starLbl.style.color = '#dc2626';
      starLbl.classList.add('on');
      starsWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    submitBtn.disabled  = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
  });

  // ── Flash bar auto-dismiss ──
  const flashBar = document.getElementById('flashBar');
  if (flashBar) {
    setTimeout(() => {
      flashBar.style.transition = 'opacity .5s, transform .5s';
      flashBar.style.opacity = '0';
      flashBar.style.transform = 'translateY(-8px)';
      setTimeout(() => flashBar.remove(), 500);
    }, 4000);
  }

  // ── Theme toggle ──
  const themeToggle = document.getElementById('themeToggle');
  const iconMoon    = themeToggle ? themeToggle.querySelector('.icon-moon') : null;
  const iconSun     = themeToggle ? themeToggle.querySelector('.icon-sun')  : null;

  function applyTheme(t) {
    if (t === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
      if (iconMoon) iconMoon.style.display = 'none';
      if (iconSun)  iconSun.style.display  = 'block';
    } else {
      document.documentElement.removeAttribute('data-theme');
      if (iconMoon) iconMoon.style.display = 'block';
      if (iconSun)  iconSun.style.display  = 'none';
    }
  }

  applyTheme(localStorage.getItem('qb-theme') || 'light');

  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem('qb-theme', next);
      applyTheme(next);
    });
  }
</script>

<script>
/* ── Profile Dropdown ── */
(function () {
  const trigger  = document.getElementById('profileTrigger');
  const dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;

  function open() {
    trigger.classList.add('is-open');
    dropdown.classList.add('is-open');
    trigger.setAttribute('aria-expanded', 'true');
  }
  function close() {
    trigger.classList.remove('is-open');
    dropdown.classList.remove('is-open');
    trigger.setAttribute('aria-expanded', 'false');
  }
  function toggle() {
    dropdown.classList.contains('is-open') ? close() : open();
  }

  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    toggle();
  });
  trigger.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    if (e.key === 'Escape') close();
  });
  document.addEventListener('click', function (e) {
    if (!dropdown.contains(e.target) && !trigger.contains(e.target)) close();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
})();
</script>
</body>
</html>