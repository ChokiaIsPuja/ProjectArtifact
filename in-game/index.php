<?php
include __DIR__ . '/../conn.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// The complete relational JOIN query string — NOW WITH PLAYER STATS JOINED!
$query = "SELECT 
            p.*, 
            c.class_name, 
            c.avatar, 
            c.base_hp,
            ps.curr_max_hp, -- 1. Grabbing current hp from player_stats
            IFNULL(SUM(ia.att_max_hp), 0) AS total_bonus_hp
          FROM player p
          LEFT JOIN class c ON p.class_id = c.class_id
          -- 2. CRUCIAL FIX: Added the missing relationship link to stats
          LEFT JOIN player_stats ps ON p.player_id = ps.player_id 
          -- 3. Link to the player's active equipment slots
          LEFT JOIN player_equipment pe ON pe.inventory_id IN (
              SELECT inventory_id FROM inventory WHERE player_id = p.player_id
          )
          -- 4. Link from equipment slots to the actual inventory item slot
          LEFT JOIN inventory i ON pe.inventory_id = i.inventory_id
          -- 5. Link from the inventory slot to the base item definition
          LEFT JOIN item it ON i.item_id = it.item_id
          -- 6. Link from the item definition to its stat attributes block
          LEFT JOIN item_attributes ia ON it.id_item_attributes = ia.id_item_attributes
          WHERE p.player_id = ?
          GROUP BY p.player_id, c.class_id, ps.player_stat_id"; // 7. Included ps in grouping

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

// Set structural defaults to prevent breaking frontend HTML if record is missing
$max_hp = 0;
$current_hp = 0;

