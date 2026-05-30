<?php
// =====================================================
//  home.php — QuickBook Home Page (OOP)
//  Project: QuickBook > app/views/home.php
// =====================================================

defined('BASE_URL') || define('BASE_URL', '/');

class NavLink {
    public string $label;
    public string $href;
    public bool   $active;

    public function __construct(string $label, string $href, bool $active = false) {
        $this->label  = $label;
        $this->href   = $href;
        $this->active = $active;
    }

    public function render(): string {
        $activeClass = $this->active ? ' class="active"' : '';
        return '<li><a href="' . htmlspecialchars($this->href) . '"' . $activeClass . '>'
             . htmlspecialchars($this->label) . '</a></li>';
    }
}

class CategoryCard {
    public string $name;
    public string $imageUrl;
    public string $imageAlt;
    public int    $providerCount;

    public function __construct(string $name, string $imageUrl, string $imageAlt, int $providerCount) {
        $this->name          = $name;
        $this->imageUrl      = $imageUrl;
        $this->imageAlt      = $imageAlt;
        $this->providerCount = $providerCount;
    }

    public function render(): string {
        return '
        <div class="cat-card" onclick="window.location.href=\'' . BASE_URL . 'browse?cat=' . urlencode(strtolower($this->name)) . '\'">
            <div class="cat-icon">
                <img src="' . htmlspecialchars($this->imageUrl) . '"
                     alt="' . htmlspecialchars($this->imageAlt) . '" loading="lazy">
            </div>
            <div class="cat-name">' . htmlspecialchars($this->name) . '</div>
        </div>';
    }
}

class ProviderCard {
    public string $name;
    public string $category;
    public string $location;
    public string $imageUrl;
    public string $badge;
    public float  $rating;
    public int    $reviewCount;
    public int    $serviceCount;

    public function __construct(
        string $name, string $category, string $location,
        string $imageUrl, string $badge,
        float $rating, int $reviewCount, int $serviceCount
    ) {
        $this->name          = $name;
        $this->category      = $category;
        $this->location      = $location;
        $this->imageUrl      = $imageUrl;
        $this->badge         = $badge;
        $this->rating        = $rating;
        $this->reviewCount   = $reviewCount;
        $this->serviceCount  = $serviceCount;
    }

    private function getBadgeHtml(): string {
        if ($this->badge === 'available') {
            return '<span class="pcard-badge badge-avail">Available Now</span>';
        }
        return '<span class="pcard-badge badge-home">Home Service</span>';
    }

    public function render(): string {
        $displayName = mb_convert_case(htmlspecialchars($this->name), MB_CASE_TITLE, 'UTF-8');
        $cat = $this->category && strtolower($this->category) !== 'service'
            ? htmlspecialchars($this->category)
            : '&#8212;';
        $ratingHtml = $this->reviewCount > 0
            ? '<span class="pcard-star">&#9733;</span> '
              . number_format($this->rating, 1)
              . '<span class="rc">(' . $this->reviewCount . ' reviews)</span>'
            : '<span class="rc pcard-noreviews">No reviews yet</span>';
        $svcLabel = $this->serviceCount . ' ' . ($this->serviceCount === 1 ? 'Service' : 'Services');

        return '
        <div class="pcard" onclick="openBookingModal(' . json_encode($this->name) . ', ' . json_encode($this->category) . ', \'\')">
            <div class="pcard-thumb">
                <img src="' . htmlspecialchars($this->imageUrl) . '"
                     alt="' . $displayName . '" loading="lazy">
                ' . $this->getBadgeHtml() . '
            </div>
            <div class="pcard-body">
                <div class="pcard-cat">' . $cat . '</div>
                <div class="pcard-name">' . $displayName . '</div>
                <div class="pcard-loc">
                    <svg class="loc-pin" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    ' . htmlspecialchars($this->location) . '
                </div>
                <div class="pcard-footer">
                    <div class="pcard-rating">' . $ratingHtml . '</div>
                    <div class="pcard-price">' . $svcLabel . '</div>
                </div>
            </div>
        </div>';
    }
}

class HowItWorksStep {
    public int    $number;
    public string $title;
    public string $description;

    public function __construct(int $number, string $title, string $description) {
        $this->number      = $number;
        $this->title       = $title;
        $this->description = $description;
    }

    public function render(): string {
        return '
        <div class="step">
            <div class="step-num">' . $this->number . '</div>
            <div class="step-title">' . htmlspecialchars($this->title) . '</div>
            <div class="step-desc">'  . htmlspecialchars($this->description) . '</div>
        </div>';
    }
}

class TestimonialCard {
    public string $text;
    public string $name;
    public string $role;
    public string $avatarUrl;
    public int    $stars;

    public function __construct(string $text, string $name, string $role, string $avatarUrl, int $stars = 5) {
        $this->text      = $text;
        $this->name      = $name;
        $this->role      = $role;
        $this->avatarUrl = $avatarUrl;
        $this->stars     = $stars;
    }

    public function render(): string {
        $starHtml = str_repeat('&#9733;', $this->stars);
        return '
        <div class="tcard">
            <div class="tcard-stars">' . $starHtml . '</div>
            <div class="tcard-text">&ldquo;' . htmlspecialchars($this->text) . '&rdquo;</div>
            <div class="tcard-author">
                <div class="tcard-avatar">
                    <img src="' . htmlspecialchars($this->avatarUrl) . '" alt="' . htmlspecialchars($this->name) . '" loading="lazy">
                </div>
                <div>
                    <div class="tcard-name">' . htmlspecialchars($this->name) . '</div>
                    <div class="tcard-role">' . htmlspecialchars($this->role) . '</div>
                </div>
            </div>
        </div>';
    }
}

class FaqItem {
    public string $question;
    public string $answer;

