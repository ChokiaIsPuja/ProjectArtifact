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

mysqli_begin_transaction($conn);

try {
    // 1. Get current Level, EXP, and Stats (Changed p.player_level to p.level)
    $query = "SELECT p.level, p.exp, ps.curr_max_hp, ps.curr_atk, ps.curr_def, ps.curr_spd 
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

    // Changed to match your exact database column ['level']
    $current_level = intval($player['level']); 
    $current_exp = intval($player['exp']) + $exp_gained;
    $leveled_up = false;
    $chosen_stat = null;
    $stat_increase = 0;

    // 2. Process Level Up triggers (100 EXP requirement flat)
    if ($current_exp >= 100) {
        $leveled_up = true;
        $current_level += 1;
        $current_exp -= 100; 

        $stats = [
            'curr_max_hp' => intval($player['curr_max_hp']),
            'curr_atk'    => intval($player['curr_atk']),
            'curr_def'    => intval($player['curr_def']),
            'curr_spd'    => intval($player['curr_spd'])
        ];

        $stat_keys = array_keys($stats);
        $chosen_stat = $stat_keys[array_rand($stat_keys)];
        
        $stat_increase = floor(($stats[$chosen_stat] / 10) + 2);
        $new_stat_value = $stats[$chosen_stat] + $stat_increase;

        if ($chosen_stat === 'curr_max_hp') {
            $update_stats_query = "UPDATE player_stats SET curr_max_hp = ?, curr_hp = ? WHERE player_id = ?";
            $stmt_stat = mysqli_prepare($conn, $update_stats_query);
            mysqli_stmt_bind_param($stmt_stat, "iii", $new_stat_value, $new_stat_value, $player_id);
        } else {
            $update_stats_query = "UPDATE player_stats SET $chosen_stat = ? WHERE player_id = ?";
            $stmt_stat = mysqli_prepare($conn, $update_stats_query);
            mysqli_stmt_bind_param($stmt_stat, "ii", $new_stat_value, $player_id);
        }
        mysqli_stmt_execute($stmt_stat);
        mysqli_stmt_close($stmt_stat);
    }

    // 3. Save Level and EXP (Changed player_level = ? to level = ?)
    $update_player = "UPDATE player SET level = ?, exp = ? WHERE player_id = ?";
    $stmt_p = mysqli_prepare($conn, $update_player);
    mysqli_stmt_bind_param($stmt_p, "iii", $current_level, $current_exp, $player_id);
    mysqli_stmt_execute($stmt_p);
    mysqli_stmt_close($stmt_p);

    // 4. Record Loot Drops to your Bag inventory table
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
        'stat_upgraded' => $chosen_stat,
        'stat_gain' => $stat_increase
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}