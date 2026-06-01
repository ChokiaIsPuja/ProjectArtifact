<?php
include_once __DIR__ . '/../../conn.php';

// Player ID must come from URL parameter (user can have multiple characters)
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
              WHERE p.player_id = ?";

    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $player_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Debug: Output what we got from the database
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

// Save player data before it gets overwritten by the enemy loop
$player_data = $row;

// --- 2. FETCH ENEMIES ---
// ... Leave the rest of your Section 2 and Section 3 exactly as they are ...
// --- STEP 2: LOAD SIDEBAR EQUIPPED SPRITES & NAMES ---
$equipped_items = ['helmet' => null, 'armor' => null, 'boots' => null, 'accessory' => null, 'armaments' => null];

if ($player_id > 0) {
    try {
        $equip_load_query = "SELECT pe.slot_name, it.item_name, it.item_desc, it.sprite, ia.att_atk, ia.att_def, ia.att_max_hp, ia.att_spd
                             FROM player_equipment pe
                             INNER JOIN bag b ON pe.bag_id = b.bag_id
                             INNER JOIN item it ON b.item_id = it.item_id
                             LEFT JOIN item_attributes ia ON it.id_item_attributes = ia.id_item_attributes
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
            mysqli_stmt_close($stmt_load); // 🛡️ CLOSED HERE
        }
    } catch (Exception $e) {
        error_log("Sidebar Equipment Loading Error: " . $e->getMessage());
    }
}


// --- STEP 3: CALCULATE RECONCILED ATTRIBUTE MODIFIERS ---
$base_atk = intval($row['attack'] ?? $row['curr_atk'] ?? 0);
$base_def = intval($row['defense'] ?? $row['curr_def'] ?? 0);
$base_spd = intval($row['spd'] ?? $row['curr_spd'] ?? 0);
$base_max_hp = intval($row['base_hp'] ?? $row['curr_max_hp'] ?? 100);

$total_atk = $base_atk;
$total_def = $base_def;
$total_max_hp = $base_max_hp;
$total_spd = $base_spd;

if ($player_id > 0) {
    try {
        $stats_query = "SELECT SUM(ia.att_atk) as gear_atk, SUM(ia.att_def) as gear_def, SUM(ia.att_max_hp) as gear_hp, SUM(ia.att_spd) as gear_spd 
                        FROM player_equipment pe
                        INNER JOIN bag b ON pe.bag_id = b.bag_id
                        INNER JOIN item it ON b.item_id = it.item_id
                        INNER JOIN item_attributes ia ON it.id_item_attributes = ia.id_item_attributes
                        WHERE pe.player_id = ?";

        $stmt_stats = mysqli_prepare($conn, $stats_query);
        if ($stmt_stats) {
            mysqli_stmt_bind_param($stmt_stats, "i", $player_id);
            mysqli_stmt_execute($stmt_stats);
            $stats_result = mysqli_stmt_get_result($stmt_stats);
            $gear = mysqli_fetch_assoc($stats_result);
            mysqli_stmt_close($stmt_stats); // 🛡️ CLOSED HERE

            $total_atk    = $base_atk + (int)($gear['gear_atk'] ?? 0);
            $total_def    = $base_def + (int)($gear['gear_def'] ?? 0);
            $total_max_hp = $base_max_hp + (int)($gear['gear_hp'] ?? 0);
            $total_spd    = $base_spd + (int)($gear['gear_spd'] ?? 0);

            error_log("DEBUG Equipment Stats: base_atk=$base_atk, gear_atk=" . ($gear['gear_atk'] ?? 'NULL') . ", total_atk=$total_atk");
            error_log("DEBUG Equipment Stats: base_hp=$base_max_hp, gear_hp=" . ($gear['gear_hp'] ?? 'NULL') . ", total_max_hp=$total_max_hp");
        }
    } catch (Exception $e) {
        error_log("Stats Calculation Failure: " . $e->getMessage());
    }
}

// --- STEP 4: CURRENT HEALTH RUNTIME UPDATES ---
$max_hp = $total_max_hp;
$current_hp = intval($row['curr_hp'] ?? $max_hp);