if ($row) {
    // 1. Calculate the overall ceiling limit dynamically (Base Class HP + Gear Modifiers)
    $max_hp = (int)$row['base_hp'] + (int)$row['total_bonus_hp'];

    // 2. Extract the actual, real-time current health saved in your database
    // This will now execute perfectly without array index errors!
    $current_hp = (int)$row['curr_max_hp'];
    $current_hp -= 5; // Simulate damage taken for testing purposes 
    // 3. Safety Guard: Make sure current health never accidentally exceeds maximum health
    if ($current_hp > $max_hp) {
        $current_hp = $max_hp;
    } else if ($current_hp < 0) {
        $current_hp = 0; // Prevent negative health values

        // echo "Debug: Max HP calculated as $max_hp (Base: {$row['base_hp']} + Bonus: {$row['total_bonus_hp']})<br>";
        // echo "Debug: Current HP is $current_hp<br>"; Works "Thumbs up"
    }

?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="asset/css/bootstrap.css">
        <link rel="stylesheet" href="asset/css/preloader.css">
        <title>ProjectArtifact - Play</title>
        <style>
            /* Prevents the sidebar layout context from squishing content wrapper zones */
            .workspace-content {
                margin-left: 300px;
                /* Gives 20px padding away from your 250px wide nav menu */
                padding-top: 20px;
            }
        </style>
    </head>

    <body class="bg-secondary">
        <div id="preloader">
            <img src="../asset/img/loading.png" alt="Loading..." class="preloader-image">
        </div>
        <nav class="text-white position-fixed p-3" style="height: 100vh; width: 300px; top: 0; left: 0; z-index: 1000; background-color: #b45b5b; ">
            <h1 class="h5 text-center mt-2 fw-bold mb-4">Equipments</h1>

            <div class="container-fluid mt-3 px-2" style="background-color: #b45b5b;">
                <!-- IMAGES ARE PLACEHOLDER -->
                <div class="row g-2 mb-3 text-center">
                    <div class="col-12">
                        <a style="text-decoration: none;" href="#">
                            <div class="card d-flex flex-column justify-content-end p-2 w-100" style="height: 120px;background-color: #FAC79B;">
                                <img src="../asset/img/inventory.png" alt="Inventory" class="mx-auto" style="max-width: 80px; max-height: 80px;">
                                <p style="margin: 0;" class="text-center small fw-bold text-dark">
                                    Inventory
                                </p>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row g-2 mb-2 text-center">
                    <div class="col-6">
                        <a style="text-decoration: none;" href="#">
                            <div class="card d-flex flex-column justify-content-end p-2 h-100" style="height: 120px;background-color: #FAC79B;">
                                <img src="../asset/img/helmet.png" alt="Helmet" class="mx-auto" style="max-width: 80px; max-height: 80px;">
                                <p class="m-0 text-center small fw-bold text-dark">
                                    Helmet
                                </p>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a style="text-decoration: none;" href="#">
                            <div class="card d-flex flex-column justify-content-end p-2 h-100" style="height: 120px;background-color: #FAC79B;">
                                <img src="../asset/img/armor.png" alt="Armor" class="mx-auto" style="max-width: 80px; max-height: 80px;">
                                <p class="m-0 text-center small fw-bold text-dark">
                                    Armor
                                </p>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row g-2 text-center">
                    <div class="col-6">
                        <a style="text-decoration: none;" href="#">
                            <div class="card d-flex flex-column justify-content-end p-2 h-100" style="height: 120px;background-color: #FAC79B;">
                                <img src="../asset/img/boots.png" alt="Boots" class="mx-auto" style="max-width: 80px; max-height: 80px;">
                                <p class="m-0 text-center small fw-bold text-dark">
                                    Boots
                                </p>
                            </div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a class="text-decoration-none" href="#">
                            <div class="card d-flex flex-column justify-content-end p-2 h-100" style="height: 120px;background-color: #FAC79B;">
                                <img src="../asset/img/accessory.webp" alt="Accessory" class="mx-auto" style="max-width: 80px; max-height: 80px;">
                                <p class="m-0 text-center small fw-bold text-dark">
                                    Accessory
                                </p>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="row g-2 mb-3 text-center">
                    <div class="col-12">
                        <a class="text-decoration-none" href="#">
                            <div class="card d-flex flex-column justify-content-end p-2 w-100" style="height: 120px; margin-top: 10px;background-color: #FAC79B;">
                                <img src="../asset/img/weapon.png" alt="Weapon" class="mx-auto" style="max-width: 220px; max-height: 80px; margin:0;">
                                <p style="margin: 0;" class="text-center small fw-bold text-dark">
                                    Armaments
                                </p>
                            </div>
                        </a>

                    </div>
                </div>
                <div class="row g-2 mb-3 text-center">
                    <div class="col-12">
                        <div class="card p-0 w-100 overflow-hidden"
                            style="height: 240px; position: relative; border:none;">

                            <img src="../asset/sprites/classes/<?= htmlspecialchars($row['avatar']) ?>"
                                alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; display: block; image-rendering: pixelated;">
                            <div style="position: absolute;left: 0;bottom: 0;width: 100%;background: rgba(255, 255, 255, 0);padding: 4px 0; margin-top:120px">
                                <p class="m-0 text-center fw-bold" style="font-size: 20px; color:white;background-color:rgb(226, 73, 73);">
                                    <?= htmlspecialchars($row['name'] ?? 'Hero') ?>
                                </p>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="progress" style="height: 20px;">

                            <?php
                            // Calculate the percentage safely, avoiding a division by zero error if data drops
                            $hp_percentage = ($max_hp > 0) ? ($current_hp / $max_hp) * 100 : 0;
                            ?>

                            <div class="progress-bar bg-danger"
                                role="progressbar"
                                style="width: <?= $hp_percentage ?>%; transition: width 0.4s ease;"
                                aria-valuenow="<?= htmlspecialchars($current_hp) ?>"
                                aria-valuemin="0"
                                aria-valuemax="<?= htmlspecialchars($max_hp) ?>">
                            </div>

                        </div>

                        <p class="text-center small text-white fw-bold mt-1 mb-0" style="text-shadow: 1px 1px 2px black;">
                            HP: <?= htmlspecialchars($current_hp) ?> / <?= htmlspecialchars($max_hp) ?>

                        </p>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-lg workspace-content px-3 d-flex flex-column" style="height: 100vh; overflow: hidden; max-width: 100rem;">
            <div class="row flex-grow-1 mb-3" style="min-height: 0;">
                <div class="col-12 h-100">
                    <div class="p-3 bg-light rounded-3 shadow-sm h-100"
                        style="overflow-y: auto; overflow-x: hidden;">

                    <?php
                    include "pages/content.php";
                }
                // DO NOT MOVE THIS, IT IS FOR THE HEALTH CHECK SO IT DOESNT OVERFLOW
                    ?>
                    </div>
                </div>
            </div>

        </div>
        <script type="text/javascript" src="asset/js/jquery-3.7.1.js"></script>
        <script type="text/javascript" src="asset/js/bootstrap.bundle.min.js"></script>
        <script type="text/javascript" src="asset/js/script.js"></script>

        <script type="text/javascript">
            $(function() {
                if ($('#beginButton').length) {
                    $('#beginButton').prop('disabled', true);
                }

                $('#beginButton').prop('disabled', true);
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
    </body>

    </html>