let menuItems = [];
/* Menu data is loaded from MySQL through includes/api.php. */
/*
  { id: 'classic-burger', name: 'Classic Burger', category: 'Burgers', price: 55, emoji: '🍔', description: 'Juicy patty, fresh lettuce, tomato and our house sauce.', popular: true },
  { id: 'omg-overload', name: 'OMG Overload', category: 'Burgers', price: 95, emoji: '🍔', description: 'Burger, ham, egg, mayo, ketchup and TLC.', popular: true },
  { id: 'classic-hotdog', name: 'Classic Hotdog Sandwich', category: 'Burgers', price: 75, emoji: '🌭', description: 'TJ jumbo, mayo, ketchup and crisp coleslaw.', popular: true },
  { id: 'loaded-footlong', name: 'Loaded Footlong', category: 'Burgers', price: 265, emoji: '🌭', description: 'Footlong with burger, egg, mayo, cheese and TLC.', popular: false },
  { id: 'chicken-poppers-rice', name: 'Chicken Poppers Rice', category: 'Silog Meals', price: 95, emoji: '🍗', description: 'Crispy chicken poppers, rice and your choice of flavor.', popular: true },
  { id: 'hamsilog', name: 'Hamsilog', category: 'Silog Meals', price: 80, emoji: '🍳', description: 'Ham, garlic rice and a sunny side up egg.', popular: true },
  { id: 'pork-tapsilog', name: 'Pork Tapsilog', category: 'Silog Meals', price: 85, emoji: '🍳', description: 'Savory pork tapa with garlic rice and egg.', popular: false },
  { id: 'longganisilog', name: 'Longganisilog', category: 'Silog Meals', price: 95, emoji: '🍳', description: 'Filipino longganisa, rice and egg, all in one plate.', popular: false },
  { id: 'chick-pop-n-fries', name: "Chick Pop 'n Fries", category: 'Snacks', price: 99, emoji: '🍟', description: 'Crispy chicken poppers over fries, drizzled with garlic mayo.', popular: true },
  { id: 'cheese-sticks', name: 'Cheese Sticks', category: 'Snacks', price: 50, emoji: '🧀', description: 'Nine golden, crunchy cheese sticks.', popular: false },
  { id: 'dumplings', name: 'Dumplings', category: 'Snacks', price: 50, emoji: '🥟', description: 'Nine pieces, served hot and ready to share.', popular: false },
  { id: 'dynamite', name: 'Dynamite', category: 'Snacks', price: 55, emoji: '🌶️', description: 'Three crunchy, cheesy chili poppers.', popular: false },
  { id: 'regular-fries', name: 'Regular Fries', category: 'Fries', price: 30, emoji: '🍟', description: 'Golden fries. Add cheese, BBQ or sour cream flavor.', popular: true },
  { id: 'medium-fries', name: 'Medium Fries', category: 'Fries', price: 65, emoji: '🍟', description: 'A bigger serving of golden, crispy fries.', popular: false },
  { id: 'large-fries', name: 'Large Fries', category: 'Fries', price: 95, emoji: '🍟', description: 'Our biggest fries for sharing or keeping.', popular: false },
  { id: 'classic-hungarian', name: 'Classic Hungarian Sandwich', category: 'Hungarian & Footlong', price: 65, emoji: '🌭', description: 'Hungarian sausage with mayo, ketchup and coleslaw.', popular: false },
  { id: 'cheesy-footlong', name: 'Cheesy Footlong Sandwich', category: 'Hungarian & Footlong', price: 155, emoji: '🌭', description: 'A jumbo footlong with a cheesy, savory finish.', popular: false },
  { id: 'classic-sandwich', name: 'Ham Sandwich', category: 'Burgers', price: 55, emoji: '🥪', description: 'Ham, mayo, ketchup and coleslaw.', popular: false },
  { id: 'plain-rice', name: 'Plain Rice', category: 'Add-ons', price: 15, emoji: '🍚', description: 'A warm serving of steamed white rice.', popular: false },
  { id: 'garlic-rice', name: 'Garlic Rice', category: 'Add-ons', price: 20, emoji: '🍚', description: 'Savory garlic fried rice.', popular: false },
  { id: 'egg', name: 'Egg', category: 'Add-ons', price: 15, emoji: '🍳', description: 'Add a freshly cooked egg to your meal.', popular: false },
  { id: 'burger-patty', name: 'Burger Patty', category: 'Add-ons', price: 20, emoji: '🍔', description: 'Add an extra burger patty to your order.', popular: false },
*/

