<?php
// register.php is loaded by AuthViewController::showRegister()
// which already sets $error and $success from flash session.
$error   = $error   ?? null;
$success = $success ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Create Account</title>
  <!-- Same Google Fonts stack as the home page: Syne + DM Sans + DM Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/register.css">
  <!-- Apply saved theme before render to prevent flash (mirrors home.php) -->
  <script>
    (function(){
      var t = localStorage.getItem('qb-theme') || 'dark';
      if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    })();
  </script>
</head>
<body>

<div class="register-page">

  <!-- LEFT PANEL -->
  <div class="register-left">
    <div class="register-left-bg"></div>

    <a href="<?= BASE_URL ?>home" class="register-left-logo">
      Quick<span>Book</span>
    </a>

    <h2>
      Join your<br>
      <em>community today.</em>
    </h2>

    <p class="register-left-desc">
      Create a free account and start booking trusted local
      service providers — or list your own services and
      grow your business digitally in Bacolod City.
    </p>

    <ul class="feature-list">
      <li class="feature-item">
        <span class="feature-check">✓</span>
        Free to sign up — no hidden fees
      </li>
      <li class="feature-item">
        <span class="feature-check">✓</span>
        Real-time availability, zero double-bookings
      </li>
      <li class="feature-item">
        <span class="feature-check">✓</span>
        Secure GCash, PayMaya &amp; card payments
      </li>
      <li class="feature-item">
        <span class="feature-check">✓</span>
        Earn loyalty points on every booking
      </li>
      <li class="feature-item">
        <span class="feature-check">✓</span>
        Home service options available
      </li>
    </ul>

    <div class="auth-stats">
      <div class="auth-stat">
        <div class="auth-stat-number">298+</div>
        <div class="auth-stat-label">Local Providers</div>
      </div>
      <div class="auth-stat-divider"></div>
      <div class="auth-stat">
        <div class="auth-stat-number">5,200+</div>
        <div class="auth-stat-label">Happy Customers</div>
      </div>
      <div class="auth-stat-divider"></div>
      <div class="auth-stat">
        <div class="auth-stat-number">4.8 ⭐</div>
        <div class="auth-stat-label">Avg. Rating</div>
      </div>
    </div>

  </div>

  <!-- RIGHT PANEL -->
  <div class="register-right">
    <div class="register-form-box">

      <!-- Progress steps -->
      <div class="register-step-label">Account Setup</div>
      <div class="register-steps">
        <div class="r-step active"></div>
        <div class="r-step"></div>
        <div class="r-step"></div>
      </div>

      <div class="form-heading">Create your account</div>
      <p class="form-subheading">
        Already have one?
        <a href="<?= BASE_URL ?>login">Sign in here</a>
      </p>

      <!-- Flash error -->
      <?php if ($error): ?>
        <div style="
          background: rgba(239,68,68,.12);
          border: 1px solid rgba(239,68,68,.35);
          color: #FCA5A5;
          border-radius: var(--radius-sm);
          padding: .8rem 1rem;
          font-family: var(--font-mono);
          font-size: .75rem;
          letter-spacing: .02em;
          margin-bottom: 1.2rem;
          display: flex;
          gap: .6rem;
          align-items: flex-start;
          line-height: 1.6;
        ">
          <span style="font-size:1rem;line-height:1.4;flex-shrink:0">⚠</span>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- Flash success -->
      <?php if ($success): ?>
        <div style="
          background: rgba(34,197,94,.1);
          border: 1px solid rgba(34,197,94,.3);
          color: #86EFAC;
          border-radius: var(--radius-sm);
          padding: .8rem 1rem;
          font-family: var(--font-mono);
          font-size: .75rem;
          letter-spacing: .02em;
          margin-bottom: 1.2rem;
          display: flex;
          gap: .6rem;
          align-items: flex-start;
          line-height: 1.6;
        ">
          <span style="font-size:1rem;line-height:1.4;flex-shrink:0">✓</span>
          <span><?= htmlspecialchars($success) ?></span>
        </div>
      <?php endif; ?>

      <!-- Form -->
      <form action="<?= BASE_URL ?>auth/register" method="POST" autocomplete="off" id="register-form">

        <!-- Role Selector -->
        <div class="role-selector-label">I want to:</div>
        <div class="role-selector" id="role-selector">

          <div class="role-option selected"
               id="role-customer"
               data-role="customer"
               tabindex="0"
               role="button"
               aria-pressed="true">
            <span class="role-icon">👤</span>
            <span class="role-label">Book Services</span>
            <span class="role-sub">I'm a customer</span>
          </div>

          <div class="role-option"
               id="role-provider"
               data-role="provider"
               tabindex="0"
               role="button"
               aria-pressed="false">
            <span class="role-icon">💼</span>
            <span class="role-label">Offer Services</span>
            <span class="role-sub">I'm a provider</span>
          </div>

        </div>
        <!-- Hidden role field — always synced before submit -->
        <input type="hidden" name="role" id="role-input" value="customer">

        <!-- Name Row -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" placeholder="Maria" required autocomplete="off">
            <div class="field-error">First name is required.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" placeholder="Santos" required autocomplete="off">
            <div class="field-error">Last name is required.</div>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="you@example.com" required autocomplete="off">
          <div class="field-error">A valid email is required.</div>
        </div>

        <!-- Phone -->
        <div class="form-group">
          <label class="form-label">Mobile Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="+63 917 000 0000" required autocomplete="off">
        </div>

        <!-- Gender & Date of Birth Row -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-control" >
              <option value="" disabled selected >Select gender</option>
              <option value="male"             >Male</option>
              <option value="female"           >Female</option>
              <option value="non_binary"       >Non-binary</option>
              <option value="prefer_not_to_say">Prefer not to say</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control" autocomplete="off"
              
              max="<?= date('Y-m-d', strtotime('-13 years')) ?>">
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="pw-wrap">
            <input
              type="password"
              name="password"
              id="reg-pw"
              class="form-control"
              placeholder="Create a password"
              required
              autocomplete="new-password"
              oninput="checkStrength(this.value)"
            >
            <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Toggle password visibility">
              <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <svg id="eye-off-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
              </svg>
            </button>
          </div>
          <div class="strength-bar">
            <div class="strength-segment" id="seg1"></div>
            <div class="strength-segment" id="seg2"></div>
            <div class="strength-segment" id="seg3"></div>
            <div class="strength-segment" id="seg4"></div>
          </div>
          <div class="strength-label" id="strength-label"></div>
          <div class="field-error">Password must be at least 8 characters.</div>
        </div>

        <!-- Terms -->
        <div class="terms-row">
          <input type="checkbox" id="terms" name="terms" required>
          <label for="terms">
            I agree to the
            <a href="#">Terms of Service</a>
            and
            <a href="#">Privacy Policy</a>
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-submit" id="submit-btn">
          Create Account
        </button>

      </form>

      <div class="divider">or sign up with</div>

      <button class="social-btn" type="button">
        <svg class="social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Continue with Google
      </button>

      <button class="social-btn" type="button">
        <svg class="social-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18">
          <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" fill="#1877F2"/>
        </svg>
        Continue with Facebook
      </button>

    </div>
  </div>