    public function __construct(string $question, string $answer) {
        $this->question = $question;
        $this->answer   = $answer;
    }

    public function render(): string {
        return '
        <div class="faq-item">
            <button class="faq-question" onclick="toggleFaq(this)">
                ' . htmlspecialchars($this->question) . '
                <span class="faq-icon">+</span>
            </button>
            <div class="faq-answer">
                <div class="faq-answer-inner">' . htmlspecialchars($this->answer) . '</div>
            </div>
        </div>';
    }
}

class StatItem {
    public string $number;
    public string $label;
    public string $icon;
    public float  $rawValue;
    public string $suffix;
    public bool   $isDecimal;

    public function __construct(string $number, string $label, string $icon = '', float $rawValue = 0, string $suffix = '', bool $isDecimal = false) {
        $this->number    = $number;
        $this->label     = $label;
        $this->icon      = $icon;
        $this->rawValue  = $rawValue;
        $this->suffix    = $suffix;
        $this->isDecimal = $isDecimal;
    }

    public function render(): string {
        $dec = $this->isDecimal ? 'true' : 'false';
        return '
        <div class="stat-item"
             data-target="' . $this->rawValue . '"
             data-suffix="' . htmlspecialchars($this->suffix) . '"
             data-decimal="' . $dec . '">
            <div class="stat-icon">' . $this->icon . '</div>
            <div class="stat-num" data-count>' . htmlspecialchars($this->number) . '</div>
            <div class="stat-label">' . htmlspecialchars($this->label) . '</div>
        </div>';
    }
}

class HomePage {

    private string $pageTitle;
    private string $cssPath;

    /** @var NavLink[]          */ private array $navLinks;
    /** @var CategoryCard[]     */ private array $categories;
    /** @var ProviderCard[]     */ private array $providers;
    /** @var HowItWorksStep[]   */ private array $steps;
    /** @var TestimonialCard[]  */ private array $testimonials;
    /** @var FaqItem[]          */ private array $faqs;
    /** @var StatItem[]         */ private array $stats;
    /** @var string[]           */ private array $searchCategories;
    /** @var string[]           */ private array $trustItems;

    public function __construct() {
        $this->cssPath   = BASE_URL . 'assets/css/home.css';
        $this->pageTitle = 'QuickBook — Smart Local Booking';
        $this->initNavLinks();
        $this->initSearchCategories();
        $this->initTrustItems();
        $this->initCategories();
        $this->initProviders();
        $this->initSteps();
        $this->initTestimonials();
        $this->initFaqs();
        $this->initStats();
    }

    private function initNavLinks(): void {
        $this->navLinks = [
            new NavLink('Home',            BASE_URL,        true),
            new NavLink('Browse Services', '#categories'),
            new NavLink('How It Works',    '#how'),
            new NavLink('For Providers',   '#cta'),
        ];
    }

    private function initSearchCategories(): void {
        $this->searchCategories = [
            'All Categories', 'Barbershop', 'Hair Salon',
            'Nail Care', 'Massage Therapy', 'Skincare Facial',
            'Fitness Training', 'Cleaning Services', 'Pet Grooming', 'Dental Services', 'Makeup Artist',
        ];
    }

    private function initTrustItems(): void {
        $this->trustItems = [
            'Verified Providers',
            'Secure Payments',
            'Verified Ratings',
            'Loyalty Rewards',
        ];
    }

    private function initCategories(): void {
        $img = BASE_URL . 'assets/img/';
        $this->categories = [
            new CategoryCard('Barbershop',          $img.'barbershop.png',   'Barber',          48),
            new CategoryCard('Hair Salon',    $img.'hairsalon.png',    'Hair Stylist',    62),
            new CategoryCard('Nail Care',       $img.'nailtech.png',     'Nail Tech',       35),
            new CategoryCard('Massage Therapy',         $img.'massage.png',      'Massage',         29),
            new CategoryCard('Skincare Facial',        $img.'facial.png',       'Skincare',        22),
            new CategoryCard('Fitness Training',         $img.'fitness.png',      'Fitness',         17),
            new CategoryCard('Cleaning Services',   $img.'cleaning.png',     'Home Cleaning',   41),
            new CategoryCard('Pet Grooming',     $img.'petgrooming.png',  'Pet Groomer',     14),
            new CategoryCard('Dental Services', $img.'dental.png',       'Dental Services', 19),
            new CategoryCard('Makeup Artist',          $img.'makeup.png',       'Makeup',          11),
        ];
    }

