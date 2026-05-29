<?php
// register.php is loaded by AuthViewController::showRegister()
$error   = $error   ?? null;
$success = $success ?? null;

// ── LIVE STATS FROM DATABASE ──────────────────────────────
$stat_providers  = 0;
$stat_customers  = 0;
$stat_avg_rating = 0.0;

try {
    $db = Database::getInstance();

    $stat_providers = (int) $db->query(
        "SELECT COUNT(*) FROM tbl_provider_profiles WHERE is_approved = 1"
    )->fetchColumn();

    $stat_customers = (int) $db->query(
        "SELECT COUNT(*) FROM tbl_users WHERE role = 'customer'"
    )->fetchColumn();

    $stat_avg_rating = (float) $db->query(
        "SELECT COALESCE(ROUND(AVG(avg_rating), 1), 0)
         FROM tbl_provider_profiles
         WHERE is_approved = 1 AND avg_rating > 0"
    )->fetchColumn();

} catch (Exception $e) { /* DB unavailable */ }

$label_providers  = $stat_providers  > 0 ? number_format($stat_providers)  . '+' : '0+';
$label_customers  = $stat_customers  > 0 ? number_format($stat_customers)  . '+' : '0+';
$label_avg_rating = $stat_avg_rating > 0 ? number_format($stat_avg_rating, 1)    : '0.0';

