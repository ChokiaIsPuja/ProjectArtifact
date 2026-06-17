<?php
// admin_add_enemies.php
include 'conn.php'; // Assumes $conn is your valid mysqli connection variable

$message = "";
$messageType = "info";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACTION 1: Create New Enemy Identity Row + Stats Node
    if (isset($_POST['action']) && $_POST['action'] === 'create_enemy') {
        $enemy_name = trim($_POST['enemy_name'] ?? '');
        $base_gold  = intval($_POST['base_gold'] ?? 0);
        $base_exp   = intval($_POST['base_exp'] ?? 0);
        $sprite     = trim($_POST['sprite'] ?? 'monster.png');
        
        // Handle nullable foreign drop key cleanly
        $drop_id = !empty($_POST['drop_id']) ? intval($_POST['drop_id']) : null;

        // Core Combat Engine Stats Array
        $enemy_hp  = intval($_POST['enemy_hp'] ?? 50);
        $enemy_str = intval($_POST['enemy_str'] ?? 10);
        $enemy_def = intval($_POST['enemy_def'] ?? 5);
        $enemy_dex = intval($_POST['enemy_dex'] ?? 10);
        $enemy_int = intval($_POST['enemy_int'] ?? 10);
        $enemy_fth = intval($_POST['enemy_fth'] ?? 10);

        if (!empty($enemy_name)) {
            // Turn off autocommit to handle safe procedural database transactions
            mysqli_autocommit($conn, FALSE);
            $transaction_success = true;

            // 1. Insert into core 'enemy' table
            $enemyQuery = "INSERT INTO enemy (enemy_name, base_gold, base_exp, sprite, drop_id) VALUES (?, ?, ?, ?, ?)";
            $enemyStmt = mysqli_prepare($conn, $enemyQuery);
            
            if ($enemyStmt) {
                mysqli_stmt_bind_param($enemyStmt, "siisi", $enemy_name, $base_gold, $base_exp, $sprite, $drop_id);
                if (!mysqli_stmt_execute($enemyStmt)) {
                    $transaction_success = false;
                    $message = "Enemy Profile Execution Error: " . mysqli_stmt_error($enemyStmt);
                }
                mysqli_stmt_close($enemyStmt);
            } else {
                $transaction_success = false;
                $message = "Enemy Profile Statement Preparation Error: " . mysqli_error($conn);
            }

            // Grab the generated structural auto-increment primary key ID
            $newEnemyId = mysqli_insert_id($conn);

            // 2. Insert into 'enemy_stats' table referencing the new id split array
            if ($transaction_success) {
                $statsQuery = "INSERT INTO enemy_stats (enemy_id, enemy_hp, enemy_str, enemy_def, enemy_dex, enemy_int, enemy_fth) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $statsStmt = mysqli_prepare($conn, $statsQuery);
                
                if ($statsStmt) {
                    mysqli_stmt_bind_param($statsStmt, "iiiiiii", $newEnemyId, $enemy_hp, $enemy_str, $enemy_def, $enemy_dex, $enemy_int, $enemy_fth);
                    if (!mysqli_stmt_execute($statsStmt)) {
                        $transaction_success = false;
                        $message = "Enemy Stats Matrix Execution Error: " . mysqli_stmt_error($statsStmt);
                    }
                    mysqli_stmt_close($statsStmt);
                } else {
                    $transaction_success = false;
                    $message = "Enemy Stats Statement Preparation Error: " . mysqli_error($conn);
                }
            }

            // Commit or Rollback transaction flags based on execution steps
            if ($transaction_success) {
                mysqli_commit($conn);
                $message = "⚔️ [{$enemy_name}] and its stats matrix successfully forged into the db_roguelike registry!";
                $messageType = "success";
            } else {
                mysqli_rollback($conn);
                $messageType = "danger";
            }
            
            // Turn autocommit back on to return connection to standard behavior
            mysqli_autocommit($conn, TRUE);
        } else {
            $message = "Enemy name cannot pass verification blanks.";
            $messageType = "warning";
        }
    }

    // ACTION 2: Assign Enemy into an Instance Level Spawner Pool Mapping
    if (isset($_POST['action']) && $_POST['action'] === 'assign_pool') {
        $level    = intval($_POST['level'] ?? 1);
        $enemy_id = intval($_POST['enemy_id'] ?? 0);

        if ($level > 0 && $enemy_id > 0) {
            $poolQuery = "INSERT INTO enemy_pools (level, enemy_id) VALUES (?, ?)";
            $poolStmt = mysqli_prepare($conn, $poolQuery);
            
            if ($poolStmt) {
                mysqli_stmt_bind_param($poolStmt, "ii", $level, $enemy_id);
                if (mysqli_stmt_execute($poolStmt)) {
                    $message = "🎯 Enemy blueprint synchronized to Level {$level} generation matrix arrays!";
                    $messageType = "success";
                } else {
                    $message = "Spawn Pool Assignment Error: " . mysqli_stmt_error($poolStmt);
                    $messageType = "danger";
                }
                mysqli_stmt_close($poolStmt);
            } else {
                $message = "Spawn Pool Statement Preparation Error: " . mysqli_error($conn);
                $messageType = "danger";
            }
        }
    }
}

