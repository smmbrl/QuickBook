<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Required — QuickBook</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0d0f14;
            --surface: #161921;
            --border: rgba(255,255,255,0.07);
            --accent: #f0c040;
            --text: #e8eaf0;
            --muted: #6b7280;
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
                radial-gradient(ellipse 50% 50% at 50% 0%, rgba(240,192,64,0.08) 0%, transparent 70%);
        }

        .card {
            width: 100%;
            max-width: 400px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
            text-align: center;
        }

        .shield-icon {
            width: 64px;
            height: 64px;
            background: rgba(240,192,64,0.12);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.8rem;
        }

        .logo {
            font-family: 'Syne', sans-serif;
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--muted);
            font-size: 0.875rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .flash.error {
            background: rgba(248,113,113,0.12);
            color: var(--error);
            border: 1px solid rgba(248,113,113,0.2);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            text-align: left;
        }

        /* ── OTP inputs (6 separate boxes) ── */
        .otp-boxes {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .otp-box {
            width: 48px;
            height: 56px;
            background: rgba(255,255,255,0.04);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Syne', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            text-align: center;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
            caret-color: var(--accent);
        }
        .otp-box:focus {
            border-color: var(--accent);
            background: rgba(240,192,64,0.06);
        }

        /* Hidden real input for form submission */
        #otp-hidden { display: none; }

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
            margin-top: 1.25rem;
            font-size: 0.85rem;
            color: var(--muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--text); }

        .hint {
            margin-top: 1rem;
            font-size: 0.8rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">⚡ QuickBook</div>

    <div class="shield-icon">🔐</div>

    <h1>Verify Your Identity</h1>
    <p class="subtitle">Enter the 6-digit code from your authenticator app to continue.</p>

    <?php if (!empty($error)): ?>
        <div class="flash error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>auth/2fa/verify" id="verify-form">
        <input type="hidden" name="otp" id="otp-hidden">

        <div class="otp-boxes" id="otp-boxes">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <input
                    type="text"
                    class="otp-box"
                    maxlength="1"
                    pattern="[0-9]"
                    inputmode="numeric"
                    autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                    data-index="<?= $i ?>"
                >
            <?php endfor; ?>
        </div>

        <button type="submit" class="btn-primary">Verify &amp; Sign In</button>
    </form>

    <a href="<?= BASE_URL ?>login" class="back-link">← Back to Login</a>
    <p class="hint">Code refreshes every 30 seconds. Open Google Authenticator or Authy.</p>
</div>

<script>
const boxes   = Array.from(document.querySelectorAll('.otp-box'));
const hidden  = document.getElementById('otp-hidden');
const form    = document.getElementById('verify-form');

// Focus first box on load
boxes[0].focus();

boxes.forEach((box, i) => {
    box.addEventListener('input', e => {
        const val = e.target.value.replace(/\D/g, '');
        e.target.value = val;

        if (val && i < 5) boxes[i + 1].focus();

        // Sync hidden input
        hidden.value = boxes.map(b => b.value).join('');

        // Auto-submit when all 6 are filled
        if (hidden.value.length === 6) {
            setTimeout(() => form.submit(), 150);
        }
    });

    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && i > 0) {
            boxes[i - 1].focus();
            boxes[i - 1].value = '';
        }
    });

    // Allow paste on first box
    box.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData)
            .getData('text').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((ch, j) => {
            if (boxes[j]) boxes[j].value = ch;
        });
        hidden.value = boxes.map(b => b.value).join('');
        if (hidden.value.length === 6) form.submit();
    });
});
</script>
</body>
</html>