// ── GOOGLE MAPS API KEY — set in config or .env ───────────
$google_maps_key = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuickBook — Create Account</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,800;0,900;1,700;1,800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/register.css">

  <?php if ($google_maps_key): ?>
  <!-- Google Maps Places for address autocomplete -->
  <script>
    window.QB_MAPS_KEY = <?= json_encode($google_maps_key) ?>;
  </script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($google_maps_key) ?>&libraries=places&callback=initAddressAutocomplete"
    async defer>
  </script>
  <?php endif; ?>

  <style>body { background: #F9F7F2 !important; color: #1A1410 !important; }</style>
</head>
<body>

<div class="register-page">

  <!-- ════════════════════════════════════════
       LEFT PANEL
  ════════════════════════════════════════ -->
  <div class="register-left">
    <div class="register-left-bg"></div>

    <a href="<?= BASE_URL ?>home" class="register-left-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png"
           alt="QuickBook Logo" class="auth-logo-img">
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
        Verified address — safe, reliable bookings
      </li>
    </ul>

    <!-- Live DB stats -->
    <div class="auth-stats">
      <div class="auth-stat">
        <div class="auth-stat-number"><?= htmlspecialchars($label_providers) ?></div>
        <div class="auth-stat-label">Local Providers</div>
      </div>
      <div class="auth-stat-divider"></div>
      <div class="auth-stat">
        <div class="auth-stat-number"><?= htmlspecialchars($label_customers) ?></div>
        <div class="auth-stat-label">Happy Customers</div>
      </div>
      <div class="auth-stat-divider"></div>
      <div class="auth-stat">
        <div class="auth-stat-number"><?= htmlspecialchars($label_avg_rating) ?> ⭐</div>
        <div class="auth-stat-label">Avg. Rating</div>
      </div>
    </div>

  </div><!-- /register-left -->

  <!-- ════════════════════════════════════════
       RIGHT PANEL
  ════════════════════════════════════════ -->
  <div class="register-right">
    <div class="register-form-box">

      <!-- Progress bar -->
      <div class="register-step-label" id="step-label">Account Setup</div>
      <div class="register-steps">
        <div class="r-step" id="bar-1"></div>
        <div class="r-step" id="bar-2"></div>
        <div class="r-step" id="bar-3"></div>
        <div class="r-step" id="bar-4"></div>
      </div>

      <div class="form-heading">Create your account</div>
      <p class="form-subheading">
        Already have one?
        <a href="<?= BASE_URL ?>login">Sign in here</a>
      </p>

      <?php if ($error): ?>
        <div class="flash-msg flash-error">
          <span>⚠</span><span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="flash-msg flash-success">
          <span>✓</span><span><?= htmlspecialchars($success) ?></span>
        </div>
      <?php endif; ?>

      <form action="<?= BASE_URL ?>auth/register" method="POST"
            autocomplete="off" id="register-form" novalidate>

        <input type="hidden" name="role"           id="role-input"            value="customer">
        <input type="hidden" name="address_lat"    id="f-addr-lat"            value="">
        <input type="hidden" name="address_lng"    id="f-addr-lng"            value="">
        <input type="hidden" name="address_place"  id="f-addr-place"          value="">
        <input type="hidden" name="address_verified" id="f-addr-verified"     value="0">

        <!-- ── ROLE ── -->
        <div class="role-selector-label">I want to:</div>
        <div class="role-selector" id="role-selector">
          <div class="role-option selected" data-role="customer"
               tabindex="0" role="button" aria-pressed="true">
            <span class="role-icon">👤</span>
            <span class="role-label">Book Services</span>
            <span class="role-sub">I'm a customer</span>
          </div>
          <div class="role-option" data-role="provider"
               tabindex="0" role="button" aria-pressed="false">
            <span class="role-icon">💼</span>
            <span class="role-label">Offer Services</span>
            <span class="role-sub">I'm a provider</span>
          </div>
        </div>

        <!-- ══════════════════════════════════════════
             PROVIDER-ONLY SECTION
        ══════════════════════════════════════════ -->
        <div class="provider-section" id="provider-section" style="display:none">

          <!-- Business Name -->
          <div class="form-section-label">Business Details</div>

          <div class="form-group">
            <label class="form-label" for="f-biz-name">Business Name <span class="req-star">*</span></label>
            <div class="input-icon-wrap">
              <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 3l9 6.75V21a.75.75 0 01-.75.75H15.75A.75.75 0 0115 21v-5.25H9V21a.75.75 0 01-.75.75H3.75A.75.75 0 013 21V9.75z"/>
              </svg>
              <input type="text" name="business_name" id="f-biz-name" class="form-control input-with-icon"
                     placeholder="e.g. Santos Cleaning Services" autocomplete="off">
            </div>
            <div class="field-error" id="err-biz-name">Business name is required for providers.</div>
          </div>

          <!-- Service Type -->
          <div class="form-group" style="margin-top:.8rem">
            <label class="form-label">Service Type <span class="req-star">*</span></label>
            <p class="field-hint">Choose how you deliver your services to clients.</p>
            <div class="service-type-selector" id="service-type-selector" role="group" aria-label="Service type">

              <div class="svc-option" data-svc="home_service" tabindex="0" role="button" aria-pressed="false">
                <div class="svc-icon-wrap">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                  </svg>
                </div>
                <div class="svc-content">
                  <div class="svc-label">Home Service</div>
                  <div class="svc-desc">You travel to the client's location</div>
                </div>
                <div class="svc-check-ring"><span>✓</span></div>
              </div>

              <div class="svc-option" data-svc="business_location" tabindex="0" role="button" aria-pressed="false">
                <div class="svc-icon-wrap">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5A.75.75 0 0114.25 12h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016 2.993 2.993 0 002.25-1.016 3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 2.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>
                  </svg>
                </div>
                <div class="svc-content">
                  <div class="svc-label">Business Location</div>
                  <div class="svc-desc">Clients come to your shop or office</div>
                </div>
                <div class="svc-check-ring"><span>✓</span></div>
              </div>

              <div class="svc-option" data-svc="flexible" tabindex="0" role="button" aria-pressed="false">
                <div class="svc-icon-wrap">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                  </svg>
                </div>
                <div class="svc-content">
                  <div class="svc-label">Flexible</div>
                  <div class="svc-desc">Both home visits &amp; at your location</div>
                </div>
                <div class="svc-check-ring"><span>✓</span></div>
              </div>

            </div>
            <input type="hidden" name="service_type" id="f-svc-type" value="">
            <div class="field-error" id="err-svc-type">Please select a service type.</div>
          </div>

          <!-- Business address (shown only for business_location / flexible) -->
          <div class="form-group biz-addr-group" id="biz-addr-group" style="display:none; margin-top:.8rem">
            <label class="form-label" for="f-biz-addr">
              Business Address <span class="req-star">*</span>
              <span class="addr-badge" id="biz-addr-badge" style="display:none">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                Verified
              </span>
            </label>
            <div class="addr-input-wrap">
              <div class="input-icon-wrap">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                </svg>
                <input type="text" id="f-biz-addr" name="business_address" class="form-control input-with-icon"
                       placeholder="Start typing your business address…" autocomplete="off">
              </div>
              <div class="addr-spinner" id="biz-addr-spinner" aria-hidden="true"></div>
              <ul class="addr-suggestions" id="biz-addr-suggestions" role="listbox" aria-label="Address suggestions"></ul>
            </div>
            <input type="hidden" name="business_address_lat"   id="f-biz-lat"   value="">
            <input type="hidden" name="business_address_lng"   id="f-biz-lng"   value="">
            <input type="hidden" name="business_address_place" id="f-biz-place"  value="">
            <input type="hidden" name="business_address_verified" id="f-biz-verified" value="0">
            <div class="field-error" id="err-biz-addr">Please select a verified business address.</div>
            <p class="field-hint">Select from the dropdown to verify your address.</p>
          </div>

        </div><!-- /provider-section -->

        <!-- ── BASIC INFO ── -->
        <div class="form-section-label">Basic Information</div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="f-first">First Name</label>
            <input type="text" name="first_name" id="f-first" class="form-control"
                   placeholder="Maria" autocomplete="off">
            <div class="field-error" id="err-first">First name is required.</div>
          </div>
          <div class="form-group">
            <label class="form-label" for="f-last">Last Name</label>
            <input type="text" name="last_name" id="f-last" class="form-control"
                   placeholder="Santos" autocomplete="off">
            <div class="field-error" id="err-last">Last name is required.</div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-email">Email Address</label>
          <input type="email" name="email" id="f-email" class="form-control"
                 placeholder="you@example.com" autocomplete="off">
          <div class="field-error" id="err-email">A valid email is required.</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-phone">Mobile Number</label>
          <input type="tel" name="phone" id="f-phone" class="form-control"
                 placeholder="+63 917 000 0000" autocomplete="off">
          <div class="field-error" id="err-phone">A valid phone number is required.</div>
        </div>

        <!-- ── HOME ADDRESS (all users) ── -->
        <div class="form-section-label" style="margin-top:1.4rem">
          Home Address
          <span class="section-label-badge" id="home-addr-verified-badge" style="display:none">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            Verified
          </span>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-addr">Street / Barangay</label>
          <div class="addr-input-wrap">
            <div class="input-icon-wrap">
              <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
              </svg>
              <input type="text" name="home_address" id="f-addr" class="form-control input-with-icon"
                     placeholder="Start typing your address in Bacolod City…" autocomplete="off">
            </div>
            <div class="addr-spinner" id="addr-spinner" aria-hidden="true"></div>
            <ul class="addr-suggestions" id="addr-suggestions" role="listbox" aria-label="Address suggestions"></ul>
          </div>
          <div class="field-error" id="err-addr">Please select a verified address from the list.</div>
          <p class="field-hint">Type at least 3 characters then select a suggestion to verify.</p>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="f-city">City</label>
            <input type="text" name="city" id="f-city" class="form-control"
                   placeholder="Bacolod City" readonly>
          </div>
          <div class="form-group">
            <label class="form-label" for="f-province">Province</label>
            <input type="text" name="province" id="f-province" class="form-control"
                   placeholder="Negros Occidental" readonly>
          </div>
        </div>

        <!-- ── PERSONAL DETAILS ── -->
        <div class="form-section-label" style="margin-top:1.4rem">Personal Details</div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="f-gender">Gender</label>
            <select name="gender" id="f-gender" class="form-control">
              <option value="" disabled selected>Select gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="non_binary">Non-binary</option>
              <option value="prefer_not_to_say">Prefer not to say</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="f-dob">Date of Birth</label>
            <input type="date" name="date_of_birth" id="f-dob" class="form-control"
                   autocomplete="off" max="<?= date('Y-m-d', strtotime('-13 years')) ?>">
            <div class="field-error" id="err-dob">You must be at least 13 years old.</div>
          </div>
        </div>

        <!-- ── SECURITY ── -->
        <div class="form-section-label" style="margin-top:1.4rem">Security</div>

        <div class="form-group">
          <label class="form-label" for="f-pw">Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="f-pw" class="form-control"
                   placeholder="Create a strong password"
                   autocomplete="new-password"
                   oninput="checkStrength(this.value); updateProgress();">
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
          <div class="field-error" id="err-pw">Password must be at least 8 characters.</div>
        </div>

        <!-- Terms -->
        <div class="terms-row">
          <input type="checkbox" id="terms" name="terms">
          <label for="terms">
            I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
          </label>
        </div>
        <div class="field-error" id="err-terms" style="margin-top:-.5rem;margin-bottom:.8rem">
          You must accept the terms to continue.
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
  </div><!-- /register-right -->

</div><!-- /register-page -->


<script>
/* ═══════════════════════════════════════════════════════════
   QUICKBOOK — REGISTER PAGE JS
   Features:
     · Role toggling (show/hide provider section)
     · Service-type selector (provider)
     · Address autocomplete — Google Places (preferred)
       or Nominatim OSM fallback when no API key
     · Address verification state (verified badge)
     · Progress bar (4 steps: basic → address → details → pw)
     · Form validation on submit
     · Password strength meter & toggle
═══════════════════════════════════════════════════════════ */

/* ─── helpers ───────────────────────────────────────────── */
function $(id)  { return document.getElementById(id); }
function showErr(id, show) {
  var el = $(id);
  if (el) el.style.display = show ? 'block' : 'none';
}
function markInput(id, invalid) {
  var el = $(id);
  if (!el) return;
  el.style.borderColor = invalid ? '#DC2626' : '';
  el.style.boxShadow   = invalid ? '0 0 0 3px rgba(220,38,38,.12)' : '';
}

/* ─── progress bar (4 segments) ────────────────────────── */
var LABELS = {
  0: 'Account Setup',
  1: 'Basic info filled',
  2: 'Address verified',
  3: 'Personal details done',
  4: 'Almost done — just hit Create Account!',
};

function updateProgress() {
  var first  = $('f-first').value.trim();
  var last   = $('f-last').value.trim();
  var email  = $('f-email').value.trim();
  var phone  = $('f-phone').value.trim();
  var addrOk = $('f-addr-verified').value === '1';
  var gender = $('f-gender').value;
  var dob    = $('f-dob').value;
  var pw     = $('f-pw').value;

  var sec1 = first && last && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) && phone.length >= 7;
  var sec2 = sec1 && addrOk;
  var sec3 = sec2 && (gender || dob);
  var sec4 = sec3 && pw.length >= 8;

  var filled = sec4 ? 4 : sec3 ? 3 : sec2 ? 2 : sec1 ? 1 : 0;
  for (var i = 1; i <= 4; i++) {
    var bar = $('bar-' + i);
    if (!bar) continue;
    bar.classList.remove('active', 'done');
    if (i <= filled)           bar.classList.add('done');
    else if (i === filled + 1) bar.classList.add('active');
  }
  $('step-label').textContent = LABELS[filled];
}

