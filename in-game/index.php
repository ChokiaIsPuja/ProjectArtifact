<?php
include __DIR__ . '/../conn.php';

// Player ID must come from URL parameter (user can have multiple characters)
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($player_id <= 0) {
    die("Error: No player character selected. Player ID is required.");
}

// --- STEP 1: LOAD MAIN CHARACTER DATA (CLEANED) ---
// 💡 REMOVED the buggy equipment sums and joins from here to stop double-calculating
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
          GROUP BY p.player_id, c.class_name, c.avatar, c.base_hp, ps.player_stat_id";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("SQL Preparation Failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $player_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);


// --- STEP 2: LOAD SIDEBAR EQUIPPED SPRITES & NAMES ---
$equipped_items = [
    'helmet'    => null,
    'armor'     => null,
    'boots'     => null,
    'accessory' => null,
    'armaments' => null
];

if ($player_id > 0) {
    try {
        $equip_load_query = "SELECT 
                                pe.slot_name, 
                                it.item_name,
                                it.item_desc,
                                it.sprite,
                                ia.att_atk,
                                ia.att_def,
                                ia.att_max_hp,
                                ia.att_spd
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
                    $equipped_items[$slot] = [
                        'item_name'  => $equip_row['item_name'],
                        'item_desc'  => $equip_row['item_desc'],
                        'sprite'     => $equip_row['sprite'],
                        'att_atk'    => $equip_row['att_atk'],
                        'att_def'    => $equip_row['att_def'],
                        'att_max_hp' => $equip_row['att_max_hp'],
                        'att_spd'    => $equip_row['att_spd']
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Sidebar Equipment Loading Error: " . $e->getMessage());
    }
}


// --- STEP 3: CALCULATE RECONCILED ATTRIBUTE MODIFIERS ---
// Base raw metrics pulled from your player stats table/row
$base_atk    = intval($row['attack']  ?? $row['curr_atk'] ?? 0);
$base_def    = intval($row['defense'] ?? $row['curr_def'] ?? 0);
$base_spd    = intval($row['spd']     ?? $row['curr_spd'] ?? 0);

// Use the base class health or raw stats row as the baseline foundation
$base_max_hp = intval($row['base_hp'] ?? $row['curr_max_hp'] ?? 100);

// Fallback total variables matching your view templates
$total_atk    = $base_atk;
$total_def    = $base_def;
$total_max_hp = $base_max_hp;
$total_spd    = $base_spd;

if ($player_id > 0) {
    try {
        $stats_query = "SELECT 
                            SUM(ia.att_atk) as gear_atk, 
                            SUM(ia.att_def) as gear_def,
                            SUM(ia.att_max_hp) as gear_hp,   
                            SUM(ia.att_spd) as gear_spd    
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

            // Compute final clean math aggregates for panel rendering
            $total_atk    = $base_atk + (int)($gear['gear_atk'] ?? 0);
            $total_def    = $base_def + (int)($gear['gear_def'] ?? 0);
            $total_max_hp = $base_max_hp + (int)($gear['gear_hp'] ?? 0);
            $total_spd    = $base_spd + (int)($gear['gear_spd'] ?? 0);
        }
    } catch (Exception $e) {
        error_log("Stats Calculation Failure: " . $e->getMessage());
    }
}

// --- STEP 4: CURRENT HEALTH RUNTIME UPDATES ---
$max_hp = $total_max_hp;

// Pull current hp from DB
$current_hp = intval($row['curr_hp'] ?? $max_hp);

// 🛠️ TESTING SIMULATION: Check if "?damage=X" is passed in the URL string
if (isset($_GET['damage'])) {
    $damage_taken = intval($_GET['damage']);
    $current_hp -= $damage_taken; // Deduct the damage from runtime display
}

