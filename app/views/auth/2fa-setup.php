<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Two-Factor Authentication — QuickBook</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth_2fa.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

<!-- Nav -->
<nav class="pv-nav">
  <div class="pv-nav-inner">
    <a href="<?= BASE_URL ?>home" class="pv-logo">
      Quick<span>Book</span>
      <span class="pv-logo-badge">Security</span>
    </a>
    <a href="<?= BASE_URL ?>profile" class="pv-nav-back">
      <i class="fa-solid fa-arrow-left" style="font-size:.6rem;margin-right:.3rem"></i> Back to Profile
    </a>
  </div>
</nav>

<!-- Page -->
<div class="tfa-page">
  <div class="tfa-card">
    <div class="tfa-grid">

      <!-- LEFT: Info + Form -->
      <div class="tfa-left">

        <?php if (!empty($_SESSION['flash_error'])): ?>
          <div class="tfa-flash error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
          </div>
          <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_success'])): ?>
          <div class="tfa-flash success">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
          </div>
          <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <div>
          <h1 class="tfa-title">Two-Factor<br>Authentication</h1>
          <p class="tfa-subtitle" style="margin-top:.35rem">Add an extra layer of security to your account.</p>
        </div>

        <?php if (!empty($alreadyEnabled)): ?>
          <div class="tfa-badge">
            <i class="fa-solid fa-shield-halved" style="font-size:.65rem"></i>
            2FA is currently active
          </div>
          <div class="tfa-instruction">Re-scan the QR code to re-link your authenticator app, then enter the 6-digit code to confirm.</div>
        <?php else: ?>
          <div class="tfa-steps">
            <div class="tfa-step done"></div>
            <div class="tfa-step active"></div>
            <div class="tfa-step"></div>
          </div>
          <div class="tfa-instruction">
            <strong>Step 1:</strong> Install <strong>Google Authenticator</strong> or <strong>Authy</strong> on your phone.<br><br>
            <strong>Step 2:</strong> Scan the QR code on the right, then enter the 6-digit code it generates below.
          </div>
        <?php endif; ?>

        <div class="tfa-divider"></div>

        <!-- Secret key -->
        <div class="tfa-secret">
          <div>
            <span class="tfa-secret-label">Can't scan? Enter this key manually:</span>
            <span class="tfa-secret-value" id="secret-text"><?= htmlspecialchars($secret) ?></span>
          </div>
          <button class="tfa-copy-btn" onclick="copySecret()">Copy</button>
        </div>

        <!-- OTP Form -->
        <form method="POST" action="<?= BASE_URL ?>auth/2fa/enable" style="display:flex;flex-direction:column;gap:.65rem">
          <div>
            <label class="tfa-otp-label" for="otp" style="margin-bottom:.4rem;display:block">Enter the 6-digit code from your app</label>
            <input
              type="text" id="otp" name="otp"
              class="tfa-otp-input"
              maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
              autocomplete="one-time-code" placeholder="000 000"
              required autofocus>
          </div>
          <button type="submit" class="tfa-btn">
            <i class="fa-solid fa-shield-halved" style="font-size:.8rem"></i>
            Verify &amp; Enable 2FA
          </button>
        </form>

      </div>

      <!-- RIGHT: QR Code -->
      <div class="tfa-right">
        <p class="tfa-qr-label"><i class="fa-solid fa-qrcode" style="color:var(--gold-dim);margin-right:.35rem"></i>Scan with your authenticator app</p>
        <div class="tfa-qr-wrap">
          <div id="qrcode"></div>
        </div>
        <p class="tfa-hint">Point your camera at the QR code.<br>Open <strong>Google Authenticator</strong> or <strong>Authy</strong>.</p>
      </div>

    </div>
  </div>
</div>

<script>
  const otpauthUrl = <?= json_encode($otpauthUrl) ?>;
  new QRCode(document.getElementById('qrcode'), {
    text: otpauthUrl, width: 200, height: 200,
    colorDark: '#000000', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
  });

  function copySecret() {
    const text = document.getElementById('secret-text').textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
      const btn = document.querySelector('.tfa-copy-btn');
      btn.textContent = 'Copied!';
      setTimeout(() => btn.textContent = 'Copy', 2000);
    });
  }

  document.getElementById('otp').addEventListener('input', function () {
    if (this.value.length === 6) this.closest('form').submit();
  });
</script>
</body>
</html>