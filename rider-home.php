<?php
declare(strict_types=1);
require __DIR__ . '/includes/rider_auth.php';
if (rider_user_id() === null) { header('Location: rider-login.php'); exit; }
if (empty($_SESSION['rider_location_csrf'])) $_SESSION['rider_location_csrf'] = bin2hex(random_bytes(32));
$riderName = (string)($_SESSION['mazsen_rider_name'] ?? 'Rider');
$csrf = (string)$_SESSION['rider_location_csrf'];
function rider_home_h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f7f4fa">
  <title>Rider deliveries | MazSen Munch &amp; Sip</title>
  <link rel="stylesheet" href="assets/css/styles.css?v=116">
  <style>
    body{min-height:100vh;margin:0;background:#f7f4fa;color:#292035;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}.rider-shell{width:min(900px,100% - 32px);margin:auto;padding:28px 0 48px}.rider-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}.rider-head h1{margin:0;font-size:26px}.rider-head p{margin:4px 0;color:#746779}.rider-head a{color:#704080}.rider-status{padding:14px;border-radius:12px;background:#f0e8f4;color:#634575}.rider-list{display:grid;gap:12px;margin-top:18px}.rider-card{padding:18px;border:1px solid #e8ddec;border-radius:15px;background:#fff}.rider-card h2{margin:0 0 8px;font-size:17px}.rider-card p{margin:5px 0;color:#675c6c}.rider-actions{display:flex;gap:8px;margin-top:14px}.rider-actions button{padding:9px 13px;border:0;border-radius:9px;background:#78439a;color:white;font-weight:700;cursor:pointer}.rider-actions button.secondary{background:#eee8f1;color:#503c57}.rider-empty{padding:24px;border:1px dashed #d8c9df;border-radius:14px;background:#fff;color:#746779;text-align:center}.rider-error{color:#a23f39}
  </style>
</head>
<body>
  <main class="rider-shell">
    <header class="rider-head"><div><h1>Hello, <?= rider_home_h($riderName) ?></h1><p>Your delivery assignments and offers</p></div><a href="rider-login.php?logout=1">Sign out</a></header>
    <div id="rider-status" class="rider-status" role="status">Loading deliveries…</div>
    <section id="rider-list" class="rider-list" aria-live="polite"></section>
  </main>
  <script>
  const riderCsrf = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const statusBox = document.getElementById('rider-status');
  const list = document.getElementById('rider-list');
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  async function loadDeliveries(){
    try {
      const response = await fetch('includes/api.php?resource=rider-portal-location', {cache:'no-store'});
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Could not load deliveries.');
      statusBox.textContent = data.offers.length ? `${data.offers.length} delivery offer${data.offers.length === 1 ? '' : 's'} waiting for your response.` : 'You are signed in. New assignments will appear here.';
      const offers = data.offers.map(order => `<article class="rider-card"><h2>Delivery offer · ${esc(order.number)}</h2><p>Customer: ${esc(order.customer)}</p><p>Phone: ${esc(order.phone)}</p><p>Address: ${esc(order.address)}</p><p>Estimated payout: ₱${Number(order.payout || 0).toFixed(2)}</p><div class="rider-actions"><button data-action="accept-offer" data-number="${esc(order.number)}">Accept</button><button class="secondary" data-action="decline-offer" data-number="${esc(order.number)}">Decline</button></div></article>`).join('');
      const assigned = data.orders.map(order => `<article class="rider-card"><h2>Order ${esc(order.number)} · ${esc(order.status)}</h2><p>Customer: ${esc(order.customer)}</p><p>Phone: ${esc(order.phone)}</p><p>Address: ${esc(order.address)}</p><p>Total: ₱${Number(order.total || 0).toFixed(2)}</p></article>`).join('');
      list.innerHTML = offers + assigned || '<div class="rider-empty">No active delivery offers or assignments right now.</div>';
    } catch (error) { statusBox.innerHTML = `<span class="rider-error">${esc(error.message)}</span>`; }
  }
  list.addEventListener('click', async event => {
    const button = event.target.closest('[data-action]'); if (!button) return;
    button.disabled = true;
    try {
      const response = await fetch('includes/api.php?resource=rider-portal-location', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:button.dataset.action,number:button.dataset.number,csrf:riderCsrf})});
      const result = await response.json(); if (!response.ok) throw new Error(result.error || 'Could not update this offer.');
      await loadDeliveries();
    } catch (error) { statusBox.textContent = error.message; button.disabled = false; }
  });
  loadDeliveries();
  setInterval(loadDeliveries, 15000);
  if (navigator.geolocation) setInterval(() => navigator.geolocation.getCurrentPosition(position => {
    fetch('includes/api.php?resource=rider-portal-location', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'location',latitude:position.coords.latitude,longitude:position.coords.longitude,csrf:riderCsrf})}).catch(()=>{});
  }, () => {}, {enableHighAccuracy:true,maximumAge:10000,timeout:10000}), 15000);
  </script>
</body>
</html>
