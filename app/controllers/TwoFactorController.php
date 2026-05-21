<?php
// app/controllers/TwoFactorController.php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;

class TwoFactorController
{
    private Google2FA $g2fa;
    private User      $userModel;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->g2fa      = new Google2FA();
        $this->userModel = new User();
    }

    public function setup(): void
    {
        $this->requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $user   = $this->userModel->findById($userId);

        $secret = $this->g2fa->generateSecretKey();
        $_SESSION['2fa_pending_secret'] = $secret;

        $appName = 'QuickBook';
        $email   = $user['email'];

        // ✅ FIX: Use getQRCodeUrl() to get the otpauth:// URI.
        //    The view will render it as a QR code using JavaScript (qrcodejs).
        //    This avoids the broken server-side PNG generation on XAMPP.
        $otpauthUrl = $this->g2fa->getQRCodeUrl($appName, $email, $secret);

        $alreadyEnabled = (bool) $user['totp_enabled'];

        require_once __DIR__ . '/../views/auth/2fa-setup.php';
    }

    public function enable(): void
    {
        $this->requireLogin();

        $otp    = trim($_POST['otp'] ?? '');
        $secret = $_SESSION['2fa_pending_secret'] ?? '';

        if (empty($secret)) {
            $_SESSION['flash_error'] = 'Session expired. Please start 2FA setup again.';
            header('Location: ' . BASE_URL . 'auth/2fa/setup'); exit;
        }

        // window=2 allows ±1 minute tolerance for clock differences between
        // your XAMPP server and the phone. Fixes "Invalid code" on local dev.
        $valid = $this->g2fa->verifyKey($secret, $otp, 2);

        if (!$valid) {
            $_SESSION['flash_error'] = 'Invalid code. Please try again.';
            header('Location: ' . BASE_URL . 'auth/2fa/setup'); exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE tbl_users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?"
        );
        $stmt->execute([$secret, $_SESSION['user_id']]);
        unset($_SESSION['2fa_pending_secret']);

        $_SESSION['flash_success'] = '2FA has been enabled on your account!';
        header('Location: ' . BASE_URL . 'profile'); exit;
    }

    public function showVerify(): void
    {
        if (empty($_SESSION['2fa_user_id'])) {
            header('Location: ' . BASE_URL . 'login'); exit;
        }
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        require_once __DIR__ . '/../views/auth/2fa-verify.php';
    }

    public function verify(): void
    {
        if (empty($_SESSION['2fa_user_id'])) {
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        $otp    = trim($_POST['otp'] ?? '');
        $userId = (int) $_SESSION['2fa_user_id'];
        $user   = $this->userModel->findById($userId);

        if (!$user || !$user['totp_enabled']) {
            $_SESSION['flash_error'] = 'Something went wrong. Please log in again.';
            unset($_SESSION['2fa_user_id']);
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        $valid = $this->g2fa->verifyKey($user['totp_secret'], $otp, 2);

        if (!$valid) {
            $_SESSION['flash_error'] = 'Invalid or expired code. Please try again.';
            header('Location: ' . BASE_URL . 'auth/2fa/verify'); exit;
        }

        session_regenerate_id(true);
        unset($_SESSION['2fa_user_id']);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_name']  = $user['first_name'];
        $_SESSION['user_email'] = $user['email'];

        $map = ['admin' => 'admin/dashboard', 'provider' => 'provider/dashboard', 'customer' => 'dashboard'];
        header('Location: ' . BASE_URL . ($map[$user['role']] ?? 'home')); exit;
    }

    public function disable(): void
    {
        $this->requireLogin();

        $otp    = trim($_POST['otp'] ?? '');
        $userId = (int) $_SESSION['user_id'];
        $user   = $this->userModel->findById($userId);

        if (!$user['totp_enabled']) {
            $_SESSION['flash_error'] = '2FA is not enabled on your account.';
            header('Location: ' . BASE_URL . 'profile'); exit;
        }

        $valid = $this->g2fa->verifyKey($user['totp_secret'], $otp, 2);

        if (!$valid) {
            $_SESSION['flash_error'] = 'Invalid code. 2FA was NOT disabled.';
            header('Location: ' . BASE_URL . 'profile'); exit;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE tbl_users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?"
        );
        $stmt->execute([$userId]);

        $_SESSION['flash_success'] = '2FA has been disabled.';
        header('Location: ' . BASE_URL . 'profile'); exit;
    }

    private function requireLogin(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'login'); exit;
        }
    }
}