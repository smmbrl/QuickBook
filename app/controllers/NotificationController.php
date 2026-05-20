<?php
// app/controllers/NotificationController.php

require_once __DIR__ . '/../../config/database.php';

class NotificationController
{
    private \PDO $db;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->db = Database::getInstance();
    }

    /** POST notifications/mark-read   body: id=<int> */
    public function markRead(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $nid    = (int)($_POST['id']         ?? 0);

        if (!$userId || !$nid) {
            http_response_code(400);
            echo json_encode(['ok' => false]);
            return;
        }

        // Only allow user to mark their own notifications
        $stmt = $this->db->prepare("
            UPDATE tbl_notifications
            SET    is_read = 1
            WHERE  id = ? AND user_id = ?
        ");
        $stmt->execute([$nid, $userId]);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    /** POST notifications/mark-all-read */
    public function markAllRead(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['ok' => false]);
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE tbl_notifications
            SET    is_read = 1
            WHERE  user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'updated' => $stmt->rowCount()]);
    }
}