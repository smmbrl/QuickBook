<?php
// app/views/admin/feedback.php
// Admin Feedback & Reviews Hub — Cream & Gold Design System
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../../config/database.php';
$db = Database::getInstance();

/* ══════════════════════════════════════════════════════════
   ENSURE TABLES EXIST
══════════════════════════════════════════════════════════ */
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS tbl_review_replies (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            review_id   INT UNSIGNED NOT NULL,
            provider_id INT UNSIGNED NOT NULL,
            reply       TEXT         NOT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_review_reply (review_id),
            INDEX idx_provider (provider_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS tbl_app_feedback (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NOT NULL,
            user_role   ENUM('customer','provider','admin') NOT NULL DEFAULT 'customer',
            type        ENUM('complaint','compliment','suggestion','bug_report') NOT NULL DEFAULT 'suggestion',
            subject     VARCHAR(200) NOT NULL DEFAULT '',
            message     TEXT NOT NULL,
            rating      TINYINT UNSIGNED DEFAULT NULL,
            status      ENUM('open','reviewed','resolved','dismissed') NOT NULL DEFAULT 'open',
            admin_note  TEXT DEFAULT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_type   (type),
            INDEX idx_user   (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) { /* silently skip */ }

/* ══════════════════════════════════════════════════════════
   TAB STATE
══════════════════════════════════════════════════════════ */
$tab = in_array($_GET['tab'] ?? '', ['reviews','feedback','replies']) ? $_GET['tab'] : 'reviews';

/* ══════════════════════════════════════════════════════════
   TAB 1 — CUSTOMER REVIEWS ON PROVIDERS
══════════════════════════════════════════════════════════ */
$filterStar   = isset($_GET['stars']) ? (int)$_GET['stars'] : 0;
$filterStar   = ($filterStar >= 1 && $filterStar <= 5) ? $filterStar : 0;
$filterStatus = $_GET['rstatus'] ?? 'all'; // all | replied | pending | hidden

$starWhere   = $filterStar ? " AND r.rating = $filterStar" : '';
$statusWhere = match($filterStatus) {
    'replied' => " AND rr.id IS NOT NULL",
    'pending' => " AND rr.id IS NULL AND r.is_visible = 1",
    'hidden'  => " AND r.is_visible = 0",
    default   => ''
};

$reviews = $db->query("
    SELECT r.*,
           r.comment AS review_text,
           CONCAT(cu.first_name,' ',cu.last_name) AS customer_name,
           cu.avatar_url AS customer_avatar,
           CONCAT(pu.first_name,' ',pu.last_name) AS provider_name,
           COALESCE(pp.business_name, CONCAT(pu.first_name,' ',pu.last_name)) AS business_name,
           s.name AS service_name,
           rr.reply AS reply_text,
           rr.created_at AS replied_at,
           rr.id AS reply_id
    FROM tbl_reviews r
    JOIN tbl_users cu ON r.customer_id = cu.id
    JOIN tbl_provider_profiles pp ON r.provider_id = pp.id
    JOIN tbl_users pu ON pp.user_id = pu.id
    LEFT JOIN tbl_services s ON r.service_id = s.id
    LEFT JOIN tbl_review_replies rr ON rr.review_id = r.id
    WHERE 1=1 $starWhere $statusWhere
    ORDER BY r.created_at DESC
    LIMIT 200
")->fetchAll();

// Review stats
$rstats = $db->query("
    SELECT
        COUNT(*)                                            AS total,
        ROUND(AVG(r.rating),1)                             AS avg_rating,
        SUM(r.is_visible = 1 AND rr.id IS NOT NULL)        AS replied,
        SUM(r.is_visible = 1 AND rr.id IS NULL)            AS pending,
        SUM(r.is_visible = 0)                              AS hidden,
        SUM(r.rating >= 4)                                 AS positive,
        SUM(r.rating <= 2)                                 AS negative
    FROM tbl_reviews r
    LEFT JOIN tbl_review_replies rr ON rr.review_id = r.id
")->fetch();

/* ══════════════════════════════════════════════════════════
   TAB 2 — APP FEEDBACK (COMPLAINTS / COMPLIMENTS)
══════════════════════════════════════════════════════════ */
$fbFilter = $_GET['fbtype'] ?? 'all';
$fbStatus = $_GET['fbstatus'] ?? 'all';

$fbTypeWhere   = ($fbFilter !== 'all') ? " AND f.type = " . $db->quote($fbFilter) : '';
$fbStatusWhere = ($fbStatus !== 'all') ? " AND f.status = " . $db->quote($fbStatus) : '';

$feedbacks = $db->query("
    SELECT f.*,
           CONCAT(u.first_name,' ',u.last_name) AS user_name,
           u.email AS user_email,
           u.role  AS user_role
    FROM tbl_app_feedback f
    JOIN tbl_users u ON f.user_id = u.id
    WHERE 1=1 $fbTypeWhere $fbStatusWhere
    ORDER BY f.created_at DESC
    LIMIT 200
")->fetchAll();

$fbStats = $db->query("
    SELECT
        COUNT(*)                              AS total,
        SUM(type='complaint')                 AS complaints,
        SUM(type='compliment')                AS compliments,
        SUM(type='suggestion')                AS suggestions,
        SUM(type='bug_report')                AS bugs,
        SUM(status='open')                    AS open_count,
        SUM(status='resolved')                AS resolved
    FROM tbl_app_feedback
")->fetch();

/* ══════════════════════════════════════════════════════════
   TAB 3 — PROVIDER REPLY MANAGEMENT
══════════════════════════════════════════════════════════ */
$replyFilter = $_GET['rprov'] ?? '';

$replyProvWhere = '';
if ($replyFilter) {
    $replyProvWhere = " AND pp.id = " . (int)$replyFilter;
}

$providerReplies = $db->query("
    SELECT rr.*,
           r.comment AS review_text,
           r.rating,
           r.created_at AS review_date,
           CONCAT(cu.first_name,' ',cu.last_name) AS customer_name,
           CONCAT(pu.first_name,' ',pu.last_name) AS provider_name,
           COALESCE(pp.business_name, CONCAT(pu.first_name,' ',pu.last_name)) AS business_name,
           pp.id AS provider_profile_id
    FROM tbl_review_replies rr
    JOIN tbl_reviews r ON rr.review_id = r.id
    JOIN tbl_users cu ON r.customer_id = cu.id
    JOIN tbl_provider_profiles pp ON rr.provider_id = pp.id
    JOIN tbl_users pu ON pp.user_id = pu.id
    WHERE 1=1 $replyProvWhere
    ORDER BY rr.created_at DESC
    LIMIT 200
")->fetchAll();

// Providers list for filter dropdown
$providersList = $db->query("
    SELECT pp.id, COALESCE(pp.business_name, CONCAT(u.first_name,' ',u.last_name)) AS name
    FROM tbl_provider_profiles pp
    JOIN tbl_users u ON pp.user_id = u.id
    ORDER BY name
")->fetchAll();

$replyStats = $db->query("
    SELECT COUNT(*) AS total_replies,
           COUNT(DISTINCT rr.provider_id) AS providers_replied,
           COUNT(DISTINCT rr.review_id) AS reviews_answered
    FROM tbl_review_replies rr
")->fetch();

/* ══════════════════════════════════════════════════════════
   FLASH
══════════════════════════════════════════════════════════ */
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

/* ══════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════ */
function fbTimeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($dt));
}
function fbTypeIcon(string $t): string {
    return match($t) {
        'complaint'   => 'fa-triangle-exclamation',
        'compliment'  => 'fa-heart',
        'suggestion'  => 'fa-lightbulb',
        'bug_report'  => 'fa-bug',
        default       => 'fa-comment'
    };
}
function fbTypeColor(string $t): string {
    return match($t) {
        'complaint'   => 'red',
        'compliment'  => 'green',
        'suggestion'  => 'blue',
        'bug_report'  => 'amber',
        default       => 'gold'
    };
}
function fbStatusColor(string $s): string {
    return match($s) {
        'open'      => 'amber',
        'reviewed'  => 'blue',
        'resolved'  => 'green',
        'dismissed' => 'dim',
        default     => 'gold'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Feedback & Reviews — QuickBook Admin</title>
<script>(function(){ var t=localStorage.getItem('qb-admin-theme')||'light'; document.documentElement.setAttribute('data-theme',t); })();</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_feedback.css">
</head>
<body>
<div class="grain"></div>
<?php require_once __DIR__ . '/_nav.php'; adminNav('feedback'); ?>

<div class="fb-page">

  <!-- Hero -->
  <div class="fb-hero anim-1">
    <div class="fb-eyebrow"><span class="fb-eyebrow-dot"></span>Platform Intelligence</div>
    <h1>Feedback <em>&amp; Reviews</em></h1>
    <p>Monitor customer reviews, manage provider replies, and track app feedback</p>
  </div>

  <!-- Flash -->
  <?php if ($flash): ?>
  <div class="fb-flash fb-flash--<?= $flash['type'] ?>" id="fbFlash">
    <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
    <span><?= htmlspecialchars($flash['msg']) ?></span>
    <button class="fb-flash-close" onclick="this.parentElement.remove()">×</button>
  </div>
  <?php endif; ?>

  <!-- KPI Strip -->
  <div class="fb-kpis anim-2">
    <div class="fb-kpi fb-kpi--gold">
      <div class="fb-kpi-val"><?= number_format((float)($rstats['avg_rating'] ?? 0), 1) ?></div>
      <div class="fb-kpi-stars">
        <?php $avg = (float)($rstats['avg_rating'] ?? 0); for ($s=1;$s<=5;$s++): ?>
          <span class="fb-star <?= $s <= round($avg) ? 'fb-star--on' : '' ?>">★</span>
        <?php endfor; ?>
      </div>
      <div class="fb-kpi-label">Avg. Rating</div>
    </div>
    <div class="fb-kpi fb-kpi--green">
      <div class="fb-kpi-val"><?= (int)($rstats['positive'] ?? 0) ?></div>
      <div class="fb-kpi-label">Positive Reviews</div>
    </div>
    <div class="fb-kpi fb-kpi--red">
      <div class="fb-kpi-val"><?= (int)($rstats['negative'] ?? 0) ?></div>
      <div class="fb-kpi-label">Negative Reviews</div>
    </div>
    <div class="fb-kpi fb-kpi--amber">
      <div class="fb-kpi-val"><?= (int)($rstats['pending'] ?? 0) ?></div>
      <div class="fb-kpi-label">Awaiting Reply</div>
    </div>
    <div class="fb-kpi fb-kpi--blue">
      <div class="fb-kpi-val"><?= (int)($fbStats['open_count'] ?? 0) ?></div>
      <div class="fb-kpi-label">Open Feedback</div>
    </div>
  </div>

  <!-- Tab Nav -->
  <div class="fb-panel anim-3">

    <div class="fb-tab-nav">
      <a href="?tab=reviews"
         class="fb-tab <?= $tab === 'reviews'  ? 'is-active' : '' ?>">
        <i class="fa-solid fa-star"></i> Customer Reviews
        <span class="fb-tab-badge"><?= (int)($rstats['total'] ?? 0) ?></span>
      </a>
      <a href="?tab=feedback"
         class="fb-tab <?= $tab === 'feedback' ? 'is-active' : '' ?>">
        <i class="fa-solid fa-comments"></i> App Feedback
        <span class="fb-tab-badge fb-tab-badge--amber"><?= (int)($fbStats['open_count'] ?? 0) ?></span>
      </a>
      <a href="?tab=replies"
         class="fb-tab <?= $tab === 'replies'  ? 'is-active' : '' ?>">
        <i class="fa-solid fa-reply-all"></i> Provider Replies
        <span class="fb-tab-badge fb-tab-badge--blue"><?= (int)($replyStats['total_replies'] ?? 0) ?></span>
      </a>
    </div>

    <!-- ════════════════════════════════════
         TAB 1 — CUSTOMER REVIEWS
    ════════════════════════════════════ -->
    <?php if ($tab === 'reviews'): ?>
    <div class="fb-tab-panel">

      <!-- Filters -->
      <div class="fb-filter-bar">
        <div class="fb-filter-group">
          <?php foreach ([0=>'All', 5=>'★ 5', 4=>'★ 4', 3=>'★ 3', 2=>'★ 2', 1=>'★ 1'] as $sv => $sl): ?>
          <a href="?tab=reviews&stars=<?= $sv ?>&rstatus=<?= $filterStatus ?>"
             class="fb-fbtn <?= $filterStar === $sv ? 'is-active' : '' ?>"><?= $sl ?></a>
          <?php endforeach; ?>
        </div>
        <div class="fb-filter-group">
          <?php foreach (['all'=>'All Status','replied'=>'Replied','pending'=>'Pending','hidden'=>'Hidden'] as $sv => $sl): ?>
          <a href="?tab=reviews&stars=<?= $filterStar ?>&rstatus=<?= $sv ?>"
             class="fb-fbtn <?= $filterStatus === $sv ? 'is-active' : '' ?>"><?= $sl ?></a>
          <?php endforeach; ?>
        </div>
        <div class="fb-filter-count"><?= count($reviews) ?> review<?= count($reviews) !== 1 ? 's' : '' ?></div>
      </div>

      <!-- Reviews list -->
      <?php if (empty($reviews)): ?>
      <div class="fb-empty">
        <div class="fb-empty-icon"><i class="fa-regular fa-face-smile-beam"></i></div>
        <p>No reviews match your filters.</p>
      </div>
      <?php else: ?>
      <div class="fb-review-list">
      <?php foreach ($reviews as $rev):
        $stars   = (int)$rev['rating'];
        $replied = !empty($rev['reply_text']);
        $visible = (bool)$rev['is_visible'];
        $custInit = strtoupper(substr($rev['customer_name'] ?? 'U', 0, 2));
      ?>
        <div class="fb-review-card <?= !$visible ? 'fb-review-card--hidden' : ($replied ? 'fb-review-card--replied' : '') ?>"
             id="rev-<?= (int)$rev['id'] ?>">

          <!-- Header row -->
          <div class="fb-rv-header">
            <div class="fb-rv-av">
              <?php if (!empty($rev['customer_avatar'])): ?>
                <img src="<?= htmlspecialchars($rev['customer_avatar']) ?>" alt="">
              <?php else: ?>
                <?= $custInit ?>
              <?php endif; ?>
            </div>
            <div class="fb-rv-meta">
              <div class="fb-rv-name"><?= htmlspecialchars($rev['customer_name']) ?></div>
              <div class="fb-rv-sub">
                booked <strong><?= htmlspecialchars($rev['business_name']) ?></strong>
                <?php if (!empty($rev['service_name'])): ?>
                  · <?= htmlspecialchars($rev['service_name']) ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="fb-rv-right">
              <div class="fb-rv-stars">
                <?php for ($s=1;$s<=5;$s++): ?>
                  <span class="fb-star <?= $s <= $stars ? 'fb-star--on' : '' ?>">★</span>
                <?php endfor; ?>
              </div>
              <div class="fb-rv-date"><?= fbTimeAgo($rev['created_at']) ?></div>
            </div>
          </div>

          <!-- Review text -->
          <div class="fb-rv-text"><?= nl2br(htmlspecialchars($rev['review_text'] ?? '')) ?></div>

          <!-- Status chips -->
          <div class="fb-rv-chips">
            <?php if (!$visible): ?>
              <span class="fb-chip fb-chip--red"><i class="fa-solid fa-eye-slash"></i> Hidden</span>
            <?php elseif ($replied): ?>
              <span class="fb-chip fb-chip--green"><i class="fa-solid fa-circle-check"></i> Replied · <?= fbTimeAgo($rev['replied_at']) ?></span>
            <?php else: ?>
              <span class="fb-chip fb-chip--amber"><i class="fa-regular fa-clock"></i> Awaiting reply</span>
            <?php endif; ?>
          </div>

          <!-- Existing provider reply -->
          <?php if ($replied): ?>
          <div class="fb-rv-reply">
            <div class="fb-rv-reply-label"><i class="fa-solid fa-reply"></i> Provider Reply</div>
            <div class="fb-rv-reply-text"><?= nl2br(htmlspecialchars($rev['reply_text'])) ?></div>
            <!-- Admin can delete a provider reply -->
            <form method="POST" action="<?= BASE_URL ?>admin/feedback/reply/delete/<?= (int)$rev['reply_id'] ?>"
                  style="margin-top:.5rem"
                  onsubmit="return confirm('Delete this provider reply?')">
              <button type="submit" class="fb-btn-sm fb-btn-sm--danger">
                <i class="fa-solid fa-trash"></i> Remove Reply
              </button>
            </form>
          </div>
          <?php endif; ?>

          <!-- Admin actions -->
          <div class="fb-rv-actions">
            <form method="POST" action="<?= BASE_URL ?>admin/feedback/review/toggle/<?= (int)$rev['id'] ?>">
              <button type="submit" class="fb-btn-sm <?= $visible ? 'fb-btn-sm--outline' : 'fb-btn-sm--green' ?>">
                <i class="fa-solid <?= $visible ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                <?= $visible ? 'Hide Review' : 'Restore Review' ?>
              </button>
            </form>
          </div>

        </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════
         TAB 2 — APP FEEDBACK
    ════════════════════════════════════ -->
    <?php elseif ($tab === 'feedback'): ?>
    <div class="fb-tab-panel">

      <!-- Feedback type stats -->
      <div class="fb-fb-stats">
        <?php
        $fbStatRows = [
          ['complaint',  'Complaints',  (int)($fbStats['complaints']  ?? 0), 'red'],
          ['compliment', 'Compliments', (int)($fbStats['compliments'] ?? 0), 'green'],
          ['suggestion', 'Suggestions', (int)($fbStats['suggestions'] ?? 0), 'blue'],
          ['bug_report', 'Bug Reports', (int)($fbStats['bugs']        ?? 0), 'amber'],
        ];
        foreach ($fbStatRows as [$type, $label, $count, $color]):
        ?>
        <a href="?tab=feedback&fbtype=<?= $type ?>&fbstatus=<?= $fbStatus ?>"
           class="fb-fb-stat <?= $fbFilter === $type ? 'is-active' : '' ?> fb-fb-stat--<?= $color ?>">
          <i class="fa-solid <?= fbTypeIcon($type) ?>"></i>
          <div class="fb-fb-stat-count"><?= $count ?></div>
          <div class="fb-fb-stat-label"><?= $label ?></div>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Status filter -->
      <div class="fb-filter-bar">
        <div class="fb-filter-group">
          <?php foreach (['all'=>'All Types','complaint'=>'Complaints','compliment'=>'Compliments','suggestion'=>'Suggestions','bug_report'=>'Bug Reports'] as $sv => $sl): ?>
          <a href="?tab=feedback&fbtype=<?= $sv ?>&fbstatus=<?= $fbStatus ?>"
             class="fb-fbtn <?= $fbFilter === $sv ? 'is-active' : '' ?>"><?= $sl ?></a>
          <?php endforeach; ?>
        </div>
        <div class="fb-filter-group">
          <?php foreach (['all'=>'All Status','open'=>'Open','reviewed'=>'Reviewed','resolved'=>'Resolved','dismissed'=>'Dismissed'] as $sv => $sl): ?>
          <a href="?tab=feedback&fbtype=<?= $fbFilter ?>&fbstatus=<?= $sv ?>"
             class="fb-fbtn <?= $fbStatus === $sv ? 'is-active' : '' ?>"><?= $sl ?></a>
          <?php endforeach; ?>
        </div>
        <div class="fb-filter-count"><?= count($feedbacks) ?> item<?= count($feedbacks) !== 1 ? 's' : '' ?></div>
      </div>

      <!-- Feedback list -->
      <?php if (empty($feedbacks)): ?>
      <div class="fb-empty">
        <div class="fb-empty-icon"><i class="fa-solid fa-inbox"></i></div>
        <p>No feedback items match your filters.</p>
      </div>
      <?php else: ?>
      <div class="fb-feedback-list">
      <?php foreach ($feedbacks as $fb):
        $color = fbTypeColor($fb['type']);
        $icon  = fbTypeIcon($fb['type']);
      ?>
        <div class="fb-feedback-card fb-feedback-card--<?= $color ?>" id="fb-<?= (int)$fb['id'] ?>">

          <div class="fb-fb-header">
            <div class="fb-fb-type fb-fb-type--<?= $color ?>">
              <i class="fa-solid <?= $icon ?>"></i>
              <?= ucfirst(str_replace('_', ' ', $fb['type'])) ?>
            </div>
            <div class="fb-fb-meta">
              <span class="fb-fb-user"><?= htmlspecialchars($fb['user_name']) ?></span>
              <span class="fb-fb-role fb-fb-role--<?= $fb['user_role'] ?>"><?= ucfirst($fb['user_role']) ?></span>
              <span class="fb-fb-email"><?= htmlspecialchars($fb['user_email']) ?></span>
            </div>
            <div class="fb-fb-right">
              <span class="fb-chip fb-chip--<?= fbStatusColor($fb['status']) ?>">
                <?= ucfirst($fb['status']) ?>
              </span>
              <div class="fb-rv-date"><?= fbTimeAgo($fb['created_at']) ?></div>
            </div>
          </div>

          <?php if (!empty($fb['subject'])): ?>
          <div class="fb-fb-subject"><?= htmlspecialchars($fb['subject']) ?></div>
          <?php endif; ?>

          <?php if (!is_null($fb['rating'])): ?>
          <div class="fb-rv-stars" style="margin:.35rem 0">
            <?php for ($s=1;$s<=5;$s++): ?>
              <span class="fb-star <?= $s <= (int)$fb['rating'] ? 'fb-star--on' : '' ?>">★</span>
            <?php endfor; ?>
          </div>
          <?php endif; ?>

          <div class="fb-fb-message"><?= nl2br(htmlspecialchars($fb['message'])) ?></div>

          <!-- Admin note -->
          <?php if (!empty($fb['admin_note'])): ?>
          <div class="fb-fb-admin-note">
            <i class="fa-solid fa-shield-halved"></i>
            <span><?= nl2br(htmlspecialchars($fb['admin_note'])) ?></span>
          </div>
          <?php endif; ?>

          <!-- Admin actions -->
          <div class="fb-rv-actions" style="flex-wrap:wrap;gap:.5rem">
            <!-- Change status -->
            <form method="POST" action="<?= BASE_URL ?>admin/feedback/update/<?= (int)$fb['id'] ?>" style="display:flex;gap:.4rem;align-items:center">
              <select name="status" class="fb-select-sm">
                <?php foreach (['open','reviewed','resolved','dismissed'] as $st): ?>
                  <option value="<?= $st ?>" <?= $fb['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="fb-btn-sm fb-btn-sm--outline">Update</button>
            </form>
            <!-- Add/edit note -->
            <button class="fb-btn-sm fb-btn-sm--blue"
                    onclick="toggleNote(<?= (int)$fb['id'] ?>)">
              <i class="fa-solid fa-note-sticky"></i>
              <?= empty($fb['admin_note']) ? 'Add Note' : 'Edit Note' ?>
            </button>
            <!-- Delete -->
            <form method="POST" action="<?= BASE_URL ?>admin/feedback/delete/<?= (int)$fb['id'] ?>"
                  onsubmit="return confirm('Delete this feedback permanently?')">
              <button type="submit" class="fb-btn-sm fb-btn-sm--danger">
                <i class="fa-solid fa-trash"></i>
              </button>
            </form>
          </div>

          <!-- Note form (hidden by default) -->
          <div class="fb-note-form" id="noteForm-<?= (int)$fb['id'] ?>" style="display:none">
            <form method="POST" action="<?= BASE_URL ?>admin/feedback/note/<?= (int)$fb['id'] ?>">
              <textarea name="admin_note" class="fb-textarea" rows="3"
                        placeholder="Internal admin note (not visible to user)…"><?= htmlspecialchars($fb['admin_note'] ?? '') ?></textarea>
              <div style="display:flex;gap:.5rem;margin-top:.5rem">
                <button type="submit" class="fb-btn-sm fb-btn-sm--blue">Save Note</button>
                <button type="button" class="fb-btn-sm fb-btn-sm--outline"
                        onclick="toggleNote(<?= (int)$fb['id'] ?>)">Cancel</button>
              </div>
            </form>
          </div>

        </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>

    <!-- ════════════════════════════════════
         TAB 3 — PROVIDER REPLIES
    ════════════════════════════════════ -->
    <?php elseif ($tab === 'replies'): ?>
    <div class="fb-tab-panel">

      <!-- Stats strip -->
      <div class="fb-reply-stats">
        <div class="fb-rs-card">
          <div class="fb-rs-val"><?= (int)($replyStats['total_replies'] ?? 0) ?></div>
          <div class="fb-rs-label">Total Replies</div>
        </div>
        <div class="fb-rs-card">
          <div class="fb-rs-val fb-rs-val--gold"><?= (int)($replyStats['providers_replied'] ?? 0) ?></div>
          <div class="fb-rs-label">Providers Active</div>
        </div>
        <div class="fb-rs-card">
          <div class="fb-rs-val fb-rs-val--blue"><?= (int)($replyStats['reviews_answered'] ?? 0) ?></div>
          <div class="fb-rs-label">Reviews Answered</div>
        </div>
        <div class="fb-rs-card">
          <div class="fb-rs-val fb-rs-val--amber"><?= (int)($rstats['pending'] ?? 0) ?></div>
          <div class="fb-rs-label">Still Pending</div>
        </div>
      </div>

      <!-- Provider filter -->
      <div class="fb-filter-bar">
        <div class="fb-filter-group">
          <a href="?tab=replies" class="fb-fbtn <?= !$replyFilter ? 'is-active' : '' ?>">All Providers</a>
          <?php foreach ($providersList as $pl): ?>
          <a href="?tab=replies&rprov=<?= $pl['id'] ?>"
             class="fb-fbtn <?= (string)$replyFilter === (string)$pl['id'] ? 'is-active' : '' ?>">
            <?= htmlspecialchars($pl['name']) ?>
          </a>
          <?php endforeach; ?>
        </div>
        <div class="fb-filter-count"><?= count($providerReplies) ?> repl<?= count($providerReplies) !== 1 ? 'ies' : 'y' ?></div>
      </div>

      <!-- Replies list -->
      <?php if (empty($providerReplies)): ?>
      <div class="fb-empty">
        <div class="fb-empty-icon"><i class="fa-solid fa-reply-all"></i></div>
        <p>No provider replies found.</p>
      </div>
      <?php else: ?>
      <div class="fb-reply-list">
      <?php foreach ($providerReplies as $pr):
        $stars = (int)$pr['rating'];
      ?>
        <div class="fb-reply-card" id="rply-<?= (int)$pr['id'] ?>">

          <div class="fb-rply-header">
            <div class="fb-rply-provider">
              <div class="fb-rply-av"><?= strtoupper(substr($pr['business_name'], 0, 2)) ?></div>
              <div>
                <div class="fb-rply-biz"><?= htmlspecialchars($pr['business_name']) ?></div>
                <div class="fb-rply-sub">replied to <?= htmlspecialchars($pr['customer_name']) ?></div>
              </div>
            </div>
            <div class="fb-rv-right">
              <div class="fb-rv-stars">
                <?php for ($s=1;$s<=5;$s++): ?>
                  <span class="fb-star <?= $s <= $stars ? 'fb-star--on' : '' ?>">★</span>
                <?php endfor; ?>
              </div>
              <div class="fb-rv-date"><?= fbTimeAgo($pr['created_at']) ?></div>
            </div>
          </div>

          <!-- Original review -->
          <div class="fb-rply-original">
            <div class="fb-rply-original-label"><i class="fa-solid fa-quote-left"></i> Customer Review</div>
            <div class="fb-rply-original-text"><?= nl2br(htmlspecialchars($pr['review_text'] ?? '')) ?></div>
          </div>

          <!-- Provider reply -->
          <div class="fb-rply-reply">
            <div class="fb-rply-reply-label"><i class="fa-solid fa-reply"></i> Provider Reply</div>
            <div class="fb-rply-reply-text"><?= nl2br(htmlspecialchars($pr['reply'])) ?></div>
          </div>

          <!-- Admin actions -->
          <div class="fb-rv-actions">
            <!-- Edit reply -->
            <button class="fb-btn-sm fb-btn-sm--outline"
                    onclick="toggleEditReply(<?= (int)$pr['id'] ?>)">
              <i class="fa-solid fa-pen-to-square"></i> Edit Reply
            </button>
            <!-- Delete reply -->
            <form method="POST"
                  action="<?= BASE_URL ?>admin/feedback/reply/delete/<?= (int)$pr['id'] ?>"
                  onsubmit="return confirm('Delete this provider reply?')">
              <button type="submit" class="fb-btn-sm fb-btn-sm--danger">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </form>
          </div>

          <!-- Edit reply form -->
          <div class="fb-note-form" id="editReply-<?= (int)$pr['id'] ?>" style="display:none">
            <form method="POST" action="<?= BASE_URL ?>admin/feedback/reply/edit/<?= (int)$pr['id'] ?>">
              <textarea name="reply" class="fb-textarea" rows="3"
                        maxlength="600"><?= htmlspecialchars($pr['reply']) ?></textarea>
              <div style="display:flex;gap:.5rem;margin-top:.5rem">
                <button type="submit" class="fb-btn-sm fb-btn-sm--blue">Save Changes</button>
                <button type="button" class="fb-btn-sm fb-btn-sm--outline"
                        onclick="toggleEditReply(<?= (int)$pr['id'] ?>)">Cancel</button>
              </div>
            </form>
          </div>

        </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
    <?php endif; ?>

  </div><!-- /fb-panel -->

</div><!-- /fb-page -->

<script>
function toggleNote(id) {
  const el = document.getElementById('noteForm-' + id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function toggleEditReply(id) {
  const el = document.getElementById('editReply-' + id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
// Auto-dismiss flash
(function(){
  const f = document.getElementById('fbFlash');
  if (f) setTimeout(() => f.remove(), 4000);
})();
</script>
</body>
</html>