<?php
require_once __DIR__ . '/../../config/database.php';

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int|false
    {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare(
            "INSERT INTO tbl_users (first_name, last_name, email, phone, password_hash, role)
             VALUES (:first_name, :last_name, :email, :phone, :password_hash, :role)"
        );
        $ok = $stmt->execute([
            ':first_name'    => $data['first_name'],
            ':last_name'     => $data['last_name'],
            ':email'         => $data['email'],
            ':phone'         => $data['phone'] ?? null,
            ':password_hash' => $hash,
            ':role'          => $data['role'] ?? 'customer',
        ]);
        return $ok ? (int) $this->db->lastInsertId() : false;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM tbl_users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM tbl_users WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch();
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tbl_users WHERE email = ?");
        $stmt->execute([strtolower(trim($email))]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function markVerified(int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE tbl_users SET is_verified = 1, is_active = 1 WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public function setRememberToken(int $userId, string $token): bool
    {
        $stmt = $this->db->prepare("UPDATE tbl_users SET remember_token = ? WHERE id = ?");
        return $stmt->execute([hash('sha256', $token), $userId]);
    }

    public function clearRememberToken(int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE tbl_users SET remember_token = NULL WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public function updatePassword(int $userId, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare("UPDATE tbl_users SET password_hash = ? WHERE id = ?");
        return $stmt->execute([$hash, $userId]);
    }

    public function createVerificationToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $exp   = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $this->db->prepare("DELETE FROM tbl_email_verifications WHERE user_id = ? AND used_at IS NULL")
                 ->execute([$userId]);
        $this->db->prepare("INSERT INTO tbl_email_verifications (user_id, token, expires_at) VALUES (?,?,?)")
                 ->execute([$userId, $token, $exp]);
        return $token;
    }

    public function validateVerificationToken(string $token): int|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tbl_email_verifications
             WHERE token = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) return false;
        $this->db->prepare("UPDATE tbl_email_verifications SET used_at = NOW() WHERE id = ?")
                 ->execute([$row['id']]);
        return (int) $row['user_id'];
    }

    public function createPasswordResetToken(string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $exp   = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->db->prepare("DELETE FROM tbl_password_resets WHERE email = ? AND used_at IS NULL")
                 ->execute([$email]);
        $this->db->prepare("INSERT INTO tbl_password_resets (email, token, expires_at) VALUES (?,?,?)")
                 ->execute([$email, $token, $exp]);
        return $token;
    }

    public function validatePasswordResetToken(string $token): string|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tbl_password_resets
             WHERE token = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ? $row['email'] : false;
    }

    public function consumePasswordResetToken(string $token): bool
    {
        $stmt = $this->db->prepare("UPDATE tbl_password_resets SET used_at = NOW() WHERE token = ?");
        return $stmt->execute([$token]);
    }
}