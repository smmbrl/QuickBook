<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/NotificationHelper.php';

class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    public function login(): void
    {
        $email    = trim($_POST['email']    ?? '');
        $password =      $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['flash_error'] = 'Email and password are required.';
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Invalid email or password. Please try again.';
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['flash_error'] = 'Invalid email or password. Please try again.';
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        if (!$user['is_verified']) {
            $_SESSION['flash_error'] = 'Please verify your email first.';
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        // ★ 2FA INTERCEPT — if 2FA is on, hold user in a "pending" session
        //   and send them to the OTP challenge page before granting access.
        if (!empty($user['totp_enabled'])) {
            session_regenerate_id(true);
            $_SESSION['2fa_user_id'] = $user['id'];   // NOT yet the real session
            header('Location: ' . BASE_URL . 'auth/2fa/verify'); exit;
        }

        // ── Normal flow (2FA not enabled) ──────────────────────────────────
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_name']  = $user['first_name'];
        $_SESSION['user_email'] = $user['email'];

        // Log admin logins into tbl_admin_logs
        if ($user['role'] === 'admin') {
            $db   = Database::getInstance();
            $ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $db->prepare("
                INSERT INTO tbl_admin_logs (admin_id, action, target_type, target_id, details, ip_address)
                VALUES (?, 'admin_login', NULL, NULL, 'Admin logged in', ?)
            ");
            $stmt->execute([$user['id'], $ip]);
        }

        $map = ['admin' => 'admin/dashboard', 'provider' => 'provider/dashboard', 'customer' => 'dashboard'];
        header('Location: ' . BASE_URL . ($map[$user['role']] ?? 'home')); exit;
    }

    public function register(): void
    {
        $firstName   = trim($_POST['first_name'] ?? '');
        $lastName    = trim($_POST['last_name']  ?? '');
        $email       = strtolower(trim($_POST['email'] ?? ''));
        $phone       = $_POST['phone']         ?? '';
        $password    = $_POST['password']      ?? '';
        $role        = in_array($_POST['role'] ?? '', ['customer','provider']) ? $_POST['role'] : 'customer';
        $terms       = isset($_POST['terms']);
        $gender      = in_array($_POST['gender'] ?? '', ['male','female','non_binary','prefer_not_to_say']) ? $_POST['gender'] : null;
        $dateOfBirth = trim($_POST['date_of_birth'] ?? '') ?: null;

        $errors = [];
        if (empty($firstName))         $errors[] = 'First name is required.';
        if (empty($lastName))          $errors[] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($password) < 8)     $errors[] = 'Password must be at least 8 characters.';
        if (!$terms)                   $errors[] = 'You must accept the Terms of Service.';
        if (strlen($phone) < 7)        $errors[] = 'Valid phone number required.';
        if ($this->userModel->emailExists($email)) $errors[] = 'Email already registered.';

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: ' . BASE_URL . 'register'); exit;
        }

        $userId = $this->userModel->create([
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email'         => $email,
            'phone'         => $phone,
            'gender'        => $gender,
            'date_of_birth' => $dateOfBirth,
            'password'      => $password,
            'role'          => $role,
        ]);

        if (!$userId) {
            $_SESSION['flash_error'] = 'Registration failed. Please try again.';
            header('Location: ' . BASE_URL . 'register'); exit;
        }

        $this->userModel->markVerified($userId);

        $db = Database::getInstance();

        if ($role === 'provider') {
            $db->prepare("INSERT INTO tbl_provider_profiles (user_id, business_name) VALUES (?,?)")
               ->execute([$userId, $firstName . ' ' . $lastName]);
        }

        // ── Welcome notification to the new user ──────────────────────────
        $roleLabel = ucfirst($role);
        $welcomeLink = $role === 'provider' ? BASE_URL . 'provider/dashboard' : BASE_URL . 'dashboard';
        NotificationHelper::send($db, [$userId], 'system',
            'Welcome to QuickBook!',
            "Hi {$firstName}! Your {$roleLabel} account has been created successfully. " .
            ($role === 'provider'
                ? 'Set up your services and availability to start receiving bookings.'
                : 'Browse providers and book your first service today!'),
            '',
            $welcomeLink
        );

        // ── Notify all admins of new registration ─────────────────────────
        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            "[Admin] New {$roleLabel} Registered",
            "{$firstName} {$lastName} ({$email}) just registered as a {$role}.",
            '',
            BASE_URL . 'admin/users'
        );

        $_SESSION['flash_success'] = 'Account created! You can now sign in.';
        header('Location: ' . BASE_URL . 'login'); exit;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/');
        header('Location: ' . BASE_URL . 'login'); exit;
    }

    public function verifyEmail(): void
    {
        $token  = $_GET['token'] ?? '';
        $userId = $token ? $this->userModel->validateVerificationToken($token) : false;
        if ($userId) {
            $this->userModel->markVerified($userId);
            $_SESSION['flash_success'] = 'Email verified! You can now sign in.';
        } else {
            $_SESSION['flash_error'] = 'Invalid or expired verification link.';
        }
        header('Location: ' . BASE_URL . 'login'); exit;
    }

    public function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            require_once __DIR__ . '/../views/forgot-password.php'; return;
        }

        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Please enter a valid email address.'];
            header('Location: ' . BASE_URL . 'auth/forgot-password'); exit;
        }

        $userModel = new User();
        $user      = $userModel->findByEmail($email);

        if ($user) {
            $token     = $userModel->createPasswordResetToken($email);
            $resetLink = BASE_URL . 'auth/reset-password?token=' . $token;
            error_log("[QuickBook] Password reset link for {$email}: {$resetLink}");
        }

        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  => 'If that email is registered, a reset link has been sent. Check your inbox (and spam folder).'
        ];
        header('Location: ' . BASE_URL . 'auth/forgot-password'); exit;
    }

    public function showResetForm(): void
    {
        $token = trim($_GET['token'] ?? '');

        if (empty($token)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Missing or invalid reset token.'];
            header('Location: ' . BASE_URL . 'auth/forgot-password'); exit;
        }

        $userModel = new User();
        $email     = $userModel->validatePasswordResetToken($token);

        if (!$email) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'This reset link has expired or already been used. Please request a new one.'];
            header('Location: ' . BASE_URL . 'auth/forgot-password'); exit;
        }

        require_once __DIR__ . '/../views/reset-password.php';
    }

    public function resetPassword(): void
    {
        $token     = trim($_POST['token']      ?? '');
        $newPw     = $_POST['new_password']    ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        $errors = [];
        if (empty($token))         $errors[] = 'Reset token is missing.';
        if (strlen($newPw) < 8)    $errors[] = 'Password must be at least 8 characters.';
        if ($newPw !== $confirmPw) $errors[] = 'Passwords do not match.';

        if (!empty($errors)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => implode(' ', $errors)];
            header('Location: ' . BASE_URL . 'auth/reset-password?token=' . urlencode($token)); exit;
        }

        $userModel = new User();
        $email     = $userModel->validatePasswordResetToken($token);

        if (!$email) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'This reset link has expired or already been used.'];
            header('Location: ' . BASE_URL . 'auth/forgot-password'); exit;
        }

        $user = $userModel->findByEmail($email);
        if (!$user) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Account not found.'];
            header('Location: ' . BASE_URL . 'auth/forgot-password'); exit;
        }

        $userModel->updatePassword((int)$user['id'], $newPw);
        $userModel->consumePasswordResetToken($token);

        // ── Notify admins of password reset (security event) ──────────────
        $db = Database::getInstance();
        NotificationHelper::send($db, NotificationHelper::adminIds($db), 'system',
            '[Admin] Password Reset',
            "User {$user['first_name']} {$user['last_name']} ({$email}) reset their password.",
            '',
            BASE_URL . 'admin/users'
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password reset successfully. You can now sign in.'];
        header('Location: ' . BASE_URL . 'login'); exit;
    }
}