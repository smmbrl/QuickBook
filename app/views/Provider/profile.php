<?php
// app/views/Provider/profile.php
// Provider's unified profile + account management page (merged with Settings)

require_once __DIR__ . '/../../../config/database.php';
$db           = Database::getInstance();
$userId       = (int)($_SESSION['user_id'] ?? 0);
$providerName = htmlspecialchars($_SESSION['user_name'] ?? 'Provider');
$initials     = strtoupper(substr($providerName, 0, 2));

// ── Fetch provider profile ────────────────────────────────────
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
$firstName    = htmlspecialchars($profile['first_name'] ?? explode(' ', $providerName)[0]);
$provFullName = htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: $providerName);
$bizName      = htmlspecialchars($profile['business_name'] ?? $providerName);
$bizCategory  = htmlspecialchars($profile['category_name'] ?? 'Service Provider');
$catSlug      = $profile['category_slug'] ?? '';
$email        = htmlspecialchars($profile['email'] ?? '');
$phone        = htmlspecialchars($profile['phone'] ?? '');
$profilePhoto = $profile['profile_photo'] ?? null;
$initials     = strtoupper(substr($bizName, 0, 2));
$isVerified   = !empty($profile['is_verified']);
$isActive     = ($profile['status'] ?? 'active') === 'active';

// ── Stats for header strip ─────────────────────────────────────
$stTotal = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ?");
$stTotal->execute([$profileId]);
$totalBookings = (int)$stTotal->fetchColumn();

$stRevenue = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM tbl_bookings WHERE provider_id = ? AND status = 'completed'");
$stRevenue->execute([$profileId]);
$totalRevenue = (float)$stRevenue->fetchColumn();

$stRating = $db->prepare("SELECT COALESCE(AVG(rating),0), COUNT(*) FROM tbl_reviews WHERE provider_id = ?");
$stRating->execute([$profileId]);
[$avgRating, $totalReviews] = $stRating->fetch(\PDO::FETCH_NUM);

$stServices = $db->prepare("SELECT COUNT(*) FROM tbl_services WHERE provider_id = ? AND is_active = 1");
$stServices->execute([$profileId]);
$totalServices = (int)$stServices->fetchColumn();

// ── Pending count for nav badge ───────────────────────────────
$stPending = $db->prepare("SELECT COUNT(*) FROM tbl_bookings WHERE provider_id = ? AND status = 'pending'");
$stPending->execute([$profileId]);
$pendingCount = (int)$stPending->fetchColumn();

// ── All categories for select ─────────────────────────────────
$cats = $db->query("SELECT * FROM tbl_categories ORDER BY name")->fetchAll();

// ── Category icon map ─────────────────────────────────────────
$catIconMap = [
    'barbershop'       => '<i class="fa-solid fa-scissors"></i>',
    'hair-salon'       => '<i class="fa-solid fa-spa"></i>',
    'nail-care'        => '<i class="fa-solid fa-hand-sparkles"></i>',
    'massage-therapy'  => '<i class="fa-solid fa-hands"></i>',
    'skincare-facial'  => '<i class="fa-solid fa-face-smile-beam"></i>',
    'fitness-training' => '<i class="fa-solid fa-dumbbell"></i>',
    'home-cleaning'    => '<i class="fa-solid fa-broom"></i>',
    'pet-grooming'     => '<i class="fa-solid fa-paw"></i>',
    'event-styling'    => '<i class="fa-solid fa-wand-magic-sparkles"></i>',
    'dental'           => '<i class="fa-solid fa-tooth"></i>',
    'tutoring'         => '<i class="fa-solid fa-book-open"></i>',
    'makeup-artist'    => '<i class="fa-solid fa-wand-magic-sparkles"></i>',
];
$catIcon  = $catIconMap[$catSlug] ?? '<i class="fa-solid fa-briefcase"></i>';
$catEmoji = $catIcon;

// ── Approval status helper ────────────────────────────────────
$statusMap = [
    1  => ['label' => 'Approved',  'cls' => 'pp-status--approved',  'icon' => '<i class="fa-solid fa-circle-check"></i>'],
    0  => ['label' => 'Pending',   'cls' => 'pp-status--pending',   'icon' => '<i class="fa-solid fa-clock"></i>'],
    -1 => ['label' => 'Suspended', 'cls' => 'pp-status--suspended', 'icon' => '<i class="fa-solid fa-ban"></i>'],
];
$approvalStatus = $statusMap[(int)$profile['is_approved']] ?? $statusMap[0];

