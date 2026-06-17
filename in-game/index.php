<?php

include __DIR__ . '/../conn.php';

// Player ID must come from URL parameter (user can have multiple characters)
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($player_id <= 0) {
    die("Error: No player character selected. Player ID is required.");
}
// --- STEP 1: LOAD MAIN CHARACTER DATA (EXPLICIT COLUMNS TO PREVENT NULLS) ---
$query = "SELECT 
            p.*, 
            c.class_name, 
            c.avatar, 
            c.base_hp,
            ps.curr_hp,
            ps.curr_max_hp,
            ps.curr_str,
            ps.curr_def,
            ps.curr_dex,
            ps.curr_int,
            ps.curr_fth
          FROM player p
          LEFT JOIN class c ON p.class_id = c.class_id
          LEFT JOIN player_stats ps ON p.player_id = ps.player_id 
          WHERE p.player_id = ?
          LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("SQL Preparation Failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $player_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    die("Error: Active player character matrix data not found.");
}

// --- STEP 2: LOAD SIDEBAR EQUIPPED SPRITES & NAMES ---
$equipped_items = [
    'helmet'    => null,
    'armor'     => null,
    'boots'     => null,
    'accessory' => null,
    'armaments' => null
];

// ✅ NEW: Initialize checklist array to track items that are currently worn
$equipped_bag_ids = [];

