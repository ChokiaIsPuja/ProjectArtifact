<?php
include_once __DIR__ . '/../../../conn.php';

// --- STEP 1: ROUTING CONTENT CONTROLLER (RESTORED FIXED MATCH) ---
$content = ''; 
$current_page = isset($_GET['p']) ? trim($_GET['p']) : '';

if ($current_page === "boss_level1") {
    $content = __DIR__ . '/boss_lv1.php';
}

// Player ID must come from URL parameter (user can have multiple characters)
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Boss ID passed from your map navigation context node link
$boss_id = isset($_GET['boss_id']) ? intval($_GET['boss_id']) : 1;

if ($player_id <= 0) {
    die("Error: No player character selected. Player ID is required.");
}

$row = [];

if ($player_id > 0) {
    $test_player = mysqli_query($conn, "SELECT * FROM player WHERE player_id = $player_id");
    if (!$test_player) {
        die("Database Error on player table: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($test_player) == 0) {
        die("Database Success, but NO player found with ID: " . $player_id . ". Check if ID " . $player_id . " actually exists in your player table!");
    }

    $test_stats = mysqli_query($conn, "SELECT * FROM player_stats WHERE player_id = $player_id");
    if (mysqli_num_rows($test_stats) == 0) {
        die("Player found, but NO matching row found in your player_stats table for player_id: " . $player_id);
    }

    $query = "SELECT 
                p.*, 
                c.class_name, 
                c.avatar, 
                c.base_hp,
                ps.*
              FROM player p
              LEFT JOIN class c ON p.class_id = c.class_id
              LEFT JOIN player_stats ps ON p.player_id = ps.player_id 
              WHERE p.player_id = ?
              LIMIT 1";

    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $player_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (empty($row)) {
            die("Debug: Query executed but returned no rows for player_id=$player_id");
        }
    } else {
        die("Query preparation failed: " . mysqli_error($conn));
    }
}

if (!empty($row)) {
    $row['name'] = $row['player_name'] ?? $row['name'] ?? 'Mimi';
} else {
    $row['name'] = "Mimi";
    $row['avatar'] = "default.png";
}

// --- STEP 2: LOAD SIDEBAR EQUIPPED SPRITES & NAMES ---
$equipped_items = ['helmet' => null, 'armor' => null, 'boots' => null, 'accessory' => null, 'armaments' => null];

if ($player_id > 0) {
    try {
        $equip_load_query = "SELECT pe.slot_name, it.item_name, it.item_desc, it.sprite, ia.att_str, ia.att_def, ia.att_max_hp, ia.att_dex, ia.att_int, ia.att_fth
                             FROM player_equipment pe
                             INNER JOIN bag b ON pe.bag_id = b.bag_id
                             INNER JOIN item it ON b.item_id = it.item_id
                             LEFT JOIN item_attributes ia ON it.item_id = ia.item_id
                             WHERE pe.player_id = ?";

        $stmt_load = mysqli_prepare($conn, $equip_load_query);
        if ($stmt_load) {
            mysqli_stmt_bind_param($stmt_load, "i", $player_id);
            mysqli_stmt_execute($stmt_load);
            $load_result = mysqli_stmt_get_result($stmt_load);

            while ($equip_row = mysqli_fetch_assoc($load_result)) {
                $slot = strtolower(trim($equip_row['slot_name']));
                if (array_key_exists($slot, $equipped_items)) {
                    $equipped_items[$slot] = $equip_row;
                }
            }
            mysqli_stmt_close($stmt_load);
        }
    } catch (Exception $e) {
        error_log("Sidebar Equipment Loading Error: " . $e->getMessage());
    }
}

// --- STEP 3: CALCULATE RECONCILED ATTRIBUTE MODIFIERS ---
$base_str = intval($row['attack'] ?? $row['curr_atk'] ?? $row['curr_str'] ?? 10);
$base_def = intval($row['defense'] ?? $row['curr_def'] ?? 5);
$base_dex = intval($row['spd'] ?? $row['curr_spd'] ?? $row['curr_dex'] ?? 10);
$base_int = intval($row['intellect'] ?? $row['curr_int'] ?? 5);
$base_fth = intval($row['faith'] ?? $row['curr_fth'] ?? 5);
$base_max_hp = intval($row['base_hp'] ?? $row['curr_max_hp'] ?? 100);
$curr_hp = intval($row['curr_hp'] ?? $base_max_hp);

