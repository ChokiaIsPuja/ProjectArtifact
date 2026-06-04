<?php
include __DIR__ . '/conn.php';
global $conn;
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $action = isset($_POST['action']) ? $_POST['action'] : (isset($_POST['aksi']) ? $_POST['aksi'] : '');

    // ==========================================
    // ACTION: ADD CHARACTER
    // ==========================================
    if ($action === 'add' || $action === 'tambah') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $classId = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        $id_user = isset($_POST['id_user']) ? intval($_POST['id_user']) : (isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : 0);

        if ($name === '' || $classId <= 0 || $id_user <= 0) {
            header('Location: index.php?error=missing');
            exit;
        }

        // 1. Fetch baseline class blueprints
        $classQuery = "SELECT base_hp, base_str, base_def, base_dex, base_int, base_fth FROM class WHERE class_id = ?";
        $stmt = mysqli_prepare($conn, $classQuery);
        mysqli_stmt_bind_param($stmt, "i", $classId);
        mysqli_stmt_execute($stmt);
        $classStats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$classStats) {
            header('Location: index.php?error=invalid_class');
            exit;
        }

        // Default leveling baselines
        $lvl = 1;
        $exp = 0;
        $gold = 200;
        $created_at = date('Y-m-d H:i:s');

        // 2. Commit the main character profile layer
        $playerQuery = "INSERT INTO player (name, class_id, level, exp, gold, id_user, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt2 = mysqli_prepare($conn, $playerQuery);
        mysqli_stmt_bind_param($stmt2, "siiiiis", $name, $classId, $lvl, $exp, $gold, $id_user, $created_at);
        
        if (!mysqli_stmt_execute($stmt2)) {
            header('Location: index.php?error=save_player');
            exit;
        }

        // Grab the unique primary key generated for this new character
        $newPlayerId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt2);

        // 3. Seed corresponding record to player_stats table automatically
        $statsQuery = "INSERT INTO player_stats (player_id, curr_max_hp, curr_str, curr_def, curr_dex, curr_int, curr_fth) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt3 = mysqli_prepare($conn, $statsQuery);
        
        // 🎯 FIXED: Changed type constraint configuration string from "iiiiii" to "iiiiiii" (7 variables)
        mysqli_stmt_bind_param($stmt3, "iiiiiii", 
            $newPlayerId, 
            $classStats['base_hp'], 
            $classStats['base_str'], 
            $classStats['base_def'],
            $classStats['base_dex'],
            $classStats['base_int'],
            $classStats['base_fth']
        );

        if (!mysqli_stmt_execute($stmt3)) {
            header('Location: index.php?error=save_stats');
            exit;
        }
        mysqli_stmt_close($stmt3);

        // =====================================================================
        // 🎲 AUTOMATIC SEED ROLLER FOR THE FRESH HERO PROFILE
        // =====================================================================
        $generatedSeed = mt_rand(100000, 99999999);
        $initialNode = 'node1'; // The entry tile point
        $statusActive = 1;

        $runQuery = "INSERT INTO runs (player_id, current_node, status, run_seed, started_at) VALUES (?, ?, ?, ?, NOW())";
        $runStmt = mysqli_prepare($conn, $runQuery);
        if ($runStmt) {
            mysqli_stmt_bind_param($runStmt, "isii", $newPlayerId, $initialNode, $statusActive, $generatedSeed);
            if (mysqli_stmt_execute($runStmt)) {
                mysqli_stmt_close($runStmt);
                
                // Automatically drop them right onto the grid canvas layout view loop!
                header('Location: index.php?saved=1');
                exit();
            }
        }

        header('Location: index.php?saved=1');
        exit;
    }

    // ==========================================
    // ACTION: EDIT CHARACTER
    // ==========================================
    if ($action === 'edit') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $classId = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

        if ($id <= 0 || $name === '' || $classId <= 0) {
            header('Location: index.php?error=missing');
            exit;
        }

        $query = "UPDATE player SET name = ?, class_id = ? WHERE player_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sii", $name, $classId, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header('Location: index.php?updated=1');
        exit;
    }
}

// ==========================================
// ACTION: DELETE CHARACTER
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if ($id > 0) {
        // Safe cascading order: drop stats and running maps first to avoid constraints block exceptions
        $deleteStats = "DELETE FROM player_stats WHERE player_id = ?";
        $stmtStats = mysqli_prepare($conn, $deleteStats);
        mysqli_stmt_bind_param($stmtStats, "i", $id);
        mysqli_stmt_execute($stmtStats);
        mysqli_stmt_close($stmtStats);

        $deleteRuns = "DELETE FROM runs WHERE player_id = ?";
        $stmtRuns = mysqli_prepare($conn, $deleteRuns);
        mysqli_stmt_bind_param($stmtRuns, "i", $id);
        mysqli_stmt_execute($stmtRuns);
        mysqli_stmt_close($stmtRuns);

        $query = "DELETE FROM player WHERE player_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: index.php?deleted=1');
    exit;
}

header('Location: index.php');
exit;