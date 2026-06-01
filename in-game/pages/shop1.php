<?php
// Top of shop1.php
include '../conn.php';
global $conn;


// Get player ID from URL (required parameter)
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($player_id <= 0) {
    die("Error: No player character selected. Player ID is required.");
}

// --- AJAX POST CHECKOUT PROCESSING HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'checkout') {

    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (!empty($input['items'])) {
        $node_id = 7;

        foreach ($input['items'] as $purchasedItem) {
            $p_id = intval($purchasedItem['item_id']);
            if (!in_array($p_id, $_SESSION['shop_inventory'][$node_id]['purchased'])) {
                $_SESSION['shop_inventory'][$node_id]['purchased'][] = $p_id;
            }
        }

        // [DATABASE OPERATIONS]: Place DB queries here to subtract gold & update player inventory

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Cart payload missing.']);
    exit;
}

// --- GET SHOP DISCOVERY LOGIC ---
$current_map_level = 1;
// ... rest of your item loading and layout HTML code below ...

$shop_query = "SELECT i.*, ia.att_atk, ia.att_def, ia.att_hp, ia.att_max_hp, ia.att_spd 
               FROM shop_item si
               JOIN shop_pool sp ON si.shop_pool_id = sp.shop_pool_id
               JOIN item i ON si.item_id = i.item_id
               LEFT JOIN item_attributes ia ON i.id_item_attributes = ia.id_item_attributes
               WHERE sp.map_level = ?";

$stmt = mysqli_prepare($conn, $shop_query);
mysqli_stmt_bind_param($stmt, "i", $current_map_level);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$consumables = [];
$equipments = [];

while ($item = mysqli_fetch_assoc($result)) {
    $cleaned_type = trim(strtolower($item['item_type']));

    if ($cleaned_type === 'consumable' || $cleaned_type === 'consumables' || $cleaned_type === 'potion' || $cleaned_type === 'food') {
        $consumables[] = $item;
    } else if (in_array($cleaned_type, ['armaments', 'armor', 'helmet', 'boots', 'accessory', 'equipment', 'equipments'])) {
        $equipments[] = $item;
    } else {
        if (isset($item['id_item_attributes']) && !is_null($item['id_item_attributes'])) {
            $equipments[] = $item;
        } else {
            $consumables[] = $item;
        }
    }
}
mysqli_stmt_close($stmt);

$node_id = 7;

if (!isset($_SESSION['shop_inventory'][$node_id])) {
    shuffle($consumables);
    shuffle($equipments);

    $_SESSION['shop_inventory'][$node_id] = [
        'consumables' => array_column(array_slice($consumables, 0, 4), 'item_id'),
        'equipments'  => array_column(array_slice($equipments, 0, 4), 'item_id'),
        'purchased'   => []
    ];
}

$shopData = $_SESSION['shop_inventory'][$node_id];

// Filter consumables
$display_consumables = array_filter(
    $consumables,
    fn($item) => in_array(
        $item['item_id'],
        $shopData['consumables']
    )
);

// Filter equipments
$display_equipments = array_filter(
    $equipments,
    fn($item) => in_array(
        $item['item_id'],
        $shopData['equipments']
    )
);
?>

<style>
    .shop-item-card {
        transition: transform 0.2s ease-in-out !important;
    }

    .shop-item-card:hover:not(.sold-out) {
        transform: scale(1.05) !important;
        z-index: 999 !important;
    }

    .shop-item-card.selected {
        border: 4px solid #ffffff !important;
        transform: scale(1.05);
    }

    /* Dim and gray out checked-out items */
    .shop-item-card.sold-out {
        opacity: 0.40;
        filter: grayscale(100%);
        cursor: not-allowed !important;
        position: relative;
    }

    .shop-item-card.sold-out::after {
        content: "SOLD OUT";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        color: #ff3333;
        font-weight: bold;
        font-size: 1.4rem;
        border: 3px solid #ff3333;
        padding: 2px 6px;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 4px;
        z-index: 10;
        font-family: 'Jaro', sans-serif;
    }
</style>

