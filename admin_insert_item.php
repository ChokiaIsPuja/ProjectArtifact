<?php
// process_insert_item.php
require 'conn.php'; // Adjust path to your database connection file
global $conn;

// Intercept non-POST routing request intrusions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_insert_item.php");
    exit;
}

// 1. Gather Master Item Data (Matching 'item' columns from image_0ab988.png)
$itemName = trim($_POST['item_name']);
$itemType = trim($_POST['item_type']);
$itemDesc = trim($_POST['item_desc']);
$price    = (int)$_POST['price'];
$sprite   = trim($_POST['sprite']); 

// 2. Gather Attribute Modifiers (Matching 'item_attributes' columns from image_0ab988.png)
$attAtk   = (int)$_POST['att_atk'];
$attDef   = (int)$_POST['att_def'];
$attHp    = (int)$_POST['att_hp'];
$attMaxHp = (int)$_POST['att_max_hp'];
$attSpd   = (int)$_POST['att_spd'];

// Validation processing step
if (empty($itemName) || empty($itemType)) {
    header("Location: admin_insert_item.php?status=missing");
    exit;
}

// Begin database insertion pipeline block 
mysqli_begin_transaction($conn);

try {
    // STEP A: Insert stats into item_attributes table
    $attrQuery = "INSERT INTO item_attributes (att_atk, att_def, att_hp, att_max_hp, att_spd) 
                  VALUES (?, ?, ?, ?, ?)";
    $attrStmt = mysqli_prepare($conn, $attrQuery);
    mysqli_stmt_bind_param($attrStmt, "iiiii", $attAtk, $attDef, $attHp, $attMaxHp, $attSpd);
    mysqli_stmt_execute($attrStmt);
    
    // Capture generated primary key
    $attributeId = mysqli_insert_id($conn);
    mysqli_stmt_close($attrStmt);

    // STEP B: Insert item into item table linked to attributes row
    $itemQuery = "INSERT INTO item (id_item_attributes, item_name, item_type, item_desc, price, sprite) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    $itemStmt = mysqli_prepare($conn, $itemQuery);
    mysqli_stmt_bind_param($itemStmt, "isssis", $attributeId, $itemName, $itemType, $itemDesc, $price, $sprite);
    mysqli_stmt_execute($itemStmt);
    mysqli_stmt_close($itemStmt);

    // Commit database state permanently if everything checks out perfectly
    mysqli_commit($conn);
    
    header("Location: admin.php?status=success");
    exit;

} catch (Exception $e) {
    // Structural rollback fallback execution to preserve data structure health
    mysqli_rollback($conn);
    
    // Pass raw error context message safely via url encode
    $errMessage = urlencode($e->getMessage());
    header("Location: admin.php?status=error&err=" . $errMessage);
    exit;
}