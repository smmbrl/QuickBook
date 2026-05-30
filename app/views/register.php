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
  <script>window.QB_MAPS_KEY = <?= json_encode($google_maps_key) ?>;</script>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($google_maps_key) ?>&libraries=places&callback=initAddressAutocomplete" async defer></script>
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
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png" alt="QuickBook Logo" class="auth-logo-img">
      Quick<span>Book</span>
    </a>

    <h2>Join your<br><em>community today.</em></h2>

    <p class="register-left-desc">
      Create a free account and start booking trusted local
      service providers — or list your own services and
      grow your business digitally in Bacolod City.
    </p>

    <ul class="feature-list">
      <li class="feature-item"><span class="feature-check">✓</span>Free to sign up — no hidden fees</li>
      <li class="feature-item"><span class="feature-check">✓</span>Real-time availability, zero double-bookings</li>
      <li class="feature-item"><span class="feature-check">✓</span>Secure GCash, PayMaya &amp; card payments</li>
      <li class="feature-item"><span class="feature-check">✓</span>Earn loyalty points on every booking</li>
      <li class="feature-item"><span class="feature-check">✓</span>Verified address — safe, reliable bookings</li>
    </ul>

    <!-- Step guide on left panel -->
    <div class="left-step-guide" id="left-step-guide">
      <div class="left-step-item active" data-step="1">
        <div class="left-step-num">1</div>
        <div class="left-step-text">
          <div class="left-step-title">Choose Role</div>
          <div class="left-step-sub">Admin, Customer, or Provider</div>
        </div>
      </div>
      <div class="left-step-connector"></div>
      <div class="left-step-item" data-step="2">
        <div class="left-step-num">2</div>
        <div class="left-step-text">
          <div class="left-step-title">Your Details</div>
          <div class="left-step-sub">Role-specific information</div>
        </div>
      </div>
      <div class="left-step-connector"></div>
      <div class="left-step-item" data-step="3">
        <div class="left-step-num">3</div>
        <div class="left-step-text">
          <div class="left-step-title">Review</div>
          <div class="left-step-sub">Confirm your details</div>
        </div>
      </div>
      <div class="left-step-connector"></div>
      <div class="left-step-item" data-step="4">
        <div class="left-step-num">4</div>
        <div class="left-step-text">
          <div class="left-step-title">Security</div>
          <div class="left-step-sub">Set your password</div>
        </div>
      </div>
    </div>

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

      <!-- Stepper header -->
      <div class="wizard-header">
        <div class="register-step-label" id="step-label">Step 1 of 4 — Choose your role</div>
        <div class="register-steps">
          <div class="r-step active" id="bar-1"></div>
          <div class="r-step" id="bar-2"></div>
          <div class="r-step" id="bar-3"></div>
          <div class="r-step" id="bar-4"></div>
        </div>

      </div>

      <div class="form-heading" id="wizard-heading">Create your account</div>
      <p class="form-subheading">
        Already have one? <a href="<?= BASE_URL ?>login">Sign in here</a>
      </p>

      <?php if ($error): ?>
        <div class="flash-msg flash-error"><span>⚠</span><span><?= htmlspecialchars($error) ?></span></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="flash-msg flash-success"><span>✓</span><span><?= htmlspecialchars($success) ?></span></div>
      <?php endif; ?>

      <form action="<?= BASE_URL ?>auth/register" method="POST"
            autocomplete="off" id="register-form" novalidate>

        <!-- Hidden state fields — always in DOM, never inside a display:none pane.
             JS populates these on submit so PHP always receives them. -->
        <input type="hidden" name="role"               id="role-input"       value="">
        <input type="hidden" name="address_lat"        id="f-addr-lat"       value="">
        <input type="hidden" name="address_lng"        id="f-addr-lng"       value="">
        <input type="hidden" name="address_place"      id="f-addr-place"     value="">
        <input type="hidden" name="address_verified"   id="f-addr-verified"  value="0">
        <input type="hidden" name="business_address_lat"      id="f-biz-lat"       value="">
        <input type="hidden" name="business_address_lng"      id="f-biz-lng"       value="">
        <input type="hidden" name="business_address_place"    id="f-biz-place"     value="">
        <input type="hidden" name="business_address_verified" id="f-biz-verified"  value="0">
        <input type="hidden" name="service_type"       id="f-svc-type"       value="">
        <input type="hidden" name="categories"         id="f-categories"     value="">
        <!-- Always-present submit fields: JS copies pane values here before POST -->
        <input type="hidden" name="first_name"    id="post-first-name"  value="">
        <input type="hidden" name="last_name"     id="post-last-name"   value="">
        <input type="hidden" name="email"         id="post-email"       value="">
        <input type="hidden" name="phone"         id="post-phone"       value="">
        <input type="hidden" name="home_address"  id="post-home-addr"   value="">
        <input type="hidden" name="gender"        id="post-gender"      value="">
        <input type="hidden" name="date_of_birth" id="post-dob"         value="">

        <!-- ══════════════════════════════════════════════════
             STEP 1 — ROLE SELECTION
        ══════════════════════════════════════════════════ -->
        <div class="wizard-pane" id="pane-1">
          <div class="role-selector-label">I want to join as:</div>
          <div class="role-selector three-col" id="role-selector">

            <div class="role-option" data-role="admin" tabindex="0" role="button" aria-pressed="false">
              <span class="role-icon">🛡️</span>
              <span class="role-label">Admin</span>
              <span class="role-sub">Platform manager</span>
            </div>

            <div class="role-option" data-role="customer" tabindex="0" role="button" aria-pressed="false">
              <span class="role-icon">👤</span>
              <span class="role-label">Customer</span>
              <span class="role-sub">Book services</span>
            </div>

            <div class="role-option" data-role="provider" tabindex="0" role="button" aria-pressed="false">
              <span class="role-icon">💼</span>
              <span class="role-label">Provider</span>
              <span class="role-sub">Offer services</span>
            </div>

          </div>
          <div class="field-error" id="err-role" style="margin-top:-.5rem;margin-bottom:.8rem">
            Please select a role to continue.
          </div>

          <!-- Role description cards -->
          <div class="role-desc-cards">
            <div class="role-desc-card" id="desc-admin" style="display:none">
              <div class="role-desc-icon">🛡️</div>
              <div>
                <div class="role-desc-title">Admin Account</div>
                <div class="role-desc-body">This account already exists. Clicking <strong>Next</strong> will take you directly to the login page.</div>
              </div>
            </div>
            <div class="role-desc-card" id="desc-customer" style="display:none">
              <div class="role-desc-icon">👤</div>
              <div>
                <div class="role-desc-title">Customer Account</div>
                <div class="role-desc-body">Browse and book verified local service providers in Bacolod. Earn loyalty points on every booking.</div>
              </div>
            </div>
            <div class="role-desc-card" id="desc-provider" style="display:none">
              <div class="role-desc-icon">💼</div>
              <div>
                <div class="role-desc-title">Provider Account</div>
                <div class="role-desc-body">List your services, manage bookings, and grow your business digitally in Bacolod City.</div>
              </div>
            </div>
          </div>
        </div><!-- /pane-1 -->

        <!-- ══════════════════════════════════════════════════
             STEP 2 — ROLE-SPECIFIC FIELDS
        ══════════════════════════════════════════════════ -->
        <div class="wizard-pane" id="pane-2" style="display:none">

          <!-- ── ADMIN fields ── -->
          <div id="step2-admin" style="display:none">
            <div class="form-section-label">Admin Account Details</div>

            <div class="form-group">
              <label class="form-label" for="f-admin-email">Email Address <span class="req-star">*</span></label>
              <div class="input-icon-wrap">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                </svg>
                <input type="email" name="admin_email" id="f-admin-email" class="form-control input-with-icon"
                       placeholder="admin@quickbook.ph" autocomplete="off">
              </div>
              <div class="field-error" id="err-admin-email">A valid email address is required.</div>
            </div>

            <div class="admin-info-notice">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
              </svg>
              Admin accounts require manual approval after registration. You will receive an email once your account is activated.
            </div>
          </div>

          <!-- ── CUSTOMER fields ── -->
          <div id="step2-customer" style="display:none">
            <div class="form-section-label">Personal Information</div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="f-first">First Name <span class="req-star">*</span></label>
                <input type="text" id="f-first" class="form-control" placeholder="Maria" autocomplete="off">
                <div class="field-error" id="err-first">First name is required.</div>
              </div>
              <div class="form-group">
                <label class="form-label" for="f-last">Last Name <span class="req-star">*</span></label>
                <input type="text" id="f-last" class="form-control" placeholder="Santos" autocomplete="off">
                <div class="field-error" id="err-last">Last name is required.</div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="f-email">Email Address <span class="req-star">*</span></label>
              <div class="input-icon-wrap">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                </svg>
                <input type="email" id="f-email" class="form-control input-with-icon" placeholder="you@example.com" autocomplete="off">
              </div>
              <div class="field-error" id="err-email">A valid email is required.</div>
            </div>

            <div class="form-group">
              <label class="form-label" for="f-phone">Mobile Number <span class="req-star">*</span></label>
              <div class="input-icon-wrap">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                </svg>
                <input type="tel" id="f-phone" class="form-control input-with-icon" placeholder="+63 917 000 0000" autocomplete="off">
              </div>
              <div class="field-error" id="err-phone">A valid phone number is required.</div>
            </div>

            <!-- Home address -->
            <div class="form-section-label" style="margin-top:1.2rem">
              Home Address
              <span class="section-label-badge" id="home-addr-verified-badge" style="display:none">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                Verified
              </span>
            </div>

            <div class="form-group">
              <label class="form-label" for="f-addr">Address <span class="req-star">*</span></label>
              <div class="addr-input-wrap">
                <div class="input-icon-wrap">
                  <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                  </svg>
                  <input type="text" id="f-addr" class="form-control input-with-icon"
                         placeholder="Start typing your address in Bacolod City…" autocomplete="off">
                </div>
                <div class="addr-spinner" id="addr-spinner" aria-hidden="true"></div>
                <ul class="addr-suggestions" id="addr-suggestions" role="listbox" aria-label="Address suggestions"></ul>
              </div>
              <div class="field-error" id="err-addr">Please enter your home address.</div>
              <p class="field-hint">Type your address and select a suggestion to verify.</p>
            </div>


            <!-- Personal details -->
            <div class="form-section-label" style="margin-top:1.2rem">Personal Details</div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="f-gender">Gender</label>
                <select id="f-gender" class="form-control">
                  <option value="" disabled selected>Select gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="non_binary">Non-binary</option>
                  <option value="prefer_not_to_say">Prefer not to say</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="f-dob">Date of Birth</label>
                <input type="date" id="f-dob" class="form-control"
                       autocomplete="off" max="<?= date('Y-m-d', strtotime('-13 years')) ?>">
                <div class="field-error" id="err-dob">You must be at least 13 years old.</div>
              </div>
            </div>
          </div><!-- /step2-customer -->

          <!-- ── PROVIDER fields ── -->
          <div id="step2-provider" style="display:none">
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
              <div class="field-error" id="err-biz-name">Business name is required.</div>
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
              <div class="field-error" id="err-svc-type">Please select a service type.</div>
            </div>

            <!-- Business address (shown conditionally) -->
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
              <div class="field-error" id="err-biz-addr">Please select a verified business address.</div>
              <p class="field-hint">Select from the dropdown to verify your address.</p>
            </div>

            <!-- Service categories single-select -->
            <div class="form-group" style="margin-top:1rem">
              <label class="form-label">Service Category <span class="req-star">*</span></label>
              <p class="field-hint">Select the category that best describes your business.</p>
              <div class="category-grid" id="category-grid" role="group" aria-label="Service categories">
                <button type="button" class="cat-chip" data-cat="Barbershop">✂️ Barbershop</button>
                <button type="button" class="cat-chip" data-cat="Hair Salon">💇 Hair Salon</button>
                <button type="button" class="cat-chip" data-cat="Nail Care">💅 Nail Care</button>
                <button type="button" class="cat-chip" data-cat="Massage Therapy">💆 Massage Therapy</button>
                <button type="button" class="cat-chip" data-cat="Skincare Facial">🧖 Skincare Facial</button>
                <button type="button" class="cat-chip" data-cat="Fitness Training">🏋️ Fitness Training</button>
                <button type="button" class="cat-chip" data-cat="Cleaning Services">🧹 Cleaning Services</button>
                <button type="button" class="cat-chip" data-cat="Pet Grooming">🐾 Pet Grooming</button>
                <button type="button" class="cat-chip" data-cat="Dental Services">🦷 Dental Services</button>
                <button type="button" class="cat-chip" data-cat="Makeup Artist">💄 Makeup Artist</button>
              </div>
              <div class="field-error" id="err-categories">Please select a service category.</div>
            </div>

            <!-- ── PROVIDER PERSONAL INFO ── -->
            <div class="form-section-label" style="margin-top:1.4rem">Personal Information</div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="f-prov-first">First Name <span class="req-star">*</span></label>
                <input type="text" id="f-prov-first" class="form-control"
                       placeholder="Maria" autocomplete="off">
                <div class="field-error" id="err-prov-first">First name is required.</div>
              </div>
              <div class="form-group">
                <label class="form-label" for="f-prov-last">Last Name <span class="req-star">*</span></label>
                <input type="text" id="f-prov-last" class="form-control"
                       placeholder="Santos" autocomplete="off">
                <div class="field-error" id="err-prov-last">Last name is required.</div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="f-prov-email">Email Address <span class="req-star">*</span></label>
              <div class="input-icon-wrap">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                </svg>
                <input type="email" id="f-prov-email" class="form-control input-with-icon"
                       placeholder="you@example.com" autocomplete="off">
              </div>
              <div class="field-error" id="err-prov-email">A valid email is required.</div>
            </div>

            <div class="form-group">
              <label class="form-label" for="f-prov-phone">Mobile Number <span class="req-star">*</span></label>
              <div class="input-icon-wrap">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                </svg>
                <input type="tel" id="f-prov-phone" class="form-control input-with-icon"
                       placeholder="+63 917 000 0000" autocomplete="off">
              </div>
              <div class="field-error" id="err-prov-phone">A valid phone number is required.</div>
            </div>

            <!-- Provider Home Address -->
            <div class="form-section-label" style="margin-top:1.2rem">
              Home Address
              <span class="section-label-badge" id="prov-addr-verified-badge" style="display:none">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                Verified
              </span>
            </div>

            <div class="form-group">
              <label class="form-label" for="f-prov-addr">Address <span class="req-star">*</span></label>
              <div class="addr-input-wrap">
                <div class="input-icon-wrap">
                  <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                  </svg>
                  <input type="text" id="f-prov-addr" class="form-control input-with-icon"
                         placeholder="Start typing your address in Bacolod City…" autocomplete="off">
                </div>
                <div class="addr-spinner" id="prov-addr-spinner" aria-hidden="true"></div>
                <ul class="addr-suggestions" id="prov-addr-suggestions" role="listbox" aria-label="Address suggestions"></ul>
              </div>
              <input type="hidden" name="home_address_lat"      id="f-prov-addr-lat"      value="">
              <input type="hidden" name="home_address_lng"      id="f-prov-addr-lng"      value="">
              <input type="hidden" name="home_address_place"    id="f-prov-addr-place"    value="">
              <input type="hidden" name="home_address_verified" id="f-prov-addr-verified" value="0">
              <!-- sync targets: populated by JS on submit so AuthController gets canonical names -->
              <input type="hidden" id="f-sync-addr-lat"      name="_sync_addr_lat"      value="">
              <input type="hidden" id="f-sync-addr-lng"      name="_sync_addr_lng"      value="">
              <input type="hidden" id="f-sync-addr-place"    name="_sync_addr_place"    value="">
              <input type="hidden" id="f-sync-addr-verified" name="_sync_addr_verified" value="">
              <div class="field-error" id="err-prov-addr">Please enter your home address.</div>
              <p class="field-hint">Type your address and select a suggestion to verify.</p>
            </div>


            <!-- Provider Personal Details -->
            <div class="form-section-label" style="margin-top:1.2rem">Personal Details</div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="f-prov-gender">Gender</label>
                <select id="f-prov-gender" class="form-control">
                  <option value="" disabled selected>Select gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="non_binary">Non-binary</option>
                  <option value="prefer_not_to_say">Prefer not to say</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="f-prov-dob">Date of Birth</label>
                <input type="date" id="f-prov-dob" class="form-control"
                       autocomplete="off" max="<?= date('Y-m-d', strtotime('-13 years')) ?>">
                <div class="field-error" id="err-prov-dob">You must be at least 13 years old.</div>
              </div>
            </div>

          </div><!-- /step2-provider -->

        </div><!-- /pane-2 -->

        <!-- ══════════════════════════════════════════════════
             STEP 3 — REVIEW / CONFIRMATION
        ══════════════════════════════════════════════════ -->
        <div class="wizard-pane" id="pane-3" style="display:none">
          <div class="review-header">
            <div class="review-check-circle">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div>
              <div class="review-header-title">Review your details</div>
              <div class="review-header-sub">Everything look good? You can go back to make changes.</div>
            </div>
          </div>

          <!-- Role badge -->
          <div class="review-role-badge" id="review-role-badge"></div>

          <!-- Review cards (populated by JS) -->
          <div class="review-cards" id="review-cards"></div>

          <p class="field-hint" style="margin-top:1rem;text-align:center">
            Your information is securely encrypted and never shared without consent.
          </p>
        </div><!-- /pane-3 -->

        <!-- ══════════════════════════════════════════════════
             STEP 4 — SECURITY
        ══════════════════════════════════════════════════ -->
        <div class="wizard-pane" id="pane-4" style="display:none">
          <div class="form-section-label">Set Your Password</div>

          <div class="form-group">
            <label class="form-label" for="f-pw">Password <span class="req-star">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password" id="f-pw" class="form-control"
                     placeholder="Create a strong password"
                     autocomplete="new-password"
                     oninput="checkStrength(this.value)">
              <button type="button" class="pw-toggle" onclick="togglePw('f-pw','eye-icon','eye-off-icon')" aria-label="Toggle password visibility">
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

          <div class="form-group" style="margin-top:.8rem">
            <label class="form-label" for="f-pw-confirm">Confirm Password <span class="req-star">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password_confirm" id="f-pw-confirm" class="form-control"
                     placeholder="Repeat your password"
                     autocomplete="new-password"
                     oninput="checkMatch()">
              <button type="button" class="pw-toggle" onclick="togglePw('f-pw-confirm','eye-icon-c','eye-off-icon-c')" aria-label="Toggle confirm password visibility">
                <svg id="eye-icon-c" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg id="eye-off-icon-c" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                </svg>
              </button>
            </div>
            <!-- Match indicator -->
            <div class="pw-match-row" id="pw-match-row" style="display:none">
              <span class="pw-match-icon" id="pw-match-icon"></span>
              <span class="pw-match-text" id="pw-match-text"></span>
            </div>
            <div class="field-error" id="err-pw-confirm">Passwords do not match.</div>
          </div>

          <div class="pw-requirements">
            <div class="pw-req-title">Password must include:</div>
            <div class="pw-req-list">
              <div class="pw-req-item" id="req-len">
                <span class="pw-req-dot"></span>At least 8 characters
              </div>
              <div class="pw-req-item" id="req-upper">
                <span class="pw-req-dot"></span>One uppercase letter
              </div>
              <div class="pw-req-item" id="req-num">
                <span class="pw-req-dot"></span>One number
              </div>
              <div class="pw-req-item" id="req-special">
                <span class="pw-req-dot"></span>One special character
              </div>
            </div>
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

          <!-- Social login (only on final step) -->
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

        </div><!-- /pane-4 -->

        <!-- ══════════════════════════════════════════════════
             WIZARD NAVIGATION
        ══════════════════════════════════════════════════ -->
        <div class="wizard-nav" id="wizard-nav">
          <button type="button" class="btn-back" id="btn-back" style="display:none" onclick="goBack()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
            </svg>
            Back
          </button>
          <button type="button" class="btn-next" id="btn-next" onclick="goNext()">
            Next
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
            </svg>
          </button>
          <button type="submit" class="btn-submit" id="btn-submit" style="display:none">
            CREATE ACCOUNT
          </button>
        </div>

      </form><!-- /register-form -->

    </div><!-- /register-form-box -->
  </div><!-- /register-right -->