const money = amount => `₱${Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const readStore = (key, fallback) => {
  try { return JSON.parse(localStorage.getItem(key)) ?? fallback; }
  catch { return fallback; }
};
let cart = readStore('mazsen-cart-v1', []);
if (!Array.isArray(cart)) cart = [];
let favorites = readStore('mazsen-favorites-v1', []);
if (!Array.isArray(favorites)) favorites = [];
let orders = [];
let activeCategory = 'All';
let searchTerm = '';
let toastTimer;
let lastCartFocus = null;
let pendingConfirmation = null;
let lastConfirmationFocus = null;
let viewAnimationTimer = null;
let foodDetailReturnFocus = null;
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
  const favoritesBadge = $('#favorite-count');
  if (favoritesBadge) {
    favoritesBadge.classList.remove('has-notification');
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

const sidebarCollapse = $('#sidebar-collapse');
if (sidebarCollapse) {
  const tabletNav = window.matchMedia('(min-width: 561px) and (max-width: 820px)');
  document.body.classList.toggle('customer-sidebar-collapsed', localStorage.getItem('mazsen-sidebar-collapsed') === 'true');
  document.body.classList.toggle('customer-sidebar-expanded', tabletNav.matches && localStorage.getItem('mazsen-tablet-sidebar-expanded') === 'true');
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
  localStorage.setItem('mazsen-cart-v1', JSON.stringify(cart));
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

function productCard(item, showOrderActions = true) {
  const unavailable = window.storeOpen === false;
  const favorite = favorites.includes(item.id);
  return `<article class="food-card" data-food-detail="${escapeHTML(item.id)}" tabindex="0" aria-haspopup="dialog" aria-label="View details for ${escapeHTML(item.name)}">
    <div class="food-art"><span class="food-emoji" aria-hidden="true">${item.emoji}</span><button class="favorite-button ${favorite ? 'is-favorite' : ''}" type="button" data-favorite="${item.id}" aria-pressed="${favorite}" aria-label="${favorite ? 'Remove from' : 'Add to'} favorites">${favorite ? '♥' : '♡'}</button></div>
    <div class="food-body">
      <div class="food-row"><span class="food-name">${item.name}</span><span class="food-price">${money(item.price)}</span></div>
      <p class="food-desc">${item.description}</p>
      ${showOrderActions ? `<div class="food-actions"><button class="add-button" data-add="${item.id}" aria-label="Add ${item.name} to order" ${unavailable ? 'disabled' : ''}><span>＋</span><span class="add-button-label">Add to order</span></button><button class="buy-button" data-buy="${item.id}" ${unavailable ? 'disabled' : ''}>${unavailable ? 'Closed' : 'Buy'}</button></div>` : ''}
    </div>
  </article>`;
}

function openFoodDetails(id, returnFocus = null) {
  const item = itemById(id);
  if (!item) return;
  if (returnFocus) foodDetailReturnFocus = returnFocus;
  const unavailable = window.storeOpen === false;
  $('#food-detail-content').innerHTML = `
    <div class="food-detail-layout">
      <div class="food-detail-art" aria-hidden="true"><span>${escapeHTML(item.emoji)}</span></div>
      <div class="food-detail-copy">
        <span class="food-detail-category">${escapeHTML(item.category)}</span>
        <div class="food-detail-heading"><h2 id="food-detail-title">${escapeHTML(item.name)}</h2><strong>${money(item.price)}</strong></div>
        <section class="food-detail-description"><h3>What's in it</h3><p>${escapeHTML(item.description)}</p></section>
        <div class="food-detail-actions"><button class="add-button" type="button" data-add="${escapeHTML(item.id)}" ${unavailable ? 'disabled' : ''}><span>＋</span><span>Add to order</span></button><button class="buy-button" type="button" data-buy="${escapeHTML(item.id)}" ${unavailable ? 'disabled' : ''}>${unavailable ? 'Closed' : 'Buy now'}</button></div>
      </div>
    </div>`;
  const modal = $('#food-detail-backdrop');
  modal.dataset.foodId = item.id;
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
  $('#popular-products').innerHTML = menuItems.filter(item => item.popular).slice(0, 4).map(item => productCard(item, false)).join('');
}

function renderCategories() {
  const categories = ['All', ...new Set(menuItems.map(item => item.category))];
  $('#category-list').innerHTML = categories.map(category => `<button class="category-button ${category === activeCategory ? 'active' : ''}" data-category="${category}" aria-pressed="${category === activeCategory}">${category}</button>`).join('');
}

function renderMenu(animate = false) {
  const filtered = menuItems.filter(item => {
    const matchesCategory = activeCategory === 'All' || item.category === activeCategory;
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
    : '<div class="empty-state account-empty"><span class="empty-icon">♡</span><strong>No favorites saved yet</strong><p>Tap the heart on a menu item to save it here.</p><button class="outline-button" data-go="products">Explore the menu</button></div>';
  const favoriteBadge = $('#favorite-count');
  favoriteBadge.textContent = '';
  favoriteBadge.setAttribute('aria-label', `${favorites.length} saved favorites`);
}

function toggleFavorite(id) {
  favorites = favorites.includes(id) ? favorites.filter(itemId => itemId !== id) : [...favorites, id];
  localStorage.setItem('mazsen-favorites-v1', JSON.stringify(favorites));
  renderPopular(); renderMenu(); renderFavorites();
  showToast(favorites.includes(id) ? 'Added to your favorites.' : 'Removed from your favorites.');
}

function escapeHTML(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]);
}

function renderAccountViews() {
  const profile = window.customerProfile;
  $('#address-content').innerHTML = profile
    ? `<article class="account-info-card"><span class="account-info-icon">⌖</span><div><span class="account-info-label">DEFAULT DELIVERY ADDRESS</span><h2>${escapeHTML(profile.full_name)}</h2><p>${escapeHTML(profile.address)}</p><small>${escapeHTML(profile.phone)}</small></div><a class="outline-button" href="customer/settings.php">Edit</a></article>`
    : '<div class="account-info-card"><div><h2>Sign in to view your address</h2><p>Your saved delivery details will appear here.</p></div><a class="outline-button" href="customer/login.php">Sign in</a></div>';
  $('#payment-content').innerHTML = `<article class="account-info-card"><span class="account-info-icon">▣</span><div><span class="account-info-label">AVAILABLE PAYMENT METHOD</span><h2>Cash on delivery</h2><p>Pay the rider when your order arrives. No card details are stored by MazSen.</p></div><span class="payment-method-badge">Available</span></article>`;
  renderNotifications();
}

function renderNotifications() {
  const container = $('#notification-content');
  if (!container) return;
  if (!orders.length) {
    container.innerHTML = '<div class="empty-state account-empty"><span class="empty-icon">♧</span><strong>No order updates yet</strong><p>Updates will appear here after you place an order.</p><button class="outline-button" data-go="products">Browse menu</button></div>';
    return;
  }
  container.innerHTML = `<div class="account-notification-list">${orders.slice(0, 12).map(order => `<article class="account-notification"><span class="notification-dot ${['Delivered','Completed'].includes(canonicalOrderStatus(order.status)) ? 'is-done' : ''}"></span><div><strong>Order ${escapeHTML(order.number)} · ${escapeHTML(canonicalOrderStatus(order.status))}</strong><p>${canonicalOrderStatus(order.status) === 'On the way' ? 'Your delivery rider is on the way.' : canonicalOrderStatus(order.status) === 'Preparing' ? 'The kitchen is preparing your order.' : canonicalOrderStatus(order.status) === 'Delivered' ? 'Your order has been delivered.' : canonicalOrderStatus(order.status) === 'Cancelled' ? 'This order was cancelled.' : 'Your order is being processed.'}</p><small>${new Date(order.createdAt).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}</small></div><button class="text-button" data-go="orders">View</button></article>`).join('')}</div>`;
}

function renderCart() {
  const quantity = cartQuantity();
  const total = cartTotal();
  $('#cart-badge').textContent = quantity;
  $('#cart-total-label').textContent = money(total);
  $('#cart-title-count').textContent = `(${quantity})`;
  const itemsStat = $('#stat-items');
  if (itemsStat) itemsStat.textContent = quantity;
  $('#cart-content').innerHTML = cart.length ? `
    <div class="cart-lines">${cart.map(row => {
      const item = itemById(row.id);
      return `<div class="cart-item">
        <span class="cart-item-emoji">${item.emoji}</span>
        <span class="cart-item-name">${item.name}<small>${money(item.price)} each · ${money(item.price * row.quantity)} total</small></span>
        <span class="quantity-control"><button data-quantity="${item.id}" data-change="-1" aria-label="Remove one ${item.name}">−</button><span>${row.quantity}</span><button data-quantity="${item.id}" data-change="1" aria-label="Add one ${item.name}">＋</button></span>
        <button class="cart-remove" data-remove="${item.id}" aria-label="Remove ${item.name}" title="Remove item">×</button>
      </div>`;
    }).join('')}</div>
    <div class="cart-bottom"><span class="cart-note">${window.storeOpen === false ? 'The store is closed and cannot accept orders right now.' : window.customerSignedIn ? 'Delivery order · Pay upon delivery' : 'Sign in or create an account to place your order.'}</span><div class="cart-total-row"><span>Total payment</span><strong>${money(total)}</strong></div><button class="primary-button checkout-button" id="checkout-button" ${window.storeOpen === false ? 'disabled' : ''}>${window.storeOpen === false ? 'Store is closed' : window.customerSignedIn ? 'Place order' : 'Sign in to order'} <span>→</span></button></div>`
    : '<div class="cart-empty">Your cart is empty. Add a favorite from the menu to get started.</div>';
  const ordersStat = $('#stat-orders');
  if (ordersStat) ordersStat.textContent = orders.length;
  renderNotifications();
}

function openCart() {
  const panel = $('#cart-panel');
  if (!panel.classList.contains('is-open')) lastCartFocus = document.activeElement;
  panel.classList.add('is-open');
  $('#cart-backdrop').classList.add('is-open');
  panel.setAttribute('aria-hidden', 'false');
  $('#cart-jump').setAttribute('aria-expanded', 'true');
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
  document.body.classList.remove('cart-open');
  if (wasOpen && lastCartFocus?.isConnected) lastCartFocus.focus();
}

const ORDER_STEPS = ['Pending', 'Confirmed', 'Preparing', 'On the way', 'Delivered'];

function canonicalOrderStatus(status) {
  return ({ Received: 'Pending', Processing: 'Preparing', Ready: 'On the way', Completed: 'Delivered' })[status] || status;
}

function renderOrderProgress(status) {
  const current = canonicalOrderStatus(status);
  if (current === 'Cancelled') return '<div class="order-progress-cancelled">This order was cancelled.</div>';
  const activeIndex = ORDER_STEPS.indexOf(current);
  return `<div class="order-progress" aria-label="Order progress: ${current}"><div class="order-progress-steps">${ORDER_STEPS.map((step, index) => `<div class="order-progress-step ${index < activeIndex ? 'is-complete' : ''} ${index === activeIndex ? 'is-current' : ''}"><span class="order-progress-marker">${index < activeIndex ? '✓' : index + 1}</span><span class="order-progress-label">${step}</span></div>`).join('')}</div></div>`;
}

function renderOrders() {
  const orderCount = orders.length;
  $('#history-count').textContent = `${orderCount} ${orderCount === 1 ? 'order' : 'orders'}`;
  const orderMarkup = orders.map(order => `
    <article class="order-card">
      <div class="order-head"><div><span class="order-number">${order.number}</span><span class="order-date">${new Date(order.createdAt).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}</span></div><span class="order-status">${canonicalOrderStatus(order.status)}</span></div>
      ${renderOrderProgress(order.status)}
      <div class="order-lines">${order.items.map(row => { const item = itemById(row.id); return `<span class="order-line-item"><span class="order-line-image" aria-hidden="true">${escapeHTML(item?.emoji ?? '🍽️')}</span><span class="order-line-copy"><strong>${row.quantity} × ${escapeHTML(item?.name ?? row.name ?? 'Menu item')}</strong></span></span>`; }).join('')}</div>
      <div class="order-bottom"><span>Delivery order</span><strong>${money(order.total)}</strong></div>
    </article>`).join('');
  $('#orders-list').innerHTML = orderCount ? orderMarkup : '<div class="empty-state"><span class="empty-icon">▤</span><strong>Nothing on your order history yet</strong><p>When you place an order, it will be saved here on this device.</p><button class="outline-button" data-go="products">Browse the menu</button></div>';

  $$('#orders-list .order-card').forEach((card, index) => {
    const canonicalStatus = canonicalOrderStatus(orders[index].status);
    card.querySelector('.order-status')?.classList.add(`order-status-${canonicalStatus.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`);
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

  const recent = orders.slice(0, 2);
  $('#recent-orders').innerHTML = recent.length ? recent.map(order => `<div class="order-card"><div class="order-head"><div><span class="order-number">${order.number}</span><span class="order-date">${new Date(order.createdAt).toLocaleDateString('en-PH', { dateStyle: 'medium' })}</span></div><span class="order-status">${order.status}</span></div><div class="order-bottom"><span>${order.items.reduce((sum, row) => sum + row.quantity, 0)} items</span><strong>${money(order.total)}</strong></div></div>`).join('') : '<div class="empty-state compact-empty"><span class="empty-icon">▤</span><strong>No orders yet</strong><p>Your placed orders will show up here.</p><button class="outline-button" data-go="products">Explore the menu</button></div>';
  renderNotifications();
}

function refresh() {
  persist();
  renderCart();
  renderOrders();
}

function goTo(view) {
  const views = ['dashboard', 'products', 'orders', 'favorites', 'addresses', 'payments', 'notifications', 'rewards'];
  const target = views.includes(view) ? view : 'dashboard';
  markOrderAlertsSeen(target);
  if (target !== 'products') closeCart();
  const targetSection = $(`#${target}-view`);
  $$('.page-content').forEach(section => {
    section.classList.remove('view-entering');
    if (section !== targetSection) section.classList.add('hidden');
  });
  targetSection.classList.remove('hidden');
  void targetSection.offsetWidth;
  targetSection.classList.add('view-entering');
  clearTimeout(viewAnimationTimer);
  viewAnimationTimer = setTimeout(() => targetSection.classList.remove('view-entering'), 720);
  $$('.nav-link').forEach(link => link.classList.toggle('active', link.dataset.view === target));
  const activeLink = $(`.nav-link[data-view="${target}"]`);
  if (activeLink && window.matchMedia('(max-width: 560px)').matches) {
    activeLink.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest', inline: 'center' });
  }
  const labels = { dashboard:'Dashboard', products:'Order Menu', orders:'My Orders', favorites:'Favorites', addresses:'Addresses', payments:'Payment Methods', notifications:'Notifications', rewards:'Rewards & Offers' };
  $('#page-label').textContent = labels[target];
  if (target === 'products') { renderCategories(); renderMenu(); }
  if (target === 'orders') renderOrders();
  if (target === 'favorites') renderFavorites();
  if (target === 'notifications') renderNotifications();
  if (target === 'addresses' || target === 'payments') renderAccountViews();
  history.replaceState(null, '', `#${target}`);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function addToCart(id) {
  if (window.storeOpen === false) { showToast('The store is closed and cannot accept orders right now.'); return; }
  const existing = cart.find(row => row.id === id);
  if (existing) existing.quantity += 1;
  else cart.push({ id, quantity: 1 });
  refresh();
  openCart();
  showToast(`${itemById(id).name} added to your order`);
}

function changeQuantity(id, amount) {
  const row = cart.find(item => item.id === id);
  if (!row) return;
  row.quantity += amount;
  if (row.quantity < 1) cart = cart.filter(item => item.id !== id);
  refresh();
}

async function buyNow(id) {
  if (window.storeOpen === false) { showToast('The store is closed and cannot accept orders right now.'); return; }
  const item = itemById(id);
  if (!item) { showToast('This menu item is no longer available.'); return; }
  if (!window.customerSignedIn) {
    window.location.href = `customer/login.php?next=products&buy=${encodeURIComponent(id)}`;
    return;
  }
  try {
    const response = await fetch('includes/api.php?resource=orders', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: [{ id, quantity: 1 }] }),
    });
    const result = await response.json();
    if (response.status === 401) {
      window.location.href = `customer/login.php?next=products&buy=${encodeURIComponent(id)}`;
      return;
    }
    if (!response.ok) throw new Error(result.error || 'Order could not be placed.');
    orders.unshift(result);
    refresh();
    goTo('orders');
    showToast(`${item.name} ordered successfully.`);
  } catch (error) {
    showToast(error.message || 'Could not reach the server.');
  }
}

