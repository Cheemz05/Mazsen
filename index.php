<?php
require __DIR__ . '/includes/customer_auth.php';
$customer = customer_user();
$customerFirstName = $customer ? explode(' ', $customer['full_name'])[0] : '';
$customerNameParts = $customer ? preg_split('/\s+/', trim($customer['full_name']), -1, PREG_SPLIT_NO_EMPTY) : [];
$customerInitials = $customerNameParts ? mb_strtoupper(mb_substr($customerNameParts[0], 0, 1) . (count($customerNameParts) > 1 ? mb_substr($customerNameParts[count($customerNameParts) - 1], 0, 1) : ''), 'UTF-8') : '';
<<<<<<< HEAD
$validCustomerViews = ['dashboard', 'products', 'orders', 'favorites', 'addresses', 'payments', 'notifications', 'rewards'];
$initialCustomerView = $_GET['view'] ?? 'dashboard';
if (!in_array($initialCustomerView, $validCustomerViews, true)) $initialCustomerView = 'dashboard';
=======
>>>>>>> 69464754c591a639bef769c3d69740596b4fd76c
$pendingBuyNow = $_SESSION['pending_buy_now'] ?? null;
unset($_SESSION['pending_buy_now']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
<<<<<<< HEAD
  <meta name="theme-color" content="#f7f4fa">
  <link rel="icon" href="logo.jpg" type="image/jpeg">
  <title>MazSen Munch &amp; Sip | <?= $initialCustomerView === 'products' ? 'Our Menu' : ($initialCustomerView === 'favorites' ? 'Favorites' : 'Order Dashboard') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <link rel="stylesheet" href="assets/css/styles.css?v=202">
  <link rel="stylesheet" href="assets/css/logout.css?v=1">
</head>
<body class="min-h-screen antialiased" data-initial-view="<?= htmlspecialchars($initialCustomerView, ENT_QUOTES, 'UTF-8') ?>">
  <div class="app-shell">
    <aside class="sidebar">
      <button class="sidebar-collapse" id="sidebar-collapse" type="button" aria-label="Minimize navigation" aria-pressed="false" title="Minimize navigation">‹</button>
      <a class="brand" href="#dashboard" data-go="dashboard" aria-label="MazSen Munch and Sip home">
=======
  <meta name="theme-color" content="#f7f3eb">
  <link rel="icon" href="logo.jpg" type="image/jpeg">
  <title>MazSen Munch &amp; Sip | Order Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="assets/css/styles.css?v=10">
  <link rel="stylesheet" href="assets/css/logout.css?v=1">
</head>
<body class="min-h-screen antialiased">
  <div class="app-shell">
    <aside class="sidebar">
      <button class="sidebar-collapse" id="sidebar-collapse" type="button" aria-label="Minimize navigation" aria-pressed="false" title="Minimize navigation">‹</button>
      <a class="brand" href="#dashboard" aria-label="MazSen Munch and Sip home">
>>>>>>> 69464754c591a639bef769c3d69740596b4fd76c
        <img class="brand-logo" src="logo.jpg" alt="MazSen Munch &amp; Sip logo">
        <span class="brand-name">MAZSEN<small>MUNCH &amp; SIP</small></span>
      </a>
      <p class="nav-label">WORKSPACE</p>
      <nav class="main-nav" aria-label="Main navigation">
<<<<<<< HEAD
        <a class="nav-link<?= $initialCustomerView === 'dashboard' ? ' active' : '' ?>" href="index.php" data-view="dashboard"><span class="nav-icon"><i data-lucide="grid-2x2"></i></span>Dashboard<span class="nav-count" id="dashboard-count">0</span></a>
        <a class="nav-link<?= $initialCustomerView === 'products' ? ' active' : '' ?>" href="menu.php"><span class="nav-icon"><i data-lucide="utensils"></i></span>Order Menu</a>
        <a class="nav-link" href="#orders" data-view="orders"><span class="nav-icon"><i data-lucide="receipt-text"></i></span>My Orders<span class="nav-count" id="order-count">0</span></a>
        <a class="nav-link<?= $initialCustomerView === 'favorites' ? ' active' : '' ?>" href="favorites.php" data-view="favorites"><span class="nav-icon"><i data-lucide="heart"></i></span>Favorites<span class="nav-count" id="favorite-count">0</span></a>
        <a class="nav-link" href="#addresses" data-view="addresses"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>Addresses</a>
        <a class="nav-link" href="#payments" data-view="payments"><span class="nav-icon"><i data-lucide="credit-card"></i></span>Payment Methods</a>
        <a class="nav-link" href="#notifications" data-view="notifications"><span class="nav-icon"><i data-lucide="bell"></i></span>Notifications<span class="nav-count" id="notification-count">0</span></a>
        <a class="nav-link" href="#rewards" data-view="rewards"><span class="nav-icon"><i data-lucide="gift"></i></span>Rewards &amp; Offers</a>
        <button class="nav-link mobile-nav-cart" id="mobile-nav-cart" type="button" aria-label="Open cart"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.2 11.1a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L22 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></span>Cart<span class="nav-count" id="mobile-nav-cart-badge">0</span></button>
        <a class="nav-link mobile-nav-profile" href="<?= $customer ? 'customer/settings.php' : 'customer/welcome.php' ?>" aria-label="Profile"><span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg></span>Profile</a>
=======
        <a class="nav-link active" href="#dashboard" data-view="dashboard"><span class="nav-icon"><i data-lucide="layout-dashboard"></i></span>Dashboard<span class="nav-count" id="dashboard-count">0</span></a>
        <a class="nav-link" href="#products" data-view="products"><span class="nav-icon"><i data-lucide="utensils"></i></span>Order Menu</a>
        <a class="nav-link" href="#orders" data-view="orders"><span class="nav-icon"><i data-lucide="receipt-text"></i></span>My Orders<span class="nav-count" id="order-count">0</span></a>
        <a class="nav-link" href="#favorites" data-view="favorites"><span class="nav-icon"><i data-lucide="heart"></i></span>Favorites<span class="nav-count" id="favorite-count">0</span></a>
        <a class="nav-link" href="#addresses" data-view="addresses"><span class="nav-icon"><i data-lucide="map-pin"></i></span>Addresses</a>
        <a class="nav-link" href="#payments" data-view="payments"><span class="nav-icon"><i data-lucide="credit-card"></i></span>Payment Methods</a>
        <a class="nav-link" href="#notifications" data-view="notifications"><span class="nav-icon"><i data-lucide="bell"></i></span>Notifications<span class="nav-count" id="notification-count">0</span></a>
        <a class="nav-link" href="#rewards" data-view="rewards"><span class="nav-icon"><i data-lucide="gift"></i></span>Rewards &amp; Offers</a>
>>>>>>> 69464754c591a639bef769c3d69740596b4fd76c
      </nav>
      
      <div class="sidebar-note"><span class="status-dot" id="sidebar-store-dot"></span><div><strong id="sidebar-store-label">Now accepting orders</strong><small id="sidebar-store-note">Pickup and delivery</small></div></div>
      <div class="sidebar-footer">Freshly made, always.<br><span>Thanks for supporting local.</span></div>
    </aside>

    <main class="main-area">
      <header class="topbar">
<<<<<<< HEAD
        <a class="mobile-brand" href="#dashboard" data-go="dashboard"><img class="brand-logo" src="logo.jpg" alt=""><span>MAZSEN<small>MUNCH &amp; SIP</small></span></a>
        <div class="breadcrumb">MazSen Munch &amp; Sip <span>/</span> <strong id="page-label">Dashboard</strong></div>
        <label class="dashboard-search-box"><span aria-hidden="true">⌕</span><input id="dashboard-search-input" type="search" placeholder="Search menu" aria-label="Search menu"><kbd>Search</kbd></label>
        <div class="top-actions"><button class="mobile-cart-button" id="cart-jump-mobile" type="button" aria-label="Open your cart" aria-controls="cart-panel" aria-expanded="false"><span aria-hidden="true">🛒</span><span id="mobile-cart-badge">0</span></button><?php if ($customer): ?><button class="account-profile-link" id="profile-open" type="button" aria-label="Open profile"><span class="customer-avatar" aria-hidden="true"><?php if (!empty($customer['profile_image'])): ?><img src="<?= htmlspecialchars($customer['profile_image'], ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><?= htmlspecialchars($customerInitials, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span></button><span class="customer-greeting">Hi, <?= htmlspecialchars($customerFirstName, ENT_QUOTES, 'UTF-8') ?></span><?php else: ?><a class="text-button" href="customer/login.php">Sign in</a><a class="outline-button" href="customer/signup.php">Create account</a><?php endif; ?></div>
      </header>

      <section class="page-content<?= $initialCustomerView === 'dashboard' ? '' : ' hidden' ?>" id="dashboard-view">
        
        <section class="customer-hero" aria-labelledby="customer-hero-title">
          <div class="customer-hero-copy"><span class="customer-hero-kicker"><i></i> MAZSEN FAVORITES, MADE FRESH</span><h1 id="customer-hero-title"><span class="hero-title-desktop">Good food.<br><span>Your next favorite<br>is waiting.</span></span><span class="hero-title-mobile">Fast bites,<br>faster orders.</span></h1><p class="hero-copy-desktop">From juicy burgers to comforting silog meals and crispy sides, find a fresh favorite for every craving.</p><p class="hero-copy-mobile">Fresh favorites, made just for you.</p><div class="customer-hero-actions"><button class="customer-hero-primary" type="button" data-go="products">Explore the menu <span aria-hidden="true">&rarr;</span></button></div><span class="customer-hero-footnote">Freshly prepared. Ready when you are.</span></div>
          <div class="customer-hero-visual" aria-hidden="true"><span class="customer-hero-orbit"></span><span class="customer-hero-glow"></span><img class="customer-hero-food" src="Photos/fitted-4x3-fill/Chicken%20Poppers%20Rice.png" alt=""><span class="customer-hero-spark spark-one">✦</span><span class="customer-hero-spark spark-two">✧</span><div class="customer-hero-note hero-note-top"><span class="hero-note-icon">✦</span><span><strong>Freshly made</strong><small>Just for you</small></span></div><div class="customer-hero-note hero-note-bottom"><span class="hero-note-icon">⌁</span><span><strong>Pickup &amp; delivery</strong><small>Choose what works for you</small></span></div></div>
        </section>
                <div class="welcome-row"><div><p class="eyebrow">WELCOME TO MAZSEN</p><h1><span id="welcome-greeting" data-first-name="<?= htmlspecialchars($customerFirstName, ENT_QUOTES, 'UTF-8') ?>">Good day<?= $customerFirstName !== '' ? ', ' . htmlspecialchars($customerFirstName, ENT_QUOTES, 'UTF-8') : '' ?></span> <span>☀</span></h1><p class="subheading">Here's what's cooking at MazSen today.</p></div></div>
        <div class="section-heading"><div><p class="eyebrow">MAZSEN FAVORITES</p><h2>Popular on the menu</h2></div><button class="text-button" data-go="products">View all products <span>→</span></button></div>
        <div class="popular-grid" id="popular-products"></div>

        <section class="dashboard-category-section" aria-labelledby="dashboard-category-heading">
          <div class="section-heading"><div><p class="eyebrow">MADE FRESH TO ORDER</p><h2 id="dashboard-category-heading">What are you craving?</h2></div><button class="text-button" data-go="products">See full menu <span>→</span></button></div>
          <div class="dashboard-category-list" id="dashboard-categories"></div>
        </section>
<footer class="home-showcase-footer" aria-label="MazSen information">
          <section class="home-footer-cta">
            <div class="home-footer-cta-copy"><span class="home-footer-tag">READY WHEN YOU ARE</span><h2>Ready to place<br>your <em>order?</em></h2><p>Pick your favorites and enjoy freshly made food for pickup or delivery.</p><button class="home-footer-order" type="button" data-go="products">Explore the menu <span aria-hidden="true">&rarr;</span></button></div>
            <div class="home-footer-food"><span>MADE FRESH TO ORDER</span><img src="Photos/fitted-4x3-fill/Classic%20Burger.png" alt="Classic MazSen burger"></div>
          </section>
          <div class="home-footer-links">
            <div class="home-footer-about"><a class="home-footer-brand" href="#dashboard">MAZSEN <small>MUNCH &amp; SIP</small></a><p>Freshly made favorites, served with care in San Mateo.</p><div class="home-footer-social" aria-label="MazSen"><span>MM</span><span aria-hidden="true">&hearts;</span></div></div>
            <div><h3>Explore</h3><a href="#products" data-go="products">Order menu</a><a href="#products" data-go="products">Popular favorites</a><a href="#products" data-go="products">Browse categories</a></div>
            <div><h3>Your account</h3><?php if ($customer): ?><a href="#orders" data-go="orders">My orders</a><a href="customer/settings.php">Account details</a><a href="#favorites" data-go="favorites">Favorites</a><?php else: ?><a href="customer/login.php">Sign in</a><a href="customer/signup.php">Create account</a><?php endif; ?></div>
            <div><h3>Visit</h3><p>Natividad St., Malanday,<br>San Mateo, Rizal 1850</p><a href="https://www.google.com/maps/search/?api=1&amp;query=Natividad+St.+Malanday+San+Mateo+Rizal+1850" target="_blank" rel="noopener noreferrer">Get directions &rarr;</a><span>Pickup and delivery</span></div>
          </div>
          <div class="home-footer-wordmark" aria-hidden="true"><span>MAZSEN</span><strong>MUNCH</strong></div>
          <div class="home-footer-legal"><span>&copy; 2026 MazSen Munch &amp; Sip. Made with care.</span><span>Good food, good moments.</span></div>
        </footer>
      </section>

      <section class="page-content<?= $initialCustomerView === 'products' ? '' : ' hidden' ?>" id="products-view">
        <div class="welcome-row page-title-row"><div><h1>Our menu</h1></div><button class="cart-summary" id="cart-jump" aria-controls="cart-panel" aria-expanded="false">Your cart <strong id="cart-total-label">₱0.00</strong><span id="cart-badge">0</span></button></div>
        <div class="menu-toolbar"><div class="category-list" id="category-list"></div><label class="search-box"><span>⌕</span><input id="search-input" type="search" placeholder="Find something delicious" aria-label="Search menu"></label></div>
        <div class="menu-grid" id="menu-products"></div>
      </section>
      <button class="cart-backdrop" id="cart-backdrop" type="button" aria-label="Close your order"></button>
      <aside class="cart-panel" id="cart-panel" role="dialog" aria-modal="true" aria-labelledby="cart-heading" aria-hidden="true"><div class="panel-heading"><div><p class="eyebrow">READY WHEN YOU ARE</p><h2 id="cart-heading">Your order <span id="cart-title-count">(0)</span></h2></div><div class="cart-heading-actions"><button class="text-button clear-cart" id="clear-cart">Clear</button><button class="cart-close" id="cart-close" type="button" aria-label="Close your order">×</button></div></div><div id="cart-content"></div></aside>

      <?php if ($customer): ?><section class="profile-overlay" id="profile-overlay" role="dialog" aria-modal="true" aria-labelledby="profile-modal-name" aria-hidden="true" hidden>
        <div class="profile-modal-card"><button class="profile-modal-close" id="profile-close" type="button" aria-label="Close profile">×</button>
          <div class="profile-modal-summary"><div class="profile-modal-avatar"><?php if (!empty($customer['profile_image'])): ?><img src="<?= htmlspecialchars($customer['profile_image'], ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><?= htmlspecialchars($customerInitials, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></div><h2 id="profile-modal-name"><?= htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($customer['email'], ENT_QUOTES, 'UTF-8') ?></p><p><?= htmlspecialchars($customer['phone'], ENT_QUOTES, 'UTF-8') ?></p></div>
          <nav class="profile-modal-menu" aria-label="Profile options"><h3>Profile</h3><a href="customer/settings.php?section=details">Account details<span class="profile-modal-arrow">›</span></a><a href="#orders" data-go="orders">Order history<span class="profile-modal-arrow">›</span></a><a href="#addresses" data-go="addresses">Delivery address<span class="profile-modal-arrow">›</span></a><a href="customer/settings.php?section=password">Change password<span class="profile-modal-arrow">›</span></a></nav>
          <a class="profile-modal-logout" href="customer/logout.php">Log out</a>
        </div>
      </section><?php endif; ?>
=======
        <a class="mobile-brand" href="#dashboard"><img class="brand-logo" src="logo.jpg" alt=""><span>MAZSEN<small>MUNCH &amp; SIP</small></span></a>
        <div class="breadcrumb">MazSen Munch &amp; Sip <span>/</span> <strong id="page-label">Dashboard</strong></div>
        <div class="top-actions"><span class="open-pill" id="store-open-pill"><i></i><span id="store-status-text">Store open</span></span><?php if ($customer): ?><details class="account-menu"><summary class="account-avatar-trigger" aria-label="Open account menu" title="Account menu"><span class="customer-avatar" aria-hidden="true"><?php if (!empty($customer['profile_image'])): ?><img src="<?= htmlspecialchars($customer['profile_image'], ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><?= htmlspecialchars($customerInitials, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span></summary><div class="account-menu-panel"><a href="customer/settings.php"><i data-lucide="user-round-cog" aria-hidden="true"></i>Edit my information</a><a href="customer/logout.php"><i data-lucide="log-out" aria-hidden="true"></i>Log out</a></div></details><span class="customer-greeting">Hi, <?= htmlspecialchars($customerFirstName, ENT_QUOTES, 'UTF-8') ?></span><?php else: ?><a class="text-button" href="customer/login.php?next=products">Sign in</a><a class="outline-button" href="customer/signup.php">Create account</a><?php endif; ?></div>
      </header>

      <section class="page-content" id="dashboard-view">
        <section class="customer-hero" aria-labelledby="customer-hero-title">
          <div class="customer-hero-copy"><span class="customer-hero-kicker"><i></i> FRESH FROM MAZSEN</span><h1 id="customer-hero-title">Good food.<br><span>Your next favorite<br>is waiting.</span></h1><p>Burgers, silog meals, fries and more—made fresh for your next munch break.</p><div class="customer-hero-actions"><button class="customer-hero-primary" type="button" data-go="products">Order now <span aria-hidden="true">→</span></button></div><span class="customer-hero-footnote">Made with care, served with a smile.</span></div>
          <div class="customer-hero-visual" aria-hidden="true"><span class="customer-hero-orbit"></span><span class="customer-hero-glow"></span><span class="customer-hero-food">🍔</span><span class="customer-hero-spark spark-one">✦</span><span class="customer-hero-spark spark-two">✧</span><div class="customer-hero-note hero-note-top"><span class="hero-note-icon">✦</span><span><strong>Freshly made</strong><small>Just for you</small></span></div><div class="customer-hero-note hero-note-bottom"><span class="hero-note-icon">⌁</span><span><strong>Pickup &amp; delivery</strong><small>Choose what works for you</small></span></div></div>
        </section>
        <div class="welcome-row"><div><p class="eyebrow">WELCOME TO MAZSEN</p><h1><span id="welcome-greeting" data-first-name="<?= htmlspecialchars($customerFirstName, ENT_QUOTES, 'UTF-8') ?>">Good day<?= $customerFirstName !== '' ? ', ' . htmlspecialchars($customerFirstName, ENT_QUOTES, 'UTF-8') : '' ?></span> <span>☀</span></h1><p class="subheading">Here's what's cooking at MazSen today.</p></div></div>
        <div class="section-heading"><div><p class="eyebrow">MAZSEN FAVORITES</p><h2>Popular on the menu</h2></div><button class="text-button" data-go="products">View all products <span>→</span></button></div>
        <div class="popular-grid" id="popular-products"></div>

        <div class="lower-grid">
          <section class="panel recent-panel"><div class="panel-heading"><div><p class="eyebrow">YOUR ACTIVITY</p><h2>Recent orders</h2></div><button class="text-button" data-go="orders">See all <span>→</span></button></div><div id="recent-orders" class="empty-state compact-empty"><span class="empty-icon">▤</span><strong>No orders yet</strong><p>Your placed orders will show up here.</p><button class="outline-button" data-go="products">Explore the menu</button></div></section>
          <section class="promo-card"><div class="promo-content"><span class="promo-kicker">MADE FOR MUNCH TIME</span><h2>A little comfort<br>in every bite.</h2><p>From silog meals to loaded fries, find a new favorite today.</p><button class="light-button" data-go="products">Explore menu <span>→</span></button></div><span class="promo-art" aria-hidden="true">🍔</span></section>
        </div>
      </section>

      <section class="page-content hidden" id="products-view">
        <div class="welcome-row page-title-row"><div><p class="eyebrow">MADE FRESH FOR YOU</p><h1>Our menu</h1><p class="subheading">Good food, good mood. Pick your favorites.</p></div><button class="cart-summary" id="cart-jump" aria-controls="cart-panel" aria-expanded="false">Your cart <strong id="cart-total-label">₱0.00</strong><span id="cart-badge">0</span></button></div>
        <div class="menu-toolbar"><div class="category-list" id="category-list"></div><label class="search-box"><span>⌕</span><input id="search-input" type="search" placeholder="Find something delicious" aria-label="Search menu"></label></div>
        <div class="menu-grid" id="menu-products"></div>
        <button class="cart-backdrop" id="cart-backdrop" type="button" aria-label="Close your order"></button>
        <aside class="cart-panel" id="cart-panel" role="dialog" aria-modal="true" aria-labelledby="cart-heading" aria-hidden="true"><div class="panel-heading"><div><p class="eyebrow">READY WHEN YOU ARE</p><h2 id="cart-heading">Your order <span id="cart-title-count">(0)</span></h2></div><div class="cart-heading-actions"><button class="text-button clear-cart" id="clear-cart">Clear</button><button class="cart-close" id="cart-close" type="button" aria-label="Close your order">×</button></div></div><div id="cart-content"></div></aside>
      </section>
>>>>>>> 69464754c591a639bef769c3d69740596b4fd76c

      <section class="page-content hidden" id="orders-view">
        <div class="welcome-row page-title-row"><div><p class="eyebrow">YOUR MAZSEN HISTORY</p><h1>My orders</h1><p class="subheading">Keep track of the orders you've placed.</p></div></div>
        <div class="orders-panel panel"><div class="orders-toolbar"><div><h2>Order history</h2><p>Orders placed from your account</p></div><span class="history-count" id="history-count">0 orders</span></div><div id="orders-list"></div></div>
      </section>
<<<<<<< HEAD
      <div class="order-detail-backdrop" id="order-detail-backdrop" aria-hidden="true" hidden>
        <aside class="order-detail-drawer" role="dialog" aria-modal="true" aria-labelledby="order-detail-drawer-title">
          <header class="order-detail-drawer-header"><div><small>ORDER TRACKER</small><strong id="order-detail-drawer-title">Order details</strong></div><button id="order-detail-close" type="button" aria-label="Close order details">&times;</button></header>
          <div class="order-detail-drawer-content" id="order-detail-drawer-content"></div>
        </aside>
      </div>
      <section class="page-content<?= $initialCustomerView === 'favorites' ? '' : ' hidden' ?>" id="favorites-view">
=======
      <section class="page-content hidden" id="favorites-view">
>>>>>>> 69464754c591a639bef769c3d69740596b4fd76c
        <div class="welcome-row page-title-row"><div><p class="eyebrow">YOUR SAVED PICKS</p><h1>Favorites</h1><p class="subheading">Keep the meals you love close at hand.</p></div></div>
        <div class="menu-grid" id="favorite-products"></div>
      </section>
      <section class="page-content hidden" id="addresses-view">
        <div class="welcome-row page-title-row"><div><p class="eyebrow">DELIVERY DETAILS</p><h1>Addresses</h1><p class="subheading">Manage the address used for your orders.</p></div><a class="outline-button" href="customer/settings.php">Edit account details</a></div><div id="address-content"></div>
      </section>
      <section class="page-content hidden" id="payments-view">
        <div class="welcome-row page-title-row"><div><p class="eyebrow">CHECKOUT</p><h1>Payment methods</h1><p class="subheading">See the payment option currently available for MazSen orders.</p></div></div><div id="payment-content"></div>
      </section>
      <section class="page-content hidden" id="notifications-view">
        <div class="welcome-row page-title-row"><div><p class="eyebrow">ORDER UPDATES</p><h1>Notifications</h1><p class="subheading">Follow the latest progress on your orders.</p></div></div><div id="notification-content"></div>
      </section>
      <section class="page-content hidden" id="rewards-view">
        <div class="welcome-row page-title-row"><div><p class="eyebrow">A LITTLE EXTRA</p><h1>Rewards &amp; Offers</h1><p class="subheading">MazSen perks and special offers will be shown here when available.</p></div></div>
<<<<<<< HEAD
        <article class="account-info-card rewards-placeholder"><span class="account-info-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13M12 8H8.5a2.5 2.5 0 1 1 2.4-3.2L12 8Zm0 0h3.5a2.5 2.5 0 1 0-2.4-3.2L12 8Z"/></svg></span><div><h2>Good things are cooking</h2><p>There are no reward points or active offers on your account yet. Check back here for future MazSen perks.</p></div></article>
      </section>
=======
        <article class="account-info-card rewards-placeholder"><span class="account-info-icon">♙</span><div><h2>Good things are cooking</h2><p>There are no reward points or active offers on your account yet. Check back here for future MazSen perks.</p></div></article>
      </section>
>>>>>>> 69464754c591a639bef769c3d69740596b4fd76c
      <footer class="page-footer"><span>© 2026 MazSen Munch &amp; Sip</span><span>Good food, made with care <b>♥</b></span></footer>
    </main>
  </div>
  <div class="toast" id="toast" role="status" aria-live="polite"></div>
  <div class="confirm-backdrop" id="confirm-backdrop" aria-hidden="true">
    <section class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
      <span class="confirm-mark" aria-hidden="true">?</span>
      <h2 id="confirm-title">Please confirm</h2>
      <p id="confirm-message"></p>
      <div class="confirm-actions"><button class="outline-button" id="confirm-cancel" type="button">Go back</button><button class="primary-button" id="confirm-accept" type="button">Confirm</button></div>
    </section>
  </div>
  <div class="food-detail-backdrop" id="food-detail-backdrop" aria-hidden="true">
    <section class="food-detail-dialog" role="dialog" aria-modal="true" aria-label="Food details" tabindex="-1">
      <header class="food-detail-top"><span>MENU DETAILS</span><button id="food-detail-close" type="button" aria-label="Back to menu" title="Back to menu"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m7 7-7-7 7-7"/></svg><span>Back</span></button></header>
      <div id="food-detail-content"></div>
    </section>
  </div>
<<<<<<< HEAD
  <script>window.customerSignedIn = <?= $customer ? 'true' : 'false' ?>; window.customerProfile = <?= json_encode($customer ? ['id'=>(int)$customer['id'],'full_name'=>$customer['full_name'],'email'=>$customer['email'],'phone'=>$customer['phone'],'address'=>$customer['address'],'profile_image'=>$customer['profile_image'] ?? null] : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.pendingBuyNow = <?= json_encode($customer ? $pendingBuyNow : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.checkoutConfirmed = <?= isset($_GET['checkout_confirmed']) && $_GET['checkout_confirmed'] === '1' ? 'true' : 'false' ?>; window.storeOpen = true;</script>
  <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <script>if (window.lucide) lucide.createIcons();</script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

  <script src="assets/js/app.js?v=83"></script>
=======
  <script>window.customerSignedIn = <?= $customer ? 'true' : 'false' ?>; window.customerProfile = <?= json_encode($customer ? ['id'=>(int)$customer['id'],'full_name'=>$customer['full_name'],'email'=>$customer['email'],'phone'=>$customer['phone'],'address'=>$customer['address'],'profile_image'=>$customer['profile_image'] ?? null] : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.pendingBuyNow = <?= json_encode($customer ? $pendingBuyNow : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; window.storeOpen = true;</script>
  <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <script>if (window.lucide) lucide.createIcons();</script>
  <script src="assets/js/app.js?v=9"></script>
>>>>>>> 69464754c591a639bef769c3d69740596b4fd76c
  <script src="assets/js/logout-transition.js?v=1"></script>
</body>
</html>
