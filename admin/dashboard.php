<?php
declare(strict_types=1);
require __DIR__ . '/../includes/admin_auth.php';
if (admin_user_id() === null) { header('Location: index.php'); exit; }
require __DIR__ . '/../includes/db.php';

$storeOpen = (bool)$pdo->query('SELECT is_open FROM store_settings WHERE id = 1')->fetchColumn();
$storeCsrf = $_SESSION['admin_store_csrf'] ?? bin2hex(random_bytes(32));
$orderCsrf = $_SESSION['admin_order_csrf'] ?? bin2hex(random_bytes(32));
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
$recent = $pdo->query("SELECT o.order_number,o.customer_name,o.customer_phone,o.delivery_address,o.status,o.total,o.created_at,c.profile_image AS customer_profile_image,r.full_name AS assigned_rider_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN delivery_riders r ON r.id=o.assigned_rider_id ORDER BY CASE WHEN o.status IN ('Pending','Confirmed','Preparing','On the way','Received','Processing','Ready') THEN 0 ELSE 1 END, o.id DESC LIMIT 30")->fetchAll();
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
  <link rel="stylesheet" href="../assets/css/styles.css?v=84">
  <style>
    :root{--admin-green:#713082;--admin-green-dark:#582665;--admin-ink:#202a24;--admin-muted:#87918a;--admin-line:#edf0ed;--admin-bg:#f5f7f5}
    body.admin-body{margin:0;background:var(--admin-bg);color:var(--admin-ink);font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","SF Pro Display",system-ui,"Segoe UI",Arial,sans-serif;font-size:14px}
    .admin-layout{display:grid;grid-template-columns:224px minmax(0,1fr);min-height:100vh;max-width:1600px;margin:auto;padding:18px;gap:18px}
    .admin-sidebar{position:sticky;top:18px;height:calc(100vh - 36px);min-height:650px;display:flex;flex-direction:column;padding:20px 14px;background:#fff;border:1px solid var(--admin-line);border-radius:19px;box-shadow:0 5px 24px #2e1c3208}
    .admin-brand{display:flex;align-items:center;gap:10px;padding:0 7px 24px;text-decoration:none;color:var(--admin-green-dark)}
    .admin-brand-mark{display:block;width:42px;height:42px;flex:none;border:2px solid #fff;border-radius:50%;background:#fff;object-fit:cover;box-shadow:0 2px 8px #2e1c3218}
    .admin-brand-name{font-size:14px;font-weight:850;letter-spacing:.07em}.admin-brand-name small{display:block;margin-top:3px;color:#9aa39c;font-size:9px;letter-spacing:.16em}
    .admin-nav-label{padding:0 10px;color:#a2aaa4;font-size:10px;font-weight:800;letter-spacing:.14em}
    .admin-nav{display:grid;gap:5px;margin-top:8px}.admin-nav a{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:10px;color:#68736b;text-decoration:none;font-size:13px;font-weight:600}.admin-nav a:hover,.admin-nav a.active{background:#eaf5ee;color:var(--admin-green-dark)}.admin-nav-icon{display:inline-grid;place-items:center;width:18px;height:18px;flex:none}.admin-nav-icon svg{width:17px;height:17px;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
    .admin-side-bottom{margin-top:auto}.admin-side-divider{height:1px;margin:15px 5px;background:var(--admin-line)}.admin-owner{display:flex;align-items:center;gap:10px;padding:10px 5px 0}.admin-owner-avatar{display:grid;place-items:center;width:35px;height:35px;border-radius:50%;background:#def1e5;color:var(--admin-green-dark);font-weight:800}.admin-owner-copy{min-width:0;flex:1}.admin-owner-copy strong,.admin-owner-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.admin-owner-copy strong{font-size:12px}.admin-owner-copy small{margin-top:3px;color:var(--admin-muted);font-size:10px}.admin-signout{color:#bf6359;text-decoration:none;font-size:11px}
    .admin-main{min-width:0;padding:7px 7px 26px}.admin-header{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:5px 0 22px}.admin-heading h1{margin:0;font-size:25px;line-height:1.2;letter-spacing:-.035em}.admin-heading p{margin:6px 0 0;color:var(--admin-muted);font-size:12px}.admin-header-actions{display:flex;align-items:center;gap:12px}
    .store-toggle{display:flex;align-items:center;gap:10px;min-height:42px;padding:0 13px;border:1px solid;border-radius:11px;background:#fff;font:inherit;cursor:pointer;transition:background .18s,color .18s,border-color .18s}.store-toggle-title{color:#707a72;font-size:12px;font-weight:650}.store-toggle strong{min-width:37px;text-align:left;font-size:12px}.store-switch-track{position:relative;display:block;width:38px;height:22px;flex:none;border-radius:999px;transition:background .18s}.store-switch-track i{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 4px #0003;transition:transform .18s}.store-toggle-open{color:#5f3568;border-color:#d5e8da;background:#f2f8f3}.store-toggle-open .store-switch-track{background:#743881}.store-toggle-open .store-switch-track i{transform:translateX(16px)}.store-toggle-closed{color:#9c5147;border-color:#eedbd7;background:#fff5f3}.store-toggle-closed .store-switch-track{background:#b8584d}.store-toggle:disabled{opacity:.55;cursor:wait}
    .period-form{display:flex;align-items:center;gap:9px}.period-form label{color:#758078;font-size:12px}.period-form select{height:36px;padding:0 30px 0 11px;border:1px solid #e5eae6;border-radius:9px;background:#fff;color:#465149;font:inherit;font-size:12px}
    .admin-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}.admin-stat,.admin-panel{background:#fff;border:1px solid var(--admin-line);border-radius:15px;box-shadow:0 4px 18px #2e1c3205}.admin-stat{min-height:119px;padding:17px 18px}.admin-stat-top{display:flex;align-items:center;justify-content:space-between;color:#737d75;font-size:12px;font-weight:600}.admin-stat-icon{display:grid;place-items:center;width:29px;height:29px;border-radius:9px;background:#eaf5ee;color:var(--admin-green);font-size:15px;font-weight:800}.admin-stat-value{display:block;margin-top:12px;font-size:25px;line-height:1.1;font-weight:800;letter-spacing:-.035em}.admin-stat-note{display:block;margin-top:6px;color:#9aa39c;font-size:10px}
    .admin-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(280px,.95fr);gap:15px;margin-top:16px}.admin-panel{min-width:0;padding:18px}.admin-panel-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px}.admin-panel-heading h2{margin:0;font-size:14px;font-weight:750;letter-spacing:-.01em}.admin-panel-heading p{margin:4px 0 0;color:var(--admin-muted);font-size:10px}.period-chip{padding:6px 9px;border-radius:7px;background:#f5f7f5;color:#6e796f;font-size:10px;font-weight:650}
    .sales-chart{display:flex;align-items:stretch;gap:8px;height:214px;overflow-x:auto;padding:9px 2px 0;border-bottom:1px solid #edf0ed;background:repeating-linear-gradient(to bottom,transparent 0,transparent 51px,#f0f3f0 52px)}.sales-column{display:flex;flex:1 0 18px;flex-direction:column;align-items:center;justify-content:flex-end;min-width:14px;height:100%;gap:7px}.sales-bar-area{display:flex;align-items:flex-end;justify-content:center;width:100%;height:calc(100% - 21px)}.sales-bar{display:block;width:min(25px,82%);min-height:3px;border-radius:7px 7px 3px 3px;background:#dfe5e1;transition:background .15s,transform .15s}.sales-bar:hover{background:#893c9b;transform:translateY(-2px)}.sales-bar.has-sales{background:linear-gradient(180deg,#a753bc,#783089)}.sales-date{color:#8e9890;font-size:9px;white-space:nowrap}.chart-empty{display:grid;place-items:center;min-height:150px;color:#99a29b;font-size:12px}
    .top-list{display:grid;gap:0}.top-row{display:grid;grid-template-columns:28px minmax(0,1fr) auto;align-items:center;gap:10px;padding:12px 0;border-bottom:1px solid #f0f2f0}.top-row:last-child{border-bottom:0}.top-rank{display:grid;place-items:center;width:25px;height:25px;border-radius:50%;background:#e9f5ed;color:var(--admin-green);font-size:11px;font-weight:800}.top-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:650}.top-meta{margin-top:4px;color:#929c94;font-size:10px}.top-sales{text-align:right;font-size:11px;font-weight:750}.top-sales small{display:block;margin-top:4px;color:#929c94;font-size:10px;font-weight:500}.top-empty{padding:25px 0;color:#929c94;text-align:center;font-size:12px}
    .orders-panel{margin-top:15px}.orders-wrap{overflow-x:auto}.orders-table{width:100%;border-collapse:collapse;text-align:left;white-space:nowrap}.orders-table th{padding:10px 9px;border-bottom:1px solid var(--admin-line);color:#939c95;font-size:10px;font-weight:650}.orders-table td{padding:12px 9px;border-bottom:1px solid #f1f3f1;font-size:11px}.orders-table tr:last-child td{border-bottom:0}.order-code{color:#354239;font-weight:750}.order-customer{max-width:185px;overflow:hidden;text-overflow:ellipsis}.order-contact{color:#7e8980}.order-status{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eef3ef;color:#667168;font-size:9px;font-weight:750}.order-status-pending{background:#fff4df;color:#9b711e}.order-status-cancelled{background:#fff0ed;color:#a5554b}.order-total{text-align:right;font-weight:750}.no-orders{padding:28px;color:#929c94;text-align:center;font-size:12px}
    .order-customer{display:flex;align-items:center;gap:10px;max-width:235px}.customer-profile-dot{display:grid;place-items:center;width:34px;height:34px;flex:none;overflow:hidden;border:2px solid #fff;border-radius:50%;background:linear-gradient(145deg,#e5f3e9,#d5eadc);color:#63366d;font-size:11px;font-weight:800;box-shadow:0 0 0 1px #dce9df}.customer-profile-dot img{width:100%;height:100%;object-fit:cover}.customer-profile-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
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
    .order-progress-button{padding:7px 10px;border:1px solid #e0d2e3;border-radius:8px;background:#eff8f1;color:#5f2a6c;font-family:inherit;font-size:12px;font-weight:600;white-space:nowrap;cursor:pointer}.order-progress-button:hover{background:#dff1e4}.order-progress-button:disabled{opacity:.55;cursor:wait}.order-done-label{color:#819087;font-size:12px}
    /* Compact workspace inspired by the supplied admin reference. */
    body.admin-body{background:#eaf0f4}
    .admin-layout{grid-template-columns:68px minmax(0,1fr) 278px;max-width:1800px;min-height:100vh;padding:14px;gap:14px}
    .admin-sidebar{top:14px;height:calc(100vh - 28px);min-height:620px;align-items:center;padding:18px 9px;background:#25292d;border:0;border-radius:22px;box-shadow:0 12px 30px #15202c26}
    .admin-brand{justify-content:center;width:100%;padding:0 0 28px}.admin-brand-mark{width:38px;height:38px;border:0;background:#dce8ef;box-shadow:none}
    .admin-brand-name,.admin-nav-label,.admin-side-bottom{display:none}
    .admin-nav{width:100%;gap:9px;margin:0}.admin-nav a{justify-content:center;width:48px;height:48px;padding:0;border-radius:15px;color:#d7dfe3;font-size:0}.admin-nav a:hover{background:#373d42;color:#fff}.admin-nav a.active{background:#f2f6f3;color:#25332a;box-shadow:0 4px 12px #10161935}
    .admin-nav-icon{width:20px;height:20px}.admin-nav-icon svg{width:19px;height:19px;stroke-width:1.7}
    .admin-main{padding:14px 12px 28px}.admin-header{align-items:flex-start;margin:4px 2px 23px}.admin-heading h1{font-size:32px;letter-spacing:-.045em}.admin-heading p{font-size:14px}
    .admin-stats{gap:12px}.admin-stat{min-height:124px;border:0;border-radius:17px;box-shadow:none}.admin-stat:nth-child(1){background:#eee4f3}.admin-stat:nth-child(2){background:#dceaf2}.admin-stat:nth-child(3){background:#f7e9b9}.admin-stat:nth-child(4){background:#e1eee3}.admin-stat-icon{background:#ffffffa8;color:#37423a}
    .admin-grid{gap:14px;margin-top:15px;grid-template-columns:minmax(0,1.45fr) minmax(260px,.85fr)}.admin-panel{border-color:#e8eeeb;border-radius:17px;box-shadow:0 7px 24px #22382a08}.sales-chart{border-radius:10px;background:repeating-linear-gradient(to bottom,transparent 0,transparent 51px,#edf1ee 52px)}.sales-bar.has-sales{background:linear-gradient(180deg,#f5ce69,#e4a939)}.sales-bar:hover{background:#d99b29}.orders-panel{margin-top:14px}
    .admin-insight-rail{position:sticky;top:14px;display:flex;height:calc(100vh - 28px);min-height:620px;flex-direction:column;padding:22px 19px;background:#fff;border:1px solid #e7ece9;border-radius:22px;box-shadow:0 8px 28px #2f203208}
    .rail-profile{display:flex;align-items:center;gap:10px;padding-bottom:19px;border-bottom:1px solid #edf0ee}.rail-profile-avatar{display:grid;width:40px;height:40px;place-items:center;border-radius:50%;background:#e7f2e9;color:#63336e;font-size:15px;font-weight:800}.rail-profile div{min-width:0;flex:1}.rail-profile strong,.rail-profile small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.rail-profile strong{font-size:13px}.rail-profile small{margin-top:2px;color:#929b94;font-size:11px}.rail-profile>i{width:18px;color:#858e87}
    .rail-card{padding:17px 15px;border:1px solid #edf0ee;border-radius:15px;background:#fbfcfb}.rail-store-card{margin-top:18px;background:#f4f8f4;border-color:#e7efe8}.rail-kicker{display:block;color:#939d95;font-size:10px;font-weight:800;letter-spacing:.12em}.rail-store-state{display:flex;align-items:center;gap:8px;margin-top:15px}.rail-store-state i{width:9px;height:9px;border-radius:50%;background:#89419a;box-shadow:0 0 0 4px #e0f1e5}.rail-store-state i.is-closed{background:#c4685c;box-shadow:0 0 0 4px #f8e8e5}.rail-store-state strong{font-size:14px}.rail-store-card p{margin:9px 0 13px;color:#7e8981;font-size:12px;line-height:1.5}.rail-store-card>a{color:#63336e;font-size:11px;font-weight:750;text-decoration:none}.rail-store-card>a span{margin-left:3px}
    .rail-period-card{margin-top:13px}.rail-period-card h2{margin:8px 0 13px;font-size:18px;letter-spacing:-.03em}.rail-metric{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-top:1px solid #edf0ee;color:#77827a;font-size:12px}.rail-metric strong{color:#26322a;font-size:13px}
    .rail-latest{margin-top:22px}.rail-latest-order{display:flex;align-items:center;gap:10px;margin-top:14px}.rail-order-avatar{display:grid;width:34px;height:34px;flex:none;place-items:center;border-radius:50%;background:#e6eff2;color:#46636f;font-size:11px;font-weight:800}.rail-latest-order div{min-width:0}.rail-latest-order strong,.rail-latest-order small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.rail-latest-order strong{font-size:12px}.rail-latest-order small{margin-top:3px;color:#8b958e;font-size:10px}.rail-latest-total{display:flex;justify-content:space-between;margin-top:15px;padding-top:12px;border-top:1px solid #edf0ee;color:#818b84;font-size:11px}.rail-latest-total strong{color:#2c3830;font-size:12px}.rail-empty{color:#929c94;font-size:12px}.rail-signout{display:flex;align-items:center;gap:9px;margin-top:auto;padding:12px 2px 0;color:#78837b;font-size:12px;text-decoration:none}.rail-signout i{width:16px;height:16px}
    @media(max-width:1360px){.admin-layout{grid-template-columns:68px minmax(0,1fr);max-width:1200px}.admin-insight-rail{display:none}}
    @media(max-width:1050px){.admin-layout{grid-template-columns:68px minmax(0,1fr)}.admin-grid{grid-template-columns:minmax(0,1fr)}}
    @media(max-width:680px){.admin-layout{display:block;padding:10px}.admin-sidebar{position:static;height:auto;min-height:0;margin-bottom:12px;align-items:stretch;padding:9px 11px;border-radius:17px}.admin-brand{display:none}.admin-nav{display:flex;justify-content:space-around;overflow:auto}.admin-nav a{width:42px;height:42px;flex:none}.admin-main{padding:5px 1px 20px}.admin-heading h1{font-size:27px}.admin-header{gap:12px}.admin-header-actions{flex-wrap:wrap}.admin-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-stat{min-height:105px}.admin-stat-value{font-size:24px}.admin-grid{grid-template-columns:minmax(0,1fr)}}
    .admin-heading h1{color:#202a24}.admin-heading p{line-height:1.5}
    .admin-stat{display:flex;flex-direction:column;justify-content:space-between;transition:transform .18s ease,box-shadow .18s ease}.admin-stat:hover{transform:translateY(-2px);box-shadow:0 9px 20px #24382b10}.admin-stat-value{margin-top:12px;font-variant-numeric:tabular-nums}.admin-stat-note{line-height:1.4}
    .admin-panel-heading{align-items:center}.admin-panel-heading h2{font-size:18px}.admin-panel-heading p{font-size:12px;line-height:1.45}
    .orders-table th{position:sticky;top:0;background:#fbfcfb}.orders-table tbody tr{transition:background-color .14s ease}.orders-table tbody tr:hover{background:#f8faf8}.orders-table td{vertical-align:middle}.order-code{font-variant-numeric:tabular-nums}.order-status-confirmed,.order-status-preparing{background:#eaf3ff;color:#426d9b}.order-status-on-the-way{background:#fff1df;color:#9b6225}.order-status-delivered{background:#e8f4ea;color:#643d6c}.rider-link-button{padding:7px 10px;border:1px solid #d5e8da;border-radius:8px;background:#f2f8f3;color:#592865;font:inherit;font-size:11px;font-weight:700;cursor:pointer}.rider-link-button:hover{background:#e5f3e9}.rider-link-button:disabled{opacity:.6;cursor:wait}
    .store-toggle:focus-visible,.period-form select:focus-visible,.order-progress-button:focus-visible,.admin-nav a:focus-visible,.rail-signout:focus-visible{outline:3px solid #b093b4;outline-offset:3px}
    .rail-card,.rail-latest{flex:none}.admin-insight-rail{overflow:auto;scrollbar-width:thin;scrollbar-color:#dce4de transparent}
    @media(max-width:680px){.admin-panel-heading{align-items:flex-start}.admin-header-actions{gap:9px}.store-toggle{min-height:40px}.period-form{gap:7px}.admin-stat-top{font-size:13px}.admin-stat-note{font-size:11px}.orders-panel .admin-panel-heading{align-items:flex-start}.admin-nav a{min-width:42px}}
    @media(max-width:680px){
      .admin-layout{padding:9px;gap:0}
      .admin-sidebar{display:flex;gap:10px;margin:0 0 15px;padding:11px 12px 9px;border-radius:18px}
      .admin-brand{display:flex;justify-content:flex-start;width:auto;padding:0 1px 2px}
      .admin-brand-mark{width:31px;height:31px}
      .admin-brand-name{display:block;color:#f5f8f6;font-size:12px;letter-spacing:.08em}
      .admin-brand-name small{margin-top:1px;color:#aebbb3;font-size:8px;letter-spacing:.13em}
      .admin-nav{display:flex;justify-content:stretch;gap:5px;width:100%;overflow:visible;margin:0}
      .admin-nav a{display:flex;flex:1 1 0;flex-direction:column;justify-content:center;gap:4px;width:auto;min-width:0;height:55px;padding:5px 2px;border-radius:12px;font-size:10px;line-height:1.1;text-align:center;white-space:nowrap}
      .admin-nav-icon{width:18px;height:18px}.admin-nav-icon svg{width:17px;height:17px}
      .admin-main{width:100%;box-sizing:border-box;padding:2px 1px 22px}
      .admin-header{align-items:flex-start;gap:12px;margin:4px 2px 16px}
      .admin-heading h1{font-size:27px}.admin-heading p{max-width:34ch;font-size:13px}
      .admin-header-actions{display:grid;grid-template-columns:auto minmax(0,1fr);align-items:center;gap:8px;width:100%}
      .store-toggle{justify-content:space-between;gap:7px;min-height:40px;padding:0 9px;border-radius:10px}
      .store-toggle-title{font-size:11px}.store-toggle strong{min-width:auto;font-size:11px}.store-switch-track{width:32px;height:19px}.store-switch-track i{width:13px;height:13px}.store-toggle-open .store-switch-track i{transform:translateX(13px)}
      .period-form{justify-content:flex-end;gap:7px;min-width:0}.period-form label{font-size:12px}.period-form select{min-width:0;max-width:116px;height:40px;padding:0 25px 0 9px;font-size:12px}
      .admin-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
      .admin-stat{min-width:0;min-height:105px;padding:12px;border-radius:14px}
      .admin-stat-top{gap:6px;font-size:12px}.admin-stat-icon{width:25px;height:25px;border-radius:8px;font-size:13px}
      .admin-stat-value{margin-top:8px;font-size:23px;overflow-wrap:anywhere}.admin-stat-note{font-size:10px;line-height:1.3}
      .admin-grid{gap:11px;margin-top:11px}.admin-panel{padding:13px;border-radius:15px}.admin-panel-heading{gap:8px;margin-bottom:12px}.admin-panel-heading h2{font-size:16px}.admin-panel-heading p{font-size:11px}.period-chip{flex:none;padding:5px 7px;font-size:10px}
      .sales-chart{height:178px;gap:6px;padding-top:8px}.sales-column{flex-basis:18px;min-width:16px}.sales-date{font-size:9px}
      .top-row{grid-template-columns:26px minmax(0,1fr) auto;gap:8px;padding:10px 0}.top-name{font-size:12px}.top-sales{font-size:11px}
      .orders-panel{margin-top:11px}.orders-wrap{margin:0 -13px;padding:0 13px 5px;overscroll-behavior-x:contain;scrollbar-width:thin;scrollbar-color:#cbd8ce transparent}.orders-table{min-width:900px}.orders-table th{position:static;padding:10px 8px;font-size:11px}.orders-table td{padding:10px 8px;font-size:12px}.order-customer{max-width:180px}.customer-profile-dot{width:30px;height:30px}
      .rail-signout{margin-top:20px}
    }
    @media(max-width:380px){
      .admin-layout{padding:7px}.admin-sidebar{padding:10px 9px 8px}.admin-nav{gap:3px}.admin-nav a{font-size:9px}
      .admin-header-actions{grid-template-columns:minmax(0,1fr) auto}.store-toggle{gap:5px;padding-inline:7px}.store-toggle-title{display:none}.period-form label{font-size:11px}.period-form select{max-width:102px}
      .admin-stat{padding:10px}.admin-stat-value{font-size:21px}.admin-stat-note{font-size:9px}
    }
    /* Glass dashboard treatment: soft gradient canvas with layered dark glass surfaces. */
    body.admin-body{background:radial-gradient(ellipse at 6% 8%,#d9e5ff 0,transparent 36%),radial-gradient(ellipse at 92% 86%,#e5d7ff 0,transparent 38%),linear-gradient(135deg,#eef2ff,#e5eaf6 52%,#f4eefa);color:#f5f4f8}
    .admin-layout{max-width:1900px;gap:16px}
    .admin-sidebar,.admin-insight-rail{border:1px solid rgba(255,255,255,.13);background:rgba(20,22,27,.82);box-shadow:0 18px 50px rgba(19,18,31,.2),inset 0 1px rgba(255,255,255,.06);-webkit-backdrop-filter:blur(24px) saturate(135%);backdrop-filter:blur(24px) saturate(135%)}
    .admin-main{padding:22px 20px 28px;border:1px solid rgba(255,255,255,.12);border-radius:28px;background:linear-gradient(145deg,rgba(12,13,16,.97),rgba(20,19,25,.96));box-shadow:0 24px 65px rgba(20,18,36,.23),inset 0 1px rgba(255,255,255,.07);color:#f5f4f8;-webkit-backdrop-filter:blur(24px);backdrop-filter:blur(24px)}
    .admin-heading h1{color:#faf9fc}.admin-heading p{color:#a6a2ad}
    .admin-header{margin-bottom:21px}
    .store-toggle{border-color:rgba(255,255,255,.14);background:rgba(255,255,255,.07);color:#f5f4f8;backdrop-filter:blur(12px)}
    .store-toggle-title{color:#c4bfca}.store-toggle-open{border-color:rgba(163,221,181,.28);background:rgba(80,137,94,.17);color:#b8ebc6}.store-toggle-closed{border-color:rgba(239,158,146,.25);background:rgba(176,78,68,.15);color:#ffb9ad}
    .period-form label{color:#b3afba}.period-form select{border-color:rgba(255,255,255,.14);background:rgba(255,255,255,.08);color:#f3f0f6;backdrop-filter:blur(12px)}
    .period-form select option{background:#242229;color:#fff}
    .admin-stat,.admin-stat:nth-child(1),.admin-stat:nth-child(2),.admin-stat:nth-child(3),.admin-stat:nth-child(4){border:1px solid rgba(255,255,255,.11);background:linear-gradient(145deg,rgba(255,255,255,.105),rgba(255,255,255,.045));box-shadow:inset 0 1px rgba(255,255,255,.08),0 10px 28px rgba(0,0,0,.13);-webkit-backdrop-filter:blur(18px) saturate(130%);backdrop-filter:blur(18px) saturate(130%)}
    .admin-stat-top{color:#c5c1cb}.admin-stat-value{color:#fff}.admin-stat-note{color:#9e99a5}.admin-stat-icon{border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.1);color:#d8b7f5}
    .admin-panel{border:1px solid rgba(255,255,255,.11);background:linear-gradient(145deg,rgba(255,255,255,.075),rgba(255,255,255,.035));box-shadow:inset 0 1px rgba(255,255,255,.055),0 12px 32px rgba(0,0,0,.13);-webkit-backdrop-filter:blur(18px) saturate(125%);backdrop-filter:blur(18px) saturate(125%)}
    .admin-panel-heading h2{color:#f7f5fa}.admin-panel-heading p,.period-chip{color:#a7a2ae}.period-chip{border:1px solid rgba(255,255,255,.09);background:rgba(255,255,255,.07)}
    .sales-chart{border-bottom-color:rgba(255,255,255,.1);background:repeating-linear-gradient(to bottom,transparent 0,transparent 51px,rgba(255,255,255,.075) 52px)}
    .sales-bar{background:rgba(255,255,255,.16)}.sales-bar.has-sales{background:linear-gradient(180deg,#d7a7ff,#9566d9)}.sales-bar:hover{background:#e0b5ff}.sales-date,.chart-empty{color:#aaa5b1}
    .top-row{border-bottom-color:rgba(255,255,255,.09)}.top-name{color:#f3f0f6}.top-meta,.top-sales small,.top-empty{color:#a39eaa}.top-sales{color:#e7d1fb}.top-rank{background:rgba(201,155,241,.16);color:#dfb9ff}
    .orders-table th{border-bottom-color:rgba(255,255,255,.13);background:rgba(20,19,25,.96);color:#aaa5b1}.orders-table td{border-bottom-color:rgba(255,255,255,.075);color:#ddd9e2}.orders-table tbody tr:hover{background:rgba(255,255,255,.045)}.order-code{color:#f4eafd}.order-contact{color:#aaa5b1}.order-total{color:#fff}.no-orders{color:#aaa5b1}
    .order-status{border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.09);color:#e3dfe8}.order-status-pending{background:rgba(235,183,90,.15);color:#f5cc79}.order-status-cancelled{background:rgba(224,115,102,.14);color:#ffaaa0}.order-status-confirmed,.order-status-preparing{background:rgba(102,168,235,.15);color:#a9d3ff}.order-status-on-the-way{background:rgba(241,173,94,.16);color:#ffd08d}.order-status-delivered{background:rgba(138,209,159,.15);color:#b5e9c2}
    .order-progress-button,.rider-link-button{border-color:rgba(207,170,237,.25);background:rgba(156,105,194,.18);color:#e2c3fb}.order-progress-button:hover,.rider-link-button:hover{background:rgba(174,120,216,.28)}.order-done-label{color:#aaa5b1}
    .admin-sidebar .admin-nav a{color:#b9b5c0}.admin-sidebar .admin-nav a:hover{background:rgba(255,255,255,.08);color:#fff}.admin-sidebar .admin-nav a.active{border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.13);color:#fff;box-shadow:0 5px 16px rgba(0,0,0,.2)}
    .admin-insight-rail{border-radius:22px;color:#f3f0f6}.rail-profile{border-bottom-color:rgba(255,255,255,.11)}.rail-profile-avatar,.rail-order-avatar{border:1px solid rgba(255,255,255,.12);background:rgba(204,166,235,.16);color:#e0bdff}.rail-profile strong,.rail-period-card h2,.rail-metric strong,.rail-latest-order strong,.rail-latest-total strong{color:#f3f0f6}.rail-profile small,.rail-card p,.rail-metric,.rail-latest-order small,.rail-latest-total,.rail-empty{color:#aaa5b1}.rail-card,.rail-store-card{border-color:rgba(255,255,255,.1);background:rgba(255,255,255,.055)}.rail-store-card{background:rgba(133,186,145,.09)}.rail-kicker{color:#aaa4b1}.rail-store-state i{background:#91d5a1;box-shadow:0 0 0 4px rgba(145,213,161,.13)}.rail-store-state i.is-closed{background:#e8877c;box-shadow:0 0 0 4px rgba(232,135,124,.13)}.rail-store-card>a{color:#dfb9ff}.rail-metric,.rail-latest-total{border-top-color:rgba(255,255,255,.1)}.rail-signout{color:#b9b4c0}
    .admin-nav a:focus-visible,.rail-signout:focus-visible,.store-toggle:focus-visible,.period-form select:focus-visible,.order-progress-button:focus-visible{outline-color:#d3a7f1}
    @media(max-width:680px){.admin-main{padding:17px 12px 22px;border-radius:22px}.admin-sidebar{background:rgba(20,22,27,.86)}.admin-stats{gap:8px}.admin-stat{min-height:104px}.admin-header{margin-bottom:16px}}
    /* Final color refinement: midnight plum, lilac highlights, and restrained warm metrics. */
    body.admin-body{background:radial-gradient(ellipse at 8% 6%,rgba(190,174,255,.72),transparent 35%),radial-gradient(ellipse at 94% 90%,rgba(225,190,255,.72),transparent 38%),linear-gradient(135deg,#e7edff,#dce4f7 54%,#eee2fb)}
    .admin-main{background:radial-gradient(ellipse at 0 0,rgba(131,82,165,.17),transparent 42%),radial-gradient(ellipse at 100% 100%,rgba(72,93,138,.12),transparent 40%),linear-gradient(145deg,rgba(16,14,21,.98),rgba(22,20,29,.97));border-color:rgba(222,194,249,.17)}
    .admin-sidebar,.admin-insight-rail{background:rgba(24,21,31,.89);border-color:rgba(222,194,249,.16)}
    .admin-stat,.admin-stat:nth-child(1),.admin-stat:nth-child(2),.admin-stat:nth-child(3),.admin-stat:nth-child(4){border-color:rgba(224,198,246,.13);background:linear-gradient(145deg,rgba(255,255,255,.085),rgba(190,151,221,.045))}
    .admin-stat:nth-child(1) .admin-stat-icon{background:rgba(195,151,234,.18);color:#d9b7fb}
    .admin-stat:nth-child(2) .admin-stat-icon{background:rgba(125,200,174,.15);color:#a6e2c7}
    .admin-stat:nth-child(3) .admin-stat-icon{background:rgba(235,193,105,.16);color:#f2d17f}
    .admin-stat:nth-child(4) .admin-stat-icon{background:rgba(229,142,131,.15);color:#f2aaa1}
    .admin-panel{border-color:rgba(224,198,246,.12);background:linear-gradient(145deg,rgba(255,255,255,.07),rgba(184,148,213,.035))}
    .sales-bar.has-sales{background:linear-gradient(180deg,#e0bdff 0%,#b681ed 56%,#8458c4 100%);box-shadow:0 0 16px rgba(181,129,237,.16)}
    .sales-bar:hover{background:#ebd4ff}
    .top-rank{background:rgba(190,145,230,.17);color:#dfbafd}
    .admin-sidebar .admin-nav a.active{border-color:rgba(211,173,244,.28);background:linear-gradient(135deg,rgba(192,146,229,.26),rgba(255,255,255,.1));color:#f3e6ff}
    .rail-card,.rail-store-card{border-color:rgba(224,198,246,.12);background:rgba(255,255,255,.045)}
    .rail-store-card{background:linear-gradient(145deg,rgba(104,159,119,.12),rgba(255,255,255,.035))}
    .rail-period-card{background:linear-gradient(145deg,rgba(174,132,211,.1),rgba(255,255,255,.035))}
    .rail-store-card>a{color:#d9b3fa}
    .order-progress-button,.rider-link-button{border-color:rgba(202,160,237,.28);background:rgba(149,98,185,.2);color:#ead3ff}
    .order-progress-button:hover,.rider-link-button:hover{background:rgba(165,111,205,.31)}
    .admin-nav a:focus-visible,.rail-signout:focus-visible,.store-toggle:focus-visible,.period-form select:focus-visible,.order-progress-button:focus-visible{outline-color:#d7b5f4}
  </style>
  <link rel="stylesheet" href="../assets/css/logout.css?v=1">
</head>
<body class="admin-body">
<div class="admin-layout">
  <aside class="admin-sidebar">
    <a class="admin-brand" href="dashboard.php"><img class="admin-brand-mark" src="../logo.jpg" alt="MazSen Munch &amp; Sip logo"><span class="admin-brand-name">MAZSEN<small>MUNCH &amp; SIP</small></span></a>
    <p class="admin-nav-label">OWNER WORKSPACE</p>
    <nav class="admin-nav" aria-label="Admin navigation">
      <a class="active" href="dashboard.php" aria-label="Dashboard" title="Dashboard"><span class="admin-nav-icon"><i data-lucide="layout-dashboard"></i></span>Dashboard</a>
      <a href="logistics.php" aria-label="Logistics" title="Logistics"><span class="admin-nav-icon"><i data-lucide="map-pinned"></i></span>Logistics</a>
      <a href="#sales" aria-label="Analytics" title="Analytics"><span class="admin-nav-icon"><i data-lucide="bar-chart-3"></i></span>Analytics</a>
      <a href="#recent-orders" aria-label="Orders" title="Orders"><span class="admin-nav-icon"><i data-lucide="receipt-text"></i></span>Orders</a>
      <a href="#top-items" aria-label="Top items" title="Top items"><span class="admin-nav-icon"><i data-lucide="trending-up"></i></span>Top items</a>
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

    <section class="admin-panel orders-panel" id="recent-orders"><div class="admin-panel-heading"><div><h2>Recent orders</h2><p>Latest customer orders from the database</p></div><span class="period-chip"><?= count($recent) ?> recent</span></div><div class="orders-wrap"><table class="orders-table"><thead><tr><th>Order</th><th>Customer</th><th>Phone</th><th>Status</th><th>Progress</th><th>Rider map</th><th>Date</th><th style="text-align:right">Total</th></tr></thead><tbody><?php foreach ($recent as $order): $statusDisplay=['Received'=>'Pending','Processing'=>'Preparing','Ready'=>'On the way','Completed'=>'Delivered'][$order['status']] ?? $order['status']; $statusClass=strtolower(str_replace(' ','-',(string)$statusDisplay)); $nextStatuses=['Pending'=>'Confirmed','Confirmed'=>'Preparing','Preparing'=>'On the way','On the way'=>'Delivered']; $nextStatus=$nextStatuses[$statusDisplay] ?? null; $nextActions=['Pending'=>'Confirm order','Confirmed'=>'Start preparing','Preparing'=>'Send with rider','On the way'=>'Mark delivered']; $nextAction=$nextActions[$statusDisplay] ?? null; ?><tr><td class="order-code"><?= h($order['order_number']) ?></td><td class="order-customer" title="<?= h($order['customer_name'] ?? 'Legacy order') ?>"><span class="customer-profile-dot" aria-hidden="true"><?php if (!empty($order['customer_profile_image'])): ?><img src="../<?= h($order['customer_profile_image']) ?>" alt=""><?php else: ?><?= h(customerInitials($order['customer_name'] ?? 'Guest')) ?><?php endif; ?></span><span class="customer-profile-name"><?= h($order['customer_name'] ?? 'Legacy order') ?></span></td><td class="order-contact"><?= h($order['customer_phone'] ?? '—') ?></td><td><span class="order-status order-status-<?= h($statusClass) ?>" data-current-status><?= h($statusDisplay) ?></span></td><td><?php if ($nextStatus !== null): ?><button type="button" class="order-progress-button" data-order-number="<?= h($order['order_number']) ?>" data-next-status="<?= h($nextStatus) ?>" data-csrf="<?= h($orderCsrf) ?>"><?= h($nextAction) ?></button><?php else: ?><span class="order-done-label"><?= $statusDisplay === 'Delivered' ? 'Delivered' : h($statusDisplay) ?></span><?php endif; ?></td><td data-rider-cell><?php if ($statusDisplay === 'On the way'): ?><button type="button" class="rider-link-button" data-track-order-number="<?= h($order['order_number']) ?>" data-csrf="<?= h($orderCsrf) ?>">Create rider link<?= !empty($order['assigned_rider_name']) ? ' · '.h($order['assigned_rider_name']) : '' ?></button><?php else: ?><span class="order-done-label">—</span><?php endif; ?></td><td class="order-contact"><?= h(date('M j, Y · g:i A', strtotime($order['created_at']))) ?></td><td class="order-total"><?= peso($order['total']) ?></td></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td class="no-orders" colspan="8">No orders have been recorded yet.</td></tr><?php endif; ?></tbody></table></div></section>
  </main>
  <aside class="admin-insight-rail" aria-label="Store overview">
    <div class="rail-profile"><span class="rail-profile-avatar">M</span><div><strong>MazSen Munch &amp; Sip</strong><small>Store overview</small></div><i data-lucide="more-horizontal" aria-hidden="true"></i></div>
    <section class="rail-card rail-store-card"><span class="rail-kicker">STORE STATUS</span><div class="rail-store-state"><i class="<?= $storeOpen ? 'is-open' : 'is-closed' ?>"></i><strong><?= $storeOpen ? 'Open for orders' : 'Currently closed' ?></strong></div><p><?= $storeOpen ? 'Customers can place pickup and delivery orders.' : 'New customer orders are paused until you reopen.' ?></p><a href="#store-toggle">Manage store status <span aria-hidden="true">↗</span></a></section>
    <section class="rail-card rail-period-card"><span class="rail-kicker">THIS PERIOD</span><h2>Last <?= $days ?> days</h2><div class="rail-metric"><span>Orders</span><strong><?= number_format((int)$stats['orders_count']) ?></strong></div><div class="rail-metric"><span>Pending</span><strong><?= number_format((int)$stats['pending_count']) ?></strong></div><div class="rail-metric"><span>Average order</span><strong><?= peso($stats['average_order']) ?></strong></div></section>
    <section class="rail-latest"><span class="rail-kicker">LATEST ORDER</span><?php if ($recent): ?><div class="rail-latest-order"><span class="rail-order-avatar"><?= h(customerInitials($recent[0]['customer_name'] ?? 'Guest')) ?></span><div><strong><?= h($recent[0]['customer_name'] ?? 'Guest') ?></strong><small><?= h($recent[0]['order_number']) ?> · <?= h(date('M j, g:i A', strtotime($recent[0]['created_at']))) ?></small></div></div><div class="rail-latest-total"><span>Order total</span><strong><?= peso($recent[0]['total']) ?></strong></div><?php else: ?><p class="rail-empty">New orders will appear here.</p><?php endif; ?></section>
    <a class="rail-signout" href="logout.php"><i data-lucide="log-out" aria-hidden="true"></i>Sign out</a>
  </aside>
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
    if(result.status==='On the way'){const cell=row?.querySelector('[data-rider-cell]');if(cell){cell.replaceChildren();if(result.rider&&result.riderUrl){const linkButton=document.createElement('button');linkButton.type='button';linkButton.className='rider-link-button';linkButton.dataset.riderUrl=result.riderUrl;linkButton.textContent=`Copy link · ${result.rider.name}`;cell.append(linkButton);attachRiderLinkButton(linkButton);}else{const addRider=document.createElement('a');addRider.href='logistics.php#riders';addRider.className='rider-link-button';addRider.textContent='Add rider to auto-assign';cell.append(addRider);}}}
    if(nextStatus){button.dataset.nextStatus=nextStatus;button.textContent=nextAction;button.disabled=false;}
    else{button.replaceWith(Object.assign(document.createElement('span'),{className:'order-done-label',textContent:'Delivered'}));}
  }catch(error){alert(error.message||'Could not reach the server.');button.disabled=false;}
}));
function attachRiderLinkButton(button){button.addEventListener('click',async()=>{button.disabled=true;const original=button.textContent;button.textContent='Preparing link…';try{let url;if(button.dataset.riderUrl){url=new URL(button.dataset.riderUrl,window.location.href).href;}else{const response=await fetch('../includes/api.php?resource=rider-tracking',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({number:button.dataset.trackOrderNumber,csrf:button.dataset.csrf})});const result=await response.json();if(!response.ok)throw new Error(result.error||'Could not create rider link.');url=new URL(result.url,window.location.href).href;}try{await navigator.clipboard.writeText(url);alert('Rider link copied. Send it to the assigned rider.');}catch{window.prompt('Copy this rider tracking link and send it to the rider:',url);}button.textContent='Rider link copied';}catch(error){alert(error.message||'Could not reach the server.');button.textContent=original;}finally{button.disabled=false;}})}
document.querySelectorAll('[data-track-order-number]').forEach(attachRiderLinkButton);
</script>
</body>
</html>
