<?php

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Set New Password</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login.css">
</head>
<body>

<div class="login-page">

  <div class="login-left">
    <div class="login-left-bg"></div>
    <a href="<?= BASE_URL ?>home" class="login-left-logo">Quick<span>Book</span></a>
    <h2>Set a new<br><em><span class="underline-accent">password.</span></em></h2>
    <p class="login-left-desc">
      Choose a strong password you haven't used before.
      Your account will be secured immediately after saving.
    </p>
    <ul class="feature-list">
      <li class="feature-item"><span class="feature-check">✓</span> Minimum 8 characters</li>
      <li class="feature-item"><span class="feature-check">✓</span> Mix letters, numbers, and symbols</li>
      <li class="feature-item"><span class="feature-check">✓</span> Don't reuse an old password</li>
    </ul>
  </div>

  <div class="login-right">
    <div class="login-form-box">

      <div class="form-heading">Set a new password</div>
      <p class="form-subheading">
        Back to <a href="<?= BASE_URL ?>login">sign in</a>
      </p>

      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= $flash['type'] === 'success' ? '✓' : '✗' ?> &nbsp;
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <form action="<?= BASE_URL ?>auth/reset-password" method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="pw-wrap">
            <input
              type="password"
              name="new_password"
              id="new-pw"
              class="form-control"
              placeholder="At least 8 characters"
              required
              minlength="8"
              autocomplete="new-password"
            >
            <button type="button" class="pw-toggle" onclick="togglePw('new-pw','eye1','eye1off')" aria-label="Toggle password">
              <svg id="eye1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <svg id="eye1off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div class="pw-wrap">
            <input
              type="password"
              name="confirm_password"
              id="confirm-pw"
              class="form-control"
              placeholder="Repeat your new password"
              required
              minlength="8"
              autocomplete="new-password"
            >
            <button type="button" class="pw-toggle" onclick="togglePw('confirm-pw','eye2','eye2off')" aria-label="Toggle password">
              <svg id="eye2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <svg id="eye2off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
              </svg>
            </button>
          </div>
          <div id="pw-match-hint" style="font-size:.75rem;margin-top:.35rem;min-height:1rem"></div>
        </div>

        <button type="submit" class="btn btn-primary btn-submit" id="resetSubmitBtn">
          Save New Password →
        </button>

      </form>

    </div>
  </div>

</div>

<script>
function togglePw(inputId, eyeOnId, eyeOffId) {
  const input  = document.getElementById(inputId);
  const eyeOn  = document.getElementById(eyeOnId);
  const eyeOff = document.getElementById(eyeOffId);
  const hidden = input.type === 'password';
  input.type           = hidden ? 'text'    : 'password';
  eyeOn.style.display  = hidden ? 'none'    : '';
  eyeOff.style.display = hidden ? ''        : 'none';
}


const newPw    = document.getElementById('new-pw');
const confirmPw = document.getElementById('confirm-pw');
const hint     = document.getElementById('pw-match-hint');
const submitBtn = document.getElementById('resetSubmitBtn');

function checkMatch() {
  if (!confirmPw.value) { hint.textContent = ''; return; }
  const match = newPw.value === confirmPw.value;
  hint.textContent  = match ? '✓ Passwords match' : '✗ Passwords do not match';
  hint.style.color  = match ? '#4ADE80' : '#FB7185';
  submitBtn.disabled = !match;
}

newPw.addEventListener('input', checkMatch);
confirmPw.addEventListener('input', checkMatch);
</script>

</body>
</html>