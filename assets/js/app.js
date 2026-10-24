let menuItems = [];
/* MySQL is the source of truth; this catalog keeps the menu visible during DB outages. */
let menuPreviewOnly = false;

function starterMenuRows() {
  return [
    ['classic-burger','Classic Burger','Burgers',55,'\u{1F354}','Juicy patty, fresh lettuce, tomato and our house sauce.',true],
    ['omg-overload','OMG Overload','Burgers',95,'\u{1F354}','Burger, ham, egg, mayo, ketchup and TLC.',true],
    ['classic-hotdog','Classic Hotdog Sandwich','Burgers',75,'\u{1F32D}','TJ jumbo, mayo, ketchup and crisp coleslaw.',true],
    ['loaded-footlong','Loaded Footlong','Burgers',265,'\u{1F32D}','Footlong with burger, egg, mayo, cheese and TLC.',false],
    ['chicken-poppers-rice','Chicken Poppers Rice','Silog Meals',95,'\u{1F357}','Crispy chicken poppers, rice and your choice of flavor.',true],
    ['hamsilog','Hamsilog','Silog Meals',80,'\u{1F373}','Ham, garlic rice and a sunny side up egg.',true],
    ['pork-tapsilog','Pork Tapsilog','Silog Meals',85,'\u{1F373}','Savory pork tapa with garlic rice and egg.',false],
    ['longganisilog','Longganisilog','Silog Meals',95,'\u{1F373}','Filipino longganisa, rice and egg, all in one plate.',false],
    ['chick-pop-n-fries',"Chick Pop 'n Fries",'Snacks',99,'\u{1F35F}','Crispy chicken poppers over fries, drizzled with garlic mayo.',true],
    ['cheese-sticks','Cheese Sticks','Snacks',50,'\u{1F9C0}','Nine golden, crunchy cheese sticks.',false],
    ['dumplings','Dumplings','Snacks',50,'\u{1F95F}','Nine pieces, served hot and ready to share.',false],
    ['dynamite','Dynamite','Snacks',55,'\u{1F336}\u{FE0F}','Three crunchy, cheesy chili poppers.',false],
    ['regular-fries','Regular Fries','Fries',30,'\u{1F35F}','Golden fries. Add cheese, BBQ or sour cream flavor.',true],
    ['medium-fries','Medium Fries','Fries',65,'\u{1F35F}','A bigger serving of golden, crispy fries.',false],
    ['large-fries','Large Fries','Fries',95,'\u{1F35F}','Our biggest fries for sharing or keeping.',false],
    ['classic-hungarian','Classic Hungarian Sandwich','Hungarian & Footlong',65,'\u{1F32D}','Hungarian sausage with mayo, ketchup and coleslaw.',false],
    ['cheesy-footlong','Cheesy Footlong Sandwich','Hungarian & Footlong',155,'\u{1F32D}','A jumbo footlong with a cheesy, savory finish.',false],
    ['classic-sandwich','Ham Sandwich','Burgers',55,'\u{1F96A}','Ham, mayo, ketchup and coleslaw.',false],
    ['plain-rice','Plain Rice','Add-ons',15,'\u{1F35A}','A warm serving of steamed white rice.',false],
    ['garlic-rice','Garlic Rice','Add-ons',20,'\u{1F35A}','Savory garlic fried rice.',false],
    ['egg','Egg','Add-ons',15,'\u{1F373}','Add a freshly cooked egg to your meal.',false],
    ['burger-patty','Burger Patty','Add-ons',20,'\u{1F354}','Add an extra burger patty to your meal.',false],
  ];
}
const fallbackPhotos = {
  'classic-burger':'Classic%20Burger.png',
  'omg-overload':'OMG%20Overload.png',
  'classic-hotdog':'Classic%20Hotdog%20Sandwich.png',
  'loaded-footlong':'Loaded%20Footlong.png',
  'classic-sandwich':'Ham%20Sandwich.png',
  'chicken-poppers-rice':'Chicken%20Poppers%20Rice.png',
  hamsilog:'Hamsilog.png',
  'pork-tapsilog':'Pork%20Tapsilog.png',
  longganisilog:'Longganisilog.png',
};
const fallbackMenu = starterMenuRows().map(([id, name, category, price, emoji, description, popular]) => ({ id, name, category, price, emoji, description, popular, image_path: fallbackPhotos[id] ? `Photos/fitted-4x3-fill/${fallbackPhotos[id]}?v=4x3-fill` : null }));

