<?php
// pages/processes/update_node.php
include __DIR__ . '/../../../conn.php';// Adjust pathing depth to find your conn file

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $playerId = isset($_POST['player_id']) ? (int)$_POST['player_id'] : 0;
    $nodeId = isset($_POST['node_id']) ? trim($_POST['node_id']) : '';

    if ($playerId > 0 && !empty($nodeId)) {
        // Update the current active run tracking row for this player
        // We target the latest active record by ordering by started_at descending
        $updateQuery = "UPDATE runs 
                        SET current_node = ? 
                        WHERE player_id = ? 
                        ORDER BY started_at DESC 
                        LIMIT 1";
                        
        $stmt = mysqli_prepare($conn, $updateQuery);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $nodeId, $playerId);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Node state advanced successfully']);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Database execution failed']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare database statement']);
            exit;
        }
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid structural payload signature context request']);