['f-first','f-last','f-email','f-phone','f-gender','f-dob'].forEach(function(id) {
  var el = $(id);
  if (el) el.addEventListener('input',  updateProgress);
  if (el) el.addEventListener('change', updateProgress);
});
updateProgress();

/* ─── role selector ─────────────────────────────────────── */
$('role-selector').addEventListener('click', function(e) {
  var opt = e.target.closest('.role-option[data-role]');
  if (!opt) return;
  document.querySelectorAll('#role-selector .role-option').forEach(function(o) {
    o.classList.remove('selected'); o.setAttribute('aria-pressed', 'false');
  });
  opt.classList.add('selected'); opt.setAttribute('aria-pressed', 'true');
  var role = opt.getAttribute('data-role');
  $('role-input').value = role;
  var provSec = $('provider-section');
  if (role === 'provider') {
    provSec.style.display = 'block';
    provSec.classList.add('provider-section-visible');
  } else {
    provSec.style.display = 'none';
    provSec.classList.remove('provider-section-visible');
  }
});
$('role-selector').addEventListener('keydown', function(e) {
  if (e.key === ' ' || e.key === 'Enter') {
    var opt = e.target.closest('.role-option[data-role]');
    if (opt) { e.preventDefault(); opt.click(); }
  }
});

/* ─── service-type selector ─────────────────────────────── */
var svcSelector = $('service-type-selector');
if (svcSelector) {
  svcSelector.addEventListener('click', function(e) {
    var opt = e.target.closest('.svc-option[data-svc]');
    if (!opt) return;
    document.querySelectorAll('.svc-option').forEach(function(o) {
      o.classList.remove('selected'); o.setAttribute('aria-pressed', 'false');
    });
    opt.classList.add('selected'); opt.setAttribute('aria-pressed', 'true');
    var svc = opt.getAttribute('data-svc');
    $('f-svc-type').value = svc;
    showErr('err-svc-type', false);

    /* show business address field for non-home-only types */
    var bizAddrGroup = $('biz-addr-group');
    if (svc === 'business_location' || svc === 'flexible') {
      bizAddrGroup.style.display = 'block';
      bizAddrGroup.classList.add('biz-addr-group-visible');
      if (!bizAddrGroup._autocomplete) {
        initAddressInput('f-biz-addr', 'biz-addr-suggestions', 'biz-addr-spinner',
          function(place) {
            $('f-biz-lat').value       = place.lat;
            $('f-biz-lng').value       = place.lng;
            $('f-biz-place').value     = place.placeId || '';
            $('f-biz-verified').value  = '1';
            $('f-biz-addr').value      = place.formatted;
            showBizVerifiedBadge(true);
            showErr('err-biz-addr', false);
            markInput('f-biz-addr', false);
          },
          function() {
            $('f-biz-verified').value  = '0';
            showBizVerifiedBadge(false);
          }
        );
        bizAddrGroup._autocomplete = true;
      }
    } else {
      bizAddrGroup.style.display = 'none';
      bizAddrGroup.classList.remove('biz-addr-group-visible');
    }
  });
  svcSelector.addEventListener('keydown', function(e) {
    if (e.key === ' ' || e.key === 'Enter') {
      var opt = e.target.closest('.svc-option');
      if (opt) { e.preventDefault(); opt.click(); }
    }
  });
}