const money = amount => `₱${Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const readStore = (key, fallback) => {
  try { return JSON.parse(localStorage.getItem(key)) ?? fallback; }
  catch { return fallback; }
};
const cartStorageKey = `mazsen-cart-v2:${window.customerSignedIn && window.customerProfile?.id ? window.customerProfile.id : 'guest'}`;
let cart = readStore(cartStorageKey, []);
if (!Array.isArray(cart)) cart = [];
let favorites = readStore('mazsen-favorites-v1', []);
if (!Array.isArray(favorites)) favorites = [];
const cartUnreadStorageKey = `${cartStorageKey}:unread`;
const favoriteUnreadStorageKey = 'mazsen-favorites-unread-v1';
let cartHasUnread = readStore(cartUnreadStorageKey, false) === true;
let favoritesHaveUnread = readStore(favoriteUnreadStorageKey, false) === true;
let orders = [];
let activeCategory = 'All';
let searchTerm = '';
let toastTimer;
let lastCartFocus = null;
let pendingConfirmation = null;
let lastConfirmationFocus = null;
let viewAnimationTimer = null;
let foodDetailReturnFocus = null;
let foodDetailQuantity = 1;
const orderAlertViews = ['dashboard', 'orders', 'notifications'];
let orderAlertKey = null;
let orderAlertState = null;

const $ = selector => document.querySelector(selector);
const $$ = selector => [...document.querySelectorAll(selector)];
function activeView() {
  const view = location.hash.slice(1);
  return ['dashboard', 'products', 'orders', 'favorites', 'addresses', 'payments', 'notifications', 'rewards'].includes(view) ? view : 'dashboard';
}

function orderSnapshot(orderList) {
  return Object.fromEntries(orderList.map(order => [String(order.number), canonicalOrderStatus(order.status)]));
}

function saveOrderAlertState() {
  if (!orderAlertKey || !orderAlertState) return;
  localStorage.setItem(orderAlertKey, JSON.stringify(orderAlertState));
  renderOrderAlertDots();
}

function renderOrderAlertDots() {
  orderAlertViews.forEach(view => {
    const link = $(`.nav-link[data-view="${view}"]`);
    if (!link) return;
    let badge = link.querySelector('.nav-count');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'nav-count';
      badge.id = `${view}-count`;
      badge.textContent = '0';
      link.append(badge);
    }
    const unread = orderAlertState?.unread?.[view]?.length ?? 0;
    badge.classList.toggle('has-notification', unread > 0);
    badge.setAttribute('aria-label', unread ? `${unread} unread order ${unread === 1 ? 'update' : 'updates'}` : 'No unread order updates');
  });
  const profileOrderLink = $('.profile-modal-menu a[data-go="orders"]');
  const hasUnreadOrders = (orderAlertState?.unread?.orders?.length ?? 0) > 0;
  profileOrderLink?.classList.toggle('has-order-notification', hasUnreadOrders);
  profileOrderLink?.setAttribute('aria-label', hasUnreadOrders ? 'Order history, new order update' : 'Order history');
  const favoritesBadge = $('#favorite-count');
  if (favoritesBadge) {
    favoritesBadge.textContent = '';
    favoritesBadge.classList.toggle('has-notification', favoritesHaveUnread && favorites.length > 0);
    favoritesBadge.setAttribute('aria-label', `${favorites.length} saved favorites`);
  }
}

function initializeOrderAlerts() {
  orderAlertKey = `mazsen-order-alerts-v1:${window.customerProfile?.id ?? window.customerProfile?.email ?? 'guest'}`;
  const snapshot = orderSnapshot(orders);
  const stored = readStore(orderAlertKey, null);
  if (!stored || !stored.snapshot || !stored.unread) {
    orderAlertState = { snapshot, unread: { dashboard: [], orders: [], notifications: [] } };
    saveOrderAlertState();
    return;
  }
  orderAlertState = {
    snapshot: stored.snapshot,
    unread: Object.fromEntries(orderAlertViews.map(view => [view, Array.isArray(stored.unread[view]) ? stored.unread[view].map(String) : []])),
  };
  trackOrderUpdates(orders);
}

function trackOrderUpdates(nextOrders, markUnread = true) {
  if (!orderAlertState) return;
  const nextSnapshot = orderSnapshot(nextOrders);
  const current = activeView();
  Object.entries(nextSnapshot).forEach(([number, status]) => {
    if (orderAlertState.snapshot[number] === status) return;
    orderAlertViews.forEach(view => {
      const unread = orderAlertState.unread[view];
      if (!markUnread || current === view) orderAlertState.unread[view] = unread.filter(item => item !== number);
      else if (!unread.includes(number)) unread.push(number);
    });
  });
  orderAlertState.snapshot = nextSnapshot;
  orderAlertViews.forEach(view => { orderAlertState.unread[view] = orderAlertState.unread[view].filter(number => Object.prototype.hasOwnProperty.call(nextSnapshot, number)); });
  saveOrderAlertState();
}

function markOrderAlertsSeen(view) {
  if (!orderAlertState || !orderAlertViews.includes(view)) return;
  orderAlertState.unread[view] = [];
  saveOrderAlertState();
}

function markOrderHistoryUnread(order) {
  if (!orderAlertState || !order?.number) return;
  const number = String(order.number);
  if (orderAlertState.unread.orders.includes(number)) return;
  orderAlertState.unread.orders.push(number);
  saveOrderAlertState();
}

const sidebarCollapse = $('#sidebar-collapse');
if (sidebarCollapse) {
  const tabletNav = window.matchMedia('(min-width: 561px) and (max-width: 820px)');
  // Keep navigation labels visible on first visit; only use the compact layout
  // when the customer has explicitly chosen to minimize it.
  document.body.classList.toggle('customer-sidebar-collapsed', localStorage.getItem('mazsen-sidebar-collapsed') === 'true');
  document.body.classList.toggle('customer-sidebar-expanded', tabletNav.matches && localStorage.getItem('mazsen-tablet-sidebar-expanded') !== 'false');
  $$('.main-nav .nav-link').forEach(link => { link.title = link.textContent.trim().replace(/\s+/g, ' '); });
  const syncSidebarButton = () => {
    const compact = tabletNav.matches
      ? !document.body.classList.contains('customer-sidebar-expanded')
      : document.body.classList.contains('customer-sidebar-collapsed');
    sidebarCollapse.setAttribute('aria-pressed', String(compact));
    sidebarCollapse.setAttribute('aria-label', compact ? 'Expand navigation' : 'Minimize navigation');
    sidebarCollapse.title = compact ? 'Expand navigation' : 'Minimize navigation';
    sidebarCollapse.textContent = compact ? '›' : '‹';
  };
  syncSidebarButton();
  window.addEventListener('resize', syncSidebarButton);
  sidebarCollapse.addEventListener('click', () => {
    if (tabletNav.matches) {
      const expanded = document.body.classList.toggle('customer-sidebar-expanded');
      localStorage.setItem('mazsen-tablet-sidebar-expanded', String(expanded));
    } else {
      const collapsed = document.body.classList.toggle('customer-sidebar-collapsed');
      localStorage.setItem('mazsen-sidebar-collapsed', String(collapsed));
    }
    syncSidebarButton();
  });
  $$('.main-nav .nav-link').forEach(link => link.addEventListener('click', () => {
    if (!tabletNav.matches) return;
    document.body.classList.remove('customer-sidebar-expanded');
    localStorage.setItem('mazsen-tablet-sidebar-expanded', 'false');
    syncSidebarButton();
  }));
}
const persist = () => {
  localStorage.setItem(cartStorageKey, JSON.stringify(cart));
};
const itemById = id => menuItems.find(item => item.id === id);
const cartQuantity = () => cart.reduce((sum, row) => sum + row.quantity, 0);
const cartTotal = () => cart.reduce((sum, row) => sum + itemById(row.id).price * row.quantity, 0);

function showToast(message) {
  const toast = $('#toast');
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
}

function askConfirmation({ title, message, buttonText, onConfirm }) {
  lastConfirmationFocus = document.activeElement;
  pendingConfirmation = onConfirm;
  $('#confirm-title').textContent = title;
  $('#confirm-message').textContent = message;
  $('#confirm-accept').textContent = buttonText;
  $('#confirm-backdrop').classList.add('is-open');
  $('#confirm-backdrop').setAttribute('aria-hidden', 'false');
  document.body.classList.add('confirmation-open');
  $('#confirm-cancel').focus();
}

function closeConfirmation(runAction = false) {
  const action = pendingConfirmation;
  pendingConfirmation = null;
  $('#confirm-backdrop').classList.remove('is-open');
  $('#confirm-backdrop').setAttribute('aria-hidden', 'true');
  document.body.classList.remove('confirmation-open');
  if (lastConfirmationFocus?.isConnected) lastConfirmationFocus.focus();
  if (runAction) action?.();
}

function productCard(item, showOrderActions = true, bestSeller = false) {
  const unavailable = window.storeOpen === false || menuPreviewOnly;
  const favorite = favorites.includes(item.id);
  return `<article class="food-card ${bestSeller ? 'is-best-seller' : ''}" data-food-detail="${escapeHTML(item.id)}" tabindex="0" aria-haspopup="dialog" aria-label="View details for ${escapeHTML(item.name)}">
    <div class="food-art">${item.image_path ? `<img class="food-photo" src="${escapeHTML(item.image_path)}" alt="${escapeHTML(item.name)}" loading="lazy"><span class="food-emoji food-emoji-fallback" aria-hidden="true">${escapeHTML(item.emoji)}</span>` : `<span class="food-emoji" aria-hidden="true">${escapeHTML(item.emoji)}</span>`}<button class="favorite-button ${favorite ? 'is-favorite' : ''}" type="button" data-favorite="${item.id}" aria-pressed="${favorite}" aria-label="${favorite ? 'Remove from' : 'Add to'} favorites">${favorite ? '♥' : '♡'}</button></div>
    <div class="food-body">
      <div class="food-row"><span class="food-name">${item.name}</span><span class="food-price">${money(item.price)}</span></div>
      <p class="food-desc">${item.description}</p>
      ${showOrderActions ? `<div class="food-actions"><button class="add-button" data-add="${item.id}" aria-label="Add ${item.name} to cart" ${unavailable ? 'disabled' : ''}><span class="add-button-label">Add to cart</span></button><button class="buy-button" data-buy="${item.id}" aria-label="Buy ${item.name} now" ${unavailable ? 'disabled' : ''}>${menuPreviewOnly ? 'Unavailable' : window.storeOpen === false ? 'Closed' : 'Buy now'}</button></div>` : ''}
    </div>
  </article>`;
}

function openFoodDetails(id, returnFocus = null, allowOrdering = true) {
  const item = itemById(id);
  if (!item) return;
  foodDetailQuantity = 1;
  if (returnFocus) foodDetailReturnFocus = returnFocus;
  const unavailable = window.storeOpen === false || menuPreviewOnly;
  $('#food-detail-content').innerHTML = `
    <div class="food-detail-layout">
      <div class="food-detail-art" aria-hidden="true">${item.image_path ? `<img src="${escapeHTML(item.image_path)}" alt="">` : `<span>${escapeHTML(item.emoji)}</span>`}</div>
      <div class="food-detail-copy">
        <div class="food-detail-meta"><span class="food-detail-category">${escapeHTML(item.category)}</span><span class="food-detail-fresh">Freshly made to order</span></div>
        <div class="food-detail-heading"><h2 id="food-detail-title">${escapeHTML(item.name)}</h2><strong>${money(item.price)}</strong></div>
        <section class="food-detail-description"><h3>What's in it</h3><p>${escapeHTML(item.description)}</p></section>
        <div class="food-detail-order-row"><span class="food-detail-quantity-label">Quantity</span><div class="food-detail-quantity"><button type="button" data-food-detail-quantity data-change="-1" aria-label="Remove one">−</button><output id="food-detail-quantity-value" aria-live="polite">1</output><button type="button" data-food-detail-quantity data-change="1" aria-label="Add one">＋</button></div></div>
        <div class="food-detail-actions"><button class="add-button" type="button" data-add="${escapeHTML(item.id)}" ${unavailable ? 'disabled' : ''}><span>Add to cart · ${money(item.price)}</span></button><button class="buy-button" type="button" data-buy="${escapeHTML(item.id)}" data-buy-quantity="1" ${unavailable ? 'disabled' : ''}>${menuPreviewOnly ? 'Unavailable' : window.storeOpen === false ? 'Closed' : `Order now · ${money(item.price)}`}</button></div>
      </div>
    </div>`;
  const modal = $('#food-detail-backdrop');
  modal.dataset.foodId = item.id;
  modal.dataset.allowOrdering = String(allowOrdering);
  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('food-detail-open');
  $('#food-detail-close').focus();
}

function closeFoodDetails() {
  const modal = $('#food-detail-backdrop');
  if (!modal.classList.contains('is-open')) return;
  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('food-detail-open');
  if (foodDetailReturnFocus?.isConnected) foodDetailReturnFocus.focus();
}

function renderPopular() {
  const popularItems = menuItems.filter(item => item.popular);
  const featuredItems = popularItems.slice(0, 4);
  for (const category of ['Snacks', 'Fries']) {
    const item = popularItems.find(product => product.category === category)
      || menuItems.find(product => product.category === category);
    if (item && !featuredItems.some(product => product.id === item.id)) featuredItems.push(item);
  }
  $('#popular-products').innerHTML = featuredItems.slice(0, 6).map(item => productCard(item, false, true)).join('');
}

function renderCategories() {
  const categories = [...new Set(menuItems.map(item => item.category))];
  if (activeCategory === 'All') activeCategory = categories[0] || '';
  $('#category-list').innerHTML = categories.map(category => `<button class="category-button ${category === activeCategory ? 'active' : ''}" data-category="${category}" aria-pressed="${category === activeCategory}">${category}</button>`).join('');
}

function renderDashboardCategories() {
  const container = $('#dashboard-categories');
  if (!container) return;
  const icons = { Burgers: '🍔', 'Silog Meals': '🍳', Snacks: '🥟', Fries: '🍟', 'Hungarian & Footlong': '🌭', 'Add-ons': '＋' };
  const categories = [...new Set(menuItems.map(item => item.category))];
  container.innerHTML = categories.map(category => {
    const count = menuItems.filter(item => item.category === category).length;
    return `<button class="dashboard-category" type="button" data-dashboard-category="${escapeHTML(category)}"><span class="dashboard-category-icon" aria-hidden="true">${icons[category] || '✦'}</span><span class="dashboard-category-copy"><strong>${escapeHTML(category)}</strong><small>${count} ${count === 1 ? 'item' : 'items'}</small></span><span class="dashboard-category-arrow" aria-hidden="true">→</span></button>`;
  }).join('');
}

function renderMenu(animate = false) {
  const filtered = menuItems.filter(item => {
    const matchesCategory = activeCategory === '' || item.category === activeCategory;
    const matchesSearch = `${item.name} ${item.description} ${item.category}`.toLowerCase().includes(searchTerm.toLowerCase());
    return matchesCategory && matchesSearch;
  });
  const grid = $('#menu-products');
  grid.innerHTML = filtered.length ? filtered.map(item => productCard(item)).join('') : '<div class="no-results">No menu items found. Try another search.</div>';
  if (animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    clearTimeout(grid.categoryAnimationTimer);
    grid.classList.remove('category-entering');
    void grid.offsetWidth;
    grid.classList.add('category-entering');
    grid.categoryAnimationTimer = window.setTimeout(() => grid.classList.remove('category-entering'), 1450);
  }
}

function renderFavorites() {
  const savedItems = menuItems.filter(item => favorites.includes(item.id));
  $('#favorite-products').innerHTML = savedItems.length
    ? savedItems.map(item => productCard(item)).join('')
    : `<div class="empty-state account-empty"><span class="empty-icon">${outlineIcon('heart')}</span><strong>No favorites saved yet</strong><p>Tap the heart on a menu item to save it here.</p><button class="outline-button" data-go="products">Explore the menu</button></div>`;
  const favoriteBadge = $('#favorite-count');
  if (favoriteBadge) {
    favoriteBadge.textContent = '';
    favoriteBadge.classList.toggle('has-notification', favoritesHaveUnread && favorites.length > 0);
    favoriteBadge.setAttribute('aria-label', `${favorites.length} saved favorites`);
  }
}

function toggleFavorite(id) {
  const isAdding = !favorites.includes(id);
  favorites = favorites.includes(id) ? favorites.filter(itemId => itemId !== id) : [...favorites, id];
  localStorage.setItem('mazsen-favorites-v1', JSON.stringify(favorites));
  if (isAdding) {
    favoritesHaveUnread = true;
    localStorage.setItem(favoriteUnreadStorageKey, 'true');
  }
  renderPopular(); renderMenu(); renderFavorites();
  showToast(favorites.includes(id) ? 'Added to your favorites.' : 'Removed from your favorites.');
}

function escapeHTML(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]);
}

function outlineIcon(name) {
  const paths = {
    bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
    card: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
    gift: '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13M12 8H8.5a2.5 2.5 0 1 1 2.4-3.2L12 8Zm0 0h3.5a2.5 2.5 0 1 0-2.4-3.2L12 8Z"/>',
    heart: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>',
    pin: '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
    receipt: '<path d="M4 3v18l2-1.5L8 21l2-1.5 2 1.5 2-1.5 2 1.5 2-1.5 2 1.5V3l-2 1.5L16 3l-2 1.5L12 3l-2 1.5L8 3 6 4.5 4 3Z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
  };
  return `<svg viewBox="0 0 24 24" aria-hidden="true">${paths[name] || ''}</svg>`;
}

function renderAccountViews() {
  const profile = window.customerProfile;
  $('#address-content').innerHTML = profile
    ? `<article class="account-info-card"><span class="account-info-icon">${outlineIcon('pin')}</span><div><span class="account-info-label">DEFAULT DELIVERY ADDRESS</span><h2>${escapeHTML(profile.full_name)}</h2><p>${escapeHTML(profile.address)}</p><small>${escapeHTML(profile.phone)}</small></div><a class="outline-button" href="customer/settings.php">Edit</a></article>`
    : '<div class="account-info-card"><div><h2>Sign in to view your address</h2><p>Your saved delivery details will appear here.</p></div><a class="outline-button" href="customer/login.php">Sign in</a></div>';
  $('#payment-content').innerHTML = `<article class="account-info-card"><span class="account-info-icon">${outlineIcon('card')}</span><div><span class="account-info-label">AVAILABLE PAYMENT METHOD</span><h2>Cash on delivery</h2><p>Pay the rider when your order arrives. No card details are stored by MazSen.</p></div><span class="payment-method-badge">Available</span></article>`;
  renderNotifications();
}

function renderNotifications() {
  const container = $('#notification-content');
  if (!container) return;
  if (!orders.length) {
    container.innerHTML = `<div class="empty-state account-empty"><span class="empty-icon">${outlineIcon('bell')}</span><strong>No order updates yet</strong><p>Updates will appear here after you place an order.</p><button class="outline-button" data-go="products">Browse menu</button></div>`;
    return;
  }
  container.innerHTML = `<div class="account-notification-list">${orders.slice(0, 12).map(order => `<article class="account-notification"><span class="notification-dot ${['Delivered','Completed'].includes(canonicalOrderStatus(order.status)) ? 'is-done' : ''}"></span><div><strong>Order ${escapeHTML(order.number)} · ${escapeHTML(canonicalOrderStatus(order.status))}</strong><p>${canonicalOrderStatus(order.status) === 'On the way' ? 'Your delivery rider is on the way.' : canonicalOrderStatus(order.status) === 'Preparing' ? 'The kitchen is preparing your order.' : canonicalOrderStatus(order.status) === 'Delivered' ? 'Your order has been delivered.' : canonicalOrderStatus(order.status) === 'Cancelled' ? 'This order was cancelled.' : 'Your order is being processed.'}</p><small>${new Date(order.createdAt).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}</small></div><button class="text-button" data-go="orders">View</button></article>`).join('')}</div>`;
}

function renderCart() {
  const quantity = cartQuantity();
  const total = cartTotal();
  $('#cart-badge').textContent = quantity;
  const mobileCartButton = $('#cart-jump-mobile');
  const mobileCartBadge = $('#mobile-cart-badge');
  if (mobileCartButton && mobileCartBadge) {
    mobileCartBadge.textContent = quantity;
    mobileCartButton.classList.toggle('has-items', quantity > 0);
    mobileCartButton.setAttribute('aria-label', quantity ? `Open cart with ${quantity} ${quantity === 1 ? 'item' : 'items'}` : 'Open empty cart');
  }
  const mobileNavCart = $('#mobile-nav-cart');
  const mobileNavCartBadge = $('#mobile-nav-cart-badge');
  if (mobileNavCart && mobileNavCartBadge) {
    mobileNavCartBadge.textContent = '';
    mobileNavCartBadge.classList.toggle('has-notification', cartHasUnread && quantity > 0);
    mobileNavCart.classList.toggle('has-items', quantity > 0);
    mobileNavCart.setAttribute('aria-label', quantity ? `Open cart with ${quantity} ${quantity === 1 ? 'item' : 'items'}` : 'Open empty cart');
  }
  $('#cart-total-label').textContent = money(total);
  $('#cart-title-count').textContent = `(${quantity})`;
  const itemsStat = $('#stat-items');
  if (itemsStat) itemsStat.textContent = quantity;
  $('#cart-content').innerHTML = cart.length ? `
    <div class="cart-lines">${cart.map(row => {
      const item = itemById(row.id);
      return `<div class="cart-item">
        <span class="cart-item-emoji">${item.image_path ? `<img src="${escapeHTML(item.image_path)}" alt="${escapeHTML(item.name)}">` : escapeHTML(item.emoji)}</span>
        <span class="cart-item-name">${item.name}<small>${money(item.price)} each · ${money(item.price * row.quantity)} total</small></span>
        <span class="quantity-control"><button data-quantity="${item.id}" data-change="-1" aria-label="Remove one ${item.name}">−</button><span>${row.quantity}</span><button data-quantity="${item.id}" data-change="1" aria-label="Add one ${item.name}">＋</button></span>
        <button class="cart-remove" data-remove="${item.id}" aria-label="Remove ${item.name}" title="Remove item">×</button>
      </div>`;
    }).join('')}</div>
    <div class="cart-bottom"><span class="cart-note">${menuPreviewOnly ? 'Ordering is unavailable while the database is offline.' : window.storeOpen === false ? 'The store is closed and cannot accept orders right now.' : window.customerSignedIn ? 'Delivery order · Pay upon delivery' : 'Sign in or create an account to place your order.'}</span><div class="cart-total-row"><span>Total payment</span><strong>${money(total)}</strong></div><button class="primary-button checkout-button" id="checkout-button" ${window.storeOpen === false || menuPreviewOnly ? 'disabled' : ''}>${menuPreviewOnly ? 'Ordering unavailable' : window.storeOpen === false ? 'Store is closed' : window.customerSignedIn ? 'Place order' : 'Sign in to order'} <span>→</span></button></div>`
    : '<div class="cart-empty">Your cart is empty. Add a favorite from the menu to get started.</div>';
  const ordersStat = $('#stat-orders');
  if (ordersStat) ordersStat.textContent = orders.length;
  renderNotifications();
}

function openCart(markAsSeen = true) {
  if (markAsSeen) {
    cartHasUnread = false;
    localStorage.setItem(cartUnreadStorageKey, 'false');
    renderCart();
  }
  const panel = $('#cart-panel');
  if (!panel.classList.contains('is-open')) lastCartFocus = document.activeElement;
  panel.classList.add('is-open');
  $('#cart-backdrop').classList.add('is-open');
  panel.setAttribute('aria-hidden', 'false');
  $('#cart-jump').setAttribute('aria-expanded', 'true');
  $('#cart-jump-mobile')?.setAttribute('aria-expanded', 'true');
  document.body.classList.add('cart-open');
  $('#cart-close').focus();
}

function closeCart() {
  const panel = $('#cart-panel');
  if (!panel) return;
  const wasOpen = panel.classList.contains('is-open');
  panel.classList.remove('is-open');
  $('#cart-backdrop').classList.remove('is-open');
  panel.setAttribute('aria-hidden', 'true');
  $('#cart-jump').setAttribute('aria-expanded', 'false');
  $('#cart-jump-mobile')?.setAttribute('aria-expanded', 'false');
  document.body.classList.remove('cart-open');
  if (wasOpen && lastCartFocus?.isConnected) lastCartFocus.focus();
}

function canonicalOrderStatus(status) {
  return ({ Received: 'Pending', Processing: 'Preparing', Ready: 'On the way', Completed: 'Delivered' })[status] || status;
}

function renderOrderProgress(status) {
  const current = canonicalOrderStatus(status);
  if (current === 'Cancelled') return '<div class="order-progress-cancelled">This order was cancelled.</div>';
  const steps = ['Preparing', 'Picked up', 'On the way', 'Delivered'];
  const activeIndex = current === 'Delivered' ? 3 : current === 'On the way' ? 2 : current === 'Preparing' ? 0 : 0;
  return `<div class="order-progress" aria-label="Order progress: ${current}"><div class="order-progress-steps">${steps.map((step, index) => `<div class="order-progress-step ${index < activeIndex ? 'is-complete' : ''} ${index === activeIndex ? 'is-current' : ''}"><span class="order-progress-marker">${index < activeIndex ? '✓' : index + 1}</span><span class="order-progress-label">${step}</span></div>`).join('')}</div></div>`;
}

const riderMaps = new Map();
const riderRouteEnabled = new Set();
const riderRoutePending = new Set();
const riderRouteCache = new Map();
const riderDestinationCache = new Map();
let lastRiderGeocodeAt = 0;
let activeMobileOrderCard = null;
let orderDrawerCloseTimer = null;

function closeMobileOrderDrawer() {
  const backdrop = $('#order-detail-backdrop');
  const card = activeMobileOrderCard;
  if (!backdrop || !card) return;
  riderMaps.forEach(entry => entry.map.remove());
  riderMaps.clear();
  const details = $('#order-detail-drawer-content')?.firstElementChild;
  if (details && card.isConnected) card.append(details);
  card.classList.remove('order-card-is-open');
  activeMobileOrderCard = null;
  backdrop.classList.remove('is-open');
  backdrop.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('order-detail-open');
  clearTimeout(orderDrawerCloseTimer);
  orderDrawerCloseTimer = window.setTimeout(() => {
    if (!activeMobileOrderCard) backdrop.hidden = true;
  }, 750);
  card.querySelector('[data-open-order]')?.focus({ preventScroll: true });
}

function openMobileOrderDrawer(card) {
  if (!card || !window.matchMedia('(max-width: 700px)').matches) return;
  if (activeMobileOrderCard) closeMobileOrderDrawer();
  const details = card.querySelector('.order-card-details');
  const backdrop = $('#order-detail-backdrop');
  const content = $('#order-detail-drawer-content');
  if (!details || !backdrop || !content) return;
  clearTimeout(orderDrawerCloseTimer);
  activeMobileOrderCard = card;
  card.classList.add('order-card-is-open');
  content.replaceChildren(details);
  $('#order-detail-drawer-title').textContent = `Order ${card.dataset.orderNumber}`;
  backdrop.hidden = false;
  backdrop.setAttribute('aria-hidden', 'false');
  document.body.classList.add('order-detail-open');
  requestAnimationFrame(() => backdrop.classList.add('is-open'));
  initRiderMaps();
  $('#order-detail-close')?.focus({ preventScroll: true });
}

function renderOrders() {
  if (activeMobileOrderCard) closeMobileOrderDrawer();
  riderMaps.forEach(entry => entry.map.remove());
  riderMaps.clear();
  const orderCount = orders.length;
  $('#history-count').textContent = `${orderCount} ${orderCount === 1 ? 'order' : 'orders'}`;
  const orderMarkup = orders.map(order => `
    <article class="order-card" data-order-number="${escapeHTML(order.number)}">
      <button class="mobile-order-summary" type="button" data-open-order aria-label="View tracker for order ${escapeHTML(order.number)}">
        <span class="mobile-order-summary-head"><span><strong>${escapeHTML(order.number)}</strong><small>${new Date(order.createdAt).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}</small></span><span class="order-status">${canonicalOrderStatus(order.status)}</span></span>
        <span class="mobile-order-summary-items">${order.items.slice(0, 2).map(row => escapeHTML(itemById(row.id)?.name ?? row.name ?? 'Menu item')).join(' · ')}${order.items.length > 2 ? ` · +${order.items.length - 2} more` : ''}</span>
        <span class="mobile-order-summary-foot"><span>Total <strong>${money(order.total)}</strong></span><span>View tracker <b aria-hidden="true">→</b></span></span>
      </button>
      <div class="order-card-details">
      <div class="order-head"><div><span class="order-number">${order.number}</span><span class="order-date">${new Date(order.createdAt).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}</span></div><span class="order-status">${canonicalOrderStatus(order.status)}</span></div>
      <div class="order-tracking-banner"><div><small>DELIVERY STATUS</small><strong>${canonicalOrderStatus(order.status)}</strong></div></div>
      ${canonicalOrderStatus(order.status) === 'On the way' ? `<section class="rider-tracking-card" aria-label="Live delivery tracker">
        <header class="track-order-heading"><span class="track-rider-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path d="M6 17h5l3-7h3l2 7M9 17l3-5m-1-2h4M7 7h3l2 3"/></svg></span><div><small>RIDER DELIVERY</small><strong>${escapeHTML(order.rider?.name || 'Your rider')}</strong><span>${order.riderLocation ? 'Your order is moving toward your address.' : 'Your rider picked up the order.'}</span></div><span class="track-live-pill"><i></i> LIVE</span></header>
        <div class="rider-map-frame"><div class="rider-map-topbar"><span class="rider-map-search"><i aria-hidden="true">⌕</i> Rider location</span><span class="rider-map-signal" aria-label="GPS tracking active">⌖</span></div><div class="rider-map" data-rider-map="${escapeHTML(order.number)}" data-lat="${order.riderLocation?.latitude ?? ''}" data-lng="${order.riderLocation?.longitude ?? ''}">${order.riderLocation ? '' : '<span class="rider-map-waiting"><b class="rider-waiting-pin">⌖</b><strong>Waiting for rider location</strong><span>The live map will appear when the rider shares GPS.</span></span>'}</div><div class="rider-map-caption"><span class="rider-map-caption-dot"></span><span data-rider-map-caption>${order.riderLocation?.updatedAt ? `GPS updated ${new Date(String(order.riderLocation.updatedAt).replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })}` : 'Waiting for rider GPS'}</span></div></div>
        <div class="track-order-progress">${renderOrderProgress(order.status)}</div>
        <div class="track-order-metrics"><div><small>DISTANCE LEFT</small><strong data-route-distance>—</strong></div><div><small>ESTIMATED ARRIVAL</small><strong data-route-eta>${order.riderLocation ? 'Calculating' : 'Waiting for GPS'}</strong></div><div><small>ORDER TOTAL</small><strong>${money(order.total)}</strong></div></div>
        <div class="track-order-address"><span aria-hidden="true">⌖</span><div><small>DELIVERY ADDRESS</small><strong>${escapeHTML(order.address || 'Address saved with your order')}</strong></div></div>
      </section>` : renderOrderProgress(order.status)}
      <div class="order-address"><div><small>DELIVERY ADDRESS</small><strong>${escapeHTML(order.address || 'Address saved with your order')}</strong></div></div>
      <div class="order-items-heading"><strong>Your order</strong><span>${order.items.reduce((sum, row) => sum + Number(row.quantity || 0), 0)} items</span></div>
      <div class="order-lines">${order.items.map(row => { const item = itemById(row.id); return `<span class="order-line-item"><span class="order-line-image" aria-hidden="true">${item?.image_path ? `<img src="${escapeHTML(item.image_path)}" alt="">` : escapeHTML(item?.emoji ?? '🍽️')}</span><span class="order-line-copy"><strong>${row.quantity} × ${escapeHTML(item?.name ?? row.name ?? 'Menu item')}</strong></span></span>`; }).join('')}</div>
      <div class="order-bottom"><span>Order total</span><strong>${money(order.total)}</strong></div>
      </div>
    </article>`).join('');
  $('#orders-list').innerHTML = orderCount ? orderMarkup : `<div class="empty-state"><span class="empty-icon">${outlineIcon('receipt')}</span><strong>Nothing on your order history yet</strong><p>When you place an order, it will be saved here on this device.</p><button class="outline-button" data-go="products">Browse the menu</button></div>`;
  if (!window.matchMedia('(max-width: 700px)').matches) initRiderMaps();

  $$('#orders-list .order-card').forEach((card, index) => {
    const canonicalStatus = canonicalOrderStatus(orders[index].status);
    card.querySelectorAll('.order-status').forEach(status => status.classList.add(`order-status-${canonicalStatus.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`));
    if (canonicalStatus !== 'Pending') return;
    const button = document.createElement('button');
    button.className = 'cancel-order-button';
    button.type = 'button';
    button.dataset.cancelOrder = orders[index].number;
    button.textContent = 'Cancel order';
    const summary = card.querySelector('.order-bottom');
    const total = summary?.querySelector('strong');
    const actions = document.createElement('div');
    actions.className = 'order-total-actions';
    if (total) actions.append(total);
    actions.append(button);
    summary?.append(actions);
  });

  renderNotifications();
}

$('#order-detail-close')?.addEventListener('click', closeMobileOrderDrawer);
$('#order-detail-backdrop')?.addEventListener('click', event => {
  if (event.target === $('#order-detail-backdrop')) closeMobileOrderDrawer();
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && activeMobileOrderCard) closeMobileOrderDrawer();
});

function initRiderMaps() {
  $$('[data-rider-map]').forEach(container => {
    if (container.dataset.lat === '' || container.dataset.lng === '') return;
    const lat = Number(container.dataset.lat), lng = Number(container.dataset.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    if (!window.L) { container.textContent = 'Map preview is unavailable.'; return; }
    const map = L.map(container, { scrollWheelZoom: false }).setView([lat, lng], 16);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>' }).addTo(map);
    const riderIcon = L.divIcon({ className: 'rider-location-icon', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 14] });
    const marker = L.marker([lat, lng], { icon: riderIcon, title: 'Rider location' }).addTo(map);
    const orderNumber = container.dataset.riderMap;
    const entry = { map, marker, routeLine: null, destinationMarker: null, lastRouteAt: 0, routeBusy: false };
    riderMaps.set(orderNumber, entry);
    const savedRoute = riderRouteCache.get(orderNumber);
    if (savedRoute) drawRiderRoute(orderNumber, savedRoute);
    else {
      riderRouteEnabled.add(orderNumber);
      window.setTimeout(() => { const order = orders.find(row => row.number === orderNumber); showRiderRoute(orderNumber, order?.address || '', null, order?.deliveryLocation || null); }, 250);
    }
    window.setTimeout(() => map.invalidateSize(), 80);
  });
}

function drawRiderRoute(orderNumber, routeData) {
  const entry = riderMaps.get(orderNumber);
  if (!entry || !window.L || !routeData?.coordinates?.length) return;
  if (entry.routeLine) entry.map.removeLayer(entry.routeLine);
  if (entry.destinationMarker) entry.map.removeLayer(entry.destinationMarker);
  const path = routeData.coordinates.map(([lng, lat]) => [lat, lng]);
  entry.routeLine = L.polyline(path, { color: '#ed4b4b', weight: 5, opacity: .94, lineCap: 'round', lineJoin: 'round' }).addTo(entry.map);
  entry.destinationMarker = L.circleMarker(path[path.length - 1], { radius: 7, color: '#fff', weight: 3, fillColor: '#ed4b4b', fillOpacity: 1 }).addTo(entry.map).bindTooltip('Delivery address');
  entry.map.fitBounds(entry.routeLine.getBounds(), { padding: [44, 44], maxZoom: 16, animate: true });
  entry.lastRouteAt = Number(routeData.calculatedAt) || Date.now();
  const caption = document.querySelector(`[data-rider-map="${CSS.escape(orderNumber)}"]`)?.parentElement?.querySelector('[data-rider-map-caption]');
  if (caption) caption.textContent = `OSM / OSRM route · about ${Math.max(1, Math.round(Number(routeData.duration || 0) / 60))} min · ${(Number(routeData.distance || 0) / 1000).toFixed(1)} km`;
  const metrics = document.querySelector(`[data-rider-map="${CSS.escape(orderNumber)}"]`)?.closest('.rider-tracking-card');
  const distance = metrics?.querySelector('[data-route-distance]');
  const eta = metrics?.querySelector('[data-route-eta]');
  if (distance) distance.textContent = Number(routeData.distance) >= 1000 ? `${(Number(routeData.distance) / 1000).toFixed(1)} km` : `${Math.max(0, Math.round(Number(routeData.distance) || 0))} m`;
  if (eta) eta.textContent = `${Math.max(1, Math.round(Number(routeData.duration || 0) / 60))} min`;
}

async function showRiderRoute(orderNumber, address, button, deliveryLocation = null) {
  const entry = riderMaps.get(orderNumber);
  const location = orders.find(order => order.number === orderNumber)?.riderLocation;
  if (!entry || !location || entry.routeBusy || riderRoutePending.has(orderNumber)) return;
  const caption = document.querySelector(`[data-rider-map="${CSS.escape(orderNumber)}"]`)?.parentElement?.querySelector('[data-rider-map-caption]');
  if (!address.trim()) { if (caption) caption.textContent = 'Add a delivery address to calculate the route.'; return; }
  entry.routeBusy = true;
  riderRoutePending.add(orderNumber);
  if (button) { button.disabled = true; button.textContent = 'Finding route…'; }
  if (caption) caption.textContent = 'Finding route · delivery address and rider GPS are sent to OpenStreetMap / OSRM';
  try {
    let destination = deliveryLocation && Number.isFinite(Number(deliveryLocation.latitude)) && Number.isFinite(Number(deliveryLocation.longitude))
      ? { lat: Number(deliveryLocation.latitude), lng: Number(deliveryLocation.longitude) }
      : riderDestinationCache.get(address);
    const destinationStorageKey = `mazsen.geocoded-address:${address.trim().toLowerCase()}`;
    if (!destination) {
      try { destination = JSON.parse(sessionStorage.getItem(destinationStorageKey) || 'null'); } catch { destination = null; }
      if (destination?.lat && destination?.lng) riderDestinationCache.set(address, destination);
      else destination = null;
    }
    if (!destination) {
      const wait = Math.max(0, 1100 - (Date.now() - lastRiderGeocodeAt));
      if (wait) await new Promise(resolve => window.setTimeout(resolve, wait));
      lastRiderGeocodeAt = Date.now();
      const geocodeUrl = new URL('https://nominatim.openstreetmap.org/search');
      geocodeUrl.search = new URLSearchParams({ format: 'jsonv2', limit: '1', countrycodes: 'ph', q: address }).toString();
      const geocodeResponse = await fetch(geocodeUrl, { headers: { Accept: 'application/json' } });
      if (!geocodeResponse.ok) throw new Error('Address lookup is temporarily unavailable.');
      const results = await geocodeResponse.json();
      if (!results.length) throw new Error('Could not match the delivery address to a map location.');
      destination = { lat: Number(results[0].lat), lng: Number(results[0].lon) };
      riderDestinationCache.set(address, destination);
      try { sessionStorage.setItem(destinationStorageKey, JSON.stringify(destination)); } catch { /* Continue without browser storage. */ }
    }
    const originLat = Number(location.latitude), originLng = Number(location.longitude);
    const routeUrl = `https://router.project-osrm.org/route/v1/driving/${originLng},${originLat};${destination.lng},${destination.lat}?overview=full&geometries=geojson`;
    const routeResponse = await fetch(routeUrl, { headers: { Accept: 'application/json' } });
    if (!routeResponse.ok) throw new Error('The routing service is temporarily unavailable.');
    const routeResult = await routeResponse.json();
    if (routeResult.code !== 'Ok' || !routeResult.routes?.[0]) throw new Error('No drivable route was found for this address.');
    const route = routeResult.routes[0];
    const routeData = { coordinates: route.geometry.coordinates, duration: route.duration, distance: route.distance, calculatedAt: Date.now(), origin: { latitude: originLat, longitude: originLng } };
    riderRouteCache.set(orderNumber, routeData);
    riderRouteEnabled.add(orderNumber);
    drawRiderRoute(orderNumber, routeData);
  } catch (error) {
    if (caption) caption.textContent = error.message || 'Could not calculate a route. Please try again.';
    const eta = document.querySelector(`[data-rider-map="${CSS.escape(orderNumber)}"]`)?.closest('.rider-tracking-card')?.querySelector('[data-route-eta]');
    if (eta) eta.textContent = 'Unavailable';
  } finally {
    entry.routeBusy = false;
    riderRoutePending.delete(orderNumber);
    if (button) { button.disabled = false; button.textContent = riderRouteCache.has(orderNumber) ? 'Refresh route' : 'Show delivery route'; }
  }
}

