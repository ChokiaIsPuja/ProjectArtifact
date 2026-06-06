<?php
include __DIR__ . '/../../../conn.php';
global $conn;

header('Content-Type: application/json');

$player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
$option_id = isset($_POST['option_id']) ? intval($_POST['option_id']) : 0;

if ($player_id <= 0 || $option_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters provided.']);
    exit;
}

// 1. Fetch text output descriptions
$txt_query = "SELECT text_output FROM event_text_output WHERE event_option_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $txt_query);
mysqli_stmt_bind_param($stmt, "i", $option_id);
mysqli_stmt_execute($stmt);
$txt_res = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$narrative_output = $txt_res['text_output'] ?? "You completed the encounter.";
mysqli_stmt_close($stmt);

// FIXED: 2. Using the correct, explicit table name event_option_requirements
$req_query = "SELECT req_gold FROM event_option_requirements WHERE event_options_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $req_query);
mysqli_stmt_bind_param($stmt, "i", $option_id);
mysqli_stmt_execute($stmt);
$requirements = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($requirements && !empty($requirements['req_gold']) && $requirements['req_gold'] > 0) {
    $gold_cost = intval($requirements['req_gold']);
    
    // Deduct gold directly from the player table
    $deduct_gold_query = "UPDATE player SET gold = gold - ? WHERE player_id = ?";
    $stmt = mysqli_prepare($conn, $deduct_gold_query);
    mysqli_stmt_bind_param($stmt, "ii", $gold_cost, $player_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// 3. Fetch stat allocations from choice_reward table matrix
$rew_query = "SELECT * FROM choice_reward WHERE event_options_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $rew_query);
mysqli_stmt_bind_param($stmt, "i", $option_id);
mysqli_stmt_execute($stmt);
$reward = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($reward) {
    // 4. Apply changes directly into your active player_stats system schema layout
    $update_stats = "UPDATE player_stats SET 
                        curr_max_hp = curr_max_hp + ?,
                        curr_str = curr_str + ?,
                        curr_def = curr_def + ?,
                        curr_dex = curr_dex + ?,
                        curr_int = curr_int + ?,
                        curr_fth = curr_fth + ?
                     WHERE player_id = ?";
                     
    $stmt = mysqli_prepare($conn, $update_stats);
    mysqli_stmt_bind_param($stmt, "iiiiiii", 
        $reward['event_max_hp'], 
        $reward['event_str'], 
        $reward['event_def'], 
        $reward['event_dex'], 
        $reward['event_int'], 
        $reward['event_fth'], 
        $player_id
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 5. Handle item inventory injection if item_id is attached to choice reward columns
    if (!empty($reward['item_id']) && $reward['qty'] > 0) {
        $inv_query = "INSERT INTO bag (player_id, item_id, qty) VALUES (?, ?, ?) 
                      ON DUPLICATE KEY UPDATE qty = qty + ?";
        $stmt = mysqli_prepare($conn, $inv_query);
        mysqli_stmt_bind_param($stmt, "iiii", $player_id, $reward['item_id'], $reward['qty'], $reward['qty']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Hand back the result confirmation bundle array directly to JavaScript canvas layer!
echo json_encode([
    'success' => true,
    'text_output' => $narrative_output
]);