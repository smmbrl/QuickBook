<?php
// app/views/admin/_nav.php
// Left sidebar navigation + top header bar — cream & gold theme
function adminNav(string $active = ''): void {
    $name     = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
    $initials = strtoupper(substr($name, 0, 2));

    $links = [
        'dashboard' => ['label' => 'Dashboard',  'icon' => 'fa-gauge-high',      'url' => 'admin/dashboard'],
        'bookings'  => ['label' => 'Bookings',   'icon' => 'fa-calendar-check',  'url' => 'admin/bookings'],
        'providers' => ['label' => 'Providers',  'icon' => 'fa-store',           'url' => 'admin/providers'],
        'users'     => ['label' => 'Users',      'icon' => 'fa-users',           'url' => 'admin/users'],
        'reports'   => ['label' => 'Reports',    'icon' => 'fa-chart-bar',       'url' => 'admin/reports'],
        'logs'      => ['label' => 'Logs',       'icon' => 'fa-scroll',          'url' => 'admin/logs'],
    ];
    ?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ══════════════ TOP HEADER BAR ══════════════ -->
<header class="adm-topbar">

  <!-- Left: mobile hamburger (hidden on desktop) -->
  <div class="adm-topbar-left">
    <button class="adm-sb-toggle-inline" id="admToggleInline" aria-label="Toggle menu">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>

  <!-- Right: profile chip · notifications · dark mode -->
  <div class="adm-topbar-right">

    <!-- Profile chip -->
    <div class="adm-topbar-profile">
      <div class="adm-topbar-av"><?= $initials ?></div>
      <div class="adm-topbar-profile-info">
        <span class="adm-topbar-profile-name"><?= $name ?></span>
        <span class="adm-topbar-profile-role">Administrator</span>
      </div>
    </div>

    <!-- Notification bell -->
    <?php
      require_once __DIR__ . '/../../../config/database.php';
      $db = Database::getInstance();
      $notifUserId = (int)($_SESSION['user_id'] ?? 0);
      require __DIR__ . '/../_partials/notification_panel.php';
    ?>

    <!-- Dark mode toggle -->
    <button class="adm-topbar-btn adm-darkmode-btn" id="admDarkToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
      <!-- Sun: shown in light mode -->
      <svg id="admIconSun" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
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
      <!-- Moon: shown in dark mode -->
      <svg id="admIconMoon" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
           viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
    </button>

  </div>
</header>

<!-- ══════════════ SIDEBAR ══════════════ -->
<aside class="adm-sidebar-nav" id="admSidebar">

  <!-- Logo -->
  <div class="adm-sb-logo-wrap">
    <a class="adm-sb-logo" href="<?= BASE_URL ?>admin/dashboard">
      <span class="adm-sb-logo-quick">Quick</span><span class="adm-sb-logo-book">Book</span>
    </a>
    <span class="adm-sb-badge">ADMIN</span>
  </div>

  <!-- Menu label -->
  <div class="adm-sb-section-label">MENU</div>

  <!-- Nav links -->
  <nav class="adm-sb-nav">
    <?php foreach ($links as $key => $link): ?>
      <a
        class="adm-sb-link <?= $active === $key ? 'is-active' : '' ?>"
        href="<?= BASE_URL . $link['url'] ?>"
      >
        <span class="adm-sb-link-ico">
          <i class="fa-solid <?= $link['icon'] ?>"></i>
        </span>
        <span class="adm-sb-link-label"><?= $link['label'] ?></span>
        <?php if ($active === $key): ?>
          <span class="adm-sb-link-dot"></span>
        <?php endif ?>
      </a>
    <?php endforeach ?>
  </nav>

  <!-- Push logout to bottom -->
  <div class="adm-sb-spacer"></div>

  <!-- Logout only — no label, no border, no profile block -->
  <div class="adm-sb-logout-wrap">
    <form method="POST" action="<?= BASE_URL ?>auth/logout">
      <button type="submit" class="adm-sb-logout-btn">
        <span class="adm-sb-link-ico">
          <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </span>
        <span class="adm-sb-link-label">Sign Out</span>
      </button>
    </form>
  </div>

</aside>

<!-- Mobile overlay -->
<div class="adm-sb-overlay" id="admOverlay"
  onclick="document.getElementById('admSidebar').classList.remove('is-open');this.classList.remove('is-visible')">
</div>

<!-- Mobile toggle (floating, shown only on small screens) -->
<button class="adm-sb-toggle" id="admToggle" aria-label="Toggle menu">
  <i class="fa-solid fa-bars"></i>
</button>

<script>
(function() {
  // ── Dark mode — apply before paint to avoid flash ──
  const html  = document.documentElement;
  const saved = localStorage.getItem('qb-admin-theme') || 'light';

  function applyTheme(t) {
    html.setAttribute('data-theme', t);
    const moon = document.getElementById('admIconMoon');
    const sun  = document.getElementById('admIconSun');
    if (t === 'dark') {
      // dark mode → show moon, hide sun
      if (sun)  sun.style.display  = 'none';
      if (moon) moon.style.display = 'block';
    } else {
      // light mode → show sun, hide moon
      if (sun)  sun.style.display  = 'block';
      if (moon) moon.style.display = 'none';
    }
    localStorage.setItem('qb-admin-theme', t);
  }

  // Apply immediately
  html.setAttribute('data-theme', saved);
  document.addEventListener('DOMContentLoaded', () => applyTheme(saved));

  const darkBtn = document.getElementById('admDarkToggle');
  if (darkBtn) {
    darkBtn.addEventListener('click', () => {
      applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    });
  }

  // ── Hide topbar on scroll down, show on scroll up ──
  let lastScrollY = 0;
  const topbar = document.querySelector('.adm-topbar');
  window.addEventListener('scroll', () => {
    const current = window.scrollY;
    if (topbar) {
      if (current > lastScrollY && current > 60) {
        topbar.style.opacity = '0';
        topbar.style.pointerEvents = 'none';
        topbar.style.transform = 'translateY(-100%)';
      } else {
        topbar.style.opacity = '1';
        topbar.style.pointerEvents = '';
        topbar.style.transform = 'translateY(0)';
      }
    }
    lastScrollY = current;
  }, { passive: true });

  // ── Mobile sidebar ──
  function bindToggle(id) {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.addEventListener('click', () => {
      document.getElementById('admSidebar').classList.toggle('is-open');
      document.getElementById('admOverlay').classList.toggle('is-visible');
    });
  }
  bindToggle('admToggle');
  bindToggle('admToggleInline');
})();
</script>

<?php } ?>