<div class="row flex-grow-1" style="min-height: 0;">
    <div class="col-12 h-100">
        <div class="rounded-3 shadow-sm p-3 h-100" style="background-color: #FAC79B; overflow-y: auto; min-height: 870px;">
            <div class="row" style="padding:20px">
                <div class="col-7">
                    <div class="row">
                        <div class="col-12">
                            <h2 style="text-align: center; font-size:40px">Shop</h2>
                        </div>
                    </div>

                    <!-- CONSUMABLES POOL -->
                    <div class="row mb-2 pt-4" style="background-color:#D39670; border-radius:12px; min-height: 160px;">
                        <?php if (empty($display_consumables)): ?>
                            <div class="col-12 text-center text-white pb-4">
                                <p class="mb-0 pt-2 opacity-75">No consumables available in this pool.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($display_consumables as $item):
                                // Check if item ID is logged in your session's purchased array
                                $isSold = in_array($item['item_id'], $shopData['purchased']);
                                $tooltip = "<strong>" . htmlspecialchars($item['item_name']) . "</strong><br>🪙 Price: " . htmlspecialchars($item['price']) . " Gold<br><hr class='my-1'>" . (!empty($item['item_desc']) ? htmlspecialchars($item['item_desc']) : "No description provided.");
                                $jsonPayload = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
                            ?>
                                <div class="col-3">
                                    <div class="card text-dark mb-3 shop-item-card <?= $isSold ? 'sold-out' : '' ?>"
                                        id="card-<?= $item['item_id'] ?>"
                                        style="background-color: #FAC79B; border-radius: 12px; cursor: pointer; border:none; "
                                        <?= !$isSold ? 'onclick="addToCart(' . $jsonPayload . ')"' : '' ?>
                                        data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" title="<?= htmlspecialchars($tooltip) ?>">
                                        <div class="card-body p-2 text-center d-flex flex-column align-items-center justify-content-between" style="min-height: 180px; ">
                                            <div style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                                                <?php if (!empty($item['sprite'])): ?>
                                                    <img src="../asset/img/items/<?= htmlspecialchars($item['sprite']) ?>" style="max-width: 120px; max-height: 120px; width: auto; height: auto; object-fit: contain; image-rendering: pixelated;" alt="Sprite">
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge w-100" style="background-color: #B46940; font-size: 0.85rem;">🪙 <?= $item['price'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- EQUIPMENT POOL -->
                    <div class="row p-2 pt-4 mb-0" style="min-height: 260px; border-radius: 10px; background-color:#D39670;">
                        <?php if (empty($display_equipments)): ?>
                            <div class="col-12 text-center text-white pb-4">
                                <p class="mb-0 pt-2 opacity-75">No equipment available in this pool.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($display_equipments as $item):
                                // Check if item ID is logged in your session's purchased array
                                $isSold = in_array($item['item_id'], $shopData['purchased']);

                                $tooltipContent = !empty($item['item_name']) ? "<div style='color:#f75a68; text-align:center; font-weight:bold;'>{$item['item_name']}</div>" : "";
                                $tooltipContent .= "<div style='color:#d4a373; text-align:center; margin-bottom:5px;'>🪙 Price: {$item['price']} Gold</div>";
                                if (isset($item['att_atk']) && $item['att_atk'] > 0) $tooltipContent .= "<div style='color:#f75a68;'>⚔️ +{$item['att_atk']} ATK</div>";
                                if (isset($item['att_def']) && $item['att_def'] > 0) $tooltipContent .= "<div style='color:#48cae4;'>🛡️ +{$item['att_def']} DEF</div>";
                                if (isset($item['att_spd']) && $item['att_spd'] > 0) $tooltipContent .= "<div style='color:#ffb703;'>⚡ +{$item['att_spd']} SPD</div>";
                                if (isset($item['att_max_hp']) && $item['att_max_hp'] > 0) $tooltipContent .= "<div style='color:#00b4d8;'>❤️ +{$item['att_max_hp']} MaxHP</div>";
                                if (!empty($item['item_desc'])) $tooltipContent .= "<div class='border-top border-secondary mt-1 pt-1 text-white-50' style='font-size:0.75rem;'><span class='text-white'>Info:</span><br>" . htmlspecialchars($item['item_desc']) . "</div>";
                                $safeTooltip = htmlspecialchars($tooltipContent, ENT_QUOTES, 'UTF-8');

                                $jsSafeItem = $item;
                                if (!empty($jsSafeItem['item_desc'])) {
                                    $jsSafeItem['item_desc'] = str_replace(["\r", "\n"], " ", $jsSafeItem['item_desc']);
                                }
                                $jsonPayload = htmlspecialchars(json_encode($jsSafeItem), ENT_QUOTES, 'UTF-8');
                            ?>
                                <div class="col-3">
                                    <!-- FIXED: Added border: none; below to clear the default frame line -->
                                    <div class="card text-dark mb-3 shop-item-card <?= $isSold ? 'sold-out' : '' ?>"
                                        id="card-<?= $item['item_id'] ?>"
                                        style="background-color: #FAC79B; border-radius: 12px; cursor: pointer; border: none;"
                                        <?= !$isSold ? 'onclick="addToCart(' . $jsonPayload . ')"' : '' ?>
                                        data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" data-bs-title="<?= $safeTooltip ?>">
                                        <div class="card-body p-2 text-center d-flex flex-column align-items-center justify-content-between" style="min-height: 180px;">
                                            <div style="width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                                                <?php if (!empty($item['sprite'])): ?>
                                                    <img src="../asset/img/items/<?= htmlspecialchars($item['sprite']) ?>" style="max-width: 120px; max-height: 120px; width: auto; height: auto; object-fit: contain; image-rendering: pixelated;" alt="Sprite">
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge w-100" style="background-color: #B46940; font-size: 0.85rem;">🪙 <?= $item['price'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="row mt-2">
                        <div class="col-6 text-center">
                            <a href="index.php?p=level1&id=7" onclick="triggerMapLeaveTransition(event, this.href)" class="btn btn-lg" style="background-color:#D39670; border-radius:12px; color:#fff; width:220px;">
                                <h2 class="m-0" style="font-family: 'Jaro', sans-serif; font-size: 24px;">Back To Map!</h2>
                            </a>
                        </div>
                        <div class="col-6 text-center">
                            <button type="button" onclick="initiateCheckout()" class="btn btn-lg mb-0" style="background-color: #D39670; border-radius: 12px; color: #fff; width: 240px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; font-weight: bold;">
                                <h2 class="m-0" style="font-family: 'Jaro', sans-serif; font-size: 24px;">Complete Purchase</h2>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-5 d-flex flex-column justify-content-center align-items-center" style="min-height: 300px; position: relative; z-index: 2;">
                    <div class="container-lg text-center">
                        <img id="altaria-sprite" src="../asset/sprites/shop/altaria.png" alt="Altaria Shopkeeper" onclick="triggerAltariaInteraction(event)" onanimationend="this.classList.remove('sprite-jump')" style="max-width: 100%; height: 600px; border:6px solid #D39670; border-radius:32px; margin-left:70px; display: block; image-rendering: pixelated; cursor: pointer; position: relative; z-index: 3;">
                    </div>
                </div>

                <div class="position-absolute w-100" style="bottom: 310px; left: 0; z-index: 9;">
                    <div style="position: absolute; right: 220px; top: 0; width: 240px;">
                        <div class="card text-white pt-0 pb-1 pl-2 pr-2" style="background-color: #FAC79B; border: 4px solid #D39670; border-radius: 10px; width: max-content; height: 50px">
                            <h4 class="fw-bold mb-0" style="font-family: 'Jaro'; color: #fff; text-align: center; font-size: 32px">Altaria, The Shopkeeper</h4>
                        </div>
                    </div>
                </div>

                <div class="row w-100 position-absolute" style="bottom: 20px; right: 0; z-index: 10; margin: 0; padding: 0 15px;">
                    <div class="col-12">
                        <div class="card text-white p-3" style="height: 200px; background-color: #FAC79B; border: 10px solid #D39670; border-radius: 12px; position: relative;">
                            <p id="altaria-dialogue-text" class="m-0" style="font-size: 30px;">"Welcome to my shop, traveler!~ I have some stuff you might like~"</p>

                            <div id="checkout-buttons" style="display: none; position: absolute; bottom: 20px; right: 30px;">
                                <button onclick="confirmPurchase()" class="btn btn-success me-2" style="font-size: 20px; font-family: 'Jaro';">Yes, please!</button>
                                <button onclick="cancelPurchase()" class="btn btn-danger" style="font-size: 20px; font-family: 'Jaro';">Wait, no.</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    const shoppingDialogues = [{
        text: '"This one is not sold, silly!"',
        expression: '../asset/sprites/shop/altaria_smiling.png'
    }];

    let cart = [];

    function playBounce() {
        const sprite = document.getElementById('altaria-sprite');
        if (sprite) {
            sprite.classList.remove('sprite-jump');
            void sprite.offsetWidth;
            sprite.classList.add('sprite-jump');
        }
    }

    function addToCart(item) {
        const card = document.getElementById('card-' + item.item_id);

        // Block interaction entirely if card is flagged sold-out
        if (card.classList.contains('sold-out')) return;

        const index = cart.findIndex(i => i.item_id === item.item_id);
        const sprite = document.getElementById('altaria-sprite');
        const dialogueText = document.getElementById('altaria-dialogue-text');

        playBounce();

        if (index > -1) {
            cart.splice(index, 1);
            card.classList.remove('selected');
            sprite.src = '../asset/sprites/shop/altaria_confused.png';
            dialogueText.textContent = '"You don\'t want that anymore?"';
        } else {
            cart.push(item);
            card.classList.add('selected');
            sprite.src = '../asset/sprites/shop/altaria_smiling.png';
            dialogueText.textContent = '"Good choice!"';
        }
    }

    function initiateCheckout() {
        playBounce();
        const sprite = document.getElementById('altaria-sprite');
        const dialogueText = document.getElementById('altaria-dialogue-text');
        const btnContainer = document.getElementById('checkout-buttons');

        if (cart.length === 0) {
            sprite.src = '../asset/sprites/shop/altaria_confused.png';
            dialogueText.textContent = '"Did you forget something?"';
            return;
        }

        let total = cart.reduce((sum, item) => sum + parseInt(item.price), 0);
        sprite.src = '../asset/sprites/shop/altaria.png';
        dialogueText.textContent = '"That will be ' + total + ' gold."';
        btnContainer.style.display = 'block';
    }

    function confirmPurchase() {
        console.log("YES BUTTON CLICKED");

        if (cart.length === 0) return;

        // 1. Grab the player's current gold balance straight from the sidebar HTML
        const goldCounter = document.getElementById('player-gold');
        const currentGold = goldCounter ? parseInt(goldCounter.textContent) : 0;

        // 2. Calculate the total cost of the cart right now
        // (Note: This assumes your cart items store their price, e.g., {item_id: 7, price: 50})
        const totalCost = cart.reduce((sum, item) => sum + parseInt(item.price || 0), 0);

        // 3. FRONTEND GOLD CHECK
        // FRONTEND GOLD CHECK
        if (currentGold < totalCost) {
            const dialogueText = document.getElementById('altaria-dialogue-text');
            const sprite = document.getElementById('altaria-sprite');

            // 1. Change dialogue text
            dialogueText.textContent = '"Hey, you don\'t have enough gold for that!"';

            // 2. Swop to the sighing sprite
            sprite.src = '../asset/sprites/shop/altaria_sigh.png';

            playBounce(); // Play your animation effect
            return; // STOP RIGHT HERE. Do not talk to the server.
        }

        // 4. If they CAN afford it, proceed with the server transaction as normal
        const urlParams = new URLSearchParams(window.location.search);
        const playerId = urlParams.get('player_id') || urlParams.get('id');
        const checkoutUrl = './pages/checkout.php';

        fetch(checkoutUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    items: cart,
                    player_id: playerId
                })
            })
            .then(response => {
                if (!response.ok) throw new Error("HTTP error " + response.status);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update gold counter on screen
                    if (goldCounter && data.new_gold !== undefined) {
                        goldCounter.textContent = data.new_gold;
                    }

                    const sprite = document.getElementById('altaria-sprite');
                    const dialogueText = document.getElementById('altaria-dialogue-text');

                    sprite.src = '../asset/sprites/shop/altaria_smiling.png';
                    dialogueText.textContent = '"Thanks for the purchase!"';

                    cart.forEach(item => {
                        const card = document.getElementById('card-' + item.item_id);
                        if (card) {
                            card.classList.remove('selected');
                            card.classList.add('sold-out');
                            card.removeAttribute('onclick');
                        }
                    });

                    cart = [];
                    document.getElementById('checkout-buttons').style.display = 'none';
                    playBounce();
                } else {
                    alert("Transaction handler failure: " + (data.message || ""));
                }
            })
            .catch(err => {
                console.error("Error committing checkout workflow:", err);
            });
    }

    function cancelPurchase() {
        const sprite = document.getElementById('altaria-sprite');
        const dialogueText = document.getElementById('altaria-dialogue-text');

        sprite.src = '../asset/sprites/shop/altaria_smiling.png';
        dialogueText.textContent = '"No problem! Take your time deciding."';
        document.getElementById('checkout-buttons').style.display = 'none';
        playBounce();
    }

    function triggerAltariaInteraction(event) {
        if (event) event.stopPropagation();
        playBounce();

        const sprite = document.getElementById('altaria-sprite');
        const dialogueText = document.getElementById('altaria-dialogue-text');

        if (sprite && dialogueText) {
            const randomDialogue = shoppingDialogues[Math.floor(Math.random() * shoppingDialogues.length)];
            sprite.src = randomDialogue.expression;
            dialogueText.textContent = randomDialogue.text;
        }
    }

    function triggerMapLeaveTransition(event, destinationUrl) {
        event.preventDefault();
        playBounce();

        const sprite = document.getElementById('altaria-sprite');
        const dialogueText = document.getElementById('altaria-dialogue-text');

        sprite.src = "../asset/sprites/shop/altaria_smiling.png";
        dialogueText.textContent = '"Good luck out there, traveler!~ Come back safely!"';

        setTimeout(function() {
            window.location.href = destinationUrl;
        }, 1500);
    }
</script>

<style>
    @keyframes retroJump {
        0% {
            transform: translateY(0) scaleY(1);
        }

        15% {
            transform: translateY(3px) scaleY(1);
        }

        40% {
            transform: translateY(-20px) scaleY(1);
        }

        70% {
            transform: translateY(0) scaleY(1);
        }

        100% {
            transform: translateY(0) scaleY(1);
        }
    }

    .sprite-jump {
        animation: retroJump 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
    }
</style>