    private function initProviders(): void {
        $this->providers = [];

        try {
            $db   = Database::getInstance();
            $rows = $db->query("
                SELECT
                    pp.id            AS profile_id,
                    pp.business_name,
                    pp.avg_rating,
                    pp.total_reviews,
                    pp.barangay,
                    pp.city,
                    pp.profile_photo,
                    u.avatar_url,
                    c.name           AS category_name,
                    COUNT(s.id)      AS service_count,
                    MAX(CASE WHEN s.location_type = 'Flexible' THEN 1 ELSE 0 END) AS has_home
                FROM tbl_provider_profiles pp
                JOIN tbl_users u     ON u.id   = pp.user_id
                JOIN tbl_services s  ON s.provider_id = pp.id AND s.is_active = 1
                LEFT JOIN tbl_categories c ON c.id = pp.category_id
                WHERE pp.is_approved = 1
                GROUP BY pp.id
                ORDER BY pp.avg_rating DESC, pp.total_reviews DESC
                LIMIT 3
            ")->fetchAll();

            foreach ($rows as $r) {
                $name     = $r['business_name'] ?: 'Unnamed Provider';
                $category = !empty($r['category_name']) ? $r['category_name'] : '';
                $loc      = trim(($r['barangay'] ? $r['barangay'] . ', ' : '') . ($r['city'] ?: 'Bacolod City'));
                $rating   = (float)  ($r['avg_rating']   ?? 0);
                $reviews  = (int)    ($r['total_reviews'] ?? 0);
                $serviceCount = (int) ($r['service_count'] ?? 0);
                $badge    = $r['has_home'] ? 'home' : 'available';
                $img = !empty($r['profile_photo'])
                    ? $r['profile_photo']
                    : (!empty($r['avatar_url'])
                        ? $r['avatar_url']
                        : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&q=75&auto=format&fit=crop');

                $this->providers[] = new ProviderCard(
                    $name, $category, $loc, $img, $badge, $rating, $reviews, $serviceCount
                );
            }
        } catch (Exception $e) {
            /* DB unavailable — show nothing */
        }

        if (empty($this->providers)) {
            $base = 'https://images.unsplash.com/';
            $this->providers = [
                new ProviderCard("Raffy's Barbershop", 'Barber',
                    'Sum-ag, Bacolod City',
                    $base.'photo-1503951914875-452162b0f3f1?w=600&q=75&auto=format&fit=crop',
                    'available', 4.9, 320, 5),
                new ProviderCard('Wellness by Marga', 'Massage Therapy',
                    'Mandalagan, Bacolod',
                    $base.'photo-1544161515-4ab6ce6db874?w=600&q=75&auto=format&fit=crop',
                    'home', 4.8, 215, 8),
                new ProviderCard('Aling Nena Nails', 'Nail Technician',
                    'Libertad, Bacolod',
                    $base.'photo-1604654894610-df63bc536371?w=600&q=75&auto=format&fit=crop',
                    'available', 4.7, 189, 3),
            ];
        }
    }

    private function initSteps(): void {
        $this->steps = [
            new HowItWorksStep(1, 'Search & Browse',
                'Find providers by service, location, or rating. Filter by barangay or city.'),
            new HowItWorksStep(2, 'Pick a Time Slot',
                'View real-time availability. Choose a slot that fits your schedule.'),
            new HowItWorksStep(3, 'Book & Pay Securely',
                'Confirm your booking and pay via GCash, PayMaya, or cash.'),
            new HowItWorksStep(4, 'Enjoy & Earn Rewards',
                'Get your service done and earn loyalty points for every booking.'),
        ];
    }

    private function initTestimonials(): void {
        $base = 'https://images.unsplash.com/';
        $this->testimonials = [
            new TestimonialCard(
                'QuickBook made it so easy to find a barber near my barangay. Booked in less than a minute and got a confirmation right away!',
                'Juan dela Cruz', 'Regular Customer · Mandalagan',
                $base.'photo-1507003211169-0a1dd7228f2d?w=80&q=75&auto=format&fit=crop&facepad=2&faces=1',
                5
            ),
            new TestimonialCard(
                'As a nail tech, QuickBook helped me get 3x more clients. The scheduling system is super smooth and my customers love it.',
                'Maria Santos', 'Nail Technician Provider',
                $base.'photo-1494790108377-be9c29b29330?w=80&q=75&auto=format&fit=crop&facepad=2&faces=1',
                5
            ),
            new TestimonialCard(
                'I love that I can see real reviews before booking. Found an amazing massage therapist who does home service. 10/10!',
                'Carla Reyes', 'Verified Customer · Libertad',
                $base.'photo-1438761681033-6461ffad8d80?w=80&q=75&auto=format&fit=crop&facepad=2&faces=1',
                5
            ),
        ];
    }

    private function initFaqs(): void {
        $this->faqs = [
            new FaqItem(
                'How do I book a service on QuickBook?',
                'Simply search for the service you need, browse available providers near you, pick a time slot that fits your schedule, and confirm your booking. You\'ll receive an instant confirmation via SMS and email.'
            ),
            new FaqItem(
                'What payment methods are accepted?',
                'QuickBook supports GCash, PayMaya, credit/debit cards, and cash on service. All digital payments are processed securely through our encrypted payment gateway.'
            ),
            new FaqItem(
                'Can I cancel or reschedule my booking?',
                'Yes! You can cancel or reschedule up to 2 hours before your appointment at no charge. Late cancellations may be subject to a small fee depending on the provider\'s policy.'
            ),
            new FaqItem(
                'Are all providers verified?',
                'Every provider on QuickBook goes through an ID verification and background check process before being listed. We also continuously monitor ratings and reviews to ensure quality service.'
            ),
            new FaqItem(
                'How do I register as a service provider?',
                'Click "List Your Services" or "Get Started Free" on this page, fill out your provider profile, upload your valid ID, and our team will review and approve your account within 24-48 hours.'
            ),
        ];
    }

    private function initStats(): void {
        $iconCalendar = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
        $iconUsers    = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
        $iconStar     = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        $iconMap      = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';

        $bookingsThisMonth = 0;
        $activeProviders   = 0;
        $avgRating         = 0.0;
        $barangaysCovered  = 0;

        try {
            $db = Database::getInstance();

            $bookingsThisMonth = (int) $db->query(
                "SELECT COUNT(*) FROM tbl_bookings
                 WHERE MONTH(created_at) = MONTH(NOW())
                   AND YEAR(created_at)  = YEAR(NOW())
                   AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')"
            )->fetchColumn();

            $activeProviders = (int) $db->query(
                "SELECT COUNT(*) FROM tbl_provider_profiles WHERE is_approved = 1"
            )->fetchColumn();

            $avgRating = (float) $db->query(
                "SELECT COALESCE(ROUND(AVG(avg_rating), 1), 0)
                 FROM tbl_provider_profiles
                 WHERE is_approved = 1 AND avg_rating > 0"
            )->fetchColumn();

            $barangaysCovered = (int) $db->query(
                "SELECT COUNT(DISTINCT barangay)
                 FROM tbl_provider_profiles
                 WHERE is_approved = 1
                   AND barangay IS NOT NULL AND barangay != ''"
            )->fetchColumn();
        } catch (Exception $e) {
            /* DB unavailable — keep zeros */
        }

        $bookingsLabel  = $bookingsThisMonth > 0 ? number_format($bookingsThisMonth) . '+' : '0';
        $providersLabel = $activeProviders   > 0 ? $activeProviders . '+'               : '0';
        $ratingLabel    = $avgRating         > 0 ? number_format($avgRating, 1)         : '0.0';
        $barangaysLabel = $barangaysCovered  > 0 ? (string) $barangaysCovered           : '0';

        $this->stats = [
            new StatItem($bookingsLabel,  'Bookings This Month', $iconCalendar, (float)$bookingsThisMonth, '+',  false),
            new StatItem($providersLabel, 'Active Providers',    $iconUsers,    (float)$activeProviders,   '+',  false),
            new StatItem($ratingLabel,    'Average Rating',      $iconStar,     $avgRating,                '',   true),
            new StatItem($barangaysLabel, 'Barangays Covered',   $iconMap,      (float)$barangaysCovered,  '',   false),
        ];
    }

    private function renderNavLinks(): string {
        return implode("\n      ", array_map(fn($l) => $l->render(), $this->navLinks));
    }

    private function renderSearchOptions(): string {
        return implode("\n          ", array_map(
            fn($cat) => '<option>' . htmlspecialchars($cat) . '</option>',
            $this->searchCategories
        ));
    }

    private function renderTrustItems(): string {
        return implode("\n        ", array_map(
            fn($item) => '<span class="trust-item"><span class="ti-dot"></span> ' . htmlspecialchars($item) . '</span>',
            $this->trustItems
        ));
    }

    private function renderCategories(): string {
        return implode("\n      ", array_map(fn($c) => $c->render(), $this->categories));
    }

    private function renderProviders(): string {
        return implode("\n      ", array_map(fn($p) => $p->render(), $this->providers));
    }

    private function renderSteps(): string {
        return implode("\n      ", array_map(fn($s) => $s->render(), $this->steps));
    }

    private function renderTestimonials(): string {
        return implode("\n      ", array_map(fn($t) => $t->render(), $this->testimonials));
    }

    private function renderFaqs(): string {
        return implode("\n      ", array_map(fn($f) => $f->render(), $this->faqs));
    }

    private function renderStats(): string {
        return implode("\n      ", array_map(fn($s) => $s->render(), $this->stats));
    }

    public function render(): void {
        $title        = htmlspecialchars($this->pageTitle);
        $css          = htmlspecialchars($this->cssPath);
        $navLinks     = $this->renderNavLinks();
        $searchOpts   = $this->renderSearchOptions();
        $trustItems   = $this->renderTrustItems();
        $categories   = $this->renderCategories();
        $providers    = $this->renderProviders();
        $steps        = $this->renderSteps();
        $testimonials = $this->renderTestimonials();
        $faqs         = $this->renderFaqs();
        $stats        = $this->renderStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="QuickBook — Smart local booking platform. Find and book trusted barbers, nail techs, massage therapists, and more in Bacolod City.">
  <title><?= $title ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $css ?>">

  <!-- Apply saved theme BEFORE render to prevent flash -->
  <script>
    (function(){
      var t = localStorage.getItem('qb-theme') || 'dark';
      if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    })();
  </script>
</head>
<body>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- BACK TO TOP -->
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Back to top">&#8593;</button>

<!-- NAVBAR -->
<nav class="navbar" id="mainNavbar">
  <div class="navbar-inner">
 
    <a href="<?= BASE_URL ?>" class="navbar-logo">
      <img src="<?= BASE_URL ?>assets/img/QB_LOGO.png"
           alt="QuickBook Logo"
           class="navbar-logo-img">
      Quick<span>Book</span>
    </a>
 
    <!-- Desktop nav links -->
    <ul class="navbar-links">
      <?= $navLinks ?>
    </ul>
 
    <div class="navbar-actions">
      <!-- THEME TOGGLE -->
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark/light mode" title="Toggle theme">
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
        <svg class="icon-sun" style="display:none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"/>
          <line x1="12" y1="1"  x2="12" y2="3"/>
          <line x1="12" y1="21" x2="12" y2="23"/>
          <line x1="4.22"  y1="4.22"  x2="5.64"  y2="5.64"/>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
          <line x1="1"  y1="12" x2="3"  y2="12"/>
          <line x1="21" y1="12" x2="23" y2="12"/>
          <line x1="4.22"  y1="19.78" x2="5.64"  y2="18.36"/>
          <line x1="18.36" y1="5.64"  x2="19.78" y2="4.22"/>
        </svg>
      </button>
 
      <!-- Desktop login/signup -->
      <a href="<?= BASE_URL ?>login"    class="btn btn-ghost btn-sm">Log In</a>
      <a href="<?= BASE_URL ?>register" class="btn btn-gold btn-sm">Sign Up</a>
 
      <!-- Mobile hamburger -->
      <button class="navbar-hamburger" id="hamburgerBtn" aria-label="Open navigation menu" aria-expanded="false" aria-controls="mobileNavDrawer">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>
 
  </div>
</nav>

<!-- MOBILE NAV DRAWER (hidden by default, toggled by hamburger) -->
<nav class="mobile-nav-drawer" id="mobileNavDrawer" aria-label="Mobile navigation">
  <a href="<?= BASE_URL ?>"           class="active">Home</a>
  <a href="#categories">Browse Services</a>
  <a href="#how">How It Works</a>
  <a href="#cta">For Providers</a>
  <a href="#faq">FAQ</a>
  <div class="mobile-nav-actions">
    <a href="<?= BASE_URL ?>login"    class="btn btn-ghost btn-sm">Log In</a>
    <a href="<?= BASE_URL ?>register" class="btn btn-gold btn-sm">Sign Up &rarr;</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="home">

  <!-- SLIDE BACKGROUNDS -->
  <div class="hero-slides">
    <div class="hero-slide active"  data-label="Massage Therapy"            style="background-image:url('<?= BASE_URL ?>assets/img/massage.png')"></div>
    <div class="hero-slide"         data-label="Barbershop &amp; Hair Cut"  style="background-image:url('<?= BASE_URL ?>assets/img/barbershop.png')"></div>
    <div class="hero-slide"         data-label="Nail Care"            style="background-image:url('<?= BASE_URL ?>assets/img/nailtech.png')"></div>
    <div class="hero-slide"         data-label="Hair Salon"                 style="background-image:url('<?= BASE_URL ?>assets/img/hairsalon.png')"></div>
    <div class="hero-slide"         data-label="Skincare &amp; Facial"     style="background-image:url('<?= BASE_URL ?>assets/img/facial.png')"></div>
    <div class="hero-slide"         data-label="Pet Grooming"               style="background-image:url('<?= BASE_URL ?>assets/img/petgrooming.png')"></div>
    <div class="hero-slide"         data-label="Fitness &amp; Training"          style="background-image:url('<?= BASE_URL ?>assets/img/fitness.png')"></div>
    <div class="hero-slide"         data-label="Cleaning Services"          style="background-image:url('<?= BASE_URL ?>assets/img/cleaning.png')"></div>
    <div class="hero-slide"         data-label="Dental Services"            style="background-image:url('<?= BASE_URL ?>assets/img/dental.png')"></div>
    <div class="hero-slide"         data-label="Makeup Artist"              style="background-image:url('<?= BASE_URL ?>assets/img/makeup.png')"></div>
  </div>

  <!-- Overlay -->
  <div class="hero-overlay"></div>
  <div class="hero-grain"></div>
  <div class="hero-rule"></div>

  <!-- CENTERED CONTENT -->
  <div class="hero-center">

    <div class="hero-eyebrow">
      <span class="eyebrow-dot"></span>
      Now live in Bacolod City &amp; surrounding barangays
    </div>

    <h1>Book local services<br><span class="accent">instantly,</span> effortlessly.</h1>

    <p class="hero-desc">
      QuickBook connects you with trusted community providers with
      real-time scheduling and instant confirmations.
    </p>

    <div class="hero-search">
      <input type="text" id="heroSearch" placeholder="What service are you looking for?" autocomplete="off">
      <div class="hs-div"></div>
      <select id="heroCategory">
        <?= $searchOpts ?>
      </select>
      <a href="<?= BASE_URL ?>browse" class="hs-btn" id="heroSearchBtn">Search &rarr;</a>
    </div>

    <div class="trust-row">
      <?= $trustItems ?>
    </div>

  </div>

  <!-- SLIDE DOTS (10) -->
  <div class="hero-dots" id="heroDots">
    <button class="hero-dot active" data-index="0"></button>
    <button class="hero-dot" data-index="1"></button>
    <button class="hero-dot" data-index="2"></button>
    <button class="hero-dot" data-index="3"></button>
    <button class="hero-dot" data-index="4"></button>
    <button class="hero-dot" data-index="5"></button>
    <button class="hero-dot" data-index="6"></button>
    <button class="hero-dot" data-index="7"></button>
    <button class="hero-dot" data-index="8"></button>
    <button class="hero-dot" data-index="9"></button>
  </div>

</section>

<!-- STATS BAND -->
<div class="stats-band">
  <div class="stats-inner">
    <?= $stats ?>
  </div>
</div>

<!-- BROWSE BY CATEGORY -->
<section class="section cat-section" id="categories">
  <div class="section-inner">
    <div class="section-header">
      <div class="eyebrow-tag">Explore</div>
      <h2>Browse by Category</h2>
      <p>Find the service you need from our growing community of verified local experts</p>
    </div>
    <div class="cat-grid">
      <?= $categories ?>
    </div>
  </div>
</section>

<!-- TOP-RATED PROVIDERS -->
<section class="section providers-section" id="providers">
  <div class="section-inner">
    <div class="section-header">
      <div class="eyebrow-tag">Top Rated</div>
      <h2>Top-Rated Providers Near You</h2>
      <p>Handpicked local experts with verified reviews and consistent 5-star service in Bacolod City</p>
    </div>
    <div class="provider-grid">
      <?= $providers ?>
    </div>
    <div style="text-align:center;margin-top:2.5rem">
      <a href="<?= BASE_URL ?>browse" class="btn btn-ghost btn-lg">View All Providers &rarr;</a>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="section how-section" id="how">
  <div class="section-inner">
    <div class="section-header">
      <div class="eyebrow-tag">Process</div>
      <h2>How QuickBook Works</h2>
      <p>Book your next appointment in under 2 minutes &mdash; from search to confirmation</p>
    </div>
    <div class="steps-container">
      <?= $steps ?>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="section testimonials-section" id="reviews">
  <div class="section-inner">
    <div class="section-header">
      <div class="eyebrow-tag">Reviews</div>
      <h2>What Our Community Says</h2>
      <p>Real reviews from real customers and providers in Bacolod City</p>
    </div>
    <div class="testimonials-grid">
      <?= $testimonials ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section faq-section" id="faq">
  <div class="section-inner">
    <div class="section-header">
      <div class="eyebrow-tag">FAQ</div>
      <h2>Frequently Asked Questions</h2>
      <p>Everything you need to know about QuickBook</p>
    </div>
    <div class="faq-list">
      <?= $faqs ?>
    </div>
  </div>
</section>

<!-- CTA BANNER -->
<section class="cta-banner" id="cta">
  <div class="cta-inner">
    <div class="eyebrow-tag" style="margin-bottom:1.2rem">For Providers</div>
    <h2>Ready to grow your business <em>digitally?</em></h2>
    <p>Join hundreds of local providers already using QuickBook to reach more customers in Bacolod.</p>
    <div class="cta-actions">
      <a href="<?= BASE_URL ?>register" class="btn btn-gold btn-lg">Get Started Free &rarr;</a>
      <a href="<?= BASE_URL ?>register" class="btn btn-ghost btn-lg">List Your Services</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <a href="<?= BASE_URL ?>" class="footer-logo">Quick<span>Book</span></a>
      <p class="footer-tagline">Smart local booking for Bacolod City &amp; surrounding barangays. Fast, trusted, community-driven.</p>
      <div class="footer-socials">

        <!-- Facebook -->
        <a href="#" class="social-btn" aria-label="Facebook">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
          </svg>
        </a>

        <!-- Instagram -->
        <a href="#" class="social-btn" aria-label="Instagram">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
          </svg>
        </a>

        <!-- Twitter / X -->
        <a href="#" class="social-btn" aria-label="Twitter / X">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
          </svg>
        </a>

      </div>
    </div>

    <!-- SERVICES — all 10 categories from the page -->
    <div class="footer-col">
      <h4>Services</h4>
      <ul>
        <li><a href="<?= BASE_URL ?>browse?cat=barber">Barbershop</a></li>
        <li><a href="<?= BASE_URL ?>browse?cat=hair+stylist">Hair Salon</a></li>
        <li><a href="<?= BASE_URL ?>browse?cat=nail+tech">Nail Care</a></li>
        <li><a href="<?= BASE_URL ?>browse?cat=massage">Massage Therapy</a></li>
        <li><a href="<?= BASE_URL ?>browse?cat=skincare">Skincare Facial</a></li>
        <li><a href="<?= BASE_URL ?>browse?cat=fitness">Fitness Training</a></li>
        <li><a href="<?= BASE_URL ?>browse?cat=home+cleaning">Cleaning Services</a></li>
        <li><a href="<?= BASE_URL ?>browse?cat=pet+groomer">Pet Grooming</a></li>
        <li><a href="<?= BASE_URL ?>browse?cat=dental+services">Dental Services</a></li>
        <li><a href="<?= BASE_URL ?>browse?cat=makeup">Makeup Artist</a></li>
      </ul>
    </div>

    <!-- COMPANY — links grounded in actual page sections -->
    <div class="footer-col">
      <h4>Company</h4>
      <ul>
        <li><a href="#">About Us</a></li>
        <li><a href="#">Blog</a></li>
        <li><a href="#providers">Top Providers</a></li>
        <li><a href="#cta">List Your Services</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </div>

    <!-- SUPPORT — links grounded in actual FAQ content -->
    <div class="footer-col">
      <h4>Support</h4>
      <ul>
        <li><a href="#faq">FAQ</a></li>
        <li><a href="#">Help Center</a></li>
        <li><a href="#">Cancel / Reschedule</a></li>
        <li><a href="#">Payment Methods</a></li>
        <li><a href="#">Report Issue</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <span>&copy; <?= date('Y') ?> <strong>QuickBook</strong> &mdash; Smart Local Booking &middot; Bacolod City, Philippines</span>
    <div class="footer-bottom-links">
      <a href="#">Privacy Policy</a>
      <a href="#">Terms of Service</a>
      <a href="#">Cookie Policy</a>
    </div>
  </div>
</footer>



<!-- BOOKING MODAL -->
<div class="modal-overlay hidden" id="bookingModal">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="modalProviderName">Book Appointment</div>
        <div class="modal-subtitle" id="modalProviderCat"></div>
      </div>
      <button class="modal-close" onclick="closeBookingModal()" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Your Name</label>
          <input type="text" class="form-input" id="bookName" placeholder="Full name">
        </div>
        <div class="form-group">
          <label class="form-label">Phone / GCash</label>
          <input type="tel" class="form-input" id="bookPhone" placeholder="09xx xxx xxxx">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Select Date</label>
        <input type="date" class="form-input" id="bookDate">
      </div>

      <div class="form-group">
        <label class="form-label">Available Time Slots</label>
        <div class="slots-grid" id="slotsGrid"></div>
      </div>

      <div class="form-group">
        <label class="form-label">Payment Method</label>
        <select class="form-select" id="bookPayment">
          <option value="">Choose payment</option>
          <option value="gcash">GCash</option>
          <option value="paymaya">PayMaya</option>
          <option value="card">Credit / Debit Card</option>
          <option value="cash">Cash on Service</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Notes (optional)</label>
        <textarea class="form-textarea" id="bookNotes" placeholder="Any special requests or instructions..." style="min-height:70px;"></textarea>
      </div>

      <hr class="modal-divider">

      <div class="booking-summary">
        <div class="summary-row">
          <span class="s-label">Provider</span>
          <span class="s-value" id="sumProvider">—</span>
        </div>
        <div class="summary-row">
          <span class="s-label">Date &amp; Time</span>
          <span class="s-value" id="sumDateTime">—</span>
        </div>
        <div class="summary-row">
          <span class="s-label">Payment</span>
          <span class="s-value" id="sumPayment">—</span>
        </div>
        <hr class="summary-divider">
      </div>

      <div style="margin-top:1.3rem">
        <button class="btn btn-gold btn-lg" style="width:100%;justify-content:center;" onclick="submitBooking()">
          Confirm Booking &rarr;
        </button>
      </div>

    </div>
  </div>
</div>

<!-- SCRIPTS -->
<script>
(function () {
  /* ── THEME TOGGLE ── */
  var btn  = document.getElementById('themeToggle');
  var moon = document.querySelector('.icon-moon');
  var sun  = document.querySelector('.icon-sun');

  function applyTheme(theme) {
    if (theme === 'light') {
      document.documentElement.removeAttribute('data-theme');
      if (moon) moon.style.display = 'none';
      if (sun)  sun.style.display  = 'block';
      btn.style.color = '#A16B0F';
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      if (moon) moon.style.display = 'block';
      if (sun)  sun.style.display  = 'none';
      btn.style.color = '#C9A84C';
    }
  }

  var saved = localStorage.getItem('qb-theme') || 'dark';
  applyTheme(saved);

  btn.addEventListener('click', function () {
    var current = document.documentElement.getAttribute('data-theme');
    var next = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem('qb-theme', next);
    applyTheme(next);
  });

/* ── HAMBURGER / MOBILE NAV ── */
(function () {
  var hamburger = document.getElementById('hamburgerBtn');
  var drawer    = document.getElementById('mobileNavDrawer');
  if (!hamburger || !drawer) return;
 
  function openDrawer() {
    drawer.classList.add('open');
    hamburger.classList.add('open');
    hamburger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }
  function closeDrawer() {
    drawer.classList.remove('open');
    hamburger.classList.remove('open');
    hamburger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }
  function toggleDrawer() {
    drawer.classList.contains('open') ? closeDrawer() : openDrawer();
  }
 
  hamburger.addEventListener('click', function (e) {
    e.stopPropagation();
    toggleDrawer();
  });
 
  /* Close when a nav link is tapped */
  drawer.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', closeDrawer);
  });
 
  /* Close when clicking outside */
  document.addEventListener('click', function (e) {
    if (drawer.classList.contains('open') &&
        !drawer.contains(e.target) &&
        !hamburger.contains(e.target)) {
      closeDrawer();
    }
  });
 
  /* Close on Escape */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer.classList.contains('open')) closeDrawer();
  });
 
  /* Close on resize to desktop width */
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) closeDrawer();
  });
})();


  /* ── BACK TO TOP ── */
  var btt = document.getElementById('backToTop');
  window.addEventListener('scroll', function () {
    btt.classList.toggle('visible', window.scrollY > 400);
  });

  /* ── HERO SEARCH ── */
  var heroSearchBtn = document.getElementById('heroSearchBtn');
  heroSearchBtn.addEventListener('click', function (e) {
    e.preventDefault();
    var q   = document.getElementById('heroSearch').value.trim();
    var cat = document.getElementById('heroCategory').value;
    var url = '<?= BASE_URL ?>browse';
    var params = [];
    if (q)   params.push('q='   + encodeURIComponent(q));
    if (cat && cat !== 'All Categories') params.push('cat=' + encodeURIComponent(cat));
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
  });

  document.getElementById('heroSearch').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') heroSearchBtn.click();
  });

  /* ── ACTIVE NAV ON SCROLL ── */
  var sections  = document.querySelectorAll('section[id]');
  var navLinks  = document.querySelectorAll('.navbar-links a');

  function onScroll() {
    var scrollY = window.scrollY;
    sections.forEach(function (sec) {
      var top    = sec.offsetTop - 80;
      var bottom = top + sec.offsetHeight;
      if (scrollY >= top && scrollY < bottom) {
        navLinks.forEach(function (a) { a.classList.remove('active'); });
        var match = document.querySelector('.navbar-links a[href="#' + sec.id + '"]');
        if (match) match.classList.add('active');
      }
    });
  }

  window.addEventListener('scroll', onScroll, { passive: true });

  /* ── HERO IMAGE SLIDER ── */
  (function () {
    var slides   = document.querySelectorAll('.hero-slide');
    var dots     = document.querySelectorAll('.hero-dot');
    var current  = 0;
    var total    = slides.length;
    var timer    = null;
    var DELAY    = 5000;
    var heroSection = document.querySelector('.hero');

    function goTo(index) {
      slides[current].classList.remove('active');
      dots[current].classList.remove('active');
      current = (index + total) % total;
      slides[current].classList.add('active');
      dots[current].classList.add('active');
    }

    function startTimer() {
      clearInterval(timer);
      timer = setInterval(function () { goTo(current + 1); }, DELAY);
    }

    startTimer();

    dots.forEach(function (dot) {
      dot.addEventListener('click', function () {
        goTo(parseInt(this.getAttribute('data-index')));
        startTimer();
      });
    });

    /* Swipe support (touch) */
    var touchStartX = 0;
    heroSection.addEventListener('touchstart', function (e) {
      touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    heroSection.addEventListener('touchend', function (e) {
      var diff = touchStartX - e.changedTouches[0].screenX;
      if (Math.abs(diff) > 50) { goTo(diff > 0 ? current + 1 : current - 1); startTimer(); }
    }, { passive: true });
  })();
})();