function syncRiderMaps() {
  orders.forEach(order => {
    const location = order.riderLocation;
    if (!location) return;
    const point = [Number(location.latitude), Number(location.longitude)];
    if (!Number.isFinite(point[0]) || !Number.isFinite(point[1])) return;
    const entry = riderMaps.get(order.number);
    if (entry) {
      entry.marker.setLatLng(point);
      entry.map.panTo(point, { animate: true, duration: 0.6 });
      const route = riderRouteCache.get(order.number);
      if (riderRouteEnabled.has(order.number) && route?.origin && Date.now() - (entry.lastRouteAttemptAt || route.calculatedAt) > 60000) {
        const dLat = (point[0] - route.origin.latitude) * 111320;
        const dLng = (point[1] - route.origin.longitude) * 111320 * Math.cos(point[0] * Math.PI / 180);
        if (Math.hypot(dLat, dLng) > 250) {
          entry.lastRouteAttemptAt = Date.now();
          showRiderRoute(order.number, order.address || '', null, order.deliveryLocation || null);
        }
      }
    }
    const updated = document.querySelector(`[data-rider-map="${CSS.escape(order.number)}"]`)?.parentElement?.querySelector('[data-rider-map-caption]');
    if (updated && !riderRouteCache.has(order.number)) updated.textContent = `Rider location updated ${new Date(String(location.updatedAt).replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })}`;
  });
}

