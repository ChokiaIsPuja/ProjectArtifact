<?php
include __DIR__ . '/../../../conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_id     = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
    $bag_id        = isset($_POST['bag_id']) ? intval($_POST['bag_id']) : 0;
    // ✅ NEW: Catch the max health pool sent from your client page math
    $client_max_hp = isset($_POST['client_max_hp']) ? intval($_POST['client_max_hp']) : 100;

    if ($player_id <= 0 || $bag_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction metrics.']);
        exit;
    }

    try {
        mysqli_begin_transaction($conn);

        // 1. Fetch item configuration and attributes using your exact database columns
        $query = "SELECT 
                    b.qty, 
                    it.item_type, 
                    it.item_name,
                    ia.att_hp, 
                    ia.att_atk, 
                    ia.att_def, 
                    ia.att_spd
                  FROM bag b
                  INNER JOIN item it ON b.item_id = it.item_id
                  LEFT JOIN item_attributes ia ON it.id_item_attributes = ia.id_item_attributes
                  WHERE b.bag_id = ? AND b.player_id = ? FOR UPDATE";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $bag_id, $player_id);
        mysqli_stmt_execute($stmt);
        $item_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        // Validation safety checks
        if (!$item_data) {
            throw new Exception("Item not found in your inventory.");
        }
        if (strtolower(trim($item_data['item_type'])) !== 'consumables') {
            throw new Exception("This item type cannot be consumed.");
        }
        if ($item_data['qty'] <= 0) {
            throw new Exception("You do not have any left to use.");
        }

        // 2. Load current player health stats 
        // ✅ FIXED: Changed column 'curr_hp' to your actual column name 'curr_max_hp'
        $stats_query = "SELECT curr_max_hp FROM player_stats WHERE player_id = ? FOR UPDATE";
        $stmt_stats = mysqli_prepare($conn, $stats_query);
        mysqli_stmt_bind_param($stmt_stats, "i", $player_id);
        mysqli_stmt_execute($stmt_stats);
        $player_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stats));

        if (!$player_stats) {
            throw new Exception("Player stats record missing.");
        }

        // ✅ REMOVED: Section 3 (The heavy SQL gear calculations are completely gone!)
        
        $message = "You used " . htmlspecialchars($item_data['item_name']) . ".";
        $effect_applied = false;

        // 4. DETECT MECHANIC TYPE: Read column values dynamically
        
        // --- TYPE A: HEALING OPERATION (`att_hp` > 0) ---
        if (intval($item_data['att_hp']) > 0) {
            $heal_val = intval($item_data['att_hp']);
            
            // ✅ FIXED: Read from 'curr_max_hp' and clamp using your 'client_max_hp' payload
            $new_hp = min($client_max_hp, intval($player_stats['curr_max_hp']) + $heal_val);
            
            // ✅ FIXED: Changed table update target from 'curr_hp' to 'curr_max_hp'
            $update_query = "UPDATE player_stats SET curr_max_hp = ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "ii", $new_hp, $player_id);
            mysqli_stmt_execute($stmt_update);
            
            $message = "Healed for +{$heal_val} HP!";
            $effect_applied = true;
        }
        
        // --- TYPE B: PERMANENT ATTACK INCREASE (`att_atk` > 0) ---
        if (intval($item_data['att_atk']) > 0) {
            $atk_val = intval($item_data['att_atk']);
            $update_query = "UPDATE player_stats SET attack = attack + ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "ii", $atk_val, $player_id);
            mysqli_stmt_execute($stmt_update);
            
            $message = "Attack permanently increased by +{$atk_val}!";
            $effect_applied = true;
        }

        // --- TYPE C: PERMANENT DEFENSE INCREASE (`att_def` > 0) ---
        if (intval($item_data['att_def']) > 0) {
            $def_val = intval($item_data['att_def']);
            $update_query = "UPDATE player_stats SET defense = defense + ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "ii", $def_val, $player_id);
            mysqli_stmt_execute($stmt_update);
            
            $message = "Defense permanently increased by +{$def_val}!";
            $effect_applied = true;
        }

        // --- TYPE D: PERMANENT SPEED INCREASE (`att_spd` > 0) ---
        if (intval($item_data['att_spd']) > 0) {
            $spd_val = intval($item_data['att_spd']);
            $update_query = "UPDATE player_stats SET spd = spd + ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "ii", $spd_val, $player_id);
            mysqli_stmt_execute($stmt_update);
            
            $message = "Speed permanently increased by +{$spd_val}!";
            $effect_applied = true;
        }

        if (!$effect_applied) {
            throw new Exception("This consumable has no attributes mapped to execute code with.");
        }

        // 5. Inventory Deduction (Reduce quantity stack or clear row entirely)
        if ($item_data['qty'] > 1) {
            $bag_update = "UPDATE bag SET qty = qty - 1 WHERE bag_id = ?";
        } else {
            $bag_update = "DELETE FROM bag WHERE bag_id = ?";
        }
        $stmt_bag = mysqli_prepare($conn, $bag_update);
        mysqli_stmt_bind_param($stmt_bag, "i", $bag_id);
        mysqli_stmt_execute($stmt_bag);

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => $message]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>