function confirmBuy(id) {
  const item = itemById(id);
  if (!item) { showToast('This menu item is no longer available.'); return; }
  askConfirmation({
    title: 'Confirm this item',
    message: `Buy 1 ${item.name} for ${money(item.price)}? This places an order for this item only.`,
    buttonText: 'Yes, buy this item',
    onConfirm: () => buyNow(id),
  });
}

async function cancelPendingOrder(number) {
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

async function placeOrder() {
  if (window.storeOpen === false) { showToast('The store is closed and cannot accept orders right now.'); return; }
  if (!cart.length) return;
  const button = $('#checkout-button');
  if (button) button.disabled = true;
  try {
    const response = await fetch('includes/api.php?resource=orders', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: cart.map(row => ({ id: row.id, quantity: row.quantity })) }),
    });
    const result = await response.json();
    if (response.status === 401) { window.location.href = 'customer/login.php?next=products'; return; }
    if (!response.ok) throw new Error(result.error || 'Order could not be placed.');
    orders.unshift(result);
    trackOrderUpdates(orders);
    cart = [];
    refresh();
    goTo('orders');
    showToast('Your order is in! Salamat for choosing MazSen.');
  } catch (error) {
    showToast(error.message || 'Could not reach the server.');
    if (button) button.disabled = false;
  }
}

