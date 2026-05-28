<?php
// app/views/Provider/reviews.php
require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$userId       = (int)($_SESSION['user_id'] ?? 0);
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');
$email        = htmlspecialchars($_SESSION['user_email'] ?? '');
$firstName    = htmlspecialchars(explode(' ', $providerName)[0]);
$initials     = strtoupper(substr($providerName, 0, 2));

/* ── Provider profile ── */
$stmt = $db->prepare("SELECT pp.*, c.name AS category_name
    FROM tbl_provider_profiles pp
    LEFT JOIN tbl_categories c ON pp.category_id = c.id
    WHERE pp.user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$profile      = $stmt->fetch() ?: [];
$profileId    = (int)($profile['id'] ?? 0);
$bizName      = htmlspecialchars($profile['business_name'] ?? $providerName);
$categoryName = htmlspecialchars($profile['category_name'] ?? 'Service Provider');
$profilePhoto = $profile['profile_photo'] ?? null;

/* ── Pending bookings count (nav badge) ── */
$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stPending->execute([$profileId]);
$pendingCount = (int)$stPending->fetchColumn();

/* ── Ensure tbl_review_replies exists (creates it on first load if missing) ── */
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS tbl_review_replies (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            review_id   INT UNSIGNED NOT NULL,
            provider_id INT UNSIGNED NOT NULL,
            reply_text  TEXT         NOT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_review (review_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (\Exception $e) { /* silently skip if insufficient privilege */ }

/* ── Star-filter from query string ── */
$filterStar = isset($_GET['stars']) ? (int)$_GET['stars'] : 0;
$filterStar = ($filterStar >= 1 && $filterStar <= 5) ? $filterStar : 0;

/* ── Reviews (no JOIN on tbl_review_replies — fetched separately for safety) ── */
$starClause = $filterStar ? " AND r.rating = " . (int)$filterStar : "";
$stReviews = $db->prepare("
    SELECT r.*,
           r.comment AS review_text,
           CONCAT(u.first_name,' ',u.last_name) AS customer_name,
           u.avatar_url AS customer_avatar,
           s.name AS service_name
    FROM tbl_reviews r
    JOIN tbl_users u ON r.customer_id = u.id
    LEFT JOIN tbl_services s ON r.service_id = s.id
    WHERE r.provider_id = ? AND r.is_visible = 1 $starClause
    ORDER BY r.created_at DESC
");
$stReviews->execute([$profileId]);
$reviews = $stReviews->fetchAll();

/* ── Fetch replies separately (safe — tbl_review_replies may not exist yet) ── */
$repliesMap = [];
try {
    $stReplies = $db->prepare("SELECT review_id, reply_text, created_at FROM tbl_review_replies WHERE provider_id = ?");
    $stReplies->execute([$profileId]);
    foreach ($stReplies->fetchAll() as $rr) {
        $repliesMap[(int)$rr['review_id']] = $rr;
    }
} catch (\Exception $e) { /* table doesn't exist yet — replies simply won't show */ }

/* ── Merge reply data into each review row ── */
foreach ($reviews as &$rev) {
    $rid = (int)$rev['id'];
    $rev['reply_text'] = $repliesMap[$rid]['reply_text'] ?? null;
    $rev['replied_at'] = $repliesMap[$rid]['created_at'] ?? null;
}
unset($rev);

/* ── Aggregate stats ── */
$stStats = $db->prepare("
    SELECT COUNT(*) AS total, ROUND(AVG(rating), 1) AS avg_rating
    FROM tbl_reviews r
    WHERE r.provider_id = ? AND r.is_visible = 1
");
$stStats->execute([$profileId]);
$stats = $stStats->fetch();

$totalReviews = (int)($stats['total'] ?? 0);
$avgRating    = (float)($stats['avg_rating'] ?? 0);
$repliedCount = count($repliesMap);          // derived from separately-fetched replies
$unanswered   = $totalReviews - $repliedCount;
$responseRate = $totalReviews > 0 ? round($repliedCount / $totalReviews * 100) : 0;

/* ── Rating breakdown ── */
$stBreakdown = $db->prepare("SELECT rating, COUNT(*) AS cnt FROM tbl_reviews WHERE provider_id = ? AND is_visible = 1 GROUP BY rating ORDER BY rating DESC");
$stBreakdown->execute([$profileId]);
$breakdown = [];
foreach ($stBreakdown->fetchAll() as $row) {
    $breakdown[(int)$row['rating']] = (int)$row['cnt'];
}

/* ── Handle reply POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_review_id'])) {
    $reviewId = (int)$_POST['reply_review_id'];
    $replyText = trim($_POST['reply_text'] ?? '');
    if ($replyText && $reviewId) {
        try {
            $chk = $db->prepare("SELECT r.id FROM tbl_reviews r WHERE r.id = ? AND r.provider_id = ?");
            $chk->execute([$reviewId, $profileId]);
            if ($chk->fetch()) {
                $ins = $db->prepare("INSERT INTO tbl_review_replies (review_id, provider_id, reply_text, created_at) VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE reply_text = VALUES(reply_text), created_at = NOW()");
                $ins->execute([$reviewId, $profileId, $replyText]);
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Reply posted successfully.'];
            }
        } catch (\Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Failed to post reply.'];
        }
    }
    header('Location: ' . BASE_URL . 'provider/reviews'); exit;
}

/* ── Flash ── */
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

/* ── Helpers ── */
function starFillR(float $avg, int $pos): string {
    $diff = $avg - $pos + 1;
    if ($diff >= 1)   return 'full';
    if ($diff >= 0.5) return 'half';
    return 'empty';
}
function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — My Reviews</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_dashboard.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_reviews.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script>(function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();</script>
</head>
<body>
<div class="grain" aria-hidden="true"></div>

<!-- ══════════════════════════════════
     NAV
══════════════════════════════════ -->
<nav class="rv-nav" role="navigation" aria-label="Provider navigation">
  <div class="rv-nav-inner">

    <a href="<?= BASE_URL ?>home" class="rv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook" style="width:40px;height:40px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="rv-logo-badge">Provider</span>
    </a>

    <div class="rv-nav-links">
      <a href="<?= BASE_URL ?>provider/dashboard"    class="rv-nav-link">Dashboard</a>
      <a href="<?= BASE_URL ?>provider/appointments" class="rv-nav-link">
        Appointments<?php if ($pendingCount): ?><sup class="rv-sup"><?= $pendingCount ?></sup><?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>provider/portfolio"    class="rv-nav-link">Portfolio</a>
      <a href="<?= BASE_URL ?>provider/schedule"     class="rv-nav-link">Schedule</a>
      <a href="<?= BASE_URL ?>provider/reviews"      class="rv-nav-link is-active">Reviews</a>
    </div>

    <div class="rv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>

      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle theme">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/>
          <line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/>
          <line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
        </svg>
      </button>

      <div class="rv-profile-trigger" id="profileTrigger" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
        <div class="rv-nav-av">
          <?php if ($profilePhoto): ?>
            <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
          <?php else: ?>
            <?= $initials ?>
          <?php endif; ?>
        </div>
        <div class="rv-nav-user-name"><?= $firstName ?></div>
        <svg class="rv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </div>

      <div class="rv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="rv-pd-header">
          <div class="rv-pd-avatar">
            <?php if ($profilePhoto): ?>
              <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="rv-pd-info">
            <div class="rv-pd-name"><?= $bizName ?></div>
            <div class="rv-pd-email"><?= $email ?></div>
            <span class="rv-pd-role">Provider</span>
          </div>
        </div>
        <div class="rv-pd-divider"></div>
        <a href="<?= BASE_URL ?>provider/profile" class="rv-pd-item" role="menuitem">
          <span class="rv-pd-item-ico"><i class="fa-solid fa-store"></i></span>
          <span>Business Profile</span>
          <svg class="rv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <a href="<?= BASE_URL ?>provider/settings" class="rv-pd-item" role="menuitem">
          <span class="rv-pd-item-ico"><i class="fa-solid fa-gear"></i></span>
          <span>Settings</span>
          <svg class="rv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <div class="rv-pd-divider"></div>
        <a href="<?= BASE_URL ?>auth/logout" class="rv-pd-item rv-pd-item--danger" role="menuitem">
          <span class="rv-pd-item-ico"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
          <span>Sign Out</span>
        </a>
      </div>
    </div>

  </div>
</nav>

<!-- ══════════════════════════════════
     HERO
══════════════════════════════════ -->
<header class="rv-hero" role="banner">
  <div class="rv-hero-overlay" aria-hidden="true"></div>
  <div class="rv-hero-inner">
    <div class="rv-hero-left">
      <p class="rv-hero-eyebrow">
        <span class="rv-dot" aria-hidden="true"></span>
        <?= $bizName ?> &nbsp;·&nbsp; Customer Feedback
      </p>
      <h1 class="rv-hero-title">My <em>Reviews</em></h1>
      <p class="rv-hero-sub">Reply to customer feedback and build trust with your audience.</p>
      <div class="rv-hero-badges">
        <span class="rv-badge-pill rv-badge-pill--gold">
          <i class="fa-solid fa-comments" style="font-size:.7rem"></i>
          <?= $totalReviews ?> Reviews Total
        </span>
        <?php if ($unanswered > 0): ?>
        <span class="rv-badge-pill rv-badge-pill--amber">
          <i class="fa-solid fa-clock" style="font-size:.7rem"></i>
          <?= $unanswered ?> Awaiting Reply
        </span>
        <?php endif; ?>
      </div>
    </div>
    <div class="rv-hero-right">
      <a href="<?= BASE_URL ?>provider/dashboard" class="rv-hero-btn rv-hero-btn--ghost">
        <i class="fa-solid fa-arrow-left" style="font-size:.75rem"></i>
        Dashboard
      </a>
      <a href="<?= BASE_URL ?>provider/appointments" class="rv-hero-btn rv-hero-btn--gold">
        <i class="fa-regular fa-calendar-check" style="font-size:.75rem"></i>
        Appointments
      </a>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════
     FLASH
══════════════════════════════════ -->
<?php if ($flash): ?>
<div class="rv-flash rv-flash--<?= $flash['type'] ?>" role="alert" id="rvFlash">
  <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
  <?= htmlspecialchars($flash['msg']) ?>
  <button class="rv-flash-close" onclick="document.getElementById('rvFlash').remove()">×</button>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════
     STATS ROW
══════════════════════════════════ -->
<section class="rv-stats-strip" aria-label="Review stats">
  <div class="rv-stats-inner">

    <div class="rv-stat-card rv-stat-card--score">
      <div class="rv-stat-score-big"><?= number_format($avgRating, 1) ?></div>
      <div class="rv-stat-score-meta">
        <div class="rv-stars rv-stars--lg" aria-label="<?= number_format($avgRating, 1) ?> out of 5">
          <?php for ($s = 1; $s <= 5; $s++): $f = starFillR($avgRating, $s); ?>
            <span class="rv-star rv-star--<?= $f ?>">
              <?= $f === 'full' ? '★' : ($f === 'half' ? '⯨' : '☆') ?>
            </span>
          <?php endfor; ?>
        </div>
        <span class="rv-stat-score-label">out of 5.0</span>
      </div>
    </div>

    <div class="rv-stat-divider" aria-hidden="true"></div>

    <div class="rv-stat-card">
      <div class="rv-stat-val"><?= $totalReviews ?></div>
      <div class="rv-stat-label">Total Reviews</div>
      <div class="rv-stat-sub">from customers</div>
    </div>

    <div class="rv-stat-divider" aria-hidden="true"></div>

    <div class="rv-stat-card">
      <div class="rv-stat-val rv-stat-val--green"><?= $repliedCount ?></div>
      <div class="rv-stat-label">Replied</div>
      <div class="rv-stat-sub"><?= $responseRate ?>% response rate</div>
    </div>

    <div class="rv-stat-divider" aria-hidden="true"></div>

    <div class="rv-stat-card">
      <div class="rv-stat-val <?= $unanswered > 0 ? 'rv-stat-val--amber' : '' ?>"><?= $unanswered ?></div>
      <div class="rv-stat-label">Unanswered</div>
      <div class="rv-stat-sub">awaiting your reply</div>
    </div>

  </div>
</section>

<!-- ══════════════════════════════════
     MAIN CONTENT
══════════════════════════════════ -->
<main class="rv-page" role="main">
  <div class="rv-layout">

    <!-- ── LEFT: Review Feed ── -->
    <div class="rv-feed-col">

      <!-- Filter bar -->
      <div class="rv-filter-bar" role="toolbar" aria-label="Filter by star rating">
        <a href="<?= BASE_URL ?>provider/reviews" class="rv-filter-chip <?= $filterStar === 0 ? 'is-active' : '' ?>">
          All
        </a>
        <?php for ($r = 5; $r >= 1; $r--): ?>
        <a href="<?= BASE_URL ?>provider/reviews?stars=<?= $r ?>" class="rv-filter-chip <?= $filterStar === $r ? 'is-active' : '' ?>">
          <?= str_repeat('★', $r) ?><?= str_repeat('☆', 5 - $r) ?>
        </a>
        <?php endfor; ?>
        <?php if ($totalReviews > 0): ?>
        <span class="rv-filter-count"><?= count($reviews) ?> showing</span>
        <?php endif; ?>
      </div>

      <!-- Review cards -->
      <?php if (empty($reviews)): ?>
      <div class="rv-empty">
        <div class="rv-empty-icon" aria-hidden="true"><i class="fa-regular fa-face-smile-beam"></i></div>
        <h3 class="rv-empty-title">No reviews yet<?= $filterStar ? " at $filterStar ★" : '' ?></h3>
        <p class="rv-empty-sub">
          <?= $filterStar ? 'Try a different star filter.' : 'Your reviews will appear here once customers start booking.' ?>
        </p>
        <?php if ($filterStar): ?>
        <a href="<?= BASE_URL ?>provider/reviews" class="rv-empty-btn">Clear filter</a>
        <?php endif; ?>
      </div>

      <?php else: foreach ($reviews as $idx => $rev):
        $hasReply    = !empty($rev['reply_text']);
        $stars       = (int)$rev['rating'];
        $customerInitials = strtoupper(substr($rev['customer_name'] ?? 'U', 0, 2));
      ?>

      <article class="rv-card <?= $hasReply ? 'rv-card--replied' : 'rv-card--pending' ?>"
               id="review-<?= (int)$rev['id'] ?>"
               style="animation-delay: <?= $idx * 0.04 ?>s">

        <!-- Card header -->
        <div class="rv-card-header">
          <div class="rv-card-avatar">
            <?php if (!empty($rev['customer_avatar'])): ?>
              <img src="<?= htmlspecialchars($rev['customer_avatar']) ?>" alt="<?= htmlspecialchars($rev['customer_name']) ?>">
            <?php else: ?>
              <?= $customerInitials ?>
            <?php endif; ?>
          </div>

          <div class="rv-card-meta">
            <div class="rv-card-name"><?= htmlspecialchars($rev['customer_name']) ?></div>
            <?php if (!empty($rev['service_name'])): ?>
            <div class="rv-card-service">
              <i class="fa-solid fa-scissors" style="font-size:.65rem;opacity:.6"></i>
              <?= htmlspecialchars($rev['service_name']) ?>
            </div>
            <?php endif; ?>
          </div>

          <div class="rv-card-right">
            <div class="rv-card-stars" aria-label="<?= $stars ?> out of 5 stars">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <span class="rv-star rv-star--<?= $s <= $stars ? 'full' : 'empty' ?>"><?= $s <= $stars ? '★' : '☆' ?></span>
              <?php endfor; ?>
            </div>
            <div class="rv-card-date"><?= date('j M Y', strtotime($rev['created_at'])) ?></div>
          </div>
        </div>

        <!-- Review body -->
        <div class="rv-card-body">
          <p class="rv-card-text"><?= htmlspecialchars($rev['review_text'] ?? '') ?></p>
        </div>

        <!-- Status chip -->
        <div class="rv-card-status-row">
          <?php if ($hasReply): ?>
            <span class="rv-status-chip rv-status-chip--replied">
              <i class="fa-solid fa-circle-check" style="font-size:.65rem"></i>
              Replied <?= timeAgo($rev['replied_at']) ?>
            </span>
          <?php else: ?>
            <span class="rv-status-chip rv-status-chip--pending">
              <i class="fa-regular fa-clock" style="font-size:.65rem"></i>
              Awaiting reply
            </span>
          <?php endif; ?>
        </div>

        <!-- Existing reply -->
        <?php if ($hasReply): ?>
        <div class="rv-reply-block">
          <div class="rv-reply-avatar"><?= $initials ?></div>
          <div class="rv-reply-content">
            <div class="rv-reply-header">
              <span class="rv-reply-name"><?= $bizName ?></span>
              <span class="rv-reply-tag">Your Reply</span>
            </div>
            <p class="rv-reply-text"><?= nl2br(htmlspecialchars($rev['reply_text'])) ?></p>
          </div>
        </div>
        <?php endif; ?>

        <!-- Reply form -->
        <div class="rv-reply-form-wrap <?= $hasReply ? 'rv-reply-form-wrap--hidden' : '' ?>" id="replyWrap-<?= (int)$rev['id'] ?>">
          <form method="POST" action="<?= BASE_URL ?>provider/reviews" class="rv-reply-form">
            <input type="hidden" name="reply_review_id" value="<?= (int)$rev['id'] ?>">
            <div class="rv-reply-input-row">
              <div class="rv-reply-form-avatar"><?= $initials ?></div>
              <textarea name="reply_text" class="rv-reply-textarea"
                        placeholder="Write a professional reply to this review…"
                        rows="2" maxlength="1000" required></textarea>
            </div>
            <div class="rv-reply-form-footer">
              <span class="rv-reply-char-count"><span class="rv-char-num">0</span> / 1000</span>
              <div class="rv-reply-form-actions">
                <?php if ($hasReply): ?>
                <button type="button" class="rv-btn-cancel" onclick="document.getElementById('replyWrap-<?= (int)$rev['id'] ?>').classList.add('rv-reply-form-wrap--hidden')">
                  Cancel
                </button>
                <?php endif; ?>
                <button type="submit" class="rv-btn-reply">
                  <i class="fa-solid fa-paper-plane" style="font-size:.75rem"></i>
                  Post Reply
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Toggle reply button (for already-replied) -->
        <?php if ($hasReply): ?>
        <div class="rv-card-footer">
          <button class="rv-btn-edit-reply" onclick="document.getElementById('replyWrap-<?= (int)$rev['id'] ?>').classList.toggle('rv-reply-form-wrap--hidden')">
            <i class="fa-solid fa-pen-to-square" style="font-size:.72rem"></i>
            Edit Reply
          </button>
        </div>
        <?php endif; ?>

      </article>

      <?php endforeach; endif; ?>
    </div>

    <!-- ── RIGHT: Assessment Panel ── -->
    <aside class="rv-panel-col" aria-label="Review assessment">

      <!-- Rating breakdown card -->
      <div class="rv-panel-card rv-panel-card--breakdown">
        <div class="rv-panel-header">
          <div class="rv-panel-accent" aria-hidden="true"></div>
          <h2 class="rv-panel-title">Assessment Reviews</h2>
        </div>

        <div class="rv-panel-score">
          <div class="rv-panel-big"><?= number_format($avgRating, 1) ?></div>
          <div class="rv-panel-score-right">
            <div class="rv-stars rv-stars--md">
              <?php for ($s = 1; $s <= 5; $s++): $f = starFillR($avgRating, $s); ?>
                <span class="rv-star rv-star--<?= $f ?>"><?= $f === 'full' ? '★' : ($f === 'half' ? '⯨' : '☆') ?></span>
              <?php endfor; ?>
            </div>
            <span class="rv-panel-review-count"><?= $totalReviews ?> Review<?= $totalReviews !== 1 ? 's' : '' ?></span>
          </div>
        </div>

        <div class="rv-breakdown-bars">
          <?php for ($r = 5; $r >= 1; $r--):
            $cnt = $breakdown[$r] ?? 0;
            $pct = $totalReviews > 0 ? round($cnt / $totalReviews * 100) : 0;
          ?>
          <a href="<?= BASE_URL ?>provider/reviews?stars=<?= $r ?>"
             class="rv-breakdown-row <?= $filterStar === $r ? 'is-active' : '' ?>"
             title="Filter <?= $r ?>-star reviews">
            <span class="rv-breakdown-label">
              <span class="rv-star rv-star--full">★</span><?= $r ?>
            </span>
            <div class="rv-breakdown-track">
              <div class="rv-breakdown-fill" style="width:<?= $pct ?>%" data-pct="<?= $pct ?>"></div>
            </div>
            <span class="rv-breakdown-pct"><?= $pct ?>%</span>
            <span class="rv-breakdown-cnt"><?= $cnt ?></span>
          </a>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Response rate card -->
      <div class="rv-panel-card">
        <div class="rv-panel-header">
          <div class="rv-panel-accent" aria-hidden="true"></div>
          <h2 class="rv-panel-title">Response Rate</h2>
        </div>

        <div class="rv-rate-ring-wrap" aria-label="<?= $responseRate ?>% response rate">
          <svg class="rv-ring" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <circle class="rv-ring-bg" cx="50" cy="50" r="38" fill="none" stroke-width="7"/>
            <circle class="rv-ring-fill" cx="50" cy="50" r="38" fill="none" stroke-width="7"
                    stroke-dasharray="<?= round(2 * M_PI * 38 * $responseRate / 100, 1) ?> 999"
                    stroke-linecap="round"/>
          </svg>
          <div class="rv-ring-label">
            <div class="rv-ring-pct"><?= $responseRate ?>%</div>
            <div class="rv-ring-sub">Response</div>
          </div>
        </div>

        <div class="rv-rate-stats">
          <div class="rv-rate-stat">
            <span class="rv-rate-stat-val rv-rate-stat-val--green"><?= $repliedCount ?></span>
            <span class="rv-rate-stat-label">Replied</span>
          </div>
          <div class="rv-rate-divider"></div>
          <div class="rv-rate-stat">
            <span class="rv-rate-stat-val <?= $unanswered > 0 ? 'rv-rate-stat-val--amber' : '' ?>"><?= $unanswered ?></span>
            <span class="rv-rate-stat-label">Pending</span>
          </div>
          <div class="rv-rate-divider"></div>
          <div class="rv-rate-stat">
            <span class="rv-rate-stat-val"><?= $totalReviews ?></span>
            <span class="rv-rate-stat-label">Total</span>
          </div>
        </div>
      </div>

      <!-- Quick tip card -->
      <div class="rv-panel-card rv-panel-card--tip">
        <div class="rv-tip-icon" aria-hidden="true"><i class="fa-solid fa-lightbulb"></i></div>
        <h3 class="rv-tip-title">Pro Tip</h3>
        <p class="rv-tip-body">
          Responding to reviews within 24 hours increases re-booking rates by up to 35%.
          Always keep replies professional and warm.
        </p>
      </div>

    </aside>

  </div>
</main>

<!-- ══════════════════════════════════
     SCRIPTS
══════════════════════════════════ -->
<script>
/* ── THEME TOGGLE ── */
(function () {
  var btn  = document.getElementById('themeToggle');
  var moon = document.querySelector('.icon-moon');
  var sun  = document.querySelector('.icon-sun');

  function applyTheme(t) {
    if (t === 'light') {
      document.documentElement.removeAttribute('data-theme');
      if (moon) moon.style.display = 'none';
      if (sun)  sun.style.display  = 'block';
    } else {
      document.documentElement.setAttribute('data-theme','dark');
      if (moon) moon.style.display = 'block';
      if (sun)  sun.style.display  = 'none';
    }
  }

  applyTheme(localStorage.getItem('qb-theme') || 'light');

  if (btn) btn.addEventListener('click', function () {
    var cur  = document.documentElement.getAttribute('data-theme');
    var next = cur === 'dark' ? 'light' : 'dark';
    localStorage.setItem('qb-theme', next);
    applyTheme(next);
  });
})();

/* ── PROFILE DROPDOWN ── */
(function () {
  var trigger  = document.getElementById('profileTrigger');
  var dropdown = document.getElementById('profileDropdown');
  if (!trigger || !dropdown) return;

  function open()  { trigger.classList.add('is-open');    dropdown.classList.add('is-open');    trigger.setAttribute('aria-expanded','true');  }
  function close() { trigger.classList.remove('is-open'); dropdown.classList.remove('is-open'); trigger.setAttribute('aria-expanded','false'); }

  trigger.addEventListener('click', function(e){ e.stopPropagation(); dropdown.classList.contains('is-open') ? close() : open(); });
  document.addEventListener('click', function(e){ if(!dropdown.contains(e.target)&&!trigger.contains(e.target)) close(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
})();

/* ── TEXTAREA CHAR COUNT ── */
document.querySelectorAll('.rv-reply-textarea').forEach(function(ta) {
  var counter = ta.closest('.rv-reply-form').querySelector('.rv-char-num');
  ta.addEventListener('input', function () {
    if (counter) counter.textContent = ta.value.length;
  });
});

/* ── ANIMATED BREAKDOWN BARS ── */
(function () {
  var fills = document.querySelectorAll('.rv-breakdown-fill');
  if (!fills.length) return;
  var io = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (e.isIntersecting) {
        e.target.style.width = e.target.dataset.pct + '%';
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.2 });
  fills.forEach(function(f) { f.style.width = '0'; io.observe(f); });
})();
</script>

</body>
</html>