<?php
// admin_insert_item.php
require 'conn.php'; // Adjust path to your database connection file
global $conn;

$message = "";
$messageType = "";

// 1. BACKEND FORM PROCESSING (Executes only when the form is submitted via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['item_name']) || empty($_POST['item_type'])) {
        header("Location: admin_insert_item.php?status=missing");
        exit;
    }

    // Extract base specifications
    $item_name  = mysqli_real_escape_string($conn, $_POST['item_name']);
    $item_type  = mysqli_real_escape_string($conn, $_POST['item_type']);
    $item_desc  = mysqli_real_escape_string($conn, $_POST['item_desc'] ?? '');
    $buy_price  = isset($_POST['buy_price']) ? intval($_POST['buy_price']) : 0;
    $sell_price = (int)($buy_price * 0.4);
    $sprite     = mysqli_real_escape_string($conn, $_POST['sprite'] ?? '../asset/sprites/items/default.png');

    // Extract combat attribute modifiers
    $att_str    = isset($_POST['att_str']) ? intval($_POST['att_str']) : 0;
    $att_def    = isset($_POST['att_def']) ? intval($_POST['att_def']) : 0;
    $att_dex    = isset($_POST['att_dex']) ? intval($_POST['att_dex']) : 0;
    $att_heal   = isset($_POST['att_heal']) ? intval($_POST['att_heal']) : 0;
    $att_max_hp = isset($_POST['att_max_hp']) ? intval($_POST['att_max_hp']) : 0;

    // Extract stat requirements (equipment_att_req)
    $req_str    = isset($_POST['req_str']) ? intval($_POST['req_str']) : 0;
    $req_def    = isset($_POST['req_def']) ? intval($_POST['req_def']) : 0;
    $req_dex    = isset($_POST['req_dex']) ? intval($_POST['req_dex']) : 0;
    $req_int    = isset($_POST['req_int']) ? intval($_POST['req_int']) : 0;
    $req_fth    = isset($_POST['req_fth']) ? intval($_POST['req_fth']) : 0;


    // Begin transaction for database atomicity
    mysqli_begin_transaction($conn);

    try {
        // PHASE A: Insert the ITEM first to get the ID
        $item_query = "INSERT INTO item (item_name, item_type, item_desc, buy_price, sell_price, sprite) 
                   VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_item = mysqli_prepare($conn, $item_query);
        mysqli_stmt_bind_param($stmt_item, "sssiis", $item_name, $item_type, $item_desc, $buy_price, $sell_price, $sprite);
        mysqli_stmt_execute($stmt_item);
        $item_id = mysqli_insert_id($conn); // Get the ID of the new item
        mysqli_stmt_close($stmt_item);

        // PHASE B: Insert into item_attributes
        $attr_query = "INSERT INTO item_attributes (att_str, att_def, att_max_hp, att_heal, att_dex, att_int, att_fth) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_attr = mysqli_prepare($conn, $attr_query);
        mysqli_stmt_bind_param($stmt_attr, "iiiiiii", $att_str, $att_def, $att_max_hp, $att_heal, $att_dex, $req_int, $req_fth);
        mysqli_stmt_execute($stmt_attr);
        $id_item_attributes = mysqli_insert_id($conn);

        // Update the item to link the attributes (if you have that column)
        $update_item = "UPDATE item SET id_item_attributes = ? WHERE item_id = ?";
        $stmt_upd = mysqli_prepare($conn, $update_item);
        mysqli_stmt_bind_param($stmt_upd, "ii", $id_item_attributes, $item_id);
        mysqli_stmt_execute($stmt_upd);

        // PHASE C: Insert into equipment_att_req (using the item_id we just got)
        $req_query = "INSERT INTO equipment_att_req (req_str, req_def, req_dex, req_int, req_fth, item_id) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_req = mysqli_prepare($conn, $req_query);
        mysqli_stmt_bind_param($stmt_req, "iiiiii", $req_str, $req_def, $req_dex, $req_int, $req_fth, $item_id);
        mysqli_stmt_execute($stmt_req);

        mysqli_commit($conn);
        header("Location: admin_insert_item.php?status=success");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        // CRITICAL: Echo the error to see exactly what is failing
        die("Database Error: " . $e->getMessage());
    }
}

// 2. CHECK STATUS ALERTS FOR THE FRONTEND
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = "🎉 Success! Item successfully forged and added to database.";
        $messageType = "success";
    } elseif ($_GET['status'] === 'missing') {
        $message = "⚠️ Missing Fields: Item Name and Type are required.";
        $messageType = "error";
    } elseif ($_GET['status'] === 'error') {
        $message = "❌ Database Failure: Could not save item data. " . htmlspecialchars($_GET['err'] ?? 'Unknown Error');
        $messageType = "error";
    }
}