// ── Notification preferences (from Settings) ──────────────────
$db->exec("
    CREATE TABLE IF NOT EXISTS tbl_provider_notification_prefs (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        provider_id      INT NOT NULL,
        pref_key         VARCHAR(80) NOT NULL,
        pref_value       TINYINT(1) NOT NULL DEFAULT 1,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_provider_pref (provider_id, pref_key)
    )
");
$notifDefaults = [
    'notif_new_booking'       => 1, 'notif_booking_confirmed' => 1,
    'notif_booking_cancelled' => 1, 'notif_reminder_24h'      => 1,
    'notif_reminder_1h'       => 0, 'notif_new_review'        => 1,
    'notif_low_rating'        => 1, 'notif_portfolio_like'    => 1,
    'notif_portfolio_comment' => 0, 'notif_system_updates'    => 1,
    'notif_security_alerts'   => 1, 'channel_inapp'           => 1,
    'channel_email'           => 1, 'channel_sms'             => 0,
    'channel_weekly_digest'   => 1, 'channel_marketing'       => 0,
];
$stPrefs = $db->prepare("SELECT pref_key, pref_value FROM tbl_provider_notification_prefs WHERE provider_id = ?");
$stPrefs->execute([$profileId]);
$savedPrefs = [];
foreach ($stPrefs->fetchAll() as $row) { $savedPrefs[$row['pref_key']] = (int)$row['pref_value']; }
$notifPrefs = array_merge($notifDefaults, $savedPrefs);

// ── 2FA status ───────────────────────────────────────────────
$stTotp = $db->prepare("SELECT totp_enabled FROM tbl_users WHERE id = ? LIMIT 1");
$stTotp->execute([$userId]);
$totpRow     = $stTotp->fetch();
$totpEnabled = (bool)($totpRow['totp_enabled'] ?? false);

// ── Flash message ─────────────────────────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Helpers ───────────────────────────────────────────────────
function chk(array $prefs, string $key): string { return !empty($prefs[$key]) ? 'checked' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_profile.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/provider_settings.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script>(function(){ var t=localStorage.getItem('qb-theme')||'light'; if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); })();</script>
  <style>
    /* ── pv-nav shared overrides ── */
    .pv-nav{position:sticky;top:0;z-index:200;background:#0D1117;backdrop-filter:blur(24px) saturate(1.8);-webkit-backdrop-filter:blur(24px) saturate(1.8);border-bottom:1px solid rgba(201,168,76,.18);box-shadow:0 2px 24px rgba(0,0,0,.35);}
    .pv-nav-inner{max-width:1380px;margin:0 auto;padding:0 2rem;height:64px;display:flex;align-items:center;gap:1.5rem;}
    .pv-logo{display:flex;align-items:center;gap:.28em;font-family:var(--font-h);font-size:1.28rem;font-weight:700;font-style:italic;letter-spacing:.01em;color:#EDE3CC;text-decoration:none;flex-shrink:0;transition:opacity .15s;}
    .pv-logo:hover{opacity:.72;}.pv-logo span{color:var(--gold);font-style:normal;}
    .pv-logo-badge{font-family:var(--font-m);font-size:.52rem;font-weight:500;letter-spacing:.1em;text-transform:uppercase;background:var(--gold-lt);color:var(--gold-dim);border:1px solid var(--gold-border);padding:.16rem .5rem;border-radius:99px;margin-left:.18rem;font-style:normal;}
    .pv-nav-end{display:flex;align-items:center;gap:.75rem;flex-shrink:0;margin-left:auto;position:relative;}
    .pv-theme-toggle{width:36px;height:36px;border-radius:99px;background:transparent;border:1px solid var(--gold-border);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .25s,border-color .25s,transform .3s,color .25s;outline:none;}
    .pv-theme-toggle:hover{background:var(--gold-lt);border-color:var(--gold-border-md);transform:rotate(20deg) scale(1.1);}
    .pv-profile-trigger{display:flex;align-items:center;gap:.65rem;padding:.3rem .55rem .3rem .3rem;border-radius:99px;border:1px solid transparent;cursor:pointer;position:relative;transition:background .2s,border-color .2s;user-select:none;}
    .pv-profile-trigger:hover,.pv-profile-trigger.is-open{background:var(--surface-md);border-color:var(--gold-border);}
    .pv-nav-av{width:34px;height:34px;border-radius:99px;background:linear-gradient(135deg,var(--gold-dim),var(--gold));color:#fff8e8;font-family:var(--font-h);font-weight:700;font-size:.72rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 0 2px var(--gold-border),0 2px 10px rgba(201,168,76,.25);overflow:hidden;}
    .pv-nav-av img{width:100%;height:100%;object-fit:cover;border-radius:99px;display:block;}
    .pv-nav-user{display:flex;flex-direction:column;line-height:1.2;}
    .pv-nav-user-name{font-size:.82rem;font-weight:600;color:var(--text-primary);white-space:nowrap;}
    .pv-profile-chevron{color:var(--text-dim);transition:transform .25s,color .2s;flex-shrink:0;}
    .pv-profile-trigger.is-open .pv-profile-chevron{transform:rotate(180deg);color:var(--gold-dim);}
    .pv-profile-dropdown{position:absolute;top:calc(100% + 10px);right:0;width:260px;background:rgba(255,255,255,0.92);backdrop-filter:blur(28px) saturate(1.8);-webkit-backdrop-filter:blur(28px) saturate(1.8);border:1.5px solid rgba(255,255,255,0.80);border-radius:var(--r-xl);box-shadow:0 20px 60px rgba(139,110,60,.18),0 4px 16px rgba(139,110,60,.10);z-index:900;opacity:0;transform:translateY(-8px) scale(0.97);pointer-events:none;transition:opacity .22s,transform .22s;overflow:hidden;}
    .pv-profile-dropdown.is-open{opacity:1;transform:translateY(0) scale(1);pointer-events:auto;}
    .pv-pd-header{display:flex;align-items:center;gap:.85rem;padding:1.1rem 1.2rem 1rem;background:linear-gradient(135deg,#FBF6EC 0%,#F5EDDA 100%);}
    .pv-pd-avatar{width:44px;height:44px;border-radius:99px;flex-shrink:0;background:linear-gradient(135deg,var(--gold-dim),var(--gold));color:#fff8e8;font-family:var(--font-h);font-weight:700;font-size:.88rem;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 2.5px var(--gold-border),0 3px 12px rgba(201,168,76,.28);overflow:hidden;}
    .pv-pd-avatar img{width:100%;height:100%;object-fit:cover;display:block;border-radius:99px;}
    .pv-pd-info{min-width:0;flex:1;}
    .pv-pd-name{font-family:var(--font-h);font-size:.9rem;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .pv-pd-email{font-family:var(--font-m);font-size:.6rem;color:var(--text-muted);margin-top:.1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .pv-pd-role{display:inline-block;margin-top:.3rem;font-family:var(--font-m);font-size:.52rem;font-weight:500;letter-spacing:.08em;text-transform:uppercase;background:var(--gold-lt);color:var(--gold-dim);border:1px solid var(--gold-border);padding:.14rem .5rem;border-radius:99px;}
    .pv-pd-divider{height:1px;background:linear-gradient(90deg,transparent,rgba(201,168,76,.25) 30%,rgba(201,168,76,.25) 70%,transparent);}
    .pv-pd-item{display:flex;align-items:center;gap:.75rem;padding:.82rem 1.2rem;font-size:.84rem;font-weight:500;color:var(--text-primary);transition:background .15s,color .15s;cursor:pointer;text-decoration:none;}
    .pv-pd-item:hover{background:rgba(201,168,76,.07);color:var(--gold-dim);}
    .pv-pd-item--danger{color:var(--text-muted);}
    .pv-pd-item--danger:hover{background:var(--red-soft);color:var(--red);}
    .pv-pd-item-ico{width:30px;height:30px;border-radius:var(--r-sm);flex-shrink:0;background:linear-gradient(135deg,#FBF6EC,#F0E7CC);border:1px solid var(--gold-border);display:flex;align-items:center;justify-content:center;font-size:.8rem;color:var(--gold-dim);}
    .pv-pd-item--danger .pv-pd-item-ico{background:var(--red-soft);border-color:var(--red-border);color:var(--red);}
    .pv-pd-item-arrow{margin-left:auto;color:var(--text-dim);flex-shrink:0;}
    [data-theme="dark"] .pv-nav{background:#0D1117;}
    .pv-nav-user-name{font-size:.82rem;font-weight:600;color:#EDE3CC;white-space:nowrap;}
    [data-theme="dark"] .pv-profile-dropdown{background:rgba(20,16,8,0.95);border-color:rgba(201,168,76,.18);}
    [data-theme="dark"] .pv-pd-header{background:linear-gradient(135deg,rgba(28,22,10,.95) 0%,rgba(20,16,8,.98) 100%);}

    /* ── Tab wrapping for extra tabs ── */
    .pp-tabs{flex-wrap:wrap;}

    /* ── Account overview ── */
    .pp-overview-banner{display:flex;align-items:center;gap:1.2rem;margin-bottom:1.5rem;padding:1.2rem;background:linear-gradient(135deg,rgba(255,252,242,.8),rgba(245,237,218,.6));border:1.5px solid rgba(201,168,76,.22);border-radius:var(--r-lg);}
    .pp-overview-av{width:68px;height:68px;border-radius:99px;background:linear-gradient(135deg,var(--gold-dim),var(--gold));color:#fff8e8;font-family:var(--font-h);font-weight:700;font-size:1.2rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 0 3px var(--gold-border),0 4px 16px rgba(201,168,76,.3);overflow:hidden;position:relative;}
    .pp-overview-av img{width:100%;height:100%;object-fit:cover;border-radius:99px;display:block;}
    .pp-overview-status{width:14px;height:14px;border-radius:99px;border:2px solid #fff;position:absolute;bottom:2px;right:2px;}
    .pp-overview-status.is-active{background:#22c55e;}.pp-overview-status.is-inactive{background:var(--text-muted);}
    .pp-overview-fullname{font-family:var(--font-h);font-size:1.05rem;font-weight:700;color:var(--text-primary);}
    .pp-overview-bizname{font-size:.82rem;color:var(--text-dim);margin:.15rem 0 .5rem;}
    .pp-overview-badges{display:flex;flex-wrap:wrap;gap:.35rem;}
    .pp-overview-badge{display:inline-flex;align-items:center;gap:.3rem;font-family:var(--font-m);font-size:.58rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;padding:.2rem .6rem;border-radius:99px;}
    .pp-overview-badge--cat{background:var(--gold-lt);border:1px solid var(--gold-border);color:var(--gold-dim);}
    .pp-overview-badge--verified{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#16a34a;}
    .pp-overview-badge--unverified{background:var(--red-soft);border:1px solid var(--red-border);color:var(--red);}
    .pp-overview-badge--active{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.28);color:#16a34a;}
    .pp-overview-badge--inactive{background:rgba(100,116,139,.1);border:1px solid rgba(100,116,139,.25);color:var(--text-muted);}
    .pp-overview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.8rem;}
    .pp-overview-item{display:flex;align-items:center;gap:.75rem;padding:.85rem;background:rgba(255,255,255,.5);border:1px solid rgba(201,168,76,.15);border-radius:var(--r-md);}
    .pp-overview-item-ico{width:34px;height:34px;border-radius:var(--r-sm);background:var(--gold-lt);border:1px solid var(--gold-border);display:flex;align-items:center;justify-content:center;color:var(--gold-dim);font-size:.8rem;flex-shrink:0;}
    .pp-overview-item-label{font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-family:var(--font-m);}
    .pp-overview-item-val{font-size:.88rem;font-weight:600;color:var(--text-primary);margin-top:.1rem;}

    /* ── Session list ── */
    .pp-session-list{display:flex;flex-direction:column;gap:.75rem;}
    .pp-session-item{display:flex;align-items:center;gap:.85rem;padding:.9rem 1rem;background:rgba(255,255,255,.5);border:1px solid rgba(201,168,76,.15);border-radius:var(--r-md);}
    .pp-session-ico{width:36px;height:36px;border-radius:var(--r-sm);background:var(--gold-lt);border:1px solid var(--gold-border);display:flex;align-items:center;justify-content:center;color:var(--gold-dim);font-size:.9rem;flex-shrink:0;}
    .pp-session-device{font-size:.88rem;font-weight:600;color:var(--text-primary);}
    .pp-session-meta{font-size:.74rem;color:var(--text-muted);margin-top:.1rem;}
    .pp-session-badge{font-family:var(--font-m);font-size:.58rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;padding:.22rem .65rem;border-radius:99px;margin-left:auto;flex-shrink:0;}
    .pp-session-badge--current{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.28);color:#16a34a;}

    /* ── 2FA ── */
    .pp-2fa-row{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
    .pp-2fa-info{display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:0;}
    .pp-2fa-method-icon{width:38px;height:38px;border-radius:var(--r-sm);background:var(--gold-lt);border:1px solid var(--gold-border);display:flex;align-items:center;justify-content:center;color:var(--gold-dim);font-size:1rem;flex-shrink:0;}
    .pp-2fa-badge{font-family:var(--font-m);font-size:.62rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;padding:.25rem .7rem;border-radius:99px;flex-shrink:0;display:inline-flex;align-items:center;gap:.3rem;}
    .pp-2fa-badge--off{background:var(--red-soft);border:1px solid var(--red-border);color:var(--red);}
    .pp-2fa-badge--on{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.28);color:#16a34a;}

    /* ── Danger rows ── */
    .pp-danger-body .pp-danger-row+.pp-danger-row{border-top:1px solid var(--red-border);padding-top:1.25rem;margin-top:1.25rem;}
    .pp-danger-row{display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;}
    .pp-danger-title{font-size:.92rem;font-weight:700;color:var(--text-primary);margin-bottom:.3rem;}
    .pp-danger-desc{font-size:.8rem;color:var(--text-muted);line-height:1.5;max-width:440px;}

    /* ── Shared button helpers ── */
    .ps-av-btn{display:inline-flex;align-items:center;gap:.5rem;padding:.52rem 1.1rem;border-radius:var(--r-md);font-family:var(--font-m);font-size:.78rem;font-weight:500;letter-spacing:.04em;cursor:pointer;transition:background .2s,border-color .2s,color .2s,transform .15s;border:1.5px solid transparent;white-space:nowrap;}
    .ps-av-btn:hover{transform:translateY(-1px);}
    .ps-av-btn--primary{background:var(--gold);color:#1a1000;border-color:var(--gold-dim);box-shadow:0 2px 12px rgba(201,168,76,.35);}
    .ps-av-btn--primary:hover{background:var(--gold-dim);border-color:var(--gold);}
    .ps-av-btn--ghost{background:rgba(255,255,255,.5);color:var(--text-dim);border-color:var(--border);backdrop-filter:blur(12px);}
    .ps-av-btn--ghost:hover{background:var(--surface-md);color:var(--text-primary);border-color:var(--gold-border);}
    .ps-save-btn{display:inline-flex;align-items:center;gap:.5rem;padding:.62rem 1.4rem;border-radius:var(--r-md);background:linear-gradient(135deg,var(--gold),var(--gold-dim));color:#1a1000;font-family:var(--font-m);font-size:.8rem;font-weight:700;letter-spacing:.05em;border:none;cursor:pointer;box-shadow:0 3px 14px rgba(201,168,76,.38);transition:opacity .2s,transform .15s;}
    .ps-save-btn:hover{opacity:.88;transform:translateY(-1px);}
    .pp-card-icon{width:38px;height:38px;border-radius:var(--r-md);background:linear-gradient(135deg,var(--gold-lt),rgba(201,168,76,.12));border:1px solid var(--gold-border);display:flex;align-items:center;justify-content:center;color:var(--gold-dim);font-size:.9rem;flex-shrink:0;}

    /* ── Modals ── */
    .pp-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(6px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .25s;}
    .pp-modal-overlay.is-open{opacity:1;pointer-events:auto;}
    .pp-modal-box{background:rgba(255,252,242,.97);backdrop-filter:blur(32px);border:1.5px solid rgba(255,255,255,.80);border-radius:var(--r-xl);box-shadow:0 32px 80px rgba(139,110,60,.22),0 4px 16px rgba(139,110,60,.12);width:100%;max-width:540px;overflow:hidden;transform:translateY(12px) scale(.97);transition:transform .28s var(--ease-out);}
    .pp-modal-overlay.is-open .pp-modal-box{transform:none;}
    .pp-modal-header{display:flex;align-items:center;justify-content:space-between;padding:1.35rem 1.75rem 1.1rem;border-bottom:1.5px solid rgba(201,168,76,.18);}
    .pp-modal-title{font-family:var(--font-h);font-size:1.12rem;font-weight:700;color:var(--text-primary);margin:0;}
    .pp-modal-subtitle{font-size:.78rem;color:var(--text-muted);margin-top:.2rem;}
    .pp-modal-close{width:32px;height:32px;border-radius:99px;background:rgba(255,255,255,.6);border:1px solid var(--border);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:.9rem;transition:background .2s,color .2s;}
    .pp-modal-close:hover{background:var(--red-soft);color:var(--red);border-color:var(--red-border);}
    .pp-modal-body{padding:1.5rem 1.75rem;}
    .pp-modal-footer{display:flex;align-items:center;justify-content:flex-end;gap:.75rem;padding:1.1rem 1.75rem;border-top:1.5px solid rgba(201,168,76,.18);background:var(--surface-md);}

    /* ── Rate widget ── */
    .pp-fb-section{margin-bottom:1.2rem;}
    .pp-fb-label{display:block;font-size:.82rem;font-weight:600;color:var(--text-primary);margin-bottom:.55rem;}
    .pp-fb-label span{color:var(--red);}
    .pp-star-row{display:flex;gap:.35rem;}
    .pp-star{background:none;border:none;font-size:1.6rem;color:var(--gold-border);cursor:pointer;transition:color .15s,transform .15s;padding:0;}
    .pp-star:hover,.pp-star.is-selected{color:var(--gold);transform:scale(1.15);}
    .pp-star-caption{font-size:.78rem;color:var(--text-muted);margin-top:.4rem;}
    .pp-fb-types{display:flex;flex-wrap:wrap;gap:.5rem;}
    .pp-fb-type{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:99px;font-size:.78rem;font-weight:500;border:1.5px solid var(--border);color:var(--text-dim);background:rgba(255,255,255,.5);cursor:pointer;transition:background .18s,color .18s,border-color .18s;}
    .pp-fb-type:hover{border-color:var(--gold-border);color:var(--gold-dim);background:var(--gold-lt);}
    .pp-fb-type.is-selected{background:var(--gold-lt);border-color:var(--gold-border-md);color:var(--gold-dim);font-weight:600;}
    .pp-fb-char-row{font-size:.72rem;color:var(--text-muted);text-align:right;margin-top:.35rem;}

    /* ── Feedback button ── */
    .pp-feedback-btn{display:inline-flex;align-items:center;justify-content:center;gap:.55rem;padding:.72rem 1.4rem;border-radius:var(--r-md);background:linear-gradient(135deg,rgba(201,168,76,.14),rgba(201,168,76,.06));border:1.5px solid var(--gold-border);color:var(--gold-dim);font-family:var(--font-m);font-size:.75rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;transition:background .2s,border-color .2s,transform .15s;}
    .pp-feedback-btn:hover{background:var(--gold-lt);border-color:var(--gold-border-md);transform:translateY(-1px);}

    /* dark mode extras */
    [data-theme="dark"] .pp-overview-item,[data-theme="dark"] .pp-session-item{background:rgba(255,255,255,.04);}
    [data-theme="dark"] .pp-modal-box{background:rgba(14,11,5,.97);border-color:rgba(201,168,76,.22);}
    [data-theme="dark"] .pp-modal-footer{background:rgba(255,255,255,.03);}
    [data-theme="dark"] .pp-overview-banner{background:linear-gradient(135deg,rgba(28,22,10,.8),rgba(20,16,8,.6));}
    @media(max-width:768px){.pp-overview-grid{grid-template-columns:1fr;}.pp-2fa-row{gap:.75rem;}.pp-danger-row{flex-direction:column;}}
  </style>
</head>
<body>

<div class="grain" aria-hidden="true"></div>

<!-- NAVBAR -->
<nav class="pv-nav" role="navigation" aria-label="Provider navigation">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo" style="width:42px;height:42px;object-fit:contain;display:block;flex-shrink:0;">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Provider</span>
    </a>
    <div class="pv-nav-end">
      <?php $notifUserId = (int)$userId; require __DIR__ . "/../_partials/notification_panel.php"; ?>
      <button class="pv-theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      </button>
      <div class="pv-profile-trigger" id="profileTrigger" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
        <div class="pv-nav-av" id="navAv">
          <?php if ($profilePhoto): ?><img id="navAvImg" src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>"><?php else: ?><span id="navAvInitials"><?= $initials ?></span><?php endif; ?>
        </div>
        <div class="pv-nav-user"><div class="pv-nav-user-name"><?= $firstName ?></div></div>
        <svg class="pv-profile-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="pv-profile-dropdown" id="profileDropdown" role="menu">
        <div class="pv-pd-header">
          <div class="pv-pd-avatar">
            <?php if ($profilePhoto): ?><img src="<?= htmlspecialchars($profilePhoto) ?>" alt="<?= $bizName ?>"><?php else: ?><?= $initials ?><?php endif; ?>
          </div>
          <div class="pv-pd-info">
            <div class="pv-pd-name"><?= $provFullName ?></div>
            <div class="pv-pd-email"><?= $email ?></div>
            <span class="pv-pd-role"><?= $bizCategory ?></span>
          </div>
        </div>
        <div class="pv-pd-divider"></div>
        <a href="<?= BASE_URL ?>provider/profile" class="pv-pd-item" role="menuitem">
          <span class="pv-pd-item-ico"><i class="fa-solid fa-store"></i></span>
          <span>Profile</span>
          <svg class="pv-pd-item-arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
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

<!-- FLASH -->
<?php if ($flash): ?>
<div class="pp-flash pp-flash--<?= $flash['type'] ?>" id="flashMsg" role="alert">
  <span><?= $flash['type'] === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-triangle-exclamation"></i>' ?></span>
  <?= htmlspecialchars($flash['msg']) ?>
  <button class="pp-flash-close" onclick="this.parentElement.remove()" aria-label="Dismiss">&#x2715;</button>
</div>
<?php endif; ?>

<!-- HERO -->
<header class="pp-hero" role="banner">
  <div class="pp-hero-overlay" aria-hidden="true"></div>
  <div class="pp-hero-inner">
    <div class="pp-hero-top-bar">
      <div></div>
      <span class="pp-status-pill <?= $approvalStatus['cls'] ?>">
        <?= $approvalStatus['icon'] ?> <?= $approvalStatus['label'] ?>
      </span>
    </div>
    <div class="pp-hero-profile-row">
      <?php $photoUrl = !empty($profile['profile_photo']) ? $profile['profile_photo'] : null; ?>
      <div class="pp-hero-av-wrap">
        <div class="pp-hero-av" id="heroAv">
          <?php if ($photoUrl): ?><img src="<?= $photoUrl ?>" alt="Profile photo" id="heroAvImg"><?php else: ?><span id="heroAvEmoji"><?= $catEmoji ?></span><?php endif; ?>
        </div>
        <label class="pp-av-upload-btn" for="profilePhotoInput" title="Change profile photo"><i class="fa-solid fa-camera"></i></label>
        <form id="photoUploadForm" method="POST" action="<?= BASE_URL ?>provider/profile/upload-photo" enctype="multipart/form-data" style="display:none;">
          <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp" style="display:none;">
        </form>
      </div>
      <div class="pp-hero-identity">
        <p class="pp-hero-eyebrow"><span class="pp-dot-pulse" aria-hidden="true"></span><?= htmlspecialchars($profile['category_name'] ?? 'Service Provider') ?></p>
        <h1 class="pp-hero-name"><?= htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?></h1>
        <div class="pp-hero-meta">
          <span class="pp-meta-chip"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($profile['email']) ?></span>
          <?php if ($profile['phone']): ?><span class="pp-meta-chip"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($profile['phone']) ?></span><?php endif; ?>
          <span class="pp-meta-chip"><i class="fa-solid fa-location-dot"></i> Bacolod City</span>
        </div>
      </div>
    </div>
    <div class="pp-hero-stats">
      <div class="pp-hs-item"><span class="pp-hs-val"><?= $totalBookings ?></span><span class="pp-hs-label">Total Bookings</span></div>
      <div class="pp-hs-div"></div>
      <div class="pp-hs-item"><span class="pp-hs-val">&#x20B1;<?= number_format($totalRevenue, 0) ?></span><span class="pp-hs-label">Revenue Earned</span></div>
      <div class="pp-hs-div"></div>
      <div class="pp-hs-item"><span class="pp-hs-val gold"><?= number_format((float)$avgRating, 1) ?> <i class="fa-solid fa-star"></i></span><span class="pp-hs-label"><?= (int)$totalReviews ?> Reviews</span></div>
      <div class="pp-hs-div"></div>
      <div class="pp-hs-item"><span class="pp-hs-val"><?= $totalServices ?></span><span class="pp-hs-label">Active Services</span></div>
    </div>
  </div>
</header>

<!-- MAIN -->
<main class="pp-page" role="main">
  <div class="pp-layout">
    <div class="pp-main">

      <nav class="pp-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= BASE_URL ?>provider/dashboard">Dashboard</a>
        <span aria-hidden="true">&#x203A;</span>
        <span>Profile</span>
      </nav>

      <!-- TABS -->
      <div class="pp-tabs" role="tablist" aria-label="Profile and settings sections">
        <button class="pp-tab is-active" data-tab="personal" role="tab" aria-selected="true"><i class="fa-solid fa-user"></i> Personal Details</button>
        <button class="pp-tab" data-tab="security" role="tab" aria-selected="false"><i class="fa-solid fa-shield-halved"></i> Security</button>
      </div>

      <!-- TAB: PERSONAL DETAILS -->
      <div class="pp-tab-panel is-active" id="tab-personal" role="tabpanel">
        <div class="pp-card">
          <div class="pp-card-head">
            <div><h2>Personal Details</h2><span class="pp-card-sub">Your account contact information</span></div>
            <div class="pp-card-head-badge pp-card-head-badge--private">Private</div>
          </div>
          <form method="POST" action="<?= BASE_URL ?>provider/profile/update-personal" class="pp-form" id="personalForm">
            <div class="pp-form-group">
              <label class="pp-form-label" for="bio">Business Bio <span class="pp-label-hint">Shown to customers</span></label>
              <textarea class="pp-form-control pp-textarea" id="bio" name="bio" rows="4" placeholder="Tell customers what makes your business special..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
              <div class="pp-char-counter"><span id="bioCount"><?= strlen($profile['bio'] ?? '') ?></span>/500 characters</div>
            </div>
            <div class="pp-form-row pp-form-row--2">
              <div class="pp-form-group">
                <label class="pp-form-label" for="first_name">First Name <span class="pp-req">*</span></label>
                <div class="pp-input-wrap"><span class="pp-input-icon"><i class="fa-solid fa-user"></i></span><input type="text" class="pp-form-control pp-form-control--icon" id="first_name" name="first_name" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>" placeholder="First name" required></div>
              </div>
              <div class="pp-form-group">
                <label class="pp-form-label" for="last_name">Last Name <span class="pp-req">*</span></label>
                <input type="text" class="pp-form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>" placeholder="Last name" required>
              </div>
            </div>
            <div class="pp-form-row pp-form-row--2">
              <div class="pp-form-group">
                <label class="pp-form-label" for="email">Email Address <span class="pp-req">*</span></label>
                <div class="pp-input-wrap"><span class="pp-input-icon"><i class="fa-solid fa-envelope"></i></span><input type="email" class="pp-form-control pp-form-control--icon" id="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" placeholder="email@example.com" required></div>
              </div>
              <div class="pp-form-group">
                <label class="pp-form-label" for="phone">Phone Number</label>
                <div class="pp-input-wrap"><span class="pp-input-icon"><i class="fa-solid fa-phone"></i></span><input type="tel" class="pp-form-control pp-form-control--icon" id="phone" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" placeholder="09XX XXX XXXX"></div>
              </div>
            </div>
            <div class="pp-info-notice"><span aria-hidden="true"><i class="fa-solid fa-circle-info"></i></span>Your email is used for login and booking notifications. Changing it will require re-verification.</div>
            <div class="pp-form-actions">
              <button type="reset" class="pp-btn pp-btn--ghost">Reset Changes</button>
              <button type="submit" class="pp-btn pp-btn--primary"><span class="pp-btn-icon" aria-hidden="true"><i class="fa-solid fa-floppy-disk"></i></span>Save Personal Info</button>
            </div>
          </form>
        </div>
      </div>

      <!-- TAB: SECURITY -->
      <div class="pp-tab-panel" id="tab-security" role="tabpanel" hidden>
        <div class="pp-card">
          <div class="pp-card-head"><div><h2>Change Password</h2><span class="pp-card-sub">Keep your account secure with a strong, unique password</span></div><div class="pp-card-head-badge pp-card-head-badge--danger">Sensitive</div></div>
          <form method="POST" action="<?= BASE_URL ?>provider/profile/update-password" class="pp-form" id="passwordForm">
            <div class="pp-form-group">
              <label class="pp-form-label" for="current_password">Current Password <span class="pp-req">*</span></label>
              <div class="pp-input-wrap"><span class="pp-input-icon"><i class="fa-solid fa-key"></i></span><input type="password" class="pp-form-control pp-form-control--icon" id="current_password" name="current_password" placeholder="Enter current password" required><button type="button" class="pp-pw-toggle" data-target="current_password" aria-label="Toggle visibility"><i class="fa-solid fa-eye"></i></button></div>
            </div>
            <div class="pp-form-row pp-form-row--2">
              <div class="pp-form-group">
                <label class="pp-form-label" for="new_password">New Password <span class="pp-req">*</span></label>
                <div class="pp-input-wrap"><span class="pp-input-icon"><i class="fa-solid fa-lock"></i></span><input type="password" class="pp-form-control pp-form-control--icon" id="new_password" name="new_password" placeholder="Min 8 characters" minlength="8" required><button type="button" class="pp-pw-toggle" data-target="new_password" aria-label="Toggle visibility"><i class="fa-solid fa-eye"></i></button></div>
                <div class="pp-pw-strength"><div class="pp-pw-strength-bar" id="pwStrengthBar"></div></div>
                <span class="pp-pw-strength-label" id="pwStrengthLabel"></span>
              </div>
              <div class="pp-form-group">
                <label class="pp-form-label" for="confirm_password">Confirm Password <span class="pp-req">*</span></label>
                <div class="pp-input-wrap"><span class="pp-input-icon"><i class="fa-solid fa-lock"></i></span><input type="password" class="pp-form-control pp-form-control--icon" id="confirm_password" name="confirm_password" placeholder="Repeat new password" required><button type="button" class="pp-pw-toggle" data-target="confirm_password" aria-label="Toggle visibility"><i class="fa-solid fa-eye"></i></button></div>
                <span class="pp-match-hint" id="pwMatchHint"></span>
              </div>
            </div>
            <div class="pp-pw-requirements">
              <div class="pp-pw-req-title">Password must contain:</div>
              <div class="pp-pw-req-list">
                <div class="pp-pw-req-item" id="req-length"><span class="pp-pw-req-dot"></span> At least 8 characters</div>
                <div class="pp-pw-req-item" id="req-upper"><span class="pp-pw-req-dot"></span> One uppercase letter</div>
                <div class="pp-pw-req-item" id="req-number"><span class="pp-pw-req-dot"></span> One number</div>
                <div class="pp-pw-req-item" id="req-special"><span class="pp-pw-req-dot"></span> One special character</div>
              </div>
            </div>
            <div class="pp-form-actions">
              <button type="reset" class="pp-btn pp-btn--ghost" onclick="resetPasswordForm()">Cancel</button>
              <button type="submit" class="pp-btn pp-btn--danger"><span class="pp-btn-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>Update Password</button>
            </div>
          </form>
        </div>

        <div class="pp-card">
          <div class="pp-card-head">
            <div style="display:flex;align-items:center;gap:.85rem;">
              <div class="pp-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
              <div><h2 style="margin:0;">Login Security</h2><span class="pp-card-sub">Devices and browsers currently signed into your account.</span></div>
            </div>
          </div>
          <div style="padding:1.5rem 1.75rem;">
            <div class="pp-session-list">
              <div class="pp-session-item">
                <div class="pp-session-ico"><i class="fa-solid fa-computer"></i></div>
                <div><div class="pp-session-device">Chrome on Windows</div><div class="pp-session-meta">Bacolod City, PH &middot; Active now</div></div>
                <span class="pp-session-badge pp-session-badge--current">Current</span>
              </div>
              <div class="pp-session-item">
                <div class="pp-session-ico" style="background:var(--surface-md);border-color:var(--border);color:var(--text-muted);"><i class="fa-solid fa-mobile-screen"></i></div>
                <div><div class="pp-session-device">Safari on iPhone</div><div class="pp-session-meta">Bacolod City, PH &middot; 2 days ago</div></div>
                <form method="POST" action="<?= BASE_URL ?>provider/profile/revoke-session" style="display:inline;">
                  <button type="submit" class="ps-av-btn ps-av-btn--ghost" style="font-size:.76rem;padding:.36rem .8rem;"><i class="fa-solid fa-right-from-bracket"></i> Revoke</button>
                </form>
              </div>
            </div>
            <div style="margin-top:1rem;">
              <form method="POST" action="<?= BASE_URL ?>provider/profile/revoke-all-sessions" style="display:inline;" onsubmit="return confirm('Sign out all other sessions?');">
                <button type="submit" class="ps-av-btn ps-av-btn--ghost" style="color:var(--red);border-color:rgba(239,68,68,.35);"><i class="fa-solid fa-circle-xmark"></i> Sign Out All Other Sessions</button>
              </form>
            </div>
          </div>
        </div>

        <div class="pp-card">
          <div class="pp-card-head">
            <div style="display:flex;align-items:center;gap:.85rem;">
              <div class="pp-card-icon"><i class="fa-solid fa-shield-halved"></i></div>
              <div><h2 style="margin:0;">Two-Factor Authentication</h2><span class="pp-card-sub">Add a second layer of protection to your provider account.</span></div>
            </div>
          </div>
          <div style="padding:1.5rem 1.75rem;">

            <?php if ($totpEnabled): ?>
            <!-- 2FA ENABLED STATE -->
            <p style="margin-bottom:1rem;color:#4ADE80;font-weight:600;">
              <i class="fa-solid fa-circle-check"></i> 2FA is enabled on your account.
            </p>
            <form method="POST" action="<?= BASE_URL ?>auth/2fa/disable">
              <div class="pp-form-group" style="margin-bottom:.85rem;">
                <label class="pp-form-label" for="pp_disable_otp">ENTER 6-DIGIT CODE FROM YOUR AUTHENTICATOR APP</label>
                <input type="text" id="pp_disable_otp" name="otp"
                       placeholder="Enter 6-digit code" maxlength="6" required
                       class="pp-form-control" style="letter-spacing:.15em;text-align:center;">
              </div>
              <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="pp-btn pp-btn--danger">
                  DISABLE 2FA
                </button>
              </div>
            </form>

            <?php else: ?>
            <!-- 2FA DISABLED STATE -->
            <div class="pp-2fa-row">
              <div class="pp-2fa-info">
                <div class="pp-2fa-method-icon"><i class="fa-brands fa-google"></i></div>
                <div>
                  <div style="font-size:.92rem;font-weight:600;color:var(--text-primary);margin-bottom:.3rem;">Authenticator App (TOTP)</div>
                  <div style="font-size:.78rem;color:var(--text-muted);">Use an app like Google Authenticator or Authy to generate one-time codes whenever you sign in.</div>
                </div>
              </div>
              <span class="pp-2fa-badge pp-2fa-badge--off"><i class="fa-solid fa-circle-xmark"></i> Not Enabled</span>
            </div>
            <div class="pp-2fa-row" style="margin-top:1rem;">
              <div class="pp-2fa-info">
                <div class="pp-2fa-method-icon"><i class="fa-solid fa-message"></i></div>
                <div>
                  <div style="font-size:.92rem;font-weight:600;color:var(--text-primary);margin-bottom:.3rem;">SMS Verification</div>
                  <div style="font-size:.78rem;color:var(--text-muted);">Receive a one-time code via SMS to: <?= $phone ? substr($phone, 0, -4) . '****' : 'No phone set' ?>.</div>
                </div>
              </div>
              <span class="pp-2fa-badge pp-2fa-badge--off"><i class="fa-solid fa-circle-xmark"></i> Not Enabled</span>
            </div>
            <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid rgba(201,168,76,.14);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
              <div>
                <div style="font-size:.88rem;font-weight:600;color:var(--text-primary);margin-bottom:.25rem;">Protect your account in 2 minutes</div>
                <div style="font-size:.78rem;color:var(--text-muted);">Scan a QR code with your authenticator app — no phone number required.</div>
              </div>
              <a href="<?= BASE_URL ?>auth/2fa/setup" class="pp-btn pp-btn--primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:.45rem;">
                <i class="fa-solid fa-shield-halved"></i> Enable Two-Factor Authentication
              </a>
            </div>
            <?php endif; ?>

          </div>
        </div>
        <div class="pp-card">
          <div class="pp-card-head">
            <div style="display:flex;align-items:center;gap:.85rem;">
              <div class="pp-card-icon"><i class="fa-solid fa-download"></i></div>
              <div><h2 style="margin:0;">Export My Data</h2><span class="pp-card-sub">Download a copy of all your profile data, booking history, and reviews.</span></div>
            </div>
          </div>
          <div style="padding:1.5rem 1.75rem;">
            <p style="font-size:.88rem;color:var(--text-dim);margin-bottom:1rem;line-height:1.55;">You can request a complete export of your QuickBook provider data. The file will be prepared and sent to <strong><?= $email ?></strong> within 24 hours.</p>
            <form method="POST" action="<?= BASE_URL ?>provider/profile/export-data" style="display:inline;">
              <button type="submit" class="ps-av-btn ps-av-btn--ghost" style="color:#3b82f6;border-color:rgba(59,130,246,.35);"><i class="fa-solid fa-file-export"></i> Request Data Export</button>
            </form>
          </div>
        </div>
        <div class="pp-card pp-card--danger">
          <div class="pp-card-head"><div><h2 style="color:var(--red);">Danger Zone</h2><span class="pp-card-sub">Irreversible actions &mdash; proceed with extreme caution.</span></div></div>
          <div class="pp-danger-body" style="padding:1.5rem 1.75rem;">
            <div class="pp-danger-row">
              <div><div class="pp-danger-title">Deactivate Provider Account</div><div class="pp-danger-desc">Temporarily hide your profile from Browse and Search. Existing bookings are preserved. You can reactivate at any time from this page.</div></div>
              <button class="pp-btn pp-btn--danger-outline" onclick="confirmDeactivate()"><i class="fa-solid fa-eye-slash"></i> Deactivate</button>
            </div>
            <div class="pp-danger-row">
              <div><div class="pp-danger-title">Delete Provider Account</div><div class="pp-danger-desc">Permanently delete your QuickBook provider account and all associated data. This action <strong>cannot be undone</strong>.</div></div>
              <button class="pp-btn pp-btn--danger" id="deleteAccountBtn"><i class="fa-solid fa-trash"></i> Delete Account</button>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /pp-main -->

    <!-- SIDEBAR -->
    <aside class="pp-sidebar" aria-label="Profile overview">
      <div class="pp-card">
        <div class="pp-card-head"><h2>Profile Completeness</h2></div>
        <?php
          $fields = ['first_name','last_name','email','phone','bio'];
          $filled = count(array_filter($fields, fn($f) => !empty($profile[$f])));
          $pct    = (int)(($filled / count($fields)) * 100);
          $pctCls = $pct >= 80 ? 'good' : ($pct >= 50 ? 'mid' : 'low');
        ?>
        <div class="pp-completeness-body">
          <div class="pp-comp-ring-wrap">
            <svg class="pp-comp-ring" viewBox="0 0 88 88" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <circle cx="44" cy="44" r="36" stroke="rgba(255,255,255,.07)" stroke-width="8"/>
              <circle cx="44" cy="44" r="36" stroke="var(--pp-comp-color)" stroke-width="8" stroke-linecap="round" stroke-dasharray="226.2" stroke-dashoffset="<?= 226.2 - (226.2 * $pct / 100) ?>" transform="rotate(-90 44 44)" class="pp-comp-arc pp-comp-arc--<?= $pctCls ?>"/>
            </svg>
            <div class="pp-comp-ring-val"><?= $pct ?>%</div>
          </div>
          <div class="pp-comp-items">
            <?php
              $compFields = ['first_name'=>'<i class="fa-solid fa-user"></i> First name','last_name'=>'<i class="fa-solid fa-user"></i> Last name','email'=>'<i class="fa-solid fa-envelope"></i> Email','phone'=>'<i class="fa-solid fa-phone"></i> Phone','bio'=>'<i class="fa-solid fa-file-lines"></i> Bio'];
              foreach ($compFields as $k => $label): $done = !empty($profile[$k]); ?>
            <div class="pp-comp-item <?= $done ? 'is-done' : '' ?>"><span class="pp-comp-check" aria-hidden="true"><?= $done ? '&#x2713;' : '&#x25CB;' ?></span><?= $label ?></div>
            <?php endforeach; ?>
          </div>
          <?php if ($pct < 100): ?><p class="pp-comp-tip">Complete your profile to rank higher in customer searches!</p><?php endif; ?>
        </div>
      </div>

      <div class="pp-card">
        <div class="pp-card-head"><h2><i class="fa-solid fa-lightbulb"></i> Profile Tips</h2></div>
        <div class="pp-tips-body">
          <div class="pp-tip-item"><div class="pp-tip-icon" aria-hidden="true"><i class="fa-solid fa-camera"></i></div><div class="pp-tip-text">Add a profile photo to increase bookings by up to <strong>30%</strong></div></div>
          <div class="pp-tip-item"><div class="pp-tip-icon" aria-hidden="true"><i class="fa-solid fa-pen-nib"></i></div><div class="pp-tip-text">A detailed bio helps customers trust you before booking</div></div>
          <div class="pp-tip-item"><div class="pp-tip-icon" aria-hidden="true"><i class="fa-solid fa-star"></i></div><div class="pp-tip-text">Prompt satisfied customers to leave reviews to build credibility</div></div>
        </div>
      </div>

      <div class="pp-card">
        <div class="pp-card-head"><h2>Account Info</h2></div>
        <div class="pp-account-body">
          <div class="pp-account-row"><span class="pp-account-label">Account ID</span><span class="pp-account-val pp-mono">#<?= $profileId ?></span></div>
          <div class="pp-account-row"><span class="pp-account-label">Member Since</span><span class="pp-account-val"><?= date('M Y', strtotime($profile['created_at'] ?? 'now')) ?></span></div>
          <div class="pp-account-row"><span class="pp-account-label">Status</span><span class="pp-account-val <?= $approvalStatus['cls'] ?>"><?= $approvalStatus['icon'] ?> <?= $approvalStatus['label'] ?></span></div>
        </div>
      </div>
    </aside>
  </div>
</main>

<!-- DEACTIVATE MODAL -->
<div class="pp-modal-overlay" id="deactivateModal" role="dialog" aria-modal="true" aria-labelledby="deactivateTitle" style="display:none">
  <div class="pp-modal-box" style="max-width:440px;">
    <div class="pp-modal-header">
      <div style="display:flex;align-items:center;gap:.75rem;"><div class="pp-card-icon" style="background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:#d97706;"><i class="fa-solid fa-triangle-exclamation"></i></div><div><h2 class="pp-modal-title">Deactivate Account</h2><p class="pp-modal-subtitle" id="deactivateTitle">Your profile will be hidden from customers.</p></div></div>
      <button class="pp-modal-close" id="closeDeactivateModal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="pp-modal-body">
      <p style="font-size:.9rem;color:var(--text-dim);line-height:1.6;margin-bottom:1rem;">Are you sure you want to deactivate your provider account? Your profile will be hidden from Browse and Search.</p>
      <p style="font-size:.84rem;color:var(--text-muted);line-height:1.5;">Existing confirmed bookings will not be affected. You can reactivate at any time from this page.</p>
    </div>
    <div class="pp-modal-footer">
      <button class="pp-btn pp-btn--ghost" type="button" id="cancelDeactivate">Cancel</button>
      <form method="POST" action="<?= BASE_URL ?>provider/profile/deactivate" style="display:inline;"><button type="submit" class="pp-btn pp-btn--danger"><i class="fa-solid fa-eye-slash"></i> Yes, Deactivate</button></form>
    </div>
  </div>
</div>

<!-- DELETE ACCOUNT MODAL -->
<div class="pp-modal-overlay" id="deleteModalOverlay" role="dialog" aria-modal="true" style="display:none">
  <div class="pp-modal-box" style="max-width:440px;">
    <div class="pp-modal-header">
      <div style="display:flex;align-items:center;gap:.75rem;"><div class="pp-card-icon" style="background:var(--red-soft);border-color:var(--red-border);color:var(--red);"><i class="fa-solid fa-trash"></i></div><div><h2 class="pp-modal-title" style="color:var(--red);">Delete Account</h2><p class="pp-modal-subtitle">This action cannot be undone.</p></div></div>
      <button class="pp-modal-close" id="closeDeleteModal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="pp-modal-body">
      <p style="font-size:.9rem;color:var(--text-dim);line-height:1.6;margin-bottom:1.2rem;">You are about to permanently delete your QuickBook provider account. All data including your profile, bookings, reviews, and portfolio will be erased and <strong>cannot be recovered</strong>.</p>
      <div class="pp-form-group"><label class="pp-form-label">Type <strong>DELETE</strong> to confirm</label><input type="text" class="pp-form-control" id="deleteConfirmInput" placeholder="Type DELETE here"></div>
    </div>
    <div class="pp-modal-footer"><button class="pp-btn pp-btn--ghost" type="button" id="cancelDelete">Cancel</button><button class="pp-btn pp-btn--danger" type="button" id="confirmDeleteBtn" style="opacity:.4;pointer-events:none;"><i class="fa-solid fa-trash"></i> Permanently Delete</button></div>
  </div>
</div>

<script>
(function () {
  // Tab switching with URL hash support
  const tabs = document.querySelectorAll('.pp-tab'), panels = document.querySelectorAll('.pp-tab-panel');
  function activateTab(target) {
    tabs.forEach(t => { t.classList.remove('is-active'); t.setAttribute('aria-selected','false'); });
    panels.forEach(p => { p.classList.remove('is-active'); p.hidden = true; });
    const tab = document.querySelector('.pp-tab[data-tab="'+target+'"]'), panel = document.getElementById('tab-'+target);
    if (tab) { tab.classList.add('is-active'); tab.setAttribute('aria-selected','true'); }
    if (panel) { panel.classList.add('is-active'); panel.hidden = false; }
    history.replaceState(null,'','#'+target);
  }
  tabs.forEach(t => t.addEventListener('click', () => activateTab(t.dataset.tab)));
  const hash = location.hash.replace('#','');
  if (hash && document.getElementById('tab-'+hash)) activateTab(hash);

  // Bio counter
  const bio = document.getElementById('bio'), cnt = document.getElementById('bioCount');
  if (bio && cnt) bio.addEventListener('input', () => { const l=bio.value.length; cnt.textContent=l; cnt.style.color=l>450?'var(--red)':l>380?'var(--yellow)':''; if(l>500) bio.value=bio.value.slice(0,500); });

  // Password toggle
  document.querySelectorAll('.pp-pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => { const inp=document.getElementById(btn.dataset.target); if(!inp) return; inp.type=inp.type==='password'?'text':'password'; btn.innerHTML=inp.type==='password'?'<i class="fa-solid fa-eye"></i>':'<i class="fa-solid fa-eye-slash"></i>'; });
  });

  // Password strength
  const pwInput=document.getElementById('new_password'), confirmInput=document.getElementById('confirm_password');
  const strengthBar=document.getElementById('pwStrengthBar'), strengthLbl=document.getElementById('pwStrengthLabel'), matchHint=document.getElementById('pwMatchHint');
  const reqLen=document.getElementById('req-length'), reqUp=document.getElementById('req-upper'), reqNum=document.getElementById('req-number'), reqSp=document.getElementById('req-special');
  function cReq(el,m){if(el)el.classList.toggle('is-met',m);}
  if(pwInput){pwInput.addEventListener('input',()=>{const v=pwInput.value,hL=v.length>=8,hU=/[A-Z]/.test(v),hN=/\d/.test(v),hS=/[^A-Za-z0-9]/.test(v);cReq(reqLen,hL);cReq(reqUp,hU);cReq(reqNum,hN);cReq(reqSp,hS);const sc=[hL,hU,hN,hS].filter(Boolean).length;if(strengthBar){strengthBar.className='pp-pw-strength-bar pp-pw-strength-bar--'+(['','weak','fair','good','strong'][sc]||'');strengthBar.style.width=(sc*25)+'%';}if(strengthLbl)strengthLbl.textContent=['','Weak','Fair','Good','Strong'][sc]||'';});}
  if(confirmInput){confirmInput.addEventListener('input',()=>{if(!matchHint||!pwInput)return;const v=confirmInput.value,m=v===pwInput.value;if(!v){matchHint.innerHTML='';matchHint.className='pp-match-hint';confirmInput.style.borderColor='';}else if(m){matchHint.innerHTML='<i class="fa-solid fa-circle-check"></i> Passwords match';matchHint.className='pp-match-hint is-match';confirmInput.style.borderColor='rgba(74,222,128,.5)';}else{matchHint.innerHTML='<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';matchHint.className='pp-match-hint is-no-match';confirmInput.style.borderColor='rgba(244,63,94,.5)';}});}
  window.resetPasswordForm=function(){['current_password','new_password','confirm_password'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});if(strengthBar){strengthBar.className='pp-pw-strength-bar';strengthBar.style.width='0';}if(strengthLbl)strengthLbl.textContent='';if(matchHint)matchHint.textContent='';};

  // Photo upload
  const photoInput=document.getElementById('profilePhotoInput'),photoForm=document.getElementById('photoUploadForm'),heroAv=document.getElementById('heroAv');
  if(photoInput&&photoForm){photoInput.addEventListener('change',function(){const file=this.files[0];if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)){showPhotoToast('Only JPG, PNG or WebP images are allowed.','error');return;}if(file.size>3*1024*1024){showPhotoToast('Image must be under 3 MB.','error');return;}const reader=new FileReader();reader.onload=function(e){let img=document.getElementById('heroAvImg');const emoji=document.getElementById('heroAvEmoji');if(emoji)emoji.remove();if(!img){img=document.createElement('img');img.id='heroAvImg';heroAv.appendChild(img);}img.src=e.target.result;};reader.readAsDataURL(file);const fd=new FormData(photoForm);fd.append('profile_photo',file);const uploadBtn=document.querySelector('.pp-av-upload-btn');uploadBtn.classList.add('pp-av-upload-btn--loading');fetch(photoForm.action,{method:'POST',body:fd}).then(r=>r.json()).then(data=>{uploadBtn.classList.remove('pp-av-upload-btn--loading');if(data.success){showPhotoToast('Profile photo updated!','success');const navAv=document.getElementById('navAv'),navIn=document.getElementById('navAvInitials');let navImg=document.getElementById('navAvImg');if(navIn)navIn.remove();if(!navImg){navImg=document.createElement('img');navImg.id='navAvImg';navAv.appendChild(navImg);}navImg.src=document.getElementById('heroAvImg').src;}else{showPhotoToast(data.error||'Upload failed.','error');}}).catch(()=>{uploadBtn.classList.remove('pp-av-upload-btn--loading');showPhotoToast('Upload failed. Please try again.','error');});});}
  function showPhotoToast(msg,type){const ex=document.getElementById('pp-photo-toast');if(ex)ex.remove();const t=document.createElement('div');t.id='pp-photo-toast';t.className='pp-photo-toast pp-photo-toast--'+type;t.innerHTML=(type==='success'?'<i class="fa-solid fa-circle-check"></i> ':'<i class="fa-solid fa-triangle-exclamation"></i> ')+msg;document.body.appendChild(t);setTimeout(()=>t.classList.add('pp-photo-toast--show'),10);setTimeout(()=>{t.classList.remove('pp-photo-toast--show');setTimeout(()=>t.remove(),400);},3500);}

  // Deactivate modal
  (function(){
    var overlay=document.getElementById('deactivateModal');
    var closeBtn=document.getElementById('closeDeactivateModal');
    var cancelBtn=document.getElementById('cancelDeactivate');
    function openDeactivate(){overlay.style.display='flex';requestAnimationFrame(()=>overlay.classList.add('is-open'));document.body.style.overflow='hidden';}
    function closeDeactivate(){overlay.classList.remove('is-open');document.body.style.overflow='';setTimeout(()=>{if(!overlay.classList.contains('is-open'))overlay.style.display='none';},260);}
    window.confirmDeactivate=openDeactivate;
    if(closeBtn)closeBtn.addEventListener('click',closeDeactivate);
    if(cancelBtn)cancelBtn.addEventListener('click',closeDeactivate);
    overlay?.addEventListener('click',(e)=>{if(e.target===overlay)closeDeactivate();});
  })();

  // Flash auto-dismiss
  const flash=document.getElementById('flashMsg'); if(flash)setTimeout(()=>flash.remove(),5000);

  // Prevent double submit
  document.querySelectorAll('.pp-form').forEach(form=>{form.addEventListener('submit',function(){const btn=this.querySelector('[type="submit"]');if(btn){btn.disabled=true;btn.innerHTML='<span class="pp-btn-icon"><i class="fa-solid fa-spinner fa-spin"></i></span> Saving\u2026';}});});

  // Escape key closes modals
  document.addEventListener('keydown',function(e){if(e.key!=='Escape')return;const dm=document.getElementById('deactivateModal');if(dm&&dm.classList.contains('is-open')){dm.classList.remove('is-open');document.body.style.overflow='';setTimeout(()=>{if(!dm.classList.contains('is-open'))dm.style.display='none';},260);}const del=document.getElementById('deleteModalOverlay');if(del?.classList.contains('is-open')){del.classList.remove('is-open');document.body.style.overflow='';setTimeout(()=>{if(!del.classList.contains('is-open'))del.style.display='none';},260);}});
})();

// Delete account modal
(function(){
  const overlay=document.getElementById('deleteModalOverlay'),openBtn=document.getElementById('deleteAccountBtn'),closeBtn=document.getElementById('closeDeleteModal'),cancelBtn=document.getElementById('cancelDelete'),confirmBtn=document.getElementById('confirmDeleteBtn'),input=document.getElementById('deleteConfirmInput');
  function openModal(){overlay.style.display='flex';requestAnimationFrame(()=>overlay.classList.add('is-open'));document.body.style.overflow='hidden';}
  function closeModal(){overlay.classList.remove('is-open');document.body.style.overflow='';if(input)input.value='';updateDeleteBtn();setTimeout(()=>{if(!overlay.classList.contains('is-open'))overlay.style.display='none';},260);}
  function updateDeleteBtn(){const ok=input?.value.trim()==='DELETE';confirmBtn.style.opacity=ok?'1':'.4';confirmBtn.style.pointerEvents=ok?'auto':'none';}
  if(openBtn)openBtn.addEventListener('click',openModal);
  if(closeBtn)closeBtn.addEventListener('click',closeModal);
  if(cancelBtn)cancelBtn.addEventListener('click',closeModal);
  overlay?.addEventListener('click',(e)=>{if(e.target===overlay)closeModal();});
  input?.addEventListener('input',updateDeleteBtn);
  confirmBtn?.addEventListener('click',()=>{confirmBtn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Deleting\u2026';confirmBtn.disabled=true;setTimeout(()=>{const f=document.createElement('form');f.method='POST';f.action='<?= BASE_URL ?>provider/settings/delete';const inp=document.createElement('input');inp.type='hidden';inp.name='confirm';inp.value='DELETE';f.appendChild(inp);document.body.appendChild(f);f.submit();},1000);});
})();
</script>

<script>
(function(){
  var html=document.documentElement,btn=document.getElementById('themeToggle');
  var moon=btn?btn.querySelector('.icon-moon'):null,sun=btn?btn.querySelector('.icon-sun'):null;
  function applyTheme(t){if(t==='dark'){html.setAttribute('data-theme','dark');if(moon)moon.style.display='block';if(sun)sun.style.display='none';}else{html.removeAttribute('data-theme');if(moon)moon.style.display='none';if(sun)sun.style.display='block';}}
  applyTheme(localStorage.getItem('qb-theme')||'light');
  if(btn)btn.addEventListener('click',function(){var n=html.getAttribute('data-theme')==='dark'?'light':'dark';localStorage.setItem('qb-theme',n);applyTheme(n);});
  var trigger=document.getElementById('profileTrigger'),dropdown=document.getElementById('profileDropdown');
  if(trigger&&dropdown){trigger.addEventListener('click',function(e){e.stopPropagation();var open=dropdown.classList.toggle('is-open');trigger.setAttribute('aria-expanded',open);});document.addEventListener('click',function(){dropdown.classList.remove('is-open');trigger.setAttribute('aria-expanded','false');});dropdown.addEventListener('click',function(e){e.stopPropagation();});}
})();
</script>
</body>
</html>