function showBizVerifiedBadge(show) {
  var badge = $('biz-addr-badge');
  if (badge) badge.style.display = show ? 'inline-flex' : 'none';
}

/* ─── ADDRESS AUTOCOMPLETE ENGINE ─────────────────────────
   Uses Google Places when QB_MAPS_KEY is defined,
   otherwise falls back to Nominatim (OSM) — no API key needed.
   ─────────────────────────────────────────────────────── */

var _placesService   = null;  /* Google AutocompleteService  */
var _geocoderService = null;  /* Google Geocoder             */
var _mapsReady       = false;

/**
 * Called by the Google Maps JS SDK callback.
 * Initialises the home-address input after the SDK loads.
 */
function initAddressAutocomplete() {
  _mapsReady       = true;
  _placesService   = new google.maps.places.AutocompleteService();
  _geocoderService = new google.maps.Geocoder();

  initAddressInput('f-addr', 'addr-suggestions', 'addr-spinner',
    function(place) {
      $('f-addr-lat').value      = place.lat;
      $('f-addr-lng').value      = place.lng;
      $('f-addr-place').value    = place.placeId || '';
      $('f-addr-verified').value = '1';
      $('f-addr').value          = place.formatted;
      $('f-city').value          = place.city    || '';
      $('f-province').value      = place.province || '';
      showHomeVerifiedBadge(true);
      showErr('err-addr', false);
      markInput('f-addr', false);
      updateProgress();
    },
    function() {
      $('f-addr-verified').value = '0';
      $('f-city').value          = '';
      $('f-province').value      = '';
      showHomeVerifiedBadge(false);
      updateProgress();
    }
  );
}