</div><!-- /register-page -->


<script>
/* ═══════════════════════════════════════════════════════════
   QUICKBOOK — REGISTER WIZARD JS
   4-Step Role-Based Flow:
     Step 1: Role selection (Admin / Customer / Provider)
     Step 2: Role-specific fields
     Step 3: Review / confirmation
     Step 4: Security (password + confirm)
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

/* ─── wizard state ───────────────────────────────────────── */
var currentStep = 1;
var selectedRole = '';
var selectedCategories = [];

/* ─── step metadata ─────────────────────────────────────── */
var STEP_LABELS = {
  1: 'Step 1 of 4 — Choose your role',
  2: 'Step 2 of 4 — Your information',
  3: 'Step 3 of 4 — Review details',
  4: 'Step 4 of 4 — Set your password',
};
var STEP_HEADINGS = {
  1: 'Create your account',
  2: 'Tell us about yourself',
  3: 'Review your information',
  4: 'Secure your account',
};

/* ─── go to step ────────────────────────────────────────── */
function goToStep(n, direction) {
  var oldPane = $('pane-' + currentStep);
  var newPane = $('pane-' + n);
  if (!newPane) return;

  /* animate out */
  if (oldPane) {
    oldPane.classList.add(direction === 'forward' ? 'pane-exit-left' : 'pane-exit-right');
    setTimeout(function() {
      oldPane.style.display = 'none';
      oldPane.classList.remove('pane-exit-left', 'pane-exit-right');
    }, 250);
  }

  /* animate in */
  newPane.style.display = 'block';
  newPane.classList.add(direction === 'forward' ? 'pane-enter-right' : 'pane-enter-left');
  setTimeout(function() {
    newPane.classList.remove('pane-enter-right', 'pane-enter-left');
  }, 300);

  currentStep = n;
  updateStepperUI();
}

