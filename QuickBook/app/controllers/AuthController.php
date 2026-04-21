<?php
require_once __DIR__ . '/../models/User.php';

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

        $user = $this->userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['flash_error'] = 'Invalid email or password.';
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        if (!$user['is_verified']) {
            $_SESSION['flash_error'] = 'Please verify your email first.';
            header('Location: ' . BASE_URL . 'login'); exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_name']  = $user['first_name'];
        $_SESSION['user_email'] = $user['email'];

        $map = ['admin' => 'admin/dashboard', 'provider' => 'provider/dashboard', 'customer' => 'dashboard'];
        header('Location: ' . BASE_URL . ($map[$user['role']] ?? 'home')); exit;
    }

    public function register(): void
    {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $phone     = $_POST['phone']    ?? '';
        $password  = $_POST['password'] ?? '';
        $role      = in_array($_POST['role'] ?? '', ['customer','provider']) ? $_POST['role'] : 'customer';
        $terms     = isset($_POST['terms']);

        $errors = [];
        if (empty($firstName))         $errors[] = 'First name is required.';
        if (empty($lastName))          $errors[] = 'Last name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($password) < 8)     $errors[] = 'Password must be at least 8 characters.';
        if (!$terms)                   $errors[] = 'You must accept the Terms of Service.';
        if ($this->userModel->emailExists($email)) $errors[] = 'Email already registered.';

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: ' . BASE_URL . 'register'); exit;
        }

        $userId = $this->userModel->create([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'phone'      => $phone,
            'password'   => $password,
            'role'       => $role,
        ]);

        if (!$userId) {
            $_SESSION['flash_error'] = 'Registration failed. Please try again.';
            header('Location: ' . BASE_URL . 'register'); exit;
        }

        // Auto-verify for now (no email server needed on localhost)
        $this->userModel->markVerified($userId);

        if ($role === 'provider') {
            $db = Database::getInstance();
            $db->prepare("INSERT INTO tbl_provider_profiles (user_id, business_name) VALUES (?,?)")
               ->execute([$userId, $firstName . ' ' . $lastName]);
        }

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
        header('Location: ' . BASE_URL . 'login'); exit;
    }

    public function showResetForm(): void
    {
        header('Location: ' . BASE_URL . 'login'); exit;
    }

    public function resetPassword(): void
    {
        header('Location: ' . BASE_URL . 'login'); exit;
    }
}