<?php
// process_manage_pools.php
require 'conn.php';
global $conn;

$action = isset($_GET['action']) ? $_GET['action'] : '';
$map_level = isset($_GET['map_level']) ? (int)$_GET['map_level'] : 1;

if ($action === 'add') {
    $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
    $shop_pool_id = isset($_GET['shop_pool_id']) ? (int)$_GET['shop_pool_id'] : 0;

    if ($item_id > 0 && $shop_pool_id > 0) {
        // Double-check to make sure this item isn't somehow linked already
        $check = mysqli_query($conn, "SELECT shop_item_id FROM shop_item WHERE item_id = $item_id AND shop_pool_id = $shop_pool_id");
        if (mysqli_num_rows($check) === 0) {
            // Insert directly using your actual column layout names
            $query = "INSERT INTO shop_item (item_id, shop_pool_id) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ii", $item_id, $shop_pool_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    header("Location: admin_manage_pools.php?map_level=" . $map_level . "&success=added");
    exit;

} elseif ($action === 'remove') {
    $shop_item_id = isset($_GET['shop_item_id']) ? (int)$_GET['shop_item_id'] : 0;

    if ($shop_item_id > 0) {
        // Clean out item link assignment connection row safely
        $query = "DELETE FROM shop_item WHERE shop_item_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $shop_item_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: admin_manage_pools.php?map_level=" . $map_level . "&success=removed");
    exit;
}

// Fallback protection redirection
header("Location: admin_manage_pools.php");
exit;