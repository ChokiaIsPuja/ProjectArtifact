<?php
include __DIR__ . '/../../../conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_id     = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
    $bag_id        = isset($_POST['bag_id']) ? intval($_POST['bag_id']) : 0;
    // Catch the total calculated max health pool sent from your client view layout
    $client_max_hp = isset($_POST['client_max_hp']) ? intval($_POST['client_max_hp']) : 100;

    if ($player_id <= 0 || $bag_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction metrics.']);
        exit;
    }

    try {
        mysqli_begin_transaction($conn);

        // 1. FETCH CONFIG: Cleanly joined via it.item_id = ia.item_id
        $query = "SELECT 
                    b.qty, 
                    it.item_id,
                    it.item_type, 
                    it.item_name,
                    ia.att_heal,   -- Restoration value (Potion)
                    ia.att_max_hp, -- Permanent Max HP upgrade value (Elixir)
                    ia.att_str, 
                    ia.att_def, 
                    ia.att_dex,
                    ia.att_int,
                    ia.att_fth
                  FROM bag b
                  INNER JOIN item it ON b.item_id = it.item_id
                  LEFT JOIN item_attributes ia ON it.item_id = ia.item_id 
                  WHERE b.bag_id = ? AND b.player_id = ? FOR UPDATE";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $bag_id, $player_id);
        mysqli_stmt_execute($stmt);
        $item_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        // Validation safety checks
        if (!$item_data) {
            throw new Exception("Item not found in your inventory.");
        }
        
        $item_type = strtolower(trim($item_data['item_type']));
        if ($item_type !== 'consumable' && $item_type !== 'consumables') {
            throw new Exception("This item type cannot be consumed.");
        }
        if ($item_data['qty'] <= 0) {
            throw new Exception("You do not have any left to use.");
        }

        // 2. Load current player live tracking states from your stats table row
        $stats_query = "SELECT curr_hp, curr_max_hp, curr_str, curr_def, curr_dex, curr_int, curr_fth 
                        FROM player_stats 
                        WHERE player_id = ? FOR UPDATE";
        $stmt_stats = mysqli_prepare($conn, $stats_query);
        mysqli_stmt_bind_param($stmt_stats, "i", $player_id);
        mysqli_stmt_execute($stmt_stats);
        $player_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stats));
        $mysqli_stats_close = mysqli_stmt_close($stmt_stats);

        if (!$player_stats) {
            throw new Exception("Player stats record missing.");
        }
        
        $message = "You used " . htmlspecialchars($item_data['item_name']) . ".";
        $effect_applied = false;

        // 4. PROCESS POTION MECHANICS: Safely updates matched column attributes

        // --- TYPE A1: STANDARD HEALING MECHANIC (POTION) ---
        if (intval($item_data['att_heal']) > 0) {
            $heal_val = intval($item_data['att_heal']);
            $current_hp = intval($player_stats['curr_hp']);
            
            if ($current_hp >= $client_max_hp) {
                throw new Exception("Your health bar is already completely full!");
            }
            
            // Safe addition capped at total maximum setup
            $new_hp = min($client_max_hp, $current_hp + $heal_val);
            
            $update_query = "UPDATE player_stats SET curr_hp = ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "ii", $new_hp, $player_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
            
            $message = "Healed for +{$heal_val} HP!";
            $effect_applied = true;
        }

        // --- TYPE A2: PERMANENT MAX HP INCREASE MECHANIC (ELIXIR/UPGRADE) ---
        if (intval($item_data['att_max_hp']) > 0) {
            $max_hp_boost = intval($item_data['att_max_hp']);
            
            // Increment both current max hp AND give them the current hp as immediate bonus health
            $update_query = "UPDATE player_stats SET curr_max_hp = curr_max_hp + ?, curr_hp = curr_hp + ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "iii", $max_hp_boost, $max_hp_boost, $player_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
            
            $message = "Maximum HP permanently increased by +{$max_hp_boost}!";
            $effect_applied = true;
        }
        
        // --- TYPE B: PERMANENT STRENGTH STAT SUPPLEMENT ---
        if (intval($item_data['att_str']) > 0) {
            $boost = intval($item_data['att_str']);
            $update_query = "UPDATE player_stats SET curr_str = curr_str + ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "ii", $boost, $player_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
            
            $message = "Strength permanently increased by +{$boost}!";
            $effect_applied = true;
        }

        // --- TYPE C: PERMANENT DEFENSE STAT SUPPLEMENT ---
        if (intval($item_data['att_def']) > 0) {
            $boost = intval($item_data['att_def']);
            $update_query = "UPDATE player_stats SET curr_def = curr_def + ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "ii", $boost, $player_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
            
            $message = "Defense permanently increased by +{$boost}!";
            $effect_applied = true;
        }

        // --- TYPE D: PERMANENT DEXTERITY STAT SUPPLEMENT ---
        if (intval($item_data['att_dex']) > 0) {
            $boost = intval($item_data['att_dex']);
            $update_query = "UPDATE player_stats SET curr_dex = curr_dex + ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "ii", $boost, $player_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
            
            $message = "Dexterity permanently increased by +{$boost}!";
            $effect_applied = true;
        }

        // --- TYPE E: PERMANENT INTELLIGENCE STAT SUPPLEMENT ---
        if (intval($item_data['att_int']) > 0) {
            $boost = intval($item_data['att_int']);
            $update_query = "UPDATE player_stats SET curr_int = curr_int + ? WHERE player_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt_update, "ii", $boost, $player_id);
            mysqli_stmt_execute($stmt_update);
            mysqli_stmt_close($stmt_update);
            
            $message = "Intelligence permanently increased by +{$boost}!";
            $effect_applied = true;
        }

        if (!$effect_applied) {
            throw new Exception("This consumable has no valid modifiers mapped to execute calculations with.");
        }

        // 5. Inventory Deduction Processing pass
        if (intval($item_data['qty']) > 1) {
            $bag_update = "UPDATE bag SET qty = qty - 1 WHERE bag_id = ?";
        } else {
            $bag_update = "DELETE FROM bag WHERE bag_id = ?";
        }
        $stmt_bag = mysqli_prepare($conn, $bag_update);
        mysqli_stmt_bind_param($stmt_bag, "i", $bag_id);
        mysqli_stmt_execute($stmt_bag);
        mysqli_stmt_close($stmt_bag);

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => $message]);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>