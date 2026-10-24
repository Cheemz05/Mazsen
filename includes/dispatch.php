<?php
declare(strict_types=1);

/** Pick the nearest recently located rider; fall back to lightest workload. */
function dispatch_pick_rider(PDO $pdo, ?float $latitude = null, ?float $longitude = null, array $exclude = []): ?array
{
    $query = $pdo->query("SELECT r.id, r.full_name, r.phone, r.current_latitude, r.current_longitude, r.location_updated_at,
            (SELECT COUNT(*) FROM orders o WHERE o.assigned_rider_id=r.id AND o.status IN ('Pending','Confirmed','Preparing','On the way','Ready')) AS active_load
        FROM delivery_riders r
        WHERE r.is_active = 1
        ORDER BY r.id");
    $riders = array_values(array_filter($query->fetchAll(), static fn(array $rider): bool => !in_array((int)$rider['id'], $exclude, true)));
    if (!$riders) return null;
    $now = time();
    foreach ($riders as &$rider) {
        $stamp = $rider['location_updated_at'] ? strtotime((string)$rider['location_updated_at']) : false;
        $rider['gps_fresh'] = $stamp !== false && $now - $stamp <= 300 && $rider['current_latitude'] !== null && $rider['current_longitude'] !== null;
        $rider['distance_km'] = null;
        if ($latitude !== null && $longitude !== null && $rider['gps_fresh']) {
            $latDelta = deg2rad($latitude - (float)$rider['current_latitude']);
            $lonDelta = deg2rad($longitude - (float)$rider['current_longitude']);
            $a = sin($latDelta / 2) ** 2 + cos(deg2rad($latitude)) * cos(deg2rad((float)$rider['current_latitude'])) * sin($lonDelta / 2) ** 2;
            $rider['distance_km'] = 6371 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
        }
    }
    unset($rider);
    usort($riders, static function (array $a, array $b) use ($latitude, $longitude): int {
        if ($latitude !== null && $longitude !== null) {
            if ($a['distance_km'] !== null && $b['distance_km'] !== null && $a['distance_km'] !== $b['distance_km']) return $a['distance_km'] <=> $b['distance_km'];
            if (($a['distance_km'] !== null) !== ($b['distance_km'] !== null)) return $a['distance_km'] !== null ? -1 : 1;
        } elseif ((int)$a['active_load'] !== (int)$b['active_load']) {
            return (int)$a['active_load'] <=> (int)$b['active_load'];
        }
        if ($a['gps_fresh'] !== $b['gps_fresh']) return $a['gps_fresh'] ? -1 : 1;
        if ((int)$a['active_load'] !== (int)$b['active_load']) return (int)$a['active_load'] <=> (int)$b['active_load'];
        return (int)$a['id'] <=> (int)$b['id'];
    });
    return $riders[0];
}

/** Offer a new order to the closest eligible rider, excluding riders who already declined. */
function dispatch_offer_next(PDO $pdo, int $orderId): ?array
{
    $find = $pdo->prepare('SELECT order_number, customer_latitude, customer_longitude FROM orders WHERE id=? AND status IN (\'Pending\',\'Confirmed\',\'Preparing\')');
    $find->execute([$orderId]); $order = $find->fetch();
    if (!$order) return null;
    $seen = $pdo->prepare('SELECT rider_id FROM order_rider_offers WHERE order_id=?'); $seen->execute([$orderId]);
    $exclude = array_map('intval', $seen->fetchAll(PDO::FETCH_COLUMN));
    $lat = $order['customer_latitude'] === null ? null : (float)$order['customer_latitude'];
    $lng = $order['customer_longitude'] === null ? null : (float)$order['customer_longitude'];
    $rider = dispatch_pick_rider($pdo, $lat, $lng, $exclude);
    if (!$rider) {
        $pdo->prepare("UPDATE orders SET rider_offer_status='No rider available' WHERE id=? AND assigned_rider_id IS NULL")->execute([$orderId]);
        return null;
    }
    $payout = (float)$pdo->query('SELECT rider_payout FROM store_settings WHERE id=1')->fetchColumn();
    $pdo->beginTransaction();
    try {
        $offer = $pdo->prepare("INSERT INTO order_rider_offers (order_id,rider_id,status,payout_amount,distance_km) VALUES (?,?,'Offered',?,?)");
        $offer->execute([$orderId, (int)$rider['id'], $payout, $rider['distance_km']]);
        $update = $pdo->prepare("UPDATE orders SET assigned_rider_id=?,rider_offer_status='Offered',rider_offer_at=NOW(),rider_accepted_at=NULL,rider_payout=? WHERE id=? AND assigned_rider_id IS NULL");
        $update->execute([(int)$rider['id'], $payout, $orderId]);
        if ($update->rowCount() !== 1) { $pdo->rollBack(); return null; }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return ['id'=>(int)$rider['id'],'name'=>(string)$rider['full_name'],'phone'=>(string)$rider['phone'],'payout'=>$payout,'distanceKm'=>$rider['distance_km']];
}

/** Assign an order and create a new private GPS link. Returns the one-time raw token. */
function dispatch_assign_order(PDO $pdo, string $number): ?array
{
    $find = $pdo->prepare("SELECT o.id, o.status, o.assigned_rider_id, r.id AS active_rider_id, r.full_name, r.phone
        FROM orders o LEFT JOIN delivery_riders r ON r.id = o.assigned_rider_id AND r.is_active = 1
        WHERE o.order_number = ? LIMIT 1");
    $find->execute([$number]);
    $order = $find->fetch();
    if (!$order || !in_array($order['status'], ['On the way','Ready'], true)) return null;

    if ($order['active_rider_id']) {
        $rider = ['id' => (int)$order['active_rider_id'], 'full_name' => $order['full_name'], 'phone' => $order['phone']];
    } else {
        $rider = dispatch_pick_rider($pdo);
    }
    if (!$rider) return null;

    $token = bin2hex(random_bytes(32));
    $update = $pdo->prepare('UPDATE orders SET assigned_rider_id = ?, rider_token_hash = ?, rider_latitude = NULL, rider_longitude = NULL, rider_location_updated_at = NULL WHERE id = ? AND status IN (\'On the way\',\'Ready\')');
    $update->execute([(int)$rider['id'], hash('sha256', $token), (int)$order['id']]);
    return ['id' => (int)$rider['id'], 'name' => (string)$rider['full_name'], 'phone' => (string)$rider['phone'], 'token' => $token];
}
?>