/* ── TOAST SYSTEM ── */
function showToast(type, title, msg, duration) {
  duration = duration || 3500;
  var icons = { success: '&#10003;', error: '&#10007;', info: '&#9432;' };
  var container = document.getElementById('toastContainer');
  var toast = document.createElement('div');
  toast.className = 'toast toast-' + type;
  toast.innerHTML = '<div class="toast-icon">' + (icons[type] || '&#9432;') + '</div>'
    + '<div class="toast-content"><div class="toast-title">' + title + '</div>'
    + '<div class="toast-msg">' + msg + '</div></div>';
  container.appendChild(toast);
  setTimeout(function () {
    toast.classList.add('removing');
    setTimeout(function () { toast.remove(); }, 300);
  }, duration);
}




/* ── FAQ ACCORDION ── */
function toggleFaq(btn) {
  var item = btn.closest('.faq-item');
  var isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item.open').forEach(function (el) {
    el.classList.remove('open');
  });
  if (!isOpen) item.classList.add('open');
}

/* ── BOOKING MODAL ── */
var currentProvider = '';
var selectedSlot    = '';

var TIME_SLOTS = [
  '9:00 AM','9:30 AM','10:00 AM','10:30 AM',
  '11:00 AM','1:00 PM','1:30 PM','2:00 PM',
  '2:30 PM','3:00 PM','3:30 PM','4:00 PM'
];
var TAKEN_SLOTS = ['9:30 AM','11:00 AM','3:00 PM'];

