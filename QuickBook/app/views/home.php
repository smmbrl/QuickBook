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
        <div class="pcard" onclick="openBookingModal(' . json_encode($this->name) . ', ' . json_encode($this->category) . ', ' . json_encode($this->startingPrice) . ')">
            <div class="pcard-thumb">
                <img src="' . htmlspecialchars($this->imageUrl) . '"
                     alt="' . htmlspecialchars($this->name) . '" loading="lazy">
                ' . $this->getBadgeHtml() . '
            </div>
            <div class="pcard-body">
                <div class="pcard-cat">'  . htmlspecialchars($this->category) . '</div>
                <div class="pcard-name">' . htmlspecialchars($this->name)     . '</div>
                <div class="pcard-loc">&#128205; '  . htmlspecialchars($this->location)  . '</div>
                <div class="pcard-footer">
                    <div class="pcard-rating">
                        &#9733; ' . number_format($this->rating, 1) . '
                        <span class="rc">(' . $this->reviewCount . ' reviews)</span>
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

    public function __construct(string $number, string $label) {
        $this->number = $number;
        $this->label  = $label;
    }

    public function render(): string {
        return '
        <div class="stat-item">
            <div class="stat-num">' . htmlspecialchars($this->number) . '</div>
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
            'All Categories', 'Barber', 'Hair Stylist',
            'Nail Tech', 'Massage', 'Skincare',
            'Fitness', 'Home Cleaning', 'Pet Groomer', 'Event Stylist', 'Makeup',
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
            new CategoryCard('Makeup',        $base.'photo-1487412947147-5cebf100ffc2?w=120&q=70&auto=format&fit=crop', 'Makeup',   11),
        ];
    }

    private function initProviders(): void {
        $base = 'https://images.unsplash.com/';
        $this->providers = [
            new ProviderCard(
                "Raffy's Barbershop", 'Barber',
                'Sum-ag, Bacolod City · 0.3 km',
                $base.'photo-1503951914875-452162b0f3f1?w=600&q=75&auto=format&fit=crop',
                'available', 4.9, 320, '&#8369;150'
            ),
            new ProviderCard(
                'Wellness by Marga', 'Massage Therapy',
                'Mandalagan, Bacolod · 1.2 km',
                $base.'photo-1544161515-4ab6ce6db874?w=600&q=75&auto=format&fit=crop',
                'home', 4.8, 215, '&#8369;400'
            ),
            new ProviderCard(
                'Aling Nena Nails', 'Nail Technician',
                'Libertad, Bacolod · 1.8 km',
                $base.'photo-1604654894610-df63bc536371?w=600&q=75&auto=format&fit=crop',
                'available', 4.7, 189, '&#8369;250'
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
        $this->stats = [
            new StatItem('2,400+', 'Bookings This Month'),
            new StatItem('180+',   'Active Providers'),
            new StatItem('4.9',    'Average Rating'),
            new StatItem('12',     'Barangays Covered'),
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
      var t = localStorage.getItem('qb-theme');
      if (t === 'light') document.documentElement.setAttribute('data-theme', 'light');
    })();
  </script>
</head>
<body>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- BACK TO TOP -->
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Back to top">&#8593;</button>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="navbar-inner">
    <a href="<?= BASE_URL ?>" class="navbar-logo">Quick<span>Book</span></a>
    <ul class="navbar-links">
      <?= $navLinks ?>
    </ul>
    <div class="navbar-actions">

      <!-- THEME TOGGLE BUTTON -->
      <!-- FIX: icon-sun has style="display:none" by default so only moon shows in dark mode -->
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
            <img src="https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=600&q=75&auto=format&fit=crop" alt="Barbershop" loading="lazy">
            <span class="avail-pill">Available Now</span>
          </div>
          <div class="fc-main-body">
            <div class="fc-main-name">Raffy's Barbershop</div>
            <div class="fc-main-tag">Barber &middot; Sum-ag, Bacolod City</div>
            <div class="fc-slot">
              <div class="fc-slot-label">Next available slot</div>
              <div class="fc-slot-time">Today, 2:00 PM</div>
              <div class="fc-slot-detail">Haircut &middot; 30 mins &middot; &#8369;150</div>
            </div>
            <button class="fc-book-btn" onclick="openBookingModal('Raffy\'s Barbershop','Barber','&#8369;150')">Book Now</button>
          </div>
        </div>

        <!-- Rating card -->
        <div class="floating-card fc-rating">
          <div class="rating-score">4.9</div>
          <div class="rating-stars">&#9733; &#9733; &#9733; &#9733; &#9733;</div>
          <div class="rating-sub">320 reviews</div>
        </div>

        <!-- Confirmed badge -->
        <div class="floating-card fc-confirmed">
          <div class="check-circle">&#10003;</div>
          <span class="confirm-text">Booking Confirmed!</span>
        </div>

      </div>
    </div>
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
        <a href="#" class="social-btn" aria-label="Facebook">f</a>
        <a href="#" class="social-btn" aria-label="Instagram">ig</a>
        <a href="#" class="social-btn" aria-label="TikTok">tt</a>
      </div>
    </div>

    <div class="footer-col">
      <h4>Services</h4>
      <ul>
        <li><a href="#">Barber</a></li>
        <li><a href="#">Hair Stylist</a></li>
        <li><a href="#">Nail Tech</a></li>
        <li><a href="#">Massage</a></li>
        <li><a href="#">Skincare</a></li>
        <li><a href="#">Fitness</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Company</h4>
      <ul>
        <li><a href="#">About Us</a></li>
        <li><a href="#">Blog</a></li>
        <li><a href="#">Careers</a></li>
        <li><a href="#">Press</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Support</h4>
      <ul>
        <li><a href="#faq">FAQ</a></li>
        <li><a href="#">Help Center</a></li>
        <li><a href="#">Safety</a></li>
        <li><a href="#">Sitemap</a></li>
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

<div class="screen-label">Screen 1 / 10 &mdash; Home</div>

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
        <div class="summary-row">
          <span class="s-label">Starting from</span>
          <span class="s-gold" id="sumPrice">—</span>
        </div>
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
  /* ── THEME TOGGLE ──
     FIX 1: applyTheme now manually controls icon visibility via JS (not CSS)
             so it works regardless of CSS specificity or load order.
     FIX 2: No toast shown when theme is toggled.
  */
  var btn  = document.getElementById('themeToggle');
  var moon = document.querySelector('.icon-moon');
  var sun  = document.querySelector('.icon-sun');

  function applyTheme(theme) {
    if (theme === 'light') {
      document.documentElement.setAttribute('data-theme', 'light');
      if (moon) moon.style.display = 'none';
      if (sun)  sun.style.display  = 'block';
      btn.style.color = '#A16B0F';
    } else {
      document.documentElement.removeAttribute('data-theme');
      if (moon) moon.style.display = 'block';
      if (sun)  sun.style.display  = 'none';
      btn.style.color = '#C9A84C';
    }
  }

  var saved = localStorage.getItem('qb-theme') || 'dark';
  applyTheme(saved);

  btn.addEventListener('click', function () {
    var current = document.documentElement.getAttribute('data-theme');
    var next = current === 'light' ? 'dark' : 'light';
    localStorage.setItem('qb-theme', next);
    applyTheme(next);
    /* FIX 2: showToast call removed — no toast on theme change */
  });

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
var currentPrice    = '';
var selectedSlot    = '';

var TIME_SLOTS = [
  '9:00 AM','9:30 AM','10:00 AM','10:30 AM',
  '11:00 AM','1:00 PM','1:30 PM','2:00 PM',
  '2:30 PM','3:00 PM','3:30 PM','4:00 PM'
];
var TAKEN_SLOTS = ['9:30 AM','11:00 AM','3:00 PM'];

function openBookingModal(name, cat, price) {
  currentProvider = name;
  currentPrice    = price;
  selectedSlot    = '';

  document.getElementById('modalProviderName').textContent = 'Book — ' + name;
  document.getElementById('modalProviderCat').textContent  = cat;
  document.getElementById('sumProvider').textContent = name;
  document.getElementById('sumPrice').innerHTML = price;
  document.getElementById('sumDateTime').textContent = '—';
  document.getElementById('sumPayment').textContent  = '—';

  /* set min date to today */
  var today = new Date();
  var dd = String(today.getDate()).padStart(2,'0');
  var mm = String(today.getMonth()+1).padStart(2,'0');
  var yyyy = today.getFullYear();
  var dateInput = document.getElementById('bookDate');
  dateInput.min   = yyyy+'-'+mm+'-'+dd;
  dateInput.value = yyyy+'-'+mm+'-'+dd;

  buildSlots();

  /* live summary updates */
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

/* close modal on overlay click */
document.getElementById('bookingModal').addEventListener('click', function (e) {
  if (e.target === this) closeBookingModal();
});

/* close modal on Escape */
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeBookingModal();
});
</script>

</body>
</html>
<?php
    }
}

$page = new HomePage();
$page->render();