// Fetch complete master layouts via core query selections
$allEnemies = [];
$poolMap = [];

// 1. Fetch Master Enemy Records Listing
$enemyDataResult = mysqli_query($conn, "SELECT * FROM enemy ORDER BY enemy_id DESC");
if ($enemyDataResult) {
    while ($row = mysqli_fetch_assoc($enemyDataResult)) {
        $allEnemies[] = $row;
    }
    mysqli_free_result($enemyDataResult);
}

// 2. Fetch Active Relational Spawn Deck View Matrix
$poolQueryText = "
    SELECT p.level, e.enemy_name, s.enemy_hp, s.enemy_str 
    FROM enemy_pools p 
    JOIN enemy e ON p.enemy_id = e.enemy_id 
    LEFT JOIN enemy_stats s ON e.enemy_id = s.enemy_id
    ORDER BY p.level ASC, e.enemy_name ASC
";
$poolDataResult = mysqli_query($conn, $poolQueryText);
if ($poolDataResult) {
    while ($row = mysqli_fetch_assoc($poolDataResult)) {
        $poolMap[$row['level']][] = $row;
    }
    mysqli_free_result($poolDataResult);
} else {
    $message = "Critical Layout Retrieval Failure: " . mysqli_error($conn);
    $messageType = "danger";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>db_roguelike // Admin Add Enemies</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #2B1E16; color: #FAC79B; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .admin-card { background-color: #3D2A1D; border: 2px solid #5A3A2A; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        .form-control, .form-select { background-color: #1A110B; border: 1px solid #5A3A2A; color: #FFF; }
        .form-control:focus, .form-select:focus { background-color: #22160E; border-color: #FAC79B; color: #FFF; box-shadow: none; }
        .btn-custom { background-color: #FAC79B; color: #2B1E16; font-weight: bold; border: 2px solid #5A3A2A; }
        .btn-custom:hover { background-color: #E2B288; color: #2B1E16; }
        .text-neon { color: #FAC79B; text-shadow: 1px 1px 3px rgba(0,0,0,0.8); }
        .badge-level { background-color: #8B5A3C; color: white; font-size: 0.9rem; }
    </style>
</head>
<body class="py-5">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold text-neon">⚔️ db_roguelike: Admin Add Enemies</h1>
        <a href="index.php" class="btn btn-outline-light btn-sm">← Back to Simulation</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType; ?> alert-dismissible fade show fw-bold text-dark" role="alert">
            <?= $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="admin-card p-4">
                <h3 class="fw-bold mb-3 border-bottom border-secondary pb-2">Forge Identity & Core Stats Matrix</h3>
                <form action="admin_add_enemies.php" method="POST">
                    <input type="hidden" name="action" value="create_enemy">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase">Enemy Name</label>
                            <input type="text" name="enemy_name" class="form-control" placeholder="e.g. Cave Bat, Orc Grunt" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase">🎨 Sprite Asset Path</label>
                            <input type="text" name="sprite" class="form-control" value="slime.png" placeholder="bat.png" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label small fw-bold text-uppercase">🪙 Base Gold</label>
                            <input type="number" name="base_gold" class="form-control" value="12" min="0" required>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label small fw-bold text-uppercase">✨ Base Exp</label>
                            <input type="number" name="base_exp" class="form-control" value="20" min="0" required>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label small fw-bold text-uppercase">🎁 Drop Table ID (Optional)</label>
                            <input type="number" name="drop_id" class="form-control" placeholder="Null / Foreign Index ID">
                        </div>
                    </div>

                    <div class="p-3 my-3 style-attributes-deck rounded" style="background-color: rgba(0,0,0,0.2); border: 1px dashed #5A3A2A;">
                        <h5 class="fw-bold small text-uppercase text-white-50 mb-3">⚔️ Linked Attribute Scaling Blocks (enemy_stats)</h5>
                        
                        <div class="row">
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label small text-uppercase">❤️ Vital HP Max</label>
                                <input type="number" name="enemy_hp" class="form-control text-info fw-bold" value="50" min="1" required>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label small text-uppercase">💥 Strength (STR)</label>
                                <input type="number" name="enemy_str" class="form-control text-warning" value="10" min="0" required>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label small text-uppercase">🛡️ Defense (DEF)</label>
                                <input type="number" name="enemy_def" class="form-control text-success" value="4" min="0" required>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label small text-uppercase">⚡ Dexterity (DEX)</label>
                                <input type="number" name="enemy_dex" class="form-control" value="10" min="0" required>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label small text-uppercase">🔮 Intelligence (INT)</label>
                                <input type="number" name="enemy_int" class="form-control" value="8" min="0" required>
                            </div>
                            <div class="col-6 col-md-4 mb-3">
                                <label class="form-label small text-uppercase">🙏 Faith (FTH)</label>
                                <input type="number" name="enemy_fth" class="form-control" value="5" min="0" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-custom w-100 py-3 mt-2">💾 Commit Complete Enemy Data Nodes</button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="admin-card p-4 mb-4">
                <h3 class="fw-bold mb-3 border-bottom border-secondary pb-2">Link Enemy to Level Pools</h3>
                <form action="admin_add_enemies.php" method="POST" class="mb-2">
                    <input type="hidden" name="action" value="assign_pool">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Target Dungeon Level ID Index</label>
                        <input type="number" name="level" class="form-control text-center fw-bold text-warning" value="1" min="1" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-uppercase">Select Master Identity Target</label>
                        <select name="enemy_id" class="form-select" required>
                            <option value="">-- Select Forged Blueprints --</option>
                            <?php foreach ($allEnemies as $enemy): ?>
                                <option value="<?= $enemy['enemy_id']; ?>">
                                    ID <?= $enemy['enemy_id']; ?>: <?= htmlspecialchars($enemy['enemy_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-info text-dark fw-bold w-100 py-2">🔗 Insert Blueprint to Spawn-Pool Matrix</button>
                </form>
            </div>

            <div class="admin-card p-4">
                <h4 class="fw-bold fs-6 text-uppercase tracking-wider text-muted mb-3">Active Room Spawn Pools Array</h4>
                <div style="max-height: 240px; overflow-y: auto; background: rgba(0,0,0,0.15); border-radius: 6px;" class="p-2">
                    <?php if (empty($poolMap)): ?>
                        <p class="text-center text-muted m-0 py-2"><small>No structural runtime entities assigned yet.</small></p>
                    <?php else: ?>
                        <?php foreach ($poolMap as $lvl => $spawnGroup): ?>
                            <div class="mb-2 p-2" style="background: rgba(255,255,255,0.02); border-radius: 4px; border-left: 3px solid #FAC79B;">
                                <span class="badge badge-level mb-2">DUNGEON LEVEL <?= $lvl; ?></span>
                                <div class="row row-cols-1 row-cols-sm-2 g-2 ps-1">
                                    <?php foreach ($spawnGroup as $member): ?>
                                        <div class="col text-white" style="font-size: 0.8rem;">
                                            ☠️ <strong><?= htmlspecialchars($member['enemy_name']); ?></strong> <span class="text-muted">(HP: <?= $member['enemy_hp'] ?? '??'; ?> | ATK: <?= $member['enemy_str'] ?? '??'; ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>