if ($player_id > 0) {
    try {
        $equip_load_query = "SELECT 
                                pe.slot_name, 
                                pe.bag_id, -- ✅ Added selection field to fill structural array references
                                it.item_name,
                                it.item_desc,
                                it.sprite,
                                ia.att_str,
                                ia.att_def,
                                ia.att_max_hp,
                                ia.att_dex,
                                ia.att_int,
                                ia.att_fth
                             FROM player_equipment pe
                             INNER JOIN bag b ON pe.bag_id = b.bag_id
                             INNER JOIN item it ON b.item_id = it.item_id
                             LEFT JOIN item_attributes ia ON it.item_id = ia.item_id
                             WHERE pe.player_id = ?";

        $stmt_load = mysqli_prepare($conn, $equip_load_query);

        if (!$stmt_load) {
            die("Step 2 Equipment Query Failed: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt_load, "i", $player_id);
        mysqli_stmt_execute($stmt_load);
        $load_result = mysqli_stmt_get_result($stmt_load);

        while ($equip_row = mysqli_fetch_assoc($load_result)) {
            // ✅ NEW: Push equipped bag row identifiers to tracking cache loop
            $equipped_bag_ids[] = intval($equip_row['bag_id']);

            $slot = strtolower(trim($equip_row['slot_name']));

            if ($slot === 'head' || $slot === 'weapon' || $slot === 'body') {
                if ($slot === 'head')   $slot = 'helmet';
                if ($slot === 'weapon') $slot = 'armaments';
                if ($slot === 'body')   $slot = 'armor';
            }

            if (array_key_exists($slot, $equipped_items)) {
                $equipped_items[$slot] = [
                    'item_name'  => $equip_row['item_name'],
                    'item_desc'  => $equip_row['item_desc'],
                    'sprite'     => $equip_row['sprite'],
                    'att_str'    => $equip_row['att_str'],
                    'att_def'    => $equip_row['att_def'],
                    'att_max_hp' => $equip_row['att_max_hp'],
                    'att_dex'    => $equip_row['att_dex'],
                    'att_int'    => $equip_row['att_int'],
                    'att_fth'    => $equip_row['att_fth']
                ];
            }
        }
        mysqli_stmt_close($stmt_load);
    } catch (Exception $e) {
        error_log("Sidebar Equipment Loading Error: " . $e->getMessage());
    }
}


// --- STEP 3: CALCULATE RECONCILED ATTRIBUTE MODIFIERS ---
$base_max_hp = intval($row['curr_max_hp'] ?? $row['base_hp'] ?? 100);
$base_str    = intval($row['curr_str']    ?? $row['attack']   ?? 0);
$base_def    = intval($row['curr_def']    ?? $row['defense']  ?? 0);
$base_dex    = intval($row['curr_dex']    ?? $row['spd']      ?? 0);
$base_int    = intval($row['curr_int']    ?? $row['int']      ?? 0);
$base_fth    = intval($row['curr_fth']    ?? $row['fth']      ?? 0);

$total_str    = $base_str;
$total_def    = $base_def;
$total_max_hp = $base_max_hp;
$total_dex    = $base_dex;
$total_int    = $base_int;
$total_fth    = $base_fth;

if ($player_id > 0) {
    try {
        $stats_query = "SELECT 
                            SUM(ia.att_str) as gear_str, 
                            SUM(ia.att_def) as gear_def,
                            SUM(ia.att_max_hp) as gear_hp,   
                            SUM(ia.att_dex) as gear_dex,
                            SUM(ia.att_int) as gear_int,
                            SUM(ia.att_fth) as gear_fth
                        FROM player_equipment pe
                        INNER JOIN bag b ON pe.bag_id = b.bag_id
                        INNER JOIN item it ON b.item_id = it.item_id
                        /* DOUBLE-CHECK: Ensure your schema uses item_id here, or swap back to id_item_attributes if needed */
                        LEFT JOIN item_attributes ia ON it.item_id = ia.item_id
                        WHERE pe.player_id = ?";

        $stmt_stats = mysqli_prepare($conn, $stats_query);

        if (!$stmt_stats) {
            die("Step 3 Stats Query Failed: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt_stats, "i", $player_id);
        mysqli_stmt_execute($stmt_stats);
        $stats_result = mysqli_stmt_get_result($stmt_stats);
        $gear = mysqli_fetch_assoc($stats_result);
        mysqli_stmt_close($stmt_stats);

        // --- FIXED: Keys now match the SQL aliases ('gear_str', 'gear_def', etc.) ---
        $total_str    = $base_str + (int)($gear['gear_str'] ?? 0);
        $total_def    = $base_def + (int)($gear['gear_def'] ?? 0);
        $total_max_hp = $base_max_hp + (int)($gear['gear_hp'] ?? 0);
        $total_dex    = $base_dex + (int)($gear['gear_dex'] ?? 0);
        $total_int    = $base_int + (int)($gear['gear_int'] ?? 0);
        $total_fth    = $base_fth + (int)($gear['gear_fth'] ?? 0);

    } catch (Exception $e) {
        error_log("Stats Calculation Failure: " . $e->getMessage());
    }
}

// --- STEP 4: CURRENT HEALTH RUNTIME UPDATES ---
$max_hp = $total_max_hp;

// FIXED: Explicit lookup ensuring real 0 HP parameters do not default back to max health values
$current_hp = (isset($row['curr_hp']) && $row['curr_hp'] !== '') ? intval($row['curr_hp']) : $max_hp;

if (isset($_GET['damage'])) {
    $damage_taken = intval($_GET['damage']);
    $current_hp -= $damage_taken;
}

// Cap values inside structural boundaries
if ($current_hp > $max_hp) {
    $current_hp = $max_hp;
} else if ($current_hp < 0) {
    $current_hp = 0;
}

$hp_percentage = ($max_hp > 0) ? ($current_hp / $max_hp) * 100 : 0;
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
                                <div id="hud-hp-bar" class="progress-bar bg-danger"
                                    role="progressbar"
                                    style="width: <?= $hp_percentage ?>%; transition: width 0.4s ease;"
                                    aria-valuenow="<?= htmlspecialchars($current_hp) ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="<?= htmlspecialchars($max_hp) ?>">
                                </div>
                            </div>
                            <p id="hud-hp-text" class="text-right small text-white fw-bold mt-1 mb-0 outlined-text-small" style="font-size: 18px; text-align: right;">
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
                                <li>Str: +<?= htmlspecialchars($item['att_str'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Dex: +<?= htmlspecialchars($item['att_dex'] ?? 0)  ?></li>
                                <li>Int: +<?= htmlspecialchars($item['att_int'] ?? 0)  ?></li>
                                <li>Fth: +<?= htmlspecialchars($item['att_fth'] ?? 0)  ?></li>
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
                                <li>Str: +<?= htmlspecialchars($item['att_str'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Dex: +<?= htmlspecialchars($item['att_dex'] ?? 0)  ?></li>
                                <li>Int: +<?= htmlspecialchars($item['att_int'] ?? 0)  ?></li>
                                <li>Fth: +<?= htmlspecialchars($item['att_fth'] ?? 0)  ?></li>
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
                                <li>Str: +<?= htmlspecialchars($item['att_str'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Dex: +<?= htmlspecialchars($item['att_dex'] ?? 0)  ?></li>
                                <li>Int: +<?= htmlspecialchars($item['att_int'] ?? 0)  ?></li>
                                <li>Fth: +<?= htmlspecialchars($item['att_fth'] ?? 0)  ?></li>
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
                                <li>Str: +<?= htmlspecialchars($item['att_str'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Dex: +<?= htmlspecialchars($item['att_dex'] ?? 0)  ?></li>
                                <li>Int: +<?= htmlspecialchars($item['att_int'] ?? 0)  ?></li>
                                <li>Fth: +<?= htmlspecialchars($item['att_fth'] ?? 0)  ?></li>
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
                                <li>Str: +<?= htmlspecialchars($item['att_str'] ?? 0)  ?></li>
                                <li>Def: +<?= htmlspecialchars($item['att_def'] ?? 0)  ?></li>
                                <li>Dex: +<?= htmlspecialchars($item['att_dex'] ?? 0)  ?></li>
                                <li>Int: +<?= htmlspecialchars($item['att_int'] ?? 0)  ?></li>
                                <li>Fth: +<?= htmlspecialchars($item['att_fth'] ?? 0)  ?></li>
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
</body>

</html>
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
            <div id="modal-inventory-wrapper" class="modal-body">
                <?php if (!empty($inventory_rows)): ?>
                    <?php foreach ($inventory_rows as $index => $item_row): 
                        $raw_type = strtolower(trim($item_row['item_type'] ?? ''));
                        $current_bag_id = intval($item_row['bag_id'] ?? 0);
                        // ✅ CHECKLIST: Flag whether item matches active elements in $equipped_bag_ids array
                        $is_currently_equipped = in_array($current_bag_id, $equipped_bag_ids);
                    ?>
                        <div class="row mb-2">
                            <div class="col-12">
                                <div class="p-2 d-flex justify-content-between align-items-center"
                                    style="background-color: <?= $is_currently_equipped ? '#E6F2FF' : '#FFF2E6' ?>; border: 2px solid #B46940; cursor: pointer;"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#item_<?= htmlspecialchars($current_bag_id) ?>_<?= $index ?>"
                                    aria-expanded="false">

                                    <div class="d-flex align-items-center gap-2">
                                        <img src="../asset/img/items/<?= htmlspecialchars($item_row['sprite'] ?: 'default.png') ?>"
                                            alt="<?= htmlspecialchars($item_row['item_name'] ?? 'Unknown Item') ?>"
                                            style="max-width: 40px; max-height: 40px; object-fit: contain; image-rendering: pixelated;">
                                        <p class="m-0 fw-bold">
                                            <?= htmlspecialchars($item_row['item_name'] ?? 'Unknown Item') ?>
                                            <?php if ($is_currently_equipped): ?>
                                                <span class="badge bg-primary ms-2 small">Equipped</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <p class="m-0 text-muted item-qty-label">x<?= htmlspecialchars($item_row['qty'] ?? 1) ?> ▾</p>
                                </div>

                                <div class="collapse" id="item_<?= htmlspecialchars($current_bag_id) ?>_<?= $index ?>">
                                    <div class="p-2 border-start border-end border-bottom" style="background-color: #FFFDFB; border-color: #B46940 !important;">
                                        <div class="d-flex gap-2 justify-content-end align-items-center mt-1">

                                            <?php if (in_array($raw_type, ['helmet', 'armor', 'boots', 'accessory', 'weapon', 'armaments', 'equipment'])): ?>
                                                <?php if ($is_currently_equipped): ?>
                                                    <button type="button" class="btn btn-sm btn-secondary text-white px-3" disabled>
                                                        Equipped
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button"
                                                        class="btn btn-sm btn-dark text-white px-3 ajax-equip-btn"
                                                        data-bag-id="<?= htmlspecialchars($current_bag_id) ?>"
                                                        data-player-id="<?= $player_id ?>"> Equip
                                                    </button>
                                                <?php endif; ?>

                                            <?php elseif ($raw_type === 'consumable' || $raw_type === 'consumables'): ?>
                                                <button type="button" 
                                                    class="btn btn-success btn-sm ajax-use-consumable-btn fw-bold px-3"
                                                    data-bag-id="<?= htmlspecialchars($current_bag_id) ?>"
                                                    data-player-id="<?= $player_id ?>"
                                                    data-max-hp="<?= $max_hp ?>"> Use Consumable
                                                </button>
                                            <?php endif; ?>

                                            <?php if (!$is_currently_equipped): ?>
                                                <button type="button" 
                                                    class="btn btn-sm btn-outline-danger px-3 ajax-drop-item-btn"
                                                    data-bag-id="<?= htmlspecialchars($current_bag_id) ?>"
                                                    data-player-id="<?= $player_id ?>">Drop
                                                </button>
                                            <?php endif; ?>
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
                                    <div id="sheet-hp-bar" class="progress-bar bg-danger"
                                        role="progressbar"
                                        style="width: <?= ($total_max_hp > 0) ? ($current_hp / $total_max_hp) * 100 : 0 ?>%; transition: width 0.4s ease;"
                                        aria-valuenow="<?= htmlspecialchars($current_hp) ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="<?= htmlspecialchars($total_max_hp) ?>">
                                    </div>
                                </div>
                                <p id="sheet-hp-text" class="text-right small text-white fw-bold mt-1 mb-0 outlined-text-small" style="font-size: 18px; text-align: right;">
                                    <?= htmlspecialchars($current_hp) ?> / <?= htmlspecialchars($total_max_hp) ?> HP
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex flex-column justify-content-between">
                        <div class="p-4 h-100 d-flex flex-column justify-content-between" style="background-color: rgba(180, 105, 64, 0.15); border-radius: 12px;">
                            <div>
                                <h4 class="fw-bold mb-3 border-bottom pb-2 text-dark" style="font-size: 22px; border-color: #B46940 !important;">Attribute Points : <?= $row['attribute_points'] ?? '0' ?></h4>

                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <span class="fw-bold text-muted text-uppercase">Level</span>
                                    <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($row['level'] ?? '0') ?></span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <span class="fw-bold text-muted text-uppercase">Class</span>
                                    <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($row['class'] ?? $row['class_name'] ?? 'Unknown') ?></span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <span class="fw-bold text-muted text-uppercase">Max HP</span>
                                    <span id="stat-display-max-hp" class="fw-bold text-dark fs-5"><?= $total_max_hp ?></span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <span class="fw-bold text-muted text-uppercase">Str</span>
                                    <span id="stat-display-str" class="fw-bold text-dark fs-5"><?= $total_str ?></span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <span class="fw-bold text-muted text-uppercase">Def</span>
                                    <span id="stat-display-def" class="fw-bold text-dark fs-5"><?= $total_def ?></span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <span class="fw-bold text-muted text-uppercase">Dex</span>
                                    <span id="stat-display-dex" class="fw-bold text-dark fs-5"><?= $total_dex ?></span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <span class="fw-bold text-muted text-uppercase">Int</span>
                                    <span id="stat-display-int" class="fw-bold text-dark fs-5"><?= $total_int ?></span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                    <span class="fw-bold text-muted text-uppercase">Fth</span>
                                    <span id="stat-display-fth" class="fw-bold text-dark fs-5"><?= $total_fth ?></span>
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
    // --- PRELOADER HUD CONTROL GATES ---
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
    // ✅ SCOPED IIFE BLOCK: Keeps drag variables safe from global conflicts
    (function() {
        if (typeof window.mapIsEngineInitialized === 'undefined') {
            window.mapIsEngineInitialized = false;
        }

        window.mapScale = 1;
        window.mapTranslateX = -700;
        window.mapTranslateY = -640;
        let isDragging = false;
        let dragStartX;
        let dragStartY;

        document.addEventListener("DOMContentLoaded", function() {
            if (window.mapIsEngineInitialized) {
                console.log("Map Engine: Dual execution blocked via defensive initialization runtime guard.");
                return;
            }
            window.mapIsEngineInitialized = true;

            console.log("Map Engine: Initializing Nearest-Neighbor Web Graph...");

            const mapContainer = document.getElementById('map');
            const svgContainer = document.getElementById('links');
            const viewport = document.querySelector('.viewport');
            if (!mapContainer || !svgContainer) return;

            mapContainer.innerHTML = '<svg id="links"></svg>';
            const freshSvgContainer = document.getElementById('links');

            const playerId = <?= json_encode((int)$id) ?>;
            const currentPlayerNodeId = <?= json_encode($currentNode) ?>;
            const characterClass = <?= json_encode($playerJob) ?>;
            const mapSeed = <?= json_encode($runSeed) ?>;

            console.log(`State Loaded -> Seed: ${mapSeed}, ActiveNode: ${currentPlayerNodeId}, Class: ${characterClass}`);

            function seededRandom(seed) {
                let m = 0x80000000;
                let a = 1103515245;
                let c = 12345;
                let s = seed;
                return function() {
                    s = (a * s + c) % m;
                    return s / (m - 1);
                };
            }
            const myRandom = seededRandom(mapSeed);

            const totalColumns = 14;
            const canvasWidth = 5000;
            const canvasHeight = 2500;

            let mapDataStructure = [];
            let globalEdges = [];
            let nodeSequenceCounter = 1;
            let combatStreak = 0;

            for (let col = 0; col <= totalColumns; col++) {
                mapDataStructure[col] = [];
                const columnX = 400 + (col * ((canvasWidth - 800) / totalColumns));

                let nodeCount = 0;

                if (col === 0 || col === totalColumns) {
                    nodeCount = 1;
                } else if (col === 1 || col === totalColumns - 1) {
                    nodeCount = Math.floor(myRandom() * 2) + 3;
                } else {
                    nodeCount = Math.floor(myRandom() * 3) + 4;
                }

                const segmentHeight = (canvasHeight - 600) / nodeCount;

                for (let i = 0; i < nodeCount; i++) {
                    let chosenType = 'combat';
                    let roll = myRandom();

                    if (combatStreak < 2) {
                        chosenType = (roll < 0.75) ? 'combat' : 'event';
                    } else {
                        if (roll < 0.55) chosenType = 'combat';
                        else if (roll < 0.80) chosenType = 'event';
                        else chosenType = 'shop';
                    }

                    if (chosenType === 'combat') combatStreak++;
                    if (chosenType === 'shop') combatStreak = 0;

                    if (col === 0) chosenType = 'start';
                    if (col === totalColumns) chosenType = 'boss';

                    let finalY = 300 + (i * segmentHeight) + (segmentHeight / 2) + (myRandom() * 40 - 20);
                    if (col === 0 || col === totalColumns) finalY = canvasHeight / 2;

                    let finalX = columnX + (col === 0 || col === totalColumns ? 0 : (myRandom() * 40 - 20));

                    let label = chosenType.charAt(0).toUpperCase() + chosenType.slice(1);
                    if (col === totalColumns) label = "BOSS";

                    mapDataStructure[col].push({
                        id: `node${nodeSequenceCounter++}`,
                        col: col,
                        nodeIdx: i,
                        x: Math.floor(finalX),
                        y: Math.floor(finalY),
                        type: chosenType,
                        label: label
                    });
                }
            }

            for (let col = 0; col < totalColumns; col++) {
                let currentLayer = mapDataStructure[col];
                let nextLayer = mapDataStructure[col + 1];
                let localEdges = [];

                currentLayer.forEach(u => {
                    let closestV = nextLayer[0];
                    let minDistance = Infinity;

                    nextLayer.forEach(v => {
                        let dist = Math.abs(u.y - v.y);
                        if (dist < minDistance) {
                            minDistance = dist;
                            closestV = v;
                        }
                    });
                    localEdges.push({
                        from: u.id,
                        to: closestV.id,
                        fromIdx: u.nodeIdx,
                        toIdx: closestV.nodeIdx
                    });
                });

                nextLayer.forEach(v => {
                    let closestU = currentLayer[0];
                    let minDistance = Infinity;

                    currentLayer.forEach(u => {
                        let dist = Math.abs(u.y - v.y);
                        if (dist < minDistance) {
                            minDistance = dist;
                            closestU = u;
                        }
                    });

                    if (!localEdges.some(e => e.from === closestU.id && e.to === v.id)) {
                        localEdges.push({
                            from: closestU.id,
                            to: v.id,
                            fromIdx: closestU.nodeIdx,
                            toIdx: v.nodeIdx
                        });
                    }
                });

                currentLayer.forEach(u => {
                    let connectedIndices = localEdges.filter(e => e.from === u.id).map(e => e.toIdx);
                    let minTargetIdx = Math.min(...connectedIndices);
                    let maxTargetIdx = Math.max(...connectedIndices);

                    let candidateTargets = [];
                    if (minTargetIdx > 0) candidateTargets.push(minTargetIdx - 1);
                    if (maxTargetIdx < nextLayer.length - 1) candidateTargets.push(maxTargetIdx + 1);

                    candidateTargets.forEach(targetIdx => {
                        if (myRandom() < 0.35) {
                            let v = nextLayer[targetIdx];

                            let crosses = localEdges.some(existingEdge => {
                                return (u.nodeIdx < existingEdge.fromIdx && targetIdx > existingEdge.toIdx) ||
                                    (u.nodeIdx > existingEdge.fromIdx && targetIdx < existingEdge.toIdx);
                            });

                            if (!crosses) {
                                localEdges.push({
                                    from: u.id,
                                    to: v.id,
                                    fromIdx: u.nodeIdx,
                                    toIdx: v.nodeIdx
                                });
                            }
                        }
                    });
                });

                localEdges.forEach(e => globalEdges.push(e));
            }

            for (let col = 0; col <= totalColumns; col++) {
                mapDataStructure[col].forEach(node => {
                    const nodeAnchor = document.createElement('a');
                    nodeAnchor.id = node.id;
                    nodeAnchor.className = `rpg-node rpg-node-${node.type}`;
                    nodeAnchor.style.position = 'absolute';
                    nodeAnchor.style.left = `${node.x}px`;
                    nodeAnchor.style.top = `${node.y}px`;

                    const labelSpan = document.createElement('span');
                    labelSpan.className = 'node-title-label';
                    labelSpan.textContent = node.label;
                    labelSpan.style.cssText = "position: absolute; bottom: -35px; left: 50%; transform: translateX(-50%); white-space: nowrap; color: #ffffff; text-shadow: 2px 2px 0px #000; font-weight: bold; font-size: 14px; pointer-events: none; z-index: 100;";

                    nodeAnchor.appendChild(labelSpan);
                    mapContainer.appendChild(nodeAnchor);
                });
            }

            let accessibleTargets = [];
            if (currentPlayerNodeId) {
                globalEdges.forEach(edge => {
                    if (edge.from === currentPlayerNodeId) accessibleTargets.push(edge.to);
                });
            } else {
                accessibleTargets = [mapDataStructure[0][0].id];
            }

            const nodeRoutes = {
                "rpg-node-combat": `in-combat/index.php?p=combat_level1&id=${playerId}`,
                "rpg-node-shop": `index.php?p=shop1&id=${playerId}`,
                "rpg-node-event": `index.php?p=event1&id=${playerId}`,
                "rpg-node-elite": `in-combat/index.php?p=elite1&id=${playerId}`,
                "rpg-node-boss": `in-combat/index.php?p=boss1&id=${playerId}`
            };

            const domNodes = document.querySelectorAll('.rpg-node');
            domNodes.forEach(node => {
                node.setAttribute("draggable", "false");

                if (node.id === currentPlayerNodeId) {
                    node.classList.add("rpg-node-current");
                    return;
                }

                if (accessibleTargets.includes(node.id)) {
                    node.classList.add("rpg-node-next");
                    node.addEventListener("click", function(e) {
                        e.preventDefault();
                        let destinationUrl = "#";
                        for (const [nodeClass, url] of Object.entries(nodeRoutes)) {
                            if (node.classList.contains(nodeClass)) {
                                destinationUrl = url;
                                break;
                            }
                        }

                        if (node.id === mapDataStructure[0][0].id && !currentPlayerNodeId) {
                            const formData = new FormData();
                            formData.append("player_id", playerId);
                            fetch("pages/processes/start_run.php", {
                                    method: "POST",
                                    body: formData
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) window.location.reload();
                                });
                            return;
                        }

                        if (destinationUrl !== "#") {
                            const nodeUpdateData = new FormData();
                            nodeUpdateData.append("player_id", playerId);
                            nodeUpdateData.append("node_id", node.id);
                            fetch("pages/processes/update_node.php", {
                                    method: "POST",
                                    body: nodeUpdateData
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) window.location.href = destinationUrl;
                                });
                        }
                    });
                    return;
                }

                if (node.id !== mapDataStructure[0][0].id || currentPlayerNodeId !== null) {
                    node.classList.add("rpg-node-locked");
                }
            });

            if (currentPlayerNodeId) {
                const activeNodeElement = document.getElementById(currentPlayerNodeId);
                if (activeNodeElement) {
                    activeNodeElement.classList.add("player-pointer");
                    activeNodeElement.setAttribute("data-class", characterClass.toLowerCase());
                }
            }

            if (freshSvgContainer) {
                freshSvgContainer.innerHTML = "";
                freshSvgContainer.setAttribute("width", canvasWidth);
                freshSvgContainer.setAttribute("height", canvasHeight);

                globalEdges.forEach(edge => {
                    const fromNodeEl = document.getElementById(edge.from);
                    const toNodeEl = document.getElementById(edge.to);

                    if (fromNodeEl && toNodeEl) {
                        const startX = parseInt(fromNodeEl.style.left) + (fromNodeEl.offsetWidth / 2 || 60);
                        const startY = parseInt(fromNodeEl.style.top) + (fromNodeEl.offsetHeight / 2 || 60);
                        const endX = parseInt(toNodeEl.style.left) + (toNodeEl.offsetWidth / 2 || 60);
                        const endY = parseInt(toNodeEl.style.top) + (toNodeEl.offsetHeight / 2 || 60);

                        const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
                        line.setAttribute("x1", startX);
                        line.setAttribute("y1", startY);
                        line.setAttribute("x2", endX);
                        line.setAttribute("y2", endY);
                        line.setAttribute("stroke", "white");
                        line.setAttribute("stroke-width", "4");
                        line.setAttribute("stroke-dasharray", "10 12");
                        line.setAttribute("data-from", edge.from);
                        line.setAttribute("data-to", edge.to);
                        freshSvgContainer.appendChild(line);
                    }
                });
            }

            window.autoCenterCameraOnActiveNode = function() {
                let targetNodeElement = null;

                if (currentPlayerNodeId) {
                    targetNodeElement = document.getElementById(currentPlayerNodeId);
                } else if (mapDataStructure[0] && mapDataStructure[0][0]) {
                    targetNodeElement = document.getElementById(mapDataStructure[0][0].id);
                }

                if (!viewport || !mapContainer || !targetNodeElement) return;

                const nodeX = parseInt(targetNodeElement.style.left) || 0;
                const nodeY = parseInt(targetNodeElement.style.top) || 0;

                const viewportWidth = viewport.clientWidth;
                const viewportHeight = viewport.clientHeight;

                const nodeWidth = targetNodeElement.offsetWidth || 60;
                const nodeHeight = targetNodeElement.offsetHeight || 60;

                window.mapTranslateX = (viewportWidth / 2) - (nodeX + (nodeWidth / 2)) * window.mapScale;
                window.mapTranslateY = (viewportHeight / 2) - (nodeY + (nodeHeight / 2)) * window.mapScale;

                mapContainer.style.transition = "transform 0.5s ease-out";
                mapContainer.style.transform = `translate(${window.mapTranslateX}px, ${window.mapTranslateY}px) scale(${window.mapScale})`;

                setTimeout(() => {
                    if (mapContainer) mapContainer.style.transition = "none";
                }, 500);
            };

            setTimeout(window.autoCenterCameraOnActiveNode, 200);

            if (viewport && mapContainer) {
                viewport.addEventListener('mousedown', (e) => {
                    isDragging = true;
                    mapContainer.style.transition = "none";

                    dragStartX = e.clientX - window.mapTranslateX;
                    dragStartY = e.clientY - window.mapTranslateY;
                    mapContainer.style.cursor = 'grabbing';
                });

                document.addEventListener('mousemove', (e) => {
                    if (!isDragging) return;
                    window.mapTranslateX = e.clientX - dragStartX;
                    window.mapTranslateY = e.clientY - dragStartY;
                    mapContainer.style.transform = `translate(${window.mapTranslateX}px, ${window.mapTranslateY}px) scale(${window.mapScale})`;
                });

                document.addEventListener('mouseup', () => {
                    isDragging = false;
                    mapContainer.style.cursor = 'grab';
                });
            }

            const zoomInBtn = document.getElementById('zoomIn');
            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', () => {
                    window.mapScale += 0.1;
                    mapContainer.style.transform = `translate(${window.mapTranslateX}px, ${window.mapTranslateY}px) scale(${window.mapScale})`;
                });
            }

            const zoomOutBtn = document.getElementById('zoomOut');
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', () => {
                    window.mapScale -= 0.1;
                    if (window.mapScale < 0.2) window.mapScale = 0.2;
                    mapContainer.style.transform = `translate(${window.mapTranslateX}px, ${window.mapTranslateY}px) scale(${window.mapScale})`;
                });
            }

            const centerMapBtn = document.getElementById('centerMap');
            if (centerMapBtn) {
                centerMapBtn.addEventListener('click', () => {
                    if (typeof window.autoCenterCameraOnActiveNode === 'function') {
                        window.autoCenterCameraOnActiveNode();
                    }
                });
            }
        });
    })();
</script>

<script>
    // --- GLOBAL AJAX ITEM EQUIPMENT SYSTEMS ---
    document.addEventListener("DOMContentLoaded", function() {
        document.body.addEventListener("click", function(e) {
            if (e.target && e.target.classList.contains("ajax-equip-btn")) {
                e.preventDefault();

                const button = e.target;
                const bagId = button.getAttribute("data-bag-id");
                const playerId = button.getAttribute("data-player-id");

                if (!bagId || !playerId) {
                    alert("Missing equip context data variables!");
                    return;
                }

                button.disabled = true;
                button.innerText = "Equipping...";

                fetch("pages/processes/equip_item.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: `player_id=${encodeURIComponent(playerId)}&bag_id=${encodeURIComponent(bagId)}`
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP status code error: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
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
    // --- UNEQUIP & SLOT RESTORATION TRANSACTIONS ---
    $(document).ready(function() {
        $(document).on('click', '.ajax-unequip-btn', function() {
            const button = $(this);
            const slotName = button.data('slot-name');
            const playerId = button.data('player-id');

            if (confirm(`Are you sure you want to unequip your ${slotName}?`)) {
                $.ajax({
                    url: 'pages/processes/unequip_item.php',
                    type: 'POST',
                    data: {
                        player_id: playerId,
                        slot_name: slotName
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
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
    // ==========================================================================
    // 🧪 CONSUMABLES & DECAY MECHANICS
    // ==========================================================================
    $(document).ready(function() {
        $(document).on('click', '.ajax-use-consumable-btn', function() {
            const button = $(this);
            const bagId = button.data('bag-id');
            const playerId = button.data('player-id');
            
            const currentClientMaxHp = parseInt($('#stat-display-max-hp').text()) || 100;
            const itemRowContainer = button.closest('.row.mb-2');

            button.prop('disabled', true).text('Processing...');

            $.ajax({
                url: 'pages/processes/use_consumable.php',
                type: 'POST',
                data: {
                    player_id: playerId,
                    bag_id: bagId,
                    client_max_hp: currentClientMaxHp
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        console.log("HUD Update Engine: " + response.message);

                        if (response.new_hp !== undefined) {
                            const newHp = parseInt(response.new_hp);
                            const percent = (newHp / currentClientMaxHp) * 100;

                            $('#hud-hp-bar, #sheet-hp-bar').css('width', percent + '%').attr('aria-valuenow', newHp);
                            
                            $('#hud-hp-text').text(newHp + ' / ' + currentClientMaxHp);
                            $('#sheet-hp-text').text(newHp + ' / ' + currentClientMaxHp + ' HP');
                        }

                        if (response.new_max_hp !== undefined) { $('#stat-display-max-hp').text(response.new_max_hp); }
                        if (response.new_str !== undefined)    { $('#stat-display-str').text(response.new_str); }
                        if (response.new_def !== undefined)    { $('#stat-display-def').text(response.new_def); }
                        if (response.new_dex !== undefined)    { $('#stat-display-dex').text(response.new_dex); }
                        if (response.new_int !== undefined)    { $('#stat-display-int').text(response.new_int); }
                        if (response.new_fth !== undefined)    { $('#stat-display-fth').text(response.new_fth); }

                        if (response.item_depleted) {
                            itemRowContainer.slideUp(300, function() {
                                $(this).remove();
                                if ($('#modal-inventory-wrapper .row.mb-2').length === 0) {
                                    $('#modal-inventory-wrapper').html('<div class="col-12 text-center text-muted py-3">Your bag is empty!</div>');
                                }
                            });
                        } else if (response.new_qty !== undefined) {
                            itemRowContainer.find('.item-qty-label').html('x' + response.new_qty + ' ▾');
                            button.prop('disabled', false).text('Use Consumable');
                        }

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

        // ==========================================================================
        // 🗑️ DROP ITEM MECHANICS
        // ==========================================================================
        $(document).on('click', '.ajax-drop-item-btn', function() {
            const button = $(this);
            const bagId = button.data('bag-id');
            const playerId = button.data('player-id');
            const itemRowContainer = button.closest('.row.mb-2');

            if (!confirm("Are you sure you want to discard this item permanently?")) return;

            button.prop('disabled', true).text('Dropping...');

            $.ajax({
                url: 'pages/processes/drop_item.php',
                type: 'POST',
                data: {
                    player_id: playerId,
                    bag_id: bagId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        itemRowContainer.slideUp(300, function() {
                            $(this).remove();
                            if ($('#modal-inventory-wrapper .row.mb-2').length === 0) {
                                $('#modal-inventory-wrapper').html('<div class="col-12 text-center text-muted py-3">Your bag is empty!</div>');
                            }
                        });
                    } else {
                        alert('Drop Failed: ' + response.message);
                        button.prop('disabled', false).text('Drop');
                    }
                },
                error: function() {
                    alert('An error occurred on the server while discarding item.');
                    button.prop('disabled', false).text('Drop');
                }
            });
        });
    });
</script>

</body>

</html>