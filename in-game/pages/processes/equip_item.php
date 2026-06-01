<?php
// equip_items.php
header('Content-Type: application/json');

// 🛠️ FIX: Added the missing forward slash right after __DIR__
include __DIR__ . '/../../../conn.php';
// Fallback protection check for incoming POST data
$player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
$bag_id    = isset($_POST['bag_id']) ? intval($_POST['bag_id']) : 0;

if ($player_id <= 0 || $bag_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid transaction context payloads supplied.']);
    exit;
}

try {
    // 1. Verify the item exists inside this player's bag and get its classification type
    $item_query = "SELECT b.bag_id, it.item_type 
                   FROM bag b 
                   INNER JOIN item it ON b.item_id = it.item_id 
                   WHERE b.bag_id = ? AND b.player_id = ? 
                   LIMIT 1";
                   
    $stmt = mysqli_prepare($conn, $item_query);
    if (!$stmt) {
        throw new Exception("Item query prepare failed: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $bag_id, $player_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item_data = mysqli_fetch_assoc($result);
    
    if (!$item_data) {
        echo json_encode(['success' => false, 'error' => 'Target item was not found in the player inventory records.']);
        exit;
    }

    // Normalized lookup keys to match your sidebar mapping structure
    $raw_type = strtolower(trim($item_data['item_type']));
    $slot_name = $raw_type;
    
    // Remap custom database types to their exact sidebar UI matching array keys
    if (in_array($raw_type, ['weapon', 'armaments'])) {
        $slot_name = 'armaments';
    } elseif (in_array($raw_type, ['accessory', 'accessories'])) {
        $slot_name = 'accessory'; // Explicitly set to singular to match your updated array key!
    }

    // 2. Clear old slot data: Look for any equipment row matching this specific slot name tied to the player's bag items
    // 2. Clear old slot data using direct player_id assignment
    $clear_query = "DELETE FROM player_equipment WHERE player_id = ? AND slot_name = ?";
    $clear_stmt = mysqli_prepare($conn, $clear_query);
    mysqli_stmt_bind_param($clear_stmt, "is", $player_id, $slot_name);
    mysqli_stmt_execute($clear_stmt);

    // 3. Bind the new equipment row including player_id!
    $equip_query = "INSERT INTO player_equipment (player_id, bag_id, slot_name) VALUES (?, ?, ?)";
    $equip_stmt = mysqli_prepare($conn, $equip_query);
    mysqli_stmt_bind_param($equip_stmt, "iis", $player_id, $bag_id, $slot_name);
    mysqli_stmt_execute($equip_stmt);

    // All transaction stages passed cleanly! Return success signal
    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database operation transaction tracking failed unexpectedly.']);
    exit;
}