/* ─── stepper UI ────────────────────────────────────────── */
function updateStepperUI() {
  /* progress bar */
  for (var i = 1; i <= 4; i++) {
    var bar = $('bar-' + i);
    if (!bar) continue;
    bar.className = 'r-step';
    if (i < currentStep)       bar.classList.add('done');
    else if (i === currentStep) bar.classList.add('active');
  }

  /* left panel guide */
  document.querySelectorAll('.left-step-item').forEach(function(item) {
    var s = parseInt(item.getAttribute('data-step'));
    item.className = 'left-step-item';
    if (s < currentStep)       item.classList.add('done');
    else if (s === currentStep) item.classList.add('active');
  });
  /* labels */
  $('step-label').textContent     = STEP_LABELS[currentStep]   || '';
  $('wizard-heading').textContent = STEP_HEADINGS[currentStep] || '';
  /* navigation buttons */
  $('btn-back').style.display   = (currentStep > 1 && currentStep < 4) ? 'flex' : 'none';
  $('btn-next').style.display   = currentStep < 4    ? 'flex' : 'none';
  $('btn-submit').style.display = currentStep === 4  ? 'flex' : 'none';
}

/* ─── navigation ─────────────────────────────────────────── */
function goNext() {
  if (!validateStep(currentStep)) return;
  if (currentStep === 3) {
    buildReview();
  }
  goToStep(currentStep + 1, 'forward');
}
function goBack() {
  goToStep(currentStep - 1, 'backward');
}

