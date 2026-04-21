<?php
// Screen 2a — Login Page
// View: app/views/login.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Sign In</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/login.css">
</head>
<body>

<div class="login-page">

  <!-- ════════════════════════════════════════════
       LEFT PANEL
  ════════════════════════════════════════════ -->
  <div class="login-left">

    <div class="login-left-bg"></div>

    <a href="<?= BASE_URL ?>home" class="login-left-logo">
      Quick<span>Book</span>
    </a>

    <h2>
      Welcome<br>
      <em><span class="underline-accent">back!</span></em>
    </h2>

    <p class="login-left-desc">
      Sign in to manage your bookings, check your loyalty
      points, and discover trusted local service providers
      near you in Bacolod City.
    </p>

    <ul class="feature-list">
      <li class="feature-item">
        <span class="feature-check">✓</span>
        View and manage all your bookings
      </li>
      <li class="feature-item">
        <span class="feature-check">✓</span>
        Check your loyalty points balance
      </li>
      <li class="feature-item">
        <span class="feature-check">✓</span>
        Rebook your favourite providers instantly
      </li>
      <li class="feature-item">
        <span class="feature-check">✓</span>
        Get real-time booking notifications
      </li>
      <li class="feature-item">
        <span class="feature-check">✓</span>
        Securely pay via GCash or PayMaya
      </li>
    </ul>

    <div class="testimonial-card">
      <p class="testimonial-quote">
        QuickBook changed how I run my barbershop. I get more
        clients, zero no-shows, and it's so easy to manage
        my schedule.
      </p>
      <div class="testimonial-author">
        <div class="testimonial-avatar">RB</div>
        <div>
          <div class="testimonial-name">Raffy Bautista</div>
          <div class="testimonial-role">Barber · Sum-ag, Bacolod City</div>
          <div class="testimonial-stars">★★★★★</div>
        </div>
      </div>
    </div>

  </div>

  <!-- ════════════════════════════════════════════
       RIGHT PANEL
  ════════════════════════════════════════════ -->
  <div class="login-right">
    <div class="login-form-box">

      <div class="form-heading">Sign in to your account</div>
      <p class="form-subheading">
        Don't have an account?
        <a href="<?= BASE_URL ?>register">Create one free</a>
      </p>

      <!-- Error alert -->
      <div class="alert alert-error" id="login-error" style="display:none">
        ✗ &nbsp; Invalid email or password. Please try again.
      </div>

      <!-- Success alert -->
      <div class="alert alert-success" id="login-success" style="display:none">
        ✓ &nbsp; Email verified! You can now log in.
      </div>

      <form action="<?= BASE_URL ?>auth/login" method="POST">

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

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="pw-wrap">
            <input
              type="password"
              name="password"
              id="login-pw"
              class="form-control"
              placeholder="Enter your password"
              required
              autocomplete="current-password"
            >
            <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Toggle password visibility">
              <!-- Eye open -->
              <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <!-- Eye off -->
              <svg id="eye-off-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="form-extras">
          <label class="remember-label">
            <input type="checkbox" name="remember">
            Remember me
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-submit">
          Sign In →
        </button>

      </form>

      <div class="divider">or continue with</div>

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

<div class="screen-label">Screen 2a / 10 — Login</div>

<script>
  function togglePw() {
    const input  = document.getElementById('login-pw');
    const eyeOn  = document.getElementById('eye-icon');
    const eyeOff = document.getElementById('eye-off-icon');
    const isHidden = input.type === 'password';
    input.type           = isHidden ? 'text'    : 'password';
    eyeOn.style.display  = isHidden ? 'none'    : '';
    eyeOff.style.display = isHidden ? ''        : 'none';
  }
</script>

</body>
</html>