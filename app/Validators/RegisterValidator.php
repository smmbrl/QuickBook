<?php
/**
 * QuickBook — Registration Validation (backend)
 * File: app/Validators/RegisterValidator.php
 *
 * Call RegisterValidator::validate($data) from AuthController::register().
 * Returns ['ok' => true] or ['ok' => false, 'errors' => [...]] .
 */

class RegisterValidator
{
    /* ──────────────────────────────────────────────────
       Main entry point
    ────────────────────────────────────────────────── */
    public static function validate(array $data): array
    {
        $errors = [];

        /* ── Basic Info ─────────────────────────────── */
        if (empty(trim($data['first_name'] ?? ''))) {
            $errors[] = 'First name is required.';
        }
        if (empty(trim($data['last_name'] ?? ''))) {
            $errors[] = 'Last name is required.';
        }
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        $phone = preg_replace('/\s+/', '', $data['phone'] ?? '');
        if (strlen($phone) < 7) {
            $errors[] = 'A valid phone number is required.';
        }

        /* ── Home Address ───────────────────────────── */
        if (empty(trim($data['home_address'] ?? ''))) {
            $errors[] = 'Home address is required.';
        }
        if (($data['address_verified'] ?? '0') !== '1') {
            $errors[] = 'Please select a verified home address from the autocomplete list.';
        }
        // Lat/lng sanity check
        $lat = (float)($data['address_lat'] ?? 0);
        $lng = (float)($data['address_lng'] ?? 0);
        if ($lat === 0.0 && $lng === 0.0) {
            $errors[] = 'Address coordinates are missing — please re-select your address.';
        }

        /* ── Personal Details ───────────────────────── */
        if (!empty($data['date_of_birth'])) {
            $dob     = new DateTime($data['date_of_birth']);
            $minDate = new DateTime('-13 years');
            if ($dob > $minDate) {
                $errors[] = 'You must be at least 13 years old to register.';
            }
        }

        /* ── Security ───────────────────────────────── */
        $pw = $data['password'] ?? '';
        if (strlen($pw) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Z]/', $pw)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[0-9]/', $pw)) {
            $errors[] = 'Password must contain at least one number.';
        }

        /* ── Terms ──────────────────────────────────── */
        if (empty($data['terms'])) {
            $errors[] = 'You must accept the Terms of Service and Privacy Policy.';
        }

        /* ── Provider-specific ──────────────────────── */
        $role = $data['role'] ?? 'customer';
        if ($role === 'provider') {
            $errors = array_merge($errors, self::validateProvider($data));
        }

        return $errors ? ['ok' => false, 'errors' => $errors] : ['ok' => true];
    }

    /* ──────────────────────────────────────────────────
       Provider sub-validator
    ────────────────────────────────────────────────── */
    private static function validateProvider(array $data): array
    {
        $errors = [];

        // Business name
        if (empty(trim($data['business_name'] ?? ''))) {
            $errors[] = 'Business name is required for provider accounts.';
        } elseif (strlen(trim($data['business_name'])) < 3) {
            $errors[] = 'Business name must be at least 3 characters.';
        } elseif (strlen(trim($data['business_name'])) > 120) {
            $errors[] = 'Business name must not exceed 120 characters.';
        }

        // Service type
        $allowed = ['home_service', 'business_location', 'flexible'];
        $svcType = $data['service_type'] ?? '';
        if (!in_array($svcType, $allowed, true)) {
            $errors[] = 'Please select a valid service type (Home Service, Business Location, or Flexible).';
        }

        // Business address — required for business_location and flexible
        if (in_array($svcType, ['business_location', 'flexible'], true)) {
            if (empty(trim($data['business_address'] ?? ''))) {
                $errors[] = 'Business address is required for your selected service type.';
            }
            if (($data['business_address_verified'] ?? '0') !== '1') {
                $errors[] = 'Please select a verified business address from the autocomplete list.';
            }
            $bizLat = (float)($data['business_address_lat'] ?? 0);
            $bizLng = (float)($data['business_address_lng'] ?? 0);
            if ($bizLat === 0.0 && $bizLng === 0.0) {
                $errors[] = 'Business address coordinates are missing — please re-select the address.';
            }
        }

        return $errors;
    }

    /* ──────────────────────────────────────────────────
       Sanitize & return a clean data array for DB insert.
       Call after validate() returns ok => true.
    ────────────────────────────────────────────────── */
    public static function sanitize(array $raw): array
    {
        $clean = [
            'first_name'        => trim($raw['first_name']  ?? ''),
            'last_name'         => trim($raw['last_name']   ?? ''),
            'email'             => strtolower(trim($raw['email'] ?? '')),
            'phone'             => preg_replace('/\s+/', '', $raw['phone'] ?? ''),
            'gender'            => $raw['gender']           ?? null,
            'date_of_birth'     => $raw['date_of_birth']    ?? null,
            'role'              => $raw['role'] === 'provider' ? 'provider' : 'customer',
            'home_address'      => trim($raw['home_address'] ?? ''),
            'address_lat'       => (float)($raw['address_lat']  ?? 0),
            'address_lng'       => (float)($raw['address_lng']  ?? 0),
            'address_place_id'  => trim($raw['address_place']   ?? ''),
            'city'              => trim($raw['city']             ?? ''),
            'province'          => trim($raw['province']         ?? ''),
            'password_hash'     => password_hash($raw['password'], PASSWORD_DEFAULT),
        ];

        if ($clean['role'] === 'provider') {
            $svcType = $raw['service_type'] ?? '';
            $clean['business_name']  = trim($raw['business_name']  ?? '');
            $clean['service_type']   = in_array($svcType, ['home_service','business_location','flexible'], true) ? $svcType : null;

            if (in_array($svcType, ['business_location', 'flexible'], true)) {
                $clean['business_address']         = trim($raw['business_address']         ?? '');
                $clean['business_address_lat']     = (float)($raw['business_address_lat']  ?? 0);
                $clean['business_address_lng']     = (float)($raw['business_address_lng']  ?? 0);
                $clean['business_address_place_id']= trim($raw['business_address_place']   ?? '');
            }
        }

        return $clean;
    }
}