/* ─── per-step validation ────────────────────────────────── */
function validateStep(step) {
  var ok = true;

  if (step === 1) {
    if (!selectedRole) {
      showErr('err-role', true);
      ok = false;
    } else {
      showErr('err-role', false);
    }
  }

  if (step === 2) {
    if (selectedRole === 'admin') {
      var adminEmail = $('f-admin-email').value.trim();
      var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(adminEmail);
      showErr('err-admin-email', !emailOk);
      markInput('f-admin-email', !emailOk);
      if (!emailOk) ok = false;
    }

    if (selectedRole === 'customer') {
      var first = $('f-first').value.trim();
      var last  = $('f-last').value.trim();
      var email = $('f-email').value.trim();
      var phone = $('f-phone').value.trim();
      /* address: require text to be filled; verification is preferred but not a hard block */
      var addrText  = $('f-addr').value.trim();
      var addrOk    = addrText.length > 0;

      showErr('err-first', !first);  markInput('f-first', !first);  if (!first) ok = false;
      showErr('err-last',  !last);   markInput('f-last',  !last);   if (!last)  ok = false;

      var eOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      showErr('err-email', !eOk); markInput('f-email', !eOk); if (!eOk) ok = false;

      var pOk = phone.length >= 7;
      showErr('err-phone', !pOk); markInput('f-phone', !pOk); if (!pOk) ok = false;

      showErr('err-addr', !addrOk); markInput('f-addr', !addrOk); if (!addrOk) ok = false;

      /* DOB check */
      var dob = $('f-dob').value;
      if (dob) {
        var dobDate = new Date(dob);
        var minDate = new Date(); minDate.setFullYear(minDate.getFullYear() - 13);
        var dobOk = dobDate <= minDate;
        showErr('err-dob', !dobOk); markInput('f-dob', !dobOk); if (!dobOk) ok = false;
      }
    }

    if (selectedRole === 'provider') {
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

      if (selectedCategories.length === 0) {
        showErr('err-categories', true);
        ok = false;
      } else {
        showErr('err-categories', false);
        $('f-categories').value = selectedCategories.join(',');
      }

      /* personal info */
      var provFirst = $('f-prov-first').value.trim();
      var provLast  = $('f-prov-last').value.trim();
      var provEmail = $('f-prov-email').value.trim();
      var provPhone = $('f-prov-phone').value.trim();
      var provAddrText = $('f-prov-addr').value.trim();
      var provAddrOk   = provAddrText.length > 0;

      showErr('err-prov-first', !provFirst); markInput('f-prov-first', !provFirst); if (!provFirst) ok = false;
      showErr('err-prov-last',  !provLast);  markInput('f-prov-last',  !provLast);  if (!provLast)  ok = false;

      var peOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(provEmail);
      showErr('err-prov-email', !peOk); markInput('f-prov-email', !peOk); if (!peOk) ok = false;

      var ppOk = provPhone.length >= 7;
      showErr('err-prov-phone', !ppOk); markInput('f-prov-phone', !ppOk); if (!ppOk) ok = false;

      showErr('err-prov-addr', !provAddrOk); markInput('f-prov-addr', !provAddrOk); if (!provAddrOk) ok = false;

      var provDob = $('f-prov-dob').value;
      if (provDob) {
        var pdDate = new Date(provDob);
        var pmDate = new Date(); pmDate.setFullYear(pmDate.getFullYear() - 13);
        var pdOk = pdDate <= pmDate;
        showErr('err-prov-dob', !pdOk); markInput('f-prov-dob', !pdOk); if (!pdOk) ok = false;
      }
    }
  }

  if (step === 4) {
    var pw  = $('f-pw').value;
    var pwc = $('f-pw-confirm').value;
    var pwOk = pw.length >= 8;
    showErr('err-pw', !pwOk); markInput('f-pw', !pwOk); if (!pwOk) ok = false;

    var matchOk = pw === pwc && pwc.length > 0;
    showErr('err-pw-confirm', !matchOk);
    markInput('f-pw-confirm', !matchOk);
    if (!matchOk) ok = false;

    var terms = $('terms').checked;
    showErr('err-terms', !terms); if (!terms) ok = false;
  }

  /* scroll to first error */
  if (!ok) {
    var firstErr = document.querySelector('#pane-' + step + ' .field-error[style*="block"]');
    if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  return ok;
}

/* ─── form submit ────────────────────────────────────────── */
$('register-form').addEventListener('submit', function(e) {
  if (!validateStep(4)) { e.preventDefault(); return; }

  /* ── Copy pane field values into the always-present hidden POST fields.
        Pane inputs are inside display:none parents when not on step 2,
        and some browsers skip submitting inputs from hidden elements.
        The hidden fields at the top of the form are never hidden, so
        they always POST correctly regardless of the current step.       ── */
  if (selectedRole === 'customer') {
    $('post-first-name').value = $('f-first').value;
    $('post-last-name').value  = $('f-last').value;
    $('post-email').value      = $('f-email').value;
    $('post-phone').value      = $('f-phone').value;
    $('post-home-addr').value  = $('f-addr').value;
    $('post-gender').value     = $('f-gender').value;
    $('post-dob').value        = $('f-dob').value;
  }

  if (selectedRole === 'provider') {
    $('post-first-name').value = $('f-prov-first').value;
    $('post-last-name').value  = $('f-prov-last').value;
    $('post-email').value      = $('f-prov-email').value;
    $('post-phone').value      = $('f-prov-phone').value;
    $('post-home-addr').value  = $('f-prov-addr').value;
    $('post-gender').value     = $('f-prov-gender').value;
    $('post-dob').value        = $('f-prov-dob').value;
    /* address coordinates: copy provider values into canonical hidden fields */
    $('f-addr-lat').value      = $('f-prov-addr-lat').value;
    $('f-addr-lng').value      = $('f-prov-addr-lng').value;
    $('f-addr-place').value    = $('f-prov-addr-place').value;
    $('f-addr-verified').value = $('f-prov-addr-verified').value;
  }

  var btn = $('btn-submit');
  if (btn) { btn.textContent = 'Creating account…'; btn.disabled = true; }
});

/* ─── build review panel ─────────────────────────────────── */
function buildReview() {
  var roleBadgeMap = {
    admin:    { label: '🛡️ Admin',    cls: 'role-badge-admin'    },
    customer: { label: '👤 Customer', cls: 'role-badge-customer' },
    provider: { label: '💼 Provider', cls: 'role-badge-provider' },
  };
  var badge = roleBadgeMap[selectedRole] || { label: selectedRole, cls: '' };
  var badgeEl = $('review-role-badge');
  badgeEl.textContent = badge.label;
  badgeEl.className   = 'review-role-badge ' + badge.cls;

  var cards = $('review-cards');
  cards.innerHTML = '';

  if (selectedRole === 'admin') {
    cards.innerHTML = reviewCard('Account', [
      { label: 'Email', value: $('f-admin-email').value.trim() },
    ]);
  }

  if (selectedRole === 'customer') {
    cards.innerHTML =
      reviewCard('Personal Information', [
        { label: 'Name',   value: [$('f-first').value.trim(), $('f-last').value.trim()].join(' ') },
        { label: 'Email',  value: $('f-email').value.trim() },
        { label: 'Phone',  value: $('f-phone').value.trim() },
        { label: 'Gender', value: $('f-gender').value ? $('f-gender').options[$('f-gender').selectedIndex]?.text : '—' },
        { label: 'Date of Birth', value: $('f-dob').value || '—' },
      ]) +
      reviewCard('Home Address', [
        { label: 'Address', value: $('f-addr').value || '—' },
      ]);
  }

  if (selectedRole === 'provider') {
    var svcLabels = { home_service: 'Home Service', business_location: 'Business Location', flexible: 'Flexible (Both)' };
    cards.innerHTML =
      reviewCard('Business Details', [
        { label: 'Business Name',    value: $('f-biz-name').value.trim() || '—' },
        { label: 'Service Type',     value: svcLabels[$('f-svc-type').value] || '—' },
        { label: 'Business Address', value: $('f-biz-addr') ? ($('f-biz-addr').value || '—') : '—' },
        { label: 'Category',         value: selectedCategories.length ? selectedCategories[0] : '—' },
      ]) +
      reviewCard('Personal Information', [
        { label: 'Name',   value: [$('f-prov-first').value.trim(), $('f-prov-last').value.trim()].filter(Boolean).join(' ') || '—' },
        { label: 'Email',  value: $('f-prov-email').value.trim() || '—' },
        { label: 'Phone',  value: $('f-prov-phone').value.trim() || '—' },
        { label: 'Gender', value: $('f-prov-gender').value ? $('f-prov-gender').options[$('f-prov-gender').selectedIndex]?.text : '—' },
        { label: 'Date of Birth', value: $('f-prov-dob').value || '—' },
      ]) +
      reviewCard('Home Address', [
        { label: 'Address',  value: $('f-prov-addr').value || '—' },
      ]);
  }
}

function reviewCard(title, rows) {
  var rowsHtml = rows.map(function(r) {
    return '<div class="review-row"><span class="review-row-label">' + escHtml(r.label) + '</span><span class="review-row-value">' + escHtml(r.value) + '</span></div>';
  }).join('');
  return '<div class="review-card"><div class="review-card-title">' + escHtml(title) + '</div>' + rowsHtml + '</div>';
}

/* ─── role selector ─────────────────────────────────────── */
$('role-selector').addEventListener('click', function(e) {
  var opt = e.target.closest('.role-option[data-role]');
  if (!opt) return;
  selectRole(opt.getAttribute('data-role'));
});
$('role-selector').addEventListener('keydown', function(e) {
  if (e.key === ' ' || e.key === 'Enter') {
    var opt = e.target.closest('.role-option[data-role]');
    if (opt) { e.preventDefault(); selectRole(opt.getAttribute('data-role')); }
  }
});

function selectRole(role) {
  selectedRole = role;
  $('role-input').value = role;
  showErr('err-role', false);

  /* highlight card */
  document.querySelectorAll('#role-selector .role-option').forEach(function(o) {
    var isThis = o.getAttribute('data-role') === role;
    o.classList.toggle('selected', isThis);
    o.setAttribute('aria-pressed', isThis ? 'true' : 'false');
  });

  /* show role description */
  ['admin','customer','provider'].forEach(function(r) {
    var el = $('desc-' + r);
    if (el) el.style.display = r === role ? 'flex' : 'none';
  });
}

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
            $('f-biz-verified').value = '0';
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
      var opt = e.target.closest('.svc-option'); if (opt) { e.preventDefault(); opt.click(); }
    }
  });
}

