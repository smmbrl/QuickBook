<?php
class ProviderDashController
{
    // ─────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────

    public function index(): void
    {
        require __DIR__ . '/../views/Provider/dashboard.php';
    }

    // ─────────────────────────────────────────────────────────
    // Bookings
    // ─────────────────────────────────────────────────────────

    public function bookings(): void
    {
        require __DIR__ . '/../views/Provider/bookings.php';
    }

    public function updateBooking(string $id): void
    {
        $db         = Database::getInstance();
        $providerId = $_SESSION['user_id'] ?? 0;
        $action     = $_POST['action'] ?? '';
        $reason     = trim($_POST['reason'] ?? '');

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
        $upd = $db->prepare("
            UPDATE tbl_bookings
            SET status = ?, notes = COALESCE(NULLIF(?, ''), notes)
            WHERE id = ?
        ");
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

    // ─────────────────────────────────────────────────────────
    // Services
    // ─────────────────────────────────────────────────────────

    public function services(): void
    {
        require __DIR__ . '/../views/Provider/services.php';
    }

    public function storeService(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider profile not found.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        $providerId   = $profile['id'];
        $name         = trim($_POST['name']               ?? '');
        $serviceType  = trim($_POST['service_type']       ?? '');
        $locationType = trim($_POST['location_type']      ?? 'On-site');
        $price        = (float)($_POST['price']           ?? 0);
        $description  = trim($_POST['description']        ?? '');
        $durationRaw  = (int)($_POST['duration_minutes']  ?? 0);
        $durationUnit = $_POST['duration_unit']           ?? 'min';
        $durationMins = ($durationUnit === 'hr') ? $durationRaw * 60 : $durationRaw;

        if ($name === '' || $serviceType === '' || $price <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Name, type, and a valid price are required.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        $ins = $db->prepare("
            INSERT INTO tbl_services
                (provider_id, name, service_type, location_type, price, duration_minutes, description, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $ins->execute([$providerId, $name, $serviceType, $locationType, $price, $durationMins ?: null, $description ?: null]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Service \"{$name}\" added successfully."];
        header('Location: ' . BASE_URL . 'provider/services');
        exit;
    }

    public function updateService(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT s.id FROM tbl_services s
            JOIN tbl_provider_profiles pp ON pp.id = s.provider_id
            WHERE s.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);

        if (!$stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        $name         = trim($_POST['name']               ?? '');
        $serviceType  = trim($_POST['service_type']       ?? '');
        $locationType = trim($_POST['location_type']      ?? 'On-site');
        $price        = (float)($_POST['price']           ?? 0);
        $description  = trim($_POST['description']        ?? '');
        $durationRaw  = (int)($_POST['duration_minutes']  ?? 0);
        $durationUnit = $_POST['duration_unit']           ?? 'min';
        $durationMins = ($durationUnit === 'hr') ? $durationRaw * 60 : $durationRaw;
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $serviceType === '' || $price <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Name, type, and a valid price are required.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        $upd = $db->prepare("
            UPDATE tbl_services
            SET name = ?, service_type = ?, location_type = ?, price = ?,
                duration_minutes = ?, description = ?, is_active = ?
            WHERE id = ?
        ");
        $upd->execute([$name, $serviceType, $locationType, $price, $durationMins ?: null, $description ?: null, $isActive, $id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Service \"{$name}\" updated successfully."];
        header('Location: ' . BASE_URL . 'provider/services');
        exit;
    }

   public function deleteService(string $id): void
{
    $db     = Database::getInstance();
    $userId = $_SESSION['user_id'] ?? 0;

    $stmt = $db->prepare("
        SELECT s.id FROM tbl_services s
        JOIN tbl_provider_profiles pp ON pp.id = s.provider_id
        WHERE s.id = ? AND pp.user_id = ?
    ");
    $stmt->execute([$id, $userId]);

    if (!$stmt->fetch()) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or access denied.'];
        header('Location: ' . BASE_URL . 'provider/services');
        exit;
    }

    $del = $db->prepare("DELETE FROM tbl_services WHERE id = ?");
    $del->execute([$id]);

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Service deleted successfully.'];
    header('Location: ' . BASE_URL . 'provider/services');
    exit;
}

    public function toggleService(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT s.id, s.is_active FROM tbl_services s
            JOIN tbl_provider_profiles pp ON pp.id = s.provider_id
            WHERE s.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);
        $service = $stmt->fetch();

        if (!$service) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Service not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/services');
            exit;
        }

        $newStatus = $service['is_active'] ? 0 : 1;
        $upd = $db->prepare("UPDATE tbl_services SET is_active = ? WHERE id = ?");
        $upd->execute([$newStatus, $id]);

        $label = $newStatus ? 'Service activated.' : 'Service deactivated.';
        $_SESSION['flash'] = ['type' => 'success', 'msg' => $label];
        header('Location: ' . BASE_URL . 'provider/services');
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // Availability
    // ─────────────────────────────────────────────────────────

    public function availability(): void
    {
        require __DIR__ . '/../views/Provider/availability.php';
    }

    public function storeAvailability(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if (!$profile) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Provider profile not found.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        $providerId = $profile['id'];
        $daysInput  = $_POST['days'] ?? [];

        if (empty($daysInput)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'No schedule data submitted.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        // Delete all existing slots and re-insert
        $del = $db->prepare("DELETE FROM tbl_provider_availability WHERE provider_id = ?");
        $del->execute([$providerId]);

        $ins = $db->prepare("
            INSERT INTO tbl_provider_availability (provider_id, day_of_week, start_time, end_time, is_available)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($daysInput as $dayName => $data) {
            $isAvailable = isset($data['is_available']) ? 1 : 0;
            $startTime   = trim($data['start_time'] ?? '08:00');
            $endTime     = trim($data['end_time']   ?? '17:00');
            $ins->execute([$providerId, $dayName, $startTime, $endTime, $isAvailable]);
        }

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Availability saved successfully.'];
        header('Location: ' . BASE_URL . 'provider/availability');
        exit;
    }

    public function updateAvailability(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT a.id FROM tbl_provider_availability a
            JOIN tbl_provider_profiles pp ON pp.id = a.provider_id
            WHERE a.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);

        if (!$stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Availability record not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        $dayOfWeek = trim($_POST['day_of_week'] ?? '');
        $startTime = trim($_POST['start_time']  ?? '');
        $endTime   = trim($_POST['end_time']    ?? '');

        if ($dayOfWeek === '' || $startTime === '' || $endTime === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'All fields are required.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        if ($startTime >= $endTime) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Start time must be before end time.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        $upd = $db->prepare("
            UPDATE tbl_provider_availability
            SET day_of_week = ?, start_time = ?, end_time = ?
            WHERE id = ?
        ");
        $upd->execute([$dayOfWeek, $startTime, $endTime, $id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Availability updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/availability');
        exit;
    }

    public function deleteAvailability(string $id): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $stmt = $db->prepare("
            SELECT a.id FROM tbl_provider_availability a
            JOIN tbl_provider_profiles pp ON pp.id = a.provider_id
            WHERE a.id = ? AND pp.user_id = ?
        ");
        $stmt->execute([$id, $userId]);

        if (!$stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Availability record not found or access denied.'];
            header('Location: ' . BASE_URL . 'provider/availability');
            exit;
        }

        $del = $db->prepare("DELETE FROM tbl_provider_availability WHERE id = ?");
        $del->execute([$id]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Availability slot removed successfully.'];
        header('Location: ' . BASE_URL . 'provider/availability');
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // Profile
    // ─────────────────────────────────────────────────────────

    public function profile(): void
    {
        require __DIR__ . '/../views/Provider/profile.php';
    }

    public function updateProfile(): void
    {
        $db     = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? 0;

        $bio        = trim($_POST['bio']               ?? '');
        $phone      = trim($_POST['phone']             ?? '');
        $address    = trim($_POST['address']           ?? '');
        $experience = (int)($_POST['experience_years'] ?? 0);

        $stmt = $db->prepare("SELECT id FROM tbl_provider_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        if ($profile) {
            $upd = $db->prepare("
                UPDATE tbl_provider_profiles
                SET bio = ?, phone = ?, address = ?, experience_years = ?
                WHERE user_id = ?
            ");
            $upd->execute([$bio ?: null, $phone ?: null, $address ?: null, $experience, $userId]);
        } else {
            $ins = $db->prepare("
                INSERT INTO tbl_provider_profiles (user_id, bio, phone, address, experience_years, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([$userId, $bio ?: null, $phone ?: null, $address ?: null, $experience]);
        }

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
        header('Location: ' . BASE_URL . 'provider/profile');
        exit;
    }
}