$total_str = $base_str;
$total_def = $base_def;
$total_max_hp = $base_max_hp;
$total_dex = $base_dex;
$total_int = $base_int;
$total_fth = $base_fth;

if ($player_id > 0) {
    try {
        $stats_query = "SELECT SUM(ia.att_str) as gear_str, SUM(ia.att_def) as gear_def, SUM(ia.att_max_hp) as gear_hp, SUM(ia.att_dex) as gear_dex, SUM(ia.att_int) as gear_int, SUM(ia.att_fth) as gear_fth
                        FROM player_equipment pe
                        INNER JOIN bag b ON pe.bag_id = b.bag_id
                        INNER JOIN item it ON b.item_id = it.item_id
                        INNER JOIN item_attributes ia ON it.item_id = ia.item_id
                        WHERE pe.player_id = ?";

        $stmt_stats = mysqli_prepare($conn, $stats_query);
        if ($stmt_stats) {
            mysqli_stmt_bind_param($stmt_stats, "i", $player_id);
            mysqli_stmt_execute($stmt_stats);
            $stats_result = mysqli_stmt_get_result($stmt_stats);
            $gear = mysqli_fetch_assoc($stats_result);
            mysqli_stmt_close($stmt_stats);

            $total_str    = $base_str + (int)($gear['gear_str'] ?? 0);
            $total_def    = $base_def + (int)($gear['gear_def'] ?? 0);
            $total_max_hp = $base_max_hp + (int)($gear['gear_hp'] ?? 0);
            $total_dex    = $base_dex + (int)($gear['gear_dex'] ?? 0);
            $total_int    = $base_int + (int)($gear['gear_int'] ?? 0);
            $total_fth    = $base_fth + (int)($gear['gear_fth'] ?? 0);
        }
    } catch (Exception $e) {
        error_log("Stats Calculation Failure: " . $e->getMessage());
    }
}

$max_hp = $total_max_hp;

// Pull raw database health first
$current_hp = isset($row['curr_hp']) ? intval($row['curr_hp']) : $max_hp;

// Process map navigation tracking or combat engine adjustments
if (isset($_GET['damage'])) {
    $current_hp -= intval($_GET['damage']);
}

// Ensure character health stays bounded safely inside adjusted values
$current_hp = max(0, min($current_hp, $max_hp));

// Inject fully validated variables directly into your final active data array layout
$row['curr_max_hp'] = $max_hp;
$row['curr_hp']      = $current_hp;
$row['str']          = $total_str;
$row['def']          = $total_def;
$row['dex']          = $total_dex;
$row['int']          = $total_int;
$row['fth']          = $total_fth;

// Master tracking assignment reference updated!
$player_data = $row;

// --- STEP 5: FETCH SINGLE BOSS PROFILE DATA RECONCILIATION ---
$active_enemies = [];
$turn_order_stack = []; 

