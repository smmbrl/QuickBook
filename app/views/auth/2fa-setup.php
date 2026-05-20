<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enable Two-Factor Authentication — QuickBook</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    <!-- ✅ QR code rendered by JavaScript — no server-side image needed -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        :root {
            --bg: #0d0f14;
            --surface: #161921;
            --border: rgba(255,255,255,0.07);
            --accent: #f0c040;
            --accent-dim: rgba(240,192,64,0.12);
            --text: #e8eaf0;
            --muted: #6b7280;
            --success: #34d399;
            --error: #f87171;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: var(--bg);
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-image:
                radial-gradient(ellipse 60% 40% at 70% 20%, rgba(240,192,64,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 40% 60% at 10% 80%, rgba(99,102,241,0.05) 0%, transparent 60%);
        }

        .card {
            width: 100%;
            max-width: 480px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }

        .logo {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: 0.05em;
            margin-bottom: 2rem;
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .steps {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        .step { flex: 1; height: 3px; border-radius: 2px; background: var(--border); }
        .step.active { background: var(--accent); }
        .step.done   { background: var(--success); }

        /* ── QR container: white box, QR renders inside by JS ── */
        .qr-block {
            background: #fff;
            border-radius: 14px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            min-height: 200px;
        }
        /* qrcodejs injects a canvas or img — center it */
        #qrcode { display: flex; align-items: center; justify-content: center; }
        #qrcode canvas, #qrcode img { display: block; }

        .secret-row {
            background: var(--accent-dim);
            border: 1px solid rgba(240,192,64,0.2);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .secret-label { color: var(--muted); font-size: 0.75rem; display: block; margin-bottom: 2px; }
        .secret-value {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--accent);
            letter-spacing: 0.08em;
            word-break: break-all;
        }
        .copy-btn {
            background: none;
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 7px;
            padding: 0.35rem 0.75rem;
            font-size: 0.78rem;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            font-family: 'DM Sans', sans-serif;
        }
        .copy-btn:hover { color: var(--text); border-color: var(--accent); }

        .instruction {
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .instruction strong { color: var(--text); }

        .otp-label { font-size: 0.85rem; font-weight: 500; margin-bottom: 0.5rem; display: block; }

        .otp-input {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Courier New', monospace;
            font-size: 1.4rem;
            text-align: center;
            letter-spacing: 0.5em;
            padding: 0.85rem;
            outline: none;
            transition: border-color 0.2s;
            margin-bottom: 1.25rem;
        }
        .otp-input:focus { border-color: var(--accent); }

        .flash {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }
        .flash.error   { background: rgba(248,113,113,0.12); color: var(--error); border: 1px solid rgba(248,113,113,0.2); }
        .flash.success { background: rgba(52,211,153,0.12);  color: var(--success); border: 1px solid rgba(52,211,153,0.2); }

        .btn-primary {
            width: 100%;
            background: var(--accent);
            color: #0d0f14;
            border: none;
            border-radius: 10px;
            padding: 0.85rem;
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            letter-spacing: 0.03em;
            transition: opacity 0.2s, transform 0.15s;
        }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.25rem;
            font-size: 0.85rem;
            color: var(--muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--text); }

        .enabled-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(52,211,153,0.12);
            color: var(--success);
            border: 1px solid rgba(52,211,153,0.2);
            border-radius: 20px;
            padding: 0.3rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
<div class="card">

    <div class="logo">⚡ QuickBook</div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="flash error"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="flash success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <h1>Two-Factor<br>Authentication</h1>
    <p class="subtitle">Add an extra layer of security to your QuickBook account.</p>

    <?php if (!empty($alreadyEnabled)): ?>
        <div class="enabled-badge">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M10 3L5 9L2 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            2FA is currently active
        </div>
        <p class="instruction">Re-scan the QR code to re-link your authenticator app, then enter the 6-digit code to confirm.</p>
    <?php else: ?>
        <div class="steps">
            <div class="step done"></div>
            <div class="step active"></div>
            <div class="step"></div>
        </div>
        <p class="instruction">
            <strong>Step 1:</strong> Install <strong>Google Authenticator</strong> or <strong>Authy</strong> on your phone.<br><br>
            <strong>Step 2:</strong> Scan the QR code below, then enter the 6-digit code it generates.
        </p>
    <?php endif; ?>

    <!-- ✅ QR code renders here via JavaScript -->
    <div class="qr-block">
        <div id="qrcode"></div>
    </div>

    <!-- Manual secret key -->
    <div class="secret-row">
        <div>
            <span class="secret-label">Can't scan? Enter this key manually:</span>
            <span class="secret-value" id="secret-text"><?= htmlspecialchars($secret) ?></span>
        </div>
        <button class="copy-btn" onclick="copySecret()">Copy</button>
    </div>

    <!-- OTP confirmation form -->
    <form method="POST" action="<?= BASE_URL ?>auth/2fa/enable">
        <label class="otp-label" for="otp">Enter the 6-digit code from your app</label>
        <input
            type="text"
            id="otp"
            name="otp"
            class="otp-input"
            maxlength="6"
            pattern="[0-9]{6}"
            inputmode="numeric"
            autocomplete="one-time-code"
            placeholder="000000"
            required
            autofocus
        >
        <button type="submit" class="btn-primary">Verify &amp; Enable 2FA</button>
    </form>

    <a href="<?= BASE_URL ?>profile" class="back-link">← Back to Profile</a>
</div>

<script>
    // ✅ Render the QR code using the otpauth:// URI from PHP
    const otpauthUrl = <?= json_encode($otpauthUrl) ?>;

    new QRCode(document.getElementById('qrcode'), {
        text:           otpauthUrl,
        width:          180,
        height:         180,
        colorDark:      '#000000',
        colorLight:     '#ffffff',
        correctLevel:   QRCode.CorrectLevel.H
    });

    // Copy secret key to clipboard
    function copySecret() {
        const text = document.getElementById('secret-text').textContent.trim();
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.querySelector('.copy-btn');
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = 'Copy', 2000);
        });
    }

    // Auto-submit when all 6 digits are typed
    document.getElementById('otp').addEventListener('input', function () {
        if (this.value.length === 6) this.closest('form').submit();
    });
</script>
</body>
</html>