function showHomeVerifiedBadge(show) {
  var badge = $('home-addr-verified-badge');
  if (badge) badge.style.display = show ? 'inline-flex' : 'none';
}

/**
 * Attach autocomplete behaviour to an address <input>.
 * Works with both Google Places and Nominatim.
 *
 * @param {string}   inputId      — id of the <input> field
 * @param {string}   listId       — id of the <ul> suggestions list
 * @param {string}   spinnerId    — id of the loading spinner element
 * @param {Function} onSelect(place)  — called with resolved place object
 * @param {Function} onClear()        — called when user edits after selection
 */
function initAddressInput(inputId, listId, spinnerId, onSelect, onClear) {
  var input   = $(inputId);
  var list    = $(listId);
  var spinner = $(spinnerId);
  if (!input || !list) return;

  var debounceTimer  = null;
  var selectedOnce   = false;
  var activeIndex    = -1;    /* keyboard nav */
  var currentItems   = [];

  /* ── reset state when user edits field after picking ── */
  input.addEventListener('input', function() {
    selectedOnce = false;
    activeIndex  = -1;
    if (onClear) onClear();

    clearTimeout(debounceTimer);
    var q = input.value.trim();
    if (q.length < 3) { hideList(); return; }

    showSpinner(true);
    debounceTimer = setTimeout(function() { fetchSuggestions(q); }, 350);
  });

  /* ── keyboard navigation ── */
  input.addEventListener('keydown', function(e) {
    if (!list.children.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = Math.min(activeIndex + 1, currentItems.length - 1);
      highlightItem(activeIndex);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = Math.max(activeIndex - 1, 0);
      highlightItem(activeIndex);
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault();
      pickItem(currentItems[activeIndex]);
    } else if (e.key === 'Escape') {
      hideList();
    }
  });

  /* ── close list when clicking outside ── */
  document.addEventListener('click', function(e) {
    if (!input.contains(e.target) && !list.contains(e.target)) hideList();
  });

  /* ── fetch from Google or Nominatim ── */
  function fetchSuggestions(query) {
    if (_mapsReady && _placesService) {
      _placesService.getPlacePredictions(
        { input: query, componentRestrictions: { country: 'ph' } },
        function(predictions, status) {
          showSpinner(false);
          if (status !== google.maps.places.PlacesServiceStatus.OK || !predictions) {
            hideList(); return;
          }
          currentItems = predictions.map(function(p) {
            return { label: p.description, placeId: p.place_id, source: 'google' };
          });
          renderList(currentItems);
        }
      );
    } else {
      /* Nominatim fallback */
      fetch('https://nominatim.openstreetmap.org/search?' + new URLSearchParams({
        q: query + ', Bacolod City, Philippines',
        format:          'json',
        addressdetails:  1,
        limit:           6,
        countrycodes:    'ph',
        'accept-language': 'en',
      }), { headers: { 'Accept-Language': 'en' } })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        showSpinner(false);
        if (!data.length) { hideList(); return; }
        currentItems = data.map(function(item) {
          return {
            label:    item.display_name,
            lat:      parseFloat(item.lat),
            lng:      parseFloat(item.lon),
            city:     item.address.city || item.address.town || item.address.municipality || '',
            province: item.address.state || item.address.county || '',
            source:   'nominatim',
          };
        });
        renderList(currentItems);
      })
      .catch(function() { showSpinner(false); hideList(); });
    }
  }

  function renderList(items) {
    list.innerHTML = '';
    if (!items.length) { hideList(); return; }
    items.forEach(function(item, idx) {
      var li = document.createElement('li');
      li.setAttribute('role', 'option');
      li.setAttribute('tabindex', '-1');
      li.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">' +
          '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>' +
          '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>' +
        '</svg>' +
        '<span>' + escHtml(item.label) + '</span>';
      li.addEventListener('mousedown', function(e) { e.preventDefault(); });
      li.addEventListener('click', function() { pickItem(item); });
      li.addEventListener('mouseover', function() { highlightItem(idx); });
      list.appendChild(li);
    });
    list.style.display = 'block';
  }

  function highlightItem(idx) {
    var items = list.querySelectorAll('li');
    items.forEach(function(li) { li.classList.remove('active'); });
    if (items[idx]) items[idx].classList.add('active');
  }

  function pickItem(item) {
    selectedOnce = true;
    hideList();
    if (item.source === 'google') {
      /* resolve lat/lng + address components via Geocoder */
      showSpinner(true);
      _geocoderService.geocode({ placeId: item.placeId }, function(results, status) {
        showSpinner(false);
        if (status !== google.maps.GeocoderStatus.OK || !results[0]) return;
        var r   = results[0];
        var lat = r.geometry.location.lat();
        var lng = r.geometry.location.lng();
        var city = '', province = '';
        r.address_components.forEach(function(c) {
          if (c.types.includes('locality'))               city     = c.long_name;
          if (c.types.includes('administrative_area_level_1')) province = c.long_name;
        });
        onSelect({ formatted: item.label, lat: lat, lng: lng, placeId: item.placeId, city: city, province: province });
      });
    } else {
      /* Nominatim: already have lat/lng */
      onSelect({ formatted: item.label, lat: item.lat, lng: item.lng, placeId: null, city: item.city, province: item.province });
    }
  }

  function showSpinner(on) { if (spinner) spinner.style.display = on ? 'block' : 'none'; }
  function hideList()      { list.style.display = 'none'; list.innerHTML = ''; currentItems = []; activeIndex = -1; }
}