$boss_stmt = mysqli_prepare($conn, "SELECT boss_id, boss_name, boss_sprite, boss_max_hp, boss_str, boss_def, boss_dex, boss_int, boss_fth 
                                    FROM boss 
                                    WHERE boss_id = ? LIMIT 1");
mysqli_stmt_bind_param($boss_stmt, "i", $boss_id);
mysqli_stmt_execute($boss_stmt);
$boss_res = mysqli_stmt_get_result($boss_stmt);
$boss_row = mysqli_fetch_assoc($boss_res);
mysqli_stmt_close($boss_stmt);

if (!$boss_row) {
    die("Error: Selected boss profile record #$boss_id not configured in system database yet.");
}

// Add player to turn order stack (Using total_dex for speed stacking)
$turn_order_stack[] = [
    'id'       => null,
    'name'     => $player_data['player_name'] ?? $player_data['name'] ?? 'Mimi',
    'type'     => 'player',
    'sprite'   => $player_data['avatar'] ?? 'player_avatar.png',
    'spd'      => $total_dex,
    'curr_spd' => $total_dex
];

// Structural array for the active boss target entity mapping
$boss_instance = [
    'id'           => 0, 
    'enemy_id'    => $boss_row['boss_id'],
    'name'        => $boss_row['boss_name'],
    'enemy_name'  => $boss_row['boss_name'],
    'boss_sprite' => $boss_row['boss_sprite'],
    'hp'          => intval($boss_row['boss_max_hp']),
    'max_hp'      => intval($boss_row['boss_max_hp']),
    'str'         => intval($boss_row['boss_str']),
    'def'         => intval($boss_row['boss_def']),
    'dex'         => intval($boss_row['boss_dex']),
    'curr_dex'    => intval($boss_row['boss_dex']),
    'int'         => intval($boss_row['boss_int']),
    'fth'         => intval($boss_row['boss_fth']),
    'alive'       => true
];
$active_enemies[] = $boss_instance;

// Add Boss entity into the turn stack
$turn_order_stack[] = [
    'id'            => 0,
    'name'          => $boss_instance['name'],
    'type'          => 'boss', 
    'boss_sprite'   => $boss_instance['boss_sprite'], 
    'spd'           => $boss_instance['dex'],
    'curr_spd'      => $boss_instance['curr_dex']
];

usort($turn_order_stack, function ($a, $b) {
    return $b['spd'] <=> $a['spd'];
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../asset/css/bootstrap.css">
    <link rel="stylesheet" href="../../../asset/css/preloader.css">
    <title>ProjectArtifact - Boss Fight</title>
    <style>
        .workspace-content { margin-left: 320px; padding-top: 20px; }
        .turn-order-card { background-color: #FAC79B; border-radius: 8px; border: 2px solid #B46940; transition: all 0.2s ease-in-out; }
        .turn-order-card.active-player-token { border: 3px solid #fff !important; box-shadow: 0 0 10px rgba(255, 255, 255, 0.4); }
        .pixelated { image-rendering: pixelated; }
    </style>
</head>

<body class="bg-dark" style="font-family: 'Jaro', sans-serif; font-weight: 400; background-color: #805138;">

    <nav class="text-white position-fixed p-3"
        style="height: calc(100vh - 30px); width: 300px; top: 15px; left: 15px; z-index: 1000; background-color: #D39670;">

        <h1 class="h5 text-center" style="font-size: 30px; margin-top:15px; margin-bottom: 30px;">Turn Order</h1>

        <div class="container-fluid mt-2 px-2" style="background-color: #D39670;">
            <div id="turn-order-list" class="mb-3 p-4" style="max-height: 540px; height:400px; overflow-y: auto; overflow-x: hidden; padding: 5px; border-radius: 8px; background-color: #C08560; box-shadow: inset 0 0 5px rgba(0,0,0,0.3);">

                <?php
                foreach ($turn_order_stack as $combatant):
                    $cardId = ($combatant['type'] === 'player') ? 'turn-card-player' : 'turn-card-enemy-' . $combatant['id'];
                    
                    if ($combatant['type'] === 'player') {
                        $spriteFolder = 'classes/';
                        $imageFile = $combatant['sprite'];
                    } elseif ($combatant['type'] === 'boss') {
                        $spriteFolder = 'bosses/';
                        $imageFile = $combatant['boss_sprite'];
                    } else {
                        $spriteFolder = 'enemies/lv1/';
                        $imageFile = $combatant['sprite'];
                    }
                ?>
                    <div class="row g-0 mb-2 text-center align-items-center combatant-turn-card"
                        id="<?= $cardId ?>"
                        style="background-color: #FAC79B; border-radius: 8px; border: 2px solid #B46940; height: 100px; padding: 0 10px; <?= $combatant['type'] === 'player' ? 'box-shadow: 0 0 8px #fff;' : '' ?>">

                        <div class="col-9 d-flex align-items-center text-start overflow-hidden" style="height: 100%">
                            <div style="width: 300px; height: 160px; overflow: hidden; position: relative;">
                                <img src="../../../asset/sprites/<?= $spriteFolder ?><?= htmlspecialchars($imageFile) ?>"
                                    class="pixelated"
                                    style="height: 200px; width: 160px; object-fit: cover; position: absolute; left: -30px; display: block;
                                    -webkit-mask-image: linear-gradient(to right, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);
                                    mask-image: linear-gradient(to right, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);">
                            </div>
                        </div>

                        <div class="col-3 text-end">
                            <span class="badge text-dark bg-white font-monospace border border-dark">
                                <?= $combatant['spd'] ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="row g-3 mb-3 text-center">
            <div class="col-12">
                <div class="card p-0 w-100 overflow-hidden" style="height: 300px; position: relative; border: none; border-radius: 12px;">
                    <img src="../../../asset/sprites/classes/<?= htmlspecialchars($player_data['avatar'] ?? 'default.png') ?>"
                        alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; display: block; image-rendering: pixelated;">

                    <div style="position: absolute; left: 0; bottom: 0; width: 100%; padding: 10px; z-index: 5;">
                        <p class="m-0 text-left fw-bold outlined-text" style="font-size: 24px; color: white; text-align: left;">
                            <?= htmlspecialchars($player_data['name'] ?? 'Hero') ?>
                        </p>

                        <div class="progress" style="height: 20px; border-radius: 5px; border: 2px solid #B46940">
                            <?php $hp_percentage = ($max_hp > 0) ? ($current_hp / $max_hp) * 100 : 0; ?>
                            <div id="player-hp-bar" class="progress-bar bg-danger"
                                role="progressbar"
                                style="width: <?= $hp_percentage ?>%; transition: width 0.4s ease;"
                                aria-valuenow="<?= htmlspecialchars($current_hp) ?>"
                                aria-valuemin="0"
                                aria-valuemax="<?= htmlspecialchars($max_hp) ?>">
                            </div>
                        </div>

                        <p class="text-right small text-white fw-bold mt-1 mb-0 outlined-text-small" style="font-size: 18px; text-align: right;">
                            <span id="player-hp-text"><?= htmlspecialchars($current_hp) ?> / <?= htmlspecialchars($max_hp) ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <button class="btn btn-dark w-100" style="background-color: #FAC79B; border:none; color:#B46940" data-bs-toggle="modal" data-bs-target="#modalStats">Stats</button>
            </div>
        </div>
        <hr class="mt-3" style="border-top: 1px solid #ffe59e;">
    </nav>

    <div class="container-lg workspace-content px-3 d-flex flex-column" style="height: 100vh; overflow: hidden; max-width: 100rem;">
        <div class="row flex-grow-1 mb-3" style="min-height: 0;">
            <div class="col-12 h-100">
                <div id="battleground-mount" class="p-3 rounded-3 shadow-sm h-100 d-flex flex-column justify-content-between" style="background-color:#D39670;">

                    <?php 
                    if (!empty($content) && file_exists($content)) {
                        include $content; 
                    } else {
                        echo '<div class="text-center text-dark py-5">';
                        echo '  <p class="fw-bold m-0" style="font-size:20px;">Error: Boss encounter content could not be located.</p>';
                        echo '  <small class="text-muted font-monospace" style="font-size:12px;">Route context: ' . htmlspecialchars($current_page) . '</small>';
                        echo '</div>';
                    }
                    ?>

                </div>
            </div>
        </div>
    </div>
    
    <?php
    $inventory_rows = [];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        try {
            $inventory_query = "SELECT b.bag_id, it.item_name, b.qty, it.item_type, it.sprite 
                                FROM bag b
                                INNER JOIN item it ON b.item_id = it.item_id
                                WHERE b.player_id = ? AND it.item_type = 'consumables'";
            $stmt_inv = mysqli_prepare($conn, $inventory_query);
            if ($stmt_inv) {
                mysqli_stmt_bind_param($stmt_inv, "i", $id);
                mysqli_stmt_execute($stmt_inv);
                $result = mysqli_stmt_get_result($stmt_inv);
                while ($item_row = mysqli_fetch_assoc($result)) { $inventory_rows[] = $item_row; }
                mysqli_stmt_close($stmt_inv);
            }
        } catch (Exception $e) { error_log($e->getMessage()); }
    }
    ?>
    <div class="modal fade" id="modalInventory" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered text-dark">
            <div class="modal-content" style="background-color: #FAC79B;">
                <div class="modal-header border-0"><h5 class="modal-title fw-bold">Bag Inventory</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <?php if (!empty($inventory_rows)): foreach ($inventory_rows as $index => $item_row): ?>
                        <div class="row mb-2"><div class="col-12">
                            <div class="p-2 d-flex justify-content-between align-items-center" style="background-color: #FFF2E6; border: 2px solid #B46940; cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#item_<?= $item_row['bag_id'] ?>_<?= $index ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="../../../asset/img/<?= htmlspecialchars($item_row['sprite'] ?: 'default.png') ?>" style="max-width: 40px; max-height: 40px; object-fit: contain; image-rendering: pixelated;">
                                    <p class="m-0 fw-bold"><?= htmlspecialchars($item_row['item_name']) ?></p>
                                </div>
                                <p class="m-0 text-muted">x<?= $item_row['qty'] ?> ▾</p>
                            </div>
                            <div class="collapse" id="item_<?= $item_row['bag_id'] ?>_<?= $index ?>"><div class="p-2 border-start border-end border-bottom" style="background-color: #FFFDFB; border-color: #B46940 !important;"><div class="d-flex gap-2 justify-content-end">
                                <button class="btn btn-success btn-sm ajax-use-consumable-btn w-100 mt-2 fw-bold" data-bag-id="<?= $item_row['bag_id'] ?>" data-player-id="<?= $player_id ?>" data-max-hp="<?= $max_hp ?>">Use Consumable</button>
                                <button class="btn btn-sm btn-outline-danger px-3">Drop</button>
                            </div></div></div>
                        </div></div>
                    <?php endforeach; else: ?><div class="col-12 text-center text-muted py-3">Your bag is empty!</div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalStats" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered text-dark">
            <div class="modal-content" style="background-color: #FAC79B; border-radius: 14px; border: none;">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" style="font-size: 26px; color: #B46940;">Character Sheet</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><div class="row">
                    <div class="col-md-6"><div class="card p-0 w-100 overflow-hidden" style="height: 500px; position: relative; border: none; border-radius: 12px;"><img src="../../../asset/sprites/classes/<?= htmlspecialchars($player_data['avatar'] ?? 'default.png') ?>" style="width: 100%; height: 100%; object-fit: cover; image-rendering: pixelated;"><div style="position: absolute; left: 0; bottom: 0; width: 100%; padding: 15px; z-index: 5;"><p class="m-0 fw-bold outlined-text" style="font-size: 28px; color: white;"><?= htmlspecialchars($player_data['name']) ?></p><div class="progress mt-2" style="height: 20px; border-radius: 5px; border: 2px solid #B46940;"><div class="progress-bar bg-danger" style="width: <?= $hp_percentage ?>%;"></div></div><p class="text-right small text-white fw-bold mt-1 mb-0 outlined-text-small" style="font-size: 18px; text-align: right;"><?= $current_hp ?> / <?= $max_hp ?> HP</p></div></div></div>
                    <div class="col-md-6 d-flex flex-column justify-content-between"><div class="p-4 h-100" style="background-color: rgba(180, 105, 64, 0.15); border-radius: 12px;">
                        <h4 class="fw-bold mb-3 border-bottom pb-2" style="font-size: 22px; border-color: #B46940 !important;">Core Attributes</h4>
                        <div class="d-flex justify-content-between mb-2 p-2 bg-white rounded"><span class="fw-bold text-muted">LEVEL</span><span class="fw-bold text-dark"><?= htmlspecialchars($player_data['level'] ?? '1') ?></span></div>
                        <div class="d-flex justify-content-between mb-2 p-2 bg-white rounded"><span class="fw-bold text-muted">CLASS</span><span class="fw-bold text-dark"><?= htmlspecialchars($player_data['class_name'] ?? 'Unknown') ?></span></div>
                        <div class="d-flex justify-content-between mb-2 p-2 bg-white rounded"><span class="fw-bold text-muted">STRENGTH</span><span class="fw-bold text-dark"><?= $total_str ?></span></div>
                        <div class="d-flex justify-content-between mb-2 p-2 bg-white rounded"><span class="fw-bold text-muted">DEFENSE</span><span class="fw-bold text-dark"><?= $total_def ?></span></div>
                        <div class="d-flex justify-content-between mb-2 p-2 bg-white rounded"><span class="fw-bold text-muted">DEXTERITY</span><span class="fw-bold text-dark"><?= $total_dex ?></span></div>
                        <div class="d-flex justify-content-between mb-2 p-2 bg-white rounded"><span class="fw-bold text-muted">INTELLIGENCE</span><span class="fw-bold text-dark"><?= $total_int ?></span></div>
                        <div class="d-flex justify-content-between mb-2 p-2 bg-white rounded"><span class="fw-bold text-muted">FAITH</span><span class="fw-bold text-dark"><?= $total_fth ?></span></div>
                    </div></div>
                </div></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript">
        $(function() { if ($('#beginButton').length) { $('#beginButton').prop('disabled', true); } });
        function startPreloaderExit() { $('#preloader').addClass('loaded'); $('body').addClass('page-ready'); }
        var preloaderPageReady = false, preloaderDownDone = false;
        function checkPreloaderExit() { if (preloaderPageReady && preloaderDownDone) { setTimeout(startPreloaderExit, 400); } }
        setTimeout(function() { preloaderDownDone = true; checkPreloaderExit(); }, 700);
        window.addEventListener('load', function() { preloaderPageReady = true; checkPreloaderExit(); });
    </script>

    <script type="text/javascript">
        document.querySelectorAll('a, img').forEach(el => { el.addEventListener('dragstart', e => { e.preventDefault(); }); });

        function updateTurnOrderSidebar() {
            const turnOrderContainer = document.getElementById('turn-order-list');
            if (!turnOrderContainer || !window.combatState || !window.combatState.turnOrder) return;

            turnOrderContainer.innerHTML = '';

            window.combatState.turnOrder.forEach(combatant => {
                if (combatant.type === 'enemy' || combatant.type === 'boss') {
                    const enemyMatch = window.combatState.enemies.find(e => e.id === combatant.id);
                    if (enemyMatch && !enemyMatch.alive) return;
                }

                const displaySpeed = combatant.curr_spd ?? combatant.spd ?? combatant.dex ?? 0;
                
                let spriteFolder = 'enemies/lv1/';
                if (combatant.type === 'player') {
                    spriteFolder = 'classes/';
                } else if (combatant.type === 'boss') {
                    spriteFolder = 'bosses/';
                }
                
                const currentImg = combatant.type === 'boss' ? (combatant.boss_sprite ?? combatant.sprite) : combatant.sprite;
                const glowStyle = combatant.type === 'player' ? 'box-shadow: 0 0 8px #fff;' : '';

                const rowHtml = `
                    <div class="row g-0 mb-2 text-center align-items-center" 
                         style="background-color: #FAC79B; border-radius: 8px; border: 2px solid #B46940; height: 100px; padding: 0 10px; ${glowStyle}">
                        <div class="col-9 d-flex align-items-center text-start overflow-hidden" style="height: 100%">
                            <div style="width: 300px; height: 160px; overflow: hidden; position: relative;">
                                <img src="../../../asset/sprites/${spriteFolder}${currentImg}"
                                     class="pixelated"
                                     style="height: 200px; width: 160px; object-fit: cover; position: absolute; left: -30px; display: block;
                                            -webkit-mask-image: linear-gradient(to right, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);
                                            mask-image: linear-gradient(to right, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);">
                            </div>
                        </div>
                        <div class="col-3 text-end">
                            <span class="badge text-dark bg-white font-monospace border border-dark">
                                ${displaySpeed}
                            </span>
                        </div>
                    </div>
                `;
                turnOrderContainer.insertAdjacentHTML('beforeend', rowHtml);
            });

            let currentHp = (window.combatState && window.combatState.player) ? window.combatState.player.hp : parseInt(<?= json_encode($current_hp); ?>);
            let maxHp = (window.combatState && window.combatState.player) ? window.combatState.player.maxHp : parseInt(<?= json_encode($max_hp); ?>);
            let percentage = (maxHp > 0) ? (currentHp / maxHp) * 100 : 0;

            $('.modal-hp-bar-sync').css('width', percentage + '%').attr('aria-valuenow', currentHp);
            $('.modal-hp-text-sync').text(currentHp + ' / ' + maxHp);
        }

        $(document).ready(function() { setTimeout(updateTurnOrderSidebar, 100); });
    </script>
</body>
</html>