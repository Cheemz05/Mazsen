<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/customer_auth.php';
require __DIR__ . '/admin_auth.php';
require __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$resource = $_GET['resource'] ?? 'products';

try {
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
        $products = $pdo->query('SELECT slug AS id, name, category, price, emoji, description, popular FROM products WHERE active = 1 ORDER BY sort_order, name')->fetchAll();
        foreach ($products as &$product) {
            $product['price'] = (float)$product['price'];
            $product['popular'] = (bool)$product['popular'];
        }
        echo json_encode($products);
        exit;
    }

    if ($resource === 'orders' && $method === 'GET') {
        $customer = customer_user();
        if (!$customer) { http_response_code(401); echo json_encode(['error' => 'Sign in to view your orders.']); exit; }
        $ordersQuery = $pdo->prepare('SELECT id, order_number AS number, created_at AS createdAt, status, total FROM orders WHERE customer_id = ? ORDER BY id DESC');
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
        $cancel = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_number = ? AND customer_id = ? AND status IN ('Pending','Received')");
        $cancel->execute([$number, $customer['id']]);
        if ($cancel->rowCount() !== 1) {
            http_response_code(409);
            echo json_encode(['error' => 'This order is no longer pending and cannot be cancelled.']);
            exit;
        }
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
        $findOrder = $pdo->prepare('SELECT status FROM orders WHERE order_number = ? LIMIT 1');
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
        $updateOrder = $pdo->prepare('UPDATE orders SET status = ? WHERE order_number = ? AND status = ?');
        $updateOrder->execute([$next, $number, $order['status']]);
        if ($updateOrder->rowCount() !== 1) {
            http_response_code(409);
            echo json_encode(['error' => 'The order changed. Refresh and try again.']);
            exit;
        }
        echo json_encode(['number' => $number, 'status' => $next]);
        exit;
    }

    if ($resource === 'orders' && $method === 'POST') {
        $customer = customer_user();
        if (!$customer) { http_response_code(401); echo json_encode(['error' => 'Sign in or create an account before placing your order.']); exit; }
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
        $number = 'MZ-' . strtoupper(bin2hex(random_bytes(3)));
        $insertOrder = $pdo->prepare("INSERT INTO orders (customer_id, customer_name, customer_phone, delivery_address, order_number, status, total) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
        $insertOrder->execute([$customer['id'], $customer['full_name'], $customer['phone'], $customer['address'], $number, $total]);
        $orderId = (int)$pdo->lastInsertId();
        $insertLine = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity) VALUES (?, ?, ?, ?, ?)');
        foreach ($items as [$product, $quantity]) {
            $insertLine->execute([$orderId, $product['id'], $product['name'], $product['price'], $quantity]);
        }
        $pdo->commit();
        http_response_code(201);
        echo json_encode(['number' => $number, 'createdAt' => date(DATE_ATOM), 'status' => 'Pending', 'total' => $total,
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
    http_response_code(500);
    echo json_encode(['error' => 'The request could not be completed.']);
}