// Clamp bounds checking
if ($current_hp > $max_hp) {
    $current_hp = $max_hp;
} else if ($current_hp < 0) {
    $current_hp = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="asset/css/bootstrap.css">
    <link rel="stylesheet" href="asset/css/preloader.css">
    <link rel="stylesheet" href="asset/css/nodes.css">
    <title>ProjectArtifact - Play</title>
    <style>
        .workspace-content {
            margin-left: 320px;
            padding-top: 20px;
        }
    </style>
</head>

<body class="bg-dark" style="font-family: 'Jaro', sans-serif; font-weight: 400; background-color: #D39670;">
    <div id="preloader">
        <img src="../asset/img/loading.png" alt="Loading..." class="preloader-image">
    </div>

    <nav class="text-white position-fixed p-3"
        style="height: calc(100vh - 30px); width: 300px; top: 15px; left: 15px; z-index: 1000; background-color: #D39670;">

        <h1 class="h5 text-center" style="font-size: 30px; margin-top:15px; margin-bottom: 30px;">Equipments</h1>

        <div class="container-fluid mt-2 px-2" style="background-color: #D39670;">

            <div class="d-flex justify-content-between mb-3" style="gap: 14px;">
                <div style="cursor: pointer; flex: 1;" data-bs-toggle="modal" data-bs-target="#modalHelmet">
                    <div class="card d-flex flex-column justify-content-end pt-3" style="height: 120px; background-color: #FAC79B; border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <?php if (!empty($equipped_items['helmet'])): ?>
                            <img src="../asset/img/items/<?= htmlspecialchars($equipped_items['helmet']['sprite']) ?>" alt="Helmet" class="mx-auto" style="max-width: 80px; max-height: 80px; object-fit: contain;">
                            <p class="m-0 pb-1 text-center small fw-bold text-dark"><?= htmlspecialchars($equipped_items['helmet']['item_name']) ?></p>
                        <?php else: ?>
                            <img src="../asset/img/items/empty/helmet.png" alt="Empty Helmet" class="mx-auto" style="max-width: 80px; max-height: 80px; opacity: 0.5;">
                            <p class="m-0 pb-1 text-center small text-muted fw-bold">Helmet</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="cursor: pointer; flex: 1;" data-bs-toggle="modal" data-bs-target="#modalArmor">
                    <div class="card d-flex flex-column justify-content-end pt-3" style="height: 120px; background-color: #FAC79B; border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <?php if (!empty($equipped_items['armor'])): ?>
                            <img src="../asset/img/items/<?= htmlspecialchars($equipped_items['armor']['sprite']) ?>" alt="Armor" class="mx-auto" style="max-width: 80px; max-height: 80px; object-fit: contain;">
                            <p class="m-0 pb-1 text-center small fw-bold text-dark"><?= htmlspecialchars($equipped_items['armor']['item_name']) ?></p>
                        <?php else: ?>
                            <img src="../asset/img/items/empty/armor.png" alt="Empty Armor" class="mx-auto" style="max-width: 80px; max-height: 80px; opacity: 0.5;">
                            <p class="m-0 pb-1 text-center small text-muted fw-bold">Armor</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mb-3" style="gap: 14px;">
                <div style="cursor: pointer; flex: 1;" data-bs-toggle="modal" data-bs-target="#modalBoots">
                    <div class="card d-flex flex-column justify-content-end pt-3" style="height: 120px; background-color: #FAC79B; border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <?php if (!empty($equipped_items['boots'])): ?>
                            <img src="../asset/img/items/<?= htmlspecialchars($equipped_items['boots']['sprite']) ?>" alt="Boots" class="mx-auto" style="max-width: 80px; max-height: 80px; object-fit: contain;">
                            <p class="m-0 pb-1 text-center small fw-bold text-dark"><?= htmlspecialchars($equipped_items['boots']['item_name']) ?></p>
                        <?php else: ?>
                            <img src="../asset/img/items/empty/boots.png" alt="Empty Boots" class="mx-auto" style="max-width: 80px; max-height: 80px; opacity: 0.5;">
                            <p class="m-0 pb-1 text-center small text-muted fw-bold">Boots</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="cursor: pointer; flex: 1;" data-bs-toggle="modal" data-bs-target="#modalAccessory">
                    <div class="card d-flex flex-column justify-content-end pt-3" style="height: 120px; background-color: #FAC79B; border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <?php if (!empty($equipped_items['accessory'])): ?>
                            <img src="../asset/img/items/<?= htmlspecialchars($equipped_items['accessory']['sprite']) ?>" alt="Accessory" class="mx-auto" style="max-width: 80px; max-height: 80px; object-fit: contain;">
                            <p class="m-0 pb-1 text-center small fw-bold text-dark"><?= htmlspecialchars($equipped_items['accessory']['item_name']) ?></p>
                        <?php else: ?>
                            <img src="../asset/img/items/empty/accessory.png" alt="Empty Accessory" class="mx-auto" style="max-width: 80px; max-height: 80px; opacity: 0.5;">
                            <p class="m-0 pb-1 text-center small text-muted fw-bold">Accessory</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3 text-center">
                <div class="col-12">
                    <div style="cursor: pointer;" class="w-100" data-bs-toggle="modal" data-bs-target="#modalArmaments">
                        <div class="card p-0 w-100 overflow-hidden" style="height: 120px; background-color: #FAC79B; border-radius: 12px; border: none; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); position: relative;">

                            <?php if (!empty($equipped_items['armaments'])): ?>
                                <img src="../asset/img/items/<?= htmlspecialchars($equipped_items['armaments']['sprite']) ?>"
                                    alt="Weapon"
                                    style="width: 60%; height: 100%; object-fit: contain; display: block; margin: 0 auto; image-rendering: pixelated; transform: scale(1.4); transform-origin: center center;">

                                <div style="position: absolute; left: 0; bottom: 0; width: 100%; padding: 6px; z-index: 5; background: linear-gradient(transparent, rgba(250, 199, 155, 0.9) 70%);">
                                    <p style="margin: 0;" class="text-center small fw-bold text-dark outlined-text-small"><?= htmlspecialchars($equipped_items['armaments']['item_name']) ?></p>
                                </div>

                            <?php else: ?>
                                <img src="../asset/img/items/empty/weapon.png"
                                    alt="Empty Weapon"
                                    style="width: 100%; height: 100%; object-fit: contain; display: block; opacity: 0.6; transform: scale(1.1); transform-origin: center center;">

                                <div style="position: absolute; left: 0; bottom: 0; width: 100%; padding: 6px; z-index: 5;">
                                    <p style="margin: 0;" class="text-center small text-muted fw-bold">Armaments</p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3 text-center">
                <div class="col-12">
                    <div class="card p-0 w-100 overflow-hidden" style="height: 300px; position: relative; border: none; border-radius: 12px;">
                        <img src="../asset/sprites/classes/<?= htmlspecialchars($row['avatar'] ?? 'default.png') ?>"
                            alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; display: block; image-rendering: pixelated;">

                        <div style="position: absolute; left: 0; bottom: 0; width: 100%; padding: 10px; z-index: 5;">
                            <p class="m-0 text-left fw-bold outlined-text" style="font-size: 24px; color: white; text-align: left;">
                                <?= htmlspecialchars($row['name'] ?? 'Hero') ?>
                            </p>
                            <div class="progress" style="height: 20px; border-radius: 5px; border: 2px solid #B46940">
                                <?php $hp_percentage = ($max_hp > 0) ? ($current_hp / $max_hp) * 100 : 0; ?>
                                <div class="progress-bar bg-danger"
                                    role="progressbar"
                                    style="width: <?= $hp_percentage ?>%; transition: width 0.4s ease;"
                                    aria-valuenow="<?= htmlspecialchars($current_hp) ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="<?= htmlspecialchars($max_hp) ?>">
                                </div>
                            </div>
                            <p class="text-right small text-white fw-bold mt-1 mb-0 outlined-text-small" style="font-size: 18px; text-align: right;">
                                <?= htmlspecialchars($current_hp) ?> / <?= htmlspecialchars($max_hp) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-6">
                    <button class="btn btn-dark w-100" style="background-color: #FAC79B; border:none; color:#B46940" data-bs-toggle="modal" data-bs-target="#modalInventory">Inventory</button>
                </div>
                <div class="col-6">
                    <button class="btn btn-dark w-100" style="background-color: #FAC79B; border:none; color:#B46940" data-bs-toggle="modal" data-bs-target="#modalStats">Stats</button>
                </div>
            </div>
            <hr class="mt-3 mb-0" style="border-top: 1px solid #ffe59e;">
            <p style="text-align: center; margin-top:0; font-size:24px">
                <span id="player-gold"><?= $row['gold'] ?></span> Gold
            </p>

        </div>
    </nav>

    <div class="container-lg workspace-content px-3 d-flex flex-column" style="height: 100vh; overflow: hidden; max-width: 100rem;">
        <div class="row flex-grow-1 mb-3" style="min-height: 0;">
            <div class="col-12 h-100">
                <div class="p-3 rounded-3 shadow-sm h-100" style="overflow-y: auto; overflow-x: hidden; background-color:#D39670;">
                    <?php
                    include __DIR__ . '/pages/content.php';
                    if (isset($content)) {
                        include $content;
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalHelmet" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered text-dark">
            <div class="modal-content" style="background-color: #FAC79B;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Helmet Slot</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <?php if (!empty($equipped_items['helmet'])): $item = $equipped_items['helmet']; ?>
                        <img src="../asset/img/items/<?= htmlspecialchars($item['sprite']) ?>" class="mb-2" style="max-width: 80px;">
                        <h4 class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></h4>
                        <p class="text-muted mb-3"><em><?= htmlspecialchars($item['item_desc'] ?? 'No description.') ?></em></p>
                        <div class="text-start bg-light p-3 rounded mb-3" style="background-color: rgba(255,255,255,0.4) !important;">
                            <h6 class="fw-bold m-0">Attributes:</h6>
                            <ul class="mb-0 mt-1 ps-3">
                                <li>Max HP: +<?= htmlspecialchars($item['att_max_hp'] ?? 0) ?></li>
                                <li>Attack: +<?= htmlspecialchars($item['att_atk'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Spd: +<?= htmlspecialchars($item['att_spd'] ?? 0)  ?></li>
                            </ul>
                        </div>
                        <button class="btn btn-danger w-100 fw-bold ajax-unequip-btn" data-slot-name="helmet" data-player-id="<?= $player_id ?>">Unequip</button>
                    <?php else: ?>
                        <p class="text-muted py-3">No equipment equipped in this slot.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalArmor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered text-dark">
            <div class="modal-content" style="background-color: #FAC79B;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Armor Slot</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <?php if (!empty($equipped_items['armor'])): $item = $equipped_items['armor']; ?>
                        <img src="../asset/img/items/<?= htmlspecialchars($item['sprite']) ?>" class="mb-2" style="max-width: 80px;">
                        <h4 class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></h4>
                        <p class="text-muted mb-3"><em><?= htmlspecialchars($item['item_desc'] ?? 'No description.') ?></em></p>
                        <div class="text-start bg-light p-3 rounded mb-3" style="background-color: rgba(255,255,255,0.4) !important;">
                            <h6 class="fw-bold m-0">Attributes:</h6>
                            <ul class="mb-0 mt-1 ps-3">
                                <li>Max HP: +<?= htmlspecialchars($item['att_max_hp'] ?? 0) ?></li>
                                <li>Attack: +<?= htmlspecialchars($item['att_atk'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Spd: +<?= htmlspecialchars($item['att_spd'] ?? 0)  ?></li>
                            </ul>
                        </div>
                        <button class="btn btn-danger w-100 fw-bold ajax-unequip-btn" data-slot-name="armor" data-player-id="<?= $player_id ?>">Unequip</button>
                    <?php else: ?>
                        <p class="text-muted py-3">No equipment equipped in this slot.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalBoots" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered text-dark">
            <div class="modal-content" style="background-color: #FAC79B;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Boots Slot</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <?php if (!empty($equipped_items['boots'])): $item = $equipped_items['boots']; ?>
                        <img src="../asset/img/items/<?= htmlspecialchars($item['sprite']) ?>" class="mb-2" style="max-width: 80px;">
                        <h4 class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></h4>
                        <p class="text-muted mb-3"><em><?= htmlspecialchars($item['item_desc'] ?? 'No description.') ?></em></p>
                        <div class="text-start bg-light p-3 rounded mb-3" style="background-color: rgba(255,255,255,0.4) !important;">
                            <h6 class="fw-bold m-0">Attributes:</h6>
                            <ul class="mb-0 mt-1 ps-3">
                                <li>Max HP: +<?= htmlspecialchars($item['att_max_hp'] ?? 0) ?></li>
                                <li>Attack: +<?= htmlspecialchars($item['att_atk'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Spd: +<?= htmlspecialchars($item['att_spd'] ?? 0)  ?></li>
                            </ul>
                        </div>
                        <button class="btn btn-danger w-100 fw-bold ajax-unequip-btn" data-slot-name="boots" data-player-id="<?= $player_id ?>">Unequip</button>
                    <?php else: ?>
                        <p class="text-muted py-3">No equipment equipped in this slot.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAccessory" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered text-dark">
            <div class="modal-content" style="background-color: #FAC79B;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Accessory Slot</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <?php if (!empty($equipped_items['accessory'])): $item = $equipped_items['accessory']; ?>
                        <img src="../asset/img/items/<?= htmlspecialchars($item['sprite']) ?>" class="mb-2" style="max-width: 80px;">
                        <h4 class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></h4>
                        <p class="text-muted mb-3"><em><?= htmlspecialchars($item['item_desc'] ?? 'No description.') ?></em></p>
                        <div class="text-start bg-light p-3 rounded mb-3" style="background-color: rgba(255,255,255,0.4) !important;">
                            <h6 class="fw-bold m-0">Attributes:</h6>
                            <ul class="mb-0 mt-1 ps-3">
                                <li>Max HP: +<?= htmlspecialchars($item['att_max_hp'] ?? 0) ?></li>
                                <li>Attack: +<?= htmlspecialchars($item['att_atk'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Spd: +<?= htmlspecialchars($item['att_spd'] ?? 0)  ?></li>
                            </ul>
                        </div>
                        <button class="btn btn-danger w-100 fw-bold ajax-unequip-btn" data-slot-name="accessory" data-player-id="<?= $player_id ?>">Unequip</button>
                    <?php else: ?>
                        <p class="text-muted py-3">No equipment equipped in this slot.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalArmaments" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered text-dark">
            <div class="modal-content" style="background-color: #FAC79B;">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Armaments</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <?php if (!empty($equipped_items['armaments'])): $item = $equipped_items['armaments']; ?>
                        <img src="../asset/img/items/<?= htmlspecialchars($item['sprite']) ?>" class="mb-2" style="max-width: 120px;">
                        <h4 class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></h4>
                        <p class="text-muted mb-3"><em><?= htmlspecialchars($item['item_desc'] ?? 'No description.') ?></em></p>
                        <div class="text-start bg-light p-3 rounded mb-3" style="background-color: rgba(255,255,255,0.4) !important;">
                            <h6 class="fw-bold m-0">Attributes:</h6>
                            <ul class="mb-0 mt-1 ps-3">
                                <li>Max HP: +<?= htmlspecialchars($item['att_max_hp'] ?? 0) ?></li>
                                <li>Attack: +<?= htmlspecialchars($item['att_atk'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Spd: +<?= htmlspecialchars($item['att_spd'] ?? 0)  ?></li>
                            </ul>
                        </div>
                        <button class="btn btn-danger w-100 fw-bold ajax-unequip-btn" data-slot-name="armaments" data-player-id="<?= $player_id ?>">Unequip</button>
                    <?php else: ?>
                        <p class="text-muted py-3">No weapon equipped in this slot.</p>
                    <?php endif; ?>
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
                            WHERE b.player_id = ?";

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
                            WHERE b.player_id = ?";

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
                                            <img src="../asset/img/items/<?= htmlspecialchars($item_row['sprite'] ?: 'default.png') ?>"
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
                                <img src="../asset/sprites/classes/<?= htmlspecialchars($row['avatar'] ?? 'default.png') ?>"
                                    alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; display: block; image-rendering: pixelated;">

                                <div style="position: absolute; left: 0; bottom: 0; width: 100%; padding: 15px; z-index: 5;">
                                    <p class="m-0 text-left fw-bold outlined-text" style="font-size: 28px; color: white; text-align: left;">
                                        <?= htmlspecialchars($row['name'] ?? 'Hero') ?>
                                    </p>
                                    <div class="progress mt-2" style="height: 20px; border-radius: 5px; border: 2px solid #B46940; background-color: rgba(0,0,0,0.5);">
                                        <?php $hp_percentage = ($max_hp > 0) ? ($current_hp / $max_hp) * 100 : 0; ?>
                                        <div class="progress-bar bg-danger"
                                            role="progressbar"
                                            style="width: <?= $hp_percentage ?>%; transition: width 0.4s ease;"
                                            aria-valuenow="<?= htmlspecialchars($current_hp) ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="<?= htmlspecialchars($max_hp) ?>">
                                        </div>
                                    </div>
                                    <p class="text-right small text-white fw-bold mt-1 mb-0 outlined-text-small" style="font-size: 18px; text-align: right;">
                                        <?= htmlspecialchars($current_hp) ?> / <?= htmlspecialchars($max_hp) ?> HP
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
                                        <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($row['level'] ?? $row['level'] ?? '0') ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <span class="fw-bold text-muted text-uppercase">Class</span>
                                        <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($row['class'] ?? $row['class_name'] ?? '0') ?></span>
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
    </script>
</body>

</html>