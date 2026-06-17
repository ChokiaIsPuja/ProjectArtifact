<?php
include __DIR__ . '/../../../conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
    $bag_id    = isset($_POST['bag_id']) ? intval($_POST['bag_id']) : 0;

    if ($player_id <= 0 || $bag_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid drop parameters.']);
        exit;
    }

    try {
        mysqli_begin_transaction($conn);

        // 1. Safety verification: verify the item actually belongs to this player character
        $check_query = "SELECT bag_id FROM bag WHERE bag_id = ? AND player_id = ? LIMIT 1";
        $stmt_check = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt_check, "ii", $bag_id, $player_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        $item_exists = mysqli_stmt_num_rows($stmt_check) > 0;
        mysqli_stmt_close($stmt_check);

        if (!$item_exists) {
            throw new Exception("Item not found or does not belong to your inventory.");
        }

        // 2. Extra Safety Check: Prevent deleting items if they are sitting in player_equipment
        // ✅ FIXED: Switched to SELECT 1 to bypass looking up a non-existent 'equip_id' column
        $equip_check = "SELECT 1 FROM player_equipment WHERE bag_id = ? AND player_id = ? LIMIT 1";
        $stmt_equip = mysqli_prepare($conn, $equip_check);
        mysqli_stmt_bind_param($stmt_equip, "ii", $bag_id, $player_id);
        mysqli_stmt_execute($stmt_equip);
        mysqli_stmt_store_result($stmt_equip);
        $is_equipped = mysqli_stmt_num_rows($stmt_equip) > 0;
        mysqli_stmt_close($stmt_equip);

        if ($is_equipped) {
            throw new Exception("Cannot discard item! You must unequip it from your gear slots first.");
        }

        // 3. Purge transaction entry from inventory stack
        $delete_query = "DELETE FROM bag WHERE bag_id = ? AND player_id = ?";
        $stmt_delete = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt_delete, "ii", $bag_id, $player_id);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Item discarded permanently.']);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request delivery channel.']);
}
?>