</div>


<script>
  /* Prevent browser autofill */
  document.querySelectorAll('.register-right .form-control').forEach(function(input) {
    input.setAttribute('readonly', true);
    input.addEventListener('focus', function() { this.removeAttribute('readonly'); });
  });

  /* ═══════════════════════════════════════════════════════════
     ROLE SELECTOR — fixed with event delegation
     Clicks on child elements (icon, label text) are bubbled up
     to the container and resolved via .closest('[data-role]').
  ═══════════════════════════════════════════════════════════ */
  var roleInput = document.getElementById('role-input');

  function selectRole(role) {
    document.querySelectorAll('#role-selector .role-option').forEach(function(opt) {
      opt.classList.remove('selected');
      opt.setAttribute('aria-pressed', 'false');
    });
    var chosen = document.querySelector('#role-selector [data-role="' + role + '"]');
    if (chosen) {
      chosen.classList.add('selected');
      chosen.setAttribute('aria-pressed', 'true');
    }
    roleInput.value = role;
  }

  /* Click — delegate to container */
  document.getElementById('role-selector').addEventListener('click', function(e) {
    var opt = e.target.closest('.role-option[data-role]');
    if (opt) selectRole(opt.getAttribute('data-role'));
  });

  /* Keyboard accessibility */
  document.getElementById('role-selector').addEventListener('keydown', function(e) {
    if (e.key === ' ' || e.key === 'Enter') {
      var opt = e.target.closest('.role-option[data-role]');
      if (opt) { e.preventDefault(); selectRole(opt.getAttribute('data-role')); }
    }
  });

  /* Safety net — re-confirm role value right before submit */
  document.getElementById('register-form').addEventListener('submit', function() {
    var selected = document.querySelector('#role-selector .role-option.selected');
    if (selected) roleInput.value = selected.getAttribute('data-role');
    var btn = document.getElementById('submit-btn');
    if (btn) { btn.textContent = 'Creating account…'; btn.disabled = true; }
  });

  /* Password toggle */
  function togglePw() {
    var input  = document.getElementById('reg-pw');
    var eyeOn  = document.getElementById('eye-icon');
    var eyeOff = document.getElementById('eye-off-icon');
    var hidden = input.type === 'password';
    input.type           = hidden ? 'text'    : 'password';
    eyeOn.style.display  = hidden ? 'none'    : '';
    eyeOff.style.display = hidden ? ''        : 'none';
  }

  /* Password strength */
  function checkStrength(value) {
    var segs  = ['seg1','seg2','seg3','seg4'].map(function(id){ return document.getElementById(id); });
    var label = document.getElementById('strength-label');
    segs.forEach(function(s){ s.className = 'strength-segment'; });
    label.textContent = '';
    if (!value.length) return;
    var score = 0;
    if (value.length >= 8)          score++;
    if (/[A-Z]/.test(value))        score++;
    if (/[0-9]/.test(value))        score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;
    var colors = ['weak','fair','fair','strong'];
    var labels = ['Weak','Fair','Good','Strong'];
    for (var i = 0; i < score; i++) segs[i].classList.add(colors[score - 1]);
    label.textContent = 'Password strength: ' + labels[score - 1];
  }
</script>

</body>
</html>