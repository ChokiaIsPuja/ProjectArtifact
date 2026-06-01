<?php
include __DIR__ . '/../../../conn.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect variables safely
    $player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
    $slot_name = isset($_POST['slot_name']) ? trim($_POST['slot_name']) : '';

    if ($player_id <= 0 || empty($slot_name)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters provided.']);
        exit;
    }

    try {
        // Direct DELETE lookup via player_id and slot_name
        $query = "DELETE FROM player_equipment WHERE player_id = ? AND LOWER(slot_name) = LOWER(?)";
        $stmt = mysqli_prepare($conn, $query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "is", $player_id, $slot_name);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Item unequipped!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to execute item removal.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'SQL prepare failed.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>