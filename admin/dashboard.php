<?php
declare(strict_types=1);
require __DIR__ . '/../includes/admin_auth.php';
if (admin_user_id() === null) { header('Location: index.php'); exit; }
require __DIR__ . '/../includes/db.php';

$storeOpen = (bool)$pdo->query('SELECT is_open FROM store_settings WHERE id = 1')->fetchColumn();
$storeCsrf = $_SESSION['admin_store_csrf'] ??= bin2hex(random_bytes(32));
$orderCsrf = $_SESSION['admin_order_csrf'] ??= bin2hex(random_bytes(32));
$period = $_GET['period'] ?? '30';
$days = in_array($period, ['7', '30', '90'], true) ? (int)$period : 30;

$summary = $pdo->prepare("SELECT COUNT(*) AS orders_count, COALESCE(SUM(total),0) AS revenue, COALESCE(AVG(total),0) AS average_order, COALESCE(SUM(status='Pending'),0) AS pending_count FROM orders WHERE status <> 'Cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
$summary->execute([$days]);
$stats = $summary->fetch();

$daily = $pdo->prepare("SELECT DATE(created_at) AS day, COUNT(*) AS orders_count, SUM(total) AS revenue FROM orders WHERE status <> 'Cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY day");
$daily->execute([$days]);
$dailyRows = $daily->fetchAll();
$dailyByDate = [];
foreach ($dailyRows as $row) $dailyByDate[$row['day']] = $row;
$chartRows = [];
$chartDays = min($days, 30);
for ($offset = $chartDays - 1; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime("-$offset days"));
    $chartRows[] = $dailyByDate[$date] ?? ['day' => $date, 'orders_count' => 0, 'revenue' => 0];
}
$maxRevenue = max(array_map(static fn($row) => (float)$row['revenue'], $chartRows ?: [['revenue' => 0]]));