function openBookingModal(name, cat) {
  currentProvider = name;
  selectedSlot    = '';

  document.getElementById('modalProviderName').textContent = 'Book — ' + name;
  document.getElementById('modalProviderCat').textContent  = cat;
  document.getElementById('sumProvider').textContent = name;
  document.getElementById('sumDateTime').textContent = '—';
  document.getElementById('sumPayment').textContent  = '—';

  var today = new Date();
  var dd = String(today.getDate()).padStart(2,'0');
  var mm = String(today.getMonth()+1).padStart(2,'0');
  var yyyy = today.getFullYear();
  var dateInput = document.getElementById('bookDate');
  dateInput.min   = yyyy+'-'+mm+'-'+dd;
  dateInput.value = yyyy+'-'+mm+'-'+dd;

  buildSlots();

  dateInput.addEventListener('change', updateSummary);
  document.getElementById('bookPayment').addEventListener('change', updateSummary);

  document.getElementById('bookingModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function buildSlots() {
  var grid = document.getElementById('slotsGrid');
  grid.innerHTML = '';
  selectedSlot = '';
  TIME_SLOTS.forEach(function (t) {
    var btn = document.createElement('div');
    btn.className = 'slot-btn' + (TAKEN_SLOTS.includes(t) ? ' slot-taken' : '');
    btn.textContent = t;
    if (!TAKEN_SLOTS.includes(t)) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.slot-btn.selected').forEach(function (el) { el.classList.remove('selected'); });
        btn.classList.add('selected');
        selectedSlot = t;
        updateSummary();
      });
    }
    grid.appendChild(btn);
  });
}

