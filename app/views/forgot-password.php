<?php

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Forgot Password</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login.css">
</head>
<body>

<div class="login-page">

  <div class="login-left">
    <div class="login-left-bg"></div>
    <a href="<?= BASE_URL ?>home" class="login-left-logo">Quick<span>Book</span></a>
    <h2>Reset your<br><em><span class="underline-accent">password.</span></em></h2>
    <p class="login-left-desc">
      Enter the email address linked to your QuickBook account and
      we'll send you a secure link to create a new password.
    </p>
    <ul class="feature-list">
      <li class="feature-item"><span class="feature-check">✓</span> Link expires in 1 hour for security</li>
      <li class="feature-item"><span class="feature-check">✓</span> No account? No email will be sent</li>
      <li class="feature-item"><span class="feature-check">✓</span> Check your spam folder if needed</li>
    </ul>
  </div>

  <div class="login-right">
    <div class="login-form-box">

      <div class="form-heading">Forgot your password?</div>
      <p class="form-subheading">
        Remembered it?
        <a href="<?= BASE_URL ?>login">Back to sign in</a>
      </p>

      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= $flash['type'] === 'success' ? '✓' : '✗' ?> &nbsp;
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <?php if (!isset($flash) || $flash['type'] !== 'success'): ?>
      <form action="<?= BASE_URL ?>auth/forgot-password" method="POST">

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input
            type="email"
            name="email"
            class="form-control"
            placeholder="you@example.com"
            required
            autocomplete="email"
          >
        </div>

        <button type="submit" class="btn btn-primary btn-submit">
          Send Reset Link →
        </button>

      </form>
      <?php endif; ?>

    </div>
  </div>

</div>

</body>
</html>