function showBizVerifiedBadge(show) {
  var badge = $('biz-addr-badge');
  if (badge) badge.style.display = show ? 'inline-flex' : 'none';
}

/* ─── category single-select ─────────────────────────────── */
document.querySelectorAll('.cat-chip').forEach(function(chip) {
  chip.addEventListener('click', function() {
    var cat = chip.getAttribute('data-cat');
    var alreadySelected = chip.classList.contains('selected');

    /* deselect all chips first */
    document.querySelectorAll('.cat-chip').forEach(function(c) {
      c.classList.remove('selected');
      c.setAttribute('aria-pressed', 'false');
    });

    if (!alreadySelected) {
      /* select the clicked one */
      chip.classList.add('selected');
      chip.setAttribute('aria-pressed', 'true');
      selectedCategories = [cat];
      showErr('err-categories', false);
    } else {
      /* clicking the selected chip deselects it */
      selectedCategories = [];
    }
    $('f-categories').value = selectedCategories.join(',');
  });
});

/* ─── ADDRESS AUTOCOMPLETE ENGINE ────────────────────────── */
var _placesService   = null;
var _geocoderService = null;
var _mapsReady       = false;

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
      showHomeVerifiedBadge(true);
      showErr('err-addr', false);
      markInput('f-addr', false);
    },
    function() {
      $('f-addr-verified').value = '0';
      showHomeVerifiedBadge(false);
    }
  );
  initAddressInput('f-prov-addr', 'prov-addr-suggestions', 'prov-addr-spinner',
    function(place) {
      $('f-prov-addr-lat').value      = place.lat;
      $('f-prov-addr-lng').value      = place.lng;
      $('f-prov-addr-place').value    = place.placeId || '';
      $('f-prov-addr-verified').value = '1';
      $('f-prov-addr').value          = place.formatted;
      showProvAddrBadge(true);
      showErr('err-prov-addr', false);
      markInput('f-prov-addr', false);
    },
    function() {
      $('f-prov-addr-verified').value = '0';
      showProvAddrBadge(false);
    }
  );
}