function updateSummary() {
  var dateVal = document.getElementById('bookDate').value;
  var payVal  = document.getElementById('bookPayment').value;

  if (dateVal && selectedSlot) {
    var d = new Date(dateVal);
    var opts = { weekday:'short', month:'short', day:'numeric' };
    document.getElementById('sumDateTime').textContent = d.toLocaleDateString('en-PH', opts) + ' · ' + selectedSlot;
  } else {
    document.getElementById('sumDateTime').textContent = '—';
  }

  var payLabels = { gcash:'GCash', paymaya:'PayMaya', card:'Credit/Debit Card', cash:'Cash on Service' };
  document.getElementById('sumPayment').textContent = payLabels[payVal] || '—';
}

function closeBookingModal() {
  document.getElementById('bookingModal').classList.add('hidden');
  document.body.style.overflow = '';
  document.getElementById('bookName').value    = '';
  document.getElementById('bookPhone').value   = '';
  document.getElementById('bookNotes').value   = '';
  document.getElementById('bookPayment').value = '';
}

function submitBooking() {
  var name    = document.getElementById('bookName').value.trim();
  var phone   = document.getElementById('bookPhone').value.trim();
  var date    = document.getElementById('bookDate').value;
  var payment = document.getElementById('bookPayment').value;

  if (!name) {
    showToast('error', 'Missing Info', 'Please enter your name.');
    return;
  }
  if (!phone || phone.length < 7) {
    showToast('error', 'Missing Info', 'Please enter a valid phone number.');
    return;
  }
  if (!selectedSlot) {
    showToast('error', 'No Slot Selected', 'Please pick a time slot.');
    return;
  }
  if (!payment) {
    showToast('error', 'No Payment', 'Please select a payment method.');
    return;
  }

  closeBookingModal();
  showToast('success', 'Booking Confirmed!', 'Your appointment with ' + currentProvider + ' is set for ' + selectedSlot + '.', 5000);
}