function refresh() {
  persist();
  renderCart();
  renderOrders();
}

function goTo(view, markAsSeen = true) {
  const views = ['dashboard', 'products', 'orders', 'favorites', 'addresses', 'payments', 'notifications', 'rewards'];
  const target = views.includes(view) ? view : 'dashboard';
  const targetSection = $(`#${target}-view`);
  if (!targetSection) return;
  if (target === 'favorites') {
    favoritesHaveUnread = false;
    localStorage.setItem(favoriteUnreadStorageKey, 'false');
  }
  $('.topbar > .dashboard-search-box')?.classList.toggle('is-hidden', target !== 'dashboard');
  if (markAsSeen) markOrderAlertsSeen(target);
  if (target !== 'products') closeCart();
  $$('.page-content').forEach(section => {
    section.classList.remove('view-entering');
    const isActiveView = section === targetSection;
    section.hidden = !isActiveView;
    section.classList.toggle('hidden', !isActiveView);
  });
  targetSection.hidden = false;
  targetSection.classList.remove('hidden');
  void targetSection.offsetWidth;
  targetSection.classList.add('view-entering');
  clearTimeout(viewAnimationTimer);
  viewAnimationTimer = setTimeout(() => targetSection.classList.remove('view-entering'), 720);
  $$('.nav-link').forEach(link => link.classList.toggle('active', link.dataset.view === target || (target === 'products' && link.getAttribute('href') === 'menu.php')));
  const labels = { dashboard:'Dashboard', products:'Order Menu', orders:'My Orders', favorites:'Favorites', addresses:'Addresses', payments:'Payment Methods', notifications:'Notifications', rewards:'Rewards & Offers' };
  $('#page-label').textContent = labels[target];
  if (target === 'products') { renderCategories(); renderMenu(); }
  if (target === 'orders') renderOrders();
  if (target === 'favorites') renderFavorites();
  if (target === 'notifications') renderNotifications();
  if (target === 'addresses' || target === 'payments') renderAccountViews();
  history.replaceState(null, '', `#${target}`);
  window.scrollTo({ top: 0, behavior: 'auto' });
}