function showHomeVerifiedBadge(show) {
  var badge = $('home-addr-verified-badge');
  if (badge) badge.style.display = show ? 'inline-flex' : 'none';
}

function showProvAddrBadge(show) {
  var badge = $('prov-addr-verified-badge');
  if (badge) badge.style.display = show ? 'inline-flex' : 'none';
}

function initAddressInput(inputId, listId, spinnerId, onSelect, onClear) {
  var input   = $(inputId);
  var list    = $(listId);
  var spinner = $(spinnerId);
  if (!input || !list) return;
  var debounceTimer = null;
  var activeIndex   = -1;
  var currentItems  = [];

  input.addEventListener('input', function() {
    activeIndex = -1;
    if (onClear) onClear();
    clearTimeout(debounceTimer);
    var q = input.value.trim();
    if (q.length < 3) { hideList(); return; }
    showSpinner(true);
    debounceTimer = setTimeout(function() { fetchSuggestions(q); }, 350);
  });

  input.addEventListener('keydown', function(e) {
    if (!list.children.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault(); activeIndex = Math.min(activeIndex + 1, currentItems.length - 1); highlightItem(activeIndex);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); highlightItem(activeIndex);
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault(); pickItem(currentItems[activeIndex]);
    } else if (e.key === 'Escape') { hideList(); }
  });

  document.addEventListener('click', function(e) {
    if (!input.contains(e.target) && !list.contains(e.target)) hideList();
  });

  function fetchSuggestions(query) {
    if (_mapsReady && _placesService) {
      _placesService.getPlacePredictions(
        { input: query, componentRestrictions: { country: 'ph' } },
        function(predictions, status) {
          showSpinner(false);
          if (status !== google.maps.places.PlacesServiceStatus.OK || !predictions) { hideList(); return; }
          currentItems = predictions.map(function(p) {
            return { label: p.description, placeId: p.place_id, source: 'google' };
          });
          renderList(currentItems);
        }
      );
    } else {
      fetch('https://nominatim.openstreetmap.org/search?' + new URLSearchParams({
        q: query + ', Bacolod City, Philippines',
        format: 'json', addressdetails: 1, limit: 6, countrycodes: 'ph', 'accept-language': 'en',
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
            province: item.address.province || item.address.state || item.address.county || item.address.region || '',
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
      li.setAttribute('role', 'option'); li.setAttribute('tabindex', '-1');
      li.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">' +
          '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>' +
          '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>' +
        '</svg><span>' + escHtml(item.label) + '</span>';
      li.addEventListener('mousedown', function(e) { e.preventDefault(); });
      li.addEventListener('click',     function() { pickItem(item); });
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
    hideList();
    if (item.source === 'google') {
      showSpinner(true);
      _geocoderService.geocode({ placeId: item.placeId }, function(results, status) {
        showSpinner(false);
        if (status !== google.maps.GeocoderStatus.OK || !results[0]) return;
        var r   = results[0];
        var lat = r.geometry.location.lat();
        var lng = r.geometry.location.lng();
        var city = '', province = '';
        r.address_components.forEach(function(c) {
          if (c.types.includes('locality'))                   city     = c.long_name;
          if (c.types.includes('administrative_area_level_1')) province = c.long_name;
        });
        onSelect({ formatted: item.label, lat: lat, lng: lng, placeId: item.placeId, city: city, province: province });
      });
    } else {
      onSelect({ formatted: item.label, lat: item.lat, lng: item.lng, placeId: null, city: item.city, province: item.province });
    }
  }

  function showSpinner(on) { if (spinner) spinner.style.display = on ? 'block' : 'none'; }
  function hideList()      { list.style.display = 'none'; list.innerHTML = ''; currentItems = []; activeIndex = -1; }
}

/* init home address (Nominatim fallback, no Google key) */
if (!window.QB_MAPS_KEY) {
  document.addEventListener('DOMContentLoaded', function() {
    initAddressInput('f-addr', 'addr-suggestions', 'addr-spinner',
      function(place) {
        $('f-addr-lat').value      = place.lat;
        $('f-addr-lng').value      = place.lng;
        $('f-addr-place').value    = place.placeId || '';
        $('f-addr-verified').value = '1';
        $('f-addr').value          = place.formatted;
        showHomeVerifiedBadge(true);
        showErr('err-addr', false);
        markInput('f-addr', false);
      },
      function() {
        $('f-addr-verified').value = '0';
        showHomeVerifiedBadge(false);
      }
    );
    initAddressInput('f-prov-addr', 'prov-addr-suggestions', 'prov-addr-spinner',
      function(place) {
        $('f-prov-addr-lat').value      = place.lat;
        $('f-prov-addr-lng').value      = place.lng;
        $('f-prov-addr-place').value    = place.placeId || '';
        $('f-prov-addr-verified').value = '1';
        $('f-prov-addr').value          = place.formatted;
        showProvAddrBadge(true);
        showErr('err-prov-addr', false);
        markInput('f-prov-addr', false);
      },
      function() {
        $('f-prov-addr-verified').value = '0';
        showProvAddrBadge(false);
      }
    );
  });
}

/* ─── password toggle ────────────────────────────────────── */
function togglePw(inputId, eyeOnId, eyeOffId) {
  var input  = $(inputId);
  var eyeOn  = $(eyeOnId);
  var eyeOff = $(eyeOffId);
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

  /* requirement indicators */
  toggleReq('req-len',     value.length >= 8);
  toggleReq('req-upper',   /[A-Z]/.test(value));
  toggleReq('req-num',     /[0-9]/.test(value));
  toggleReq('req-special', /[^A-Za-z0-9]/.test(value));

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

function toggleReq(id, met) {
  var el = $(id);
  if (!el) return;
  el.classList.toggle('met', met);
}

/* ─── password match ─────────────────────────────────────── */
function checkMatch() {
  var pw  = $('f-pw').value;
  var pwc = $('f-pw-confirm').value;
  var row = $('pw-match-row');
  var icon= $('pw-match-icon');
  var txt = $('pw-match-text');
  if (!pwc) { row.style.display = 'none'; return; }
  row.style.display = 'flex';
  var match = pw === pwc;
  icon.textContent = match ? '✓' : '✗';
  icon.className   = 'pw-match-icon ' + (match ? 'match-ok' : 'match-fail');
  txt.textContent  = match ? 'Passwords match' : 'Passwords do not match';
  txt.className    = 'pw-match-text ' + (match ? 'match-ok' : 'match-fail');
  if (match) { showErr('err-pw-confirm', false); markInput('f-pw-confirm', false); }
}

/* ─── step 2 panel reveal ────────────────────────────────── */
function showStep2Panel() {
  ['admin','customer','provider'].forEach(function(r) {
    var el = $('step2-' + r);
    if (el) el.style.display = r === selectedRole ? 'block' : 'none';
  });
}

/* override goNext to show step 2 panel on first transition */
var _origGoNext = goNext;
goNext = function() {
  if (currentStep === 1) {
    if (!validateStep(1)) return;
    if (selectedRole === 'admin') {
      window.location.href = '<?= BASE_URL ?>login';
      return;
    }
    showStep2Panel();
    goToStep(2, 'forward');
    return;
  }
  if (currentStep === 2) {
    if (!validateStep(2)) return;
    buildReview();
    goToStep(3, 'forward');
    return;
  }
  if (currentStep === 3) {
    goToStep(4, 'forward');
    return;
  }
};

/* ─── utility ────────────────────────────────────────────── */
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ─── init ───────────────────────────────────────────────── */
updateStepperUI();
</script>

</body>
</html>