<?php
// ── Shared admin nav ───────────────────────────────────────────
// Usage: adminNav('dashboard')  (pass the active page key)
function adminNav(string $active = ''): void {
    $name     = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
    $initials = strtoupper(substr($name, 0, 2));
    $links = [
        'dashboard' => ['label' => 'Dashboard', 'url' => 'admin/dashboard'],
        'bookings'  => ['label' => 'Bookings',  'url' => 'admin/bookings'],
        'providers' => ['label' => 'Providers', 'url' => 'admin/providers'],
        'users'     => ['label' => 'Users',      'url' => 'admin/users'],
        'reports'   => ['label' => 'Reports',    'url' => 'admin/reports'],
    ];
    ?>
<nav class="pv-nav">
  <div class="pv-nav-inner">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
    <!-- Logo -->
    <a class="pv-logo" href="<?= BASE_URL ?>admin/dashboard">
      Quick<span>Book</span>
      <span class="pv-logo-badge">ADMIN</span>
    </a>

    <!-- Centre links -->
    <div class="pv-nav-links">
      <?php foreach ($links as $key => $link): ?>
        <a class="pv-nav-link <?= $active === $key ? 'is-active' : '' ?>"
           href="<?= BASE_URL . $link['url'] ?>">
          <?= $link['label'] ?>
        </a>
      <?php endforeach ?>
    </div>

    <!-- Right side -->
    <div class="pv-nav-end">

      <button class="pv-notif-btn" title="Notifications">
        🔔
        <span class="pv-notif-dot"></span>
      </button>

      <div class="pv-nav-user">
        <div class="pv-nav-av"><?= $initials ?></div>
        <div style="display:flex;flex-direction:column;line-height:1.25">
          <span style="font-size:.83rem;font-weight:600;color:#fff"><?= $name ?></span>
          <span style="font-size:.65rem;letter-spacing:.06em;text-transform:uppercase;color:rgba(255,255,255,.35)">Administrator</span>
        </div>
      </div>

      <form method="POST" action="<?= BASE_URL ?>auth/logout" style="margin:0">
        <button type="submit" class="pv-nav-logout">Sign out →</button>
      </form>

    </div>
  </div>
</nav>
<?php } ?>