// Handle the fixed mobile navigation at its source so taps always switch views.
$('.main-nav')?.addEventListener('click', event => {
  const link = event.target.closest('a[data-view]');
  if (!link || !$('.main-nav')?.contains(link)) return;
  event.preventDefault();
  event.stopPropagation();
  if (['dashboard', 'products', 'favorites'].includes(link.dataset.view)) {
    window.location.assign(link.href);
    return;
  }
  goTo(link.dataset.view);
});

function animateItemToCart(id, startRect, foodPicture) {
  const mobileNavCartIcon = $('#mobile-nav-cart .nav-icon');
  const mobileNavCartIsVisible = mobileNavCartIcon && mobileNavCartIcon.getClientRects().length > 0;
  const target = (window.matchMedia('(max-width: 700px)').matches && mobileNavCartIsVisible
    ? [mobileNavCartIcon, $('#cart-jump-mobile'), $('#cart-jump'), $('#cart-badge')]
    : ['#cart-jump-mobile', '#cart-jump', '#cart-badge'].map(selector => $(selector)))
    .find(element => element && element.getClientRects().length > 0);
  const item = itemById(id);
  if (!startRect || !target || !item || window.matchMedia('(prefers-reduced-motion: reduce)').matches || typeof Element.prototype.animate !== 'function') return;
  const endRect = target.getBoundingClientRect();
  if (!endRect.width || !endRect.height) return;
  const startX = startRect.left + startRect.width / 2;
  const startY = startRect.top + startRect.height / 2;
  const endX = endRect.left + endRect.width / 2;
  const endY = endRect.top + endRect.height / 2;
  const dx = endX - startX;
  const dy = endY - startY;
  const flyerSize = Math.max(58, Math.min(76, window.innerWidth * .18));
  const flyer = document.createElement('div');
  flyer.className = 'cart-fly-box';
  if (foodPicture) {
    const art = foodPicture;
    art.classList.add('cart-fly-art');
    art.style.width = '100%';
    art.style.setProperty('height', '100%', 'important');
    art.style.minHeight = '0';
    art.style.maxHeight = 'none';
    art.style.setProperty('aspect-ratio', '1 / 1', 'important');
    art.style.border = '0';
    art.style.borderRadius = '11px';
    art.style.boxShadow = 'none';
    const foodEmoji = art.querySelector('.food-emoji, span');
    if (foodEmoji) foodEmoji.style.fontSize = `${flyerSize * .58}px`;
    flyer.append(art);
  } else {
    flyer.textContent = item.emoji;
  }
  Object.assign(flyer.style, {
    position: 'fixed', zIndex: '10000', left: `${startX}px`,
    top: `${startY}px`, width: `${flyerSize}px`, height: `${flyerSize}px`,
    margin: '0', display: 'grid', placeItems: 'center', overflow: 'hidden',
    border: '1px solid #dce8de', borderRadius: '14px',
    background: '#f7faf7',
    boxShadow: '0 10px 24px rgba(0, 0, 0, .24)',
    fontSize: `${flyerSize * .58}px`, pointerEvents: 'none',
    transition: 'none', transform: 'translate(-50%, -50%)',
  });
  document.body.append(flyer);
  const arc = Math.min(110, Math.max(30, Math.hypot(dx, dy) * .14));
  const animation = flyer.animate([
    { transform: 'translate(-50%, -50%) rotate(0deg) scale(1)', opacity: 1, offset: 0 },
    { transform: `translate(calc(-50% + ${dx * .48}px), calc(-50% + ${dy * .48 - arc}px)) rotate(-12deg) scale(.78)`, opacity: 1, offset: .48 },
    { transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) rotate(18deg) scale(.16)`, opacity: 0, offset: 1 },
  ], { duration: 1300, easing: 'cubic-bezier(.2,.75,.25,1)' });
  animation.onfinish = () => flyer.remove();
  animation.oncancel = () => flyer.remove();
}

function requireDeliveryAddress() {
  if (!window.customerSignedIn) {
    window.location.href = 'customer/login.php?next=products';
    return false;
  }
  const address = String(window.customerProfile?.address ?? '').trim();
  if (address.length >= 8 && address.length <= 500) return true;
  if (window.customerSignedIn) window.location.href = 'customer/settings.php?section=address';
  return false;
}

function addToCart(id, startRect, quantity = 1) {
  if (!window.customerSignedIn) {
    window.location.href = 'customer/login.php?next=products';
    return;
  }
  if (menuPreviewOnly) { showToast('Ordering is unavailable while the database is offline.'); return; }
  if (window.storeOpen === false) { showToast('The store is closed and cannot accept orders right now.'); return; }
  const existing = cart.find(row => row.id === id);
  quantity = Math.max(1, Math.min(99, Number(quantity) || 1));
  if (existing) existing.quantity += quantity;
  else cart.push({ id, quantity });
  cartHasUnread = true;
  localStorage.setItem(cartUnreadStorageKey, 'true');
  refresh();
  animateItemToCart(id, startRect, startRect?.foodPicture);
  openCart(false);
  showToast(`${itemById(id).name} × ${quantity} added to your order`);
}

function changeQuantity(id, amount) {
  const row = cart.find(item => item.id === id);
  if (!row) return;
  row.quantity += amount;
  if (row.quantity < 1) cart = cart.filter(item => item.id !== id);
  refresh();
}

async function buyNow(id, quantity = 1) {
  if (menuPreviewOnly) { showToast('Ordering is unavailable while the database is offline.'); return; }
  if (window.storeOpen === false) { showToast('The store is closed and cannot accept orders right now.'); return; }
  const item = itemById(id);
  if (!item) { showToast('This menu item is no longer available.'); return; }
  if (!window.customerSignedIn) {
    window.location.href = `customer/login.php?next=products&buy=${encodeURIComponent(id)}&qty=${quantity}`;
    return;
  }
  if (!requireDeliveryAddress()) return;
  try {
    const response = await fetch('includes/api.php?resource=orders', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: [{ id, quantity }] }),
    });
    const result = await response.json();
    if (response.status === 401) {
      window.location.href = `customer/login.php?next=products&buy=${encodeURIComponent(id)}`;
      return;
    }
    if (response.status === 422 && /delivery address/i.test(result.error || '')) { window.location.href = 'customer/settings.php?section=address'; return; }
    if (!response.ok) throw new Error(result.error || 'Order could not be placed.');
    orders.unshift(result);
    trackOrderUpdates(orders);
    markOrderHistoryUnread(result);
    refresh();
    goTo('orders', false);
    showToast(`${item.name} ordered successfully.`);
  } catch (error) {
    showToast(error.message || 'Could not reach the server.');
  }
}

function confirmBuy(id, quantity = 1) {
  const item = itemById(id);
  if (!item) { showToast('This menu item is no longer available.'); return; }
  askConfirmation({
    title: 'Confirm this item',
    message: `Order ${quantity} ${item.name} for ${money(item.price * quantity)}? This places an order for this item only.`,
    buttonText: 'Yes, buy this item',
    onConfirm: () => buyNow(id, quantity),
  });
}

async function cancelPendingOrder(number) {
  const currentOrder = orders.find(order => String(order.number) === String(number));
  if (!currentOrder || canonicalOrderStatus(currentOrder.status) !== 'Pending') {
    showToast('This order can no longer be cancelled.');
    return;
  }
  try {
    const response = await fetch('includes/api.php?resource=orders', {
      method: 'DELETE', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ number }),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'This order could not be cancelled.');
    orders = orders.map(order => order.number === number ? { ...order, status: 'Cancelled' } : order);
    trackOrderUpdates(orders, false);
    renderOrders();
    showToast(`Order ${number} cancelled.`);
  } catch (error) {
    showToast(error.message || 'Could not reach the server.');
  }
}

async function placeOrder(confirmedDetails = false) {
  if (menuPreviewOnly) { showToast('Ordering is unavailable while the database is offline.'); return; }
  if (window.storeOpen === false) { showToast('The store is closed and cannot accept orders right now.'); return; }
  if (!cart.length) return;
  if (!requireDeliveryAddress()) return;
  if (!confirmedDetails) {
    window.location.href = 'customer/order-details.php';
    return;
  }
  const button = $('#checkout-button');
  if (button) button.disabled = true;
  try {
    let customerLocation = null;
    if (window.isSecureContext && navigator.geolocation) {
      try {
        showToast('Allow location access to match your order with the closest rider. You can decline.');
        const position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, maximumAge: 60000, timeout: 5000 }));
        customerLocation = { latitude: position.coords.latitude, longitude: position.coords.longitude };
      } catch { /* Rider assignment falls back to availability and workload when location is unavailable. */ }
    }
    const response = await fetch('includes/api.php?resource=orders', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: cart.map(row => ({ id: row.id, quantity: row.quantity })), customerLocation }),
    });
    const result = await response.json();
    if (response.status === 401) { window.location.href = 'customer/login.php?next=products'; return; }
    if (response.status === 422 && /delivery address/i.test(result.error || '')) { window.location.href = 'customer/settings.php?section=address'; return; }
    if (!response.ok) throw new Error(result.error || 'Order could not be placed.');
    orders.unshift(result);
    trackOrderUpdates(orders);
    markOrderHistoryUnread(result);
    cart = [];
    refresh();
    goTo('orders', false);
    showToast('Your order is in! Salamat for choosing MazSen.');
  } catch (error) {
    showToast(error.message || 'Could not reach the server.');
    if (button) button.disabled = false;
  }
}

const profileOverlay = $('#profile-overlay');
const profileOpenButton = $('#profile-open');
const profileCloseButton = $('#profile-close');
let profileCloseTimer = null;
function closeProfileOverlay() {
  if (!profileOverlay) return;
  profileOverlay.classList.remove('is-open');
  profileOverlay.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('profile-modal-open');
  clearTimeout(profileCloseTimer);
  profileCloseTimer = window.setTimeout(() => { profileOverlay.hidden = true; }, 1000);
  profileOpenButton?.focus();
}
profileOpenButton?.addEventListener('click', () => {
  if (!profileOverlay) return;
  clearTimeout(profileCloseTimer);
  profileOverlay.hidden = false;
  profileOverlay.setAttribute('aria-hidden', 'false');
  document.body.classList.add('profile-modal-open');
  requestAnimationFrame(() => profileOverlay.classList.add('is-open'));
  profileCloseButton?.focus();
});
profileCloseButton?.addEventListener('click', closeProfileOverlay);
profileOverlay?.addEventListener('click', event => {
  if (event.target === profileOverlay) closeProfileOverlay();
  else if (event.target.closest('[data-go]')) closeProfileOverlay();
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && profileOverlay?.classList.contains('is-open')) closeProfileOverlay();
});

document.addEventListener('click', event => {
  if (event.target.closest('#order-detail-close')) { closeMobileOrderDrawer(); return; }
  if (event.target === $('#order-detail-backdrop')) { closeMobileOrderDrawer(); return; }
  const orderTrigger = event.target.closest('[data-open-order]');
  if (orderTrigger) { openMobileOrderDrawer(orderTrigger.closest('.order-card')); return; }
  if (event.target.closest('#food-detail-close')) { closeFoodDetails(); return; }
  if (event.target === $('#food-detail-backdrop')) { closeFoodDetails(); return; }

  const favoriteButton = event.target.closest('[data-favorite]');
  if (favoriteButton) { toggleFavorite(favoriteButton.dataset.favorite); return; }

  const addButton = event.target.closest('[data-add]');
  if (addButton) {
    if (!window.customerSignedIn) {
      window.location.href = 'customer/login.php?next=products';
      return;
    }
    const foodPicture = addButton.closest('.food-card')?.querySelector('.food-art') || addButton.closest('.food-detail-layout')?.querySelector('.food-detail-art');
    const startRect = (foodPicture || addButton).getBoundingClientRect();
    const pictureClone = foodPicture?.cloneNode(true) || null;
    pictureClone?.querySelectorAll('button').forEach(button => button.remove());
    if (pictureClone && foodPicture) {
      const computed = getComputedStyle(foodPicture);
      Object.assign(pictureClone.style, { background: computed.background, borderRadius: computed.borderRadius });
    }
    const start = { left: startRect.left, top: startRect.top, width: startRect.width, height: startRect.height, foodPicture: pictureClone };
    const quantity = addButton.closest('.food-detail-layout') ? foodDetailQuantity : 1;
    closeFoodDetails(); addToCart(addButton.dataset.add, start, quantity); return;
  }

  const buyButton = event.target.closest('[data-buy]');
  if (buyButton) { closeFoodDetails(); confirmBuy(buyButton.dataset.buy, Number(buyButton.dataset.buyQuantity) || 1); return; }

  const cancelButton = event.target.closest('[data-cancel-order]');
  if (cancelButton) {
    const number = cancelButton.dataset.cancelOrder;
    const order = orders.find(item => String(item.number) === String(number));
    if (!order || canonicalOrderStatus(order.status) !== 'Pending') {
      showToast(canonicalOrderStatus(order?.status || '') === 'On the way'
        ? 'This order can no longer be cancelled because it is on the way.'
        : 'This order can no longer be cancelled.');
      return;
    }
    const showCancelConfirmation = () => askConfirmation({
      title: 'Cancel this order?',
      message: `Order ${number} is still pending. Do you want to cancel it?`,
      buttonText: 'Yes, cancel order',
      onConfirm: () => cancelPendingOrder(number),
    });
    if (activeMobileOrderCard) {
      closeMobileOrderDrawer();
      window.setTimeout(showCancelConfirmation, 750);
    } else showCancelConfirmation();
    return;
  }

  const categoryButton = event.target.closest('[data-category]');
  if (categoryButton) {
    activeCategory = categoryButton.dataset.category;
    $$('[data-category]').forEach(button => {
      const isActive = button.dataset.category === activeCategory;
      button.classList.toggle('active', isActive);
      button.setAttribute('aria-pressed', String(isActive));
    });
    renderMenu(true);
    return;
  }

  const dashboardCategory = event.target.closest('[data-dashboard-category]');
  if (dashboardCategory) {
    activeCategory = dashboardCategory.dataset.dashboardCategory;
    renderCategories();
    renderMenu();
    goTo('products');
    return;
  }

  const foodCard = event.target.closest('[data-food-detail]');
  if (foodCard) {
    if (foodCard.closest('#dashboard-view') && window.matchMedia('(max-width: 700px)').matches) {
      const selectedFood = itemById(foodCard.dataset.foodDetail);
      const query = new URLSearchParams();
      if (selectedFood?.category) query.set('category', selectedFood.category);
      const queryString = query.toString();
      window.location.assign(`menu.php${queryString ? `?${queryString}` : ''}`);
      return;
    }
    openFoodDetails(foodCard.dataset.foodDetail, foodCard, true);
    return;
  }

  const detailQuantityButton = event.target.closest('[data-food-detail-quantity]');
  if (detailQuantityButton) {
    foodDetailQuantity = Math.max(1, Math.min(99, foodDetailQuantity + Number(detailQuantityButton.dataset.change)));
    const item = itemById($('#food-detail-backdrop').dataset.foodId);
    $('#food-detail-quantity-value').textContent = String(foodDetailQuantity);
    const total = item ? money(item.price * foodDetailQuantity) : '';
    const addAction = $('#food-detail-content [data-add]');
    const buyAction = $('#food-detail-content [data-buy]');
    if (addAction) addAction.querySelector('span').textContent = `Add to cart · ${total}`;
    if (buyAction) { buyAction.dataset.buyQuantity = String(foodDetailQuantity); buyAction.textContent = `Order now · ${total}`; }
    return;
  }

  const quantityButton = event.target.closest('[data-quantity]');
  if (quantityButton) { changeQuantity(quantityButton.dataset.quantity, Number(quantityButton.dataset.change)); return; }

  const goButton = event.target.closest('[data-go], [data-view]');
  if (goButton) {
    event.preventDefault();
    const goView = goButton.dataset.go || goButton.dataset.view;
    if (goView === 'products') {
      window.location.assign('menu.php');
      return;
    }
    goTo(goView);
    if (goButton.dataset.trackOrderNumber) window.setTimeout(() => document.querySelector(`#orders-list [data-order-number="${CSS.escape(goButton.dataset.trackOrderNumber)}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 90);
    return;
  }

  if (event.target.closest('#mobile-nav-cart, #cart-jump-mobile')) { openCart(); return; }
  if (event.target.closest('#cart-jump')) { openCart(); return; }
  if (event.target.closest('#cart-close, #cart-backdrop')) { closeCart(); return; }
  if (event.target.closest('#checkout-button')) { placeOrder(); return; }
  if (event.target.closest('#clear-cart')) { cart = []; refresh(); showToast('Cart cleared'); }
  const removeButton = event.target.closest('[data-remove]');
  if (removeButton) { cart = cart.filter(row => row.id !== removeButton.dataset.remove); refresh(); }
});

