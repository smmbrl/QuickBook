<?php
class ProviderDashController
{
    public function index(): void
    {
        require __DIR__ . '/../views/provider/dashboard.php';
    }

    public function bookings(): void
    {
        require __DIR__ . '/../views/provider/bookings.php';
    }

    public function updateBooking(string $id): void
    {
        $db         = Database::getInstance();
        $providerId = $_SESSION['user_id'] ?? 0;
        $action     = $_POST['action'] ?? '';
        $reason     = trim($_POST['reason'] ?? '');

        // Verify the booking belongs to this provider
        $stmt = $db->prepare("
            SELECT b.id FROM tbl_bookings b
            JOIN tbl_provider_profiles pp ON pp.id = b.provider_id
            WHERE b.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $providerId]);

        if (!$stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Booking not found.'];
            header('Location: ' . BASE_URL . 'provider/bookings');
            exit;
        }

        $statusMap = [
            'confirm'  => 'confirmed',
            'start'    => 'in_progress',
            'complete' => 'completed',
            'reject'   => 'rejected',
            'cancel'   => 'cancelled',
        ];

        if (!isset($statusMap[$action])) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid action.'];
            header('Location: ' . BASE_URL . 'provider/bookings');
            exit;
        }

        $newStatus = $statusMap[$action];

        $upd = $db->prepare("UPDATE tbl_bookings SET status = ?, notes = COALESCE(NULLIF(?, ''), notes) WHERE id = ?");
        $upd->execute([$newStatus, $reason ?: null, $id]);

        $labels = [
            'confirm'  => 'Booking confirmed.',
            'start'    => 'Booking marked as in progress.',
            'complete' => 'Booking marked as completed.',
            'reject'   => 'Booking rejected.',
            'cancel'   => 'Booking cancelled.',
        ];

        $_SESSION['flash'] = ['type' => 'success', 'msg' => $labels[$action]];
        header('Location: ' . BASE_URL . 'provider/bookings');
        exit;
    }

    public function services(): void
    {
        require __DIR__ . '/../views/provider/services.php';
    }

    public function storeService(): void
    {
        // handle new service
    }

    public function updateService(string $id): void
    {
        // handle service update
    }

    public function availability(): void
    {
        require __DIR__ . '/../views/provider/availability.php';
    }

    public function updateAvailability(): void
    {
        // handle availability update
    }

    public function profile(): void
    {
        require __DIR__ . '/../views/provider/profile.php';
    }

    public function updateProfile(): void
    {
        // handle profile update
    }
}