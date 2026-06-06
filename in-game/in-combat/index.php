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
            mysqli_stmt_close($stmt_load); 
        }
    } catch (Exception $e) {
        error_log("Sidebar Equipment Loading Error: " . $e->getMessage());
    }
}


// --- STEP 3: CALCULATE RECONCILED ATTRIBUTE MODIFIERS ---
$base_atk = intval($row['attack'] ?? $row['curr_atk'] ?? $row['curr_str'] ?? 10);
$base_def = intval($row['defense'] ?? $row['curr_def'] ?? 5);
$base_spd = intval($row['spd'] ?? $row['curr_spd'] ?? $row['curr_dex'] ?? 10);
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
            mysqli_stmt_close($stmt_stats); 

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
$turn_order_stack = []; 
$encounter_limit = rand(2, 5);
$stmt2 = mysqli_prepare($conn, "SELECT e.enemy_id, e.enemy_name, e.sprite, es.enemy_hp, es.enemy_str, es.enemy_def, es.enemy_dex, es.enemy_int, es.enemy_fth 
                                 FROM enemy e 
                                 JOIN enemy_stats es ON e.enemy_id = es.enemy_id 
                                 ORDER BY RAND() LIMIT ?");
mysqli_stmt_bind_param($stmt2, "i", $encounter_limit);
mysqli_stmt_execute($stmt2);
$res2 = mysqli_stmt_get_result($stmt2);

// Add the player to the global turn order list first
$turn_order_stack[] = [
    'id'       => null,
    'name'     => $player_data['player_name'] ?? $player_data['name'] ?? 'Mimi',
    'type'     => 'player',
    'sprite'   => $player_data['avatar'] ?? 'player_avatar.png',
    'spd'      => $total_spd,
    'curr_spd' => $total_spd
];

$i = 0;
while ($enemy_row = mysqli_fetch_assoc($res2)) {
    // Mapping internal database names to what the HTML expects
    $enemy_instance = [
        'id'         => $i,
        'enemy_id'   => $enemy_row['enemy_id'],
        'name'       => $enemy_row['enemy_name'] . ' ' . ($i + 1),
        'enemy_name' => $enemy_row['enemy_name'],
        'sprite'     => $enemy_row['sprite'],
        'hp'         => intval($enemy_row['enemy_hp']),
        'max_hp'     => intval($enemy_row['enemy_hp']),
        'str'        => intval($enemy_row['enemy_str']),
        'def'        => intval($enemy_row['enemy_def']),
        'dex'        => intval($enemy_row['enemy_dex']),
        'curr_dex'   => intval($enemy_row['enemy_dex']),
        'int'        => intval($enemy_row['enemy_int']),
        'fth'        => intval($enemy_row['enemy_fth']),
        'alive'      => true
    ];
    $active_enemies[] = $enemy_instance;

    // Add this enemy unit to the turn order stack with full parameters
    $turn_order_stack[] = [
        'id'       => $i,
        'name'     => $enemy_instance['name'],
        'type'     => 'enemy',
        'sprite'   => $enemy_instance['sprite'],
        'spd'      => $enemy_instance['dex'],
        'curr_spd' => $enemy_instance['curr_dex']
    ];
    $i++;
}
mysqli_stmt_close($stmt2); 

// Sort the turn order by speed safely on page load
usort($turn_order_stack, function ($a, $b) {
    return $b['spd'] <=> $a['spd'];
});
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
            <div id="turn-order-list" class="mb-3 p-4" style="max-height: 540px; height:400px; overflow-y: auto; overflow-x: hidden; padding: 5px; border-radius: 8px; background-color: #C08560; box-shadow: inset 0 0 5px rgba(0,0,0,0.3);">

                <?php
                foreach ($turn_order_stack as $combatant):
                    $cardId = ($combatant['type'] === 'player')
                        ? 'turn-card-player'
                        : 'turn-card-enemy-' . $combatant['id'];
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

    <script type="text/javascript">
        document.querySelectorAll('a, img').forEach(el => {
            el.addEventListener('dragstart', e => {
                e.preventDefault();
            });
        });

        function updateTurnOrderSidebar() {
            const turnOrderContainer = document.getElementById('turn-order-list');
            if (!turnOrderContainer || !window.combatState || !window.combatState.turnOrder) return;

            // Clear out the static PHP rows
            turnOrderContainer.innerHTML = '';

            window.combatState.turnOrder.forEach(combatant => {
                // If an enemy is dead, don't show them in the turn order layout
                if (combatant.type === 'enemy') {
                    const enemyMatch = window.combatState.enemies.find(e => e.id === combatant.id);
                    if (enemyMatch && !enemyMatch.alive) return;
                }

                // Pull the dynamic speed parameter correctly
                const displaySpeed = combatant.curr_spd ?? combatant.spd ?? combatant.dex ?? 0;
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

        // Run updates safely on load once window.combatState is initialized by content_combat.php
        $(document).ready(function() {
            setTimeout(updateTurnOrderSidebar, 100);
        });
    </script>
</body>
</html>