// Debug: Log what we're working with
error_log("DEBUG FINAL HP: player_id=$player_id, base_max_hp=$base_max_hp, total_max_hp=$total_max_hp, max_hp=$max_hp, curr_hp_from_db=" . ($row['curr_hp'] ?? 'NULL') . ", final_current_hp=$current_hp");

if (isset($_GET['damage'])) {
    $current_hp -= intval($_GET['damage']);
}

$current_hp = max(0, min($current_hp, $max_hp));


// --- 2. FETCH ENEMIES ---
$active_enemies = [];
$turn_order_stack = []; // 🛡️ Define the array to fix the warning
$encounter_limit = rand(2, 5);
$stmt2 = mysqli_prepare($conn, "SELECT e.enemy_id, e.enemy_name, e.sprite, es.hp, es.atk, es.def, es.spd 
                                 FROM enemy e 
                                 JOIN enemy_stats es ON e.enemy_id = es.enemy_id 
                                 ORDER BY RAND() LIMIT ?");
mysqli_stmt_bind_param($stmt2, "i", $encounter_limit);
mysqli_stmt_execute($stmt2);
$res2 = mysqli_stmt_get_result($stmt2);

// Add the player to the global turn order list first
$turn_order_stack[] = [
    'name' => $player_data['player_name'] ?? $player_data['name'] ?? 'Mimi',
    'type' => 'player',
    'sprite' => $player_data['avatar'] ?? 'player_avatar.png'
];

while ($row = mysqli_fetch_assoc($res2)) {
    // Mapping internal database names to what the HTML expects
    $row['id'] = $row['enemy_id'];
    $row['name'] = $row['enemy_name'];
    $row['max_hp'] = $row['hp'];
    $row['alive'] = true;
    $active_enemies[] = $row;

    // Add this enemy unit to the turn order stack for index.php to read safely
    $turn_order_stack[] = [
        'name' => $row['enemy_name'],
        'type' => 'enemy',
        'sprite' => $row['sprite']
    ];
}
mysqli_stmt_close($stmt2); // 🛡️ Connection released
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../asset/css/bootstrap.css">
    <link rel="stylesheet" href="../../asset/css/preloader.css">
    <title>ProjectArtifact - Play</title>
    <style>
        .workspace-content {
            margin-left: 320px;
            padding-top: 20px;
        }

        .turn-order-card {
            background-color: #FAC79B;
            border-radius: 8px;
            border: 2px solid #B46940;
            transition: all 0.2s ease-in-out;
        }

        .turn-order-card.active-player-token {
            border: 3px solid #fff !important;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.4);
        }

        .pixelated {
            image-rendering: pixelated;
        }
    </style>
</head>

<body class="bg-dark" style="font-family: 'Jaro', sans-serif; font-weight: 400; background-color: #805138;">


    <nav class="text-white position-fixed p-3"
        style="height: calc(100vh - 30px); width: 300px; top: 15px; left: 15px; z-index: 1000; background-color: #D39670;">

        <h1 class="h5 text-center" style="font-size: 30px; margin-top:15px; margin-bottom: 30px;">Turn Order</h1>

        <div class="container-fluid mt-2 px-2" style="background-color: #D39670;">
            <div class="mb-3 p-4" style="max-height: 540px; height:400px; overflow-y: auto; overflow-x: hidden; padding: 5px; border-radius: 8px; background-color: #C08560; box-shadow: inset 0 0 5px rgba(0,0,0,0.3);">

                <?php
                foreach ($turn_order_stack as $combatant):
                    // Fix: Only append the ID if it exists (i.e., for enemies)
                    $cardId = ($combatant['type'] === 'player')
                        ? 'turn-card-player'
                        : 'turn-card-enemy-' . ($combatant['id'] ?? 'unknown');
                ?>
                    <div class="row g-0 mb-2 text-center align-items-center combatant-turn-card"
                        id="<?= $cardId ?>"
                        style="background-color: #FAC79B; border-radius: 8px; border: 2px solid #B46940; height: 100px; padding: 0 10px; <?= $combatant['type'] === 'player' ? 'box-shadow: 0 0 8px #fff;' : '' ?>">

                        <div class="col-9 d-flex align-items-center text-start overflow-hidden" style="height: 100%">
                            <div style="width: 300px; height: 160px; overflow: hidden; position: relative;">
                                <img src="../../asset/sprites/<?= $combatant['type'] === 'player' ? 'classes/' : 'enemies/lv1/' ?><?= htmlspecialchars($combatant['sprite']) ?>"
                                    class="pixelated"
                                    style="height: 200px; width: 160px; object-fit: cover; position: absolute; left: -30px; display: block;
                                    -webkit-mask-image: linear-gradient(to right, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);
                                    mask-image: linear-gradient(to right, rgba(0,0,0,1) 60%, rgba(0,0,0,0) 100%);">
                            </div>
                        </div>

                        <div class="col-3 text-end">
                            <span class="badge text-dark bg-white font-monospace border border-dark">
                                <?php
                                if ($combatant['type'] === 'player') {
                                    echo isset($player_speed) ? $player_speed : 10;
                                } else {
                                    $enemy_speed = 0;
                                    foreach ($active_enemies as $enemy) {
                                        if ($enemy['name'] === $combatant['name']) {
                                            $enemy_speed = $enemy['curr_spd'] ?? $enemy['spd'] ?? 0;
                                            break;
                                        }
                                    }
                                    echo $enemy_speed;
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="row g-3 mb-3 text-center">
            <div class="col-12">
                <div class="card p-0 w-100 overflow-hidden" style="height: 300px; position: relative; border: none; border-radius: 12px;">
                    <img src="../../asset/sprites/classes/<?= htmlspecialchars($player_data['avatar'] ?? 'default.png') ?>"
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
        </div>
    </nav>

    <div class="container-lg workspace-content px-3 d-flex flex-column" style="height: 100vh; overflow: hidden; max-width: 100rem;">
        <div class="row flex-grow-1 mb-3" style="min-height: 0;">
            <div class="col-12 h-100">
                <div id="battleground-mount" class="p-3 rounded-3 shadow-sm h-100 d-flex flex-column justify-content-between" style="background-color:#D39670;">

                    <?php include __DIR__ . '/content_combat.php'; ?>

                </div>
            </div>
        </div>
    </div>
    <?php
    // 1. THE PROCEDURAL LOADER BLOCK (Matching your new direct player_id relation)
    $inventory_rows = [];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        try {
            $inventory_query = "SELECT 
                        b.bag_id, 
                        it.item_name, 
                        b.qty, 
                        it.item_type, 
                        it.sprite 
                    FROM bag b
                    INNER JOIN item it ON b.item_id = it.item_id
                    WHERE b.player_id = ? AND it.item_type = 'consumables'";

            // 🛠️ CHANGED: Switched entirely to procedural mysqli functions
            $stmt_inv = mysqli_prepare($conn, $inventory_query);
            if ($stmt_inv) {
                mysqli_stmt_bind_param($stmt_inv, "i", $id);
                mysqli_stmt_execute($stmt_inv);
                $result = mysqli_stmt_get_result($stmt_inv);

                while ($item_row = mysqli_fetch_assoc($result)) {
                    $inventory_rows[] = $item_row;
                }
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }
    ?>

    <div class="modal fade" id="modalInventory" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered text-dark">
            <div class="modal-content" style="background-color: #FAC79B;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Bag Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($inventory_rows)): ?>
                        <?php foreach ($inventory_rows as $index => $item_row): ?>

                            <div class="row mb-2">
                                <div class="col-12">
                                    <div class="p-2 d-flex justify-content-between align-items-center"
                                        style="background-color: #FFF2E6; border: 2px solid #B46940; cursor: pointer;"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#item_<?= htmlspecialchars($item_row['bag_id'] ?? 0) ?>_<?= $index ?>"
                                        aria-expanded="false">

                                        <div class="d-flex align-items-center gap-2">
                                            <img src="../../asset/img/<?= htmlspecialchars($item_row['sprite'] ?: 'default.png') ?>"
                                                alt="<?= htmlspecialchars($item_row['item_name'] ?? 'Unknown Item') ?>"
                                                style="max-width: 40px; max-height: 40px; object-fit: contain; image-rendering: pixelated;">
                                            <p class="m-0 fw-bold"><?= htmlspecialchars($item_row['item_name'] ?? 'Unknown Item') ?></p>
                                        </div>

                                        <p class="m-0 text-muted">x<?= htmlspecialchars($item_row['qty'] ?? 1) ?> ▾</p>
                                    </div>

                                    <div class="collapse" id="item_<?= htmlspecialchars($item_row['bag_id'] ?? 0) ?>_<?= $index ?>">
                                        <div class="p-2 border-start border-end border-bottom" style="background-color: #FFFDFB; border-color: #B46940 !important;">
                                            <div class="d-flex gap-2 justify-content-end">

                                                <?php if (in_array(strtolower($item_row['item_type'] ?? ''), ['helmet', 'armor', 'boots', 'accessory', 'weapon', 'armaments', 'equipment'])): ?>
                                                    <button type="button"
                                                        class="btn btn-sm btn-dark text-white px-3 ajax-equip-btn"
                                                        data-bag-id="<?= htmlspecialchars($item_row['bag_id'] ?? 0) ?>"
                                                        data-player-id="<?= $player_id ?>"> Equip
                                                    </button>
                                                <?php else: ?>
                                                    <?php if (isset($item_row['item_type']) && strtolower(trim($item_row['item_type'])) === 'consumables'): ?>
                                                        <button class="btn btn-success btn-sm ajax-use-consumable-btn w-100 mt-2 fw-bold"
                                                            data-bag-id="<?= $item_row['bag_id'] ?>"
                                                            data-player-id="<?= $player_id ?>"
                                                            data-max-hp="<?= $max_hp ?>"> Use Consumable
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <button class="btn btn-sm btn-outline-danger px-3">Drop</button>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center text-muted py-3">Your bag is empty!</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php error_log("DEBUG BEFORE MODAL: max_hp=$max_hp, current_hp=$current_hp"); ?>
    <div class="modal fade" id="modalStats" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered text-dark">
            <div class="modal-content" style="background-color: #FAC79B; border-radius: 14px; border: none;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="font-size: 26px; color: #B46940;">Character Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="card p-0 w-100 overflow-hidden" style="height: 500px; position: relative; border: none; border-radius: 12px;">
                                <img src="../../asset/sprites/classes/<?= htmlspecialchars($player_data['avatar'] ?? 'default.png') ?>"
                                    alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; display: block; image-rendering: pixelated;">

                                <div style="position: absolute; left: 0; bottom: 0; width: 100%; padding: 15px; z-index: 5;">
                                    <p class="m-0 text-left fw-bold outlined-text" style="font-size: 28px; color: white; text-align: left;">
                                        <?= htmlspecialchars($player_data['name'] ?? 'Hero') ?>
                                    </p>

                                    <div class="progress mt-2" style="height: 20px; border-radius: 5px; border: 2px solid #B46940; background-color: rgba(0,0,0,0.5);">
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
                                        <span id="player-hp-text"><?= htmlspecialchars($current_hp) ?> / <?= htmlspecialchars($max_hp) ?></span> HP
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 d-flex flex-column justify-content-between">
                            <div class="p-4 h-100 d-flex flex-column justify-content-between" style="background-color: rgba(180, 105, 64, 0.15); border-radius: 12px;">
                                <div>
                                    <h4 class="fw-bold mb-3 border-bottom pb-2 text-dark" style="font-size: 22px; border-color: #B46940 !important;">Core Attributes</h4>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <span class="fw-bold text-muted text-uppercase">Level</span>
                                        <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($player_data['level'] ?? '1') ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <span class="fw-bold text-muted text-uppercase">Class</span>
                                        <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($player_data['class_name'] ?? 'Unknown') ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <span class="fw-bold text-muted text-uppercase">Max HP</span>
                                        <span class="fw-bold text-dark fs-5"><?= $max_hp ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <span class="fw-bold text-muted text-uppercase">Attack</span>
                                        <span class="fw-bold text-dark fs-5"><?= $total_atk ?></span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <span class="fw-bold text-muted text-uppercase">Defense</span>
                                        <span class="fw-bold text-dark fs-5"><?= $total_def ?></span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <span class="fw-bold text-muted text-uppercase">Speed</span>
                                        <span class="fw-bold text-dark fs-5"><?= $total_spd ?></span>
                                    </div>
                                </div>

                                <div class="text-center mt-3 pt-2 border-top border-secondary-subtle">
                                    <small class="text-muted text-uppercase fw-bold" style="font-size: 12px; letter-spacing: 1px;">ProjectArtifact Attribute Framework</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script type="text/javascript">
        $(function() {
            if ($('#beginButton').length) {
                $('#beginButton').prop('disabled', true);
            }
        });

        function startPreloaderExit() {
            $('#preloader').addClass('loaded');
            $('body').addClass('page-ready');
            $('#preloader').one('animationend', function(e) {
                if (e.originalEvent.animationName === 'slideUp') {
                    $(this).remove();
                }
            });
        }

        var preloaderPageReady = false;
        var preloaderDownDone = false;

        function checkPreloaderExit() {
            if (preloaderPageReady && preloaderDownDone) {
                setTimeout(startPreloaderExit, 400);
            }
        }

        setTimeout(function() {
            preloaderDownDone = true;
            checkPreloaderExit();
        }, 700);

        window.addEventListener('load', function() {
            preloaderPageReady = true;
            checkPreloaderExit();
        });
    </script>

    <script>
        const map = document.getElementById('map');
        const viewport = document.querySelector('.viewport');

        let scale = 1;
        let translateX = -700;
        let translateY = -640;
        let isDragging = false;
        let startX;
        let startY;

        function updateMap() {
            if (map) {
                map.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
            }
        }

        if (viewport && map) {
            viewport.addEventListener('mousedown', (e) => {
                isDragging = true;
                startX = e.clientX - translateX;
                startY = e.clientY - translateY;
                map.style.cursor = 'grabbing';
            });

            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                translateX = e.clientX - startX;
                translateY = e.clientY - startY;
                updateMap();
            });

            document.addEventListener('mouseup', () => {
                isDragging = false;
                map.style.cursor = 'grab';
            });
        }

        const zoomInBtn = document.getElementById('zoomIn');
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', () => {
                scale += 0.1;
                updateMap();
            });
        }

        const zoomOutBtn = document.getElementById('zoomOut');
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', () => {
                scale -= 0.1;
                if (scale < 0.2) scale = 0.2;
                updateMap();
            });
        }

        const centerMapBtn = document.getElementById('centerMap');
        if (centerMapBtn) {
            centerMapBtn.addEventListener('click', () => {
                translateX = -700;
                translateY = -640;
                scale = 1;
                updateMap();
            });
        }
    </script>

    <script>
        const PROXIMITY_THRESHOLD = 80;

        function getNodeData() {
            const mapEl = document.getElementById("map");
            if (!mapEl) return [];
            const mapRect = mapEl.getBoundingClientRect();
            const nodes = Array.from(document.querySelectorAll(".rpg-node"));

            return nodes.map(n => {
                const r = n.getBoundingClientRect();
                return {
                    id: n.id,
                    x: (r.left + r.width / 2) - mapRect.left,
                    y: (r.top + r.height / 2) - mapRect.top
                };
            });
        }

        function lineIntersects(A, B, C, D) {
            function ccw(p1, p2, p3) {
                return (p3.y - p1.y) * (p2.x - p1.x) > (p2.y - p1.y) * (p3.x - p1.x);
            }
            if (A.id === C.id || A.id === D.id || B.id === C.id || B.id === D.id) return false;
            return ccw(A, C, D) !== ccw(B, C, D) && ccw(A, B, C) !== ccw(A, B, D);
        }

        function generateEdges() {
            const data = getNodeData();
            const edges = [];
            const edgeSet = new Set();

            if (data.length === 0) return edges;

            const columnsList = [];
            const sortedNodes = [...data].sort((a, b) => a.x - b.x);

            sortedNodes.forEach(node => {
                let targetColumn = columnsList.find(col => Math.abs(col[0].x - node.x) < PROXIMITY_THRESHOLD);
                if (targetColumn) {
                    targetColumn.push(node);
                } else {
                    columnsList.push([node]);
                }
            });

            const columns = columnsList
                .sort((a, b) => a[0].x - b[0].x)
                .map(col => col.sort((a, b) => a.y - b.y));

            const nodeLookup = new Map(data.map(n => [n.id, n]));

            for (let i = 0; i < columns.length - 1; i++) {
                const currentColumn = columns[i];
                const nextColumn = columns[i + 1];

                currentColumn.forEach((currNode, currIdx) => {
                    let targets = [];

                    if (nextColumn.length === 1) {
                        targets.push(nextColumn[0]);
                    } else if (currentColumn.length === 1) {
                        targets = nextColumn.slice(0, 3);
                    } else {
                        nextColumn.forEach((nextNode, nextIdx) => {
                            if (Math.abs(currIdx - nextIdx) <= 1) {
                                if (Math.abs(currNode.y - nextNode.y) < 250) {
                                    targets.push(nextNode);
                                }
                            }
                        });

                        if (targets.length === 0 && nextColumn.length > 0) {
                            let closest = nextColumn[0];
                            let minDist = Math.abs(currNode.y - nextColumn[0].y);
                            nextColumn.forEach(n => {
                                let d = Math.abs(currNode.y - n.y);
                                if (d < minDist) {
                                    minDist = d;
                                    closest = n;
                                }
                            });
                            targets.push(closest);
                        }
                    }

                    targets.forEach(target => {
                        const key = `${currNode.id}->${target.id}`;
                        if (edgeSet.has(key)) return;

                        let crossesExistingEdge = false;
                        for (let existingEdge of edges) {
                            const A = nodeLookup.get(existingEdge.from);
                            const B = nodeLookup.get(existingEdge.to);
                            const C = currNode;
                            const D = target;

                            if (A && B && lineIntersects(A, B, C, D)) {
                                crossesExistingEdge = true;
                                break;
                            }
                        }

                        if (!crossesExistingEdge) {
                            edgeSet.add(key);
                            edges.push({
                                from: currNode.id,
                                to: target.id
                            });
                        }
                    });
                });
            }

            return edges;
        }

        function drawLines() {
            const svg = document.getElementById("links");
            if (!svg) return;
            svg.innerHTML = "";

            const data = getNodeData();
            const edges = generateEdges();
            const lookup = new Map(data.map(n => [n.id, n]));

            edges.forEach(edge => {
                const from = lookup.get(edge.from);
                const to = lookup.get(edge.to);

                if (from && to) {
                    const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                    line.setAttribute("x1", from.x);
                    line.setAttribute("y1", from.y);
                    line.setAttribute("x2", to.x);
                    line.setAttribute("y2", to.y);
                    line.setAttribute("stroke", "white");
                    line.setAttribute("stroke-width", "4");
                    line.setAttribute("stroke-dasharray", "10 12");
                    svg.appendChild(line);
                }
            });
        }

        drawLines();
        updateMap();
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Uses Global Event Delegation so it never misses a click inside Bootstrap modals
            document.body.addEventListener("click", function(e) {
                if (e.target && e.target.classList.contains("ajax-equip-btn")) {
                    e.preventDefault();

                    const button = e.target;
                    const bagId = button.getAttribute("data-bag-id");
                    const playerId = button.getAttribute("data-player-id");

                    // Validation check to ensure data attributes exist
                    if (!bagId || !playerId) {
                        alert("Missing equip context data variables!");
                        return;
                    }

                    // Visual feedback: Freeze button so the player doesn't spam click during database lag
                    button.disabled = true;
                    button.innerText = "Equipping...";

                    // Targets your exact process directory location perfectly
                    fetch("pages/processes/equip_item.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                            },
                            body: `player_id=${encodeURIComponent(playerId)}&bag_id=${encodeURIComponent(bagId)}`
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP status code error: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Clean full page reload so character stats and sidebar images update instantly
                                window.location.reload();
                            } else {
                                alert("Equip Failed: " + (data.error || "Unknown validation error."));
                                button.disabled = false;
                                button.innerText = "Equip";
                            }
                        })
                        .catch(error => {
                            console.error("Critical Processing Error:", error);
                            alert(`Network Connectivity Error.\nDetails: ${error.message}`);
                            button.disabled = false;
                            button.innerText = "Equip";
                        });
                }
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            // Listen for unequip clicks on any of our 5 slot buttons
            $(document).on('click', '.ajax-unequip-btn', function() {
                const button = $(this);
                const slotName = button.data('slot-name');
                const playerId = button.data('player-id');

                // Optional confirmation prompt
                if (confirm(`Are you sure you want to unequip your ${slotName}?`)) {
                    $.ajax({
                        url: 'pages/processes/unequip_item.php', // Ensure this path correctly points to your process file
                        type: 'POST',
                        data: {
                            player_id: playerId,
                            slot_name: slotName
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Refresh the dashboard display so the panel metrics drop back down instantly
                                window.location.reload();
                            } else {
                                alert('Transaction Failed: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Failure:', error);
                            alert('An error occurred while unequipping the item.');
                        }
                    });
                }
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            $(document).on('click', '.ajax-use-consumable-btn', function() {
                const button = $(this);
                const bagId = button.data('bag-id');
                const playerId = button.data('player-id');
                const maxHp = button.data('max-hp'); // ✅ Grab the client-calculated max HP

                button.prop('disabled', true).text('Processing...');

                // ✅ Single combined AJAX call sending all parameters together
                $.ajax({
                    url: 'pages/processes/use_consumable.php',
                    type: 'POST',
                    data: {
                        player_id: playerId,
                        bag_id: bagId,
                        client_max_hp: maxHp // ✅ Sent alongside the player and item context
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Instantly reloads the dashboard page frame to display updated stats
                            window.location.reload();
                        } else {
                            alert('Action Failed: ' + response.message);
                            button.prop('disabled', false).text('Use Consumable');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        alert('An error occurred on the server.');
                        button.prop('disabled', false).text('Use Consumable');
                    }
                });
            });
        });

        function useItem(itemName, healAmount) {
            if (healAmount > 0) {
                window.ActiveBattle.player.hp = Math.min(
                    window.ActiveBattle.player.maxHp,
                    window.ActiveBattle.player.hp + healAmount
                );
                console.log(`Used ${itemName} to heal for ${healAmount}`);
                // Add code here to update your visual health bar
            }
            // Logic to decrement quantity via AJAX could go here
        }
    </script>
    <script type="text/javascript">
        document.querySelectorAll('a, img').forEach(el => {
            el.addEventListener('dragstart', e => {
                e.preventDefault();
            });
        });


        function updateTurnOrderSidebar() {
            const turnOrderContainer = document.getElementById('turn-order-list');
            if (!turnOrderContainer) return;

            // Clear out the static PHP rows
            turnOrderContainer.innerHTML = '';

            combatState.turnOrder.forEach(combatant => {
                // If an enemy is dead, don't show them in the turn order layout
                if (combatant.type === 'enemy') {
                    const enemyMatch = combatState.enemies.find(e => e.id === combatant.id);
                    if (enemyMatch && !enemyMatch.alive) return;
                }

                // Pull the dynamic current speed, falling back to base speed
                const displaySpeed = combatant.curr_spd ?? combatant.spd ?? 0;

                let spriteFolder = combatant.type === 'player' ? 'classes/' : 'enemies/lv1/';
                const glowStyle = combatant.type === 'player' ? 'box-shadow: 0 0 8px #fff;' : '';

                const rowHtml = `
            <div class="row g-0 mb-2 text-center align-items-center" 
                 style="background-color: #FAC79B; border-radius: 8px; border: 2px solid #B46940; height: 100px; padding: 0 10px; ${glowStyle}">
                <div class="col-9 d-flex align-items-center text-start overflow-hidden" style="height: 100%">
                    <div style="width: 300px; height: 160px; overflow: hidden; position: relative;">
                        <img src="../../asset/sprites/${spriteFolder}${combatant.sprite}"
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
        }

        // Call this once right away when the page loads!
        updateTurnOrderSidebar();
    </script>

</body>

</html>