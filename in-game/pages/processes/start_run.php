<?php
include __DIR__ . '/../../../conn.php';
// Set response headers to return pure JSON back to JavaScript
header('Content-Type: application/json');

// 2. Intercept incoming POST requests safely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Safely capture and cast the incoming player ID payload
    $playerId = isset($_POST['player_id']) ? (int)$_POST['player_id'] : 0;

    if ($playerId > 0) {
        // 3. Prepare the insertion string
        // We set current_node to 'node1' automatically on start, and pass NOW() for our datetime stamp!
        $insertQuery = "INSERT INTO runs (player_id, current_node, started_at) VALUES (?, 'node1', NOW())";
        $stmt = mysqli_prepare($conn, $insertQuery);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $playerId);
            
            if (mysqli_stmt_execute($stmt)) {
                // SUCCESS: Data written smoothly! Hand a green light back to Javascript.
                echo json_encode([
                    "success" => true,
                    "message" => "New active game run initialized successfully."
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "Database execution failed: " . mysqli_stmt_error($stmt)
                ]);
            }
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "SQL compilation statement preparation failed."
            ]);
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Invalid or corrupt Player ID data received."
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Bad request protocol. Must pass data via POST."
    ]);
}
exit();
?>
