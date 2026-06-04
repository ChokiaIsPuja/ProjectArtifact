<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Adjust this path to point to your actual database connection file
require 'conn.php';
global $conn;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = isset($_POST['aksi']) ? $_POST['aksi'] : '';
    $playerId = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;

    if ($playerId <= 0) {
        die("Error: Invalid or missing character selection context.");
    }

    // =========================================================================
    // CASE 1: CONTINUE AN EXISTING RUN
    // =========================================================================
    if ($action === 'continue') {
        // Look up if they already have an active run row with a valid seed
        $checkQuery = "SELECT run_seed FROM runs WHERE player_id = ? AND status = 1 LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "i", $playerId);
        mysqli_stmt_execute($checkStmt);
        $result = mysqli_stmt_get_result($checkStmt);
        
        if ($runRow = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($checkStmt);
            // Great! An active run exists. Keep their exact layout and route them to the map
            header("Location: in-game/index.php?p=level1&id=" . $playerId);
            exit();
        } else {
            mysqli_stmt_close($checkStmt);
            // Fallback: If they clicked continue but the run table is empty, convert it to a fresh run setup automatically!
            $action = 'add';
        }
    }

    // =========================================================================
    // CASE 2: START A FRESH RUN (ROLL NEW SEED)
    // =========================================================================
    if ($action === 'add') {
        // 1. Archive any old active runs for this character first by setting status to 0
        $retireQuery = "UPDATE runs SET status = 0 WHERE player_id = ? AND status = 1";
        $retireStmt = mysqli_prepare($conn, $retireQuery);
        mysqli_stmt_bind_param($retireStmt, "i", $playerId);
        mysqli_stmt_execute($retireStmt);
        mysqli_stmt_close($retireStmt);

        // 2. Generate a completely fresh, massive random seed identity
        $generatedSeed = mt_rand(100000, 99999999);
        $initialNode = 'node1'; // The canvas starting room
        $statusActive = 1;      // 1 = Active Run

        // 3. Write the brand new initialized session row into the database
        $runQuery = "INSERT INTO runs (player_id, current_node, status, run_seed, started_at) 
                     VALUES (?, ?, ?, ?, NOW())";

        $runStmt = mysqli_prepare($conn, $runQuery);
        if ($runStmt) {
            mysqli_stmt_bind_param($runStmt, "isii", $playerId, $initialNode, $statusActive, $generatedSeed);
            if (mysqli_stmt_execute($runStmt)) {
                mysqli_stmt_close($runStmt);
                
                // Route them cleanly into the map canvas page!
                header("Location: in-game/index.php?p=level1&id=" . $playerId);
                exit();
            }
        }
        die("Database Error: Failed to write system run tracking row details.");
    }
    
} else {
    header("Location: index.php");
    exit();
}
?>