/* init home address even without Google Maps (Nominatim) */
if (!window.QB_MAPS_KEY) {
  document.addEventListener('DOMContentLoaded', function() {
    initAddressInput('f-addr', 'addr-suggestions', 'addr-spinner',
      function(place) {
        $('f-addr-lat').value      = place.lat;
        $('f-addr-lng').value      = place.lng;
        $('f-addr-place').value    = place.placeId || '';
        $('f-addr-verified').value = '1';
        $('f-addr').value          = place.formatted;
        $('f-city').value          = place.city    || '';
        $('f-province').value      = place.province || '';
        showHomeVerifiedBadge(true);
        showErr('err-addr', false);
        markInput('f-addr', false);
        updateProgress();
      },
      function() {
        $('f-addr-verified').value = '0';
        $('f-city').value          = '';
        $('f-province').value      = '';
        showHomeVerifiedBadge(false);
        updateProgress();
      }
    );
  });
}

/* ─── utility ────────────────────────────────────────────── */
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ─── form validation on submit ─────────────────────────── */
$('register-form').addEventListener('submit', function(e) {
  var first   = $('f-first').value.trim();
  var last    = $('f-last').value.trim();
  var email   = $('f-email').value.trim();
  var phone   = $('f-phone').value.trim();
  var pw      = $('f-pw').value;
  var dob     = $('f-dob').value;
  var terms   = $('terms').checked;
  var addrOk  = $('f-addr-verified').value === '1';
  var role    = $('role-input').value;
  var ok      = true;

  showErr('err-first', !first); markInput('f-first', !first); if (!first) ok = false;
  showErr('err-last',  !last);  markInput('f-last',  !last);  if (!last)  ok = false;

  var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  showErr('err-email', !emailOk); markInput('f-email', !emailOk); if (!emailOk) ok = false;

  var phoneOk = phone.length >= 7;
  showErr('err-phone', !phoneOk); markInput('f-phone', !phoneOk); if (!phoneOk) ok = false;

  showErr('err-addr', !addrOk); markInput('f-addr', !addrOk); if (!addrOk) ok = false;

  var pwOk = pw.length >= 8;
  showErr('err-pw', !pwOk); markInput('f-pw', !pwOk); if (!pwOk) ok = false;

  if (dob) {
    var dobDate = new Date(dob);
    var minDate = new Date(); minDate.setFullYear(minDate.getFullYear() - 13);
    var dobOk = dobDate <= minDate;
    showErr('err-dob', !dobOk); markInput('f-dob', !dobOk); if (!dobOk) ok = false;
  }

  showErr('err-terms', !terms); if (!terms) ok = false;

  /* Provider-specific */
  if (role === 'provider') {
    var bizName = $('f-biz-name').value.trim();
    showErr('err-biz-name', !bizName); markInput('f-biz-name', !bizName);
    if (!bizName) ok = false;

    var svcType = $('f-svc-type').value;
    showErr('err-svc-type', !svcType);
    if (!svcType) ok = false;

    if (svcType === 'business_location' || svcType === 'flexible') {
      var bizAddrOk = $('f-biz-verified').value === '1';
      showErr('err-biz-addr', !bizAddrOk); markInput('f-biz-addr', !bizAddrOk);
      if (!bizAddrOk) ok = false;
    }
  }

  if (!ok) { e.preventDefault(); return; }

  var btn = $('submit-btn');
  if (btn) { btn.textContent = 'Creating account…'; btn.disabled = true; }
});

/* ─── password toggle ────────────────────────────────────── */
function togglePw() {
  var input  = $('f-pw');
  var eyeOn  = $('eye-icon');
  var eyeOff = $('eye-off-icon');
  var hidden = input.type === 'password';
  input.type           = hidden ? 'text'    : 'password';
  eyeOn.style.display  = hidden ? 'none'    : '';
  eyeOff.style.display = hidden ? ''        : 'none';
}

/* ─── password strength ──────────────────────────────────── */
function checkStrength(value) {
  var segs  = ['seg1','seg2','seg3','seg4'].map(function(id){ return $(id); });
  var label = $('strength-label');
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