document.addEventListener('keydown', event => {
  const panel = $('#cart-panel');
  if (!panel?.classList.contains('is-open')) return;
  if (event.key === 'Escape') { closeCart(); return; }
  if (event.key === 'Tab') {
    const focusable = [...panel.querySelectorAll('button:not([disabled]), a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])')];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }
});

document.addEventListener('keydown', event => {
  const modal = $('#food-detail-backdrop');
  if (!modal?.classList.contains('is-open')) return;
  if (event.key === 'Escape') { closeFoodDetails(); return; }
  if (event.key === 'Tab') {
    const focusable = [...modal.querySelectorAll('button:not([disabled])')];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }
});

document.addEventListener('keydown', event => {
  const foodCard = event.target.closest?.('[data-food-detail]');
  if (!foodCard || event.target !== foodCard || !['Enter', ' '].includes(event.key)) return;
  event.preventDefault();
  openFoodDetails(foodCard.dataset.foodDetail, foodCard, true);
});

document.addEventListener('keydown', event => {
  const modal = $('#confirm-backdrop');
  if (!modal.classList.contains('is-open')) return;
  if (event.key === 'Escape') { closeConfirmation(); return; }
  if (event.key === 'Tab') {
    const first = $('#confirm-cancel');
    const last = $('#confirm-accept');
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }
});

