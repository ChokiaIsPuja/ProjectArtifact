<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../../conn.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['player_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data payload']);
    exit;
}

$player_id = intval($data['player_id']);
$exp_gained = intval($data['exp_gained']);
$items_dropped = $data['items_dropped'] ?? [];
$current_hp = isset($data['current_hp']) ? intval($data['current_hp']) : null;

mysqli_begin_transaction($conn);

try {
    // 1. Get current Level, EXP, Attribute Points, and Max HP
    $query = "SELECT p.level, p.exp, p.attribute_points, ps.curr_max_hp
              FROM player p 
              JOIN player_stats ps ON p.player_id = ps.player_id 
              WHERE p.player_id = ? FOR UPDATE";
              
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $player_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $player = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$player) {
        throw new Exception("Character database entry not found.");
    }

    $current_level = intval($player['level']); 
    $current_exp = intval($player['exp']) + $exp_gained;
    $current_attr_points = intval($player['attribute_points'] ?? 0);
    
    $leveled_up = false;
    $points_gained = 0;

    // 2. Process Level Up triggers (100 EXP requirement flat)
    if ($current_exp >= 100) {
        $leveled_up = true;
        $current_level += 1;
        $current_exp -= 100; 

        // --- ATTRIBUTE POINT LOGIC ---
        // Every 5 levels gain 3 points, otherwise gain 1 point
        if ($current_level % 5 === 0) {
            $points_gained = 3;
        } else {
            $points_gained = 1;
        }
        $current_attr_points += $points_gained;

        // Fully heal the player back to their max health tier as a level-up bonus
        $base_max_hp = intval($player['curr_max_hp']);
        $update_hp_query = "UPDATE player_stats SET curr_hp = ? WHERE player_id = ?";
        $stmt_hp = mysqli_prepare($conn, $update_hp_query);
        mysqli_stmt_bind_param($stmt_hp, "ii", $base_max_hp, $player_id);
        mysqli_stmt_execute($stmt_hp);
        mysqli_stmt_close($stmt_hp);

    } else {
        // If the character did NOT level up, save mid-combat damage parameters safely
        if ($current_hp !== null) {
            $update_hp_query = "UPDATE player_stats SET curr_hp = ? WHERE player_id = ?";
            $stmt_hp = mysqli_prepare($conn, $update_hp_query);
            mysqli_stmt_bind_param($stmt_hp, "ii", $current_hp, $player_id);
            mysqli_stmt_execute($stmt_hp);
            mysqli_stmt_close($stmt_hp);
        }
    }

    // 3. Save Level, EXP, and Attribute Points back into the player table
    $update_player = "UPDATE player SET level = ?, exp = ?, attribute_points = ? WHERE player_id = ?";
    $stmt_p = mysqli_prepare($conn, $update_player);
    mysqli_stmt_bind_param($stmt_p, "iiii", $current_level, $current_exp, $current_attr_points, $player_id);
    mysqli_stmt_execute($stmt_p);
    mysqli_stmt_close($stmt_p);

    // 4. Record Loot Drops to Bag inventory table
    if (!empty($items_dropped)) {
        foreach ($items_dropped as $item) {
            $item_id = intval($item['item_id']);
            
            $bag_query = "INSERT INTO bag (player_id, item_id, qty) VALUES (?, ?, 1) 
                          ON DUPLICATE KEY UPDATE qty = qty + 1";
            $stmt_bag = mysqli_prepare($conn, $bag_query);
            mysqli_stmt_bind_param($stmt_bag, "ii", $player_id, $item_id);
            mysqli_stmt_execute($stmt_bag);
            mysqli_stmt_close($stmt_bag);
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'leveled_up' => $leveled_up,
        'new_level' => $current_level,
        'current_exp' => $current_exp,
        'points_gained' => $points_gained,
        'total_points' => $current_attr_points
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}