$best = $pdo->prepare("SELECT oi.product_name, SUM(oi.quantity) AS sold, SUM(oi.unit_price * oi.quantity) AS revenue FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status <> 'Cancelled' AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY oi.product_id, oi.product_name ORDER BY sold DESC, revenue DESC LIMIT 5");
$best->execute([$days]);
$bestRows = $best->fetchAll();
$recent = $pdo->query("SELECT o.order_number,o.customer_name,o.customer_phone,o.delivery_address,o.status,o.total,o.created_at,c.profile_image AS customer_profile_image FROM orders o LEFT JOIN customers c ON c.id=o.customer_id ORDER BY CASE WHEN o.status IN ('Pending','Confirmed','Preparing','On the way','Received','Processing','Ready') THEN 0 ELSE 1 END, o.id DESC LIMIT 30")->fetchAll();
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function peso(mixed $value): string { return '₱' . number_format((float)$value, 2); }
function customerInitials(mixed $name): string {
    $parts = preg_split('/\s+/', trim((string)$name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return '?';
    $initials = mb_substr($parts[0], 0, 1);
    if (count($parts) > 1) $initials .= mb_substr($parts[count($parts) - 1], 0, 1);
    return mb_strtoupper($initials, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#f5f7f5">
  <title>Owner Dashboard | MazSen Munch &amp; Sip</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <style>
    :root{--admin-green:#168b50;--admin-green-dark:#116c40;--admin-ink:#202a24;--admin-muted:#87918a;--admin-line:#edf0ed;--admin-bg:#f5f7f5}
    body.admin-body{margin:0;background:var(--admin-bg);color:var(--admin-ink);font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","SF Pro Display",system-ui,"Segoe UI",Arial,sans-serif;font-size:14px}
    .admin-layout{display:grid;grid-template-columns:224px minmax(0,1fr);min-height:100vh;max-width:1600px;margin:auto;padding:18px;gap:18px}
    .admin-sidebar{position:sticky;top:18px;height:calc(100vh - 36px);min-height:650px;display:flex;flex-direction:column;padding:20px 14px;background:#fff;border:1px solid var(--admin-line);border-radius:19px;box-shadow:0 5px 24px #17352208}
    .admin-brand{display:flex;align-items:center;gap:10px;padding:0 7px 24px;text-decoration:none;color:var(--admin-green-dark)}
    .admin-brand-mark{display:block;width:42px;height:42px;flex:none;border:2px solid #fff;border-radius:50%;background:#fff;object-fit:cover;box-shadow:0 2px 8px #17352218}
    .admin-brand-name{font-size:14px;font-weight:850;letter-spacing:.07em}.admin-brand-name small{display:block;margin-top:3px;color:#9aa39c;font-size:9px;letter-spacing:.16em}
    .admin-nav-label{padding:0 10px;color:#a2aaa4;font-size:10px;font-weight:800;letter-spacing:.14em}
    .admin-nav{display:grid;gap:5px;margin-top:8px}.admin-nav a{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:10px;color:#68736b;text-decoration:none;font-size:13px;font-weight:600}.admin-nav a:hover,.admin-nav a.active{background:#eaf5ee;color:var(--admin-green-dark)}.admin-nav-icon{display:inline-grid;place-items:center;width:18px;height:18px;flex:none}.admin-nav-icon svg{width:17px;height:17px;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .admin-side-bottom{margin-top:auto}.admin-side-divider{height:1px;margin:15px 5px;background:var(--admin-line)}.admin-owner{display:flex;align-items:center;gap:10px;padding:10px 5px 0}.admin-owner-avatar{display:grid;place-items:center;width:35px;height:35px;border-radius:50%;background:#def1e5;color:var(--admin-green-dark);font-weight:800}.admin-owner-copy{min-width:0;flex:1}.admin-owner-copy strong,.admin-owner-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.admin-owner-copy strong{font-size:12px}.admin-owner-copy small{margin-top:3px;color:var(--admin-muted);font-size:10px}.admin-signout{color:#bf6359;text-decoration:none;font-size:11px}
    .admin-main{min-width:0;padding:7px 7px 26px}.admin-header{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:5px 0 22px}.admin-heading h1{margin:0;font-size:25px;line-height:1.2;letter-spacing:-.035em}.admin-heading p{margin:6px 0 0;color:var(--admin-muted);font-size:12px}.admin-header-actions{display:flex;align-items:center;gap:12px}
    .store-toggle{display:flex;align-items:center;gap:10px;min-height:42px;padding:0 13px;border:1px solid;border-radius:11px;background:#fff;font:inherit;cursor:pointer;transition:background .18s,color .18s,border-color .18s}.store-toggle-title{color:#707a72;font-size:12px;font-weight:650}.store-toggle strong{min-width:37px;text-align:left;font-size:12px}.store-switch-track{position:relative;display:block;width:38px;height:22px;flex:none;border-radius:999px;transition:background .18s}.store-switch-track i{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 4px #0003;transition:transform .18s}.store-toggle-open{color:#286e44;border-color:#d5e8da;background:#f2f8f3}.store-toggle-open .store-switch-track{background:#258a50}.store-toggle-open .store-switch-track i{transform:translateX(16px)}.store-toggle-closed{color:#9c5147;border-color:#eedbd7;background:#fff5f3}.store-toggle-closed .store-switch-track{background:#b8584d}.store-toggle:disabled{opacity:.55;cursor:wait}
    .period-form{display:flex;align-items:center;gap:9px}.period-form label{color:#758078;font-size:12px}.period-form select{height:36px;padding:0 30px 0 11px;border:1px solid #e5eae6;border-radius:9px;background:#fff;color:#465149;font:inherit;font-size:12px}
    .admin-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}.admin-stat,.admin-panel{background:#fff;border:1px solid var(--admin-line);border-radius:15px;box-shadow:0 4px 18px #17352205}.admin-stat{min-height:119px;padding:17px 18px}.admin-stat-top{display:flex;align-items:center;justify-content:space-between;color:#737d75;font-size:12px;font-weight:600}.admin-stat-icon{display:grid;place-items:center;width:29px;height:29px;border-radius:9px;background:#eaf5ee;color:var(--admin-green);font-size:15px;font-weight:800}.admin-stat-value{display:block;margin-top:12px;font-size:25px;line-height:1.1;font-weight:800;letter-spacing:-.035em}.admin-stat-note{display:block;margin-top:6px;color:#9aa39c;font-size:10px}
    .admin-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(280px,.95fr);gap:15px;margin-top:16px}.admin-panel{min-width:0;padding:18px}.admin-panel-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px}.admin-panel-heading h2{margin:0;font-size:14px;font-weight:750;letter-spacing:-.01em}.admin-panel-heading p{margin:4px 0 0;color:var(--admin-muted);font-size:10px}.period-chip{padding:6px 9px;border-radius:7px;background:#f5f7f5;color:#6e796f;font-size:10px;font-weight:650}
    .sales-chart{display:flex;align-items:stretch;gap:8px;height:214px;overflow-x:auto;padding:9px 2px 0;border-bottom:1px solid #edf0ed;background:repeating-linear-gradient(to bottom,transparent 0,transparent 51px,#f0f3f0 52px)}.sales-column{display:flex;flex:1 0 18px;flex-direction:column;align-items:center;justify-content:flex-end;min-width:14px;height:100%;gap:7px}.sales-bar-area{display:flex;align-items:flex-end;justify-content:center;width:100%;height:calc(100% - 21px)}.sales-bar{display:block;width:min(25px,82%);min-height:3px;border-radius:7px 7px 3px 3px;background:#dfe5e1;transition:background .15s,transform .15s}.sales-bar:hover{background:#20a65f;transform:translateY(-2px)}.sales-bar.has-sales{background:linear-gradient(180deg,#34c878,#16934f)}.sales-date{color:#8e9890;font-size:9px;white-space:nowrap}.chart-empty{display:grid;place-items:center;min-height:150px;color:#99a29b;font-size:12px}
    .top-list{display:grid;gap:0}.top-row{display:grid;grid-template-columns:28px minmax(0,1fr) auto;align-items:center;gap:10px;padding:12px 0;border-bottom:1px solid #f0f2f0}.top-row:last-child{border-bottom:0}.top-rank{display:grid;place-items:center;width:25px;height:25px;border-radius:50%;background:#e9f5ed;color:var(--admin-green);font-size:11px;font-weight:800}.top-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:650}.top-meta{margin-top:4px;color:#929c94;font-size:10px}.top-sales{text-align:right;font-size:11px;font-weight:750}.top-sales small{display:block;margin-top:4px;color:#929c94;font-size:10px;font-weight:500}.top-empty{padding:25px 0;color:#929c94;text-align:center;font-size:12px}
    .orders-panel{margin-top:15px}.orders-wrap{overflow-x:auto}.orders-table{width:100%;border-collapse:collapse;text-align:left;white-space:nowrap}.orders-table th{padding:10px 9px;border-bottom:1px solid var(--admin-line);color:#939c95;font-size:10px;font-weight:650}.orders-table td{padding:12px 9px;border-bottom:1px solid #f1f3f1;font-size:11px}.orders-table tr:last-child td{border-bottom:0}.order-code{color:#354239;font-weight:750}.order-customer{max-width:185px;overflow:hidden;text-overflow:ellipsis}.order-contact{color:#7e8980}.order-status{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eef3ef;color:#667168;font-size:9px;font-weight:750}.order-status-pending{background:#fff4df;color:#9b711e}.order-status-cancelled{background:#fff0ed;color:#a5554b}.order-total{text-align:right;font-weight:750}.no-orders{padding:28px;color:#929c94;text-align:center;font-size:12px}
    .order-customer{display:flex;align-items:center;gap:10px;max-width:235px}.customer-profile-dot{display:grid;place-items:center;width:34px;height:34px;flex:none;overflow:hidden;border:2px solid #fff;border-radius:50%;background:linear-gradient(145deg,#e5f3e9,#d5eadc);color:#287447;font-size:11px;font-weight:800;box-shadow:0 0 0 1px #dce9df}.customer-profile-dot img{width:100%;height:100%;object-fit:cover}.customer-profile-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    @media(max-width:1050px){.admin-layout{grid-template-columns:190px minmax(0,1fr);padding:12px;gap:12px}.admin-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-grid{grid-template-columns:minmax(0,1fr)}}
    @media(max-width:680px){.admin-layout{display:block;padding:10px}.admin-sidebar{position:static;height:auto;min-height:0;margin-bottom:14px;padding:12px}.admin-brand{padding:0 4px 10px}.admin-nav-label,.admin-side-bottom{display:none}.admin-nav{display:flex;overflow:auto;margin:0}.admin-nav a{flex:none;padding:9px 10px;font-size:11px}.admin-main{padding:3px 1px 20px}.admin-header{align-items:flex-start;flex-direction:column;margin:8px 2px 17px}.admin-header-actions{width:100%;justify-content:space-between}.admin-heading h1{font-size:22px}.admin-stats{gap:9px}.admin-stat{min-height:105px;padding:13px}.admin-stat-value{font-size:21px}.admin-panel{padding:14px}.sales-chart{height:185px}.period-form{justify-content:space-between}}
    /* Larger admin typography for clear reading across the dashboard. */
    body.admin-body{font-size:16px;line-height:1.55}
    .admin-brand-name{font-size:16px}.admin-brand-name small{font-size:11px}
    .admin-nav-label{font-size:12px}.admin-nav a{font-size:15px}.admin-owner-copy strong{font-size:14px}.admin-owner-copy small,.admin-signout{font-size:12px}
    .admin-heading h1{font-size:29px}.admin-heading p{font-size:14px}
    .store-toggle-title,.store-toggle strong,.period-form label,.period-form select{font-size:14px}
    .admin-stat-top{font-size:14px}.admin-stat-value{font-size:29px}.admin-stat-note{font-size:12px}
    .admin-panel-heading h2{font-size:16px}.admin-panel-heading p{font-size:12px}.period-chip{font-size:12px}
    .sales-date{font-size:11px}.chart-empty,.top-empty{font-size:14px}
    .top-rank{font-size:13px}.top-name{font-size:14px}.top-meta,.top-sales small{font-size:12px}.top-sales{font-size:13px}
    .orders-table th{font-size:12px}.orders-table td{font-size:13px}.order-status{font-size:11px}.no-orders{font-size:14px}
    @media(max-width:680px){.admin-nav a{font-size:13px}.admin-heading h1{font-size:26px}.admin-heading p{font-size:13px}.admin-stat-top{font-size:13px}.admin-stat-value{font-size:24px}.admin-stat-note{font-size:11px}.sales-date{font-size:10px}}
    /* Further increase requested for admin visibility. */
    body.admin-body{font-size:17px}
    .admin-brand-name{font-size:17px}.admin-brand-name small{font-size:12px}
    .admin-nav-label{font-size:13px}.admin-nav a{font-size:16px}.admin-owner-copy strong{font-size:15px}.admin-owner-copy small,.admin-signout{font-size:13px}
    .admin-heading h1{font-size:31px}.admin-heading p{font-size:15px}
    .store-toggle-title,.store-toggle strong,.period-form label,.period-form select{font-size:15px}
    .admin-stat-top{font-size:15px}.admin-stat-value{font-size:31px}.admin-stat-note{font-size:13px}
    .admin-panel-heading h2{font-size:17px}.admin-panel-heading p{font-size:13px}.period-chip{font-size:13px}
    .sales-date{font-size:12px}.chart-empty,.top-empty{font-size:15px}
    .top-rank{font-size:14px}.top-name{font-size:15px}.top-meta,.top-sales small{font-size:13px}.top-sales{font-size:14px}
    .orders-table th{font-size:13px}.orders-table td{font-size:14px}.order-status{font-size:12px}.no-orders{font-size:15px}
    @media(max-width:680px){.admin-nav a{font-size:14px}.admin-heading h1{font-size:28px}.admin-heading p{font-size:14px}.admin-stat-top{font-size:14px}.admin-stat-value{font-size:26px}.admin-stat-note{font-size:12px}.sales-date{font-size:11px}.orders-table th{font-size:12px}.orders-table td{font-size:13px}}
    .order-progress-button{padding:7px 10px;border:1px solid #cfe5d5;border-radius:8px;background:#eff8f1;color:#167342;font-family:inherit;font-size:12px;font-weight:600;white-space:nowrap;cursor:pointer}.order-progress-button:hover{background:#dff1e4}.order-progress-button:disabled{opacity:.55;cursor:wait}.order-done-label{color:#819087;font-size:12px}
  </style>
  <link rel="stylesheet" href="../assets/css/logout.css?v=1">
</head>
<body class="admin-body">
<div class="admin-layout">
  <aside class="admin-sidebar">
    <a class="admin-brand" href="dashboard.php"><img class="admin-brand-mark" src="../logo.jpg" alt="MazSen Munch &amp; Sip logo"><span class="admin-brand-name">MAZSEN<small>MUNCH &amp; SIP</small></span></a>
    <p class="admin-nav-label">OWNER WORKSPACE</p>
    <nav class="admin-nav" aria-label="Admin navigation">
      <a class="active" href="dashboard.php"><span class="admin-nav-icon"><i data-lucide="layout-dashboard"></i></span>Dashboard</a>
      <a href="#sales"><span class="admin-nav-icon"><i data-lucide="bar-chart-3"></i></span>Analytics</a>
      <a href="#recent-orders"><span class="admin-nav-icon"><i data-lucide="receipt-text"></i></span>Orders</a>
      <a href="#top-items"><span class="admin-nav-icon"><i data-lucide="trending-up"></i></span>Top items</a>
    </nav>
    <div class="admin-side-bottom"><div class="admin-side-divider"></div><div class="admin-owner"><span class="admin-owner-avatar">M</span><span class="admin-owner-copy"><strong>Store owner</strong><small>MazSen Munch &amp; Sip</small></span><a class="admin-signout" href="logout.php">Sign out</a></div></div>
  </aside>

  <main class="admin-main">
    <header class="admin-header"><div class="admin-heading"><h1>Dashboard</h1><p>Welcome back. Here is your store activity.</p></div><div class="admin-header-actions"><button type="button" id="store-toggle" class="store-toggle <?= $storeOpen ? 'store-toggle-open' : 'store-toggle-closed' ?>" aria-label="Toggle store open or closed" aria-pressed="<?= $storeOpen ? 'true' : 'false' ?>" data-csrf="<?= h($storeCsrf) ?>"><span class="store-toggle-title">Store</span><span class="store-switch-track" aria-hidden="true"><i></i></span><strong id="store-toggle-label"><?= $storeOpen ? 'Open' : 'Closed' ?></strong></button><form class="period-form" method="get"><label for="period">Period</label><select id="period" name="period" onchange="this.form.submit()"><option value="7" <?= $days===7?'selected':'' ?>>7 days</option><option value="30" <?= $days===30?'selected':'' ?>>30 days</option><option value="90" <?= $days===90?'selected':'' ?>>90 days</option></select></form></div></header>

    <section class="admin-stats" aria-label="Store summary">
      <article class="admin-stat"><div class="admin-stat-top"><span>Revenue</span><span class="admin-stat-icon">₱</span></div><strong class="admin-stat-value"><?= peso($stats['revenue']) ?></strong><small class="admin-stat-note">Completed and active orders · <?= $days ?> days</small></article>
      <article class="admin-stat"><div class="admin-stat-top"><span>Orders</span><span class="admin-stat-icon">#</span></div><strong class="admin-stat-value"><?= number_format((int)$stats['orders_count']) ?></strong><small class="admin-stat-note">Excludes cancelled · <?= $days ?> days</small></article>
      <article class="admin-stat"><div class="admin-stat-top"><span>Average order</span><span class="admin-stat-icon">↗</span></div><strong class="admin-stat-value"><?= peso($stats['average_order']) ?></strong><small class="admin-stat-note">Per non-cancelled order · <?= $days ?> days</small></article>
      <article class="admin-stat"><div class="admin-stat-top"><span>Pending orders</span><span class="admin-stat-icon">◷</span></div><strong class="admin-stat-value"><?= number_format((int)$stats['pending_count']) ?></strong><small class="admin-stat-note">Awaiting store action · <?= $days ?> days</small></article>
    </section>

    <section class="admin-grid">
      <article class="admin-panel" id="sales"><div class="admin-panel-heading"><div><h2>Orders &amp; revenue</h2><p>Daily sales totals, excluding cancelled orders</p></div><span class="period-chip">Last <?= $chartDays ?> days</span></div>
        <?php if ((float)$stats['orders_count'] === 0.0): ?><div class="chart-empty">No orders in this period yet.</div><?php else: ?><div class="sales-chart" role="img" aria-label="Daily revenue chart for the last <?= $chartDays ?> days"><?php foreach ($chartRows as $row): $amount=(float)$row['revenue']; $height=$maxRevenue > 0 ? max($amount > 0 ? 5 : 2, (int)round($amount / $maxRevenue * 100)) : 2; ?><div class="sales-column"><div class="sales-bar-area"><span class="sales-bar <?= $amount>0?'has-sales':'' ?>" style="height:<?= $height ?>%" title="<?= h(date('M j, Y', strtotime($row['day']))) ?> · <?= peso($amount) ?> · <?= (int)$row['orders_count'] ?> orders"></span></div><small class="sales-date"><?= h(date($chartDays > 14 ? 'd' : 'M j', strtotime($row['day']))) ?></small></div><?php endforeach; ?></div><?php endif; ?>
      </article>
      <article class="admin-panel" id="top-items"><div class="admin-panel-heading"><div><h2>Top selling items</h2><p>Ranked by units sold · <?= $days ?> days</p></div></div>
        <?php if (!$bestRows): ?><div class="top-empty">Item sales will appear here.</div><?php else: ?><div class="top-list"><?php foreach ($bestRows as $index=>$item): ?><div class="top-row"><span class="top-rank"><?= $index+1 ?></span><div><div class="top-name" title="<?= h($item['product_name']) ?>"><?= h($item['product_name']) ?></div><div class="top-meta"><?= number_format((int)$item['sold']) ?> sold</div></div><div class="top-sales"><?= peso($item['revenue']) ?><small>sales</small></div></div><?php endforeach; ?></div><?php endif; ?>
      </article>
    </section>

    <section class="admin-panel orders-panel" id="recent-orders"><div class="admin-panel-heading"><div><h2>Recent orders</h2><p>Latest customer orders from the database</p></div><span class="period-chip"><?= count($recent) ?> recent</span></div><div class="orders-wrap"><table class="orders-table"><thead><tr><th>Order</th><th>Customer</th><th>Phone</th><th>Status</th><th>Progress</th><th>Date</th><th style="text-align:right">Total</th></tr></thead><tbody><?php foreach ($recent as $order): $statusDisplay=['Received'=>'Pending','Processing'=>'Preparing','Ready'=>'On the way','Completed'=>'Delivered'][$order['status']] ?? $order['status']; $statusClass=strtolower(str_replace(' ','-',(string)$statusDisplay)); $nextStatuses=['Pending'=>'Confirmed','Confirmed'=>'Preparing','Preparing'=>'On the way','On the way'=>'Delivered']; $nextStatus=$nextStatuses[$statusDisplay] ?? null; $nextActions=['Pending'=>'Confirm order','Confirmed'=>'Start preparing','Preparing'=>'Send with rider','On the way'=>'Mark delivered']; $nextAction=$nextActions[$statusDisplay] ?? null; ?><tr><td class="order-code"><?= h($order['order_number']) ?></td><td class="order-customer" title="<?= h($order['customer_name'] ?? 'Legacy order') ?>"><span class="customer-profile-dot" aria-hidden="true"><?php if (!empty($order['customer_profile_image'])): ?><img src="../<?= h($order['customer_profile_image']) ?>" alt=""><?php else: ?><?= h(customerInitials($order['customer_name'] ?? 'Guest')) ?><?php endif; ?></span><span class="customer-profile-name"><?= h($order['customer_name'] ?? 'Legacy order') ?></span></td><td class="order-contact"><?= h($order['customer_phone'] ?? '—') ?></td><td><span class="order-status order-status-<?= h($statusClass) ?>" data-current-status><?= h($statusDisplay) ?></span></td><td><?php if ($nextStatus !== null): ?><button type="button" class="order-progress-button" data-order-number="<?= h($order['order_number']) ?>" data-next-status="<?= h($nextStatus) ?>" data-csrf="<?= h($orderCsrf) ?>"><?= h($nextAction) ?></button><?php else: ?><span class="order-done-label"><?= $statusDisplay === 'Delivered' ? 'Delivered' : h($statusDisplay) ?></span><?php endif; ?></td><td class="order-contact"><?= h(date('M j, Y · g:i A', strtotime($order['created_at']))) ?></td><td class="order-total"><?= peso($order['total']) ?></td></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td class="no-orders" colspan="7">No orders have been recorded yet.</td></tr><?php endif; ?></tbody></table></div></section>
  </main>
</div>
  <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <script src="../assets/js/logout-transition.js?v=1"></script>
<script>if (window.lucide) lucide.createIcons();</script>
<script>
const storeToggle=document.getElementById('store-toggle');
storeToggle?.addEventListener('click',async()=>{
  const wasOpen=storeToggle.getAttribute('aria-pressed')==='true';
  storeToggle.disabled=true;
  try{
    const response=await fetch('../includes/api.php?resource=store',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({is_open:!wasOpen,csrf:storeToggle.dataset.csrf})});
    const result=await response.json();
    if(!response.ok)throw new Error(result.error||'Could not update store status.');
    const isOpen=result.is_open===true;
    storeToggle.setAttribute('aria-pressed',String(isOpen));
    storeToggle.classList.toggle('store-toggle-open',isOpen);
    storeToggle.classList.toggle('store-toggle-closed',!isOpen);
    document.getElementById('store-toggle-label').textContent=isOpen?'Open':'Closed';
  }catch(error){alert(error.message||'Could not reach the server.');}
  finally{storeToggle.disabled=false;}
});
document.querySelectorAll('[data-order-number]').forEach(button=>button.addEventListener('click',async()=>{
  button.disabled=true;
  try{
    const response=await fetch('../includes/api.php?resource=orders',{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({number:button.dataset.orderNumber,csrf:button.dataset.csrf})});
    const result=await response.json();
    if(!response.ok)throw new Error(result.error||'Could not update this order.');
    const row=button.closest('tr');
    const badge=row?.querySelector('[data-current-status]');
    if(badge){badge.textContent=result.status;badge.className='order-status order-status-'+result.status.toLowerCase().replace(/[^a-z0-9]+/g,'-');}
    const nextStatus=({'Pending':'Confirmed','Confirmed':'Preparing','Preparing':'On the way','On the way':'Delivered'})[result.status];const nextAction=({'Pending':'Confirm order','Confirmed':'Start preparing','Preparing':'Send with rider','On the way':'Mark delivered'})[result.status];
    if(nextStatus){button.dataset.nextStatus=nextStatus;button.textContent=nextAction;button.disabled=false;}
    else{button.replaceWith(Object.assign(document.createElement('span'),{className:'order-done-label',textContent:'Delivered'}));}
  }catch(error){alert(error.message||'Could not reach the server.');button.disabled=false;}
}));
</script>
</body>
</html>