$('#confirm-cancel').addEventListener('click', () => closeConfirmation());
$('#confirm-accept').addEventListener('click', () => closeConfirmation(true));
$('#confirm-backdrop').addEventListener('click', event => {
  if (event.target === event.currentTarget) closeConfirmation();
});

$('#search-input').addEventListener('input', event => { searchTerm = event.target.value.trim(); renderMenu(); });
$('#dashboard-search-input')?.addEventListener('focus', () => {
  const menuSearch = $('#search-input');
  if (!menuSearch) return;
  menuSearch.value = searchTerm;
  goTo('products');
  requestAnimationFrame(() => menuSearch.focus());
});

async function startApp() {
  updateWelcomeGreeting();
  const [productsResponse, storeResponse] = await Promise.all([fetch('includes/api.php?resource=products'), fetch('includes/api.php?resource=store', { cache: 'no-store' })]);
  let menuLoadError = '';
  if (productsResponse.ok) {
    menuItems = await productsResponse.json();
    if (!Array.isArray(menuItems) || !menuItems.length) { menuItems = fallbackMenu; menuPreviewOnly = true; menuLoadError = 'The menu API returned no products.'; }
  } else {
    menuItems = fallbackMenu;
    menuPreviewOnly = true;
    try { menuLoadError = (await productsResponse.json()).error || `Menu request failed (${productsResponse.status}).`; }
    catch { menuLoadError = `Menu request failed (${productsResponse.status}).`; }
  }
  favorites = favorites.filter(id => menuItems.some(item => item.id === id));
  const requestedCategory = new URLSearchParams(location.search).get('category');
  if (requestedCategory && menuItems.some(item => item.category === requestedCategory)) activeCategory = requestedCategory;
  if (storeResponse.ok) window.storeOpen = (await storeResponse.json()).is_open === true;
  cart = cart.filter(row => menuItems.some(item => item.id === row.id) && Number.isInteger(row.quantity) && row.quantity > 0);
  if (window.customerSignedIn) {
    const ordersResponse = await fetch('includes/api.php?resource=orders');
    if (ordersResponse.ok) orders = await ordersResponse.json();
    window.setInterval(refreshCustomerOrders, 12000);
  }
  initializeOrderAlerts();
  renderPopular();
  renderCategories();
  renderDashboardCategories();
  renderMenu();
  renderCart();
  renderOrders();
  renderFavorites();
  renderAccountViews();
  updateStoreStatus();
  if (menuPreviewOnly) {
    const notice = document.createElement('div');
    notice.className = 'menu-offline-notice';
    notice.setAttribute('role', 'alert');
    notice.textContent = `Ordering is unavailable because the menu could not load from the database. ${menuLoadError || 'Check that MySQL is running and the database is configured.'}`;
    $('#menu-products')?.before(notice);
    showToast(`Menu preview only: ${menuLoadError || 'database unavailable'}`);
  }
  const initialView = document.body.dataset.initialView || new URLSearchParams(location.search).get('view') || location.hash.slice(1);
  if (['dashboard', 'products', 'orders', 'favorites', 'addresses', 'payments', 'notifications', 'rewards'].includes(initialView)) goTo(initialView);
  if (initialView === 'cart') { goTo('dashboard'); openCart(); }
  if (window.pendingBuyNow) {
    const pendingBuy = typeof window.pendingBuyNow === 'string' ? { id: window.pendingBuyNow, quantity: 1 } : window.pendingBuyNow;
    window.pendingBuyNow = null;
    await buyNow(pendingBuy.id, pendingBuy.quantity);
  }
  if (window.checkoutConfirmed) {
    window.checkoutConfirmed = false;
    history.replaceState(null, '', `${location.pathname}${location.hash}`);
    await placeOrder(true);
  }
  window.setInterval(refreshStoreStatus, 60000);
}

