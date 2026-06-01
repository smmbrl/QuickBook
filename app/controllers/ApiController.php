<?php
// app/controllers/ApiController.php

class ApiController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    /**
     * POST api/favorites/toggle
     * Body: { "provider_id": 123 }
     * Returns: { "favorited": true|false, "count": 42 }
     */
    public function toggleFavorite(): void
    {
        header('Content-Type: application/json');

        $db         = Database::getInstance();
        $customerId = (int)$_SESSION['user_id'];

        $raw        = file_get_contents('php://input');
        $body       = json_decode($raw, true);
        $providerId = (int)($body['provider_id'] ?? 0);

        if (!$providerId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid provider_id']);
            exit;
        }

        // Verify provider exists and is approved
        $check = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE id = ? AND is_approved = 1 LIMIT 1");
        $check->execute([$providerId]);
        if (!$check->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['error' => 'Provider not found']);
            exit;
        }

        // Check current favourite status
        $exists = $db->prepare("SELECT id FROM tbl_provider_favorites WHERE customer_id = ? AND provider_id = ?");
        $exists->execute([$customerId, $providerId]);
        $favId = $exists->fetchColumn();

        if ($favId) {
            // Remove favourite
            $db->prepare("DELETE FROM tbl_provider_favorites WHERE id = ?")->execute([$favId]);
            $favorited = false;
        } else {
            // Add favourite
            $db->prepare("
                INSERT INTO tbl_provider_favorites (customer_id, provider_id, created_at)
                VALUES (?, ?, NOW())
            ")->execute([$customerId, $providerId]);
            $favorited = true;
        }

        // Return updated count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM tbl_provider_favorites WHERE provider_id = ?");
        $countStmt->execute([$providerId]);
        $count = (int)$countStmt->fetchColumn();

        echo json_encode(['favorited' => $favorited, 'count' => $count]);
        exit;
    }
}