/* Close modal on overlay click */
document.getElementById('bookingModal').addEventListener('click', function (e) {
  if (e.target === this) closeBookingModal();
});

/* Close modal on Escape */
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeBookingModal();
});

/* ── STATS COUNT-UP ANIMATION ── */
(function () {
  var items = document.querySelectorAll('.stat-item[data-target]');
  if (!items.length) return;

  function animateCount(el) {
    var numEl    = el.querySelector('[data-count]');
    if (!numEl) return;
    var target   = parseFloat(el.dataset.target) || 0;
    var suffix   = el.dataset.suffix  || '';
    var isDecimal = el.dataset.decimal === 'true';
    var duration = 1400;
    var start    = null;

    if (target === 0) return;

    function step(ts) {
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var ease = 1 - Math.pow(1 - progress, 3);
      var current = target * ease;
      if (isDecimal) {
        numEl.textContent = current.toFixed(1) + suffix;
      } else {
        var rounded = Math.floor(current);
        numEl.textContent = (rounded >= 1000 ? rounded.toLocaleString() : rounded) + (progress < 1 ? '' : suffix);
      }
      if (progress < 1) requestAnimationFrame(step);
      else {
        if (isDecimal) {
          numEl.textContent = target.toFixed(1) + suffix;
        } else {
          numEl.textContent = (target >= 1000 ? Math.floor(target).toLocaleString() : Math.floor(target)) + suffix;
        }
      }
    }

    requestAnimationFrame(step);
  }

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        animateCount(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.3 });

  items.forEach(function (el) { observer.observe(el); });
})();
</script>

</body>
</html>
<?php
    }
}

$page = new HomePage();
$page->render();