function updateWelcomeGreeting() {
  const greeting = $('#welcome-greeting');
  if (!greeting) return;
  const hour = new Date().getHours();
  const timeGreeting = hour < 12 ? 'Good day' : hour < 17 ? 'Good afternoon' : 'Good evening';
  const firstName = greeting.dataset.firstName?.trim();
  greeting.textContent = firstName ? `${timeGreeting}, ${firstName}` : timeGreeting;
}

async function refreshCustomerOrders() {
  if (!window.customerSignedIn) return;
  try {
    const response = await fetch('includes/api.php?resource=orders', { cache: 'no-store' });
    if (!response.ok) return;
    const updatedOrders = await response.json();
    const statusChanged = updatedOrders.length !== orders.length || updatedOrders.some((order, index) => order.number !== orders[index]?.number || order.status !== orders[index]?.status);
    const firstLocation = updatedOrders.some((order, index) => Boolean(order.riderLocation) && !orders[index]?.riderLocation);
    const hasChanges = statusChanged || firstLocation;
    if (hasChanges) {
      trackOrderUpdates(updatedOrders);
      orders = updatedOrders;
      renderOrders();
    } else {
      orders = updatedOrders;
      syncRiderMaps();
    }
  } catch { /* Keep the latest known progress while offline. */ }
}

function updateStoreStatus() {
  const isOpen = window.storeOpen !== false;
  const pill = $('#store-open-pill');
  if (pill) pill.classList.toggle('is-closed', !isOpen);
  const label = $('#store-status-text');
  if (label) label.textContent = isOpen ? 'Store open' : 'Store closed';
  const status = $('#customer-store-status');
  if (status) status.textContent = isOpen ? 'Open' : 'Closed';
  const visitStatus = $('.visit-store-status');
  if (visitStatus) visitStatus.classList.toggle('is-closed', !isOpen);
  const note = $('#customer-store-note');
  if (note) note.textContent = isOpen ? 'Taking pickup orders' : 'Not accepting new orders';
  const sidebarLabel = $('#sidebar-store-label');
  if (sidebarLabel) sidebarLabel.textContent = isOpen ? 'Now accepting orders' : 'Store is closed';
  const sidebarNote = $('#sidebar-store-note');
  if (sidebarNote) sidebarNote.textContent = isOpen ? 'Pickup and delivery' : 'Not accepting orders';
  const sidebarDot = $('#sidebar-store-dot');
  if (sidebarDot) sidebarDot.classList.toggle('is-closed', !isOpen);
}

async function refreshStoreStatus() {
  try {
    const response = await fetch('includes/api.php?resource=store', { cache: 'no-store' });
    if (!response.ok) return;
    const store = await response.json();
    const wasOpen = window.storeOpen !== false;
    window.storeOpen = store.is_open === true;
    updateStoreStatus();
    if (wasOpen !== window.storeOpen) {
      renderPopular(); renderMenu(); renderCart();
      if (!window.storeOpen) showToast('The store is now closed to new orders.');
    }
  } catch { /* Keep last known state while offline. */ }
}

document.addEventListener('click', event => {
  document.querySelectorAll('.account-menu[open]').forEach(menu => {
    if (!menu.contains(event.target)) menu.removeAttribute('open');
  });
});

document.addEventListener('keydown', event => {
  if (event.key !== 'Escape') return;
  document.querySelectorAll('.account-menu[open]').forEach(menu => {
    menu.removeAttribute('open');
    menu.querySelector('summary')?.focus();
  });
});

startApp().catch(error => showToast(error.message));
