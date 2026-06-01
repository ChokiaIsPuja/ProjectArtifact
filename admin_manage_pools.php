<?php
// admin_manage_pools.php
require 'conn.php';
global $conn;

// Determine which pool/map level we are viewing (defaults to Map Level 1)
$selected_map_level = isset($_GET['map_level']) ? (int)$_GET['map_level'] : 1;

// 1. Fetch or automatically ensure a shop_pool exists for this map level
$pool_check_query = "SELECT shop_pool_id FROM shop_pool WHERE map_level = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $pool_check_query);
mysqli_stmt_bind_param($stmt, "i", $selected_map_level);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if ($pool_row = mysqli_fetch_assoc($res)) {
    $current_pool_id = $pool_row['shop_pool_id'];
} else {
    // If no pool entry exists for this level yet, create it dynamically
    mysqli_query($conn, "INSERT INTO shop_pool (map_level) VALUES ($selected_map_level)");
    $current_pool_id = mysqli_insert_id($conn);
}
mysqli_stmt_close($stmt);

// 2. Fetch all items CURRENTLY assigned to this specific pool
$assigned_query = "SELECT si.shop_item_id, i.item_id, i.item_name, i.item_type, i.sprite, i.price 
                   FROM shop_item si
                   JOIN item i ON si.item_id = i.item_id
                   WHERE si.shop_pool_id = ?
                   ORDER BY i.item_name ASC";
$stmt = mysqli_prepare($conn, $assigned_query);
mysqli_stmt_bind_param($stmt, "i", $current_pool_id);
mysqli_stmt_execute($stmt);
$assigned_result = mysqli_stmt_get_result($stmt);

// Track IDs of assigned items so we can exclude them from the "available" list
$assigned_ids = [];
$assigned_items_html = "";

while ($row = mysqli_fetch_assoc($assigned_result)) {
    $assigned_ids[] = $row['item_id'];
    $assigned_items_html .= "
    <tr style='border-bottom: 1px solid #323238;'>
        <td style='padding:10px;'><img src='{$row['sprite']}' style='width:24px;height:24px;image-rendering:pixelated;'></td>
        <td style='padding:10px; font-weight:bold;'>[" . htmlspecialchars($row['item_type']) . "] " . htmlspecialchars($row['item_name']) . "</td>
        <td style='padding:10px; color:#ffcc00;'>🪙 {$row['price']}</td>
        <td style='padding:10px; text-align:right;'>
            <a href='process_manage_pools.php?action=remove&shop_item_id={$row['shop_item_id']}&map_level={$selected_map_level}' 
               style='background:#f75a68; color:white; padding:4px 8px; text-decoration:none; border-radius:4px; font-size:0.8rem;'>Remove</a>
        </td>
    </tr>";
}
mysqli_stmt_close($stmt);

// 3. Fetch all other game items AVAILABLE to be added (excluding already assigned ones)
$not_in_clause = !empty($assigned_ids) ? "WHERE item_id NOT IN (" . implode(',', $assigned_ids) . ")" : "";
$available_query = "SELECT item_id, item_name, item_type, sprite, price FROM item $not_in_clause ORDER BY item_name ASC";
$available_result = mysqli_query($conn, $available_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pool Distribution Dashboard</title>
</head>
<body style="background: #121214; color: #e1e1e6; font-family: monospace; padding: 20px;">

    <div style="max-width: 1200px; margin: 20px auto; background: #202024; padding: 30px; border-radius: 6px; border: 1px solid #323238;">
        
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #323238; padding-bottom:15px; margin-bottom:25px;">
            <h2 style="margin:0; color:#5757df;">🗺️ Shop Pool Distribution Panel</h2>
            <div>
                <label style="margin-right:10px; color:#ffcc00;">Select Target Map Pool:</label>
                <select onchange="location = this.value;" style="padding:8px; background:#121214; border:1px solid #323238; color:#fff; border-radius:4px; font-weight:bold;">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <option value="admin_manage_pools.php?map_level=<?= $i ?>" <?= $selected_map_level === $i ? 'selected' : '' ?>>Map Level Tier <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div style="background:#123b24; color:#04d361; padding:10px; border-radius:4px; margin-bottom:20px; font-weight:bold;">
                <?= $_GET['success'] == 'added' ? '✅ Item appended to current map stock.' : '❌ Item removed from map stock execution pool.' ?>
            </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
            
            <div style="background:#19191c; border:1px solid #323238; border-radius:6px; padding:20px;">
                <h3 style="color:#2a9d8f; margin-top:0; border-bottom:1px solid #323238; padding-bottom:10px;">📋 Current Stock: Map Level <?= $selected_map_level ?></h3>
                <div style="max-height: 500px; overflow-y: auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.9rem;">
                        <tbody>
                            <?= empty($assigned_items_html) ? "<tr><td style='padding:20px; color:#7c7c8a; text-align:center;'>This shop pool is empty! Add items from the right panel.</td></tr>" : $assigned_items_html ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="background:#19191c; border:1px solid #323238; border-radius:6px; padding:20px;">
                <h3 style="color:#ffb703; margin-top:0; border-bottom:1px solid #323238; padding-bottom:10px;">📦 Global Item Database Vault</h3>
                <div style="max-height: 500px; overflow-y: auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.9rem;">
                        <tbody>
                            <?php if(mysqli_num_rows($available_result) === 0): ?>
                                <tr><td style="padding:20px; color:#7c7c8a; text-align:center;">No further vault items are available to add.</td></tr>
                            <?php else: ?>
                                <?php while($item = mysqli_fetch_assoc($available_result)): ?>
                                    <tr style="border-bottom: 1px solid #323238;">
                                        <td style="padding:10px;"><img src="<?= $item['sprite'] ?>" style="width:24px;height:24px;image-rendering:pixelated;"></td>
                                        <td style="padding:10px; font-weight:bold;">[<?= htmlspecialchars($item['item_type']) ?>] <?= htmlspecialchars($item['item_name']) ?></td>
                                        <td style="padding:10px; color:#ffcc00;">🪙 <?= $item['price'] ?></td>
                                        <td style="padding:10px; text-align:right;">
                                            <a href="process_manage_pools.php?action=add&item_id=<?= $item['item_id'] ?>&shop_pool_id=<?= $current_pool_id ?>&map_level=<?= $selected_map_level ?>" 
                                               style="background:#5757df; color:white; padding:4px 8px; text-decoration:none; border-radius:4px; font-size:0.8rem;">+ Add to Pool</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div style="margin-top:25px; border-top:1px solid #323238; padding-top:15px; text-align:left;">
            <a href="admin_insert_item.php" style="color:#5757df; text-decoration:none; font-weight:bold;">← Return to Master Item Forge Creator Panel</a>
        </div>
    </div>

</body>
</html>