<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Identity — QuickBook</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth_2fa.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- Nav -->
<nav class="pv-nav">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Security</span>
    </a>
    <a href="<?= BASE_URL ?>login" class="pv-nav-back">
      <i class="fa-solid fa-arrow-left" style="font-size:.6rem;margin-right:.3rem"></i> Back to Login
    </a>
  </div>
</nav>

<!-- Page -->
<div class="tfa-page">
  <div class="tfa-card" style="max-width:480px">
    <div class="tfa-center">

      <!-- Icon -->
      <div class="tfa-shield">
        <i class="fa-solid fa-shield-halved" style="color:var(--gold-dim);font-size:1.5rem"></i>
      </div>

      <!-- Title -->
      <div>
        <h1 class="tfa-title">Verify Your Identity</h1>
        <p class="tfa-subtitle" style="margin-top:.3rem">Enter the 6-digit code from your authenticator app to continue.</p>
      </div>

      <!-- Error -->
      <?php if (!empty($error)): ?>
        <div class="tfa-flash error">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <!-- OTP Boxes -->
      <form method="POST" action="<?= BASE_URL ?>auth/2fa/verify" id="verify-form" style="width:100%;display:flex;flex-direction:column;gap:.9rem">
        <input type="hidden" name="otp" id="otp-hidden">
        <div class="tfa-otp-boxes" id="otp-boxes">
          <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" class="tfa-otp-box"
              maxlength="1" pattern="[0-9]" inputmode="numeric"
              autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
              data-index="<?= $i ?>">
          <?php endfor; ?>
        </div>
        <button type="submit" class="tfa-btn">
          <i class="fa-solid fa-lock" style="font-size:.8rem"></i>
          Verify &amp; Sign In
        </button>
      </form>

      <div class="tfa-divider"></div>

      <p class="tfa-hint">
        Code refreshes every <strong>30 seconds</strong>.<br>
        Open <strong>Google Authenticator</strong> or <strong>Authy</strong>.
      </p>

    </div>
  </div>
</div>

<script>
  const boxes  = Array.from(document.querySelectorAll('.tfa-otp-box'));
  const hidden = document.getElementById('otp-hidden');
  const form   = document.getElementById('verify-form');

  boxes[0].focus();

  boxes.forEach((box, i) => {
    box.addEventListener('input', e => {
      const val = e.target.value.replace(/\D/g, '');
      e.target.value = val;
      if (val && i < 5) boxes[i + 1].focus();
      hidden.value = boxes.map(b => b.value).join('');
      if (hidden.value.length === 6) setTimeout(() => form.submit(), 150);
    });
    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !box.value && i > 0) {
        boxes[i - 1].focus(); boxes[i - 1].value = '';
      }
    });
    box.addEventListener('paste', e => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      pasted.split('').forEach((ch, j) => { if (boxes[j]) boxes[j].value = ch; });
      hidden.value = boxes.map(b => b.value).join('');
      if (hidden.value.length === 6) form.submit();
    });
  });
</script>
</body>
</html>