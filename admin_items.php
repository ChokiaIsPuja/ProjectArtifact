<?php
// admin_insert_item.php
require 'conn.php'; // Adjust path to your database connection file
global $conn;

$message = "";
$messageType = "";

// Check URL query parameters for backend processing result messages
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

// Fetch all items and their joined attribute modifiers to display in the table below
$allQuery = "SELECT i.*, ia.att_atk, ia.att_def, ia.att_spd, ia.att_hp, ia.att_max_hp 
             FROM item i
             LEFT JOIN item_attributes ia ON i.id_item_attributes = ia.id_item_attributes
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

    <div style="max-width: 1100px; margin: 20px auto; background: #202024; padding: 30px; border-radius: 6px; border: 1px solid #323238;">
        
        <h2 style="margin-top: 0; color: #5757df; border-bottom: 1px solid #323238; padding-bottom: 10px;">⚔️ Item Insertion Panel</h2>
        
        <?php if (!empty($message)): ?>
            <div style="padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; 
                        background: <?= $messageType === 'success' ? '#123b24' : '#421616' ?>; 
                        color: <?= $messageType === 'success' ? '#04d361' : '#f75a68' ?>;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form action="process_insert_item.php" method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                
                <div>
                    <h3 style="color: #ffcc00; margin-top: 0; margin-bottom: 15px;">1. Base Specifications</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; margin-bottom:5px;">Item Name *</label>
                            <input type="text" name="item_name" required style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px;">Item Type *</label>
                            <select name="item_type" required style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px;">
                                <option value="weapon">Weapon</option>
                                <option value="helmet">Helmet</option>
                                <option value="armor">Armor</option>
                                <option value="boots">Boots</option>
                                <option value="Accessory">Accessory</option>
                                <option value="Consumable">Consumable</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px;">Item Description</label>
                        <textarea name="item_desc" rows="3" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; resize: none; box-sizing: border-box;"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label style="display:block; margin-bottom:5px;">Gold Price</label>
                            <input type="number" name="price" value="0" min="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px;">Sprite URL Path</label>
                            <input type="text" name="sprite" value="../asset/sprites/items/default.png" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                        </div>
                    </div>
                </div>

                <div style="border-left: 1px solid #323238; padding-left: 20px;">
                    <h3 style="color: #ffcc00; margin-top: 0; margin-bottom: 15px;">2. Combat Attribute Modifiers</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; margin-bottom:5px;">Bonus ATK</label>
                            <input type="number" name="att_atk" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px;">Bonus DEF</label>
                            <input type="number" name="att_def" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; margin-bottom:5px;">Bonus SPD</label>
                            <input type="number" name="att_spd" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:5px;">Heal HP (Consumables)</label>
                            <input type="number" name="att_hp" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                        </div>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label style="display:block; margin-bottom:5px;">Max HP Structural Boost</label>
                        <input type="number" name="att_max_hp" value="0" style="width:100%; padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; box-sizing: border-box;">
                    </div>

                    <button type="submit" style="background:#5757df; color:white; font-weight:bold; border:none; padding:12px; width:100%; border-radius:4px; cursor:pointer; font-size:1rem; font-family: monospace;">
                        Forge Item & Commit Data
                    </button>
                </div>

            </div>
        </form>
    </div>

    <div style="max-width: 1100px; margin: 30px auto; background: #202024; padding: 30px; border-radius: 6px; border: 1px solid #323238;">
        <h2 style="margin-top: 0; color: #2a9d8f; border-bottom: 1px solid #323238; padding-bottom: 10px;">📦 Live Item Registry Database</h2>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #323238; color: #b5b5c3;">
                        <th style="padding: 12px 8px;">ID</th>
                        <th style="padding: 12px 8px;">Sprite</th>
                        <th style="padding: 12px 8px;">Item Name</th>
                        <th style="padding: 12px 8px;">Type</th>
                        <th style="padding: 12px 8px;">Price</th>
                        <th style="padding: 12px 8px; text-align: center; color: #f75a68;">ATK</th>
                        <th style="padding: 12px 8px; text-align: center; color: #48cae4;">DEF</th>
                        <th style="padding: 12px 8px; text-align: center; color: #ffb703;">SPD</th>
                        <th style="padding: 12px 8px; text-align: center; color: #04d361;">HEAL</th>
                        <th style="padding: 12px 8px; text-align: center; color: #00b4d8;">MAX HP</th>
                        <th style="padding: 12px 8px;">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($itemsResult) === 0): ?>
                        <tr>
                            <td colspan="11" style="padding: 20px; text-align: center; color: #7c7c8a;">No items found inside the database yet. Use the forge above!</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($itemsResult)): ?>
                            <tr style="border-bottom: 1px solid #29292e; background: PHP_ROUND_HALF_UP;">
                                <td style="padding: 10px 8px; color: #7c7c8a;"><?= $row['item_id'] ?></td>
                                <td style="padding: 10px 8px;">
                                    <img src="<?= htmlspecialchars($row['sprite']) ?>" alt="icon" style="width: 32px; height: 32px; image-rendering: pixelated; background: #121214; border-radius: 4px; padding: 2px; border: 1px solid #323238; display: block;">
                                </td>
                                <td style="padding: 10px 8px; font-weight: bold; color: #fff;"><?= htmlspecialchars($row['item_name']) ?></td>
                                <td style="padding: 10px 8px;"><span style="background: #29292e; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;"><?= htmlspecialchars($row['item_type']) ?></span></td>
                                <td style="padding: 10px 8px; color: #ffcc00; font-weight: bold;">🪙 <?= $row['price'] ?></td>
                                
                                <td style="padding: 10px 8px; text-align: center; font-weight: bold; color: <?= $row['att_atk'] > 0 ? '#f75a68' : '#4e4e5a' ?>;"><?= $row['att_atk'] ?></td>
                                <td style="padding: 10px 8px; text-align: center; font-weight: bold; color: <?= $row['att_def'] > 0 ? '#48cae4' : '#4e4e5a' ?>;"><?= $row['att_def'] ?></td>
                                <td style="padding: 10px 8px; text-align: center; font-weight: bold; color: <?= $row['att_spd'] > 0 ? '#ffb703' : '#4e4e5a' ?>;"><?= $row['att_spd'] ?></td>
                                <td style="padding: 10px 8px; text-align: center; font-weight: bold; color: <?= $row['att_hp'] > 0 ? '#04d361' : '#4e4e5a' ?>;"><?= $row['att_hp'] ?></td>
                                <td style="padding: 10px 8px; text-align: center; font-weight: bold; color: <?= $row['att_max_hp'] > 0 ? '#00b4d8' : '#4e4e5a' ?>;"><?= $row['att_max_hp'] ?></td>
                                
                                <td style="padding: 10px 8px; color: #b5b5c3; font-size: 0.85rem; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($row['item_desc']) ?>">
                                    <?= htmlspecialchars($row['item_desc']) ?>
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