// 3. FETCH DATA WITH JOINED MODIFIERS AND REQUIREMENTS
$allQuery = "SELECT i.*, 
                    ia.att_str, ia.att_def, ia.att_dex, ia.att_heal, ia.att_max_hp, ia.att_int, ia.att_fth,
                    er.req_str, er.req_def, er.req_dex, er.req_int, er.req_fth
             FROM item i
             LEFT JOIN item_attributes ia ON i.item_id = ia.item_id
             LEFT JOIN equipment_att_req er ON i.item_id = er.item_id
             ORDER BY i.item_id DESC";
$itemsResult = mysqli_query($conn, $allQuery);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Forge Panel - Master Item Database</title>
</head>

<body style="background: #121214; color: #e1e1e6; font-family: monospace; padding: 20px;">

    <div style="max-width: 1200px; margin: 20px auto; background: #202024; padding: 30px; border-radius: 6px; border: 1px solid #323238;">

        <h2 style="margin-top: 0; color: #5757df; border-bottom: 1px solid #323238; padding-bottom: 10px;">⚔️ Item Insertion Panel</h2>

        <?php if (!empty($message)): ?>
            <div style="padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; 
                        background: <?= $messageType === 'success' ? '#123b24' : '#421616' ?>; 
                        color: <?= $messageType === 'success' ? '#04d361' : '#f75a68' ?>;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form action="admin_insert_item.php" method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">

                <div>
                    <h3 style="color: #ffcc00; margin-top: 0; margin-bottom: 15px;">1. Base Specs</h3>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Item Name *</label>
                        <input type="text" name="item_name" required style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Item Type *</label>
                        <select name="item_type" required style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px;">
                            <option value="weapon">Weapon</option>
                            <option value="helmet">Helmet</option>
                            <option value="armor">Armor</option>
                            <option value="boots">Boots</option>
                            <option value="accessory">Accessory</option>
                            <option value="consumable">Consumable</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Item Description</label>
                        <textarea name="item_desc" rows="2" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; resize: none; box-sizing: border-box;"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; margin-bottom:4px;">Buy Price</label>
                            <input type="number" name="buy_price" value="0" min="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                        </div>
                    </div>

                    <div>
                        <label style="display:block; margin-bottom:5px;">Sprite URL Path</label>
                        <input type="text" name="sprite" value="" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>
                </div>

                <div style="border-left: 1px solid #323238; padding-left: 20px;">
                    <h3 style="color: #ffcc00; margin-top: 0; margin-bottom: 15px;">2. Combat Modifiers</h3>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Strength Boost (att_str)</label>
                        <input type="number" name="att_str" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Defense Boost (att_def)</label>
                        <input type="number" name="att_def" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Dexterity Boost (att_dex)</label>
                        <input type="number" name="att_dex" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Intelligence Boost (att_int)</label>
                        <input type="number" name="att_int" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Faith Boost (att_fth)</label>
                        <input type="number" name="att_fth" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Heal Amount (att_heal)</label>
                        <input type="number" name="att_heal" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>

                    <div>
                        <label style="display:block; margin-bottom:5px;">Max HP Structural Boost</label>
                        <input type="number" name="att_max_hp" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>
                </div>

                <div style="border-left: 1px solid #323238; padding-left: 20px; display: flex; flex-direction: column; justify-content: space-between;">
                    <div style="width: 100%;">
                        <h3 style="color: #ff9f1c; margin-top: 0; margin-bottom: 15px;">3. Stat Requirements</h3>

                        <div style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:5px;">Required STR (req_str)</label>
                            <input type="number" name="req_str" value="0" min="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#ff9f1c; border-radius:4px; box-sizing: border-box;">
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:5px;">Required DEF (req_def)</label>
                            <input type="number" name="req_def" value="0" min="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#ff9f1c; border-radius:4px; box-sizing: border-box;">
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:5px;">Required DEX (req_dex)</label>
                            <input type="number" name="req_dex" value="0" min="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#ff9f1c; border-radius:4px; box-sizing: border-box;">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:5px;">Required INT (req_int)</label>
                            <input type="number" name="req_int" value="0" min="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#ff9f1c; border-radius:4px; box-sizing: border-box;">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display:block; margin-bottom:5px;">Required FTH (req_fth)</label>
                            <input type="number" name="req_fth" value="0" min="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#ff9f1c; border-radius:4px; box-sizing: border-box;">
                        </div>
                    </div>

                    <button type="submit" style="background:#5757df; color:white; font-weight:bold; border:none; padding:12px; width:100%; border-radius:4px; cursor:pointer; font-size:1rem; font-family: monospace; align-self: flex-end;">
                        Forge Item & Commit Data
                    </button>
                </div>

            </div>
        </form>
    </div>

    <div style="max-width: 1200px; margin: 30px auto; background: #202024; padding: 30px; border-radius: 6px; border: 1px solid #323238;">
        <h2 style="margin-top: 0; color: #2a9d8f; border-bottom: 1px solid #323238; padding-bottom: 10px;">📦 Live Item Registry Database</h2>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #323238; color: #b5b5c3;">
                        <th style="padding: 12px 6px;">ID</th>
                        <th style="padding: 12px 6px;">Sprite</th>
                        <th style="padding: 12px 6px;">Item Name</th>
                        <th style="padding: 12px 6px;">Type</th>
                        <th style="padding: 12px 6px;">Buy/Sell Price</th>
                        <th style="padding: 12px 6px; text-align: center; color: #f75a68;">STR</th>
                        <th style="padding: 12px 6px; text-align: center; color: #48cae4;">DEF</th>
                        <th style="padding: 12px 6px; text-align: center; color: #ffb703;">DEX</th>
                        <th style="padding: 12px 6px; text-align: center; color: #2a9d8f;">INT</th>
                        <th style="padding: 12px 6px; text-align: center; color: #5757df;">FTH</th>
                        <th style="padding: 12px 6px; text-align: center; color: #04d361;">HEAL</th>
                        <th style="padding: 12px 6px; text-align: center; color: #00b4d8;">MAX HP</th>
                        <th style="padding: 12px 6px; text-align: center; color: #ff9f1c; border-left: 1px solid #323238;">REQ STR</th>
                        <th style="padding: 12px 6px; text-align: center; color: #ff9f1c;">REQ DEF</th>
                        <th style="padding: 12px 6px; text-align: center; color: #ff9f1c;">REQ DEX</th>
                        <th style="padding: 12px 6px; text-align: center; color: #ff9f1c;">REQ INT</th>
                        <th style="padding: 12px 6px; text-align: center; color: #ff9f1c;">REQ FTH</th>
                        <th style="padding: 12px 6px;">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($itemsResult) === 0): ?>
                        <tr>
                            <td colspan="15" style="padding: 20px; text-align: center; color: #7c7c8a;">No items found inside the database yet. Use the forge above!</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($itemsResult)): ?>
                            <tr style="border-bottom: 1px solid #29292e;">
                                <td style="padding: 10px 6px; color: #7c7c8a;"><?= $row['item_id'] ?></td>
                                <td style="padding: 10px 6px;">
                                    <img src="<?= htmlspecialchars($row['sprite']) ?>" alt="icon" style="width: 32px; height: 32px; image-rendering: pixelated; background: #121214; border-radius: 4px; padding: 2px; border: 1px solid #323238; display: block;">
                                </td>
                                <td style="padding: 10px 6px; font-weight: bold; color: #fff;"><?= htmlspecialchars($row['item_name']) ?></td>
                                <td style="padding: 10px 6px;"><span style="background: #29292e; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;"><?= htmlspecialchars($row['item_type']) ?></span></td>
                                <td style="padding: 10px 6px; font-size: 0.85rem;">
                                    <span style="color: #ffcc00; display:block;">🪙 B: <?= $row['buy_price'] ?></span>
                                    <span style="color: #e76f51; display:block;">🪙 S: <?= $row['sell_price'] ?></span>
                                </td>

                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['att_str'] > 0 ? '#f75a68' : '#4e4e5a' ?>;"><?= (int)($row['att_str'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['att_def'] > 0 ? '#48cae4' : '#4e4e5a' ?>;"><?= (int)($row['att_def'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['att_dex'] > 0 ? '#ffb703' : '#4e4e5a' ?>;"><?= (int)($row['att_dex'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['att_int'] > 0 ? '#5757df' : '#4e4e5a' ?>;"><?= (int)($row['att_int'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['att_fth'] > 0 ? '#ff9f1c' : '#4e4e5a' ?>;"><?= (int)($row['att_fth'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['att_heal'] > 0 ? '#04d361' : '#4e4e5a' ?>;"><?= (int)($row['att_heal'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['att_max_hp'] > 0 ? '#00b4d8' : '#4e4e5a' ?>;"><?= (int)($row['att_max_hp'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['req_str'] > 0 ? '#ff9f1c' : '#4e4e5a' ?>; border-left: 1px solid #323238;"><?= (int)($row['req_str'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['req_def'] > 0 ? '#ff9f1c' : '#4e4e5a' ?>;"><?= (int)($row['req_def'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['req_dex'] > 0 ? '#ff9f1c' : '#4e4e5a' ?>;"><?= (int)($row['req_dex'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['req_int'] > 0 ? '#ff9f1c' : '#4e4e5a' ?>;"><?= (int)($row['req_int'] ?? 0) ?></td>
                                <td style="padding: 10px 6px; text-align: center; font-weight: bold; color: <?= $row['req_fth'] > 0 ? '#ff9f1c' : '#4e4e5a' ?>;"><?= (int)($row['req_fth'] ?? 0) ?></td>

                                <td style="padding: 10px 6px; color: #b5b5c3; font-size: 0.85rem; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($row['item_desc'] ?? '') ?>">
                                    <?= htmlspecialchars($row['item_desc'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>

</html>