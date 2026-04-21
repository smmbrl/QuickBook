<?php
// =====================================================
//  home.php — QuickBook Home Page (OOP)
//  Project: QuickBook > app/views/home.php
// =====================================================

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
        <div class="cat-card">
            <div class="cat-icon">
                <img src="' . htmlspecialchars($this->imageUrl) . '"
                     alt="' . htmlspecialchars($this->imageAlt) . '">
            </div>
            <div class="cat-name">' . htmlspecialchars($this->name) . '</div>
            <div class="cat-count">' . $this->providerCount . ' providers</div>
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
    public string $startingPrice;

    public function __construct(
        string $name, string $category, string $location,
        string $imageUrl, string $badge,
        float $rating, int $reviewCount, string $startingPrice
    ) {
        $this->name          = $name;
        $this->category      = $category;
        $this->location      = $location;
        $this->imageUrl      = $imageUrl;
        $this->badge         = $badge;
        $this->rating        = $rating;
        $this->reviewCount   = $reviewCount;
        $this->startingPrice = $startingPrice;
    }

    private function getBadgeHtml(): string {
        if ($this->badge === 'available') {
            return '<span class="pcard-badge badge-avail">Available Now</span>';
        }
        return '<span class="pcard-badge badge-home">Home Service</span>';
    }

    public function render(): string {
        return '
        <div class="pcard">
            <div class="pcard-thumb">
                <img src="' . htmlspecialchars($this->imageUrl) . '"
                     alt="' . htmlspecialchars($this->name) . '">
                ' . $this->getBadgeHtml() . '
            </div>
            <div class="pcard-body">
                <div class="pcard-cat">'  . htmlspecialchars($this->category) . '</div>
                <div class="pcard-name">' . htmlspecialchars($this->name)     . '</div>
                <div class="pcard-loc">'  . htmlspecialchars($this->location)  . '</div>
                <div class="pcard-footer">
                    <div class="pcard-rating">
                        ★ ' . number_format($this->rating, 1) . '
                        <span class="rc">(' . $this->reviewCount . ')</span>
                    </div>
                    <div class="pcard-price">From ' . htmlspecialchars($this->startingPrice) . '</div>
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

class HomePage {

    private string $pageTitle;
    private string $cssPath;

    /** @var NavLink[]        */ private array $navLinks;
    /** @var CategoryCard[]   */ private array $categories;
    /** @var ProviderCard[]   */ private array $providers;
    /** @var HowItWorksStep[] */ private array $steps;
    /** @var string[]         */ private array $searchCategories;
    /** @var string[]         */ private array $trustItems;

    public function __construct() {
        $this->cssPath   = BASE_URL . 'assets/css/home.css';
        $this->pageTitle = 'QuickBook — Smart Local Booking';
        $this->initNavLinks();
        $this->initSearchCategories();
        $this->initTrustItems();
        $this->initCategories();
        $this->initProviders();
        $this->initSteps();
    }

    private function initNavLinks(): void {
        $this->navLinks = [
            new NavLink('Home',            BASE_URL,              true),
            // ✅ FIXED: Browse Services → #categories, For Providers → #cta
            new NavLink('Browse Services', '#categories'),
            new NavLink('How It Works',    '#how'),
            new NavLink('For Providers',   '#cta'),
        ];
    }

    private function initSearchCategories(): void {
        $this->searchCategories = [
            'All Categories', 'Barber', 'Hair Stylist',
            'Nail Tech', 'Massage', 'Skincare',
            'Fitness', 'Home Cleaning', 'Pet Groomer', 'Event Stylist',
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
        $base = 'https://images.unsplash.com/';
        $this->categories = [
            new CategoryCard('Barber',        $base.'photo-1585747860715-2ba37e788b70?w=120&q=70&auto=format&fit=crop', 'Barber',   48),
            new CategoryCard('Hair Stylist',  $base.'photo-1560066984-138dadb4c035?w=120&q=70&auto=format&fit=crop', 'Hair',     62),
            new CategoryCard('Nail Tech',     $base.'photo-1604654894610-df63bc536371?w=120&q=70&auto=format&fit=crop', 'Nail',     35),
            new CategoryCard('Massage',       $base.'photo-1544161515-4ab6ce6db874?w=120&q=70&auto=format&fit=crop', 'Massage',  29),
            new CategoryCard('Skincare',      $base.'photo-1570172619644-dfd03ed5d881?w=120&q=70&auto=format&fit=crop', 'Skincare', 22),
            new CategoryCard('Fitness',       $base.'photo-1534438327276-14e5300c3a48?w=120&q=70&auto=format&fit=crop', 'Fitness',  17),
            new CategoryCard('Home Cleaning', $base.'photo-1558618666-fcd25c85cd64?w=120&q=70&auto=format&fit=crop', 'Cleaning', 41),
            new CategoryCard('Pet Groomer',   $base.'photo-1548199973-03cce0bbc87b?w=120&q=70&auto=format&fit=crop', 'Pet',      14),
            new CategoryCard('Event Stylist', $base.'photo-1492684223066-81342ee5ff30?w=120&q=70&auto=format&fit=crop', 'Event',    19),
            new CategoryCard('Wellness',      $base.'photo-1506126613408-eca07ce68773?w=120&q=70&auto=format&fit=crop', 'Wellness', 11),
        ];
    }

    private function initProviders(): void {
        $base = 'https://images.unsplash.com/';
        $this->providers = [
            new ProviderCard(
                "Raffy's Barbershop", 'Barber',
                'Sum-ag, Bacolod City · 0.3 km',
                $base.'photo-1503951914875-452162b0f3f1?w=600&q=75&auto=format&fit=crop',
                'available', 4.9, 320, '₱150'
            ),
            new ProviderCard(
                'Wellness by Marga', 'Massage Therapy',
                'Mandalagan, Bacolod · 1.2 km',
                $base.'photo-1544161515-4ab6ce6db874?w=600&q=75&auto=format&fit=crop',
                'home', 4.8, 215, '₱400'
            ),
            new ProviderCard(
                'Aling Nena Nails', 'Nail Technician',
                'Libertad, Bacolod · 1.8 km',
                $base.'photo-1604654894610-df63bc536371?w=600&q=75&auto=format&fit=crop',
                'available', 4.7, 189, '₱250'
            ),
        ];
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

    public function render(): void {
        $title      = htmlspecialchars($this->pageTitle);
        $css        = htmlspecialchars($this->cssPath);
        $navLinks   = $this->renderNavLinks();
        $searchOpts = $this->renderSearchOptions();
        $trustItems = $this->renderTrustItems();
        $categories = $this->renderCategories();
        $providers  = $this->renderProviders();
        $steps      = $this->renderSteps();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $css ?>">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="navbar-inner">
    <a href="<?= BASE_URL ?>" class="navbar-logo">
      Quick<span>Book</span>
    </a>
    <ul class="navbar-links">
      <?= $navLinks ?>
    </ul>
    <div class="navbar-actions">
      <a href="<?= BASE_URL ?>login"    class="btn btn-ghost btn-sm">Log In</a>
      <a href="<?= BASE_URL ?>register" class="btn btn-gold btn-sm">Sign Up Free</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="home">
  <div class="hero-bg"></div>
  <div class="hero-grain"></div>
  <div class="hero-line"></div>

  <div class="hero-inner">

    <!-- LEFT — Copy -->
    <div class="hero-copy">
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
        <input type="text" placeholder="What service are you looking for?">
        <div class="hs-div"></div>
        <select>
          <?= $searchOpts ?>
        </select>
        <a href="<?= BASE_URL ?>browse" class="hs-btn">Search →</a>
      </div>

      <div class="trust-row">
        <?= $trustItems ?>
      </div>
    </div>

    <!-- RIGHT — Card Stack -->
    <div class="hero-visual">
      <div class="card-stack">

        <!-- Stats card -->
        <div class="floating-card fc-stats">
          <div class="stat-label">Today's Bookings</div>
          <div class="stat-number">24</div>
          <div class="stat-sub">Confirmed today</div>
          <div class="mini-avatars">
            <div class="mini-av" style="background:#3B82F6">JL</div>
            <div class="mini-av" style="background:#8B5CF6">RA</div>
            <div class="mini-av" style="background:#EC4899">MC</div>
            <div class="mini-av mini-av-gold">+8</div>
          </div>
        </div>

        <!-- Main card -->
        <div class="floating-card fc-main">
          <div class="fc-main-thumb">
            <img src="https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=600&q=75&auto=format&fit=crop" alt="Barbershop">
            <span class="avail-pill">Available Now</span>
          </div>
          <div class="fc-main-body">
            <div class="fc-main-name">Raffy's Barbershop</div>
            <div class="fc-main-tag">Barber · Sum-ag, Bacolod City</div>
            <div class="fc-slot">
              <div class="fc-slot-label">Next available slot</div>
              <div class="fc-slot-time">Today, 2:00 PM</div>
              <div class="fc-slot-detail">Haircut · 30 mins · ₱150</div>
            </div>
            <a href="#" class="fc-book-btn">Book Now</a>
          </div>
        </div>

        <!-- Rating card -->
        <div class="floating-card fc-rating">
          <div class="rating-score">4.9</div>
          <div class="rating-stars">★ ★ ★ ★ ★</div>
          <div class="rating-sub">320 reviews</div>
        </div>

        <!-- Confirmed badge -->
        <div class="floating-card fc-confirmed">
          <div class="check-circle">✓</div>
          <span class="confirm-text">Booking Confirmed!</span>
        </div>

      </div>
    </div>

  </div>
</section>

<!-- BROWSE BY CATEGORY -->
<!-- ✅ FIXED: Added id="categories" for nav anchor -->
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
      <a href="<?= BASE_URL ?>browse" class="btn btn-ghost btn-lg">View All Providers →</a>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<!-- ✅ Already has id="how" via the nav link -->
<section class="section how-section" id="how">
  <div class="section-inner">
    <div class="section-header">
      <div class="eyebrow-tag">Process</div>
      <h2>How QuickBook Works</h2>
      <p>Book your next appointment in under 2 minutes — from search to confirmation</p>
    </div>
    <div class="steps-container">
      <?= $steps ?>
    </div>
  </div>
</section>

<!-- CTA BANNER -->
<!-- ✅ FIXED: Added id="cta" for "For Providers" nav anchor -->
<section class="cta-banner" id="cta">
  <div class="cta-inner">
    <div class="eyebrow-tag" style="margin-bottom:1.2rem">For Providers</div>
    <h2>Ready to grow your business <em>digitally?</em></h2>
    <p>Join hundreds of local providers already using QuickBook to reach more customers in Bacolod.</p>
    <div class="cta-actions">
      <a href="<?= BASE_URL ?>register" class="btn btn-gold btn-lg">Get Started Free →</a>
      <a href="<?= BASE_URL ?>register" class="btn btn-ghost btn-lg">List Your Services</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  &copy; <?= date('Y') ?> <strong>QuickBook</strong> — Smart Local Booking Platform · Bacolod City, Philippines
</footer>

<div class="screen-label">Screen 1 / 10 — Home</div>

</body>
</html>
<?php
    }
}

$page = new HomePage();
$page->render();