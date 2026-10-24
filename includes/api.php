<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/customer_auth.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/rider_auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dispatch.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$resource = $_GET['resource'] ?? 'products';

try {
    if ($resource === 'rider-portal-location') {
        $riderId = rider_user_id();
        if ($riderId === null) { http_response_code(401); echo json_encode(['error' => 'Sign in to the rider app first.']); exit; }
        $riderCheck = $pdo->prepare('SELECT is_active FROM delivery_riders WHERE id = ?'); $riderCheck->execute([$riderId]);
        if (!(bool)$riderCheck->fetchColumn()) { http_response_code(403); echo json_encode(['error' => 'This rider account is inactive.']); exit; }
        if ($method === 'GET') {
            $expiredQuery = $pdo->query("SELECT ofr.id,ofr.order_id,ofr.rider_id FROM order_rider_offers ofr JOIN orders o ON o.id=ofr.order_id WHERE ofr.status='Offered' AND ofr.offered_at < NOW() - INTERVAL 60 SECOND AND o.status IN ('Pending','Confirmed','Preparing') AND o.rider_offer_status='Offered' AND o.assigned_rider_id=ofr.rider_id ORDER BY ofr.offered_at LIMIT 20");
            foreach ($expiredQuery->fetchAll() as $expired) {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE order_rider_offers SET status='Expired',responded_at=NOW() WHERE id=? AND status='Offered'")->execute([(int)$expired['id']]);
                $pdo->prepare("UPDATE orders SET assigned_rider_id=NULL,rider_offer_status=NULL,rider_offer_at=NULL,rider_payout=NULL WHERE id=? AND assigned_rider_id=? AND rider_offer_status='Offered'")->execute([(int)$expired['order_id'],(int)$expired['rider_id']]);
                $pdo->commit();
                dispatch_offer_next($pdo,(int)$expired['order_id']);
            }
            $orders = $pdo->prepare("SELECT order_number AS number, customer_name AS customer, customer_phone AS phone, delivery_address AS address, status, total, rider_offer_status AS offerStatus, rider_payout AS riderPayout FROM orders WHERE assigned_rider_id = ? AND (status IN ('On the way','Ready','Preparing','Confirmed') OR (status='Pending' AND rider_offer_status='Accepted')) ORDER BY FIELD(status,'On the way','Ready','Preparing','Confirmed','Pending'), created_at ASC");
            $orders->execute([$riderId]);
            $offers = $pdo->prepare("SELECT o.order_number AS number,o.customer_name AS customer,o.customer_phone AS phone,o.delivery_address AS address,o.total,ofr.payout_amount AS payout,ofr.distance_km AS distanceKm,ofr.offered_at AS offeredAt FROM order_rider_offers ofr JOIN orders o ON o.id=ofr.order_id WHERE ofr.rider_id=? AND ofr.status='Offered' AND o.status IN ('Pending','Confirmed','Preparing') ORDER BY ofr.offered_at DESC");
            $offers->execute([$riderId]); echo json_encode(['orders' => $orders->fetchAll(), 'offers' => $offers->fetchAll()]); exit;
        }
        if ($method === 'POST') {
            $body = json_decode(file_get_contents('php://input'), true);
            $csrf = is_array($body) ? (string)($body['csrf'] ?? '') : '';
            if (empty($_SESSION['rider_location_csrf']) || !hash_equals($_SESSION['rider_location_csrf'], $csrf)) { http_response_code(403); echo json_encode(['error' => 'Session expired. Refresh and sign in again.']); exit; }
            $action = is_array($body) ? (string)($body['action'] ?? '') : '';
            if (in_array($action, ['accept-offer','decline-offer'], true)) {
                $number = trim((string)($body['number'] ?? ''));
                $offerQuery = $pdo->prepare("SELECT ofr.id,ofr.order_id FROM order_rider_offers ofr JOIN orders o ON o.id=ofr.order_id WHERE ofr.rider_id=? AND o.order_number=? AND ofr.status='Offered' AND o.status IN ('Pending','Confirmed','Preparing') LIMIT 1");
                $offerQuery->execute([$riderId,$number]); $offer = $offerQuery->fetch();
                if (!$offer) { http_response_code(409); echo json_encode(['error' => 'This delivery offer is no longer available.']); exit; }
                if ($action === 'accept-offer') {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE order_rider_offers SET status='Accepted',responded_at=NOW() WHERE id=? AND status='Offered'")->execute([(int)$offer['id']]);
                    $accepted = $pdo->prepare("UPDATE orders SET rider_offer_status='Accepted',rider_accepted_at=NOW() WHERE id=? AND assigned_rider_id=? AND rider_offer_status='Offered'");
                    $accepted->execute([(int)$offer['order_id'],$riderId]);
                    if ($accepted->rowCount() !== 1) { $pdo->rollBack(); http_response_code(409); echo json_encode(['error' => 'Another rider already accepted this order.']); exit; }
                    $pdo->commit(); echo json_encode(['success' => true, 'accepted' => true]); exit;
                }
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE order_rider_offers SET status='Declined',responded_at=NOW() WHERE id=? AND status='Offered'")->execute([(int)$offer['id']]);
                $pdo->prepare("UPDATE orders SET assigned_rider_id=NULL,rider_offer_status=NULL,rider_offer_at=NULL,rider_accepted_at=NULL,rider_payout=NULL WHERE id=? AND assigned_rider_id=? AND rider_offer_status='Offered'")->execute([(int)$offer['order_id'],$riderId]);
                $pdo->commit();
                $nextRider = dispatch_offer_next($pdo,(int)$offer['order_id']);
                echo json_encode(['success' => true, 'nextRider' => $nextRider ? $nextRider['name'] : null]); exit;
            }
            if (!in_array($action, ['', 'location'], true)) { http_response_code(422); echo json_encode(['error' => 'Invalid rider action.']); exit; }
            $latitude = filter_var($body['latitude'] ?? null, FILTER_VALIDATE_FLOAT); $longitude = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) { http_response_code(422); echo json_encode(['error' => 'A valid GPS location is required.']); exit; }
            $pdo->beginTransaction();
            $updateRider = $pdo->prepare('UPDATE delivery_riders SET current_latitude = ?, current_longitude = ?, location_updated_at = NOW() WHERE id = ? AND is_active = 1');
            $updateRider->execute([$latitude, $longitude, $riderId]);
            $updateOrders = $pdo->prepare("UPDATE orders SET rider_latitude = ?, rider_longitude = ?, rider_location_updated_at = NOW() WHERE assigned_rider_id = ? AND rider_offer_status='Accepted' AND status IN ('On the way','Ready')");
            $updateOrders->execute([$latitude, $longitude, $riderId]);
            $pdo->commit(); echo json_encode(['success' => true, 'updatedAt' => date(DATE_ATOM)]); exit;
        }
        http_response_code(405); echo json_encode(['error' => 'Unsupported rider request.']); exit;
    }

    if ($resource === 'logistics') {
        if (admin_user_id() === null || $method !== 'GET') { http_response_code(admin_user_id() === null ? 401 : 405); echo json_encode(['error' => 'Admin sign-in is required.']); exit; }
        $riders = $pdo->query("SELECT r.id, r.full_name AS name, r.phone, r.is_active AS active, (r.password_hash IS NOT NULL AND r.password_hash <> '') AS hasLogin, r.current_latitude AS latitude, r.current_longitude AS longitude, r.location_updated_at AS updatedAt, SUM(o.status IN ('On the way','Ready')) AS activeLoad FROM delivery_riders r LEFT JOIN orders o ON o.assigned_rider_id=r.id GROUP BY r.id ORDER BY r.is_active DESC, r.full_name")->fetchAll();
        $activeOrders = $pdo->query("SELECT o.order_number AS number,o.customer_name AS customer,o.customer_phone AS phone,o.delivery_address AS address,o.status,o.total,o.rider_offer_status AS offerStatus,o.rider_payout AS riderPayout,o.created_at AS createdAt,o.rider_latitude AS latitude,o.rider_longitude AS longitude,o.rider_location_updated_at AS locationUpdatedAt,r.id AS riderId,r.full_name AS riderName,r.phone AS riderPhone FROM orders o LEFT JOIN delivery_riders r ON r.id=o.assigned_rider_id WHERE o.status IN ('Pending','Confirmed','Preparing','On the way','Ready') ORDER BY o.created_at DESC LIMIT 100")->fetchAll();
        $payout = (float)$pdo->query('SELECT rider_payout FROM store_settings WHERE id=1')->fetchColumn();
        echo json_encode(['riders' => $riders, 'orders' => $activeOrders, 'riderPayout' => $payout]); exit;
    }

    if ($resource === 'logistics-settings' && $method === 'PATCH') {
        if (admin_user_id() === null) { http_response_code(401); echo json_encode(['error' => 'Admin sign-in is required.']); exit; }
        $body = json_decode(file_get_contents('php://input'), true);
        $csrf = is_array($body) ? (string)($body['csrf'] ?? '') : '';
        if (empty($_SESSION['admin_order_csrf']) || !hash_equals($_SESSION['admin_order_csrf'], $csrf)) { http_response_code(403); echo json_encode(['error' => 'Your session expired. Refresh and try again.']); exit; }
        $payout = filter_var($body['riderPayout'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($payout === false || $payout < 0 || $payout > 5000) { http_response_code(422); echo json_encode(['error' => 'Set a delivery payout between ₱0 and ₱5,000.']); exit; }
        $save = $pdo->prepare('UPDATE store_settings SET rider_payout=? WHERE id=1'); $save->execute([round($payout,2)]);
        echo json_encode(['success' => true, 'riderPayout' => round($payout,2)]); exit;
    }

    if ($resource === 'logistics-riders' && in_array($method, ['POST','PATCH'], true)) {
        if (admin_user_id() === null) { http_response_code(401); echo json_encode(['error' => 'Admin sign-in is required.']); exit; }
        $body = json_decode(file_get_contents('php://input'), true);
        $csrf = is_array($body) ? (string)($body['csrf'] ?? '') : '';
        if (empty($_SESSION['admin_order_csrf']) || !hash_equals($_SESSION['admin_order_csrf'], $csrf)) { http_response_code(403); echo json_encode(['error' => 'Your session expired. Refresh and try again.']); exit; }
        if (!is_array($body)) { http_response_code(422); echo json_encode(['error' => 'Invalid rider details.']); exit; }
        if ($method === 'POST') {
            $name = trim((string)($body['name'] ?? '')); $phone = trim((string)($body['phone'] ?? ''));
            $password = (string)($body['password'] ?? '');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || mb_strlen($phone) < 6 || mb_strlen($phone) > 30 || strlen($password) < 8 || strlen($password) > 200) { http_response_code(422); echo json_encode(['error' => 'Enter a rider name, phone number, and password with at least 8 characters.']); exit; }
            $insert = $pdo->prepare('INSERT INTO delivery_riders (full_name,phone,password_hash) VALUES (?,?,?)'); $insert->execute([$name,$phone,password_hash($password, PASSWORD_DEFAULT)]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]); exit;
        }
        $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) { http_response_code(422); echo json_encode(['error' => 'Invalid rider update.']); exit; }
        if (array_key_exists('password', $body)) {
            $password = (string)$body['password'];
            if (strlen($password) < 8 || strlen($password) > 200) { http_response_code(422); echo json_encode(['error' => 'Rider password must be at least 8 characters.']); exit; }
            $setPassword = $pdo->prepare('UPDATE delivery_riders SET password_hash = ? WHERE id = ?'); $setPassword->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            echo json_encode(['success' => true]); exit;
        }
        if (!array_key_exists('active', $body)) { http_response_code(422); echo json_encode(['error' => 'Invalid rider update.']); exit; }
        if (!(bool)$body['active']) {
            $load = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE assigned_rider_id = ? AND (status IN ('On the way','Ready') OR (status IN ('Pending','Confirmed','Preparing') AND rider_offer_status IN ('Offered','Accepted')))"); $load->execute([$id]);
            if ((int)$load->fetchColumn() > 0) { http_response_code(409); echo json_encode(['error' => 'This rider still has active deliveries. Reassign or finish them before deactivating.']); exit; }
        }
        $update = $pdo->prepare('UPDATE delivery_riders SET is_active = ? WHERE id = ?'); $update->execute([(int)(bool)$body['active'], $id]);
        if ((bool)$body['active']) {
            $waiting = $pdo->query("SELECT id FROM orders WHERE assigned_rider_id IS NULL AND rider_offer_status='No rider available' AND status IN ('Pending','Confirmed','Preparing') ORDER BY created_at ASC LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($waiting as $orderId) dispatch_offer_next($pdo,(int)$orderId);
        }
        echo json_encode(['success' => true]); exit;
    }

    if ($resource === 'logistics-dispatch' && $method === 'POST') {
        if (admin_user_id() === null) { http_response_code(401); echo json_encode(['error' => 'Admin sign-in is required.']); exit; }
        $body = json_decode(file_get_contents('php://input'), true);
        $csrf = is_array($body) ? (string)($body['csrf'] ?? '') : '';
        if (empty($_SESSION['admin_order_csrf']) || !hash_equals($_SESSION['admin_order_csrf'], $csrf)) { http_response_code(403); echo json_encode(['error' => 'Your session expired. Refresh and try again.']); exit; }
        $number = trim((string)($body['number'] ?? ''));
        if (!preg_match('/^MZ-[A-F0-9]{6}$/', $number)) { http_response_code(422); echo json_encode(['error' => 'Invalid order number.']); exit; }
        $rider = dispatch_assign_order($pdo, $number);
        if (!$rider) { http_response_code(409); echo json_encode(['error' => 'No active riders are available, or this order is not being delivered. Add or activate a rider first.']); exit; }
        echo json_encode(['success' => true, 'rider' => ['id' => $rider['id'], 'name' => $rider['name'], 'phone' => $rider['phone']], 'url' => '../rider.php?token=' . $rider['token']]); exit;
    }

    // Rider links are bearer credentials: only their SHA-256 hashes are stored.
    if ($resource === 'rider-location') {
        $token = (string)($_GET['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            http_response_code(401); echo json_encode(['error' => 'Invalid tracking link.']); exit;
        }
        $find = $pdo->prepare('SELECT order_number, status, assigned_rider_id, rider_latitude, rider_longitude, rider_location_updated_at FROM orders WHERE rider_token_hash = ? LIMIT 1');
        $find->execute([hash('sha256', $token)]);
        $trackedOrder = $find->fetch();
        if (!$trackedOrder) { http_response_code(404); echo json_encode(['error' => 'Tracking link is no longer active.']); exit; }
        if ($method === 'GET') {
            echo json_encode(['number' => $trackedOrder['order_number'], 'status' => $trackedOrder['status'] === 'Ready' ? 'On the way' : $trackedOrder['status'], 'latitude' => $trackedOrder['rider_latitude'] === null ? null : (float)$trackedOrder['rider_latitude'], 'longitude' => $trackedOrder['rider_longitude'] === null ? null : (float)$trackedOrder['rider_longitude'], 'updatedAt' => $trackedOrder['rider_location_updated_at']]);
            exit;
        }
        if ($method === 'POST') {
            if (!in_array($trackedOrder['status'], ['On the way', 'Ready'], true)) { http_response_code(409); echo json_encode(['error' => 'Location sharing is available only while the order is on the way.']); exit; }
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) { http_response_code(422); echo json_encode(['error' => 'A valid GPS location is required.']); exit; }
            $latitude = filter_var($body['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                http_response_code(422); echo json_encode(['error' => 'A valid GPS location is required.']); exit;
            }
            $update = $pdo->prepare("UPDATE orders SET rider_latitude = ?, rider_longitude = ?, rider_location_updated_at = NOW() WHERE rider_token_hash = ? AND status IN ('On the way','Ready')");
            $update->execute([$latitude, $longitude, hash('sha256', $token)]);
            if ($trackedOrder['assigned_rider_id']) {
                $updateRider = $pdo->prepare('UPDATE delivery_riders SET current_latitude = ?, current_longitude = ?, location_updated_at = NOW() WHERE id = ?');
                $updateRider->execute([$latitude, $longitude, (int)$trackedOrder['assigned_rider_id']]);
            }
            if ($update->rowCount() < 1) {
                $check = $pdo->prepare('SELECT rider_location_updated_at FROM orders WHERE rider_token_hash = ?'); $check->execute([hash('sha256', $token)]);
                if (!$check->fetchColumn()) { http_response_code(404); echo json_encode(['error' => 'Tracking link is no longer active.']); exit; }
            }
            echo json_encode(['success' => true, 'updatedAt' => date(DATE_ATOM)]); exit;
        }
        http_response_code(405); echo json_encode(['error' => 'Unsupported tracking request.']); exit;
    }

    if ($resource === 'rider-tracking' && $method === 'POST') {
        if (admin_user_id() === null) { http_response_code(401); echo json_encode(['error' => 'Admin sign-in is required.']); exit; }
        $body = json_decode(file_get_contents('php://input'), true);
        $csrf = is_array($body) ? (string)($body['csrf'] ?? '') : '';
        if (empty($_SESSION['admin_order_csrf']) || !hash_equals($_SESSION['admin_order_csrf'], $csrf)) { http_response_code(403); echo json_encode(['error' => 'Your session expired. Refresh the dashboard and try again.']); exit; }
        $number = trim((string)($body['number'] ?? ''));
        if (!preg_match('/^MZ-[A-F0-9]{6}$/', $number)) { http_response_code(422); echo json_encode(['error' => 'Invalid order number.']); exit; }
        $rider = dispatch_assign_order($pdo, $number);
        if (!$rider) { http_response_code(409); echo json_encode(['error' => 'Tracking links require an order on the way and at least one active rider in Logistics.']); exit; }
        echo json_encode(['url' => '../rider.php?token=' . $rider['token'], 'rider' => ['id' => $rider['id'], 'name' => $rider['name'], 'phone' => $rider['phone']]]); exit;
    }

    if ($resource === 'store' && $method === 'GET') {
        $isOpen = (bool)$pdo->query('SELECT is_open FROM store_settings WHERE id = 1')->fetchColumn();
        echo json_encode(['is_open' => $isOpen]);
        exit;
    }

    if ($resource === 'store' && $method === 'POST') {
        if (admin_user_id() === null) {
            http_response_code(401);
            echo json_encode(['error' => 'Admin sign-in is required to change store status.']);
            exit;
        }
        $body = json_decode(file_get_contents('php://input'), true);
        $csrf = is_array($body) ? (string)($body['csrf'] ?? '') : '';
        if (empty($_SESSION['admin_store_csrf']) || !hash_equals($_SESSION['admin_store_csrf'], $csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
            exit;
        }
        if (!is_array($body) || !array_key_exists('is_open', $body) || !is_bool($body['is_open'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Choose whether the store should be open or closed.']);
            exit;
        }
        $update = $pdo->prepare('UPDATE store_settings SET is_open = ? WHERE id = 1');
        $update->execute([$body['is_open'] ? 1 : 0]);
        echo json_encode(['is_open' => $body['is_open']]);
        exit;
    }

    if ($resource === 'products' && $method === 'GET') {
        // Older installations may predate the optional product image column.
        $hasProductImages = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='image_path'")->fetchColumn();
        $imageColumn = $hasProductImages ? 'image_path' : 'NULL AS image_path';
        $products = $pdo->query("SELECT slug AS id, name, category, price, emoji, description, {$imageColumn}, popular FROM products WHERE active = 1 ORDER BY sort_order, name")->fetchAll();
        // Recover the starter menu if every product was removed or deactivated.
        // This keeps the customer menu and order validation backed by the same rows.
        if (!$products) {
            $pdo->beginTransaction();
            $pdo->exec("INSERT INTO products (slug,name,category,price,emoji,description,popular,active,sort_order) VALUES
                ('classic-burger','Classic Burger','Burgers',55,'🍔','Juicy patty, fresh lettuce, tomato and our house sauce.',1,1,1),
                ('omg-overload','OMG Overload','Burgers',95,'🍔','Burger, ham, egg, mayo, ketchup and TLC.',1,1,2),
                ('classic-hotdog','Classic Hotdog Sandwich','Burgers',75,'🌭','TJ jumbo, mayo, ketchup and crisp coleslaw.',1,1,3),
                ('loaded-footlong','Loaded Footlong','Burgers',265,'🌭','Footlong with burger, egg, mayo, cheese and TLC.',0,1,4),
                ('chicken-poppers-rice','Chicken Poppers Rice','Silog Meals',95,'🍗','Crispy chicken poppers, rice and your choice of flavor.',1,1,5),
                ('hamsilog','Hamsilog','Silog Meals',80,'🍳','Ham, garlic rice and a sunny side up egg.',1,1,6),
                ('pork-tapsilog','Pork Tapsilog','Silog Meals',85,'🍳','Savory pork tapa with garlic rice and egg.',0,1,7),
                ('longganisilog','Longganisilog','Silog Meals',95,'🍳','Filipino longganisa, rice and egg, all in one plate.',0,1,8),
                ('chick-pop-n-fries','Chick Pop n Fries','Snacks',99,'🍟','Crispy chicken poppers over fries, drizzled with garlic mayo.',1,1,9),
                ('cheese-sticks','Cheese Sticks','Snacks',50,'🧀','Nine golden, crunchy cheese sticks.',0,1,10),
                ('dumplings','Dumplings','Snacks',50,'🥟','Nine pieces, served hot and ready to share.',0,1,11),
                ('dynamite','Dynamite','Snacks',55,'🌶️','Three crunchy, cheesy chili poppers.',0,1,12),
                ('regular-fries','Regular Fries','Fries',30,'🍟','Golden fries. Add cheese, BBQ or sour cream flavor.',1,1,13),
                ('medium-fries','Medium Fries','Fries',65,'🍟','A bigger serving of golden, crispy fries.',0,1,14),
                ('large-fries','Large Fries','Fries',95,'🍟','Our biggest fries for sharing or keeping.',0,1,15),
                ('classic-hungarian','Classic Hungarian Sandwich','Hungarian & Footlong',65,'🌭','Hungarian sausage with mayo, ketchup and coleslaw.',0,1,16),
                ('cheesy-footlong','Cheesy Footlong Sandwich','Hungarian & Footlong',155,'🌭','A jumbo footlong with a cheesy, savory finish.',0,1,17),
                ('classic-sandwich','Ham Sandwich','Burgers',55,'🥪','Ham, mayo, ketchup and coleslaw.',0,1,18),
                ('plain-rice','Plain Rice','Add-ons',15,'🍚','A warm serving of steamed white rice.',0,1,19),
                ('garlic-rice','Garlic Rice','Add-ons',20,'🍚','Savory garlic fried rice.',0,1,20),
                ('egg','Egg','Add-ons',15,'🍳','Add a freshly cooked egg to your meal.',0,1,21),
                ('burger-patty','Burger Patty','Add-ons',20,'🍔','Add an extra burger patty to your meal.',0,1,22)
                ON DUPLICATE KEY UPDATE active=1");
            $pdo->commit();
            $products = $pdo->query("SELECT slug AS id, name, category, price, emoji, description, {$imageColumn}, popular FROM products WHERE active = 1 ORDER BY sort_order, name")->fetchAll();
        }
        foreach ($products as &$product) {
            $product['price'] = (float)$product['price'];
            $product['popular'] = (bool)$product['popular'];
            // Serve the same fitted 4:3 artwork across existing database installs.
            $menuPhotos = [
                'classic-burger' => 'Photos/fitted-4x3-fill/Classic%20Burger.png',
                'omg-overload' => 'Photos/fitted-4x3-fill/OMG%20Overload.png',
                'classic-hotdog' => 'Photos/fitted-4x3-fill/Classic%20Hotdog%20Sandwich.png',
                'loaded-footlong' => 'Photos/fitted-4x3-fill/Loaded%20Footlong.png',
                'classic-sandwich' => 'Photos/fitted-4x3-fill/Ham%20Sandwich.png',
                'chicken-poppers-rice' => 'Photos/fitted-4x3-fill/Chicken%20Poppers%20Rice.png',
                'hamsilog' => 'Photos/fitted-4x3-fill/Hamsilog.png',
                'pork-tapsilog' => 'Photos/fitted-4x3-fill/Pork%20Tapsilog.png',
                'longganisilog' => 'Photos/fitted-4x3-fill/Longganisilog.png',
            ];
            if (isset($menuPhotos[$product['id']])) {
                $product['image_path'] = $menuPhotos[$product['id']] . '?v=4x3-fill';
            }
        }
        echo json_encode($products);
        exit;
    }

    if ($resource === 'orders' && $method === 'GET') {
        $customer = customer_user();
        if (!$customer) { http_response_code(401); echo json_encode(['error' => 'Sign in to view your orders.']); exit; }
        $ordersQuery = $pdo->prepare("SELECT o.id, o.order_number AS number, o.created_at AS createdAt, o.status, o.total, o.delivery_address AS address, o.customer_latitude, o.customer_longitude, IF(o.status IN ('On the way','Ready'),o.rider_latitude,NULL) AS rider_latitude, IF(o.status IN ('On the way','Ready'),o.rider_longitude,NULL) AS rider_longitude, IF(o.status IN ('On the way','Ready'),o.rider_location_updated_at,NULL) AS rider_location_updated_at, IF(o.rider_offer_status='Accepted' OR o.status IN ('On the way','Ready','Delivered'),r.full_name,NULL) AS rider_name FROM orders o LEFT JOIN delivery_riders r ON r.id=o.assigned_rider_id WHERE o.customer_id = ? ORDER BY o.id DESC");
        $ordersQuery->execute([$customer['id']]);
        $orders = $ordersQuery->fetchAll();
        $linesQuery = $pdo->prepare('SELECT oi.order_id, p.slug AS id, oi.product_name AS name, oi.quantity FROM order_items oi JOIN products p ON p.id = oi.product_id JOIN orders o ON o.id = oi.order_id WHERE o.customer_id = ? ORDER BY oi.id');
        $linesQuery->execute([$customer['id']]);
        $lines = $linesQuery->fetchAll();
        $byOrder = [];
        foreach ($lines as $line) {
            $byOrder[$line['order_id']][] = ['id' => $line['id'], 'name' => $line['name'], 'quantity' => (int)$line['quantity']];
        }
        foreach ($orders as &$order) {
            $order['total'] = (float)$order['total'];
            $order['items'] = $byOrder[$order['id']] ?? [];
            $order['deliveryLocation'] = $order['customer_latitude'] === null || $order['customer_longitude'] === null ? null : ['latitude' => (float)$order['customer_latitude'], 'longitude' => (float)$order['customer_longitude']];
            $order['riderLocation'] = $order['rider_latitude'] === null || $order['rider_longitude'] === null ? null : ['latitude' => (float)$order['rider_latitude'], 'longitude' => (float)$order['rider_longitude'], 'updatedAt' => $order['rider_location_updated_at']];
            $order['rider'] = $order['rider_name'] ? ['name' => $order['rider_name']] : null;
            unset($order['customer_latitude'], $order['customer_longitude'], $order['rider_latitude'], $order['rider_longitude'], $order['rider_location_updated_at'], $order['rider_name']);
            unset($order['id']);
        }
        echo json_encode($orders);
        exit;
    }

    if ($resource === 'orders' && $method === 'DELETE') {
        $customer = customer_user();
        if (!$customer) { http_response_code(401); echo json_encode(['error' => 'Sign in to manage your orders.']); exit; }
        $body = json_decode(file_get_contents('php://input'), true);
        $number = trim((string)($body['number'] ?? ''));
        if (!preg_match('/^MZ-[A-F0-9]{6}$/', $number)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid order number.']);
            exit;
        }
        $cancel = $pdo->prepare("UPDATE orders SET status = 'Cancelled', assigned_rider_id=NULL, rider_offer_status='Cancelled' WHERE order_number = ? AND customer_id = ? AND status IN ('Pending','Received')");
        $cancel->execute([$number, $customer['id']]);
        if ($cancel->rowCount() !== 1) {
            http_response_code(409);
            echo json_encode(['error' => 'This order is no longer pending and cannot be cancelled.']);
            exit;
        }
        $pdo->prepare("UPDATE order_rider_offers ofr JOIN orders o ON o.id=ofr.order_id SET ofr.status='Cancelled',ofr.responded_at=NOW() WHERE o.order_number=? AND ofr.status='Offered'")->execute([$number]);
        echo json_encode(['success' => true, 'status' => 'Cancelled']);
        exit;
    }

    if ($resource === 'orders' && $method === 'PATCH') {
        if (admin_user_id() === null) {
            http_response_code(401);
            echo json_encode(['error' => 'Admin sign-in is required to update order progress.']);
            exit;
        }
        $body = json_decode(file_get_contents('php://input'), true);
        $csrf = is_array($body) ? (string)($body['csrf'] ?? '') : '';
        if (empty($_SESSION['admin_order_csrf']) || !hash_equals($_SESSION['admin_order_csrf'], $csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'Your session expired. Refresh the dashboard and try again.']);
            exit;
        }
        $number = trim((string)($body['number'] ?? ''));
        if (!preg_match('/^MZ-[A-F0-9]{6}$/', $number)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid order number.']);
            exit;
        }
        $findOrder = $pdo->prepare('SELECT status, rider_offer_status FROM orders WHERE order_number = ? LIMIT 1');
        $findOrder->execute([$number]);
        $order = $findOrder->fetch();
        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found.']);
            exit;
        }
        $current = match ($order['status']) {
            'Received' => 'Pending',
            'Processing' => 'Preparing',
            'Ready' => 'On the way',
            'Completed' => 'Delivered',
            default => $order['status'],
        };
        $next = ['Pending'=>'Confirmed', 'Confirmed'=>'Preparing', 'Preparing'=>'On the way', 'On the way'=>'Delivered'][$current] ?? null;
        if ($next === null) {
            http_response_code(409);
            echo json_encode(['error' => 'This order has no further status step.']);
            exit;
        }
        if ($next === 'On the way' && $order['rider_offer_status'] === 'Offered') {
            http_response_code(409);
            echo json_encode(['error' => 'Waiting for the selected rider to accept this delivery offer.']);
            exit;
        }
        $updateOrder = $pdo->prepare("UPDATE orders SET status = ?, rider_token_hash = IF(? = 'Delivered', NULL, rider_token_hash), rider_latitude = IF(? = 'Delivered', NULL, rider_latitude), rider_longitude = IF(? = 'Delivered', NULL, rider_longitude), rider_location_updated_at = IF(? = 'Delivered', NULL, rider_location_updated_at) WHERE order_number = ? AND status = ?");
        $updateOrder->execute([$next, $next, $next, $next, $next, $number, $order['status']]);
        if ($updateOrder->rowCount() !== 1) {
            http_response_code(409);
            echo json_encode(['error' => 'The order changed. Refresh and try again.']);
            exit;
        }
        if ($next === 'Delivered') {
            $pdo->prepare("UPDATE order_rider_offers ofr JOIN orders o ON o.id=ofr.order_id SET ofr.status='Completed',ofr.responded_at=NOW() WHERE o.order_number=? AND ofr.status='Accepted'")->execute([$number]);
        }
        $assignedRider = $next === 'On the way' ? dispatch_assign_order($pdo, $number) : null;
        echo json_encode(['number' => $number, 'status' => $next, 'rider' => $assignedRider ? ['id' => $assignedRider['id'], 'name' => $assignedRider['name'], 'phone' => $assignedRider['phone']] : null, 'riderUrl' => $assignedRider ? '../rider.php?token=' . $assignedRider['token'] : null]);
        exit;
    }

    if ($resource === 'orders' && $method === 'POST') {
        $customer = customer_user();
        if (!$customer) { http_response_code(401); echo json_encode(['error' => 'Sign in or create an account before placing your order.']); exit; }
        $customerExists = $pdo->prepare('SELECT 1 FROM customers WHERE id = ? LIMIT 1');
        $customerExists->execute([$customer['id']]);
        if (!$customerExists->fetchColumn()) {
            unset($_SESSION['customer']);
            http_response_code(401);
            echo json_encode(['error' => 'Your account is not in this database anymore. Please create an account again or sign in.']);
            exit;
        }
        $addressQuery = $pdo->prepare('SELECT address FROM customers WHERE id = ? LIMIT 1');
        $addressQuery->execute([$customer['id']]);
        $deliveryAddress = trim((string)$addressQuery->fetchColumn());
        if (mb_strlen($deliveryAddress) < 8 || mb_strlen($deliveryAddress) > 500) {
            http_response_code(422);
            echo json_encode(['error' => 'Add your complete delivery address in Account Settings before ordering.']);
            exit;
        }
        $storeOpen = (bool)$pdo->query('SELECT is_open FROM store_settings WHERE id = 1')->fetchColumn();
        if (!$storeOpen) { http_response_code(409); echo json_encode(['error' => 'The store is closed right now and cannot accept new orders.']); exit; }
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || empty($body['items']) || !is_array($body['items'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Add at least one item before placing an order.']);
            exit;
        }
        $pdo->beginTransaction();
        $getProduct = $pdo->prepare('SELECT id, slug, name, price FROM products WHERE slug = ? AND active = 1');
        $items = [];
        $total = 0;
        foreach ($body['items'] as $row) {
            $quantity = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            $getProduct->execute([(string)($row['id'] ?? '')]);
            $product = $getProduct->fetch();
            if (!$product || !$quantity) {
                throw new InvalidArgumentException('An item in your cart is unavailable. Please refresh the menu.');
            }
            $items[] = [$product, $quantity];
            $total += (float)$product['price'] * $quantity;
        }
        $customerLatitude = isset($body['customerLocation']['latitude']) ? filter_var($body['customerLocation']['latitude'], FILTER_VALIDATE_FLOAT) : null;
        $customerLongitude = isset($body['customerLocation']['longitude']) ? filter_var($body['customerLocation']['longitude'], FILTER_VALIDATE_FLOAT) : null;
        if ($customerLatitude === false || $customerLongitude === false || ($customerLatitude !== null && ($customerLatitude < -90 || $customerLatitude > 90)) || ($customerLongitude !== null && ($customerLongitude < -180 || $customerLongitude > 180))) { $customerLatitude = null; $customerLongitude = null; }
        if ($customerLatitude === null || $customerLongitude === null) { $customerLatitude = null; $customerLongitude = null; }
        $number = 'MZ-' . strtoupper(bin2hex(random_bytes(3)));
        $insertOrder = $pdo->prepare("INSERT INTO orders (customer_id, customer_name, customer_phone, delivery_address, customer_latitude, customer_longitude, order_number, status, total) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
        $insertOrder->execute([$customer['id'], $customer['full_name'], $customer['phone'], $deliveryAddress, $customerLatitude, $customerLongitude, $number, $total]);
        $orderId = (int)$pdo->lastInsertId();
        $insertLine = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity) VALUES (?, ?, ?, ?, ?)');
        foreach ($items as [$product, $quantity]) {
            $insertLine->execute([$orderId, $product['id'], $product['name'], $product['price'], $quantity]);
        }
        $pdo->commit();
        $riderOffer = dispatch_offer_next($pdo, $orderId);
        http_response_code(201);
        echo json_encode(['number' => $number, 'createdAt' => date(DATE_ATOM), 'status' => 'Pending', 'total' => $total, 'address' => $deliveryAddress, 'riderOfferSent' => $riderOffer !== null,
            'items' => array_map(static fn($entry) => ['id' => $entry[0]['slug'], 'name' => $entry[0]['name'], 'quantity' => $entry[1]], $items)]);
        exit;
    }
    http_response_code(405);
    echo json_encode(['error' => 'Unsupported API request.']);
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('MazSen API failure [' . $resource . ']: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'The request could not be completed.']);
}