document.addEventListener('click', event => {
  if (event.target.closest('#food-detail-close')) { closeFoodDetails(); return; }
  if (event.target === $('#food-detail-backdrop')) { closeFoodDetails(); return; }

  const favoriteButton = event.target.closest('[data-favorite]');
  if (favoriteButton) { toggleFavorite(favoriteButton.dataset.favorite); return; }

  const addButton = event.target.closest('[data-add]');
  if (addButton) { closeFoodDetails(); addToCart(addButton.dataset.add); return; }

  const buyButton = event.target.closest('[data-buy]');
  if (buyButton) { closeFoodDetails(); confirmBuy(buyButton.dataset.buy); return; }

  const cancelButton = event.target.closest('[data-cancel-order]');
  if (cancelButton) {
    const number = cancelButton.dataset.cancelOrder;
    askConfirmation({
      title: 'Cancel this order?',
      message: `Order ${number} is still pending. Do you want to cancel it?`,
      buttonText: 'Yes, cancel order',
      onConfirm: () => cancelPendingOrder(number),
    });
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

  const foodCard = event.target.closest('[data-food-detail]');
  if (foodCard) { openFoodDetails(foodCard.dataset.foodDetail, foodCard); return; }

  const quantityButton = event.target.closest('[data-quantity]');
  if (quantityButton) { changeQuantity(quantityButton.dataset.quantity, Number(quantityButton.dataset.change)); return; }

  const goButton = event.target.closest('[data-go], [data-view]');
  if (goButton) { event.preventDefault(); goTo(goButton.dataset.go || goButton.dataset.view); return; }

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
  openFoodDetails(foodCard.dataset.foodDetail, foodCard);
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

async function startApp() {
  updateWelcomeGreeting();
  const [productsResponse, storeResponse] = await Promise.all([fetch('includes/api.php?resource=products'), fetch('includes/api.php?resource=store', { cache: 'no-store' })]);
  if (!productsResponse.ok) throw new Error('Please start Apache and MySQL in XAMPP and import database/schema.sql.');
  menuItems = await productsResponse.json();
  favorites = favorites.filter(id => menuItems.some(item => item.id === id));
  if (storeResponse.ok) window.storeOpen = (await storeResponse.json()).is_open === true;
  cart = cart.filter(row => menuItems.some(item => item.id === row.id) && Number.isInteger(row.quantity) && row.quantity > 0);
  if (window.customerSignedIn) {
    const ordersResponse = await fetch('includes/api.php?resource=orders');
    if (ordersResponse.ok) orders = await ordersResponse.json();
    window.setInterval(refreshCustomerOrders, 25000);
  }
  initializeOrderAlerts();
  renderPopular();
  renderCategories();
  renderMenu();
  renderCart();
  renderOrders();
  renderFavorites();
  renderAccountViews();
  updateStoreStatus();
  const initialView = location.hash.slice(1);
  if (['dashboard', 'products', 'orders', 'favorites', 'addresses', 'payments', 'notifications', 'rewards'].includes(initialView)) goTo(initialView);
  if (window.pendingBuyNow) {
    const id = window.pendingBuyNow;
    window.pendingBuyNow = null;
    await buyNow(id);
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
    const hasChanges = updatedOrders.length !== orders.length || updatedOrders.some((order, index) => order.number !== orders[index]?.number || order.status !== orders[index]?.status);
    if (hasChanges) {
      trackOrderUpdates(updatedOrders);
      orders = updatedOrders;
      renderOrders();
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
